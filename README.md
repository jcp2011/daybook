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
|   +-- emoji-data.json              # Unicode emoji dataset (emoji-picker-element-data)
|   +-- emoji-picker-element/        # emoji-picker-element web component (local copy)
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

The emoji dataset and the web component are versioned independently. To update either or both to the latest release, run the download script from a machine with internet access:

```bash
bash tools/download-emoji-picker.sh
```

The script uses only `curl` and `tar` — no npm or Node.js required. It fetches the latest versions from the npm registry, replaces `assets/emoji-picker-element/` and `assets/emoji-data.json`, and prints the installed version numbers. Commit the updated files to keep the repository deployable on air-gapped machines.

## Logo

Place a file named `logo.png` inside `assets/` to display your logo in the top-right corner of the header. The file is git-ignored so it stays local to each deployment.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full history of changes.
