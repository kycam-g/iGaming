<?php

declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
\App\Core\Support\Env::load(dirname(__DIR__).'/.env');
$lock=fopen(sys_get_temp_dir().'/mz90-agency-process.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Rotina de agência já em execução.\n");exit(1);}
try {
 $result=(new \App\Modules\Platform\AgencyService())->runBatch();
 echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
 exit(0);
} catch(\Throwable $error) {
 fwrite(STDERR,"Agência: ".$error->getMessage()."\n");exit(1);
}
