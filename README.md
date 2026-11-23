# Time Calendar

![Version](https://img.shields.io/badge/version-3.7.2-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![License](https://img.shields.io/badge/license-GPL--2.0-green)

> A full-featured WordPress event calendar with registration, waitlist, email templates, and a design token system.

---

## Features

- **Flexible event management** — single, multi-date, and recurring events
- **Registration forms** with waitlist, cancellation links, and rate limiting
- **Trial-session flow** — per event: book directly or register for a trial first, then mark as registered manually
- **Customizable email templates** — structured editor or full HTML expert mode
- **Design token system** — light/dark mode, all colors via CSS Custom Properties
- **Admin dashboard** with KPIs, upcoming events, and 30-day registration chart
- **Schema.org structured data** for SEO
- **Mobile-optimized calendar** — slider, scaled, or optimized views
- **No coding required** for basic setup

## Requirements

| Dependency | Version |
|---|---|
| WordPress | 6.0+ |
| PHP | 8.2+ |
| ACF PRO | 6.0+ |

## Installation

1. Upload the `time-calendar` folder to `wp-content/plugins/`
2. Activate the plugin in **Plugins**
3. Ensure **ACF PRO** is installed and active
4. Go to **Events > Kalender** to start

---

## Shortcodes

<details>
<summary><code>[time_calendar]</code> — Interactive frontend calendar</summary>

| Attribute | Values | Default |
|---|---|---|
| `type` | `all` / category slug | `all` |
| `week_only` | `true` / `false` | global setting |
| `mobile` | `slider` / `optimized` / `scaled` / `desktop` | global setting |

```
[time_calendar type="training" week_only="true"]
```

</details>

<details>
<summary><code>[time_events]</code> — Server-side event listing</summary>

| Attribute | Values | Default |
|---|---|---|
| `preset` | any saved preset key | `default` |
| `category` | category slug | all |
| `layout` | `grid` / `list` / `cards` | `grid` |
| `columns` | `1` / `2` / `3` | `3` |
| `group_by` | `none` / `month` / `month_inline` | `none` |
| `show_past` | `true` / `false` | `false` |
| `limit` | integer | `-1` (all) |
| `show_image` | `true` / `false` | `true` |
| `show_date` | `true` / `false` | `true` |
| `show_time` | `true` / `false` | `true` |
| `show_location` | `true` / `false` | `true` |
| `show_trainer` | `true` / `false` | `true` |
| `show_price` | `true` / `false` | `true` |
| `show_excerpt` | `true` / `false` | `true` |
| `show_badge` | `true` / `false` | `true` |

```
[time_events preset="kompakt"]
[time_events category="seminar" layout="cards" columns="2"]
```

</details>

<details>
<summary><code>[time_event_info]</code> — Event detail bar / cards</summary>

| Attribute | Values | Default |
|---|---|---|
| `event_id` | post ID | current post |
| `layout` | `bar` / `cards` | `bar` |
| `show_date` | `true` / `false` | `true` |
| `show_time` | `true` / `false` | `true` |
| `show_location` | `true` / `false` | `true` |
| `show_host` | `true` / `false` | `true` |
| `show_seats` | `true` / `false` | `true` |
| `show_audience` | `true` / `false` | `true` |

```
[time_event_info layout="cards"]
```

</details>

<details>
<summary><code>[time_registration]</code> — Registration form</summary>

| Attribute | Values | Default |
|---|---|---|
| `event_id` | post ID | current post |
| `title` | string | `Anmelden` |

```
[time_registration event_id="42"]
```

</details>

<details>
<summary><code>[time_price_bar]</code> — Sticky price bar</summary>

| Attribute | Values | Default |
|---|---|---|
| `post_id` | post ID | current post |
| `link` | URL | `#anmelden` |
| `link_text` | string | `Jetzt anmelden` |

```
[time_price_bar link="#contact"]
```

</details>

<details>
<summary><code>[training_ical_button]</code> — iCal download</summary>

| Attribute | Values | Default |
|---|---|---|
| `event_id` | post ID | current post |
| `label` | string | `Zum Kalender hinzufuegen` |

</details>

---

## Mail Placeholders

| Placeholder | Description |
|---|---|
| `{{firstname}}` | First name |
| `{{lastname}}` | Last name |
| `{{event_title}}` | Event title |
| `{{event_date}}` | Date & time |
| `{{event_location}}` | Location |
| `{{storno_url}}` | Cancellation link |
| `{{blogname}}` | Site name |
| `{{anrede}}` | Salutation (Sie/du) |
| `{{anrede_possessiv}}` | Possessive (Ihre/deine) |
| `{{anrede_akkusativ}}` | Accusative |
| `{{anrede_dativ}}` | Dative |
| `{{anrede_imperativ}}` | Imperative form |

---

## File Structure

```
time-calendar/
├── functions.php                          # Bootstrap & constants
├── includes/
│   ├── admin/
│   │   ├── settings.php                   # Settings page
│   │   ├── confirm-modal.php              # Shared admin confirmation dialog
│   │   ├── dashboard.php                  # Admin dashboard
│   │   ├── admin-page.php                 # Admin calendar
│   │   ├── events-overview.php            # Events list
│   │   ├── categories.php                 # Categories
│   │   └── updater.php                    # GitHub updater
│   ├── post-type/
│   │   ├── cpt.php                        # CPT & ACF fields
│   │   └── schema.php                     # Schema.org output
│   ├── ajax.php                           # Event AJAX handlers
│   ├── shortcodes/
│   │   ├── shortcode-calendar.php         # [time_calendar]
│   │   ├── shortcode-events.php           # [time_events]
│   │   ├── shortcode-event-info.php       # [time_event_info]
│   │   ├── shortcode-registration.php     # [time_registration]
│   │   ├── shortcode-price-bar.php        # [time_price_bar]
│   │   └── ical.php                       # iCal endpoint
│   └── registration/
│       ├── registration.php               # DB helpers
│       ├── registration-ajax.php          # Registration AJAX
│       ├── registration-mail.php          # Mail logic & templates
│       ├── registration-admin-page.php    # Admin UI
│       ├── cancel.php                     # Cancellation handler
│       ├── export.php                     # CSV export
│       ├── waitlist.php                   # Waitlist logic
│       └── reminder.php                   # Reminder cron
├── assets/
│   ├── js/
│   │   ├── admin/calendar.js
│   │   └── frontend/
│   │       ├── calendar-frontend.js       # Calendar + week plan
│   │       └── registration.js            # Registration form
│   └── css/
│       ├── design-system.css              # CSS Custom Properties
│       ├── admin/
│       └── frontend/
│           ├── calendar-frontend.css
│           ├── events.css
│           ├── event-info.css
│           ├── price-bar.css
│           └── registration.css
└── templates/
    └── single-time_event.php
```

---

### 3.7.2

- Status actions in the registrations list and on the dashboard now use an in-plugin modal instead of
  `window.confirm()`. The mail dispatch is a checkbox inside the dialog, so confirming, marking as
  registered or cancelling can each be done with or without notifying the participant.
- Deleting a registration goes through the same dialog.

### 3.7.1

- Fix: the registration form could not be submitted when the trial flow was active — the form omits
  address/zip/city, and the JS read those fields unconditionally, throwing before the AJAX call.
- Fix: the form anchor was rendered as `id="/#anmelden"`, so every CTA pointing at `#anmelden`
  (price bar, waitlist button) jumped nowhere.

### 3.7.0

- New per-event field **Anmelde-Ablauf** (`registration_flow`): `direct` (binding booking) or `trial` (trial session first).
  Existing events without the field keep their previous behaviour (price type `free`/`request` = trial).
- New registration status **`registered`** ("Angemeldet") — set manually in the backend once a contract is signed,
  optionally with a confirmation mail. Counts towards capacity like `confirmed`.
- New mail templates: **Dankes-Mail (Probetraining)** and **Anmelde-Mail (verbindlich angemeldet)**.
- Admin notification mails now carry a **Reply-To** header with the registrant's address.
- DB schema is now migrated on plugin updates, not only on activation (`tc_db_version` option).

### 3.4.4

---

## License

GPL-2.0 — see [LICENSE](./LICENSE) for details.
