<?php

declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
\App\Core\Support\Env::load(dirname(__DIR__).'/.env');
$lock=fopen(sys_get_temp_dir().'/mz90-vip-process.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"VIP job já em execução.\n");exit(1);}
try{
    $result=(new \App\Modules\Platform\VipBenefitService())->runBatch();
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    exit(empty($result['errors'])?0:1);
}catch(Throwable $e){fwrite(STDERR,"VIP job falhou: ".$e->getMessage()."\n");exit(1);}
