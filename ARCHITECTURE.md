# Architecture review — Mizuki Booking

Design review of v3.0.0, and what changed as a result. Written for whoever maintains this next.

---

## 1. What the system has to do

**Functional**

- Many sessions a day, each with its own time, length and participant limit
- A rolling 2+ month schedule that maintains itself
- Per-class reschedule and cancellation rules (72h for Fresh Flower / Ikebana, 24h for Preserved Flower / IFDA)
- Studio closures that remove dates from sale
- Course packages of N sessions, extendable, with an audit trail
- Paid workshops through the existing WooCommerce shop
- Registration approval where the studio wants to vet students
- Student accounts, self-service reschedule, and a studio control panel on the front end

**Non-functional — the real numbers**

This is a single flower studio, not a marketplace. Sizing honestly matters more than sizing big:

| | Estimate |
|---|---|
| Sessions | ~15/week → ~800/year |
| Bookings | ~60/week → ~3,000/year |
| Students | low thousands over the plugin's life |
| Peak concurrency | a handful — spikes when a popular class opens |
| Availability | shared hosting; occasional slow requests are survivable |

**Consequence:** correctness under small concurrency matters; throughput does not. A single MySQL instance is ample for years. Anything more — a queue, a cache tier, a separate service — would be cost with no return.

**Constraints:** must live inside WordPress 6.x on Astra + Elementor + WooCommerce, on shared hosting, maintainable by a small agency.

---

## 2. Shape of the system

```
        ┌───────────── Front end ─────────────┐
        │  Calendar   Student area   Studio   │
        │ [calendar]  [dashboard]  [manage]   │
        └───────┬─────────────┬───────────┬───┘
                │ REST        │ REST      │ admin-post
                ▼             ▼           ▼
        ┌──────────────────────────────────────┐
        │  Domain layer (plain PHP classes)    │
        │  Sessions · Bookings · Enrollments   │
        │  ClassTypes · Blackouts · Students   │
        └───────┬──────────────────────┬───────┘
                │                      │
                ▼                      ▼
        ┌──────────────┐      ┌────────────────┐
        │ 7 custom     │      │ WP / Woo:      │
        │ MySQL tables │      │ users, orders  │
        └──────────────┘      └────────────────┘
                ▲
                │  wp-cron: reminders, horizon top-up, hold sweep
```

**Two decisions worth defending:**

**Custom tables, not custom post types.** Bookings are rows with a dozen typed columns, queried by ranges and joins (`session_date BETWEEN`, `SUM(seats) GROUP BY session`). As CPTs that becomes a `postmeta` self-join per field, unindexable and slow by 2,000 rows. Cost: no free WP admin UI, no free REST — both hand-written here. Worth it.

**One handler for admin and front-end writes.** `MZK_Admin::handle()` serves both wp-admin forms and `[mizuki_manage]`, differing only in where they redirect. One capability check, one nonce check, one set of rules. The alternative — a second REST surface for the front end — doubles the code that can be wrong about permissions.

---

## 3. The invariant everything protects

> **A session never has more occupying bookings than its effective capacity.**

`effective = max(0, capacity + capacity_adjustment)`, and *occupying* is `confirmed | attended | pending | awaiting_approval`.

That set is the subtle part. A seat is occupied while it is merely *held* — during checkout, or while a registration waits for approval — otherwise two students could take the same last place, and the studio would find out on the day. `cancelled`, `declined`, `expired` and `moved` release it.

Capacity is never denormalised into a counter column. It is always `SUM(seats)` over occupying rows, which cannot drift out of sync with the bookings themselves. At this scale the query is trivial; at 100× it would want a counter, and that is the first thing I would revisit.

---

## 4. Findings

Six issues. Four were worth fixing, two were noted and left.

### Fixed

**F1 — Unpaid checkouts created accounts and e-mailed strangers.** *Correctness, user-facing.*
Seat holds ran the same account-creation path as real bookings, so starting a checkout and abandoning it left an orphan WordPress user behind and sent a *"welcome, set your password"* e-mail to someone who never paid.
→ Holds no longer create accounts. The account is created when payment confirms, in `MZK_Woo::confirm_bookings()`, and the id written back to the booking.

**F2 — A course package could be over-spent.** *Data integrity, silent.*
Seats were locked; package balances were not. Two bookings made in the same moment with one session left each read `sessions_left = 1` and both spent it, leaving `sessions_used > sessions_total`. Rare, invisible when it happened, and it costs the studio a free class.
→ The balance is now re-read under a per-enrollment advisory lock, held across the check and the insert. Locks are always taken **package first, then session**, so two requests cannot grab them in opposite orders and deadlock.

**F3 — A public endpoint wrote on every page view.** *Scaling.*
The calendar swept expired holds on each load to keep availability honest between cron runs — an `UPDATE` per visitor, including bots.
→ Throttled to one sweep a minute via a transient. Availability is still fresh to within a minute; write load is now flat regardless of traffic.

**F4 — Missing composite indexes.** *Scaling, cheap.*
The two hottest queries filter on column pairs — `(session_id, status)` for seat counts, `(enrollment_id, status)` for balances — with only single-column indexes available. The hold sweep scanned by `(status, hold_expires_at)`.
→ All three added. `dbDelta` applies them on upgrade.

Also tightened: uninstall now clears the generated-pages and demo-content options, the transient, the cron hooks and the student *role* — while deliberately **leaving student accounts alone**, since deleting people's logins on an uninstall would be destructive and unexpected. And the setup wizard's "is this shortcode already on a page?" check no longer matches `[mizuki_calendar_archive]` when looking for `[mizuki_calendar]`.

### Noted, not fixed

**N1 — `adjust_capacity()` and session edits take no lock.** A booking landing in the same millisecond as the studio pressing "−" could slip past the `effective >= taken` guard. The window is milliseconds, one of the two actors is a human in wp-admin, and the failure is one extra student in a class the studio is actively looking at. Locking it would add contention to the common path to fix a race nobody will hit. Revisit if the studio ever scripts capacity changes.

**N2 — REST nonces and full-page caching.** The booking nonce is embedded in the page. Under a full-page cache with a TTL beyond 24 hours, a visitor could receive an expired nonce and see *"your session expired, please refresh"* on their first booking attempt. Not a problem today — the site has no page cache — but it is the first thing to check if one is added. The fix at that point is to fetch the nonce over REST at page load rather than baking it in.

---

## 5. Failure behaviour

| If this fails | What happens | Recovery |
|---|---|---|
| Payment never completes | Seat held, then released | Automatic, within the hold window |
| `GET_LOCK` unavailable | Falls back to an unlocked check | Degrades to the pre-lock race; never blocks a booking |
| wp-cron doesn't run | Reminders late; horizon stops growing; holds linger | Real server cron (documented in SETUP.md); the calendar sweeps holds itself |
| `wp_mail()` fails | Booking is still made; the student sees on-screen confirmation | Studio can resend from the Bookings screen |
| Session deleted with bookings | Refused unless forced | Forcing cancels the bookings explicitly |
| Two admins approve at once | Second sees "not awaiting approval" | Status guard in `approve()` |

The through-line: **money and seats are never lost silently.** Every path that can't complete either refuses loudly or leaves an order note.

---

## 6. What I would revisit, and when

| Trigger | Change |
|---|---|
| >50k bookings | Denormalise seat counts into `sessions.seats_taken`, maintained transactionally |
| A page cache is added | Fetch the REST nonce at runtime instead of embedding it |
| Multiple studios / locations | Add `location_id` to sessions and class types; today's single-tenant assumption is baked in |
| Students self-serve more | The approval queue becomes a state machine worth modelling explicitly, rather than a status column |
| Instructors get logins | Needs a real capability model; `manage_options` is currently the only gate |

---

## 7. Verdict

The design is sound for what it has to do. It has one clear invariant, protects it in one place, and stores data in a shape that can be queried honestly.

It is *not* "perfect" in the abstract, and shouldn't try to be — several deliberate simplifications (single tenant, single instructor, counters computed not cached) trade scale the studio will never need for code the agency can maintain. The two things most likely to bite are both listed above, both cheap to fix, and neither is a problem at today's volume.
