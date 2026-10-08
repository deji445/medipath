<?php

namespace App\Services\Audit;

use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class AuditRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(StaffUser $actor, string $action, Model $entity, array $metadata = []): void
    {
        DB::table('audit_events')->insert([
            'hospital_id' => $entity->getAttribute('hospital_id') ?? ($entity->getTable() === 'hospitals' ? $entity->getKey() : $actor->hospital_id),
            'actor_type' => $actor->isSuperAdmin() ? 'SUPER_ADMIN' : 'STAFF',
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
