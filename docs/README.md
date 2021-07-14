# Introduction

`castor/async-message-bus` provides middleware to handle messages asyncronously
as part of your message bus.

# Basic Usage

Simply, instantiate the bus and provide some middleware.

You must create a message bus and pass some middleware to it. By default, we
provide middleware that finds handlers from a service container using a naming
convention.

```php
<?php

use Castor\MessageBus;
use Castor\Queue;

$driver = new Queue\InMemoryDriver();

$bus = new MessageBus();
$bus->add(new MessageBus\HandleAsync($driver));
$bus->add(new HandleMessage());

$bus->handle(MessageBus\Async::exec(new SomeCommand()));

// Then, in another process, like a worker...

$runner = new MessageBus\AsyncBusRunner($driver, $bus);
$runner->run('messages');

```