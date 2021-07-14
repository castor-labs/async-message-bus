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
use Error;
use Throwable;

/**
 * Class AsyncBusRunner passes messages from a queue to a message bus.
 *
 * It is a helper class intended to make easy the process of running your
 * asynchronous message bus in a worker context.
 *
 * It also provides three cancellation methods out of the box. You can cancel
 * the consuming when a memory limit is reached, when a x number of messages
 * has been handled or when x number of seconds have passed.
 */
class AsyncBusRunner
{
    private Queue\Driver $queue;
    private Handler $bus;
    private MessageSerializer $serializer;
    private int $maxMessages;
    private int $maxMemory;
    private int $maxSeconds;

    /**
     * AsyncBusRunner constructor.
     */
    public function __construct(
        Queue\Driver $queue,
        Handler $bus,
        MessageSerializer $serializer = null,
        int $maxMessages = 0,
        int $maxMemory = 0,
        int $maxSeconds = 0
    ) {
        $this->queue = $queue;
        $this->bus = $bus;
        $this->serializer = $serializer ?? PhpMessageSerializer::instance();
        $this->maxMessages = $maxMessages;
        $this->maxMemory = $maxMemory;
        $this->maxSeconds = $maxSeconds;
    }

    /**
     * @psalm-param  null|callable(Throwable,object,callable):void $errorHandler
     */
    public function run(string $queue, callable $errorHandler = null): void
    {
        $callback = $this->createHandler($errorHandler);

        $callback = $this->wrapMaxMemory($this->wrapMaxMessages($this->wrapMaxSeconds($callback)));

        $this->queue->consume($queue, $callback);
    }

    /**
     * @param null|callable(Throwable,object,callable):void $errorHandler
     *
     * @return callable(string,callable):void
     */
    private function createHandler(callable $errorHandler = null): callable
    {
        if (null === $errorHandler) {
            $errorHandler = static function (Throwable $exception, object $message, callable $cancel): void {
                echo sprintf(
                    'Error handling "%s": %s thrown at %s, line %s says that %s',
                    get_class($message),
                    get_class($exception),
                    $exception->getFile(),
                    $exception->getLine(),
                    $exception->getMessage()
                ).PHP_EOL;
                if ($exception instanceof Error) {
                    echo 'Cancelling consuming due to an important PHP error...'.PHP_EOL;
                    $cancel();
                }
            };
        }

        return function (string $rawMessage, callable $cancel) use ($errorHandler): void {
            $message = $this->serializer->deserialize($rawMessage);

            try {
                $this->bus->handle($message);
            } catch (Throwable $exception) {
                $errorHandler($exception, $message, $cancel);
            }
        };
    }

    /**
     * @psalm-param callable(string,callable):void $callback
     * @psalm-return callable(string,callable):void
     * @psalm-suppress MixedAssignment
     * @psalm-suppress MixedOperand
     */
    private function wrapMaxMessages(callable $callback): callable
    {
        if ($this->maxMessages <= 0) {
            return $callback;
        }
        $messageCount = 0;

        return function (string $rawMessage, callable $cancel) use ($callback, &$messageCount) {
            $callback($rawMessage, $cancel);
            ++$messageCount;
            if ($messageCount >= $this->maxMessages) {
                $cancel();
            }
        };
    }

    /**
     * @psalm-param callable(string,callable):void $callback
     * @psalm-return callable(string,callable):void
     */
    private function wrapMaxMemory(callable $callback): callable
    {
        if ($this->maxMemory <= 0) {
            return $callback;
        }

        return function (string $rawMessage, callable $cancel) use ($callback) {
            $callback($rawMessage, $cancel);
            if ($this->maxMemory < memory_get_usage(true)) {
                $cancel();
            }
        };
    }

    /**
     * @psalm-param callable(string,callable):void $callback
     * @psalm-return callable(string,callable):void
     */
    private function wrapMaxSeconds(callable $callback): callable
    {
        if ($this->maxSeconds <= 0) {
            return $callback;
        }
        $startTime = time();

        return function (string $rawMessage, callable $cancel) use ($startTime, $callback) {
            $callback($rawMessage, $cancel);
            if ($this->maxSeconds >= time() - $startTime) {
                $cancel();
            }
        };
    }
}
