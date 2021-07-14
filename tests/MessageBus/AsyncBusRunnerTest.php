<?php

declare(strict_types=1);

/**
 * @project Castor Async Message Bus
 * @link https://github.com/castor-labs/async-message-bus
 * @package castor/async-message-bus
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2021 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Castor\MessageBus;

use Castor\Queue;
use PHPUnit\Framework\TestCase;

/**
 * Class AsyncBusRunnerTest.
 *
 * @covers \Castor\MessageBus\AsyncBusRunner
 *
 * @internal
 */
class AsyncBusRunnerTest extends TestCase
{
    public function testItRunsNormally(): void
    {
        $queue = new Queue\InMemoryDriver();
        $serializer = $this->createMock(MessageSerializer::class);
        $bus = $this->createMock(Handler::class);

        $serializer->expects(self::exactly(3))
            ->method('deserialize')
            ->with('foo-message')
            ->willReturn(new FooMessage())
        ;
        $bus->expects(self::exactly(3))
            ->method('handle')
            ->with($this->isInstanceOf(FooMessage::class))
        ;

        $runner = new AsyncBusRunner($queue, $bus, $serializer);

        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $runner->run('messages');
    }

    public function testItRunsNoMoreThanTwoMessages(): void
    {
        $queue = new Queue\InMemoryDriver();
        $serializer = $this->createMock(MessageSerializer::class);
        $bus = $this->createMock(Handler::class);

        $serializer->expects(self::exactly(2))
            ->method('deserialize')
            ->with('foo-message')
            ->willReturn(new FooMessage())
        ;
        $bus->expects(self::exactly(2))
            ->method('handle')
            ->with($this->isInstanceOf(FooMessage::class))
        ;

        $runner = new AsyncBusRunner($queue, $bus, $serializer, 2);

        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');

        $runner->run('messages');
        self::assertSame(3, $queue->count('messages'));
    }

    public function testItContinuesOnNormalErrors(): void
    {
        $baseDir = dirname(__DIR__, 2);
        $queue = new Queue\InMemoryDriver();
        $serializer = $this->createMock(MessageSerializer::class);
        $bus = $this->createMock(Handler::class);

        $serializer->expects(self::exactly(5))
            ->method('deserialize')
            ->with('foo-message')
            ->willReturn(new FooMessage())
        ;
        $bus->expects(self::exactly(5))
            ->method('handle')
            ->with($this->isInstanceOf(FooMessage::class))
            ->will($this->onConsecutiveCalls(
                $this->throwException(new \RuntimeException('something bad happened')),
                null,
                null,
                null,
                null,
            ))
        ;

        $runner = new AsyncBusRunner($queue, $bus, $serializer);

        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');

        $runner->run('messages');
        $this->expectOutputString("Error handling \"Castor\\MessageBus\\FooMessage\": RuntimeException thrown at {$baseDir}/tests/MessageBus/AsyncBusRunnerTest.php, line 99 says that something bad happened\n");
        self::assertSame(0, $queue->count('messages'));
    }

    public function testItStopsAtTypeError(): void
    {
        $baseDir = dirname(__DIR__, 2);
        $queue = new Queue\InMemoryDriver();
        $serializer = $this->createMock(MessageSerializer::class);
        $bus = $this->createMock(Handler::class);

        $serializer->expects(self::once())
            ->method('deserialize')
            ->with('foo-message')
            ->willReturn(new FooMessage())
        ;
        $bus->expects(self::once())
            ->method('handle')
            ->with($this->isInstanceOf(FooMessage::class))
            ->willThrowException(new \TypeError('Argument 1 of something need something else'))
        ;

        $runner = new AsyncBusRunner($queue, $bus, $serializer);

        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');
        $queue->publish('messages', 'foo-message');

        $runner->run('messages');
        $this->expectOutputString("Error handling \"Castor\\MessageBus\\FooMessage\": TypeError thrown at {$baseDir}/tests/MessageBus/AsyncBusRunnerTest.php, line 135 says that Argument 1 of something need something else\nCancelling consuming due to an important PHP error...\n");
        self::assertSame(4, $queue->count('messages'));
    }
}
