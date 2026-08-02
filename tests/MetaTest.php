<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use PHPUnit\Framework\TestCase;
use VideoPlatform\Meta;

final class MetaTest extends TestCase
{
    public function testParsesRateTagsAndAuthors(): void
    {
        $meta = Meta::parse("3:action:scifi;nolan:kubrick;\n");

        $this->assertSame(3, $meta->rate);
        $this->assertSame(['action', 'scifi'], $meta->tags);
        $this->assertSame(['nolan', 'kubrick'], $meta->authors);
    }
}
