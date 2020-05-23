<?php

declare(strict_types=1);

namespace Happyr\BrefMessenger;

use Bref\Symfony\Messenger\Service\BusDriver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Using this dispatched will allow use of Symfony's failure strategies.
 */
class SymfonyBusDriver implements BusDriver
{
    private $logger;
    private $eventDispatcher;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $eventDispatcher)
    {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function putEnvelopeOnBus(MessageBusInterface $bus, Envelope $envelope, string $transportName): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->eventDispatcher->dispatch($event);

        if (!$event->shouldHandle()) {
            return;
        }

        try {
            $envelope = $bus->dispatch($envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp()));
        } catch (\Throwable $throwable) {
            $firstNestedException = null;
            if ($throwable instanceof HandlerFailedException) {
                $envelope = $throwable->getEnvelope();
                $nestedExceptions = $throwable->getNestedExceptions();
                $firstNestedException = $nestedExceptions[array_key_first($nestedExceptions)];
            }

            if ($throwable instanceof ValidationFailedException) {
                $this->logValidationException($throwable);
            } else {
                $this->logException($envelope, $throwable, $transportName, $firstNestedException);
            }

            $this->eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, $transportName, $throwable));

            return;
        }

        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, $transportName));

        $message = $envelope->getMessage();
        $this->logger->info('{class} was handled successfully (acknowledging to transport).', [
            'message' => $message,
            'transport' => $transportName,
            'class' => \get_class($message),
        ]);
    }

    private function logValidationException(ValidationFailedException $exception): void
    {
        $violations = $exception->getViolations();
        $violationMessages = [];
        /** @var ConstraintViolationInterface $v */
        foreach ($violations as $v) {
            $violationMessages[] = \sprintf('%s: %s', $v->getPropertyPath(), (string) $v->getMessage());
        }

        $this->logger->error('{class} did failed validation.', [
            'class' => get_class($exception->getViolatingMessage()),
            'violations' => \json_encode($violationMessages),
        ]);
    }

    private function logException(Envelope $envelope, \Throwable $throwable, string $transportName, ?\Throwable $firstNestedException): void
    {
        $message = $envelope->getMessage();
        $context = [
            'exception' => $throwable,
            'message' => $message,
            'transport' => $transportName,
            'class' => \get_class($message),
        ];

        if (null === $firstNestedException) {
            $logMessage = 'Dispatching {class} caused an exception: '.$throwable->getMessage();
        } else {
            $logMessage = 'Handling {class} caused an HandlerFailedException: '.$throwable->getMessage();
            $context['first_exception'] = $firstNestedException;
        }

        $this->logger->error($logMessage, $context);
    }
}
