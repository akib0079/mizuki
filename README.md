# mizuki

Booking and membership plugin for [mizuki.com.sg](https://mizuki.com.sg/) — a WordPress + WooCommerce site for Mizuki Flower Studio (Astra theme, Elementor Pro).

One plugin owns the whole journey: **calendar → payment → course packages → the student's own account area**, so seats have a single source of truth and classes cannot be oversold.

| | |
|---|---|
| Plugin folder | [`mizuki-booking/`](mizuki-booking/) |
| Version | 2.0.0 |
| Requires | WordPress 6.0+, PHP 7.4+, WooCommerce (optional, for paid bookings) |
| Full docs | [`mizuki-booking/README.md`](mizuki-booking/README.md) |
| Setup checklist | **[`SETUP.md`](SETUP.md)** ← start here |

---

## What it does

Every requirement the client listed, mapped to a feature:

| Client asked for | Status |
|---|---|
| Multiple sessions per day, varied times and durations, editable any time | ✅ |
| Participant limit per session, changeable, with seats held/blocked for chat sign-ups | ✅ |
| At least 2 months of schedule visible | ✅ (auto-tops up daily) |
| Rescheduling — open for Preserved Flower & IFDA, blocked within 3 days for Fresh Flower & Ikebana | ✅ (per-class rule, enforced server-side) |
| Block out dates when the studio is closed | ✅ |
| Course session extension for IFDA & Preserved Flower | ✅ (with audit trail) |
| Auto confirmation after sign-up | ✅ |
| Auto reminder 2 days before class | ✅ |
| Paid workshops through the existing shop | ✅ (WooCommerce) |
| Student self-service area | ✅ (My Account) |

---

## Shortcodes

Put these on any page or in any Elementor shortcode widget.

### Booking calendar

| Shortcode | What it shows |
|---|---|
| `[mizuki_calendar]` | Full calendar, every class, with a class filter |
| `[mizuki_calendar class="ikebana"]` | One class only |
| `[mizuki_calendar class="preserved-flower"]` | One class only |
| `[mizuki_calendar months="3"]` | More months than the default (minimum 2) |
| `[mizuki_calendar view="list"]` | Plain chronological list instead of a month grid |
| `[mizuki_calendar showfilter="no"]` | Hide the class dropdown |

Attributes combine: `[mizuki_calendar class="ikebana" months="3" view="list"]`

Class slugs are set per class under **Bookings → Classes & Rules**. The four seeded ones are:

`fresh-flower` · `ikebana` · `preserved-flower` · `ifda`

### Student area

| Shortcode | What it shows |
|---|---|
| `[mizuki_my_bookings]` | The student's bookings, with reschedule and cancel |
| `[mizuki_my_courses]` | Course package balance, sessions used, expiry |

`[mizuki_my_bookings]` works two ways: logged-in students see all their bookings; anyone arriving from the link in a confirmation e-mail can manage that one booking without an account.

Both also appear automatically as **My Classes** and **My Courses** tabs in WooCommerce → My Account.

---

## Where things live in the admin

A single **Bookings** menu:

| Screen | Use it for |
|---|---|
| **Schedule** | Month view of everything; quick −/+ to hold or open places |
| **Sessions** | Add/edit single sessions; weekly pattern + generator |
| **Bookings** | Filter, move, cancel, mark attended, add a chat booking manually |
| **Course Packages** | Balances, extend sessions or expiry, full history |
| **Blocked Dates** | Studio closures |
| **Classes & Rules** | Per-class limits, durations and reschedule cutoffs |
| **Settings** | Months ahead, reminders, payments, e-mail templates |

---

## Still to scope

**IFDA membership platform** (item 5b on the client's page list). What's built covers course balances and the student's class area. If "membership platform" means lesson materials, certificates, progress tracking or instructor sign-off, that is a separate build and needs the client's own definition in writing before it can be quoted.
