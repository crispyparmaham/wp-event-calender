# Time Calendar

A WordPress plugin for managing and displaying trainings and seminars in an interactive calendar with drag & drop support.

**Current version: 2.1.7**

---

## Features

- **Custom Post Type** `training_event` for trainings and seminars
- **ACF field group** with tabs for general info, details, date & time, and pricing
- **Admin calendar** with FullCalendar v6 — drag & drop, resize, and inline event creation
- **Recurring events** — weekly recurrence with a configurable end date
- **Calendar shortcode** `[time_calendar]` with filter tabs, calendar/week-plan views, and event popovers
- **Week-only mode** — lock the frontend calendar to the current week, hiding all navigation and view switchers
- **Wochenplan view** — alternative week grid view with time slots, grouped by Vormittag / Nachmittag / Abend; responsive card layout on mobile
- **Event overview list** — optional card grid displayed below the calendar, showing all events filtered by the active category tab; deduplicated and sorted alphabetically
- **Universal listing shortcode** `[time_events]` — fully server-side rendered event listing with grid, list, and cards layouts; 14 shortcode attributes for layout, filtering, and content control; no JavaScript required
- **Category system** — custom event categories with individual colors, managed under Events → Kategorien
- **Custom primary color** — pick any brand color; all frontend elements update automatically via CSS Custom Properties
- **Light / Dark Mode** — set globally via the settings page, applies to all shortcode instances
- **Redesigned settings page** — tab-based admin UI (Design · Kalender · E-Mails · Updates) with toast notifications
- **Price bar shortcode** `[time_price_bar]` — fixed bottom bar with Early Bird and on-request logic
- **Registration shortcode** `[time_registration]` — full registration form with AJAX submission, confirmation emails, and admin notifications
- **URL parameter filtering** for menu links (e.g. `?tc_type=training`)
- **Client-side caching** — events are fetched once via AJAX and filtered locally for instant UI response
- **GitHub auto-updater** — updates delivered via WordPress update mechanism from GitHub releases

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

---

## File Structure

```
time-calendar/
├── functions.php
├── includes/
│   ├── admin/
│   │   ├── settings.php               # Settings registration, sanitize, page render
│   │   ├── dashboard.php              # Admin dashboard widget
│   │   ├── admin-page.php             # Admin calendar page & asset enqueue
│   │   ├── events-overview.php        # Events list view in admin
│   │   ├── categories.php             # Category management
│   │   └── updater.php                # GitHub auto-updater bootstrap
│   ├── post-type/
│   │   └── cpt.php                    # CPT & ACF field group registration
│   ├── ajax.php                       # AJAX handlers (load / create / update events)
│   ├── shortcodes/
│   │   ├── shortcode-calendar.php     # Frontend calendar shortcode & asset enqueue
│   │   ├── shortcode-events.php       # Universal event listing shortcode [time_events]
│   │   ├── shortcode-registration.php # Registration form shortcode
│   │   ├── shortcode-price-bar.php    # Price bar shortcode
│   │   └── ical.php                   # iCal feed endpoint
│   └── registration/
│       ├── registration.php           # Registration data management & AJAX handlers
│       ├── registration-admin-page.php# Registration admin interface
│       ├── cancel.php                 # Cancellation handler & email
│       ├── export.php                 # CSV export
│       ├── waitlist.php               # Waitlist logic
│       └── reminder.php               # Cron-based reminder emails
└── assets/
    ├── js/
    │   ├── admin/
    │   │   └── calendar.js            # Admin calendar logic (FullCalendar)
    │   └── frontend/
    │       └── calendar-frontend.js   # Frontend calendar + wochenplan + event overview
    └── css/
        ├── admin/
        │   └── settings.css           # Admin settings page styles (tab UI)
        └── frontend/
            ├── calendar-frontend.css  # Frontend calendar + wochenplan styles
            ├── event-list.css         # Event overview card grid styles
            └── events.css             # [time_events] shortcode styles (self-contained)
```

---

## Settings Page

Navigate to **Events → Einstellungen**. The settings page uses a tab-based layout. All tabs share a single form — saving from any tab saves all settings.

The active tab is persisted in `localStorage` and reflected in the URL hash (e.g. `#kalender`), so the correct tab is restored after saving.

### 🎨 Tab: Design

| Setting | Description |
|---|---|
| **Primärfarbe** | Brand color for all interactive elements (buttons, badges, focus rings). Applied as `--tc-primary` CSS Custom Property. Default: `#4f46e5` |
| **Farbmodus** | Toggle between Light Mode and Dark Mode for all `[time_calendar]` shortcode instances |

### 📅 Tab: Kalender

| Setting | Key | Default | Description |
|---|---|---|---|
| **Nur aktuelle Woche anzeigen** | `frontend_week_only` | `false` | Locks the frontend calendar to the current week. Hides all navigation, view switchers, and the Kalender/Wochenplan toggle. Shows the Wochenplan view for the current week only. |
| **Event-Übersicht anzeigen** | `show_event_list` | `false` | Renders a card grid of all events below the calendar. Cards are deduplicated, sorted alphabetically, and react to the active category filter tab. |
| **Überschrift der Event-Liste** | `event_list_title` | `Unsere Events` | Section heading displayed above the event card grid. |

### 📧 Tab: E-Mails & Anmeldungen

| Setting | Description |
|---|---|
| **Bestätigungs-E-Mail Empfänger** | Email address that receives registration notifications. The sender address is controlled by Fluent SMTP (if installed). |
| **Erinnerungsmail (3 Tage vorher)** | Enables the daily cron job that sends reminder emails to confirmed registrants 3 days before their event. Already-sent reminders are not re-sent. |

### 🔄 Tab: Updates

Shows the current plugin version and a link to check for new releases via the WordPress update mechanism.

---

## Calendar Shortcode

```
[time_calendar]
[time_calendar type="training"]
[time_calendar type="seminar" view="listMonth"]
[time_calendar week_only="true"]
[time_calendar week_only="false"]
```

**Via PHP template:**
```php
<?php echo do_shortcode('[time_calendar]'); ?>
<?php echo do_shortcode('[time_calendar type="training" view="timeGridWeek"]'); ?>
<?php echo do_shortcode('[time_calendar week_only="true"]'); ?>
```

### Attributes

| Attribute | Values | Default | Description |
|---|---|---|---|
| `type` | `all`, `training`, `seminar` | `all` | Pre-selected filter tab |
| `view` | `dayGridMonth`, `timeGridWeek`, `listMonth` | `dayGridMonth` | Initial calendar view (ignored in week-only mode) |
| `week_only` | `true`, `false` | _(global setting)_ | Overrides the global "Nur aktuelle Woche" setting for this specific shortcode instance |

The `week_only` shortcode attribute always takes precedence over the global setting in **Events → Einstellungen → Kalender**.

### URL Parameter

The `?tc_type=` URL parameter overrides the shortcode `type` attribute. Useful for navigation menu links:

```
/your-page/?tc_type=training   → Gruppentraining pre-selected
/your-page/?tc_type=seminar    → Seminare pre-selected
/your-page/                    → All events (default)
```

### Week-Only Mode

When `week_only` is active (via shortcode attribute or global setting):

- The calendar is replaced by the **Wochenplan** view, locked to the current week
- All navigation buttons (prev / next / today) are hidden
- The Kalender / Wochenplan view toggle is hidden
- On mobile, the responsive card layout is shown instead of the table

### Event Overview List

When **Event-Übersicht anzeigen** is enabled in the settings, a card grid appears below the calendar:

- Shows each unique event exactly once (recurring events are deduplicated by title)
- Sorted alphabetically
- Reacts to the active category filter tab in real time
- Each card links directly to the event's permalink
- Cards display: category color stripe, category badge, title, intro text (max 2 lines), location, trainer
- Dark Mode is applied automatically via CSS Custom Properties

---

## Training Events Shortcode

Universal event listing shortcode with multiple layouts. Purely server-side rendered — no JavaScript or AJAX required.

```
[time_events]
[time_events category="seminar" layout="list" group_by="month" columns="1"]
[time_events category="training" layout="grid" columns="3" show_price="false" show_excerpt="false"]
[time_events layout="grid" show_image="false" columns="2"]
[time_events limit="5" layout="cards" group_by="none"]
```

**Via PHP template:**
```php
<?php echo do_shortcode('[time_events layout="cards" columns="3"]'); ?>
<?php echo do_shortcode('[time_events category="seminar" group_by="month" layout="list"]'); ?>
```

### Attributes

#### Filtering

| Attribute | Values | Default | Description |
|---|---|---|---|
| `category` | Any `event_type` slug | _(all)_ | Filter by category, e.g. `"seminar"` or `"training"` |
| `show_past` | `true`, `false` | `false` | Include events whose `start_date` is in the past |
| `limit` | Integer | `-1` | Maximum number of events to display; `-1` shows all |

#### Layout

| Attribute | Values | Default | Description |
|---|---|---|---|
| `layout` | `grid`, `list`, `cards` | `grid` | Display mode (see below) |
| `columns` | `1`, `2`, `3` | `3` | Number of columns in grid/cards mode; ignored for list |
| `group_by` | `month`, `none` | `none` | Group events under a month heading ("März 2026") |

#### Content

| Attribute | Default | Description |
|---|---|---|
| `show_image` | `true` | Featured image (16:9). Falls back to a colored placeholder using the category color |
| `show_date` | `true` | Start date in German long format, e.g. "Mo, 24. März 2026" |
| `show_time` | `true` | Start/end time next to the date, e.g. "· 09:00–17:00 Uhr" |
| `show_location` | `true` | Location (stripped of HTML) |
| `show_trainer` | `true` | Seminar/training leadership (`seminar_leadership` field) |
| `show_price` | `true` | Price in German format, e.g. "199,00 €". Shows "Preis auf Anfrage" when `price_on_request` is set |
| `show_excerpt` | `true` | Post excerpt or trimmed content (max 18 words) |
| `show_badge` | `true` | Category badge with the category's brand color |

### Layouts

#### `grid` (default)
CSS Grid with `columns` columns. Image on top (16:9), content below. Responsive: 3 → 2 → 1 columns at 900px / 600px breakpoints. Hover: `translateY(-3px)` + raised shadow.

#### `list`
One card per row. Image on the left (200px fixed, full width on mobile), content on the right. Compact, suited for long event lists.

#### `cards`
Like `grid`, but with a 4px accent stripe at the top of each card in the category color. Larger spacing and a more prominent title.

### Sold-Out State

When an event's capacity is tracked (`track_participants = true`) and the registration count in `wp_tc_registrations` reaches the `participants` limit:

- A red **Ausgebucht** badge overlays the image (or appears in the card body when `show_image="false"`)
- The card is rendered at reduced opacity
- The hover lift effect is disabled

### Month Grouping

When `group_by="month"`, events are grouped under a centered month/year heading with a horizontal rule. Months with no events are skipped. The selected `layout` applies within each group.

### Dark Mode

The shortcode's CSS is self-contained — it does not depend on `calendar-frontend.css`. Dark mode is applied automatically via `@media (prefers-color-scheme: dark)` and also responds to the `.tc-dark` parent class (set by the global Farbmodus setting).

---

## Wochenplan View

The Wochenplan (week plan) is an alternative view to FullCalendar, showing events in a structured time-slot table.

| Feature | Description |
|---|---|
| **Navigation** | Prev / Next week buttons (hidden in week-only mode) |
| **Time grouping** | Events grouped into Vormittag / Nachmittag / Abend rows |
| **Equal columns** | `table-layout: fixed` — all 7 day columns have equal width |
| **Mobile layout** | On screens ≤ 767px the table is hidden and replaced by a vertical day-card layout — no horizontal scrolling |
| **Today highlight** | Today's column / day header is highlighted with `--tc-primary-light` |
| **Event click** | Opens the same detail popover as the FullCalendar view |

---

## CSS Custom Properties

All frontend colors are defined as CSS Custom Properties on `.tc-frontend-wrap`. Override them in your theme to match your brand without touching plugin CSS.

| Property | Default (Light) | Description |
|---|---|---|
| `--tc-primary` | Set via settings | Primary action color (buttons, badges, focus rings) |
| `--tc-primary-dark` | Auto-computed | Darkened primary for hover states |
| `--tc-primary-light` | Auto-computed | Translucent primary for backgrounds |
| `--tc-bg` | `#ffffff` | Calendar and card background |
| `--tc-surface` | `#f9fafb` | Header cells, table backgrounds |
| `--tc-border` | `#e5e7eb` | All borders and dividers |
| `--tc-text` | `#111827` | Primary text color |
| `--tc-text-muted` | `#6b7280` | Secondary / hint text |
| `--tc-today` | `--tc-primary-light` | Today highlight background |

Dark Mode values are set on `.tc-dark` and activated by the global Farbmodus setting.

---

## Admin Calendar

Navigate to **Events → Kalender** in the WordPress admin.

| Action | Result |
|---|---|
| Click on an empty day | Opens modal to create a new event |
| Drag & drop an event | Updates `start_date` / `end_date` via AJAX |
| Resize an event | Updates `end_date` via AJAX |
| Click on an event | Opens the post editor |

Trainings are displayed in **indigo**, seminars in **green**. Recurring event occurrences are shown at reduced opacity and cannot be dragged individually — only the main post is editable.

---

## Price Bar Shortcode

Displays a fixed bar at the bottom of the screen with pricing info and a CTA button. Reads ACF fields from the current post automatically.

```
[time_price_bar]
[time_price_bar link="#contact" link_text="Jetzt buchen"]
[time_price_bar post_id="42" request_text="Termin anfragen"]
```

### Attributes

| Attribute | Default | Description |
|---|---|---|
| `post_id` | current post | Post ID to read pricing from |
| `link` | `#anmelden` | Anchor or URL for the CTA button |
| `link_text` | `Jetzt anmelden` | Button label when a price is set |
| `request_text` | `Jetzt anfragen` | Button label when "Preis auf Anfrage" is active |

### Behavior

| State | Display |
|---|---|
| `price_on_request = true` | Shows `price_on_request_label` + request button |
| Early Bird deadline not yet passed | Shows Early Bird price + deadline hint + regular price fallback |
| Early Bird expired or not set | Shows regular price only |

---

## Registration Shortcode

```
[time_registration]
[time_registration event_id="42"]
[time_registration event_id="42" title="Jetzt anmelden"]
```

### Attributes

| Attribute | Default | Description |
|---|---|---|
| `event_id` | `0` | Post ID of a specific event. Auto-detected on single event pages. |
| `title` | `Anmelden` | Form title heading |

### Features

- Automatic event detection on single event pages
- Dynamic event selection with date picker for recurring events
- Capacity tracking with "Ausgebucht" state when full
- AJAX submission with confirmation email to registrant and admin notification
- Registrations stored in `wp_tc_registrations` custom database table
- Cancellation link in confirmation email

### Admin Management

Navigate to **Events → Anmeldungen** to manage registrations (list, edit, status change, delete, CSV export).

---

## AJAX Endpoints

All endpoints require a valid `tc_nonce` nonce.

| Action | Method | Description |
|---|---|---|
| `tc_get_events` | POST | Returns all published events including recurring occurrences |
| `tc_create_event` | POST | Creates a new `training_event` post |
| `tc_update_event` | POST | Updates date/time fields of an existing post |
| `tc_submit_registration` | POST | Submits a new registration |
| `tc_cancel_registration` | POST | Cancels a registration via token link |

---

## ACF Fields

| Tab | Field | Name | Type |
|---|---|---|---|
| Allgemein | Event-Typ | `event_type` | Select (training / seminar) |
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
| Preis | Regulärer Preis | `normal_preis` | Number |
| Preis | Early-Bird-Preis | `early_bird.early_bird_preis` | Number (Group) |
| Preis | Anmeldung bis | `early_bird.anmeldung` | Date Picker (`Y-m-d`) |

> **Note:** The `intro_text` field is used as the card description in the Event Overview list. Keep it short (1–2 sentences) for best results.

---

## Recurring Events

Recurring events are stored as a **single post** — no duplicate posts are created. Occurrences are generated on the fly in PHP when events are loaded.

- Recurrence interval: **weekly**
- Configurable weekday and end date (`recurring_until`)
- The first occurrence is always the post's own `start_date`
- Subsequent occurrences are generated from `start_date + 1 day` onwards to prevent duplicates
- In the Event Overview list, recurring events appear only once (deduplicated by title)

---

## License

MIT — feel free to use and adapt for client projects.
