<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A point-in-time result of the operational probe. Platform-level, no tenant
 * scope: it describes the system, not a household.
 */
#[Fillable(['checked_at', 'status', 'checks'])]
class HealthSample extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'checks' => 'array',
        ];
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }
}
