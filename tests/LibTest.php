<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib.php';

/**
 * The escaping and query-string handling the plain-PHP pages rely on.
 */
final class LibTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $get;

    protected function setUp(): void
    {
        $this->get = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->get;
    }

    public function testEscapeHtmlEscapesQuotesAsWellAsTags(): void
    {
        $this->assertSame(
            '&lt;script&gt;&quot;x&quot;&amp;&#039;y&#039;&lt;/script&gt;',
            escapeHtml('<script>"x"&\'y\'</script>'),
        );
    }

    public function testFilterFormEscapesTheFilterValues(): void
    {
        $_GET = ['tag' => '"><script>alert(1)</script>', 'author' => '"onmouseover="x'];

        $html = renderFilterForm(gridParams());

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('"onmouseover="', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;', $html);
    }

    public function testPagerButtonEscapesTheFilterValuesItCarriesForward(): void
    {
        $_GET = ['tag' => '"><script>alert(1)</script>'];

        $html = renderPagerButton(gridParams(), 1, '+');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('name="p" value="1"', $html);
    }

    public function testLinkListEscapesTagNames(): void
    {
        $html = renderLinkList(['<b>bold</b>'], 'tag');

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('tag=%3Cb%3Ebold%3C%2Fb%3E', $html);
    }

    public function testGridQueryDropsEmptyFiltersAndPageZero(): void
    {
        $_GET = ['tag' => 'action', 'p' => '0'];

        $this->assertSame('s=20&l=4&o=0&u=d&tag=action', gridQuery());
    }

    public function testSanitizeGridQueryKeepsOnlyTheGridFields(): void
    {
        $this->assertSame(
            'p=2&tag=action',
            sanitizeGridQuery('p=2&tag=action&evil=1'),
        );
    }

    public function testSanitizeGridQueryDropsArraysAndEmptyValues(): void
    {
        $this->assertSame('', sanitizeGridQuery('tag[]=a&author='));
    }

    public function testGridUrlCannotBeSteeredAwayFromTheGrid(): void
    {
        $this->assertSame('index.php', gridUrl("\r\nLocation: http://evil.example/"));
        $this->assertSame('index.php', gridUrl(''));
        $this->assertSame('index.php?tag=action', gridUrl('tag=action'));
    }
}
