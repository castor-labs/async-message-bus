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
 * Interface MessageSerializer.
 */
interface MessageSerializer
{
    /**
     * Serializes a message.
     */
    public function serialize(object $message): string;

    /**
     * Deserializes a message.
     */
    public function deserialize(string $rawMessage): object;
}
