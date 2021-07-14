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
 * Class PhpMessageSerializer.
 */
final class PhpMessageSerializer implements MessageSerializer
{
    private static ?PhpMessageSerializer $instance = null;

    public static function instance(): PhpMessageSerializer
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function serialize(object $message): string
    {
        return serialize($message);
    }

    public function deserialize(string $rawMessage): object
    {
        $result = unserialize($rawMessage, ['allowed_classes' => true]);
        if (!is_object($result)) {
            throw new \RuntimeException('Could not deserialize message into an object');
        }

        return $result;
    }
}
