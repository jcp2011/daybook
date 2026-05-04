# Daybook

A self-contained PHP application for managing dated instructions. Supports plain text and rich text entries, archiving, inline editing, and sorting.

## Requirements

- PHP 8.1 or later with the `pdo_sqlite` extension enabled
- A writable `data/` directory (created automatically on first run)

No framework, no Composer, no build step required.

## Setup

```bash
# Clone the repository
git clone https://github.com/jcp2011/daybook.git
cd daybook

# Serve with the built-in PHP server
php -S localhost:8080
```

Open `http://localhost:8080` in a browser.

The SQLite database is created automatically at `data/instructions.db` on the first request.

## Features

- Add instructions with a date/time and a plain or rich text description
- Rich text editor (Quill.js, fully local — no CDN) with:
  - Bold, italic
  - Text colour, background colour, font size
  - Ordered and unordered lists
  - Hyperlinks (http, https, mailto — unsafe schemes are stripped on save)
  - Full Unicode emoji picker (emoji-picker-element, fully local — no CDN) with search and categories
- Edit active instructions inline (archived instructions are read-only)
- Archive and restore instructions (archived entries record the archival date/time)
- Delete instructions permanently
- Sort by date ascending or descending (click the Date column header)
- Timestamps (archived date, default date input) use the server's local timezone, detected automatically from the OS
- Custom logo: place `assets/logo.png` to display it in the header

## Project Structure

```
.
+-- assets/
|   +-- emoji-picker/                # emoji-picker-element web component (local copy)
|   |   +-- picker.js                #   web component implementation
|   |   +-- database.js              #   IndexedDB cache layer (patched for plain HTTP)
|   |   +-- emoji-picker-element.js  #   entry-point re-export
|   |   +-- en/emojibase/data.json   #   emoji dataset - English
|   |   +-- fr/emojibase/data.json   #   emoji dataset - French
|   |   +-- i18n/fr.js              #   UI translations - French
|   +-- fonts/                       # Self-hosted web fonts
|   |   +-- NotoColorEmoji.0.woff2   #   Noto Color Emoji - unicode subsets 0-9
|   |   +-- ...
|   +-- app.css                      # Application stylesheet
|   +-- quill.js                     # Quill 1.3.7 (local copy)
|   +-- quill.snow.css               # Quill Snow theme (local copy)
+-- data/                            # SQLite database (git-ignored)
+-- src/
|   +-- functions.php                # All business logic and database functions
+-- tests/
|   +-- Unit/
|   |   +-- FunctionsTest.php
|   +-- bootstrap.php
+-- tools/
|   +-- download-emoji-picker.sh     # Download/update emoji-picker-element assets
|   +-- download-fonts.sh            # Download/update Noto Color Emoji font
|   +-- php-cs-fixer.phar
|   +-- phpstan.phar
|   +-- phpunit.phar
|   +-- SHA256SUMS
+-- .php-cs-fixer.php
+-- CHANGELOG.md
+-- index.php                        # Entry point and UI
+-- phpstan.neon
+-- phpunit.xml
```

## Development

All tooling runs from local PHARs in `tools/` — no global installation needed.

### Code style

```bash
php tools/php-cs-fixer.phar fix --config=.php-cs-fixer.php
```

### Static analysis

```bash
php tools/phpstan.phar analyse --memory-limit=512M
```

### Tests

```bash
php tools/phpunit.phar
```

## Updating the emoji picker

Run the download script from a machine with internet access:

```bash
bash tools/download-emoji-picker.sh
```

The script uses only `curl`, `tar`, and `python3` — no npm or Node.js required. It:

1. Fetches the latest versions of `emoji-picker-element` and `emoji-picker-element-data` from the npm registry
2. Extracts only the files needed (`picker.js`, `database.js`, `index.js`, data and i18n files)
3. Automatically patches `database.js` with a fallback hash so the picker works on plain HTTP (non-localhost IP addresses where `crypto.subtle` is unavailable)

Commit the updated `assets/emoji-picker/` afterwards to keep the repository deployable on air-gapped machines.

To add or remove languages, edit the `LANGUAGES` variable at the top of the script.

### Note on the database.js patch

`database.js` ships from npm without a `crypto.subtle` fallback. The download script patches `jsonChecksum()` automatically. If after an update you see "Could not load emoji" and `TypeError: Cannot read properties of undefined (reading 'digest')` in the browser console, the patch did not apply cleanly (upstream changed the function). Re-apply it manually: add a guard `if (typeof crypto !== 'undefined' && crypto.subtle)` around the `crypto.subtle.digest()` call and add a djb2 integer hash as the else branch.

## Updating the Noto Color Emoji font

Emoji rendering varies significantly across operating systems — Windows in
particular displays emoji quite differently from macOS or Linux. To ensure a
consistent appearance everywhere, the application uses the
[Noto Color Emoji](https://fonts.google.com/noto/specimen/Noto+Color+Emoji)
font, self-hosted under `assets/fonts/`.

The font is split into 10 unicode-range subsets (totalling ~2 MB). The browser
only downloads the subset(s) it actually needs for the emoji characters present
on the page.

Run the download script from a machine with internet access:

```bash
bash tools/download-fonts.sh
```

The script uses only `curl` — no npm or Node.js required. It fetches the
current woff2 subsets directly from Google Fonts and saves them to
`assets/fonts/`. Commit the updated files afterwards to keep the repository
deployable on air-gapped machines.

## Logo

Place a file named `logo.png` inside `assets/` to display your logo in the top-right corner of the header. The file is git-ignored so it stays local to each deployment.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full history of changes.
