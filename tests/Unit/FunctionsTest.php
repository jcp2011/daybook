<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = get_db(':memory:');
    }

    // --- h() ---

    public function test_h_escapes_angle_brackets(): void
    {
        self::assertSame('&lt;script&gt;', h('<script>'));
    }

    public function test_h_escapes_double_quotes(): void
    {
        self::assertSame('&quot;', h('"'));
    }

    public function test_h_escapes_single_quotes(): void
    {
        self::assertSame('&#039;', h("'"));
    }

    public function test_h_handles_empty_string(): void
    {
        self::assertSame('', h(''));
    }

    // --- sanitise_style_attr() ---

    public function test_sanitise_style_attr_keeps_color(): void
    {
        self::assertSame('color: red', sanitise_style_attr('color: red'));
    }

    public function test_sanitise_style_attr_keeps_background_color(): void
    {
        self::assertSame('background-color: #fff', sanitise_style_attr('background-color: #fff'));
    }

    public function test_sanitise_style_attr_keeps_font_size(): void
    {
        self::assertSame('font-size: 20px', sanitise_style_attr('font-size: 20px'));
    }

    public function test_sanitise_style_attr_strips_unknown_property(): void
    {
        self::assertSame('', sanitise_style_attr('font-weight: bold'));
    }

    public function test_sanitise_style_attr_strips_expression_attack(): void
    {
        self::assertSame('', sanitise_style_attr('behavior: expression(alert(1))'));
    }

    public function test_sanitise_style_attr_keeps_allowed_and_strips_unknown(): void
    {
        $result = sanitise_style_attr('color: red; font-weight: bold; font-size: 14px');
        self::assertSame('color: red; font-size: 14px', $result);
    }

    public function test_sanitise_style_attr_handles_empty_string(): void
    {
        self::assertSame('', sanitise_style_attr(''));
    }

    // --- sanitise_rich_html() ---

    public function test_sanitise_rich_html_allows_paragraph(): void
    {
        $result = sanitise_rich_html('<p>Hello</p>');
        self::assertStringContainsString('<p>Hello</p>', $result);
    }

    public function test_sanitise_rich_html_allows_strong_and_em(): void
    {
        $result = sanitise_rich_html('<p><strong>bold</strong> <em>italic</em></p>');
        self::assertStringContainsString('<strong>bold</strong>', $result);
        self::assertStringContainsString('<em>italic</em>', $result);
    }

    public function test_sanitise_rich_html_strips_script_tag(): void
    {
        $result = sanitise_rich_html('<script>alert(1)</script><p>safe</p>');
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringNotContainsString('</script>', $result);
        self::assertStringContainsString('<p>safe</p>', $result);
    }

    public function test_sanitise_rich_html_strips_onclick_attribute(): void
    {
        $result = sanitise_rich_html('<p onclick="alert(1)">text</p>');
        self::assertStringNotContainsString('onclick', $result);
        self::assertStringContainsString('text', $result);
    }

    public function test_sanitise_rich_html_strips_anchor_tag(): void
    {
        $result = sanitise_rich_html('<a href="javascript:alert(1)">click</a>');
        self::assertStringNotContainsString('<a', $result);
        self::assertStringNotContainsString('javascript', $result);
    }

    public function test_sanitise_rich_html_allows_https_link(): void
    {
        $result = sanitise_rich_html('<p><a href="https://example.com">Visit</a></p>');
        self::assertStringContainsString('href="https://example.com"', $result);
        self::assertStringContainsString('Visit', $result);
    }

    public function test_sanitise_rich_html_allows_http_link(): void
    {
        $result = sanitise_rich_html('<p><a href="http://example.com">Visit</a></p>');
        self::assertStringContainsString('href="http://example.com"', $result);
    }

    public function test_sanitise_rich_html_allows_mailto_link(): void
    {
        $result = sanitise_rich_html('<p><a href="mailto:test@example.com">Email</a></p>');
        self::assertStringContainsString('href="mailto:test@example.com"', $result);
    }

    public function test_sanitise_rich_html_adds_rel_noopener_to_links(): void
    {
        $result = sanitise_rich_html('<p><a href="https://example.com">Visit</a></p>');
        self::assertStringContainsString('rel="noopener noreferrer"', $result);
        self::assertStringContainsString('target="_blank"', $result);
    }

    public function test_sanitise_rich_html_strips_data_href(): void
    {
        $result = sanitise_rich_html('<p><a href="data:text/html,xss">click</a></p>');
        self::assertStringNotContainsString('<a', $result);
        self::assertStringNotContainsString('data:', $result);
    }

    public function test_sanitise_rich_html_strips_link_with_no_href(): void
    {
        $result = sanitise_rich_html('<p><a>bare link</a></p>');
        self::assertStringNotContainsString('<a', $result);
        self::assertStringContainsString('bare link', $result);
    }

    public function test_sanitise_rich_html_keeps_allowed_style_property(): void
    {
        $result = sanitise_rich_html('<span style="color: red;">text</span>');
        self::assertStringContainsString('color: red', $result);
    }

    public function test_sanitise_rich_html_strips_disallowed_style_property(): void
    {
        $result = sanitise_rich_html('<span style="font-weight: bold;">text</span>');
        self::assertStringNotContainsString('font-weight', $result);
        self::assertStringContainsString('text', $result);
    }

    public function test_sanitise_rich_html_allows_ordered_list(): void
    {
        $result = sanitise_rich_html('<ol><li>First</li><li>Second</li></ol>');
        self::assertStringContainsString('<ol>', $result);
        self::assertStringContainsString('<li>First</li>', $result);
    }

    public function test_sanitise_rich_html_allows_unordered_list(): void
    {
        $result = sanitise_rich_html('<ul><li>Item</li></ul>');
        self::assertStringContainsString('<ul>', $result);
        self::assertStringContainsString('<li>Item</li>', $result);
    }

    public function test_sanitise_rich_html_strips_img_with_onerror(): void
    {
        $result = sanitise_rich_html('<img src="x" onerror="alert(1)">');
        self::assertStringNotContainsString('<img', $result);
        self::assertStringNotContainsString('onerror', $result);
    }

    // --- DB integration tests ---

    public function test_add_and_fetch_active_instruction(): void
    {
        add_instruction($this->db, '2026-05-01', 'Test instruction', false);
        $rows = fetch_instructions($this->db, false);

        self::assertCount(1, $rows);
        self::assertSame('Test instruction', $rows[0]['description']);
        self::assertSame('2026-05-01', $rows[0]['date']);
        self::assertSame(0, (int) $rows[0]['is_rich']);
    }

    public function test_add_rich_instruction_stores_flag(): void
    {
        add_instruction($this->db, '2026-05-01', '<p>Rich</p>', true);
        $rows = fetch_instructions($this->db, false);

        self::assertSame(1, (int) $rows[0]['is_rich']);
    }

    public function test_delete_instruction_removes_row(): void
    {
        add_instruction($this->db, '2026-05-01', 'To delete', false);
        $id = (int) fetch_instructions($this->db, false)[0]['id'];

        delete_instruction($this->db, $id);

        self::assertCount(0, fetch_instructions($this->db, false));
    }

    public function test_archive_instruction_moves_to_archived(): void
    {
        add_instruction($this->db, '2026-05-01', 'To archive', false);
        $id = (int) fetch_instructions($this->db, false)[0]['id'];

        set_archived($this->db, $id, true);

        self::assertCount(0, fetch_instructions($this->db, false));
        self::assertCount(1, fetch_instructions($this->db, true));
    }

    public function test_archive_instruction_sets_archived_at(): void
    {
        add_instruction($this->db, '2026-05-01', 'To archive', false);
        $id = (int) fetch_instructions($this->db, false)[0]['id'];

        set_archived($this->db, $id, true);

        $row = fetch_instructions($this->db, true)[0];
        self::assertNotNull($row['archived_at']);
        self::assertNotEmpty((string) $row['archived_at']);
    }

    public function test_unarchive_instruction_restores_to_active(): void
    {
        add_instruction($this->db, '2026-05-01', 'Archived', false);
        $id = (int) fetch_instructions($this->db, false)[0]['id'];
        set_archived($this->db, $id, true);

        set_archived($this->db, $id, false);

        self::assertCount(1, fetch_instructions($this->db, false));
        self::assertCount(0, fetch_instructions($this->db, true));
    }

    public function test_unarchive_clears_archived_at(): void
    {
        add_instruction($this->db, '2026-05-01', 'Archived', false);
        $id = (int) fetch_instructions($this->db, false)[0]['id'];
        set_archived($this->db, $id, true);

        set_archived($this->db, $id, false);

        $row = fetch_instructions($this->db, false)[0];
        self::assertNull($row['archived_at']);
    }

    public function test_update_instruction_changes_date_and_description(): void
    {
        add_instruction($this->db, '2026-05-01', 'Original', false);
        $id = (int) fetch_instructions($this->db, false)[0]['id'];

        update_instruction($this->db, $id, '2026-06-01', '<p>Updated</p>', true);

        $row = fetch_instructions($this->db, false)[0];
        self::assertSame('2026-06-01', (string) $row['date']);
        self::assertSame('<p>Updated</p>', (string) $row['description']);
        self::assertSame(1, (int) $row['is_rich']);
    }

    public function test_update_instruction_does_not_affect_archived_rows(): void
    {
        add_instruction($this->db, '2026-05-01', 'Original', false);
        $id = (int) fetch_instructions($this->db, false)[0]['id'];
        set_archived($this->db, $id, true);

        update_instruction($this->db, $id, '2026-06-01', 'Updated', false);

        $row = fetch_instructions($this->db, true)[0];
        self::assertSame('Original', (string) $row['description']);
    }

    public function test_fetch_instructions_orders_by_date_ascending(): void
    {
        add_instruction($this->db, '2026-01-01', 'First', false);
        add_instruction($this->db, '2026-03-01', 'Third', false);
        add_instruction($this->db, '2026-02-01', 'Second', false);

        $rows = fetch_instructions($this->db, false, 'asc');

        self::assertSame('First', (string) $rows[0]['description']);
        self::assertSame('Second', (string) $rows[1]['description']);
        self::assertSame('Third', (string) $rows[2]['description']);
    }

    public function test_fetch_instructions_orders_by_date_descending(): void
    {
        add_instruction($this->db, '2026-01-01', 'First', false);
        add_instruction($this->db, '2026-03-01', 'Third', false);
        add_instruction($this->db, '2026-02-01', 'Second', false);

        $rows = fetch_instructions($this->db, false);

        self::assertSame('Third', (string) $rows[0]['description']);
        self::assertSame('Second', (string) $rows[1]['description']);
        self::assertSame('First', (string) $rows[2]['description']);
    }
}
