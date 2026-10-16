<?php

namespace Tests\Unit\Services\Assessment;

use App\Enums\RedFlagCode;
use App\Services\Assessment\EmergencyConcernDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\EmergencyPhraseCorpus;

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
        yield from EmergencyPhraseCorpus::cases();
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
        yield 'no breathing difficulty' => ['I am not having trouble breathing'];
        yield 'no bullet wound' => ['There is no bullet wound'];
        yield 'no overdose' => ['She has not overdosed'];
        yield 'no blood in stool' => ['There is no blood in my stool'];
        yield 'no blood in urine' => ['There is no blood in my urine'];
    }
}
