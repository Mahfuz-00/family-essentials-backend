<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingAnnouncement extends Model
{
    use HasFactory;

    protected $table = 'marketing_announcements';

    protected $fillable = [
        'title',
        'message',
        'cta_text',
        'cta_url',
        'tone',
        'target_audience',
        'is_active',
        'dismissable',
        'impressions_count',
        'clicks_count',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'dismissable' => 'boolean',
        'impressions_count' => 'integer',
        'clicks_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isCurrentlyLive(): bool
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

        return true;
    }
}
