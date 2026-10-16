<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentReasonCode;
use App\Enums\AssessmentStatus;
use App\Enums\RecommendedAction;
use App\Enums\RedFlagCode;
use App\Enums\SeverityLevel;
use App\Enums\VisitStatus;
use App\Http\Requests\Assessment\StoreAssessmentAnswerRequest;
use App\Http\Requests\Assessment\StoreSafetyScreenRequest;
use App\Models\Visit;
use App\Services\Assessment\AssessmentConversationProvider;
use App\Services\Assessment\AssessmentProvider;
use App\Services\Assessment\AssessmentQuestionnaire;
use App\Services\Assessment\AssessmentResult;
use App\Services\Assessment\AssessmentUnavailableException;
use App\Services\Assessment\DeterministicEmergencyScreener;
use App\Services\Assessment\RedFlagDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PatientAssessmentController extends Controller
{
    public function safety(Request $request): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        if ($visit->assessment?->severity === SeverityLevel::High
            || $visit->assessment?->deterministic_red_flag_match) {
            return to_route('assessment.emergency');
        }

        if (isset($visit->intake_answers['safety_screen'])) {
            return to_route('assessment.ready');
        }

        return Inertia::render('assessment/Safety');
    }

    public function storeSafety(
        StoreSafetyScreenRequest $request,
        RedFlagDetector $detector,
    ): RedirectResponse {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        /** @var array<int, string> $selectedCodes */
        $selectedCodes = $request->validated('red_flags', []);

        if ($request->validated('safety_status') === 'CONCERN' && $selectedCodes === []) {
            $selectedCodes = [RedFlagCode::UnspecifiedEmergencyConcern->value];
        }
        $answers = array_fill_keys(array_map(
            fn (RedFlagCode $code): string => $code->answerKey(),
            RedFlagCode::cases(),
        ), false);

        foreach ($selectedCodes as $selectedCode) {
            $code = RedFlagCode::from($selectedCode);
            $answers[$code->answerKey()] = true;
        }

        $matches = $detector->detect($answers);
        $matchedCodes = array_map(
            fn (RedFlagCode $code): string => $code->value,
            $matches,
        );

        DB::transaction(function () use ($visit, $matchedCodes): void {
            $intakeAnswers = $visit->intake_answers ?? [];
            $intakeAnswers['safety_screen'] = [
                'completed_at' => now()->toIso8601String(),
                'red_flags' => $matchedCodes,
            ];

            $visit->update(['intake_answers' => $intakeAnswers]);

            if ($matchedCodes === []) {
                return;
            }

            $mainComplaint = trim((string) ($intakeAnswers['main_complaint'] ?? ''));
            $summary = 'The patient reported an emergency warning sign during the safety check.';

            if ($mainComplaint !== '') {
                $summary .= ' Main concern: '.$mainComplaint;
            }

            $visit->assessment()->update([
                'status' => AssessmentStatus::Complete,
                'severity' => SeverityLevel::High,
                'recommended_action' => RecommendedAction::SeekEmergencyCareNow,
                'symptom_summary' => mb_substr($summary, 0, 1500),
                'red_flags' => $matchedCodes,
                'reason_codes' => $matchedCodes,
                'deterministic_red_flag_match' => true,
                'question_count' => 0,
                'error_code' => null,
            ]);

            $visit->update([
                'status' => VisitStatus::AssessmentComplete,
                'completed_at' => now(),
            ]);
        });

        if ($matchedCodes !== []) {
            return to_route('assessment.emergency');
        }

        return to_route('assessment.ready');
    }

    public function emergency(Request $request): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);

        if (! $visit?->assessment
            || ($visit->assessment->severity !== SeverityLevel::High
                && ! $visit->assessment->deterministic_red_flag_match)) {
            return to_route('assessment.safety');
        }

        $matchedWarnings = array_values(array_map(
            fn (RedFlagCode $code): array => [
                'code' => $code->value,
                'question' => $code->question(),
            ],
            array_filter(array_map(
                fn (string $value): ?RedFlagCode => RedFlagCode::tryFrom($value),
                $visit->assessment->red_flags,
            )),
        ));

        return Inertia::render('assessment/Emergency', [
            'matchedWarnings' => $matchedWarnings,
            'emergencyContact' => [
                'label' => config('medipath.emergency.contact_label'),
                'phone' => config('medipath.emergency.phone'),
            ],
        ]);
    }

    public function ready(
        Request $request,
        AssessmentQuestionnaire $questionnaire,
    ): Response|RedirectResponse {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        if ($visit->assessment?->severity === SeverityLevel::High
            || $visit->assessment?->deterministic_red_flag_match) {
            return to_route('assessment.emergency');
        }

        if (! isset($visit->intake_answers['safety_screen'])) {
            return to_route('assessment.safety');
        }

        if ($visit->assessment?->status === AssessmentStatus::Complete) {
            return to_route('routing.index');
        }

        $answers = $this->assessmentAnswers($visit);

        if (config('medipath.assessment.driver') === 'gemini') {
            $pendingQuestion = $this->pendingConversationQuestion($visit, count($answers));
            $maxQuestions = $this->maxConversationQuestions();

            return Inertia::render('assessment/Question', [
                'mainConcern' => $visit->intake_answers['main_complaint'] ?? null,
                'conversationMode' => true,
                'question' => $pendingQuestion,
                'transcript' => $this->conversationTranscript($visit, $answers),
                'progress' => [
                    'current' => min(count($answers) + 1, $maxQuestions),
                    'total' => $maxQuestions,
                ],
            ]);
        }

        $group = $questionnaire->nextGroup($answers);

        if ($request->integer('section') === 1) {
            $group = $questionnaire->groups()[0];
        }

        if (! $group) {
            return to_route('routing.index');
        }

        return Inertia::render('assessment/Question', [
            'mainConcern' => $visit->intake_answers['main_complaint'] ?? null,
            'group' => $group,
            'answers' => $answers,
            'progress' => [
                'current' => $group['key'] === 'symptom_pattern' ? 1 : 2,
                'total' => count($questionnaire->groups()),
            ],
        ]);
    }

    public function storeAnswer(
        StoreAssessmentAnswerRequest $request,
        AssessmentQuestionnaire $questionnaire,
        AssessmentProvider $provider,
        AssessmentConversationProvider $conversationProvider,
        DeterministicEmergencyScreener $emergencyScreener,
    ): RedirectResponse {
        $visit = $this->activeVisit($request);

        if (! $visit || ! isset($visit->intake_answers['safety_screen'])) {
            return to_route('assessment.safety');
        }

        if ($visit->assessment?->status === AssessmentStatus::Complete) {
            return $visit->assessment->severity === SeverityLevel::High
                ? to_route('assessment.emergency')
                : to_route('routing.index');
        }

        if ($visit->assessment?->status === AssessmentStatus::Failed) {
            return to_route('assessment.result');
        }

        if (config('medipath.assessment.driver') === 'gemini') {
            return $this->storeConversationAnswer(
                $request,
                $visit,
                $conversationProvider,
                $emergencyScreener,
            );
        }

        $answers = $this->assessmentAnswers($visit);
        $submittedGroupAnswers = $request->validated('answers');
        $isGroupedSubmission = is_array($submittedGroupAnswers);
        $currentGroup = $questionnaire->nextGroup($answers);

        if ($isGroupedSubmission) {
            $submittedGroupKey = (string) $request->validated('group_key');
            $submittedGroup = collect($questionnaire->groups())->firstWhere('key', $submittedGroupKey);

            if (! $submittedGroup) {
                return back()->withErrors(['answers' => 'This assessment section is no longer available.']);
            }

            if ($submittedGroupKey !== 'symptom_pattern'
                && ($currentGroup['key'] ?? null) !== $submittedGroupKey) {
                return back()->withErrors(['answers' => 'Complete the earlier assessment section first.']);
            }

            if (! $currentGroup && $submittedGroupKey !== 'symptom_pattern') {
                return to_route('routing.index');
            }

            foreach ($submittedGroup['questions'] as $question) {
                $questionKey = $question['key'];
                $answer = $submittedGroupAnswers[$questionKey] ?? null;
                $allowedAnswers = array_column($question['options'], 'value');

                if (! is_string($answer) || ! in_array($answer, $allowedAnswers, true)) {
                    return back()->withErrors([
                        "answers.$questionKey" => 'Choose one of the available answers.',
                    ])->withInput();
                }

                $answers[$questionKey] = $answer;
            }
        } else {
            $currentQuestion = $questionnaire->nextQuestion($answers);
            $questionKey = (string) $request->validated('question_key');
            $answer = (string) $request->validated('answer');

            if (! $currentQuestion || $currentQuestion['key'] !== $questionKey) {
                return back()->withErrors(['answer' => 'This assessment step has changed. Please answer the question currently shown.']);
            }

            $allowedAnswers = array_column($currentQuestion['options'], 'value');

            if (! in_array($answer, $allowedAnswers, true)) {
                return back()->withErrors(['answer' => 'Choose one of the available answers.']);
            }

            $answers[$questionKey] = $answer;
        }

        $redFlag = null;
        foreach ($answers as $questionKey => $answer) {
            $redFlag ??= $questionnaire->redFlagFor($questionKey, $answer);
        }

        $hasNextStep = $isGroupedSubmission
            ? $questionnaire->nextGroup($answers) !== null
            : $questionnaire->nextQuestion($answers) !== null;

        DB::transaction(function () use ($visit, $answers, $redFlag, $hasNextStep): void {
            $intakeAnswers = $visit->intake_answers ?? [];
            $intakeAnswers['assessment_conversation'] = $answers;
            $visit->update([
                'intake_answers' => $intakeAnswers,
                'status' => VisitStatus::Assessing,
            ]);

            if ($redFlag) {
                $visit->assessment()->update([
                    'status' => AssessmentStatus::Complete,
                    'severity' => SeverityLevel::High,
                    'recommended_action' => RecommendedAction::SeekEmergencyCareNow,
                    'symptom_summary' => mb_substr(
                        'The patient reported a new emergency warning sign during assessment. Main concern: '.
                        ($intakeAnswers['main_complaint'] ?? 'Not supplied.'),
                        0,
                        1500,
                    ),
                    'red_flags' => [$redFlag->value],
                    'reason_codes' => [$redFlag->value],
                    'deterministic_red_flag_match' => true,
                    'question_count' => count($answers),
                    'error_code' => null,
                ]);

                $visit->update([
                    'status' => VisitStatus::AssessmentComplete,
                    'completed_at' => now(),
                ]);

                return;
            }

            if ($hasNextStep) {
                $visit->assessment()->update([
                    'status' => AssessmentStatus::Processing,
                    'question_count' => count($answers),
                    'error_code' => null,
                ]);

                return;
            }
            $visit->assessment()->update([
                'status' => AssessmentStatus::Processing,
                'question_count' => count($answers),
                'error_code' => null,
            ]);
        });

        if ($redFlag) {
            return to_route('assessment.emergency');
        }

        if ($hasNextStep) {
            return to_route('assessment.ready');
        }

        try {
            $result = $provider->assess(
                $visit->fresh(['patient', 'assessment']),
                $answers,
                hash('sha256', 'medipath-assessment:'.$visit->getKey().':1.0'),
            );

            DB::transaction(function () use ($visit, $result, $answers): void {
                $assessment = $visit->assessment()->lockForUpdate()->firstOrFail();

                if ($assessment->deterministic_red_flag_match) {
                    return;
                }

                $assessment->update([
                    'status' => AssessmentStatus::Complete,
                    'severity' => $result->severity,
                    'recommended_action' => $result->recommendedAction,
                    'symptom_summary' => $result->symptomSummary,
                    'red_flags' => $result->redFlags,
                    'reason_codes' => $result->reasonCodes,
                    'deterministic_red_flag_match' => false,
                    'ai_schema_version' => $result->schemaVersion,
                    'provider_model' => $result->providerModel,
                    'question_count' => count($answers),
                    'error_code' => null,
                ]);
                $visit->update([
                    'status' => VisitStatus::AssessmentComplete,
                    'completed_at' => now(),
                ]);
            });
        } catch (AssessmentUnavailableException $exception) {
            DB::transaction(function () use ($visit, $provider, $exception, $answers): void {
                $assessment = $visit->assessment()->lockForUpdate()->firstOrFail();

                if ($assessment->deterministic_red_flag_match) {
                    return;
                }

                $assessment->update([
                    'status' => AssessmentStatus::Failed,
                    'severity' => null,
                    'recommended_action' => null,
                    'symptom_summary' => null,
                    'red_flags' => [],
                    'reason_codes' => [],
                    'provider_model' => $provider->model(),
                    'question_count' => count($answers),
                    'error_code' => $exception->errorCode,
                ]);
            });
        }

        $visit->refresh()->load('assessment');

        if ($visit->assessment?->severity === SeverityLevel::High) {
            return to_route('assessment.emergency');
        }

        return $visit->assessment?->status === AssessmentStatus::Failed
            ? to_route('assessment.result')
            : to_route($isGroupedSubmission ? 'routing.index' : 'assessment.result');
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        $assessment = $visit->assessment;

        if ($assessment?->status === AssessmentStatus::Failed) {
            return Inertia::render('assessment/Unavailable', [
                'emergencyContact' => [
                    'label' => config('medipath.emergency.contact_label'),
                    'phone' => config('medipath.emergency.phone'),
                ],
            ]);
        }

        if (! $assessment || $assessment->status !== AssessmentStatus::Complete) {
            return to_route('assessment.ready');
        }

        if ($assessment->severity === SeverityLevel::High) {
            return to_route('assessment.emergency');
        }

        return Inertia::render('assessment/Result', [
            'result' => [
                'severity' => $assessment->severity?->value,
                'recommendedAction' => $assessment->recommended_action?->value,
                'summary' => $assessment->symptom_summary,
                'reasonCodes' => $assessment->reason_codes,
            ],
        ]);
    }

    public function recover(
        Request $request,
        AssessmentConversationProvider $provider,
    ): RedirectResponse {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        $answers = $this->assessmentAnswers($visit);

        if ($visit->assessment?->status !== AssessmentStatus::Failed || $answers === []) {
            return to_route('assessment.result');
        }

        $errorCode = $visit->assessment->error_code;
        $this->saveConversationResult(
            $visit,
            $this->serviceFallbackResult($visit, $provider->model()),
            count($answers),
            $errorCode,
        );

        return to_route('assessment.result');
    }

    /** @return array<string, string> */
    private function assessmentAnswers(Visit $visit): array
    {
        $answers = $visit->intake_answers['assessment_conversation'] ?? [];

        return is_array($answers)
            ? array_filter($answers, fn (mixed $value): bool => is_string($value))
            : [];
    }

    /** @return array{key: string, question: string, help: string, options: array<int, never>} */
    private function pendingConversationQuestion(Visit $visit, int $answerCount): array
    {
        $pending = $visit->intake_answers['assessment_pending_question'] ?? null;

        if (is_array($pending)
            && is_string($pending['key'] ?? null)
            && is_string($pending['question'] ?? null)) {
            return [
                'key' => $pending['key'],
                'question' => $pending['question'],
                'help' => 'Answer in your own words. Mention any change that feels important.',
                'options' => [],
            ];
        }

        return [
            'key' => 'ai_'.($answerCount + 1),
            'question' => 'When did this concern start, and how has it changed since then?',
            'help' => 'A short answer is enough. MediPath will ask only what is needed next.',
            'options' => [],
        ];
    }

    /**
     * @param  array<string, string>  $answers
     * @return array<int, array{question: string, answer: string}>
     */
    private function conversationTranscript(Visit $visit, array $answers): array
    {
        $questions = $visit->intake_answers['assessment_questions'] ?? [];

        if (! is_array($questions)) {
            $questions = [];
        }

        return collect($answers)->map(
            fn (string $answer, string $key): array => [
                'question' => is_string($questions[$key] ?? null)
                    ? $questions[$key]
                    : 'Follow-up question',
                'answer' => $answer,
            ],
        )->values()->all();
    }

    private function storeConversationAnswer(
        StoreAssessmentAnswerRequest $request,
        Visit $visit,
        AssessmentConversationProvider $provider,
        DeterministicEmergencyScreener $emergencyScreener,
    ): RedirectResponse {
        $answers = $this->assessmentAnswers($visit);
        $question = $this->pendingConversationQuestion($visit, count($answers));
        $questionKey = (string) $request->validated('question_key');
        $answer = trim((string) $request->validated('answer'));

        if ($questionKey !== $question['key'] || $answer === '') {
            return back()->withErrors([
                'answer' => 'Answer the question currently shown before continuing.',
            ])->withInput();
        }

        $answers[$questionKey] = $answer;
        $storedQuestions = $visit->intake_answers['assessment_questions'] ?? [];
        $questions = is_array($storedQuestions) ? $storedQuestions : [];
        $questions[$questionKey] = $question['question'];
        $redFlags = array_map(
            fn (RedFlagCode $code): string => $code->value,
            $emergencyScreener->screenText($answer),
        );

        DB::transaction(function () use ($visit, $answers, $questions, $redFlags, $answer): void {
            $intakeAnswers = $visit->intake_answers ?? [];
            $intakeAnswers['assessment_conversation'] = $answers;
            $intakeAnswers['assessment_questions'] = $questions;
            unset($intakeAnswers['assessment_pending_question']);
            $visit->update([
                'intake_answers' => $intakeAnswers,
                'status' => $redFlags === [] ? VisitStatus::Assessing : VisitStatus::AssessmentComplete,
                'completed_at' => $redFlags === [] ? null : now(),
            ]);

            if ($redFlags !== []) {
                $visit->assessment()->update([
                    'status' => AssessmentStatus::Complete,
                    'severity' => SeverityLevel::High,
                    'recommended_action' => RecommendedAction::SeekEmergencyCareNow,
                    'symptom_summary' => mb_substr(
                        'A follow-up answer may describe an emergency warning sign. Patient response: '.$answer,
                        0,
                        1500,
                    ),
                    'red_flags' => $redFlags,
                    'reason_codes' => $redFlags,
                    'deterministic_red_flag_match' => true,
                    'question_count' => count($answers),
                    'error_code' => null,
                ]);

                return;
            }

            $visit->assessment()->update([
                'status' => AssessmentStatus::Processing,
                'question_count' => count($answers),
                'error_code' => null,
            ]);
        });

        if ($redFlags !== []) {
            return to_route('assessment.emergency');
        }

        try {
            $maxQuestions = $this->maxConversationQuestions();
            $turn = $provider->nextTurn(
                $visit->fresh(['patient', 'assessment']),
                $answers,
                $questions,
                hash('sha256', 'medipath-conversation:'.$visit->getKey().':'.count($answers)),
                count($answers) >= $maxQuestions,
            );

            if ($turn->question !== null && count($answers) < $maxQuestions) {
                $nextKey = 'ai_'.(count($answers) + 1);
                $intakeAnswers = $visit->fresh()->intake_answers ?? [];
                $intakeAnswers['assessment_pending_question'] = [
                    'key' => $nextKey,
                    'question' => $turn->question,
                ];
                $visit->update(['intake_answers' => $intakeAnswers]);

                return to_route('assessment.ready');
            }

            $result = $turn->result ?? $this->questionLimitResult($visit, $answers, $provider->model());
            $this->saveConversationResult($visit, $result, count($answers));
        } catch (AssessmentUnavailableException $exception) {
            $this->saveConversationResult(
                $visit,
                $this->serviceFallbackResult($visit, $provider->model()),
                count($answers),
                $exception->errorCode,
            );
        }

        $visit->refresh()->load('assessment');

        return $visit->assessment?->severity === SeverityLevel::High
            ? to_route('assessment.emergency')
            : to_route('assessment.result');
    }

    private function maxConversationQuestions(): int
    {
        return max(1, min((int) config('medipath.assessment.max_questions', 10), 10));
    }

    /** @param array<string, string> $answers */
    private function questionLimitResult(Visit $visit, array $answers, string $model): AssessmentResult
    {
        $mainConcern = trim((string) ($visit->intake_answers['main_complaint'] ?? 'The reported concern'));

        return new AssessmentResult(
            severity: SeverityLevel::Medium,
            recommendedAction: RecommendedAction::SeekCareSoon,
            symptomSummary: mb_substr(
                $mainConcern.'. The conversational assessment reached its question limit without enough clarity for routine guidance.',
                0,
                1500,
            ),
            redFlags: [],
            reasonCodes: [AssessmentReasonCode::InconclusiveAfterQuestionLimit->value],
            schemaVersion: '1.0',
            providerModel: $model,
        );
    }

    private function serviceFallbackResult(Visit $visit, string $model): AssessmentResult
    {
        $mainConcern = trim((string) ($visit->intake_answers['main_complaint'] ?? 'The reported concern'));

        return new AssessmentResult(
            severity: SeverityLevel::Medium,
            recommendedAction: RecommendedAction::SeekCareSoon,
            symptomSummary: mb_substr(
                $mainConcern.'. The AI assessment service was temporarily unavailable, so MediPath used a conservative care recommendation based on the saved information. This is guidance, not a diagnosis.',
                0,
                1500,
            ),
            redFlags: [],
            reasonCodes: [AssessmentReasonCode::AssessmentServiceUnavailable->value],
            schemaVersion: '1.0',
            providerModel: $model.'-safe-fallback',
        );
    }

    private function saveConversationResult(
        Visit $visit,
        AssessmentResult $result,
        int $questionCount,
        ?string $errorCode = null,
    ): void {
        DB::transaction(function () use ($visit, $result, $questionCount, $errorCode): void {
            $assessment = $visit->assessment()->lockForUpdate()->firstOrFail();

            if ($assessment->deterministic_red_flag_match) {
                return;
            }

            $assessment->update([
                'status' => AssessmentStatus::Complete,
                'severity' => $result->severity,
                'recommended_action' => $result->recommendedAction,
                'symptom_summary' => $result->symptomSummary,
                'red_flags' => $result->redFlags,
                'reason_codes' => $result->reasonCodes,
                'deterministic_red_flag_match' => false,
                'ai_schema_version' => $result->schemaVersion,
                'provider_model' => $result->providerModel,
                'question_count' => $questionCount,
                'error_code' => $errorCode,
            ]);
            $visit->update([
                'status' => VisitStatus::AssessmentComplete,
                'completed_at' => now(),
            ]);
        });
    }

    private function activeVisit(Request $request): ?Visit
    {
        $reference = $request->session()->get('active_visit_reference');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return Visit::query()->with('assessment')->find($reference);
    }
}
