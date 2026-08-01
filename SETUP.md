# Setup checklist

Everything you need to do, in order. Roughly 45–60 minutes for the first pass, plus the product migration.

Work on **staging first** if you have one. Steps 8 and 9 touch live products and live e-mail.

---

## 1. Install the plugin

1. Zip the `mizuki-booking` folder (or download the release zip).
2. WordPress admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Install Now** → **Activate**.

On activation it creates its tables and seeds four classes with the client's rules already applied:

| Class | Limit | Duration | Course package | Reschedule closes |
|---|---|---|---|---|
| Fresh Flower | 6 | 2 hrs | no | **3 days** before |
| Ikebana | 6 | 2 hrs | no | **3 days** before |
| Preserved Flower | 6 | 4 hrs | yes | 24 hrs before |
| IFDA | 6 | 4 hrs | yes | 24 hrs before |

## 2. Check the site timezone

**Settings → General → Timezone** must be **Singapore**.

Every cutoff is calculated in site time. If this is wrong, the 3-day rule fires on the wrong day.

## 3. Confirm the class rules

**Bookings → Classes & Rules** → check each class and edit if the client disagrees:

- **Default participant limit** and **duration** — starting values for new sessions
- **Reschedule closes this many hours before** — `72` = 3 days. A Saturday class then locks on the Wednesday
- **Course package** — tick for classes sold as a block of sessions (IFDA, Preserved Flower)
- **Only students with an active package can book** — tick if those classes should never be bookable by the public

## 4. Build the schedule

**Bookings → Sessions → Weekly pattern**

1. Add one row per session the studio normally runs each week — day, start time, duration, limit. A day can have as many as you like (2–3 is typical).
2. Then **Generate the schedule** → from today → 3 months ahead → **Generate sessions**.

From now on this tops itself up automatically every day, so the calendar always shows the months ahead you set in Settings.

One-off or irregular classes: **Sessions → Add session** instead.

## 5. Block the closed dates

**Bookings → Blocked Dates** — add every studio closure you already know (holidays, travel, CNY).

Blocked dates disappear from the calendar immediately and are skipped when the schedule generates. If a date already has bookings, you'll get a warning telling you how many.

## 6. Create the pages

Create two pages and paste in one shortcode each:

| Page | Shortcode |
|---|---|
| Book a Class | `[mizuki_calendar]` |
| My Bookings | `[mizuki_my_bookings]` |

Then **Bookings → Settings → Pages** → select both from the dropdowns.

⚠️ **Do not skip the second one.** The "My Bookings" page is what every confirmation e-mail links to. Without it, students can't reschedule themselves.

For a class-specific page (e.g. on the Ikebana page) use `[mizuki_calendar class="ikebana"]`.

## 7. Settings

**Bookings → Settings**

- **Months shown ahead** — 3 (minimum 2)
- **Require a contact number** — on
- **Send reminder** — 2 days before, from 9 o'clock
- **Studio name / e-mail** — check these are right; they appear in every e-mail
- **E-mail templates** — read them through and adjust the wording to the studio's voice. Merge tags are listed above the fields
- Click **Send test** for each of the four templates to your own address before going live

## 8. Set up the products (paid bookings)

For each product that should sell a class, edit it → **Mizuki Booking** tab:

| Product | Set behaviour to | Also set |
|---|---|---|
| Workshop (Regular / Seasonal) | Session booking | Class = the matching class |
| Naturepresso, AIFE | Session booking | Class |
| IFDA course | Course package | Class = IFDA, Sessions granted = e.g. 25 |
| Preserved Flower course | Course package | Class, Sessions granted |

Then in **Settings → Payments**: hold time 45 minutes, confirm on **Paid (processing)**.

**Optional but recommended** — create a **$0 "Book a session"** product set to *Session booking* for IFDA and Preserved Flower. A zero-priced booking draws one session from the student's package instead of charging them. A paid booking never touches their balance.

## 9. Migrate the existing workshop products ⚠️

Your current workshop products sell the date as WooCommerce **variation attributes** (`dates`, `session`). Those dropdowns don't know about participant limits — **they can oversell a class**.

For each one, in this order:

1. Add that workshop's sessions under **Bookings → Sessions**
2. Change the product from **Variable** to **Simple**
3. Remove the `dates` and `session` attributes
4. Set **Mizuki Booking → Session booking** + the class
5. Buy it yourself end to end before moving to the next product

Do them **one at a time**. Keep the old product live until the replacement has been through a real checkout.

## 10. Make cron reliable

WP-Cron only fires when someone visits the site. On a quiet day the 2-day reminders go out late.

Ask the host to add a real cron job, every 15 minutes:

```bash
curl -s https://mizuki.com.sg/wp-cron.php?doing_wp_cron > /dev/null
```

Then in `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

## 11. Make e-mail reliable

`wp_mail()` from a shared host lands in spam. Install an SMTP plugin (WP Mail SMTP, or SES/Postmark/SendGrid) and authenticate the sending domain (SPF + DKIM).

Test by booking as a student and confirming the e-mail arrives in a Gmail inbox, not spam.

---

## Before you hand over — test these

Book as a real student would, on a phone:

- [ ] Calendar shows at least 2 months
- [ ] A day with 2–3 sessions shows all of them with correct times and places left
- [ ] Booking a session sends the confirmation e-mail within a minute
- [ ] The link in that e-mail opens the booking and lets you reschedule
- [ ] **Ikebana or Fresh Flower, class within 3 days → reschedule is refused** with a clear message
- [ ] **Preserved Flower or IFDA → reschedule is allowed**
- [ ] A full session shows "Fully booked" and cannot be booked
- [ ] A blocked date is not bookable
- [ ] Paid workshop: checkout completes and the booking appears under **Bookings**
- [ ] Abandon a checkout → the held place comes back within the hold window
- [ ] Buy a course package → the student appears under **Course Packages** with the right number of sessions
- [ ] Extending a package adds the sessions and shows in its history
- [ ] Reduce a session's places with the − button and confirm the website offers fewer

---

## Everyday use, for the studio

| To do this | Go here |
|---|---|
| See the week/month at a glance | **Bookings → Schedule** |
| Add a student who booked over chat | **Bookings → Bookings → Add booking** |
| Hold back places for chat students | **Schedule** → the **−** button on that session |
| Change one session's limit or time | **Sessions → Edit** |
| Close the studio for a day | **Blocked Dates** |
| Give a student more sessions or more time | **Course Packages → Manage → Extend** |
| Move a student to another date | **Bookings** → *Move to…* on their row |
| Check who's coming to a class | **Schedule** → **List** on that session |

---

## Notes

- Deactivating the plugin keeps all data. **Deleting** it drops every table — export first if you ever need to.
- The `−` / `+` buttons change how many places are offered on the website without touching the class limit, so the real limit stays visible.
- Students who book over chat should be added manually so the seat count stays honest.
