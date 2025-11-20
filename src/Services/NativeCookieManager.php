<?php
declare(strict_types=1);

namespace DropProtocol\Services;

use DropProtocol\Contracts\CookieManagerInterface;

class NativeCookieManager implements CookieManagerInterface
{
    public function setCookie(string $name, string $value, array $options): void
    {
        setcookie(
            $name,
            $value,
            $options
        );
    }
}
