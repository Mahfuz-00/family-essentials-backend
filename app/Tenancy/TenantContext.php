<?php

namespace App\Tenancy;

/**
 * Per-request tenant context. Backed by a container singleton and mirrored into
 * PostgreSQL session GUCs by {@see TenantGucs}.
 *
 * It carries two identities: the household (`tenant_id`) and the acting person
 * (`userId`). Both are needed because the ledger tables are isolated per person
 * as well as per household — an unset person is as invisible as an unset tenant.
 */
class TenantContext
{
    private ?int $tenantId = null;

    private ?int $userId = null;

    private bool $bypassed = false;

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    /**
     * The acting person for person-level (ledger) isolation.
     */
    public function setUser(?int $userId): void
    {
        $this->userId = $userId;
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
        $this->userId = null;
        $this->bypassed = false;
    }
}
