<?php

namespace Tests\Unit;

use App\Enums\AlertStyle;
use Tests\TestCase;

/**
 * The style-to-platform mapping. Each platform gets as close as it allows; this
 * is the one place the mapping is decided.
 */
class AlertStyleTest extends TestCase
{
    public function test_each_style_maps_to_an_android_channel(): void
    {
        $this->assertSame('arrivals_silent', AlertStyle::Silent->androidChannel());
        $this->assertSame('arrivals_vibrate', AlertStyle::Vibrate->androidChannel());
        $this->assertSame('arrivals_sound', AlertStyle::Sound->androidChannel());
        $this->assertSame('arrivals_voice', AlertStyle::Voice->androidChannel());
    }

    public function test_silent_and_vibrate_carry_no_ios_sound(): void
    {
        $this->assertNull(AlertStyle::Silent->iosSound());
        $this->assertNull(AlertStyle::Vibrate->iosSound());

        // Sound and voice both arrive with the bundled file; voice adds speech
        // once the app opens, because iOS cannot speak from a push.
        $this->assertSame('arrival.caf', AlertStyle::Sound->iosSound());
        $this->assertSame('arrival.caf', AlertStyle::Voice->iosSound());
    }

    public function test_quiet_styles_are_passive_on_ios(): void
    {
        $this->assertSame('passive', AlertStyle::Silent->iosInterruptionLevel());
        $this->assertSame('passive', AlertStyle::Vibrate->iosInterruptionLevel());
        $this->assertSame('active', AlertStyle::Sound->iosInterruptionLevel());
        $this->assertSame('active', AlertStyle::Voice->iosInterruptionLevel());
    }

    public function test_only_voice_speaks(): void
    {
        $this->assertTrue(AlertStyle::Voice->shouldSpeak());

        foreach ([AlertStyle::Silent, AlertStyle::Vibrate, AlertStyle::Sound] as $style) {
            $this->assertFalse($style->shouldSpeak());
        }
    }

    public function test_an_unknown_stored_value_falls_back_to_sound(): void
    {
        // Failing loud is worse than failing to the sensible default.
        $this->assertSame(AlertStyle::Sound, AlertStyle::fromUser(null));
        $this->assertSame(AlertStyle::Sound, AlertStyle::fromUser('loud'));
        $this->assertSame(AlertStyle::Voice, AlertStyle::fromUser('voice'));
    }
}
