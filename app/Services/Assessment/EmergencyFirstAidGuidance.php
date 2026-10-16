<?php

namespace App\Services\Assessment;

final class EmergencyFirstAidGuidance
{
    /**
     * Basic bystander actions for narrowly identified injuries only.
     *
     * @return array<int, string>
     */
    public function forText(string $text): array
    {
        $normalized = $this->normalize($text);

        if ($normalized === '' || $this->needsSpecialistInstructions($normalized)) {
            return [];
        }

        $steps = [];

        if ($this->mentionsExternalLimbBleeding($normalized)) {
            $steps = [
                'Use a clean cloth or dressing to apply firm, continuous pressure to the wound.',
                'If blood soaks through, add another cloth or dressing on top. Keep the first one in place.',
                'If something is stuck in the wound, leave it in place and press around it, not on it.',
            ];
        }

        if ($this->mentionsSuspectedLimbFracture($normalized)) {
            $steps[] = 'Keep the injured limb still. Do not straighten it or put weight on it.';
            $steps[] = 'Do not move the person unless the area is unsafe.';
        }

        if ($steps !== []) {
            $steps[] = 'Keep the person comfortable and follow the 112 dispatcher\'s instructions.';
        }

        return array_values(array_unique($steps));
    }

    private function normalize(string $text): string
    {
        $normalized = mb_strtolower(str_replace(['’', '‘'], "'", trim($text)));

        return str_replace(
            ['bleading', 'fracturd', 'brocken'],
            ['bleeding', 'fractured', 'broken'],
            $normalized,
        );
    }

    private function needsSpecialistInstructions(string $text): bool
    {
        return preg_match(
            '/\b(shot|gunshot|bullet|stabbed|knife|amputated|severed|cut off|chopped off|torn off|open fracture|compound fracture|bone (?:is )?(?:showing|visible|sticking out)|head|neck|spine|hip|pelvis|chest|stomach|abdomen|belly|eye|eyes|ear|ears)\b/u',
            $text,
        ) === 1;
    }

    private function mentionsExternalLimbBleeding(string $text): bool
    {
        $bodyPart = '(?:arm|arms|forearm|forearms|elbow|elbows|wrist|wrists|hand|hands|leg|legs|thigh|thighs|knee|knees|shin|shins|ankle|ankles|foot|feet)';

        return preg_match('/\b(?:bleeding|blood (?:is )?(?:coming|flowing)) (?:from )?(?:my |the |his |her |their )?'.$bodyPart.'\b/u', $text) === 1
            || preg_match('/\b(?:my |the |his |her |their )?'.$bodyPart.' (?:is |are )?(?:bleeding|losing blood)\b/u', $text) === 1;
    }

    private function mentionsSuspectedLimbFracture(string $text): bool
    {
        $bodyPart = '(?:arm|arms|leg|legs|hand|hands|foot|feet)';

        return preg_match('/\b(?:i|we|they|he|she|someone|somebody) (?:just )?(?:broke|fractured) (?:my |our |their |his |her )?'.$bodyPart.'\b/u', $text) === 1
            || preg_match('/\b(?:my |the |his |her |their )?'.$bodyPart.' (?:is |looks |seems )?(?:broken|fractured|badly deformed)\b/u', $text) === 1;
    }
}
