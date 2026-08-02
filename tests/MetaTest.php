<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use JsonException;
use PHPUnit\Framework\TestCase;
use VideoPlatform\Meta;

final class MetaTest extends TestCase
{
    public function testParsesLegacyRateTagsAndAuthors(): void
    {
        $meta = Meta::parse("3:action:scifi;nolan:kubrick;\n");

        $this->assertSame(3, $meta->rate);
        $this->assertSame(['action', 'scifi'], $meta->tags);
        $this->assertSame(['nolan', 'kubrick'], $meta->authors);
    }

    public function testRoundTripsThroughJson(): void
    {
        $meta = new Meta(4, ['action', 'scifi'], ['nolan']);

        $decoded = Meta::fromJson($meta->toJson());

        $this->assertSame(4, $decoded->rate);
        $this->assertSame(['action', 'scifi'], $decoded->tags);
        $this->assertSame(['nolan'], $decoded->authors);
    }

    public function testJsonUsesTheDocumentedShape(): void
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((new Meta(3, ['a'], ['b']))->toJson(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['rate' => 3, 'tags' => ['a'], 'authors' => ['b']], $decoded);
    }

    public function testJsonKeepsSlashesAndUnicodeReadable(): void
    {
        $json = (new Meta(0, ['sci-fi/noir'], ['Émile']))->toJson();

        $this->assertStringContainsString('sci-fi/noir', $json);
        $this->assertStringContainsString('Émile', $json);
    }

    public function testFromJsonToleratesMissingFields(): void
    {
        $meta = Meta::fromJson('{}');

        $this->assertSame(0, $meta->rate);
        $this->assertSame([], $meta->tags);
        $this->assertSame([], $meta->authors);
    }

    public function testFromJsonDropsNonScalarAndEmptyEntries(): void
    {
        $meta = Meta::fromJson('{"rate":"5","tags":["  action  ","",null,["x"]],"authors":[]}');

        $this->assertSame(5, $meta->rate);
        $this->assertSame(['action'], $meta->tags);
        $this->assertSame([], $meta->authors);
    }

    public function testFromArrayAcceptsCommaSeparatedFormInput(): void
    {
        $meta = Meta::fromArray(['rate' => '2', 'tags' => 'action, scifi ,', 'authors' => 'nolan']);

        $this->assertSame(2, $meta->rate);
        $this->assertSame(['action', 'scifi'], $meta->tags);
        $this->assertSame(['nolan'], $meta->authors);
    }

    public function testFromJsonRejectsMalformedJson(): void
    {
        $this->expectException(JsonException::class);

        Meta::fromJson('{not json');
    }

    public function testFromJsonRejectsNonObjectJson(): void
    {
        $this->expectException(JsonException::class);

        Meta::fromJson('"3:action;nolan;"');
    }

    public function testEmptyMeta(): void
    {
        $meta = Meta::empty();

        $this->assertSame(0, $meta->rate);
        $this->assertSame([], $meta->tags);
        $this->assertSame([], $meta->authors);
        $this->assertNull($meta->date);
    }

    public function testKeepsAnIsoDateAndRoundTripsIt(): void
    {
        $meta = new Meta(3, [], [], '2024-05-06');

        $this->assertSame('2024-05-06', $meta->date);
        $this->assertSame('2024-05-06', Meta::fromJson($meta->toJson())->date);
    }

    public function testKeepsTheTimeWhenTheSidecarCarriesOne(): void
    {
        $this->assertSame('2024-05-06T21:30:00', (new Meta(0, [], [], '2024-05-06T21:30:00'))->date);
        $this->assertSame('2024-05-06T21:30:00', (new Meta(0, [], [], '2024-05-06 21:30'))->date);
    }

    public function testDropsTheDateItCannotRead(): void
    {
        $this->assertNull((new Meta(0, [], [], 'last tuesday'))->date);
        $this->assertNull((new Meta(0, [], [], '06/05/2024'))->date);
        $this->assertNull((new Meta(0, [], [], '2024-13-45'))->date);
        $this->assertNull((new Meta(0, [], [], '2024-05-06T21:30:00 and then some'))->date);
        $this->assertNull((new Meta(0, [], [], '  '))->date);
        $this->assertNull(Meta::fromArray(['date' => ['2024-05-06']])->date);
    }

    public function testDateKeyIsOmittedWhenThereIsNoDate(): void
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((new Meta(1, [], []))->toJson(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('date', $decoded);
    }

    public function testTimestampSortsByTheStoredDate(): void
    {
        $earlier = new Meta(0, [], [], '2024-05-06');
        $later = new Meta(0, [], [], '2024-05-07');

        $this->assertNotNull($earlier->timestamp());
        $this->assertLessThan((int) $later->timestamp(), (int) $earlier->timestamp());
        $this->assertNull((new Meta(0, [], []))->timestamp());
    }

    public function testDateOnlyFeedsTheDatePicker(): void
    {
        $this->assertSame('2024-05-06', (new Meta(0, [], [], '2024-05-06T21:30:00'))->dateOnly());
        $this->assertSame('', (new Meta(0, [], []))->dateOnly());
    }

    public function testWithDateKeepsEverythingElse(): void
    {
        $meta = (new Meta(4, ['action'], ['nolan']))->withDate('2024-05-06');

        $this->assertSame(4, $meta->rate);
        $this->assertSame(['action'], $meta->tags);
        $this->assertSame(['nolan'], $meta->authors);
        $this->assertSame('2024-05-06', $meta->date);
        $this->assertNull($meta->withDate(null)->date);
    }

    public function testTodayIsStoredInTheCanonicalForm(): void
    {
        $this->assertSame(date('Y-m-d'), Meta::today());
        $this->assertSame(Meta::today(), (new Meta(0, [], [], Meta::today()))->date);
    }
}
