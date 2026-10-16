<?php

namespace App\Geo;

final readonly class GeoPoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self((float) ($data['latitude'] ?? 0), (float) ($data['longitude'] ?? 0));
    }
}
