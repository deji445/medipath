<?php

namespace Tests\Fixtures;

final class ClinicalSafetyBenchmarkCorpus
{
    /** @return iterable<string, array{string}> */
    public static function nonEmergencyControls(): iterable
    {
        yield 'mild stomach discomfort' => ['My stomach feels mildly uncomfortable after lunch.'];
        yield 'ordinary headache' => ['I have had a mild headache since this morning.'];
        yield 'sore leg' => ['My leg feels sore after a long walk.'];
        yield 'small healed cut' => ['I had a small paper cut, but the bleeding stopped.'];
        yield 'brief resolved nosebleed' => ['I had a small nosebleed earlier and it has stopped.'];
        yield 'routine cold symptoms' => ['I have a runny nose and a mild cough.'];
        yield 'minor ankle discomfort' => ['My ankle aches, but I can walk normally.'];
        yield 'non-urgent rash' => ['I have a small itchy patch on my arm.'];
        yield 'explicitly no breathing problem' => ['I am coughing but I do not have trouble breathing.'];
        yield 'explicitly no chest pain' => ['I have indigestion and no chest pain.'];
        yield 'resolved faint feeling' => ['I felt light-headed earlier but did not faint or pass out.'];
        yield 'family history only' => ['My father had a stroke years ago; I do not have any symptoms.'];
    }

    /**
     * @return iterable<string, array{string, bool, string|null}>
     */
    public static function firstAidCases(): iterable
    {
        yield 'closed leg fracture' => [
            'I broke my leg.',
            true,
            'Keep the injured limb still.',
        ];
        yield 'external arm bleeding' => [
            'My arm is bleeding.',
            true,
            'apply firm, continuous pressure',
        ];
        yield 'penetrating arm injury' => [
            'I was shot in the arm.',
            false,
            null,
        ];
        yield 'open fracture' => [
            'The bone is visible through an open fracture in my leg.',
            false,
            null,
        ];
        yield 'eye bleeding' => [
            'My eye is bleeding.',
            false,
            null,
        ];
    }
}
