<?php

namespace App\Enums;

enum RedFlagCode: string
{
    case SevereBreathingDifficulty = 'SEVERE_BREATHING_DIFFICULTY';
    case UnresponsiveOrSevereNeurologicalChange = 'UNRESPONSIVE_OR_SEVERE_NEUROLOGICAL_CHANGE';
    case SevereUncontrolledBleeding = 'SEVERE_UNCONTROLLED_BLEEDING';
    case PossibleStroke = 'POSSIBLE_STROKE';
    case ConcerningChestPain = 'CONCERNING_CHEST_PAIN';
    case SevereAllergicReaction = 'SEVERE_ALLERGIC_REACTION';
    case MajorTraumaBurnOrPoisoning = 'MAJOR_TRAUMA_BURN_OR_POISONING';
    case ImmediateHarmIntent = 'IMMEDIATE_HARM_INTENT';
    case CriticalVitalSigns = 'CRITICAL_VITAL_SIGNS';
    case UnspecifiedEmergencyConcern = 'UNSPECIFIED_EMERGENCY_CONCERN';

    public function answerKey(): string
    {
        return match ($this) {
            self::SevereBreathingDifficulty => 'severe_breathing_difficulty',
            self::UnresponsiveOrSevereNeurologicalChange => 'unresponsive_or_severe_neurological_change',
            self::SevereUncontrolledBleeding => 'severe_uncontrolled_bleeding',
            self::PossibleStroke => 'possible_stroke',
            self::ConcerningChestPain => 'concerning_chest_pain',
            self::SevereAllergicReaction => 'severe_allergic_reaction',
            self::MajorTraumaBurnOrPoisoning => 'major_trauma_burn_or_poisoning',
            self::ImmediateHarmIntent => 'immediate_harm_intent',
            self::CriticalVitalSigns => 'critical_vital_signs',
            self::UnspecifiedEmergencyConcern => 'unspecified_emergency_concern',
        };
    }

    public function question(): string
    {
        return match ($this) {
            self::SevereBreathingDifficulty => 'Is the patient unable to breathe normally, choking, or struggling severely to breathe?',
            self::UnresponsiveOrSevereNeurologicalChange => 'Is the patient unconscious, having a seizure, unable to wake, or suddenly severely confused?',
            self::SevereUncontrolledBleeding => 'Is the patient bleeding heavily or is the bleeding not stopping?',
            self::PossibleStroke => 'Does the patient have sudden face drooping, one-sided weakness or numbness, or difficulty speaking?',
            self::ConcerningChestPain => 'Does the patient have severe pressure or squeezing chest pain, especially with breathlessness, sweating, nausea, or pain spreading elsewhere?',
            self::SevereAllergicReaction => 'Does the patient have a possible severe allergic reaction with difficulty breathing or swallowing?',
            self::MajorTraumaBurnOrPoisoning => 'Has the patient experienced major trauma, a serious burn, or suspected poisoning?',
            self::ImmediateHarmIntent => 'Has the patient said they may immediately harm themselves or someone else?',
            self::CriticalVitalSigns => 'Do the recorded vital signs contain a critically abnormal adult reading?',
            self::UnspecifiedEmergencyConcern => 'The patient reports an emergency warning sign or is not sure whether one applies.',
        };
    }

    public function optionLabel(): string
    {
        return match ($this) {
            self::SevereBreathingDifficulty => 'Severe trouble breathing, choking, or being unable to breathe normally',
            self::UnresponsiveOrSevereNeurologicalChange => 'Unconsciousness, a seizure, inability to wake, or sudden severe confusion',
            self::SevereUncontrolledBleeding => 'Heavy bleeding or bleeding that will not stop',
            self::PossibleStroke => 'Sudden face drooping, one-sided weakness or numbness, or difficulty speaking',
            self::ConcerningChestPain => 'Severe pressure or squeezing chest pain, especially with breathlessness, sweating, nausea, or spreading pain',
            self::SevereAllergicReaction => 'A severe allergic reaction with difficulty breathing or swallowing',
            self::MajorTraumaBurnOrPoisoning => 'Major trauma, a serious burn, or suspected poisoning',
            self::ImmediateHarmIntent => 'Immediate risk of harm to self or someone else',
            self::CriticalVitalSigns => 'A critically abnormal adult vital-sign reading',
            self::UnspecifiedEmergencyConcern => 'An emergency warning sign not listed here, or uncertainty about one',
        };
    }

    public function patientMessage(): string
    {
        return 'This answer may indicate an emergency. Seek immediate professional help and go to the nearest appropriate emergency facility.';
    }
}
