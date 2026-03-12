# Training Calendar

A WordPress plugin for managing and displaying trainings and seminars in an interactive calendar with drag & drop support.

---

## Features

- **Custom Post Type** `training_event` for trainings and seminars
- **ACF field group** with tabs for general info, details, date & time, and pricing
- **Admin calendar** with FullCalendar v6 — drag & drop, resize, and inline event creation
- **Recurring events** — weekly recurrence with a configurable end date
- **Settings page** under Events → Einstellungen for global plugin configuration
- **Light / Dark Mode** toggle — set globally via the settings page, applies to all `[training_calendar]` shortcodes
- **Price bar shortcode** `[training_price_bar]` — fixed bottom bar with price, Early Bird and on-request logic
- **URL parameter filtering** for menu links (e.g. `?tc_type=training`)
- **Client-side caching** — events are fetched once and filtered locally for instant UI response
- **Early Bird pricing logic** — automatically switches between Early Bird and regular price based on deadline

---

## Requirements

- WordPress 6.0+
- [Advanced Custom Fields PRO](https://www.advancedcustomfields.com/) 6.0+
- PHP 8.0+

---

## Installation

1. Clone or download this repository into your plugins directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/your-agency/training-calendar.git
   ```
2. Activate the plugin in **WordPress Admin → Plugins**
3. Make sure ACF PRO is installed and activated
4. Go to **Events → Kalender** to open the admin calendar

---

## File Structure

```
training-calendar/
├── training-calendar.php          # Plugin entry point
├── includes/
│   ├── cpt.php                    # CPT & ACF field group registration
│   ├── ajax.php                   # AJAX handlers (load / create / update events)
│   ├── admin-page.php             # Admin menu page & asset enqueue
│   ├── shortcode-calendar.php     # Frontend calendar shortcode & asset enqueue
│   ├── shortcode-price-bar.php    # Frontend price bar shortcode & asset enqueue
│   ├── registration.php           # Registration data management & AJAX handlers
│   ├── registration-admin-page.php# Registration admin interface
│   └── shortcode-registration.php # Registration form shortcode & asset enqueue
└── assets/
    ├── js/
    │   ├── calendar.js            # Admin calendar logic (FullCalendar)
    │   ├── calendar-frontend.js   # Frontend calendar logic (FullCalendar)
    │   └── registration.js        # Registration form interactivity
    └── css/
        ├── calendar.css           # Admin styles
        ├── calendar-frontend.css  # Frontend calendar styles
        ├── price-bar.css          # Frontend price bar styles
        └── registration.css       # Registration form styles
```

---

## ACF Fields

The plugin registers the following fields on the `training_event` post type:

| Tab | Field | Name | Type |
|---|---|---|---|
| Allgemein | Event-Typ | `event_type` | Select (training / seminar) |
| Allgemein | Einleitungstext | `intro_text` | Textarea |
| Allgemein | Partnerlogo | `partnerlogo` | Image |
| Details | Seminar-/Trainingsleitung | `seminar_leadership` | Text |
| Details | Teilnehmer | `participants` | Text |
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
[training_price_bar]
[training_price_bar link="#contact" link_text="Jetzt buchen"]
[training_price_bar post_id="42" request_text="Termin anfragen"]
```

**Via PHP template:**
```php
<?php echo do_shortcode('[training_price_bar]'); ?>
<?php echo do_shortcode('[training_price_bar link="#contact" link_text="Jetzt buchen"]'); ?>
<?php echo do_shortcode('[training_price_bar post_id="42" request_text="Termin anfragen"]'); ?>
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

Displays a registration form for trainings and seminars. Users can select an event and submit their registration details.

```
[training_registration]
[training_registration event_id="42"]
[training_registration event_id="42" title="Jetzt anmelden"]
```

**Via PHP template:**
```php
<?php echo do_shortcode('[training_registration]'); ?>
<?php echo do_shortcode('[training_registration event_id="42" title="Anmeldeformular"]'); ?>
```

### Features

- **Automatic event detection** — on single event pages, the current event is pre-selected
- **Dynamic event selection** — loads all published events when no event_id specified
- **Event details** — displays trainer, location, and date when event is selected
- **Multi-day support** — shows date picker if event spans multiple days
- **Form fields** — First name, last name, email (required), phone, company, notes
- **Real-time validation** — user-friendly error messages
- **AJAX submission** — no page reload required
- **Confirmation email** — automatic dispatch from configured sender address
- **Database storage** — registrations stored in custom database table `wp_tc_registrations`

### Attributes

| Attribute | Default | Description |
|---|---|---|
| `event_id` | `0` | Post ID of a specific event (optional). If omitted, users can select from all events. Auto-detected on single event pages. |
| `title` | `Anmelden` | Form title heading |

### Admin Management

Navigate to **Events → Anmeldungen** in the WordPress admin to manage registrations.

| Feature | Description |
|---|---|
| **List view** | View all registrations with sortable columns (name, email, event, status, date) |
| **Status dropdown** | Change registration status (pending/confirmed/cancelled) via AJAX |
| **Edit registration** | View/edit registration details and add internal notes |
| **Delete** | Remove registrations with confirmation prompt |
| **Timestamps** | See when each registration was submitted |

### Settings

Navigate to **Events → Einstellungen** to configure:

| Setting | Description |
|---|---|
| **Bestätigungs-E-Mail von** | Sender email address for confirmation emails (defaults to admin email). Uses Fluent SMTP if installed. |

---

## Frontend Shortcode

```
[training_calendar]
[training_calendar type="training"]
[training_calendar type="seminar" view="listMonth"]
```

**Via PHP template:**
```php
<?php echo do_shortcode('[training_calendar]'); ?>
<?php echo do_shortcode('[training_calendar type="training"]'); ?>
<?php echo do_shortcode('[training_calendar type="seminar" view="listMonth"]'); ?>
```

### Attributes

| Attribute | Values | Default | Description |
|---|---|---|---|
| `type` | `all`, `training`, `seminar` | `all` | Pre-selected filter tab |
| `view` | `dayGridMonth`, `timeGridWeek`, `listMonth` | `dayGridMonth` | Initial calendar view |

The **color mode** (light/dark) is configured globally under **Events → Einstellungen** and applies automatically to all shortcode instances — no attribute needed.

### URL Parameter

The `?tc_type=` URL parameter overrides the shortcode `type` attribute. Use this for menu links by appending it to the page URL where the shortcode is placed:

```
/your-page/?tc_type=training   → Gruppentraining pre-selected
/your-page/?tc_type=seminar    → Seminare pre-selected
/your-page/                    → All events (default)
```

---

## AJAX Endpoints

All endpoints require a valid `tc_nonce` nonce.

| Action | Method | Description |
|---|---|---|
| `tc_get_events` | POST | Returns all published events incl. recurring occurrences |
| `tc_create_event` | POST | Creates a new `training_event` post as `publish` |
| `tc_update_event` | POST | Updates `start_date`, `end_date`, `start_time`, `end_time` of a post |

---

## Recurring Events

Recurring events are stored as a **single post** with a recurrence rule — no duplicate posts are created. Occurrences are generated on the fly in PHP when events are loaded.

- Recurrence interval: **weekly**
- Configurable weekday and end date (`recurring_until`)
- The first occurrence is always the post's own `start_date`
- Subsequent occurrences start from `start_date + 1 day` onwards to prevent duplicates

---

## License

MIT — feel free to use and adapt for client projects.