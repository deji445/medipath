<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property array<int, array{status: string, at: string, actor?: string}> $events
 */
class AmbulanceRequest extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['events' => 'array', 'eta_minutes' => 'integer'];
    }

    /** @return array<string, mixed> */
    public function patientData(): array
    {
        return [
            'id' => $this->id, 'status' => $this->status,
            'requestedAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'vehicle' => $this->vehicle_id ? DB::table('ambulance_vehicles')->where('id', $this->vehicle_id)->value('name') : null,
            'crew' => $this->crew, 'etaMinutes' => $this->eta_minutes,
            'events' => array_map(fn (array $event) => ['status' => $event['status'], 'at' => $event['at']], $this->events),
        ];
    }
}
