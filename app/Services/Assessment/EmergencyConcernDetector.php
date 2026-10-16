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
            '/\b(not breathing|stopped breathing|barely breathing|blue lips|lips (are |turning )?blue|baby (is )?(turning )?blue|child (is )?(turning )?blue)\b/u',
            '/\b(struggling|struggle|difficulty|trouble|problems?) (to breathe|breathing)\b/u',
            '/\b(have|having|with) (difficulty|trouble|problems?) breathing\b/u',
            '/\b(choking|gasping for (air|breath)|severe shortness of breath|cannot get enough air)\b/u',
        ],
        RedFlagCode::UnresponsiveOrSevereNeurologicalChange->value => [
            '/\b(unconscious|unresponsive|not responding|collapsed|fainted|passed out|black(?:ed)? out|no pulse|cannot find (a )?pulse|cant find (a )?pulse|can\'t find (a )?pulse|cardiac arrest|heart (has )?stopped)\b/u',
            '/\b(cannot|cant|can\'t|unable to) wake\b/u',
            '/\b(will not|wont|won\'t) wake( up)?\b/u',
            '/\b(seizure|seizing|convulsing|having a fit|fits? (will not|wont|won\'t) stop|repeated seizures|sudden severe confusion)\b/u',
            '/\b(baby|child|person|patient|he|she|they) (is |has gone )?(limp|unresponsive)\b/u',
        ],
        RedFlagCode::SevereUncontrolledBleeding->value => [
            '/\b(bleeding (heavily|badly|uncontrollably)|uncontrolled bleeding|blood (is )?spurting)\b/u',
            '/\b(bleeding|blood) (will not|wont|won\'t|cannot|cant|can\'t) stop\b/u',
            '/\b(bleeding|blood) (from|coming from) (my |the )?(arm|arms|leg|legs)\b/u',
            '/\b(my |the )?(arm|arms|leg|legs) (is |are )?(bleeding|losing blood)\b/u',
            '/\b(bleeding|blood) (from|coming from) (my |the )?(eye|eyes|ear|ears)\b/u',
            '/\b(eye|eyes|ear|ears) (is |are )?(bleeding|leaking blood)\b/u',
            '/\b(bleeding|blood) (from|coming from) (my |the )?(ear|ears).{0,30}(nose|nostril)\b/u',
            '/\b(vomiting|throwing up|coughing) blood\b/u',
            '/\b(pooping|pooing|defecating|peeing|weeing|urinating|passing) blood\b/u',
            '/\bblood (in|with) (my |the )?(poop|poo|stool|faeces|feces|bowel movement|urine|pee|wee)\b/u',
            '/\b(bloody|black tarry|tarry black) (poop|poo|stool|faeces|feces|urine|pee|wee)\b/u',
            '/\b(po(op)?|stool|faeces|feces) (is |looks )?(black and tarry|tarry and black)\b/u',
            '/\b(rectal bleeding|bleeding from (my |the )?(rectum|anus|back passage))\b/u',
            '/\bblood.{0,24}\b(when|while) (i |he |she |they )?(pee|pees|urinate|urinates|poop|poops|defecate|defecates)\b/u',
            '/\b(urine|pee|wee) (is |looks |has become )?(bloody|red with blood)\b/u',
            '/\b(heavy|uncontrolled|nonstop) nosebleed\b/u',
            '/\b(pregnant|pregnancy).{0,45}(bleeding heavily|heavy bleeding|losing a lot of blood)\b/u',
        ],
        RedFlagCode::PossibleStroke->value => [
            '/\b(face droop|drooping face|face (is )?drooping|one side of (my |the |his |her |their )?face (is )?drooping|one[- ]sided weakness|one side (is )?(weak|numb))\b/u',
            '/\b(slurred speech|speech (is |has become )?slurred|sudden difficulty speaking|suddenly cannot speak)\b/u',
            '/\b(sudden|suddenly).{0,25}(arm|leg|face).{0,20}(weak|numb|paralysed|paralyzed)\b/u',
            '/\b(sudden|suddenly).{0,25}(cannot see|can\'t see|vision loss|lost (my |their )?vision)\b/u',
        ],
        RedFlagCode::ConcerningChestPain->value => [
            '/\b(severe|crushing|squeezing) (chest pain|chest pressure)\b/u',
            '/\bchest (hurts?|is hurting|aches?|pain|pressure|tightness)\b/u',
            '/\b(pain|hurt|aching|pressure|tightness) in (my |the )?chest\b/u',
            '/\bchest (pain|pressure).{0,45}(arm|jaw|back|sweating|breathless|shortness of breath)\b/u',
        ],
        RedFlagCode::SevereAllergicReaction->value => [
            '/\b(anaphylaxis|anaphylactic shock|severe allergic reaction)\b/u',
            '/\b(throat|tongue|face|lips) (is |are )?(closing|swelling|swollen)\b/u',
            '/\ballergic reaction.{0,40}(cannot breathe|difficulty breathing|difficulty swallowing)\b/u',
        ],
        RedFlagCode::MajorTraumaBurnOrPoisoning->value => [
            '/\b(major trauma|serious burn|severe burn|electrical burn|chemical burn|electrocuted|suspected poisoning|has been poisoned|overdose|overdosed|drowning|smoke inhalation|inhaled smoke)\b/u',
            '/\b(hit by (a )?(car|vehicle)|gunshot|shot me|stabbed|large open wound|bullet wound)\b/u',
            '/\b(i|we|they|he|she|someone|somebody) (was|were|have been|has been|got|just got) (shot|stabbed)\b/u',
            '/\b(i think |think )?(i|we|they|he|she|iv|ive)(\'?ve| have| has)? been shot\b/u',
            '/\b(shot|stabbed) (in|through) (my |the |his |her |their )?(head|neck|chest|stomach|abdomen|belly|back|arm|leg)\b/u',
            '/\b(hole|open wound) in (my |the |his |her |their )?(stomach|abdomen|belly|chest)\b/u',
            '/\b(knife|bullet) (is )?(still )?(in|inside) (my |the |his |her |their )?(body|chest|stomach|abdomen|belly|back)\b/u',
            '/\b(serious|bad|major) (car|motorbike|motorcycle|road) (accident|crash)\b/u',
            '/\b(thrown|ejected) from (a |the )?(car|vehicle|motorbike|motorcycle)\b/u',
            '/\b(fell|fallen) from (a |the )?(roof|balcony|height|storey|story|scaffold)\b/u',
            '/\b(serious head injury|skull (is )?(cracked|broken)|head (is )?split open)\b/u',
            '/\b(blast|explosion) (injury|wound|burn)\b/u',
            '/\b(i|we|they|he|she) (just )?(broke|fractured) (my |our |their |his |her )?(leg|arm|hip|pelvis)\b/u',
            '/\b(my |the |his |her |their )?(leg|arm|hip|pelvis) (is |looks |seems )?(broken|fractured|badly deformed)\b/u',
            '/\b(open|compound) fracture\b/u',
            '/\b(drank|swallowed|took|inhaled) (bleach|poison|pesticide|insecticide|chemical|too many (pills|tablets)|chemical fumes?)\b/u',
            '/\b(bit(?:ten)? by (a )?(snake|cobra)|snake bite|venomous bite)\b/u',
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
            [
                'bleading',
                'brething',
                'unconcious',
                'unconsious',
                'seazure',
                'seziure',
                'stabed',
                'vomitting',
                'vommiting',
            ],
            [
                'bleeding',
                'breathing',
                'unconscious',
                'unconscious',
                'seizure',
                'seizure',
                'stabbed',
                'vomiting',
                'vomiting',
            ],
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
