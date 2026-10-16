<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Backup
 */
class BackupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => basename((string) $this->filename),
            'path' => $this->filename,
            'disk' => $this->disk,
            'driver' => $this->driver,
            'type' => $this->type,
            'status' => $this->status,
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'error' => $this->error,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verify_error' => $this->verify_error,
            // Verified means checksummed after the fact — not merely created.
            'restorable' => $this->isRestorable(),
            'has_drifted' => $this->hasDrifted(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
        ];
    }
}
