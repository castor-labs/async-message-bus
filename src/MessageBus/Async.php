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

/**
 * Class Async allows to wrap a message to be handled by the Async Middleware
 * and execute it asynchronously.
 */
class Async extends Envelope
{
    private ?string $queueName;
    private int $publishCount;

    /**
     * Async constructor.
     */
    public function __construct(object $message, string $queueName = null)
    {
        parent::__construct($message);
        $this->queueName = $queueName;
        $this->publishCount = 0;
    }

    public static function exec(object $command, string $queueName = null): Async
    {
        return new self($command, $queueName);
    }

    public function getQueueName(): ?string
    {
        return $this->queueName;
    }

    public function getPublishCount(): int
    {
        return $this->publishCount;
    }

    public function hasBeenPublished(): bool
    {
        return $this->publishCount > 0;
    }

    public function registerPublish(): void
    {
        ++$this->publishCount;
    }
}
