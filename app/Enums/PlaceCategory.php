<?php

namespace App\Enums;

enum PlaceCategory: string
{
    case Home = 'home';
    case Grocery = 'grocery';
    case Pharmacy = 'pharmacy';
    case School = 'school';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Grocery => 'Grocery',
            self::Pharmacy => 'Pharmacy',
            self::School => 'School',
            self::Other => 'Other',
        };
    }
}
