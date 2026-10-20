<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus;
use App\Enums\ConsentType;
use App\Enums\SeverityLevel;
use App\Enums\SexAtBirth;
use App\Enums\VisitStatus;
use App\Http\Requests\Intake\LookupPatientRequest;
use App\Http\Requests\Intake\StoreHistoryRequest;
use App\Http\Requests\Intake\StoreIdentityRequest;
use App\Http\Requests\Intake\StoreProfileRequest;
use App\Http\Requests\Intake\StoreVisitRequest;
use App\Http\Requests\Intake\VerifyPhoneRequest;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Assessment\InitialConcernSafetyScreener;
use App\Services\Routing\ManualLocationGeocoder;
use App\Services\Verification\PhoneVerificationService;
use App\Support\PatientAge;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PatientIntakeController extends Controller
{
    public function start(Request $request): Response
    {
        if ($request->boolean('restart')) {
            $request->session()->forget('intake');
            $request->session()->forget(['active_visit_reference', 'assessment_red_flags']);
        }

        $patientId = $request->session()->get('intake.patient_id');
        $patient = is_string($patientId)
            ? Patient::query()->with(['conditions', 'allergies', 'medications'])->find($patientId)
            : null;

        $activeReference = $request->session()->get('active_visit_reference');
        $activeVisit = is_string($activeReference)
            ? Visit::query()
                ->with(['patient', 'assessment', 'preselectedHospital'])
                ->find($activeReference)
            : null;

        return Inertia::render('intake/Start', [
            'phoneNumber' => $request->session()->get('intake.phone'),
            'existingPatient' => $patient ? [
                'maskedName' => $this->maskName($patient->first_name, $patient->last_name),
                'sexAtBirth' => $patient->sex_at_birth->value,
            ] : null,
            'history' => $patient ? [
                'conditions' => $patient->conditions->where('is_active', true)->pluck('name')->values()->all(),
                'allergies' => $patient->allergies->where('is_active', true)->pluck('name')->values()->all(),
                'medications' => $patient->medications->where('is_active', true)
                    ->map->only(['name', 'dose', 'frequency'])->values()->all(),
            ] : ['conditions' => [], 'allergies' => [], 'medications' => []],
            'resumeJourney' => $activeVisit && ! $request->boolean('restart') ? [
                'href' => $this->resumeHref($activeVisit),
                'patientName' => $this->maskName(
                    $activeVisit->patient->first_name,
                    $activeVisit->patient->last_name,
                ),
                'mainConcern' => mb_substr(
                    (string) ($activeVisit->intake_answers['main_complaint'] ?? 'Patient intake'),
                    0,
                    120,
                ),
            ] : null,
        ]);
    }

    public function lookup(
        LookupPatientRequest $request,
        PhoneVerificationService $verification,
    ): RedirectResponse {
        $phone = (string) $request->validated('phone_number');
        $walkInHospitalId = $request->session()->get('intake.preselected_hospital_id');
        $arrivalType = $request->session()->get('intake.arrival_type');
        $chatHandoff = $request->session()->get('intake.chat_handoff');

        $request->session()->forget('intake');
        $request->session()->forget(['active_visit_reference', 'assessment_red_flags']);
        $issued = $verification->issue($phone);
        $request->session()->put('intake.pending_phone_verification', $issued['challenge']);
        $request->session()->put('intake.streamlined', $request->boolean('streamlined'));

        if (is_string($issued['demo_code'])) {
            $request->session()->put('intake.demo_verification_code', $issued['demo_code']);
        }

        if (is_string($walkInHospitalId) && $walkInHospitalId !== '') {
            $request->session()->put('intake.preselected_hospital_id', $walkInHospitalId);
        }

        if (is_string($arrivalType) && $arrivalType !== '') {
            $request->session()->put('intake.arrival_type', $arrivalType);
        }

        if (is_array($chatHandoff) && is_string($chatHandoff['main_complaint'] ?? null)) {
            $request->session()->put('intake.chat_handoff', $chatHandoff);
        }

        return to_route('intake.phone.verify');
    }

    public function verifyPhone(Request $request): Response|RedirectResponse
    {
        $challenge = $request->session()->get('intake.pending_phone_verification');

        if (! is_array($challenge) || ! is_string($challenge['phone'] ?? null)) {
            return to_route('intake.start');
        }

        return Inertia::render('intake/VerifyPhone', [
            'phoneNumber' => $this->maskPhone($challenge['phone']),
            'expiresAt' => $challenge['expires_at'] ?? null,
            'demoCode' => $request->session()->get('intake.demo_verification_code'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function confirmPhone(
        VerifyPhoneRequest $request,
        PhoneVerificationService $verification,
    ): RedirectResponse {
        $challenge = $request->session()->get('intake.pending_phone_verification');

        if (! is_array($challenge) || ! is_string($challenge['phone'] ?? null)) {
            return to_route('intake.start');
        }

        $maxAttempts = (int) config('medipath.phone_verification.max_attempts', 5);
        $attempts = (int) ($challenge['attempts'] ?? 0);

        if ($verification->isExpired($challenge)) {
            throw ValidationException::withMessages([
                'code' => 'This code has expired. Request a new code and try again.',
            ]);
        }

        if ($attempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'code' => 'Too many incorrect attempts. Request a new code.',
            ]);
        }

        if (! $verification->matches($challenge, (string) $request->validated('code'))) {
            $challenge['attempts'] = $attempts + 1;
            $request->session()->put('intake.pending_phone_verification', $challenge);

            throw ValidationException::withMessages([
                'code' => $challenge['attempts'] >= $maxAttempts
                    ? 'Too many incorrect attempts. Request a new code.'
                    : 'That code is not correct. Check the message and try again.',
            ]);
        }

        $phone = $challenge['phone'];
        $patient = Patient::query()->where('normalized_phone', $phone)->first();
        $streamlined = $request->session()->get('intake.streamlined') === true;

        $request->session()->forget([
            'intake.pending_phone_verification',
            'intake.demo_verification_code',
            'intake.streamlined',
        ]);
        $request->session()->put('intake.phone', $phone);

        if ($patient) {
            $request->session()->put('intake.patient_id', $patient->getKey());
        }

        $request->session()->regenerate();

        return $streamlined ? to_route('intake.start') : to_route('intake.identity');
    }

    public function resendPhoneCode(
        Request $request,
        PhoneVerificationService $verification,
    ): RedirectResponse {
        $challenge = $request->session()->get('intake.pending_phone_verification');

        if (! is_array($challenge) || ! is_string($challenge['phone'] ?? null)) {
            return to_route('intake.start');
        }

        $sentAt = isset($challenge['sent_at']) && is_string($challenge['sent_at'])
            ? CarbonImmutable::parse($challenge['sent_at'])
            : null;
        $cooldown = (int) config('medipath.phone_verification.resend_cooldown_seconds', 60);

        if ($sentAt?->addSeconds($cooldown)->isFuture()) {
            throw ValidationException::withMessages([
                'resend' => 'Please wait a moment before requesting another code.',
            ]);
        }

        $issued = $verification->issue($challenge['phone']);
        $request->session()->put('intake.pending_phone_verification', $issued['challenge']);

        if (is_string($issued['demo_code'])) {
            $request->session()->put('intake.demo_verification_code', $issued['demo_code']);
        }

        return to_route('intake.phone.verify')->with('status', 'A new verification code is ready.');
    }

    public function storeProfile(StoreProfileRequest $request): RedirectResponse
    {
        if (! $request->session()->has('intake.phone')) {
            return to_route('intake.start');
        }

        $validated = $request->validated();
        $patientId = $request->session()->get('intake.patient_id');
        $patient = is_string($patientId)
            ? Patient::query()->whereKey($patientId)->firstOrFail()
            : null;

        if ($patient && $patient->sex_at_birth === SexAtBirth::Unknown && isset($validated['sex_at_birth'])) {
            $patient->update(['sex_at_birth' => $validated['sex_at_birth']]);
            $patient->refresh();
        }

        $identity = $patient ? [
            'patient_id' => $patient->getKey(),
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
            'sex_at_birth' => $patient->sex_at_birth->value,
        ] : [
            'patient_id' => null,
            'first_name' => trim((string) $validated['first_name']),
            'last_name' => trim((string) $validated['last_name']),
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'sex_at_birth' => $validated['sex_at_birth'],
        ];

        if ($patient && $validated['history_mode'] === 'UNCHANGED') {
            $history = [
                'conditions' => $patient->conditions()->where('is_active', true)->pluck('name')->all(),
                'allergies' => $patient->allergies()->where('is_active', true)->pluck('name')->all(),
                'medications' => $patient->medications()->where('is_active', true)
                    ->get(['name', 'dose', 'frequency'])->map->only(['name', 'dose', 'frequency'])->all(),
                'conditions_status' => 'UNCHANGED',
                'allergies_status' => 'UNCHANGED',
                'medications_status' => 'UNCHANGED',
            ];
        } else {
            $submittedMedications = $validated['medications'] ?? [];
            $medications = collect(is_array($submittedMedications) ? $submittedMedications : [])
                ->filter(fn (mixed $item): bool => is_array($item) && trim((string) ($item['name'] ?? '')) !== '')
                ->map(fn (array $item): array => [
                    'name' => trim((string) $item['name']),
                    'dose' => ($dose = trim((string) ($item['dose'] ?? ''))) !== '' ? $dose : null,
                    'frequency' => ($frequency = trim((string) ($item['frequency'] ?? ''))) !== '' ? $frequency : null,
                ])->values()->all();

            $history = [
                'conditions' => $validated['conditions_status'] === 'DETAILS'
                    ? $this->parseLines((string) ($validated['conditions_text'] ?? '')) : [],
                'allergies' => $validated['allergies_status'] === 'DETAILS'
                    ? $this->parseLines((string) ($validated['allergies_text'] ?? '')) : [],
                'medications' => $validated['medications_status'] === 'DETAILS' ? $medications : [],
                'conditions_status' => $validated['conditions_status'],
                'allergies_status' => $validated['allergies_status'],
                'medications_status' => $validated['medications_status'],
            ];
        }

        $request->session()->put('intake.identity', $identity);
        $request->session()->put('intake.history', $history);

        return to_route('intake.visit');
    }

    public function identity(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('intake.phone')) {
            return to_route('intake.start');
        }

        $patientId = $request->session()->get('intake.patient_id');
        $patient = is_string($patientId)
            ? Patient::query()->whereKey($patientId)->first()
            : null;

        return Inertia::render('intake/Identity', [
            'phoneNumber' => $this->maskPhone((string) $request->session()->get('intake.phone')),
            'existingPatient' => $patient ? [
                'maskedName' => $this->maskName($patient->first_name, $patient->last_name),
                'dateOfBirth' => $patient->date_of_birth?->format('Y-m-d'),
                'sexAtBirth' => $patient->sex_at_birth->value,
            ] : null,
            'identity' => $request->session()->get('intake.identity'),
        ]);
    }

    public function storeIdentity(StoreIdentityRequest $request): RedirectResponse
    {
        if (! $request->session()->has('intake.phone')) {
            return to_route('intake.start');
        }

        $patientId = $request->session()->get('intake.patient_id');
        $patient = is_string($patientId)
            ? Patient::query()->whereKey($patientId)->firstOrFail()
            : null;

        if ($patient) {
            $identity = [
                'patient_id' => $patient->getKey(),
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
                'sex_at_birth' => $patient->sex_at_birth->value,
            ];

            $request->session()->put('intake.history', [
                'conditions' => $patient->conditions()->where('is_active', true)->pluck('name')->all(),
                'allergies' => $patient->allergies()->where('is_active', true)->pluck('name')->all(),
                'medications' => $patient->medications()->where('is_active', true)
                    ->get(['name', 'dose', 'frequency'])->map->only(['name', 'dose', 'frequency'])->all(),
            ]);
        } else {
            $validated = $request->validated();
            $identity = [
                'patient_id' => null,
                'first_name' => trim((string) $validated['first_name']),
                'last_name' => trim((string) $validated['last_name']),
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'sex_at_birth' => $validated['sex_at_birth'],
            ];
            $request->session()->put('intake.history', [
                'conditions' => [], 'allergies' => [], 'medications' => [],
            ]);
        }

        $request->session()->put('intake.identity', $identity);

        return to_route('intake.history');
    }

    public function history(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('intake.identity')) {
            return to_route('intake.identity');
        }

        $storedHistory = $request->session()->get('intake.history', []);
        $history = is_array($storedHistory) ? $storedHistory : [];
        $medications = isset($history['medications']) && is_array($history['medications'])
            ? $history['medications']
            : [];

        return Inertia::render('intake/History', [
            'conditionsText' => implode("\n", $history['conditions'] ?? []),
            'allergiesText' => implode("\n", $history['allergies'] ?? []),
            'medicationsText' => collect($medications)
                ->filter(fn (mixed $medication): bool => is_array($medication))
                ->map(fn (array $medication): string => implode(' | ', array_filter([
                    $medication['name'] ?? null,
                    $medication['dose'] ?? null,
                    $medication['frequency'] ?? null,
                ], fn (mixed $value): bool => is_string($value) && $value !== '')))
                ->implode("\n"),
        ]);
    }

    public function storeHistory(StoreHistoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $request->session()->put('intake.history', [
            'conditions' => $this->parseLines((string) ($validated['conditions_text'] ?? '')),
            'allergies' => $this->parseLines((string) ($validated['allergies_text'] ?? '')),
            'medications' => $this->parseMedications((string) ($validated['medications_text'] ?? '')),
        ]);

        return to_route('intake.visit');
    }

    public function visit(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('intake.history')) {
            return to_route('intake.history');
        }

        $identity = $request->session()->get('intake.identity');
        $visit = $request->session()->get('intake.visit');
        $chatHandoff = $request->session()->get('intake.chat_handoff');

        if (! is_array($visit)
            && is_array($chatHandoff)
            && is_string($chatHandoff['main_complaint'] ?? null)) {
            $visit = ['main_complaint' => $chatHandoff['main_complaint']];
        }

        return Inertia::render('intake/Visit', [
            'visit' => $visit,
            'sexAtBirth' => is_array($identity) ? ($identity['sex_at_birth'] ?? null) : null,
            'patientAge' => PatientAge::fromDateOfBirth(
                is_array($identity) ? ($identity['date_of_birth'] ?? null) : null,
            ),
        ]);
    }

    public function storeVisit(
        StoreVisitRequest $request,
        InitialConcernSafetyScreener $safetyScreener,
        ManualLocationGeocoder $geocoder,
    ): RedirectResponse {
        $visitData = $this->resolveLocation($request->validated(), $geocoder);
        $request->session()->put('intake.visit', $visitData);
        $request->session()->forget('intake.chat_handoff');

        if ($request->boolean('streamlined')) {
            return $this->persistIntake($request, 'assessment.ready', $safetyScreener);
        }

        return to_route('intake.review');
    }

    public function editVisit(Request $request): Response|RedirectResponse
    {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        $visit->load('patient');
        $manualVitals = $visit->manual_vitals ?? [];

        return Inertia::render('intake/Visit', [
            'visit' => [
                'age_years' => $visit->age_years,
                'filling_as' => $visit->filling_as->value,
                'respondent_name' => $visit->respondent_name,
                'relationship_to_patient' => $visit->relationship_to_patient,
                'pregnancy_status' => $visit->pregnancy_status->value,
                'main_complaint' => $visit->intake_answers['main_complaint'] ?? '',
                'manual_location' => $visit->manual_location,
                'latitude' => $visit->latitude,
                'longitude' => $visit->longitude,
                'temperature_c' => $manualVitals['temperature_c'] ?? null,
                'heart_rate_bpm' => $manualVitals['heart_rate_bpm'] ?? null,
                'oxygen_saturation_percent' => $manualVitals['oxygen_saturation_percent'] ?? null,
            ],
            'sexAtBirth' => $visit->sex_at_birth->value,
            'patientAge' => PatientAge::fromDateOfBirth(
                $visit->patient->date_of_birth?->format('Y-m-d'),
            ),
            'formAction' => '/intake/visit/current',
            'formMethod' => 'patch',
            'backHref' => '/intake/assessment',
        ]);
    }

    public function updateVisit(
        StoreVisitRequest $request,
        InitialConcernSafetyScreener $safetyScreener,
        ManualLocationGeocoder $geocoder,
    ): RedirectResponse {
        $visit = $this->activeVisit($request);

        if (! $visit) {
            return to_route('intake.start');
        }

        /** @var array<string, mixed> $visitData */
        $visitData = $this->resolveLocation($request->validated(), $geocoder);

        $visit = DB::transaction(function () use ($visit, $visitData): Visit {
            $lockedVisit = Visit::query()->whereKey($visit->getKey())->lockForUpdate()->firstOrFail();
            $intakeAnswers = $lockedVisit->intake_answers ?? [];
            $intakeAnswers['main_complaint'] = $visitData['main_complaint'];
            $intakeAnswers['symptom_details'] = $visitData['symptom_details'] ?? null;
            $intakeAnswers['symptom_onset'] = $visitData['symptom_onset'] ?? null;
            unset(
                $intakeAnswers['safety_screen'],
                $intakeAnswers['assessment_conversation'],
                $intakeAnswers['assessment_questions'],
                $intakeAnswers['assessment_pending_question'],
            );

            $lockedVisit->update([
                'status' => VisitStatus::Draft,
                'filling_as' => $visitData['filling_as'],
                'respondent_name' => $visitData['respondent_name'] ?? null,
                'relationship_to_patient' => $visitData['relationship_to_patient'] ?? null,
                'age_years' => $visitData['age_years'],
                'pregnancy_status' => $visitData['pregnancy_status'],
                'manual_location' => $visitData['manual_location'] ?? null,
                'latitude' => $visitData['latitude'] ?? null,
                'longitude' => $visitData['longitude'] ?? null,
                'manual_vitals' => $this->manualVitals($visitData),
                'intake_answers' => $intakeAnswers,
                'completed_at' => null,
            ]);
            $lockedVisit->assessment()->update([
                'status' => AssessmentStatus::Pending,
                'severity' => null,
                'recommended_action' => null,
                'symptom_summary' => null,
                'red_flags' => [],
                'reason_codes' => [],
                'deterministic_red_flag_match' => false,
                'ai_schema_version' => '1.0',
                'provider_model' => null,
                'question_count' => 0,
                'error_code' => null,
            ]);

            return $lockedVisit->fresh(['patient', 'assessment']);
        });

        if ($safetyScreener->screen($visit)) {
            return to_route('assessment.emergency');
        }

        return to_route('assessment.ready');
    }

    public function review(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('intake.identity')
            || ! $request->session()->has('intake.history')
            || ! $request->session()->has('intake.visit')) {
            return to_route('intake.start');
        }

        return Inertia::render('intake/Review', [
            'identity' => $request->session()->get('intake.identity'),
            'phoneNumber' => $this->maskPhone((string) $request->session()->get('intake.phone')),
            'history' => $request->session()->get('intake.history'),
            'visit' => $request->session()->get('intake.visit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persistIntake($request, 'intake.complete');
    }

    private function persistIntake(
        Request $request,
        string $nextRoute,
        ?InitialConcernSafetyScreener $safetyScreener = null,
    ): RedirectResponse {
        abort_unless(
            $request->session()->has('intake.identity')
            && $request->session()->has('intake.history')
            && $request->session()->has('intake.visit'),
            422,
            'The intake is incomplete.',
        );

        /** @var array<string, mixed> $identity */
        $identity = $request->session()->get('intake.identity');
        /** @var array<string, mixed> $history */
        $history = $request->session()->get('intake.history');
        /** @var array<string, mixed> $visitData */
        $visitData = $request->session()->get('intake.visit');
        $phone = (string) $request->session()->get('intake.phone');
        $ageYears = PatientAge::fromDateOfBirth($identity['date_of_birth'] ?? null)
            ?? (int) $visitData['age_years'];

        $visit = DB::transaction(function () use ($identity, $history, $visitData, $phone, $ageYears): Visit {
            $patientId = $identity['patient_id'] ?? null;
            $patient = is_string($patientId) && $patientId !== ''
                ? Patient::query()->whereKey($patientId)->firstOrFail()
                : Patient::query()->create([
                    'normalized_phone' => $phone,
                    'first_name' => $identity['first_name'],
                    'last_name' => $identity['last_name'],
                    'date_of_birth' => $identity['date_of_birth'],
                    'sex_at_birth' => $identity['sex_at_birth'],
                ]);

            $this->syncHistory($patient, $history);

            $manualVitals = array_filter([
                'temperature_c' => $visitData['temperature_c'] ?? null,
                'heart_rate_bpm' => $visitData['heart_rate_bpm'] ?? null,
                'oxygen_saturation_percent' => $visitData['oxygen_saturation_percent'] ?? null,
                'captured_at' => now()->toIso8601String(),
            ], fn (mixed $value, string $key): bool => $key === 'captured_at' || $value !== null, ARRAY_FILTER_USE_BOTH);

            if (count($manualVitals) === 1) {
                $manualVitals = [];
            }

            $visit = $patient->visits()->create([
                'preselected_hospital_id' => $visitData['preselected_hospital_id']
                    ?? session('intake.preselected_hospital_id'),
                'status' => VisitStatus::Draft,
                'filling_as' => $visitData['filling_as'],
                'respondent_name' => $visitData['respondent_name'] ?? null,
                'relationship_to_patient' => $visitData['relationship_to_patient'] ?? null,
                'age_years' => $ageYears,
                'sex_at_birth' => $identity['sex_at_birth'],
                'pregnancy_status' => $visitData['pregnancy_status'],
                'manual_location' => $visitData['manual_location'] ?? null,
                'latitude' => $visitData['latitude'] ?? null,
                'longitude' => $visitData['longitude'] ?? null,
                'manual_vitals' => $manualVitals ?: null,
                'intake_answers' => [
                    'arrival_type' => session('intake.arrival_type', 'PRE_ARRIVAL'),
                    'main_complaint' => $visitData['main_complaint'],
                    'symptom_details' => $visitData['symptom_details'] ?? null,
                    'symptom_onset' => $visitData['symptom_onset'] ?? null,
                    'conditions' => $history['conditions'],
                    'allergies' => $history['allergies'],
                    'medications' => $history['medications'],
                    'history_status' => [
                        'conditions' => $history['conditions_status'] ?? 'DETAILS',
                        'allergies' => $history['allergies_status'] ?? 'DETAILS',
                        'medications' => $history['medications_status'] ?? 'DETAILS',
                    ],
                ],
            ]);

            $visit->assessment()->create(['status' => AssessmentStatus::Pending]);

            foreach ([ConsentType::AiDisclaimer, ConsentType::PrivacyNotice] as $consentType) {
                $visit->consentEvents()->create([
                    'patient_id' => $patient->getKey(),
                    'consent_type' => $consentType,
                    'consent_version' => '1.0',
                    'granted' => true,
                    'recorded_at' => now(),
                ]);
            }

            if (($visitData['location_permission_granted'] ?? false) === true) {
                $visit->consentEvents()->create([
                    'patient_id' => $patient->getKey(),
                    'consent_type' => ConsentType::LocationAccess,
                    'consent_version' => '1.0',
                    'granted' => true,
                    'recorded_at' => now(),
                ]);
            }

            return $visit;
        });

        $request->session()->forget('intake');
        $request->session()->flash('completed_visit_reference', $visit->getKey());
        $request->session()->put('active_visit_reference', $visit->getKey());

        if ($safetyScreener?->screen($visit)) {
            return to_route('assessment.emergency');
        }

        return to_route($nextRoute);
    }

    public function complete(Request $request): Response|RedirectResponse
    {
        $reference = $request->session()->get('completed_visit_reference')
            ?? $request->session()->get('active_visit_reference');

        if (! $reference) {
            return to_route('intake.start');
        }

        return Inertia::render('intake/Complete', ['reference' => $reference]);
    }

    /** @return array<int, string> */
    private function parseLines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n|,/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique(fn (string $line): string => mb_strtolower($line))
            ->values()
            ->all();
    }

    private function resumeHref(Visit $visit): string
    {
        if ($visit->status === VisitStatus::Submitted) {
            return route('submission.complete', absolute: false);
        }

        if ($visit->assessment?->status === AssessmentStatus::Complete) {
            if ($visit->assessment->severity === SeverityLevel::High) {
                return route('assessment.emergency', absolute: false);
            }

            return $visit->preselectedHospital
                ? route('submission.review', absolute: false)
                : route('routing.index', absolute: false);
        }

        if ($visit->assessment?->status === AssessmentStatus::Failed) {
            return route('assessment.result', absolute: false);
        }

        return route('assessment.ready', absolute: false);
    }

    /** @return array<int, array{name: string, dose: string|null, frequency: string|null}> */
    private function parseMedications(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(function (string $line): array {
                $parts = array_pad(array_map('trim', explode('|', $line, 3)), 3, '');

                return [
                    'name' => $parts[0],
                    'dose' => $parts[1] !== '' ? $parts[1] : null,
                    'frequency' => $parts[2] !== '' ? $parts[2] : null,
                ];
            })
            ->filter(fn (array $medication): bool => $medication['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $visitData
     * @return array<string, mixed>|null
     */
    private function manualVitals(array $visitData): ?array
    {
        $vitals = array_filter([
            'temperature_c' => $visitData['temperature_c'] ?? null,
            'heart_rate_bpm' => $visitData['heart_rate_bpm'] ?? null,
            'oxygen_saturation_percent' => $visitData['oxygen_saturation_percent'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        if ($vitals === []) {
            return null;
        }

        $vitals['captured_at'] = now()->toIso8601String();

        return $vitals;
    }

    /**
     * @param  array<string, mixed>  $visitData
     * @return array<string, mixed>
     */
    private function resolveLocation(array $visitData, ManualLocationGeocoder $geocoder): array
    {
        if (isset($visitData['latitude'], $visitData['longitude'])) {
            $visitData['latitude'] = (float) $visitData['latitude'];
            $visitData['longitude'] = (float) $visitData['longitude'];
            $visitData['location_permission_granted'] = (bool) ($visitData['location_permission_granted'] ?? false);

            return $visitData;
        }

        $coordinates = $geocoder->coordinates($visitData['manual_location'] ?? null);

        if ($coordinates !== null) {
            $visitData = [...$visitData, ...$coordinates];
        }

        $visitData['location_permission_granted'] = false;

        return $visitData;
    }

    private function activeVisit(Request $request): ?Visit
    {
        $reference = $request->session()->get('active_visit_reference');

        return is_string($reference) && $reference !== ''
            ? Visit::query()->find($reference)
            : null;
    }

    /** @param array<string, mixed> $history */
    private function syncHistory(Patient $patient, array $history): void
    {
        if (($history['conditions_status'] ?? 'DETAILS') !== 'UNSURE') {
            $patient->conditions()->update(['is_active' => false]);
            foreach ($history['conditions'] as $name) {
                $patient->conditions()->updateOrCreate(['name' => $name], ['is_active' => true]);
            }
        }

        if (($history['allergies_status'] ?? 'DETAILS') !== 'UNSURE') {
            $patient->allergies()->update(['is_active' => false]);
            foreach ($history['allergies'] as $name) {
                $patient->allergies()->updateOrCreate(['name' => $name], ['is_active' => true]);
            }
        }

        if (($history['medications_status'] ?? 'DETAILS') !== 'UNSURE') {
            $patient->medications()->update(['is_active' => false]);
            foreach ($history['medications'] as $medication) {
                $patient->medications()->updateOrCreate(
                    ['name' => $medication['name']],
                    [
                        'dose' => $medication['dose'],
                        'frequency' => $medication['frequency'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function maskPhone(string $phone): string
    {
        return str_repeat('•', max(0, mb_strlen($phone) - 4)).mb_substr($phone, -4);
    }

    private function maskName(string $firstName, string $lastName): string
    {
        return mb_substr($firstName, 0, 1).'*** '.mb_substr($lastName, 0, 1).'***';
    }
}
