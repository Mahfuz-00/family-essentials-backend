<?php

namespace App\Notifications;

use App\Enums\AlertStyle;

/**
 * One notification to deliver.
 *
 * `style` is the member's preference, not a promise: each platform gets as close
 * as it allows (see the matrix in `Docs/PRD.md` §4.8). `route` is what the app
 * opens on tap — `place:12` or `request:91`.
 */
final readonly class PushMessage
{
    /**
     * @param  array<string, string>  $data  Extra payload keys, string-valued.
     */
    public function __construct(
        public string $title,
        public string $body,
        public AlertStyle $style = AlertStyle::Sound,
        public array $data = [],
        public ?string $route = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        return array_filter(
            array_merge($this->data, [
                // The client needs the style to choose the right channel when it
                // shows a foreground message itself.
                'style' => $this->style->value,
            ], $this->route === null ? [] : ['route' => $this->route]),
            static fn (string $value): bool => $value !== '',
        );
    }
}
