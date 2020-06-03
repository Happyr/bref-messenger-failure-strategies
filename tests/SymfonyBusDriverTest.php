<?php

declare(strict_types=1);

namespace Happyr\BrefMessenger\Test;

use Happyr\BrefMessenger\SymfonyBusDriver;
use Happyr\BrefMessenger\Test\Fixture\DummyEventDispatcher;
use Happyr\BrefMessenger\Test\Fixture\DummyMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class SymfonyBusDriverTest extends TestCase
{
    public function testBusIsDispatchingMessages()
    {
        $apiMessage = new DummyMessage('API');
        $ipaMessage = new DummyMessage('IPA');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $bus->expects($this->at(0))->method('dispatch')->with(
            new Envelope($apiMessage, [new ReceivedStamp('transport'), new ConsumedByWorkerStamp()])
        )->willReturnArgument(0);

        $bus->expects($this->at(1))->method('dispatch')->with(
            new Envelope($ipaMessage, [new ReceivedStamp('transport'), new ConsumedByWorkerStamp()])
        )->willReturnArgument(0);

        $dispatcher = new DummyEventDispatcher();

        $worker = new SymfonyBusDriver(new NullLogger(), $dispatcher);
        $worker->putEnvelopeOnBus($bus, new Envelope($apiMessage), 'transport');
        $worker->putEnvelopeOnBus($bus, new Envelope($ipaMessage), 'transport');

        $events = $dispatcher->getEvents();
        $this->assertCount(4, $events);
        $this->assertInstanceOf(WorkerMessageReceivedEvent::class, $events[0]);
        $this->assertInstanceOf(WorkerMessageHandledEvent::class, $events[1]);
        $this->assertInstanceOf(WorkerMessageReceivedEvent::class, $events[2]);
        $this->assertInstanceOf(WorkerMessageHandledEvent::class, $events[3]);
    }
}
