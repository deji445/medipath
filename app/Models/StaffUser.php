<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $first_name
 * @property string $last_name
 * @property string $id
 * @property string $email
 * @property string|null $hospital_id
 * @property StaffRole $role
 * @property bool $is_active
 * @property-read string $name
 */
class StaffUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'staff_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = '';

    protected $fillable = [
        'id',
        'hospital_id',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'role' => StaffRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Present the split database fields as the display name expected by the UI.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /**
     * @return BelongsTo<Hospital, $this>
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === StaffRole::SuperAdmin;
    }

    public function belongsToHospital(Hospital $hospital): bool
    {
        return $this->hospital_id !== null && hash_equals($this->hospital_id, $hospital->getKey());
    }
}
