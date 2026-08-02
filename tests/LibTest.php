<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use PHPUnit\Framework\TestCase;
use VideoPlatform\Meta;

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

    //the UI: one stylesheet, a CSS grid, and stars that need no image assets

    public function testRatingIsTextStarsAndNeedsNoImages(): void
    {
        $this->assertSame(
            '<span class="rating" title="3/5">&#9733;&#9733;&#9733;&#9734;&#9734;</span>',
            renderRating(3),
        );

        $this->assertStringNotContainsString('<img', renderRating(0));
    }

    public function testRatingClampsValuesFromOutsideTheZeroToFiveScale(): void
    {
        //a rating read off disk is not trusted to be in range

        $this->assertStringContainsString('title="5/5"', renderRating(9));
        $this->assertStringContainsString('title="0/5"', renderRating(-1));
        $this->assertSame(5, substr_count(renderRating(9), '&#9733;'));
        $this->assertSame(5, substr_count(renderRating(-1), '&#9734;'));
    }

    public function testHeadDeclaresTheDoctypeViewportAndEscapedTitle(): void
    {
        ob_start();
        renderHead('<script>alert(1)</script>');
        $html = (string) ob_get_clean();

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $html);
        $this->assertStringContainsString('<title>&lt;script&gt;alert(1)&lt;/script&gt;</title>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testStylesheetDrivesTheGridAndUsesValidHexColours(): void
    {
        $css = pageStyle();

        $this->assertStringContainsString('repeat(', $css);
        $this->assertStringContainsString('auto-fill', $css);
        $this->assertStringContainsString('aspect-ratio: 16 / 9', $css);
        $this->assertStringNotContainsString('zoom:', $css);

        //every colour is a real hex colour, not a bare "ee1111"

        preg_match_all('/(?:color|background)\s*:\s*([^;]+);/', $css, $colours);

        foreach ($colours[1] as $colour) {
            $this->assertMatchesRegularExpression(
                '/^(#[0-9a-f]{3,8}|var\(--[a-z-]+\)|rgba?\(|none|inherit)/',
                trim($colour),
                'not a valid colour: ' . $colour,
            );
        }
    }

    public function testVideoCardEscapesTheFilenameAndCarriesTheReturnQuery(): void
    {
        $vid = '"><script>alert(1)</script>.mp4';

        $html = renderVideoCard($vid, new Meta(2, ['<b>tag</b>'], ['auth']), 'p=2&tag=action');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>tag</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;tag&lt;/b&gt;', $html);
        $this->assertStringContainsString('ret=p%3D2%26tag%3Daction', $html);
        $this->assertStringContainsString('class="thumb"', $html);
        $this->assertStringContainsString('&#9733;&#9733;&#9734;', $html);
    }

    public function testVideoCardWithoutMetadataShowsNoRating(): void
    {
        $html = renderVideoCard('clip.mp4', null, '');

        $this->assertStringNotContainsString('class="rating"', $html);
        $this->assertStringContainsString('thumbs/clip.mp4.png', $html);
        $this->assertStringContainsString('src=&#039;thumbs/err.png&#039;', $html);
    }

    public function testBarRendersTheFiltersAndPagerExactlyOnce(): void
    {
        ob_start();
        renderBar('index.php', 'index.php', gridParams());
        $html = (string) ob_get_clean();

        $this->assertSame(1, substr_count($html, 'class="filters"'));
        $this->assertSame(1, substr_count($html, 'class="pager"'));
        $this->assertStringContainsString('value="Prev"', $html);
        $this->assertStringNotContainsString('&amp;', $html);
        $this->assertStringContainsString('<b>Home</b>', $html);
    }

    public function testBarOutsideTheGridCarriesNavigationOnly(): void
    {
        ob_start();
        renderBar('edit.php', 'index.php?p=2');
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString('class="filters"', $html);
        $this->assertStringNotContainsString('class="pager"', $html);
        $this->assertStringContainsString('href="index.php?p=2"', $html);
    }

    //the tag/author index, folded into the grid page

    public function testIndexPanelListsCountsAndFilterLinks(): void
    {
        $counts = ['tags' => ['sci fi' => 2], 'authors' => ['ana' => 3]];

        $html = renderIndexPanel($counts, []);

        $this->assertStringContainsString('<details class="index">', $html);
        $this->assertStringContainsString('href="index.php?tag=sci+fi"', $html);
        $this->assertStringContainsString('href="index.php?author=ana"', $html);
        $this->assertStringContainsString('<span class="count">3</span>', $html);
        $this->assertStringContainsString('1 tags &middot; 1 authors', $html);
    }

    public function testIndexPanelListsVideosMissingMetadataAsEditLinks(): void
    {
        $counts = ['tags' => [], 'authors' => []];

        $html = renderIndexPanel($counts, ['a&b.mp4']);

        $this->assertStringContainsString('href="edit.php?vid=a%26b.mp4"', $html);
        $this->assertStringContainsString('a&amp;b.mp4', $html);
        $this->assertStringContainsString('1 missing metadata', $html);
    }

    public function testIndexPanelSaysNoneWhenThereIsNothingToList(): void
    {
        $html = renderIndexPanel(['tags' => [], 'authors' => []], []);

        $this->assertSame(3, substr_count($html, '<span class="count">none</span>'));
        $this->assertStringNotContainsString('missing metadata', $html);
    }

    //filtering: comma-separated tags and authors, and a rating floor

    public function testFilterValuesSplitsAndTrimsAndDropsEmpties(): void
    {
        $this->assertSame(['action', 'sci fi'], filterValues(' action , sci fi ,,'));
        $this->assertSame([], filterValues(''));
        $this->assertSame([], filterValues(' , '));
    }

    public function testEmptyFiltersMatchEverything(): void
    {
        $params = ['tag' => '', 'author' => '', 'rate' => ''];

        $this->assertTrue(matchesFilters(Meta::empty(), $params));
        $this->assertTrue(matchesFilters(new Meta(3, ['action'], ['ana']), $params));
    }

    public function testSeveralTagsNarrowRatherThanWiden(): void
    {
        $meta = new Meta(0, ['action', 'sci fi'], []);

        $both = ['tag' => 'action, sci fi', 'author' => '', 'rate' => ''];
        $extra = ['tag' => 'action, sci fi, noir', 'author' => '', 'rate' => ''];

        $this->assertTrue(matchesFilters($meta, $both));
        $this->assertFalse(matchesFilters($meta, $extra));
    }

    public function testSeveralAuthorsNarrowToo(): void
    {
        $meta = new Meta(0, [], ['ana', 'bo']);

        $this->assertTrue(matchesFilters($meta, ['tag' => '', 'author' => 'ana,bo', 'rate' => '']));
        $this->assertFalse(matchesFilters($meta, ['tag' => '', 'author' => 'ana,cy', 'rate' => '']));
    }

    public function testRatingIsMinimumNotExactMatch(): void
    {
        $params = ['tag' => '', 'author' => '', 'rate' => '3'];

        $this->assertTrue(matchesFilters(new Meta(3, [], []), $params));
        $this->assertTrue(matchesFilters(new Meta(5, [], []), $params));
        $this->assertFalse(matchesFilters(new Meta(2, [], []), $params));
    }

    public function testRatingZeroKeepsEverything(): void
    {
        //"0" is not "no filter": it is a floor every rating clears

        $this->assertTrue(matchesFilters(Meta::empty(), ['tag' => '', 'author' => '', 'rate' => '0']));
        $this->assertTrue(matchesFilters(new Meta(4, [], []), ['tag' => '', 'author' => '', 'rate' => '0']));
    }

    public function testTagMatchingIsExactNotSubstring(): void
    {
        $meta = new Meta(0, ['action'], []);

        $this->assertFalse(matchesFilters($meta, ['tag' => 'act', 'author' => '', 'rate' => '']));
    }

    public function testFilterFormOffersTheKnownTagsAndAuthorsAsAutocomplete(): void
    {
        $html = renderFilterForm(gridParams(), [
            'tags' => ['action', '"><script>alert(1)</script>'],
            'authors' => ['ana'],
        ]);

        $this->assertStringContainsString('name="tag" list="tags"', $html);
        $this->assertStringContainsString('name="author" list="authors"', $html);
        $this->assertStringContainsString('<datalist id="tags"><option value="action">', $html);
        $this->assertStringContainsString('<datalist id="authors"><option value="ana">', $html);

        //the completion script is ours; nothing from a tag name may reach it

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('<option value="&quot;&gt;&lt;script&gt;', $html);
        $this->assertSame(1, substr_count($html, '<script>'));
    }

    public function testCompletionScriptComesWithTheDatalistAndOnlyThen(): void
    {
        //it drives input[list], so it is pointless without one

        $known = ['tags' => ['action'], 'authors' => []];

        $this->assertStringContainsString('input[list]', renderFilterForm(gridParams(), $known));
        $this->assertStringNotContainsString('<script', renderFilterForm(gridParams()));
    }

    public function testFilterFormWithoutAnIndexHasNoDatalist(): void
    {
        $html = renderFilterForm(gridParams());

        $this->assertStringNotContainsString('<datalist', $html);
        $this->assertStringNotContainsString(' list="', $html);
    }
}
