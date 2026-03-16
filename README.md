# Time Calendar

A WordPress plugin for managing and displaying events in an interactive calendar with drag & drop support.

**Current version: 3.0.0**

---

## Features

- **Custom Post Type** `time_event` for events, trainings, and seminars
- **ACF field group** with tabs for general info, details, date & time, repeater dates, and pricing
- **Admin calendar** with FullCalendar v6 — drag & drop, resize, and inline event creation
- **Recurring events** — weekly recurrence with a configurable end date
- **Repeater dates** — multiple individual dates per event with per-date seat limits
- **Calendar shortcode** `[time_calendar]` with filter tabs, calendar/week-plan views, and event popovers
- **Week-only mode** — lock the frontend calendar to the current week, hiding all navigation and view switchers
- **Wochenplan view** — alternative week grid view with time slots, grouped by Vormittag / Nachmittag / Abend; responsive card layout on mobile
- **Event overview list** — optional card grid displayed below the calendar, showing all events filtered by the active category tab; deduplicated and sorted alphabetically
- **Universal listing shortcode** `[time_events]` — fully server-side rendered event listing with grid, list, and cards layouts; 14 shortcode attributes for layout, filtering, and content control; no JavaScript required
- **Category system** — custom event categories with individual colors, managed under Events → Kategorien
- **Custom primary color** — pick any brand color; all frontend elements update automatically via CSS Custom Properties
- **Light / Dark Mode** — set globally via the settings page, applies to all shortcode instances
- **Redesigned settings page** — tab-based admin UI (Design · Kalender · E-Mails · Updates) with toast notifications
- **Admin dashboard** — KPI cards, next events, recent registrations, and 30-day chart
- **Price bar shortcode** `[time_price_bar]` — fixed bottom bar with Early Bird and on-request logic
- **Registration shortcode** `[time_registration]` — full registration form with AJAX submission, confirmation emails, and admin notifications
- **Waitlist system** — automatic waitlist when events are full, with slot-available notifications
- **iCal export** — `.ics` file download for individual events via `[training_ical_button]` shortcode
- **URL parameter filtering** for menu links (e.g. `?tc_type=training`)
- **Client-side caching** — events are fetched once via AJAX and filtered locally for instant UI response
- **GitHub auto-updater** — updates delivered via WordPress update mechanism from GitHub releases
- **Post-type migration tool** — safe one-click migration from `training_event` to `time_event` with dry-run preview

---

## Requirements

- WordPress 6.0+
- [Advanced Custom Fields PRO](https://www.advancedcustomfields.com/) 6.0+
- PHP 8.2+

---

## Installation

1. Clone or download this repository into your plugins directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/crispyparmaham/wp-event-calender.git time-calendar
   ```
2. Run `composer install` inside the plugin directory (required for the GitHub auto-updater)
3. Activate the plugin in **WordPress Admin → Plugins**
4. Make sure ACF PRO is installed and activated
5. Go to **Events → Kalender** to open the admin calendar

### Upgrading from v2.x

If you are upgrading from a version that used the `training_event` post type, follow the [Migration Guide](MIGRATION.md) to migrate your existing posts to `time_event`.

---

## File Structure

```
time-calendar/
├── functions.php
├── MIGRATION.md                        # Post-type migration guide
├── includes/
│   ├── admin/
│   │   ├── settings.php               # Settings registration, sanitize, page render
│   │   ├── dashboard.php              # Admin dashboard with KPIs & charts
│   │   ├── admin-page.php             # Admin calendar page & asset enqueue
│   │   ├── events-overview.php        # Events list view in admin
│   │   ├── categories.php             # Category management
│   │   ├── migration.php              # Post-type migration tool
│   │   └── updater.php                # GitHub auto-updater bootstrap
│   ├── post-type/
│   │   └── cpt.php                    # CPT & ACF field group registration
│   ├── ajax.php                       # AJAX handlers (load / create / update events)
│   ├── shortcodes/
│   │   ├── shortcode-calendar.php     # Frontend calendar shortcode & asset enqueue
│   │   ├── shortcode-events.php       # Universal event listing shortcode [time_events]
│   │   ├── shortcode-registration.php # Registration form shortcode
│   │   ├── shortcode-price-bar.php    # Price bar shortcode
│   │   └── ical.php                   # iCal feed endpoint & button shortcode
│   └── registration/
│       ├── registration.php           # Registration data management & AJAX handlers
│       ├── registration-admin-page.php# Registration admin interface
│       ├── cancel.php                 # Cancellation handler & email
│       ├── export.php                 # CSV export
│       ├── waitlist.php               # Waitlist logic & auto-promotion
│       └── reminder.php               # Cron-based reminder emails
├── templates/
│   └── single-time_event.php          # Single event template
└── assets/
    ├── js/
    │   ├── admin/
    │   │   └── calendar.js            # Admin calendar logic (FullCalendar)
    │   └── frontend/
    │       ├── calendar-frontend.js   # Frontend calendar + wochenplan + event overview
    │       └── registration.js        # Registration form AJAX handling
    └── css/
        ├── design-system.css          # Central CSS Custom Properties (light/dark)
        ├── admin/
        │   ├── calendar.css           # Admin calendar styles
        │   └── settings.css           # Admin settings page styles (tab UI)
        └── frontend/
            ├── calendar-frontend.css  # Frontend calendar + wochenplan styles
            ├── event-list.css         # Event overview card grid styles
            ├── events.css             # [time_events] shortcode styles
            ├── price-bar.css          # Price bar styles
            └── registration.css       # Registration form styles
```

---

## Settings Page

Navigate to **Events → Einstellungen**. The settings page uses a tab-based layout. All tabs share a single form — saving from any tab saves all settings.

The active tab is persisted in `localStorage` and reflected in the URL hash (e.g. `#kalender`), so the correct tab is restored after saving.

### Tab: Design

| Setting | Description |
|---|---|
| **Primärfarbe** | Brand color for all interactive elements (buttons, badges, focus rings). Applied as `--tc-primary` CSS Custom Property. Default: `#4f46e5` |
| **Farbmodus** | Toggle between Light Mode and Dark Mode for all `[time_calendar]` shortcode instances |

### Tab: Kalender

| Setting | Key | Default | Description |
|---|---|---|---|
| **Nur aktuelle Woche anzeigen** | `frontend_week_only` | `false` | Locks the frontend calendar to the current week. Hides all navigation, view switchers, and the Kalender/Wochenplan toggle. |
| **Event-Übersicht anzeigen** | `show_event_list` | `false` | Renders a card grid of all events below the calendar. |
| **Überschrift der Event-Liste** | `event_list_title` | `Unsere Events` | Section heading displayed above the event card grid. |

### Tab: E-Mails & Anmeldungen

| Setting | Description |
|---|---|
| **Bestätigungs-E-Mail Empfänger** | Email address that receives registration notifications. |
| **Erinnerungsmail (3 Tage vorher)** | Enables the daily cron job that sends reminder emails to confirmed registrants 3 days before their event. |

### Tab: Updates

Shows the current plugin version and a link to check for new releases via the WordPress update mechanism.

---

## Shortcodes

### `[time_calendar]`

Interactive calendar with filter tabs, multiple views, and event popovers.

```
[time_calendar]
[time_calendar type="training"]
[time_calendar type="seminar" view="listMonth"]
[time_calendar week_only="true"]
```

| Attribute | Values | Default | Description |
|---|---|---|---|
| `type` | `all`, or any category slug | `all` | Pre-selected filter tab |
| `view` | `dayGridMonth`, `timeGridWeek`, `listMonth` | `dayGridMonth` | Initial calendar view |
| `week_only` | `true`, `false` | _(global setting)_ | Override the global week-only setting |

The `?tc_type=` URL parameter overrides the shortcode `type` attribute for menu links.

### `[time_events]`

Server-side rendered event listing with multiple layouts. No JavaScript required.

```
[time_events]
[time_events category="seminar" layout="list" group_by="month" columns="1"]
[time_events layout="cards" columns="3" show_price="false"]
```

#### Filtering

| Attribute | Values | Default | Description |
|---|---|---|---|
| `category` | Any `event_type` slug | _(all)_ | Filter by category |
| `show_past` | `true`, `false` | `false` | Include past events |
| `limit` | Integer | `-1` | Maximum events to display |

#### Layout

| Attribute | Values | Default | Description |
|---|---|---|---|
| `layout` | `grid`, `list`, `cards` | `grid` | Display mode |
| `columns` | `1`, `2`, `3` | `3` | Number of columns (grid/cards) |
| `group_by` | `month`, `none` | `none` | Group events under month headings |

#### Content Toggles

| Attribute | Default | Description |
|---|---|---|
| `show_image` | `true` | Featured image (16:9) |
| `show_date` | `true` | Start date in German format |
| `show_time` | `true` | Start/end time |
| `show_location` | `true` | Location |
| `show_trainer` | `true` | Seminar/training leadership |
| `show_price` | `true` | Price or "Preis auf Anfrage" |
| `show_excerpt` | `true` | Excerpt or trimmed content |
| `show_badge` | `true` | Category badge |

### `[time_registration]`

Registration form with AJAX submission and email notifications.

```
[time_registration]
[time_registration event_id="42"]
[time_registration event_id="42" title="Jetzt anmelden"]
```

| Attribute | Default | Description |
|---|---|---|
| `event_id` | `0` | Post ID (auto-detected on single event pages) |
| `title` | `Anmelden` | Form title heading |

### `[time_price_bar]`

Fixed bottom bar with pricing info and CTA button.

```
[time_price_bar]
[time_price_bar link="#contact" link_text="Jetzt buchen"]
```

| Attribute | Default | Description |
|---|---|---|
| `post_id` | current post | Post ID to read pricing from |
| `link` | `#anmelden` | CTA button URL |
| `link_text` | `Jetzt anmelden` | Button label (with price) |
| `request_text` | `Jetzt anfragen` | Button label (on request) |

### `[training_ical_button]`

Downloads an `.ics` file for a specific event.

```
[training_ical_button]
[training_ical_button event_id="42" label="Zum Kalender hinzufügen"]
```

---

## AJAX Endpoints

All endpoints require a valid `tc_nonce` nonce.

| Action | Method | Description |
|---|---|---|
| `tc_get_events` | POST | Returns all published events including recurring occurrences |
| `tc_create_event` | POST | Creates a new `time_event` post |
| `tc_update_event` | POST | Updates date/time fields of an existing post |
| `tc_submit_registration` | POST | Submits a new registration |
| `tc_get_event_details` | POST | Returns event details for the registration form |
| `tc_cancel_registration` | POST | Cancels a registration via token link |
| `tc_submit_waitlist` | POST | Submits a waitlist entry |
| `tc_update_registration_status` | POST | Admin: update registration status |

---

## ACF Fields

| Tab | Field | Name | Type |
|---|---|---|---|
| Allgemein | Event-Typ | `event_type` | Select (dynamic from categories) |
| Allgemein | Einleitungstext | `intro_text` | Textarea |
| Allgemein | Partnerlogo | `partnerlogo` | Image |
| Details | Seminar-/Trainingsleitung | `seminar_leadership` | Text |
| Details | Max. Teilnehmer | `participants` | Number |
| Details | Teilnehmer tracken? | `track_participants` | Boolean |
| Details | Für wen geeignet | `difficulty` | Text |
| Details | Ort | `location` | WYSIWYG |
| Datum & Uhrzeit | Mehrtägig? | `more_days` | True/False |
| Datum & Uhrzeit | Startdatum | `start_date` | Date Picker (`Y-m-d`) |
| Datum & Uhrzeit | Startzeit | `start_time` | Time Picker (`H:i`) |
| Datum & Uhrzeit | Endzeit | `end_time` | Time Picker (`H:i`) |
| Datum & Uhrzeit | Enddatum | `end_date` | Date Picker (`Y-m-d`) |
| Datum & Uhrzeit | Wiederkehrend? | `is_recurring` | True/False |
| Datum & Uhrzeit | Wochentag | `recurring_weekday` | Select (0–6) |
| Datum & Uhrzeit | Wiederholen bis | `recurring_until` | Date Picker (`Y-m-d`) |
| Termine | Mehrere Termine | `event_dates` | Repeater |
| Preis | Probetraining anzeigen | `price_on_request` | True/False |
| Preis | Regulärer Preis | `normal_preis` | Number |
| Preis | Early-Bird-Preis | `early_bird.early_bird_preis` | Number (Group) |
| Preis | Anmeldung bis | `early_bird.anmeldung` | Date Picker (`Y-m-d`) |

---

## CSS Design System

All colors are defined as CSS Custom Properties in `assets/css/design-system.css`. This file is loaded first and provides the single source of truth for both light and dark mode.

### Core Variables

| Property | Light | Dark | Description |
|---|---|---|---|
| `--tc-primary` | Set via settings | _(same)_ | Primary action color |
| `--tc-primary-dark` | Auto-computed | _(same)_ | Darkened primary for hover states |
| `--tc-primary-light` | Auto-computed | _(same)_ | Translucent primary for backgrounds |
| `--tc-bg` | `#ffffff` | `#0f172a` | Main background |
| `--tc-bg-secondary` | `#f8fafc` | `#1e293b` | Secondary background |
| `--tc-surface` | `#f1f5f9` | `#1e293b` | Surface / header cells |
| `--tc-surface-raised` | `#ffffff` | `#334155` | Raised surface (cards) |
| `--tc-border` | `#e2e8f0` | `#334155` | All borders and dividers |
| `--tc-border-strong` | `#cbd5e1` | `#475569` | Strong borders |
| `--tc-text` | `#0f172a` | `#f1f5f9` | Primary text color |
| `--tc-text-muted` | `#64748b` | `#94a3b8` | Secondary / hint text |
| `--tc-text-subtle` | `#94a3b8` | `#64748b` | Subtle / placeholder text |

### Status Colors

| Property | Value | Description |
|---|---|---|
| `--tc-success` | `#059669` | Success states |
| `--tc-warning` | `#d97706` | Warning states |
| `--tc-danger` | `#dc2626` | Error / danger states |
| `--tc-info` | `#0ea5e9` | Info states |

### Dark Mode

Dark mode is activated by adding the `.tc-dark` class to the outermost wrapper element of each shortcode. The class is set server-side via `tc_dark_class()` based on the global Farbmodus setting. No JavaScript is required for the theme switch — all colors cascade via CSS Custom Properties.

Individual CSS files contain **no** dark mode overrides — all theming is handled exclusively by `design-system.css`.

---

## Migration from v2.x

Version 3.0 renames the Custom Post Type from `training_event` to `time_event`. A built-in migration tool handles the transition safely. See [MIGRATION.md](MIGRATION.md) for the full guide.

---

## License

MIT — feel free to use and adapt for client projects.
