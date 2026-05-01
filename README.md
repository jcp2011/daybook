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
  - Emoji picker integrated into the toolbar
- Edit active instructions inline (archived instructions are read-only)
- Archive and restore instructions (archived entries record the archival date/time)
- Delete instructions permanently
- Sort by date ascending or descending (click the Date column header)
- Custom logo: place `assets/logo.png` to display it in the header

## Project Structure

```
.
+-- assets/
|   +-- quill.js          # Quill 1.3.7 (local copy)
|   +-- quill.snow.css    # Quill Snow theme (local copy)
+-- data/                 # SQLite database (git-ignored)
+-- src/
|   +-- functions.php     # All business logic and database functions
+-- tests/
|   +-- Unit/
|   |   +-- FunctionsTest.php
|   +-- bootstrap.php
+-- tools/
|   +-- php-cs-fixer.phar
|   +-- phpstan.phar
|   +-- phpunit.phar
|   +-- SHA256SUMS
+-- .php-cs-fixer.php
+-- CHANGELOG.md
+-- index.php             # Entry point and UI
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

## Logo

Place a file named `logo.png` inside `assets/` to display your logo in the top-right corner of the header. The file is git-ignored so it stays local to each deployment.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full history of changes.
