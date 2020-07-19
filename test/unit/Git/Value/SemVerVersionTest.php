<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git\Value;

use InvalidArgumentException;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use PHPUnit\Framework\TestCase;

final class SemVerVersionTest extends TestCase
{
    /**
     * @dataProvider detectableReleases
     */
    public function testDetectedReleaseVersions(
        string $milestoneName,
        int $expectedMajor,
        int $expectedMinor,
        string $expectedVersionName
    ): void {
        $version = SemVerVersion::fromMilestoneName($milestoneName);

        self::assertSame($expectedMajor, $version->major());
        self::assertSame($expectedMinor, $version->minor());
        self::assertSame($expectedVersionName, $version->fullReleaseName());
    }

    /**
     * @return array<int, array<int, int|string>>
     *
     * @psalm-return array<int, array{0: string, 1: int, 2: int, 3: string}>
     */
    public function detectableReleases(): array
    {
        return [
            ['1.2.3', 1, 2, '1.2.3'],
            ['v1.2.3', 1, 2, '1.2.3'],
            ['v4.3.2', 4, 3, '4.3.2'],
            ['v44.33.22', 44, 33, '44.33.22'],
        ];
    }

    /**
     * @dataProvider invalidReleases
     */
    public function testRejectsInvalidReleaseStrings(string $invalid): void
    {
        $this->expectException(InvalidArgumentException::class);

        SemVerVersion::fromMilestoneName($invalid);
    }

    /** @return array<int, array<int, string>> */
    public function invalidReleases(): array
    {
        return [
            ['1.2.3.4'],
            ['v1.2.3.4'],
            ['x1.2.3'],
            ['1.2.3 '],
            [' 1.2.3'],
            [''],
            ['potato'],
            ['1.2.'],
            ['1.2'],
        ];
    }

    /**
     * @dataProvider releaseBranchNames
     */
    public function testReleaseBranchNames(string $milestoneName, string $expectedTargetBranch): void
    {
        self::assertEquals(
            BranchName::fromName($expectedTargetBranch),
            SemVerVersion::fromMilestoneName($milestoneName)
                ->targetReleaseBranchName()
        );
    }

    /** @return array<int, array<int, string>> */
    public function releaseBranchNames(): array
    {
        return [
            ['1.2.3', '1.2.x'],
            ['2.0.0', '2.0.x'],
            ['99.99.99', '99.99.x'],
        ];
    }

    /**
     * @dataProvider newMinorReleasesProvider
     */
    public function testIsNewMinorRelease(string $milestoneName, bool $expected): void
    {
        self::assertSame(
            $expected,
            SemVerVersion::fromMilestoneName($milestoneName)
                ->isNewMinorRelease()
        );
    }

    /**
     * @return array<int, array<int, string|bool>>
     *
     * @psalm-return array<int, array{0: string, 1: bool}>
     */
    public function newMinorReleasesProvider(): array
    {
        return [
            ['1.0.0', true],
            ['1.1.0', true],
            ['1.1.1', false],
            ['1.1.2', false],
            ['1.1.90', false],
            ['0.9.0', true],
        ];
    }

    /**
     * @dataProvider lessThanEqualProvider
     */
    public function testLessThanEqual(string $a, string $b, bool $expected): void
    {
        self::assertEquals(
            $expected,
            SemVerVersion::fromMilestoneName($a)
                ->lessThanEqual(SemVerVersion::fromMilestoneName($b))
        );
    }

    /**
     * @return string[][]|bool[][]
     *
     * @psalm-return non-empty-list<array{string, string, bool}>
     */
    public function lessThanEqualProvider(): array
    {
        return [
            ['0.0.1', '0.0.1', true],
            ['0.0.2', '0.0.1', false],
            ['0.0.1', '0.0.2', true],
            ['0.0.1', '0.1.0', true],
            ['0.1.0', '0.0.1', false],
            ['1.0.0', '0.0.1', false],
            ['0.0.1', '1.0.0', true],
            ['1.0.0', '1.0.0', true],
            ['1.0.1', '1.0.0', false],
            ['1.0.0', '1.0.1', true],
            ['0.1.0', '0.1.0', true],
            ['0.1.1', '0.1.0', false],
            ['0.1.0', '0.1.1', true],
            ['0.1.0', '0.1.0', true],
            ['0.1.1', '0.1.0', false],
            ['2.0.0', '1.0.0', false],
            ['1.0.0', '2.0.0', true],
        ];
    }
}
