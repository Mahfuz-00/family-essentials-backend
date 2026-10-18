<?php

namespace App\Enums;

/**
 * How a notification should announce itself.
 *
 * The names describe the intent; each platform gets as close as it allows, and
 * `PushMessage` maps the style to a channel id / iOS sound. See
 * `Docs/PRD.md` §4.8 for the honest per-platform matrix.
 */
enum AlertStyle: string
{
    case Silent = 'silent';
    case Vibrate = 'vibrate';
    case Sound = 'sound';
    case Voice = 'voice';

    public function label(): string
    {
        return match ($this) {
            self::Silent => 'Silent',
            self::Vibrate => 'Vibrate',
            self::Sound => 'Sound',
            self::Voice => 'Voice',
        };
    }

    /**
     * The Android channel the server should target. Channels are created by the
     * app with their sound and vibration fixed at creation.
     */
    public function androidChannel(): string
    {
        return 'arrivals_'.$this->value;
    }

    /**
     * The bundled iOS sound. Null means "no sound" (`silent`).
     */
    public function iosSound(): ?string
    {
        return match ($this) {
            self::Silent => null,
            self::Vibrate => null,
            self::Sound, self::Voice => 'arrival.caf',
        };
    }

    /**
     * iOS interruption level: passive for quiet styles, active for the rest.
     */
    public function iosInterruptionLevel(): string
    {
        return match ($this) {
            self::Silent => 'passive',
            self::Vibrate => 'passive',
            self::Sound, self::Voice => 'active',
        };
    }

    public function shouldSpeak(): bool
    {
        return $this === self::Voice;
    }

    /**
     * Read a stored value safely: an unknown or missing value falls back to
     * `sound` rather than leaving a member with no alert at all.
     */
    public static function fromUser(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Sound;
    }
}
