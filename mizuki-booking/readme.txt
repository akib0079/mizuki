=== Mizuki Booking ===
Contributors: avixdigital
Tags: booking, calendar, classes, workshop, courses
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Class booking calendar for Mizuki Flower Studio: multiple sessions per day, per-session participant limits, a 2+ month schedule, rule-based rescheduling, blocked dates, course session packages, and automatic confirmation and reminder e-mails.

== Description ==

Built for mizuki.com.sg.

* **Multiple sessions per day** — as many as you need, each with its own start time and duration (2 hours, 4 hours, anything). Editable at any time.
* **Participant limit per session** — set a maximum, change it whenever, and hold seats back or open extra ones for students who booked directly over chat.
* **At least 2 months of schedule** — the calendar always shows the months ahead you configure, and tops itself up daily from your weekly pattern.
* **Rescheduling with per-class rules** — Preserved Flower and IFDA students can move their booking freely; Fresh Flower and Ikebana lock 3 days before the class (a Saturday class stops changing on Wednesday).
* **Blocked dates** — mark studio closures; those days disappear from the calendar immediately and are skipped when the schedule is generated.
* **Course packages** — IFDA and Preserved Flower students buy a fixed number of sessions (e.g. 25). Bookings draw down the balance, and you can extend sessions or the expiry date any time, with an audit trail.
* **Automatic e-mails** — confirmation the moment a student signs up, and a reminder 2 days before the class (both configurable).

== Installation ==

1. Upload the `mizuki-booking` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **Bookings → Classes & Rules** and check the four seeded classes.
4. Go to **Bookings → Sessions → Weekly pattern**, add the sessions you run each week, and generate the schedule.
5. Create a page with `[mizuki_calendar]` and another with `[mizuki_my_bookings]`, then select both under **Bookings → Settings → Pages**.

== Shortcodes ==

* `[mizuki_calendar]` — calendar for all classes.
* `[mizuki_calendar class="ikebana"]` — one class only.
* `[mizuki_calendar months="3"]` — show more months (minimum 2).
* `[mizuki_calendar view="list"]` — chronological list instead of a grid.
* `[mizuki_my_bookings]` — student self-service: view, reschedule, cancel.

== Changelog ==

= 3.2.0 =
* Each class now says how students book it: on the calendar, pay-first through the shop, or as a course. The booking gate follows that setting.
* A student who cannot book is always offered the way forward — a link to the course or the product — instead of a dead end telling them to contact the studio.
* Paid classes show "Book and pay" on the calendar and go to the product, so a seat and its payment can never disagree.
* Booking requests awaiting approval now appear on the Schedule dashboard, with a count badge on the Bookings menu.
* New Payments step in Setup: see how every class is paid for, and create the missing WooCommerce products as drafts, already wired to the calendar.

= 3.1.2 =
* Fixed: links and buttons on the plugin pages appeared underlined in the theme's blue. Astra underlines every link inside post content and on WooCommerce pages, which outranked the button styles.

= 3.1.1 =
* Fixed: comments inside a CREATE TABLE made dbDelta mis-parse the bookings table, so schema updates could fail silently. Added an automated guard against it.
* Fixed: the classes page had no button styling, because the shared component styles were scoped to the calendar and manager only.
* Fixed: the class photo picker did nothing, because the script ran before the WordPress media library had loaded.
* Redesigned the classes page: proper cards, image framing, fact rows, next dates and real call-to-action buttons.
* New Diagnostics panel on the Setup screen showing which tables exist, row counts and the last schedule generation, with a Check and repair database button.
* Generating the schedule now reports database errors and warns when nothing was created, instead of silently reporting zero.

= 3.1.0 =
* Fixed: the My Account endpoints were registered at the site root, which stopped the "My Classes" page resolving at /my-classes/ and showed the blog index instead.
* New [mizuki_classes] page: what each class is, price, length, next dates and an Enrol button. Added to the setup wizard as "Our Classes".
* Classes now carry a summary, price note, photo and optional booking link, editable under Classes & Rules.
* The calendar now honours ?class=slug, so Enrol and "Book a session" links land on the right class.

= 3.0.1 =
* Architecture review fixes: unpaid checkouts no longer create student accounts or send welcome e-mails; course packages can no longer be over-spent by simultaneous bookings; the calendar endpoint no longer writes on every page view; added composite indexes; uninstall cleans up fully.

= 3.0.0 =
* Setup screen: creates every page with its shortcode in one click, and installs or removes demo content.
* Registration approval per class — the place is held while it waits, and the student is told either way.
* Student accounts created automatically, with a login page and a dashboard showing classes, course balance and details.
* Front-end studio manager [mizuki_manage]: approvals, sessions, places and closures without wp-admin.
* New shortcodes: [mizuki_login], [mizuki_dashboard], [mizuki_manage].

= 2.0.0 =
* All-in-one: WooCommerce integration. Session-booking and course-package products, seats held during checkout and confirmed on payment, packages granted automatically.
* Unpaid, cancelled, failed and refunded orders release their seats.
* New My Account tabs: My Classes and My Courses, plus a [mizuki_my_courses] shortcode.
* Paid sessions no longer draw down a student's course package.

= 1.1.0 =
* Front end restyled to match the live mizuki.com.sg design system (Astra + Elementor kit colours, Poppins/Radio Canada type, square corners, teal buttons).
* Fixed theme conflicts: Astra's global button !important rules, button font weight, and class colour leaking onto call-to-action buttons.
* Calendar grid now pads the final week so no grey block shows after the last day of the month.

= 1.0.0 =
* First release.
