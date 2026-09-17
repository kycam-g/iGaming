<?php

declare(strict_types=1);

namespace App\Core\Security;

final class Token
{
    public static function generate(): string { return bin2hex(random_bytes(32)); }
    public static function hash(string $token): string { return hash('sha256', $token); }
}
