# Mizuki Booking

Booking calendar plugin for [mizuki.com.sg](https://mizuki.com.sg/). Built to the studio's stated requirements — every one of the eight asks maps to a concrete feature below.

## Requirement → implementation

| # | Client requirement | Where it lives |
|---|---|---|
| 1 | Multiple sessions per day, varied times/durations, editable | `MZK_Sessions` + **Bookings → Sessions**. Each session row is independent: date, start time, duration in minutes, own limit. Weekly patterns generate them in bulk; any generated session stays individually editable. |
| 2 | Participant limit per session, changeable, plus holding/blocking seats for chat sign-ups | `capacity` (the class limit) and `capacity_adjustment` (a signed manual override) on each session. `− / +` buttons on the schedule and session list change places offered online without touching the real limit. Manual bookings can be entered for chat students, which take a seat properly. |
| 3 | At least 2 months of schedule visible | Settings → *Months shown ahead* (minimum 2, default 3). `MZK_Sessions::ensure_horizon()` runs daily so the horizon never shrinks. The front-end calendar renders every month in range. |
| 4 | Rescheduling — open for Preserved Flower/IFDA, blocked within 3 days for Fresh Flower/Ikebana | Per-class `reschedule_cutoff_hours`. Seeded at **72 h** for Fresh Flower and Ikebana (a Saturday class locks on Wednesday) and **24 h** for Preserved Flower and IFDA. Enforced in `MZK_Class_Types::can_reschedule()`, on both the source and target session, server-side. |
| 5 | Block out dates | `MZK_Blackouts`. Saving a blocked range immediately closes the sessions inside it, hides those dates on the calendar, and warns you about bookings already in the range. Generation skips blocked dates. |
| 6 | Course session extension (IFDA & Preserved Flower) | `MZK_Enrollments`: package of N sessions with optional expiry. Each confirmed booking draws one down. **Extend** adds sessions and/or pushes the expiry, writing an audit-logged entry (who, when, why). |
| 7 | Auto confirmation after sign-up | `MZK_Mailer::send_confirmation()` fires inside `MZK_Bookings::create()`. Template editable in Settings. |
| 8 | Auto reminder 2 days before class | `MZK_Cron::run_reminders()` — hourly check, sends from the configured hour on the day that is N days out (default 2). Each booking is stamped once, so it can never double-send. |

## Install

1. Copy `mizuki-booking/` into `wp-content/plugins/`.
2. Activate. Activation creates seven tables and seeds the four classes (Fresh Flower, Ikebana, Preserved Flower, IFDA) with the reschedule rules above.
3. **Bookings → Sessions → Weekly pattern** — add the sessions run each week, then *Generate sessions*.
4. Create two pages:
   - Booking page: `[mizuki_calendar]`
   - Manage page: `[mizuki_my_bookings]`
5. **Bookings → Settings → Pages** — select both. The manage page is what confirmation e-mails link to.
6. Confirm the site timezone is *Asia/Singapore* under Settings → General. All cutoffs are computed in site time.

## Admin screens

- **Schedule** — month grid, every session with `booked/limit`, quick −/+ seat controls, close/open, jump to participants.
- **Sessions** — full list with filters, single-session editor, weekly pattern + generator.
- **Bookings** — filter by status/class/date/search; move a booking to another session, cancel, mark attended/no-show, resend confirmation, and add bookings manually for chat sign-ups (with optional overbooking and silent mode).
- **Course Packages** — balances with progress bars, extension form, extension history, and the list of sessions consumed.
- **Blocked Dates** — closure ranges, studio-wide or per class, with an affected-bookings count.
- **Classes & Rules** — per-class defaults and the reschedule/cancel policy.
- **Settings** — horizon, reminder timing, e-mail templates with merge tags, test send, manual reminder run, shortcode reference.

## Student flow

1. Picks a date on the calendar → sees that day's sessions with places left.
2. Books with name, e-mail, phone → seat is taken under a database lock (no double-booking of the last place) → confirmation e-mail with a private manage link.
3. Two days before: reminder e-mail.
4. Manage link → reschedule (only to sessions allowed by that class's rule) or cancel. Blocked attempts explain why, e.g. *"Ikebana bookings can no longer be changed — rescheduling closes 3 days before the class starts."*

## The all-in-one flow

One plugin owns the whole journey — calendar, payment, course packages and the student's own area — so seats have a single source of truth.

```
Student picks a session on a product page
        │
        ▼
Add to cart ── validated against live capacity, blackouts and session status
        │
        ▼
Order placed ── seat held (status: pending) for N minutes; the place is
        │        no longer offered to anyone else
        ▼
Payment ────── booking confirmed + confirmation e-mail
        │      course-package products grant their sessions here
        ▼
2 days before ── reminder e-mail
        │
        ▼
My Account ── student reschedules or cancels under that class's own rules
```

Unpaid orders release their seat automatically, so an abandoned checkout never blocks a class. Cancelled, failed and refunded orders release too.

### Product setup

Edit any product → **Mizuki Booking** tab:

| Behaviour | What it does | Use for |
|---|---|---|
| *None* | ordinary product | flowers, vases |
| **Session booking** | student picks a date on the product page; seat held, then confirmed on payment | Regular + Seasonal Workshops, Ikebana, Naturepresso, AIFE |
| **Course package** | purchase grants N sessions (with optional validity) | IFDA, Preserved Flower |

A **zero-priced** session-booking product draws one session from the student's package instead of charging — that's the "book a session from my course" product for IFDA and Preserved Flower students. A paid session never touches their package balance.

### Student area

WooCommerce **My Account** gains two tabs:

- **My Classes** — upcoming and past bookings, reschedule and cancel within the class rules.
- **My Courses** — package balance, sessions used, expiry, and a direct link to book the next one.

Both are also available as shortcodes (`[mizuki_my_bookings]`, `[mizuki_my_courses]`) if the studio prefers its own page.

### Migrating the existing workshop products

The live workshop products currently carry the session as WooCommerce **variation attributes** (`dates`, `session`). Those dropdowns don't know about participant limits, so they can oversell. To move a product across:

1. Add its sessions under **Bookings → Sessions** (or a weekly pattern).
2. Change the product from *Variable* to *Simple* and remove the `dates` / `session` attributes.
3. Set **Mizuki Booking → Session booking** and pick the class.

Do them one at a time and keep the old product live until the new one is checked out end to end.

## Design system

The front end is matched to the live mizuki.com.sg build (WordPress 7.0.2, Astra 4.13.8, Elementor Pro 4.2.1, ElementsKit, WooCommerce 10.9.4), verified against the real page rather than by eye:

| Token | Value | Source |
|---|---|---|
| Primary (navy) | `#2D5778` | Elementor kit `--e-global-color-primary` — nav links, section fills |
| Action (teal) | `#3F827A` | `--e-global-color-secondary` — every button on the site |
| Text | `#162A3C` | `--e-global-color-text` |
| Muted | `#555D60` · surface `#F0F5FA` · border `#e7e7e7` · rule `#bcbcbc` | Astra globals |
| Body type | Poppins 300, fluid 14→18px, line-height 1.65 | Astra body |
| Headings | Radio Canada 700, UPPERCASE, 2px tracking, 25px rule beneath | site section titles |
| Shape | square — `border-radius: 0` everywhere | site buttons, inputs, cards |
| Buttons | teal fill, white text, `12px 24px`, weight 300 | Elementor button widget |
| Fields | underline only, transparent fill | the studio's enquiry form |

Colours are read from the Elementor kit variables at runtime with the current values as fallbacks, so a future palette change in the site kit carries into the plugin automatically.

Three conflicts with the theme were found and fixed during the check:

1. **Astra forces every `<button>`** to `background-color: #3F827A !important; border-color: #3F827A !important`. Secondary buttons rendered navy-on-teal (unreadable), so the plugin's own button rules carry a matching, deliberately scoped `!important`.
2. **Theme button weight 700** overrode the plugin's 300; button selectors are now scoped under `.mzk-calendar` / `.mzk-manage` to win on specificity.
3. **Class colour leaked onto the call-to-action**, so a Preserved Flower "Book" button rendered sage and an Ikebana one navy. Class colour now travels in its own `--mzk-class` variable and only paints the card edge and calendar dots.

Verified on the live page at desktop (1280px) and mobile (375px): 19/19 computed-style assertions match the site, no horizontal overflow, 46px day cells and 42px buttons on mobile.

## Data model

| Table | Purpose |
|---|---|
| `mzk_class_types` | Classes and their rules (capacity/duration defaults, course flag, cutoffs). |
| `mzk_sessions` | Bookable slots: date, time, duration, capacity, manual adjustment, status. |
| `mzk_templates` | Weekly patterns used to generate sessions. |
| `mzk_blackouts` | Studio closure ranges. |
| `mzk_enrollments` | Course packages (sessions purchased, expiry, status). |
| `mzk_enrollment_log` | Audit trail of extensions. |
| `mzk_bookings` | Bookings, with manage token, reschedule count and reminder stamp. |

Deactivating keeps all data. Deleting the plugin runs `uninstall.php`, which drops everything.

## Extension points

Actions: `mzk_booking_created`, `mzk_booking_rescheduled`, `mzk_booking_cancelled`, `mzk_session_created`, `mzk_session_updated`, `mzk_enrollment_extended`, `mzk_blackout_saved`, `mzk_reminders_sent`.

Filters: `mzk_manage_capability`, `mzk_session_decorated`, `mzk_mail_tags`, `mzk_mail`, `mzk_mail_html`.

## REST API

Namespace `mizuki/v1`; writes require the `wp_rest` nonce and are rate-limited per IP.

- `GET /calendar?from&to&class_type`
- `POST /bookings`
- `GET /bookings/{id}?token=`
- `POST /bookings/{id}/reschedule`
- `POST /bookings/{id}/cancel`
- `GET /my-bookings` (logged-in students)

## Notes for deployment

- Reminders and the schedule top-up run on WP-Cron. On a low-traffic site, replace it with a real cron hitting `wp-cron.php` so reminders are punctual.
- Transactional mail through `wp_mail()` — pair with an SMTP plugin (or SES/Postmark) so confirmations don't land in spam.
- The seeded 72 h / 24 h cutoffs are editable per class; nothing is hard-coded.
