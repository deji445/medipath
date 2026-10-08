<?php

namespace App\Services\Assessment;

use App\Models\Visit;

interface AssessmentConversationProvider
{
    /**
     * @param  array<string, string>  $answers
     * @param  array<string, string>  $questions
     */
    public function nextTurn(
        Visit $visit,
        array $answers,
        array $questions,
        string $idempotencyKey,
        bool $mustComplete = false,
    ): AssessmentConversationTurn;

    public function model(): string;
}
