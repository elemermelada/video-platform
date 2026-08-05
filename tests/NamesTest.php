<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use PHPUnit\Framework\TestCase;
use VideoPlatform\Names;

/**
 * The one place case is decided: how names sort and when two of them are one.
 */
final class NamesTest extends TestCase
{
    public function testCapitalsAreNotSortedApartFromLowercase(): void
    {
        $this->assertSame(
            ['action', 'Blade', 'crime', 'Drama'],
            Names::sort(['Drama', 'crime', 'Blade', 'action']),
        );
    }

    public function testTwoSpellingsOfOneNameSortTogetherCapitalFirst(): void
    {
        $this->assertSame(
            ['Action', 'action', 'actor'],
            Names::sort(['actor', 'action', 'Action']),
        );
    }

    public function testSortingIsTotalSoEqualNamesNeverSwapAbout(): void
    {
        $this->assertSame(0, Names::compare('action', 'action'));
        $this->assertSame(
            Names::compare('Action', 'action') > 0,
            Names::compare('action', 'Action') < 0,
        );
    }

    public function testNamesAreTheSameHoweverTheyAreCapitalised(): void
    {
        $this->assertTrue(Names::same('Action', 'action'));
        $this->assertTrue(Names::same('SCI FI', 'sci fi'));
        $this->assertFalse(Names::same('action', 'actions'));
    }

    public function testContainsFindsNamesUnderAnotherCapitalisation(): void
    {
        $this->assertTrue(Names::contains(['Action', 'Heist'], 'action'));
        $this->assertTrue(Names::contains(['action'], 'ACTION'));
        $this->assertFalse(Names::contains(['action'], 'act'));
        $this->assertFalse(Names::contains([], 'action'));
    }

    public function testSpellingsOfOneNameShareOneKey(): void
    {
        $this->assertSame(Names::key('Action'), Names::key('aCTION'));
        $this->assertNotSame(Names::key('action'), Names::key('actions'));
    }
}
