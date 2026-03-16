# Migration: training_event → time_event

## Hintergrund

Der Custom Post Type wurde von `training_event` zu `time_event` umbenannt.
WordPress speichert den `post_type` direkt in der Tabelle `wp_posts`, daher
müssen bestehende Einträge einmalig migriert werden.

## Voraussetzungen

- WordPress 6.0+
- PHP 8.2+
- Administrator-Rechte

## Schritt-für-Schritt-Anleitung

### Schritt 1: Plugin-Dateien ersetzen

Die neuen Plugin-Dateien auf den Server laden (z.B. per FTP/SFTP oder Git).
Die geänderten Dateien enthalten bereits den neuen Post-Type `time_event`.

### Schritt 2: Datenbank-Backup erstellen

**Wichtig!** Vor der Migration unbedingt ein vollständiges Datenbank-Backup anlegen.

- Falls UpdraftPlus, BackWPup oder ein anderes Backup-Plugin aktiv ist: darüber ein Backup erstellen.
- Alternativ: Export über phpMyAdmin oder `wp db export` (WP-CLI).

### Schritt 3: Plugin deaktivieren und wieder aktivieren

Im WordPress-Admin unter **Plugins** das Plugin „Drag & Drop Event Calendar"
deaktivieren und anschließend wieder aktivieren.

### Schritt 4: Migrations-Seite aufrufen

Im WP-Admin navigieren zu:

**Time Calendar → Migration**

(Die Seite erscheint nur, wenn die Migration noch nicht durchgeführt wurde.)

### Schritt 5: „Jetzt migrieren" klicken

Auf der Migrations-Seite wird angezeigt:

- Wie viele Posts migriert werden
- Ob ein Backup-Plugin erkannt wurde
- Eine Vorschau (Dry Run) der betroffenen Einträge

Nach Klick auf **„Jetzt migrieren"** wird die Migration durchgeführt:

- `wp_posts.post_type` wird von `training_event` auf `time_event` geändert
- Rewrite-Regeln werden neu generiert
- Die Migration wird als abgeschlossen markiert

### Schritt 6: Permalinks neu speichern

Nach der Migration zu **Einstellungen → Permalinks** navigieren und
auf **„Änderungen speichern"** klicken, um die Permalinks neu zu generieren.

## Was wird migriert?

| Tabelle       | Feld        | Änderung                                    |
|---------------|-------------|---------------------------------------------|
| `wp_posts`    | `post_type` | `training_event` → `time_event`             |

## Was bleibt unverändert?

- **ACF-Felder** (`wp_postmeta`): Bleiben erhalten, da sie über die Post-ID referenziert werden.
- **Anmeldungen** (`wp_tc_registrations`): Bleiben erhalten, da nur die `event_id` (Post-ID) gespeichert wird.
- **URLs**: Der Rewrite-Slug bleibt `/events/`, die öffentlichen URLs ändern sich nicht.

## Rollback

Falls etwas schiefgeht:

1. Datenbank-Backup wiederherstellen
2. Alte Plugin-Dateien wieder einspielen
3. Permalinks neu speichern

Alternativ manuell per SQL:

```sql
UPDATE wp_posts SET post_type = 'training_event' WHERE post_type = 'time_event';
```

(Tabellenpräfix ggf. anpassen.)
