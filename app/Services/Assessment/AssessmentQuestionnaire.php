<?php

namespace App\Services\Assessment;

use App\Enums\RedFlagCode;

final class AssessmentQuestionnaire
{
    /**
     * @return array<int, array{
     *     key: string,
     *     question: string,
     *     help: string,
     *     options: array<int, array{value: string, label: string}>
     * }>
     */
    public function questions(): array
    {
        return [
            [
                'key' => 'duration',
                'question' => 'How long has this concern been present?',
                'help' => 'Choose the closest answer. You can still explain the timing to a clinician later.',
                'options' => [
                    ['value' => 'LESS_THAN_DAY', 'label' => 'Less than 24 hours'],
                    ['value' => 'ONE_TO_THREE_DAYS', 'label' => '1 to 3 days'],
                    ['value' => 'FOUR_TO_SEVEN_DAYS', 'label' => '4 to 7 days'],
                    ['value' => 'MORE_THAN_WEEK', 'label' => 'More than a week'],
                ],
            ],
            [
                'key' => 'trend',
                'question' => 'Since it started, how has it changed?',
                'help' => 'Think about the overall pattern, not just how it feels this minute.',
                'options' => [
                    ['value' => 'IMPROVING', 'label' => 'It is improving'],
                    ['value' => 'UNCHANGED', 'label' => 'It is about the same'],
                    ['value' => 'WORSENING', 'label' => 'It is getting worse'],
                ],
            ],
            [
                'key' => 'intensity',
                'question' => 'How strong or difficult to manage is it right now?',
                'help' => 'Use the description that best matches the patient’s experience.',
                'options' => [
                    ['value' => 'MILD', 'label' => 'Mild: noticeable but manageable'],
                    ['value' => 'MODERATE', 'label' => 'Moderate: uncomfortable or distracting'],
                    ['value' => 'SEVERE', 'label' => 'Severe: very difficult to manage'],
                ],
            ],
            [
                'key' => 'daily_impact',
                'question' => 'How much is it affecting normal daily activity?',
                'help' => 'Examples include walking, eating, sleeping, working, or caring for yourself.',
                'options' => [
                    ['value' => 'LITTLE', 'label' => 'Little or no effect'],
                    ['value' => 'LIMITED', 'label' => 'Some activities are limited'],
                    ['value' => 'UNABLE', 'label' => 'Unable to manage normal activities'],
                ],
            ],
            [
                'key' => 'new_warning_sign',
                'question' => 'Has any new emergency warning sign appeared?',
                'help' => 'Choose one warning sign if it applies now. This immediately stops routine questions.',
                'options' => [
                    ['value' => 'NONE', 'label' => 'No new emergency warning sign'],
                    ...array_map(
                        fn (RedFlagCode $code): array => [
                            'value' => $code->value,
                            'label' => $code->optionLabel(),
                        ],
                        RedFlagCode::cases(),
                    ),
                ],
            ],
            [
                'key' => 'associated_symptoms',
                'question' => 'Is any of the following happening as well?',
                'help' => 'Choose the closest answer. These details help decide how soon care may be needed.',
                'options' => [
                    ['value' => 'NONE', 'label' => 'None of these'],
                    ['value' => 'FEVER', 'label' => 'Fever or chills'],
                    ['value' => 'VOMITING', 'label' => 'Repeated vomiting or unable to keep fluids down'],
                    ['value' => 'DIZZINESS', 'label' => 'Dizziness or feeling faint'],
                    ['value' => 'OTHER', 'label' => 'Another symptom not listed here'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     questions: array<int, array{
     *         key: string,
     *         question: string,
     *         help: string,
     *         options: array<int, array{value: string, label: string}>
     *     }>
     * }>
     */
    public function groups(): array
    {
        $questions = $this->questions();

        return [
            [
                'key' => 'symptom_pattern',
                'title' => 'Tell us about the symptom pattern',
                'description' => 'Choose the closest answer for each question. All three answers are saved together.',
                'questions' => array_slice($questions, 0, 3),
            ],
            [
                'key' => 'impact_and_signs',
                'title' => 'Check impact and related signs',
                'description' => 'These answers help determine urgency. A new warning sign immediately stops routine assessment.',
                'questions' => array_slice($questions, 3, 3),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $answers
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     questions: array<int, array{
     *         key: string,
     *         question: string,
     *         help: string,
     *         options: array<int, array{value: string, label: string}>
     *     }>
     * }|null
     */
    public function nextGroup(array $answers): ?array
    {
        foreach ($this->groups() as $group) {
            foreach ($group['questions'] as $question) {
                if (! array_key_exists($question['key'], $answers)) {
                    return $group;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $answers
     * @return array{key: string, question: string, help: string, options: array<int, array{value: string, label: string}>}|null
     */
    public function nextQuestion(array $answers): ?array
    {
        foreach ($this->questions() as $question) {
            if (! array_key_exists($question['key'], $answers)) {
                return $question;
            }
        }

        return null;
    }

    /** @return array{key: string, question: string, help: string, options: array<int, array{value: string, label: string}>}|null */
    public function question(string $key): ?array
    {
        foreach ($this->questions() as $question) {
            if ($question['key'] === $key) {
                return $question;
            }
        }

        return null;
    }

    public function label(string $key, string $value): string
    {
        $question = $this->question($key);

        foreach ($question['options'] ?? [] as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }

    public function redFlagFor(string $questionKey, string $answer): ?RedFlagCode
    {
        return $questionKey === 'new_warning_sign'
            ? RedFlagCode::tryFrom($answer)
            : null;
    }
}
