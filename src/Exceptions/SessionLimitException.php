<?php
declare(strict_types=1);

namespace DropProtocol\Exceptions;

/**
 * Exception thrown when user exceeds maximum session limit
 */
class SessionLimitException extends DropProtocolException
{
    public function __construct(string $message = 'Session limit exceeded', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
