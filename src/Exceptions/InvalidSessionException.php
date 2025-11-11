<?php
declare(strict_types=1);

namespace DropProtocol\Exceptions;

/**
 * Exception thrown when session is invalid or expired
 */
class InvalidSessionException extends DropProtocolException
{
    public function __construct(string $message = 'Invalid or expired session', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}
