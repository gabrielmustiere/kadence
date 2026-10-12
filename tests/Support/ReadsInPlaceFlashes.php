<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Symfony\UX\TwigComponent\Test\RenderedComponent;

trait ReadsInPlaceFlashes
{
    /**
     * @return array{type: string, message: string}|null the flash an action rendered in place sent to the layout
     */
    private static function flashOf(TestLiveComponent $component, RenderedComponent $rendered): ?array
    {
        /** @var array{payload?: mixed}|null $event the test helper documents the payload under « data », LiveResponder sends it under « payload » */
        $event = $component->getDispatchedBrowserEvent($rendered, 'app:flash');
        $payload = $event['payload'] ?? null;
        if (!\is_array($payload) || !\is_string($payload['type'] ?? null) || !\is_string($payload['message'] ?? null)) {
            return null;
        }

        return ['type' => $payload['type'], 'message' => $payload['message']];
    }
}
