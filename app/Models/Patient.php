<?php

namespace App\Models;

use App\Enums\SexAtBirth;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $normalized_phone
 * @property string $first_name
 * @property string $last_name
 * @property Carbon|null $date_of_birth
 * @property SexAtBirth $sex_at_birth
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['normalized_phone', 'first_name', 'last_name', 'date_of_birth', 'sex_at_birth'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'sex_at_birth' => SexAtBirth::class,
        ];
    }

    /** @return HasMany<PatientCondition, $this> */
    public function conditions(): HasMany
    {
        return $this->hasMany(PatientCondition::class);
    }

    /** @return HasMany<PatientAllergy, $this> */
    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    /** @return HasMany<PatientMedication, $this> */
    public function medications(): HasMany
    {
        return $this->hasMany(PatientMedication::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
