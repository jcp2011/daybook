<?php

declare(strict_types=1);

const DB_PATH            = __DIR__ . '/../data/instructions.db';
const ALLOWED_HTML_TAGS  = ['p', 'br', 'strong', 'em', 'span', 'ol', 'ul', 'li'];
const ALLOWED_STYLE_PROPS = ['color', 'background-color', 'font-size'];

/**
 * Detects the OS timezone from /etc/localtime and applies it to PHP.
 *
 * Falls back to the timezone already configured in php.ini when detection
 * fails (e.g. the symlink is absent or points to an unrecognised zone).
 */
function apply_system_timezone(): void
{
    $link = is_link('/etc/localtime') ? (string) readlink('/etc/localtime') : '';

    if (preg_match('~zoneinfo/(.+)$~', $link, $m) && in_array($m[1], timezone_identifiers_list(), true)) {
        date_default_timezone_set($m[1]);
    }
}

/**
 * Opens the SQLite database and creates the schema if it does not exist.
 *
 * @param string $path Filesystem path to the database file, or ':memory:'.
 * @return PDO Configured database connection.
 * @throws \PDOException If the database cannot be opened or created.
 */
function get_db(string $path = DB_PATH): PDO
{
    if ($path !== ':memory:' && !is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('
        CREATE TABLE IF NOT EXISTS instructions (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            date        TEXT    NOT NULL,
            description TEXT    NOT NULL,
            archived    INTEGER NOT NULL DEFAULT 0,
            archived_at TEXT    DEFAULT NULL,
            is_rich     INTEGER NOT NULL DEFAULT 0
        )
    ');

    return $db;
}

/**
 * Inserts a new instruction record.
 *
 * @param PDO    $db          Database connection.
 * @param string $date        ISO 8601 date string (YYYY-MM-DD).
 * @param string $description Plain text or sanitised rich HTML content.
 * @param bool   $is_rich     True when $description contains rich HTML.
 */
function add_instruction(PDO $db, string $date, string $description, bool $is_rich): void
{
    $stmt = $db->prepare(
        'INSERT INTO instructions (date, description, is_rich) VALUES (?, ?, ?)'
    );
    $stmt->execute([$date, $description, $is_rich ? 1 : 0]);
}

/**
 * Updates the date, description, and rich-text flag of an active instruction.
 *
 * The WHERE clause restricts the update to non-archived rows, preventing edits
 * to archived instructions even via a crafted POST request.
 *
 * @param PDO    $db          Database connection.
 * @param int    $id          Instruction primary key.
 * @param string $date        ISO 8601 date string (YYYY-MM-DD).
 * @param string $description Plain text or sanitised rich HTML content.
 * @param bool   $is_rich     True when $description contains rich HTML.
 */
function update_instruction(PDO $db, int $id, string $date, string $description, bool $is_rich): void
{
    $stmt = $db->prepare(
        'UPDATE instructions SET date = ?, description = ?, is_rich = ? WHERE id = ? AND archived = 0'
    );
    $stmt->execute([$date, $description, $is_rich ? 1 : 0, $id]);
}

/**
 * Permanently deletes an instruction.
 *
 * @param PDO $db Database connection.
 * @param int $id Instruction primary key.
 */
function delete_instruction(PDO $db, int $id): void
{
    $stmt = $db->prepare('DELETE FROM instructions WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Archives or restores an instruction.
 *
 * Sets archived_at to the current local time when archiving; NULL when restoring.
 *
 * @param PDO  $db       Database connection.
 * @param int  $id       Instruction primary key.
 * @param bool $archived True to archive, false to restore.
 */
function set_archived(PDO $db, int $id, bool $archived): void
{
    if ($archived) {
        $stmt = $db->prepare(
            'UPDATE instructions SET archived = 1, archived_at = ? WHERE id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    } else {
        $stmt = $db->prepare(
            'UPDATE instructions SET archived = 0, archived_at = NULL WHERE id = ?'
        );
        $stmt->execute([$id]);
    }
}

/**
 * Fetches all instructions matching the given archive state.
 *
 * Results are ordered by date, then by insertion order, in the requested direction.
 *
 * @param PDO    $db       Database connection.
 * @param bool   $archived True to fetch archived rows, false for active rows.
 * @param string $sort     Sort direction: 'asc' or 'desc' (default).
 * @return list<mixed> Rows from the instructions table, each as an associative array.
 */
function fetch_instructions(PDO $db, bool $archived, string $sort = 'desc'): array
{
    $direction = $sort === 'asc' ? 'ASC' : 'DESC';
    $stmt      = $db->prepare(
        "SELECT * FROM instructions WHERE archived = ? ORDER BY date $direction, id $direction"
    );
    $stmt->execute([$archived ? 1 : 0]);

    return array_values($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Escapes a value for safe HTML output.
 *
 * @param string $value Raw string.
 * @return string HTML-safe string.
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitises rich HTML from the Quill editor before storage.
 *
 * Only elements listed in ALLOWED_HTML_TAGS are kept. All other elements are
 * replaced by their plain-text content. On kept elements, all attributes are
 * stripped except 'style', which is itself filtered to ALLOWED_STYLE_PROPS.
 *
 * @param string $html Untrusted HTML string received from POST input.
 * @return string Safe HTML ready for direct output.
 */
function sanitise_rich_html(string $html): string
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>');
    libxml_clear_errors();

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!($body instanceof DOMElement)) {
        return '';
    }

    sanitise_dom_node($doc, $body);

    $output = '';
    foreach ($body->childNodes as $child) {
        $output .= $doc->saveHTML($child);
    }

    return $output;
}

/**
 * Recursively sanitises child nodes of $parent.
 *
 * Disallowed elements are replaced with their plain-text content.
 * Allowed elements have their attributes sanitised, then their children processed.
 *
 * @param DOMDocument $doc    Owner document, needed to create replacement text nodes.
 * @param DOMNode     $parent Node whose children are sanitised in place.
 */
function sanitise_dom_node(DOMDocument $doc, DOMNode $parent): void
{
    $children = [];
    foreach ($parent->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $node) {
        if ($node->nodeType === XML_TEXT_NODE) {
            continue;
        }

        if (!($node instanceof DOMElement) || !in_array($node->tagName, ALLOWED_HTML_TAGS, true)) {
            $parent->replaceChild($doc->createTextNode($node->textContent), $node);
            continue;
        }

        sanitise_dom_element($node);
        sanitise_dom_node($doc, $node);
    }
}

/**
 * Removes all attributes from $element except 'style'.
 *
 * The 'style' attribute is filtered to keep only properties listed in
 * ALLOWED_STYLE_PROPS. If no safe declarations remain, 'style' is also removed.
 *
 * @param DOMElement $element Element to sanitise in place.
 */
function sanitise_dom_element(DOMElement $element): void
{
    $attr_names = [];
    foreach ($element->attributes as $attr) {
        $attr_names[] = $attr->name;
    }

    foreach ($attr_names as $name) {
        if ($name !== 'style') {
            $element->removeAttribute($name);
            continue;
        }

        $safe = sanitise_style_attr($element->getAttribute('style'));
        if ($safe === '') {
            $element->removeAttribute('style');
        } else {
            $element->setAttribute('style', $safe);
        }
    }
}

/**
 * Filters a CSS style attribute value, keeping only allowed property declarations.
 *
 * @param string $style Raw CSS declaration block (e.g. "color: red; font-weight: bold").
 * @return string Filtered CSS declaration block containing only ALLOWED_STYLE_PROPS.
 */
function sanitise_style_attr(string $style): string
{
    $safe = [];

    foreach (explode(';', $style) as $declaration) {
        $parts = explode(':', $declaration, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $prop  = strtolower(trim($parts[0]));
        $value = trim($parts[1]);

        if ($value !== '' && in_array($prop, ALLOWED_STYLE_PROPS, true)) {
            $safe[] = $prop . ': ' . $value;
        }
    }

    return implode('; ', $safe);
}
