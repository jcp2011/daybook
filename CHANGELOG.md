# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- Initial Daybook application: instruction list with add, archive, and delete
- Rich text editor (Quill 1.3.7, local) supporting bold, italic, text colour,
  background colour, and font size
- Server-side HTML sanitisation for rich content (DOMDocument whitelist)
- Archive timestamp displayed in the archived tab
- Unit and integration test suite (PHPUnit 11.x)
- Tooling config: php-cs-fixer, phpstan (level max), phpunit
