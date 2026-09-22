<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/src/Modules/Platform/RebateService.php';
use App\Modules\Platform\RebateService;
function check(bool $ok,string $text):void{if(!$ok)throw new RuntimeException($text);}
// R$ 0,30 a 0,2% = 60 unidades de 1/10000 centavo; frações preservadas.
check(RebateService::unitsForBet(30,20)===600,'Cálculo em unidades incorreto.');
check(RebateService::availableMinor(RebateService::unitsForBet(30,20))===0,'Não arredondar cada aposta para cima.');
check(RebateService::availableMinor(RebateService::unitsForBet(30,20)*17)===1,'Frações devem se acumular até um centavo.');
check(RebateService::unitsForBet(100000,100)===10000000,'Rebate de 1% incorreto.');
check(RebateService::availableMinor(10000000)===1000,'R$ 10 esperados para R$ 1.000 a 1%.');
try{RebateService::unitsForBet(100,1001);throw new RuntimeException('Taxa excessiva aceita.');}catch(DomainException $expected){}
echo "Rebate math tests: OK\n";
