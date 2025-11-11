<?php
declare(strict_types=1);

namespace DropProtocol\Exceptions;

/**
 * Exception thrown when security violation is detected
 * 
 * Triggered by IP or User-Agent changes in strict validation mode.
 */
class SecurityViolationException extends DropProtocolException
{
    public function __construct(string $message = 'Security violation detected', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
