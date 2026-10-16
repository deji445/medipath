<?php

namespace Tests\Unit;

use App\Enums\RedFlagCode;
use App\Services\Assessment\DeterministicEmergencyScreener;
use App\Services\Assessment\EmergencyConcernDetector;
use App\Services\Assessment\EmergencyFirstAidGuidance;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ClinicalSafetyBenchmarkCorpus;
use Tests\Fixtures\EmergencyPhraseCorpus;
use Tests\TestCase;

class ClinicalSafetyBenchmarkTest extends TestCase
{
    #[DataProvider('emergencyScenarios')]
    public function test_every_emergency_benchmark_scenario_escalates_deterministically(
        string $message,
        RedFlagCode $expected,
    ): void {
        $matches = app(EmergencyConcernDetector::class)->detect($message);

        $this->assertContains($expected, $matches);
    }

    /** @return iterable<string, array{string, RedFlagCode}> */
    public static function emergencyScenarios(): iterable
    {
        yield from EmergencyPhraseCorpus::cases();
    }

    #[DataProvider('nonEmergencyControls')]
    public function test_non_emergency_controls_do_not_trigger_deterministic_escalation(
        string $message,
    ): void {
        $this->assertSame([], app(EmergencyConcernDetector::class)->detect($message));
    }

    /** @return iterable<string, array{string}> */
    public static function nonEmergencyControls(): iterable
    {
        yield from ClinicalSafetyBenchmarkCorpus::nonEmergencyControls();
    }

    #[DataProvider('firstAidScenarios')]
    public function test_first_aid_is_limited_to_narrowly_supported_injuries(
        string $message,
        bool $shouldProvideGuidance,
        ?string $expectedFragment,
    ): void {
        $steps = app(EmergencyFirstAidGuidance::class)->forText($message);

        if (! $shouldProvideGuidance) {
            $this->assertSame([], $steps);

            return;
        }

        $this->assertNotEmpty($steps);
        $this->assertStringContainsString(
            (string) $expectedFragment,
            implode(' ', $steps),
        );
        $this->assertStringContainsString('112', implode(' ', $steps));
    }

    /** @return iterable<string, array{string, bool, string|null}> */
    public static function firstAidScenarios(): iterable
    {
        yield from ClinicalSafetyBenchmarkCorpus::firstAidCases();
    }

    public function test_emergency_copy_uses_the_configured_ghana_number_only(): void
    {
        config()->set('app.locale', 'en');
        config()->set('medipath.emergency.phone', '112');

        $message = app(DeterministicEmergencyScreener::class)->emergencyChatMessage();

        $this->assertStringContainsString('112', $message);
        $this->assertStringNotContainsString('911', $message);
        $this->assertStringNotContainsString('999', $message);
        $this->assertStringNotContainsString('ambulance has been dispatched', mb_strtolower($message));
    }

    public function test_benchmark_covers_each_supported_text_red_flag_category(): void
    {
        $covered = [];

        foreach (EmergencyPhraseCorpus::cases() as [, $redFlag]) {
            $covered[$redFlag->value] = true;
        }

        $expected = [
            RedFlagCode::SevereBreathingDifficulty,
            RedFlagCode::UnresponsiveOrSevereNeurologicalChange,
            RedFlagCode::SevereUncontrolledBleeding,
            RedFlagCode::PossibleStroke,
            RedFlagCode::ConcerningChestPain,
            RedFlagCode::SevereAllergicReaction,
            RedFlagCode::MajorTraumaBurnOrPoisoning,
            RedFlagCode::ImmediateHarmIntent,
        ];

        foreach ($expected as $redFlag) {
            $this->assertArrayHasKey($redFlag->value, $covered);
        }
    }
}
