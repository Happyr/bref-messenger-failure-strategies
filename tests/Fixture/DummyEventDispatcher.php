<?php

declare(strict_types=1);

namespace Happyr\BrefMessenger\Test\Fixture;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DummyEventDispatcher implements EventDispatcherInterface
{
    private $events = [];

    public function dispatch(object $event, string $eventName = null): object
    {
        $this->events[] = $event;

        return $event;
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
