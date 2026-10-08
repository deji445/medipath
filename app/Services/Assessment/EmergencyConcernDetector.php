<?php

namespace App\Services\Assessment;

use App\Enums\RedFlagCode;

final class EmergencyConcernDetector
{
    /**
     * @var array<string, array<int, string>>
     */
    private const RULES = [
        RedFlagCode::SevereBreathingDifficulty->value => [
            '/\b(cannot|cant|can\'t|unable to|not) breathe\b/u',
            '/\b(cannot|cant|can\'t|unable to) breath\b/u',
            '/\b(struggling|struggle|difficulty|severe trouble) (to breathe|breathing)\b/u',
            '/\b(choking|gasping for (air|breath)|severe shortness of breath)\b/u',
        ],
        RedFlagCode::UnresponsiveOrSevereNeurologicalChange->value => [
            '/\b(unconscious|unresponsive|not responding|passed out|black(?:ed)? out)\b/u',
            '/\b(cannot|cant|can\'t|unable to) wake\b/u',
            '/\b(seizure|seizing|convulsing|having a fit|sudden severe confusion)\b/u',
        ],
        RedFlagCode::SevereUncontrolledBleeding->value => [
            '/\b(bleeding (heavily|badly|uncontrollably)|uncontrolled bleeding|blood (is )?spurting)\b/u',
            '/\b(bleeding|blood) (will not|wont|won\'t|cannot|cant|can\'t) stop\b/u',
            '/\b(bleeding|blood) (from|coming from) (my |the )?(eye|eyes|ear|ears)\b/u',
            '/\b(eye|eyes|ear|ears) (is |are )?(bleeding|leaking blood)\b/u',
            '/\b(bleeding|blood) (from|coming from) (my |the )?(ear|ears).{0,30}(nose|nostril)\b/u',
            '/\b(vomiting|throwing up|coughing) blood\b/u',
            '/\b(heavy|uncontrolled|nonstop) nosebleed\b/u',
        ],
        RedFlagCode::PossibleStroke->value => [
            '/\b(face droop|drooping face|one[- ]sided weakness|one side (is )?(weak|numb))\b/u',
            '/\b(slurred speech|sudden difficulty speaking|suddenly cannot speak)\b/u',
        ],
        RedFlagCode::ConcerningChestPain->value => [
            '/\b(severe|crushing|squeezing) (chest pain|chest pressure)\b/u',
            '/\bchest (hurts?|is hurting|aches?|pain|pressure|tightness)\b/u',
            '/\b(pain|hurt|aching|pressure|tightness) in (my |the )?chest\b/u',
        ],
        RedFlagCode::SevereAllergicReaction->value => [
            '/\b(anaphylaxis|anaphylactic shock|severe allergic reaction)\b/u',
            '/\b(throat|tongue|face) (is )?(closing|swelling|swollen)\b/u',
            '/\ballergic reaction.{0,40}(cannot breathe|difficulty breathing|difficulty swallowing)\b/u',
        ],
        RedFlagCode::MajorTraumaBurnOrPoisoning->value => [
            '/\b(major trauma|serious burn|severe burn|suspected poisoning|has been poisoned|overdose)\b/u',
            '/\b(hit by (a )?(car|vehicle)|gunshot|shot me|stabbed|large open wound)\b/u',
            '/\b(leg|arm|hand|foot|finger|toe) (is |was |has been |got )?(cut|chopped|torn) off\b/u',
            '/\bcut off (my |the )?(leg|arm|hand|foot|finger|toe)\b/u',
            '/\b(amputated|severed) (leg|arm|hand|foot|finger|toe)\b/u',
        ],
        RedFlagCode::ImmediateHarmIntent->value => [
            '/\b(kill|harm|hurt) (myself|someone|somebody|another person)\b/u',
            '/\b(suicidal|suicide plan|end my life)\b/u',
        ],
    ];

    /** @return array<int, RedFlagCode> */
    public function detect(string $concern): array
    {
        $text = mb_strtolower(str_replace(['’', '‘'], "'", trim($concern)));
        $text = str_replace(
            ['bleading', 'unconcious', 'unconsious', 'seazure'],
            ['bleeding', 'unconscious', 'unconscious', 'seizure'],
            $text,
        );
        $matches = [];

        foreach (self::RULES as $code => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->hasCurrentMatch($pattern, $text)) {
                    $matches[] = RedFlagCode::from($code);
                    break;
                }
            }
        }

        return $matches;
    }

    private function hasCurrentMatch(string $pattern, string $text): bool
    {
        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return false;
        }

        foreach ($matches[0] as [$match, $offset]) {
            $prefix = substr($text, max(0, $offset - 55), min(55, $offset));

            if (! $this->isNegated($prefix, $match)) {
                return true;
            }
        }

        return false;
    }

    private function isNegated(string $prefix, string $match): bool
    {
        if (preg_match('/^(not|cannot|cant|can\'t)\b/u', $match) === 1) {
            return false;
        }

        return preg_match(
            '/\b(no|not|never|without|denies|denied|do not|don\'t|does not|doesn\'t|is not|isn\'t|was not|wasn\'t|no longer)\b(?:\s+[\pL\pN\'’-]+){0,5}\s*$/u',
            $prefix,
        ) === 1;
    }
}
