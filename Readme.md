# Bref Messenger failure strategies

So you have fallen in love with [Bref](https://bref.sh) and you really want to use
Symfony's excellent Messenger component. You've probably also installed the 
[Bref Symfony Messenger bundle](https://github.com/brefphp/symfony-messenger)
that allows you to publish messages on SQS and SNS etc. But you are missing something...
You want to be able to use Symfony Messenger retry strategies, right?

This is the package for you!

## Install

```cli
composer require happyr/bref-messenger-failure-strategies
```

Now you have a class called `Happyr\BrefMessenger\SymfonyBusDriver` that implements
`Bref\Messenger\Service\BusDriver`. Feel free to configure your consumers with this 
new class. 

## Example

On each consumer you can choose to let Symfony handle failures as described in
[the documentation](https://symfony.com/doc/current/messenger.html#retries-failures). 


```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        failure_transport: failed
        transports:
            failed: 'doctrine://default?queue_name=failed'
            workqueue:
              dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'
              retry_strategy:
                  max_retries: 3
                  # milliseconds delay
                  delay: 1000
                  multiplier: 2
                  max_delay: 60

services:
    Happyr\BrefMessenger\SymfonyBusDriver: 
        autowire: true

    my_sqs_consumer:
        class: Bref\Messenger\Service\Sqs\SqsConsumer
        arguments:
            - '@Happyr\BrefMessenger\SymfonyBusDriver'
            - '@messenger.routable_message_bus'
            - '@Symfony\Component\Messenger\Transport\Serialization\SerializerInterface'
            - 'my_sqs' # Same as transport name
        tags:
            - { name: bref_messenger.consumer }
# ...

```

The delay is only supported on SQS "normal queue". If you are using SNS or SQS FIFO
you should use the failure queue directly.

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        failure_transport: failed
        transports:
            failed: 'doctrine://default?queue_name=failed'
            workqueue:
              dsn: 'sns://arn:aws:sns:us-east-1:1234567890:foobar'
              retry_strategy:
                  max_retries: 0
services:
    # ...

```

Make sure you re-run the failure queue time to time. The following config will 
run a script for 5 seconds every 30 minutes. It will run for 5 seconds even though
no messages has failed. 

```yaml
# serverless.yml

functions:
    website:
        # ...
    consumer:
        # ...

    console:
        handler: bin/console
        Timeout: 120 # in seconds
        layers:
            - ${bref:layer.php-74}
            - ${bref:layer.console}
        events:
            - schedule:
                  rate: rate(30 minutes)
                  input:
                      cli: messenger:consume failed --time-limit=5 --limit=50

```