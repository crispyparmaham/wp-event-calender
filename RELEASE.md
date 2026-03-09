# Release-Anleitung — Drag & Drop Event Calendar

Neue Versionen werden direkt über GitHub Releases bereitgestellt.
WordPress erkennt das Update automatisch und zeigt es im Plugin-Bereich an.

---

## Schritt-für-Schritt

### 1. Versionsnummer erhöhen

In `functions.php` beide Stellen aktualisieren:

```php
 * Version:      2.0.6          ← Plugin-Header (Zeile 5)

define( 'TC_VERSION', '2.0.6' ); ← Konstante (Zeile 15)
```

> **Wichtig:** Beide Werte müssen identisch sein und exakt mit dem
> GitHub-Release-Tag übereinstimmen (kein "v"-Präfix).

---

### 2. Änderungen committen und pushen

```bash
git add functions.php
git commit -m "Release 2.0.6"
git push origin main
```

---

### 3. Git-Tag erstellen und pushen

```bash
git tag 2.0.6
git push origin 2.0.6
```

---

### 4. GitHub Release erstellen

**Option A — GitHub CLI (empfohlen):**

```bash
gh release create 2.0.6 \
  --title "Version 2.0.6" \
  --notes "## Was ist neu

- Feature XY hinzugefügt
- Bug ABC behoben"
```

**Option B — GitHub Web-Interface:**

1. Öffne das Repository auf github.com
2. Klicke auf **Releases → Draft a new release**
3. Wähle den Tag `2.0.6`
4. Trage Titel und Changelog ein
5. Klicke **Publish release**

---

### 5. ZIP-Asset anhängen (empfohlen)

GitHub erstellt automatisch ein Quell-ZIP — das reicht für einfache Fälle.

Für saubere Deployments (ohne GitHub-Metadateien, mit korrektem
Ordnernamen) empfiehlt sich ein eigenes ZIP-Asset:

```bash
# Plugin-Ordner als ZIP mit korrektem Ordnernamen verpacken
cd ..
zip -r training-calendar-2.0.6.zip training-calendar/ \
  --exclude "*.git*" \
  --exclude "*/.DS_Store" \
  --exclude "*/node_modules/*"

# Als Asset zum Release hochladen
gh release upload 2.0.6 training-calendar-2.0.6.zip
```

> Wenn ein ZIP-Asset vorhanden ist, bevorzugt der Updater dieses
> gegenüber dem automatisch generierten Quell-ZIP.

---

## WordPress erkennt das Update

Nach dem Release:

- WordPress prüft alle 12 Stunden automatisch auf Updates
- Der Update-Hinweis erscheint unter **Plugins → Update verfügbar**
- Sofortiger Check: **Training Events → Einstellungen → Update →
  "Jetzt auf Updates prüfen"**

---

## Privates Repository

Falls das Repository privat ist, einen GitHub Personal Access Token
erstellen und in den Plugin-Einstellungen hinterlegen:

1. GitHub → **Settings → Developer settings → Personal access tokens**
2. **Fine-grained token** → Repository auswählen → Permission:
   `Contents: Read-only`
3. Token in WordPress eintragen:
   **Training Events → Einstellungen → Update → GitHub Access Token**

---

## Versionierungskonvention

```
MAJOR.MINOR.PATCH

2.0.6
│ │ └── Bugfix / kleines Update
│ └──── Neue Funktion (rückwärtskompatibel)
└────── Breaking Change / komplette Überarbeitung
```

Tag-Format: `2.0.6` (kein "v"-Präfix — WordPress und der Updater
erwarten eine reine Versionsnummer für `version_compare()`).
