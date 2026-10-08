<?php

namespace Tests\Unit\Services\Assessment;

use App\Enums\RedFlagCode;
use App\Services\Assessment\RedFlagDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RedFlagDetectorTest extends TestCase
{
    private RedFlagDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new RedFlagDetector;
    }

    public function test_it_returns_no_matches_when_every_answer_is_false(): void
    {
        $answers = array_fill_keys(
            array_map(fn (RedFlagCode $code): string => $code->answerKey(), RedFlagCode::cases()),
            false,
        );

        $this->assertSame([], $this->detector->detect($answers));
        $this->assertFalse($this->detector->hasEmergency($answers));
    }

    #[DataProvider('redFlagProvider')]
    public function test_each_structured_emergency_answer_maps_to_its_stable_code(RedFlagCode $expected): void
    {
        $matches = $this->detector->detect([$expected->answerKey() => true]);

        $this->assertSame([$expected], $matches);
        $this->assertTrue($this->detector->hasEmergency([$expected->answerKey() => true]));
        $this->assertNotSame('', $expected->question());
        $this->assertNotSame('', $expected->patientMessage());
    }

    public function test_it_returns_every_matching_code_in_a_stable_order(): void
    {
        $matches = $this->detector->detect([
            RedFlagCode::ImmediateHarmIntent->answerKey() => true,
            RedFlagCode::SevereBreathingDifficulty->answerKey() => true,
            RedFlagCode::PossibleStroke->answerKey() => true,
        ]);

        $this->assertSame([
            RedFlagCode::SevereBreathingDifficulty,
            RedFlagCode::PossibleStroke,
            RedFlagCode::ImmediateHarmIntent,
        ], $matches);
    }

    /** @return iterable<string, array{RedFlagCode}> */
    public static function redFlagProvider(): iterable
    {
        foreach (RedFlagCode::cases() as $code) {
            yield $code->value => [$code];
        }
    }
}
