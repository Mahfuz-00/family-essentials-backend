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
     * The sentence a member sees when their own cue fires.
     *
     * The wording follows the cue — a direction and the place's kind — rather
     * than the task's shop: "leaving work" and "arriving home" are different
     * moments and deserve different sentences.
     */
    public function triggerMessage(GeofenceEventType $direction, int $count, string $placeName): string
    {
        $items = $count === 1 ? 'item' : 'items';
        $tasks = $count === 1 ? 'task' : 'tasks';

        if ($direction === GeofenceEventType::Exit) {
            return match ($this) {
                self::Office => "Leaving work: {$count} {$items} to pick up.",
                self::Home => "You left home: {$count} {$tasks} still open.",
                self::Store => "Leaving {$placeName} — {$count} {$items} still on the list.",
                self::Other => "You left {$placeName}: {$count} {$tasks}.",
            };
        }

        return match ($this) {
            self::Home => "You're home — {$count} {$items} still on the list.",
            self::Office => "At work: {$count} {$tasks} for you.",
            self::Store => "You're at {$placeName}: {$count} {$items}.",
            self::Other => "At {$placeName}: {$count} {$tasks}.",
        };
    }

    /**
     * The sentence used when a place is entered and the trigger is not known to
     * be an exit — kept for the place-specific shopping list copy.
     */
    public function arrivalMessage(int $count): string
    {
        return $this->triggerMessage(GeofenceEventType::Enter, $count, $this->label());
    }
}
