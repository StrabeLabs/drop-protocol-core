<?php
declare(strict_types=1);

namespace DropProtocol\Contracts;

interface CookieManagerInterface
{
    public function setCookie(string $name, string $value, array $options): void;
}
