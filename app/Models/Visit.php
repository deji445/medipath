<?php

namespace App\Models;

use App\Enums\FillingAs;
use App\Enums\PregnancyStatus;
use App\Enums\SexAtBirth;
use App\Enums\VisitStatus;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed> $intake_answers
 * @property string|null $manual_location
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int $age_years
 * @property string $patient_id
 * @property FillingAs $filling_as
 * @property SexAtBirth $sex_at_birth
 * @property PregnancyStatus $pregnancy_status
 * @property string|null $respondent_name
 * @property string|null $relationship_to_patient
 * @property array<string, mixed>|null $manual_vitals
 * @property array<string, mixed>|null $wearable_vitals
 * @property Carbon|null $completed_at
 * @property Patient $patient
 * @property Assessment|null $assessment
 * @property Hospital|null $preselectedHospital
 * @property HospitalSubmission|null $hospitalSubmission
 */
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'started_at';

    protected $fillable = [
        'patient_id', 'preselected_hospital_id', 'status', 'filling_as',
        'respondent_name', 'relationship_to_patient', 'age_years',
        'sex_at_birth', 'pregnancy_status', 'intake_answers', 'manual_vitals',
        'wearable_vitals', 'latitude', 'longitude', 'manual_location', 'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'filling_as' => FillingAs::class,
            'sex_at_birth' => SexAtBirth::class,
            'pregnancy_status' => PregnancyStatus::class,
            'intake_answers' => 'array',
            'manual_vitals' => 'array',
            'wearable_vitals' => 'array',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Hospital, $this> */
    public function preselectedHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'preselected_hospital_id');
    }

    /** @return HasOne<Assessment, $this> */
    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }

    /** @return HasMany<ConsentEvent, $this> */
    public function consentEvents(): HasMany
    {
        return $this->hasMany(ConsentEvent::class);
    }

    /** @return HasOne<HospitalSubmission, $this> */
    public function hospitalSubmission(): HasOne
    {
        return $this->hasOne(HospitalSubmission::class);
    }
}
