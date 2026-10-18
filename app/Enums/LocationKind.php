<?php

namespace App\Enums;

/**
 * What a saved place *is*, for the purpose of wording a notification.
 *
 * Derived from the existing `places.category` rather than stored: a place keeps
 * the one classification it has always had, and no data is duplicated or
 * migrated. `grocery` and `pharmacy` are both a Store; `school` and anything
 * unrecognised fall to Other.
 */
enum LocationKind: string
{
    case Home = 'home';
    case Office = 'office';
    case Store = 'store';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Office => 'Office',
            self::Store => 'Store',
            self::Other => 'Other',
        };
    }

    /**
     * The sentence a member sees when they arrive with work waiting here.
     *
     * The wording lives with the kind so the API, the app and the tests cannot
     * drift apart over what a Store arrival says.
     */
    public function arrivalMessage(int $count): string
    {
        return match ($this) {
            self::Home => $count === 1
                ? 'You have 1 household task to take care of.'
                : "You have {$count} household tasks to take care of.",
            self::Office => $count === 1
                ? 'You have 1 task associated with work.'
                : "You have {$count} tasks associated with work.",
            self::Store => $count === 1
                ? 'You have 1 item to buy here.'
                : "You have {$count} items to buy here.",
            self::Other => $count === 1
                ? 'You have 1 task here.'
                : "You have {$count} tasks here.",
        };
    }
}
