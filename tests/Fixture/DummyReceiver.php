<?php

declare(strict_types=1);

namespace Happyr\BrefMessenger\Test\Fixture;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class DummyReceiver implements ReceiverInterface
{
    private $deliveriesOfEnvelopes;
    private $acknowledgeCount = 0;
    private $rejectCount = 0;

    public function __construct(array $deliveriesOfEnvelopes)
    {
        $this->deliveriesOfEnvelopes = $deliveriesOfEnvelopes;
    }

    public function get(): iterable
    {
        $val = array_shift($this->deliveriesOfEnvelopes);

        return null === $val ? [] : $val;
    }

    public function ack(Envelope $envelope): void
    {
        ++$this->acknowledgeCount;
    }

    public function reject(Envelope $envelope): void
    {
        ++$this->rejectCount;
    }

    public function getAcknowledgeCount(): int
    {
        return $this->acknowledgeCount;
    }

    public function getRejectCount(): int
    {
        return $this->rejectCount;
    }
}
