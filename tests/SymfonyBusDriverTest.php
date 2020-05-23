<?php

declare(strict_types=1);
namespace Happyr\BrefMessenger\Test;


use Happyr\BrefMessenger\SymfonyBusDriver;
use Happyr\BrefMessenger\Test\Fixture\DummyMessage;
use Happyr\BrefMessenger\Test\Fixture\DummyReceiver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Worker;

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

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));

        $worker = new SymfonyBusDriver(new NullLogger(), new EventDispatcher());
        $worker->putEnvelopeOnBus($bus, new Envelope($apiMessage), 'transport');
        $worker->putEnvelopeOnBus($bus, new Envelope($ipaMessage), 'transport');
    }
}
