<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use App\Modules\Users\UserRepository;
use App\Modules\Wallet\WalletService;
use DomainException;
use RuntimeException;

final class Games2ApiGameService
{
    public function __construct(private readonly Games2ApiConfigService $config,private readonly WalletService $wallet,private readonly UserRepository $users){}

    public function launch(array $user,int $gameId): array
    {
        if(!$this->config->isEnabled()) throw new DomainException('Integração Games2API está desativada no painel administrativo.');
        if($gameId<=0)throw new DomainException('Jogo inválido.');
        if(($user['status']??'')!=='ACTIVE')throw new DomainException('Conta indisponível para jogar.');
        $stmt=Database::connection()->prepare("SELECT g.id,g.external_id,g.name,g.enabled,g.api_source,p.code provider_code,p.enabled provider_enabled FROM casino_games g JOIN casino_providers p ON p.id=g.provider_id WHERE g.id=:id LIMIT 1");
        $stmt->execute(['id'=>$gameId]);$game=$stmt->fetch();
        if(!$game||!((int)$game['enabled'])||!((int)$game['provider_enabled']))throw new DomainException('Jogo indisponível.');
        if(strtoupper((string)$game['api_source'])!=='GAMES2API')throw new DomainException('Este jogo não pertence à integração Games2API.');
        $credentials=$this->config->credentials();
        $payload=['method'=>'game_launch','agent_code'=>$credentials['agent_code'],'agent_token'=>$credentials['agent_token'],'user_code'=>(string)$user['public_id'],'provider_code'=>strtoupper(trim((string)$game['provider_code'])),'game_code'=>(string)$game['external_id'],'lang'=>'pt'];
        $response=$this->postJson((string)$this->config->publicConfig()['base_url'],$payload);
        $this->assertSuccess($response,'GAME_LAUNCH_FAILED');
        $url=trim((string)($response['launch_url']??$response['game_url']??$response['url']??''));
        if($url===''||!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https')throw new DomainException('Games2API não retornou uma URL HTTPS válida para o jogo.');
        return ['launch_url'=>$url,'provider_code'=>$payload['provider_code'],'game_code'=>$payload['game_code']];
    }

    public function handleCallback(array $payload): array
    {
        if(!$this->config->isEnabled())throw new DomainException('GAMES2API_DISABLED');
        $method=strtolower(trim((string)($payload['method']??'')));
        if(!in_array($method,['user_balance','transaction'],true))throw new DomainException('METHOD_NOT_SUPPORTED');
        if(!$this->config->authenticateCallback((string)($payload['agent_code']??''),(string)($payload['agent_secret']??'')))throw new DomainException('INVALID_CREDENTIALS');
        $userCode=trim((string)($payload['user_code']??''));
        if($userCode==='')throw new DomainException('USER_CODE_REQUIRED');
        $user=$this->users->findByPublicId($userCode);
        if(!$user||($user['status']??'')!=='ACTIVE')throw new DomainException('INVALID_USER');
        if($method==='user_balance')return ['status'=>1,'msg'=>'SUCCESS','balance'=>$this->money($this->wallet->accountBalanceMinor((string)$user['id'],'CASH'))];

        $event=is_array($payload['slot']??null)?$payload['slot']:[];
        $txnId=trim((string)($event['txn_id']??$payload['txn_id']??''));
        $gameCode=trim((string)($event['game_code']??$payload['game_code']??''));
        if($txnId===''||$gameCode==='')throw new DomainException('TRANSACTION_FIELDS_REQUIRED');
        $betMinor=$this->toMinor($event['bet_money']??$event['bet']??0);
        $winMinor=$this->toMinor($event['win_money']??$event['win']??0);
        $txnType=strtolower(trim((string)($event['txn_type']??'debit_credit')));
        $eventType=$betMinor>0&&$winMinor>0?'WINBET':($betMinor>0?'BET':'WIN');
        if($betMinor===0&&$winMinor===0&&$txnType!=='debit_credit')throw new DomainException('INVALID_AMOUNT');
        $result=$this->wallet->settleCasino((string)$user['id'],$betMinor,$winMinor,'GAMES2API',$txnId,$gameCode,$eventType);
        return ['status'=>1,'msg'=>'SUCCESS','balance'=>$this->money((int)$result['balance_minor'])];
    }

    private function postJson(string $url,array $payload): array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('Extensão cURL do PHP não está habilitada.');
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $ch=curl_init(rtrim($url,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','User-Agent: MZ90-Games2API/1.0'],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);
        if($raw===false)throw new RuntimeException('Falha de rede: '.$error);
        $decoded=json_decode((string)$raw,true,512,JSON_BIGINT_AS_STRING);
        if($status<200||$status>=300)throw new RuntimeException('HTTP '.$status.' retornado pela Games2API.');
        if(!is_array($decoded))throw new RuntimeException('Resposta inválida da Games2API ('.($contentType?:'sem Content-Type').').');
        return $decoded;
    }
    private function assertSuccess(array $response,string $fallback): void { $status=$response['status']??null;$ok=$status===true||$status===1||$status==='1'||strtolower((string)$status)==='success';if(!$ok)throw new DomainException(trim((string)($response['msg']??$response['message']??$response['error']??$fallback))); }
    private function toMinor(mixed $value): int { if(!is_numeric($value))throw new DomainException('INVALID_AMOUNT');$amount=(float)$value;if(!is_finite($amount)||$amount<0||$amount>100000000)throw new DomainException('INVALID_AMOUNT');return (int)round($amount*100); }
    private function money(int $minor): float { return round($minor/100,2); }
}
