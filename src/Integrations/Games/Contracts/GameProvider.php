<?php

declare(strict_types=1);

namespace App\Integrations\Games\Contracts;

interface GameProvider
{
    public function listGames(): array;
    public function launchGame(array $player, string $gameCode): array;
    public function handleCallback(array $payload, array $headers = []): array;
}
