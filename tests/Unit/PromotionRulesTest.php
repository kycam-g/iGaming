<?php
// Regras que dispensam conexão de banco: executar com php tests/Unit/PromotionRulesTest.php.
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Modules/Platform/PromotionRedemptionService.php';
use App\Modules\Platform\PromotionRedemptionService;
$service=new PromotionRedemptionService();
$window=new ReflectionMethod(PromotionRedemptionService::class,'window');
[$day,$start,$end]=$window->invoke(null);
assert(preg_match('/^\d{4}-\d{2}-\d{2}$/',$day)===1);
assert(strtotime($end)-strtotime($start)===86400);
assert((new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('H:i')==='21:00');
try{$service->claimCoupon('not-a-user','not valid !');throw new RuntimeException('Código inválido aceito.');}
catch(DomainException $e){assert(str_contains($e->getMessage(),'código válido'));}
$schema=file_get_contents(dirname(__DIR__,2).'/database/migrations/019_promotion_redemptions.sql');
assert(str_contains($schema,'UNIQUE KEY uq_promotion_user_coupon'));
assert(str_contains($schema,'UNIQUE KEY uq_promotion_day'));
echo "OK: janela Brasília, rejeição de código inválido e restrições únicas.\n";
