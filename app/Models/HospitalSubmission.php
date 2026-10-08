<?php

namespace App\Models;

use App\Enums\ArrivalType;
use App\Enums\RecommendedAction;
use App\Enums\SeverityLevel;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $hospital_id
 * @property SubmissionStatus $status
 * @property ArrivalType $arrival_type
 * @property SeverityLevel $severity
 * @property RecommendedAction $recommended_action
 * @property array<string, mixed> $submission_snapshot
 * @property Carbon|null $received_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $closed_at
 */
class HospitalSubmission extends Model
{
    use HasUuids;

    const CREATED_AT = 'received_at';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'visit_id', 'hospital_id', 'consent_event_id', 'status', 'arrival_type',
        'severity', 'recommended_action', 'submission_snapshot',
        'acknowledged_at', 'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'arrival_type' => ArrivalType::class,
            'severity' => SeverityLevel::class,
            'recommended_action' => RecommendedAction::class,
            'submission_snapshot' => 'array',
            'received_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /** @return BelongsTo<ConsentEvent, $this> */
    public function consentEvent(): BelongsTo
    {
        return $this->belongsTo(ConsentEvent::class);
    }
}
