<?php

namespace Happyr\BrefMessenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Validator\ConstraintViolationInterface;

class ExceptionLogger implements EventSubscriberInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerMessageFailedEvent::class => ['onException', 20],
        ];
    }

    public function onException(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $throwable = $event->getThrowable();
        $firstNestedException = null;
        if ($throwable instanceof HandlerFailedException) {
            $envelope = $throwable->getEnvelope();
            $nestedExceptions = method_exists($throwable, 'getNestedExceptions') ? $throwable->getNestedExceptions() : $throwable->getWrappedExceptions();
            $firstNestedException = $nestedExceptions[array_key_first($nestedExceptions)];
        }

        if ($throwable instanceof ValidationFailedException) {
            $this->logValidationException($throwable);
        } else {
            $this->logException($envelope, $throwable, $event->getReceiverName(), $firstNestedException);
        }
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
