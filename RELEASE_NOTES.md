# Release Notes – Time Calendar v3.0.1

**Veröffentlicht:** März 2026

---

## Zentrales Design System für Dark/Light Mode

### Überblick

Version 3.0.1 führt ein einheitliches Design System ein, das den Dark/Light Mode von Grund auf neu implementiert. Alle Frontend-Komponenten nutzen jetzt ausschließlich zentrale CSS Custom Properties — keine hardcodierten Farben, keine verstreuten Dark-Mode-Blöcke, keine Inkonsistenzen mehr.

---

### Neu

- **`assets/css/design-system.css`** — Neue zentrale Datei mit allen CSS Custom Properties für beide Farbmodi. Wird als erste CSS-Datei geladen und ist die Single Source of Truth für das gesamte Farbsystem.

- **`tc_dark_class()` Helper** — Neue PHP-Funktion in `functions.php`, die `'tc-dark'` oder `''` zurückgibt. Alle Shortcodes nutzen diese Funktion statt eigener Inline-Logik.

- **Smooth Theme Transitions** — Beim Wechsel zwischen Light und Dark Mode werden `background-color`, `color`, `border-color` und `box-shadow` mit sanften CSS-Übergängen animiert.

- **Erweiterte Farbpalette** — Neue semantische Variablen:
  - `--tc-bg-secondary`, `--tc-surface-raised` für differenzierte Hintergründe
  - `--tc-border-strong` für stärkere Rahmen
  - `--tc-text-subtle` für Placeholder- und tertiären Text
  - `--tc-success`, `--tc-warning`, `--tc-danger`, `--tc-info` inkl. Hintergrundvarianten
  - `--tc-shadow-sm`, `--tc-shadow`, `--tc-shadow-lg` für konsistente Schatten
  - `--tc-radius-sm`, `--tc-radius`, `--tc-radius-lg` für einheitliche Radien
  - `--tc-transition` für zentrale Übergangsdauer

### Geändert

- **`calendar-frontend.css`** — Lokale Variablenblöcke (`.tc-frontend-wrap { --tc-bg: ... }`) und der separate `.tc-dark`-Block entfernt. Alle Farben referenzieren jetzt `var(--tc-*)` aus dem Design System.

- **`events.css`** — Eigene `--tce-*` Variablen vollständig entfernt. `@media (prefers-color-scheme: dark)` Block und `.tc-dark .tc-events-wrap` Block entfernt. Nutzt ausschließlich `var(--tc-*)`.

- **`event-list.css`** — Hardcodierter Dark-Mode-Hover-Schatten (`rgba(0,0,0,.35)`) durch `var(--tc-shadow-lg)` ersetzt.

- **`registration.css`** — Kompletter `.tc-dark`-Block mit 20+ Regeln entfernt. Alle `#fff`, `#333`, `#ddd`, `#2d2d2d`, `#3a3a3a` etc. durch semantische Variablen ersetzt. Validierungs- und Statusfarben nutzen jetzt `--tc-danger`, `--tc-success`, `--tc-warning`. Waitlist-Notice wird per CSS-Klasse statt Inline-Styles gestylt.

- **`price-bar.css`** — `.tc-dark .tc-price-bar`-Block (10 Regeln) entfernt. Hintergrund, Rahmen, Text und Schatten nutzen zentrale Variablen. Early-Bird-Grün bleibt als bewusste Ausnahme hardcodiert.

- **`admin/calendar.css`** — FullCalendar-Buttons nutzen `var(--tc-primary)` statt hardcodiertem `#4f46e5`.

- **`shortcode-calendar.php`** — `tc_get_setting('calendar_mode')` durch `tc_dark_class()` ersetzt. CSS-Dependency auf `tc-design-system` gesetzt.

- **`shortcode-events.php`** — Inline Dark-Mode-Logik durch `tc_dark_class()` ersetzt. CSS-Dependency auf `tc-design-system` gesetzt.

- **`shortcode-registration.php`** — Inline Dark-Mode-Logik durch `tc_dark_class()` ersetzt. Inline-Styles der Waitlist-Notice entfernt (Styling via CSS-Klasse). CSS-Dependency auf `tc-design-system` gesetzt.

- **`shortcode-price-bar.php`** — `tc_get_setting('calendar_mode')` + lokale Variable durch `tc_dark_class()` ersetzt. CSS-Dependency auf `tc-design-system` gesetzt.

- **`settings.php`** — `wp_head`-Hook: `--tc-primary-light` Opacity von `0.15` auf `0.12` angepasst für bessere Konsistenz mit dem Design System.

- **`README.md`** — Dateistruktur um `design-system.css` und `price-bar.css` ergänzt. CSS Custom Properties Sektion komplett überarbeitet mit vollständiger Variablenreferenz für Light/Dark Mode.

### Entfernt

- Alle lokalen Dark-Mode-CSS-Blöcke in Einzeldateien (`.tc-dark .tc-registration-form`, `.tc-dark .tc-price-bar`, `.tc-dark .tc-evlist-card:hover`, `.tc-events-wrap.tc-dark`, `@media (prefers-color-scheme: dark)` in events.css)
- Alle eigenständigen CSS-Variablen-Definitionen in Einzeldateien (`.tc-frontend-wrap { --tc-bg: ... }`, `.tc-events-wrap { --tce-bg: ... }`)
- Inline-Styles auf der Waitlist-Notice in `shortcode-registration.php`

---

### Upgrade-Hinweise

- **Keine Breaking Changes** für Endnutzer. Alle Shortcodes funktionieren wie bisher.
- **Themes mit eigenen CSS-Overrides**: Falls du `--tc-bg`, `--tc-text` etc. auf `.tc-frontend-wrap` überschrieben hast, setze diese jetzt auf `:root` oder `.tc-dark`. Die Variablennamen bleiben identisch.
- **`--tce-*` Variablen entfernt**: Falls du in eigenem CSS auf `--tce-bg`, `--tce-text` etc. referenziert hast (aus `events.css`), ersetze diese durch `--tc-bg`, `--tc-text` etc.
- Die **`prefers-color-scheme: dark`** Media Query in `events.css` wurde entfernt. Dark Mode wird ausschließlich über die Plugin-Einstellung gesteuert, nicht über die Betriebssystem-Präferenz.

---

### Betroffene Dateien

```
Neu:
  assets/css/design-system.css

Geändert:
  functions.php
  includes/admin/settings.php
  includes/shortcodes/shortcode-calendar.php
  includes/shortcodes/shortcode-events.php
  includes/shortcodes/shortcode-registration.php
  includes/shortcodes/shortcode-price-bar.php
  assets/css/frontend/calendar-frontend.css
  assets/css/frontend/events.css
  assets/css/frontend/event-list.css
  assets/css/frontend/registration.css
  assets/css/frontend/price-bar.css
  assets/css/admin/calendar.css
  README.md
```
