<?php
// Testes de estrutura sem banco: php tests/Unit/WelcomeRouletteTest.php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/src/Modules/Platform/WelcomeRouletteService.php';
require $root . '/src/Modules/Platform/PromotionRedemptionService.php';

use App\Modules\Platform\WelcomeRouletteService;
use App\Modules\Platform\PromotionRedemptionService;

function verify(bool $condition, string $error): void {
    if (!$condition) throw new RuntimeException($error);
}
$schema = file_get_contents($root.'/database/migrations/027_welcome_roulette.sql');
$service = file_get_contents($root.'/src/Modules/Platform/WelcomeRouletteService.php');
$routes = file_get_contents($root.'/public/index.php');
verify(class_exists(WelcomeRouletteService::class), 'Serviço da roleta indisponível.');
verify(method_exists(WelcomeRouletteService::class,'status') && method_exists(WelcomeRouletteService::class,'spin'), 'Rotas de status/giro não têm serviço.');
verify(method_exists(PromotionRedemptionService::class,'creditRoulette'), 'Integração com carteira ausente.');
verify(str_contains($schema, 'UNIQUE KEY uq_roulette_credit_source'), 'Chave única de origem ausente.');
verify(str_contains($schema, 'UNIQUE KEY uq_roulette_spin_redemption'), 'Chave única do resultado ausente.');
verify(str_contains($service, 'creditRoulette($db,$userId,$campaign,$awardCfg)') && str_contains($service, '\'forced_reward_cents\''), 'O prêmio sorteado deve percorrer o fluxo financeiro compartilhado.');
verify(str_contains($service, "pt.status='PAID'") && str_contains($service, 'pt.created_at>=?'), 'Fonte de depósito incorreta.');
verify(str_contains($service, 'pr.created_at>=?') && str_contains($service, 'used_spins=used_spins+1'), 'Origem da indicação ou consumo ausente.');
verify(str_contains($routes, '/api/promotions/roulette/spin') && str_contains($routes, '/api/promotions/roulette/status'), 'Endpoints não publicados.');
echo "OK: endpoints, fontes qualificadas e restrições de duplicidade da roleta. Teste de MySQL/carteira pendente.\n";
