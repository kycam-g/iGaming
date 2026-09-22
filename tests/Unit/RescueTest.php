<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/src/Modules/Platform/RescueService.php';
use App\Modules\Platform\RescueService;
$cases=[[2000,300,60],[50000,500,2500],[100000,700,7000],[10000,0,0],[99,333,3],[0,300,0]];
foreach($cases as [$loss,$rate,$expected]){
    $actual=RescueService::refundMinor($loss,$rate);
    if($actual!==$expected)throw new RuntimeException("Rescue calculation failed for {$loss}, {$rate}: {$actual} !== {$expected}");
}
foreach([[-1,100],[100,-1],[100,10001]] as [$loss,$rate]){
    try{RescueService::refundMinor($loss,$rate);throw new RuntimeException('Invalid values accepted');}
    catch(DomainException $expected){}
}
$sql=file_get_contents(dirname(__DIR__,2).'/database/migrations/025_rescue_daily.sql');
foreach(['uq_rescue_user_day','fk_rescue_user','fk_rescue_redemption','effective_day'] as $required)if(!str_contains((string)$sql,$required))throw new RuntimeException('Missing migration guard '.$required);
echo "Rescue unit test passed (6 calculations, 3 invalid cases, 4 schema guards).\n";
