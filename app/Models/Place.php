<?php

namespace App\Models;

use App\Enums\LocationKind;
use App\Enums\PlaceCategory;
use App\Geo\GeoPoint;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'address', 'latitude', 'longitude', 'radius_meters', 'category', 'created_by',
])]
class Place extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function point(): GeoPoint
    {
        return new GeoPoint((float) $this->latitude, (float) $this->longitude);
    }

    /**
     * What this place is, for notification wording.
     *
     * Derived from `category` so there is no second classification to keep in
     * step — and an unrecognised or missing category is Other rather than an
     * error, which keeps older rows readable.
     */
    public function kind(): LocationKind
    {
        return PlaceCategory::tryFrom((string) $this->category)?->kind() ?? LocationKind::Other;
    }
}
