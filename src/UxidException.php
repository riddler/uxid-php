<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * A generation request the wire format refuses. Its `errorCode` names the
 * refusal from the closed set in {@see ErrorCode}; the message says what was
 * wrong in words. (`getCode()` is an integer in PHP, so the name has its own
 * property and `getCode()` is always 0.)
 */
final class UxidException extends \InvalidArgumentException
{
    /**
     * @param ErrorCode $errorCode the refusal's name
     * @param string    $message   what was wrong, in words
     */
    public function __construct(
        /** The refusal's name, from the closed set in {@see ErrorCode}. */
        public readonly ErrorCode $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
