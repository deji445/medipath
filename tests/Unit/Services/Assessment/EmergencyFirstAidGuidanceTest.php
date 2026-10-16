<?php

namespace Tests\Unit\Services\Assessment;

use App\Services\Assessment\EmergencyFirstAidGuidance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmergencyFirstAidGuidanceTest extends TestCase
{
    public function test_it_gives_basic_pressure_guidance_for_external_limb_bleeding(): void
    {
        $steps = (new EmergencyFirstAidGuidance)->forText('My arm is bleeding.');

        $this->assertContains(
            'Use a clean cloth or dressing to apply firm, continuous pressure to the wound.',
            $steps,
        );
        $this->assertContains(
            'If blood soaks through, add another cloth or dressing on top. Keep the first one in place.',
            $steps,
        );
    }

    public function test_it_gives_immobilisation_guidance_for_a_suspected_limb_fracture(): void
    {
        $steps = (new EmergencyFirstAidGuidance)->forText('I broke my leg.');

        $this->assertContains(
            'Keep the injured limb still. Do not straighten it or put weight on it.',
            $steps,
        );
        $this->assertContains(
            'Do not move the person unless the area is unsafe.',
            $steps,
        );
    }

    #[DataProvider('specialistInjuries')]
    public function test_it_does_not_oversimplify_injuries_that_need_specialist_instructions(string $message): void
    {
        $this->assertSame([], (new EmergencyFirstAidGuidance)->forText($message));
    }

    /** @return iterable<string, array{string}> */
    public static function specialistInjuries(): iterable
    {
        yield 'internal bleeding' => ['I am vomiting blood'];
        yield 'shooting' => ['I was shot in the arm'];
        yield 'amputation' => ['My leg was cut off'];
        yield 'open fracture' => ['I have an open fracture in my leg'];
        yield 'head injury' => ['My head is bleeding'];
        yield 'eye injury' => ['My eye is bleeding'];
    }
}
