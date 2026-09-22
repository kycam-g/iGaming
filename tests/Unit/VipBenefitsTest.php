<?php
// Testes de regras determinísticas que não exigem servidor MySQL.
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Modules/Platform/VipBenefitService.php';
use App\Modules\Platform\VipBenefitService;
$service=new VipBenefitService();
$periods=new ReflectionMethod(VipBenefitService::class,'periods');
$tz=new DateTimeZone('America/Sao_Paulo');
foreach([
    ['2026-09-21 01:59:00','2026-09-20','2026-09-14','2026-09'],
    ['2026-09-21 02:01:00','2026-09-21','2026-09-21','2026-09'],
    ['2026-10-01 01:59:00','2026-09-30','2026-09-28','2026-09'],
    ['2026-10-01 02:01:00','2026-10-01','2026-09-28','2026-10'],
] as [$date,$daily,$weekly,$monthly]){
    $actual=$periods->invoke($service,new DateTimeImmutable($date,$tz));
    if($actual!==['daily'=>$daily,'weekly'=>$weekly,'monthly'=>$monthly])throw new RuntimeException('Período incorreto: '.$date);
}
$earned=new ReflectionMethod(VipBenefitService::class,'earned');
$levels=[1=>['cfg'=>['goal_cents'=>500]],2=>['cfg'=>['goal_cents'=>1000]]];
foreach([[0,0],[499,0],[500,1],[999,1],[1000,2]] as [$amount,$level]){
    if($earned->invoke($service,$levels,$amount)!==$level)throw new RuntimeException('Progressão VIP incorreta.');
}
$sql=file_get_contents(dirname(__DIR__,2).'/database/migrations/020_vip_recurring.sql');
foreach(['uq_vip_month_review','uq_vip_period','fk_vip_period_redemption','maintenance_mode','activated_at'] as $required){
    if(!str_contains($sql,$required))throw new RuntimeException('Restrição da migração ausente: '.$required);
}
foreach(['invalid',''] as $kind){try{$service->claim('example',$kind);throw new RuntimeException('Tipo inválido aceito.');}catch(DomainException $e){}}
echo "OK: viradas diária/semanal/mensal, progressão, validação de tipo e unicidade SQL.\n";
