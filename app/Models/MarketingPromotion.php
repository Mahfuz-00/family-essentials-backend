<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingPromotion extends Model
{
    use HasFactory;

    protected $table = 'marketing_promotions';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'currency',
        'duration',
        'duration_in_months',
        'max_redemptions',
        'times_redeemed',
        'applicable_tiers',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'duration_in_months' => 'integer',
        'max_redemptions' => 'integer',
        'times_redeemed' => 'integer',
        'applicable_tiers' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->times_redeemed >= $this->max_redemptions) {
            return false;
        }

        return true;
    }
}
