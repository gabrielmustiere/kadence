<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\ComponentToolsTrait;

/**
 * An action rendered in place has no page to show the flashes of the session on: its message goes to the flash stack of
 * the layout as a browser event instead.
 */
trait NotifiesInPlace
{
    use ComponentToolsTrait;

    private function notify(string $type, string $message): void
    {
        $this->dispatchBrowserEvent('app:flash', ['type' => $type, 'message' => $message]);
    }
}
