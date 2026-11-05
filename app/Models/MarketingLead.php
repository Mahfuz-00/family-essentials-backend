<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingLead extends Model
{
    use HasFactory;

    protected $table = 'marketing_leads';

    protected $fillable = [
        'email',
        'name',
        'source',
        'campaign',
        'status',
        'notes',
        'converted_tenant_id',
    ];

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }
}
