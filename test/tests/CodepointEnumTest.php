<?php

declare(strict_types=1);

namespace MLUnipoints\Test;

use BackedEnum;
use Generator;
use MLUnipoints\Block;
use MLUnipoints\Category;
use MLUnipoints\Codepoint;
use MLUnipoints\Info\CategoryInfo;
use MLUnipoints\Info\CodepointInfo;
use MLUnipoints\Info\UnicodeInfo;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;
use ReflectionEnumBackedCase;

final class CodepointEnumTest extends TestCase
{
    public static function provideEnumClasses(): array
    {
        $result = [
            [Codepoint::class, null],
        ];
        foreach (Block::cases() as $block) {
            $class = Codepoint::class . '\\' . $block->name;
            if (enum_exists($class)) {
                $result[] = [$class, $block];
            }
        }
        return $result;
    }

    public static function provideNameTestCases(): array
    {
        return [
            [
                Codepoint::NULL,
                'NULL',
                [
                    'abbreviations' => ['NUL'],
                ],
            ],
            [
                Codepoint::LINE_FEED,
                'LINE FEED',
                [
                    'controlNames' => ['NEW LINE', 'END OF LINE'],
                    'abbreviations' => ['LF', 'EOL'],
                    'unicode1Name' => 'LINE FEED (LF)',
                    'informativeAliases' => ['new line (NL)', 'end of line (EOL)'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideEnumClasses
     */
    public function testUnicodeInfoAttribute(string $className, ?Block $block): void
    {
        $info = UnicodeInfo::from($className);
        $this->assertInstanceOf(UnicodeInfo::class, $info);
        $class = new ReflectionEnum($className);
        $enumAttributes = $class->getAttributes(UnicodeInfo::class);
        $this->assertCount(1, $enumAttributes);
        $this->assertEquals($info, $enumAttributes[0]->newInstance());
        $this->assertMatchesRegularExpression('/^[1-9]\d*(\.\d+)*$/', $info->unicodeVersion);
    }

    /**
     * @dataProvider provideEnumClasses
     */
    public function testEnumType(string $className, ?Block $block): void
    {
        $planeClass = new ReflectionEnum($className);
        $type = $planeClass->getBackingType();
        $this->assertNotNull($type);
        $this->assertTrue($type->isBuiltin());
        $this->assertSame('string', $type->getName());
    }

    public function testEnumeratedCasesList(): void
    {
        $cases = self::provideEnumClasses();
        $this->assertGreaterThan(1, count($cases));
        $this->assertNull($cases[0][1]);
        $this->assertInstanceOf(Block::class, $cases[1][1]);
    }

    /**
     * @dataProvider provideEnumClasses
     */
    public function testInfos(string $className, ?Block $block): void
    {
        foreach (self::listEnumCases($className, $block) as [$case, $previousCase]) {
            $this->testInfo($case, $previousCase, $block);
        }
    }


    /**
     * @dataProvider provideNameTestCases
     */
    public function testNames(Codepoint $codepoint, string $expectedName, array $otherExpectedNames)
    {
        $info = CodepointInfo::from($codepoint);
        $blockSpecificCodepoint = constant(Codepoint::class . "\\{$info->block->name}::{$codepoint->name}");
        $blockSpecificInfo = CodepointInfo::from($blockSpecificCodepoint);
        foreach ([$info, $blockSpecificInfo] as $info) {
            $this->assertSame($expectedName, $info->name);
            foreach ($otherExpectedNames as $property => $expectedNames) {
                $actualNames = $info->{$property};
                if (is_array($expectedNames)) {
                    $this->assertIsArray($actualNames);
                    $commonNames = array_intersect($expectedNames, $actualNames);
                    $this->assertEqualsCanonicalizing($expectedNames, $commonNames);
                } else {
                    $this->assertSame($expectedNames, $actualNames);
                }
            }
        }
    }

    private function testInfo(BackedEnum $case, ?BackedEnum $previousCase, ?Block $block): void
    {
        $info = CodepointInfo::from($case);
        $this->assertInstanceOf(CodepointInfo::class, $info);
        $caseReflection = new ReflectionEnumBackedCase($case, $case->name);
        $caseAttributes = $caseReflection->getAttributes(CodepointInfo::class);
        $this->assertCount(1, $caseAttributes);
        $this->assertEquals($info, $caseAttributes[0]->newInstance());
        $this->assertGreaterThanOrEqual(0, $info->id);
        $this->assertNotSame('', $info->name);
        $this->assertNotSame(Category::Surrogate, $info->category);
        $categoryInfo = CategoryInfo::from($info->category);
        $this->assertSame([], $categoryInfo->childCategories);
        if ($block === null) {
            $this->assertInstanceOf(Codepoint::class, $case);
            $this->assertInstanceOf(Block::class, $info->block);
            if ($previousCase !== null) {
                $this->assertInstanceOf(Codepoint::class, $previousCase);
            }
        } else {
            $this->assertInstanceOf(Codepoint::class . '\\' . $block->name, $case);
            $this->assertNull($info->block);
            if ($previousCase !== null) {
                $this->assertInstanceOf(Codepoint::class . '\\' . $block->name, $previousCase);
            }
        }
        if ($previousCase !== null) {
            $previousInfo = CodepointInfo::from($previousCase);
            $this->assertGreaterThan($previousInfo->id, $info->id);
        }
    }

    private static function listEnumCases(string $className, ?Block $block): Generator
    {
        $class = new ReflectionEnum($className);
        $previousCase = null;
        $index = 0;
        foreach ($class->getCases() as $caseClass) {
            $case = $caseClass->getValue();
            yield $index++ => [$case, $previousCase];
            $previousCase = $case;
        }
    }
}
