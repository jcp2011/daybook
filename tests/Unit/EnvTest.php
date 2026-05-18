<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalEnv;

    private string $tmpFile;

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $this->tmpFile     = tempnam(sys_get_temp_dir(), 'daybook_env_test_');
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;

        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function test_load_parses_key_value_pairs(): void
    {
        file_put_contents($this->tmpFile, "FOO=bar\nBAZ=qux\n");
        Env::load($this->tmpFile);

        self::assertSame('bar', $_ENV['FOO']);
        self::assertSame('qux', $_ENV['BAZ']);
    }

    public function test_load_strips_double_quotes(): void
    {
        file_put_contents($this->tmpFile, 'FOO="hello world"');
        Env::load($this->tmpFile);

        self::assertSame('hello world', $_ENV['FOO']);
    }

    public function test_load_strips_single_quotes(): void
    {
        file_put_contents($this->tmpFile, "FOO='hello world'");
        Env::load($this->tmpFile);

        self::assertSame('hello world', $_ENV['FOO']);
    }

    public function test_load_ignores_comment_lines(): void
    {
        file_put_contents($this->tmpFile, "# this is a comment\nFOO=bar");
        Env::load($this->tmpFile);

        self::assertSame('bar', $_ENV['FOO']);
        self::assertArrayNotHasKey('# this is a comment', $_ENV);
    }

    public function test_load_ignores_blank_lines(): void
    {
        file_put_contents($this->tmpFile, "\n\nFOO=bar\n\n");
        Env::load($this->tmpFile);

        self::assertSame('bar', $_ENV['FOO']);
    }

    public function test_load_handles_value_containing_equals(): void
    {
        file_put_contents($this->tmpFile, 'FOO=a=b=c');
        Env::load($this->tmpFile);

        self::assertSame('a=b=c', $_ENV['FOO']);
    }

    public function test_load_does_nothing_when_file_absent(): void
    {
        $before = $_ENV;
        Env::load('/non/existent/path/.env');
        self::assertSame($before, $_ENV);
    }

    public function test_get_returns_value_when_set(): void
    {
        $_ENV['FOO'] = 'bar';
        self::assertSame('bar', Env::get('FOO'));
    }

    public function test_get_returns_null_when_absent(): void
    {
        unset($_ENV['DAYBOOK_TEST_ABSENT']);
        self::assertNull(Env::get('DAYBOOK_TEST_ABSENT'));
    }

    public function test_require_returns_value_when_set(): void
    {
        $_ENV['FOO'] = 'bar';
        self::assertSame('bar', Env::require('FOO'));
    }

    public function test_require_throws_when_key_absent(): void
    {
        unset($_ENV['DAYBOOK_TEST_ABSENT']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/DAYBOOK_TEST_ABSENT/');
        Env::require('DAYBOOK_TEST_ABSENT');
    }
}
