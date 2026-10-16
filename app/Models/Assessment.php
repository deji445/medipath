<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\RecommendedAction;
use App\Enums\SeverityLevel;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AssessmentStatus $status
 * @property SeverityLevel|null $severity
 * @property RecommendedAction|null $recommended_action
 * @property string|null $symptom_summary
 * @property array<int, string> $red_flags
 * @property array<int, string> $reason_codes
 * @property bool $deterministic_red_flag_match
 * @property int $question_count
 * @property string|null $error_code
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'visit_id', 'status', 'severity', 'recommended_action', 'symptom_summary',
        'red_flags', 'reason_codes', 'deterministic_red_flag_match',
        'ai_schema_version', 'provider_model', 'question_count', 'error_code',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'severity' => SeverityLevel::class,
            'recommended_action' => RecommendedAction::class,
            'red_flags' => 'array',
            'reason_codes' => 'array',
            'deterministic_red_flag_match' => 'boolean',
        ];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
