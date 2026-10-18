<?php

namespace App\Enums;

enum PlaceCategory: string
{
    case Home = 'home';
    case Office = 'office';
    case Grocery = 'grocery';
    case Pharmacy = 'pharmacy';
    case School = 'school';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Office => 'Office',
            self::Grocery => 'Grocery',
            self::Pharmacy => 'Pharmacy',
            self::School => 'School',
            self::Other => 'Other',
        };
    }

    /**
     * The notification-facing kind for this category.
     *
     * A pharmacy and a grocery are both somewhere you go to buy things, so both
     * read as a Store; a school is neither home, work nor a shop.
     */
    public function kind(): LocationKind
    {
        return match ($this) {
            self::Home => LocationKind::Home,
            self::Office => LocationKind::Office,
            self::Grocery, self::Pharmacy => LocationKind::Store,
            self::School, self::Other => LocationKind::Other,
        };
    }
}
