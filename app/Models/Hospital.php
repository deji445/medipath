<?php

namespace App\Models;

use App\Enums\HospitalCapability;
use App\Enums\RoutingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property HospitalCapability $capability
 * @property RoutingMode $routing_mode
 * @property bool $accepting_submissions
 * @property bool $is_active
 * @property bool $is_live_partner
 * @property string|null $brand_name
 * @property string|null $brand_color
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $directory_source_url
 * @property Carbon|null $directory_verified_at
 * @property int|null $staff_users_count
 * @property int|null $submissions_count
 */
class Hospital extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'slug',
        'name',
        'capability',
        'routing_mode',
        'accepting_submissions',
        'is_active',
        'is_live_partner',
        'brand_name',
        'brand_color',
        'logo_url',
        'address',
        'latitude',
        'longitude',
        'directory_source_url',
        'directory_verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability' => HospitalCapability::class,
            'routing_mode' => RoutingMode::class,
            'accepting_submissions' => 'boolean',
            'is_active' => 'boolean',
            'is_live_partner' => 'boolean',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'directory_verified_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StaffUser, $this>
     */
    public function staffUsers(): HasMany
    {
        return $this->hasMany(StaffUser::class);
    }

    /** @return HasMany<HospitalSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(HospitalSubmission::class);
    }
}
