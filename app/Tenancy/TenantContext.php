<?php

namespace App\Tenancy;

/**
 * Per-request tenant context. Backed by a container singleton and mirrored into
 * PostgreSQL session GUCs by {@see TenantGucs}.
 */
class TenantContext
{
    private ?int $tenantId = null;

    private bool $bypassed = false;

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function bypass(bool $bypassed = true): void
    {
        $this->bypassed = $bypassed;
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    public function reset(): void
    {
        $this->tenantId = null;
        $this->bypassed = false;
    }
}
