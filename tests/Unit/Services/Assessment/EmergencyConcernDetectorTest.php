<?php

namespace Tests\Unit\Services\Assessment;

use App\Enums\RedFlagCode;
use App\Services\Assessment\EmergencyConcernDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmergencyConcernDetectorTest extends TestCase
{
    #[DataProvider('emergencyPhrases')]
    public function test_it_recognizes_plain_language_emergency_concerns(
        string $concern,
        RedFlagCode $expected,
    ): void {
        $matches = (new EmergencyConcernDetector)->detect($concern);

        $this->assertContains($expected, $matches);
    }

    /** @return iterable<string, array{string, RedFlagCode}> */
    public static function emergencyPhrases(): iterable
    {
        yield 'ear and nose bleeding' => [
            'Bleeding from my ears and nose',
            RedFlagCode::SevereUncontrolledBleeding,
        ];
        yield 'cannot breathe' => ['I cannot breathe', RedFlagCode::SevereBreathingDifficulty];
        yield 'passed out' => ['She passed out and is not responding', RedFlagCode::UnresponsiveOrSevereNeurologicalChange];
        yield 'plain chest pain' => ['My chest hurts', RedFlagCode::ConcerningChestPain];
        yield 'stroke language' => ['One side is weak and my speech is slurred', RedFlagCode::PossibleStroke];
        yield 'severe allergy' => ['My throat is closing after an allergic reaction', RedFlagCode::SevereAllergicReaction];
        yield 'severed limb' => ['My leg was cut off', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'immediate self harm' => ['I want to harm myself', RedFlagCode::ImmediateHarmIntent];
        yield 'common breathing typo' => ['I cant breath', RedFlagCode::SevereBreathingDifficulty];
        yield 'common bleeding typo' => ['My eyes are bleading', RedFlagCode::SevereUncontrolledBleeding];
    }

    #[DataProvider('nonEmergencyNegations')]
    public function test_it_does_not_treat_explicitly_negated_symptoms_as_current_emergencies(string $concern): void
    {
        $this->assertSame([], (new EmergencyConcernDetector)->detect($concern));
    }

    /** @return iterable<string, array{string}> */
    public static function nonEmergencyNegations(): iterable
    {
        yield 'no chest pain' => ['I do not have chest pain'];
        yield 'denies bleeding' => ['The patient denies bleeding heavily'];
        yield 'no longer unconscious' => ['She is no longer unconscious'];
        yield 'without stroke sign' => ['He is speaking normally without slurred speech'];
    }
}
