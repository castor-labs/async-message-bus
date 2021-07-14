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
use Exception;

/**
 * Class HandleAsync is a middleware that sends messages to a queue in order
 * to handle them later.
 *
 * It handles retrying and re-queueing failed messages. It also provides a mechanism
 * to store failed messages in another queue after a certain amount of retries.
 */
final class HandleAsync implements Middleware
{
    private Queue\Driver $queue;
    private MessageSerializer $serializer;
    private string $queueName;
    private int $maxRetries;
    private bool $storeFailed;
    private string $failedSuffix;

    /**
     * HandleAsync constructor.
     */
    public function __construct(
        Queue\Driver $queue,
        MessageSerializer $serializer = null,
        string $queueName = 'messages',
        string $failedSuffix = '.failed',
        int $maxRetries = 10,
        bool $storeFailed = true
    ) {
        $this->queue = $queue;
        $this->queueName = $queueName;
        $this->failedSuffix = $failedSuffix;
        $this->maxRetries = $maxRetries;
        $this->storeFailed = $storeFailed;
        $this->serializer = $serializer ?? PhpMessageSerializer::instance();
    }

    /**
     * @throws Exception
     */
    public function process(object $message, Stack $stack): void
    {
        if (!$message instanceof Envelope) {
            $stack->next()->handle($message);

            return;
        }

        $async = $message->open(Async::class);
        if (!$async instanceof Async) {
            $stack->next()->handle($message);

            return;
        }

        if (!$async->hasBeenPublished()) {
            $this->publish($async, $message);

            return;
        }

        try {
            $stack->next()->handle($message);
        } catch (Exception $exception) {
            $this->publish($async, $message);

            throw $exception;
        }
    }

    private function publish(Async $async, object $message): void
    {
        $isMaxRetry = $async->getPublishCount() >= $this->maxRetries;
        if (true === $isMaxRetry && false === $this->storeFailed) {
            // We do not publish anything if store failed mode is not enabled
            // and we have reached the maximum retries.
            return;
        }

        // Queue name in the message has priority over the default queue.
        $queueName = $async->getQueueName() ?? $this->queueName;

        // If the queuing count reaches the max retries needs to go to the failed queue.
        if ($isMaxRetry) {
            $queueName .= $this->failedSuffix;
        }

        $async->registerPublish();
        $this->queue->publish($queueName, $this->serializer->serialize($message));
    }
}
