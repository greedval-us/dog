<?php

namespace App\Models;

use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string $actor_name
 * @property string $target_type
 * @property int $target_id
 * @property string $action
 * @property string|null $reason
 * @property array<string, array{before: mixed, after: mixed}> $changes
 */
#[Fillable(['actor_id', 'actor_name', 'target_type', 'target_id', 'action', 'reason', 'changes'])]
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changes' => 'array', 'target_id' => 'integer'];
    }
}
