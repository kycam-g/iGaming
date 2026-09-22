<?php

declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
\App\Core\Support\Env::load(dirname(__DIR__).'/.env');
try{
    $result=(new \App\Modules\Platform\RescueService())->runBatch();
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    if(!empty($result['errors']))exit(1);
}catch(\Throwable $error){fwrite(STDERR,'Rescue failed: '.$error->getMessage().PHP_EOL);exit(1);}
