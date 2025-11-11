<?php
declare(strict_types=1);

namespace DropProtocol\Services;

/**
 * Cryptographically secure session token generator
 */
final class SessionService
{
    private function __construct()
    {
    }

    /**
     * Generate random session token
     *
     * @param int $length Number of random bytes (default: 32 = 256 bits entropy)
     * @return string Hexadecimal session token (length * 2 characters)
     */
    public static function generate(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
