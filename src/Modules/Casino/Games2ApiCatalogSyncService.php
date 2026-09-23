<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use DomainException;
use RuntimeException;

final class Games2ApiCatalogSyncService
{
    public function __construct(private readonly Games2ApiConfigService $config){}

    public function sync(): array
    {
        if(!$this->multiSourceIndexReady())throw new DomainException('Execute as migrations antes de sincronizar a Games2API.');
        if(!$this->config->isEnabled())throw new DomainException('Ative a Games2API antes de sincronizar o catálogo.');
        $credentials=$this->config->credentials();$url=(string)$this->config->publicConfig()['base_url'];
        $auth=['agent_code'=>$credentials['agent_code'],'agent_token'=>$credentials['agent_token']];
        $providersResponse=$this->postJson($url,['method'=>'provider_list']+$auth);$this->assertSuccess($providersResponse,'provider_list');
        $providers=$this->extractList($providersResponse,['data.providers','data','providers','result.providers','result']);
        if(!$providers)throw new DomainException('Games2API não retornou provedores no formato esperado.');
        $pdo=Database::connection();$providerCount=0;$gameCount=0;$skipped=0;$now=(new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $providerUpsert=$pdo->prepare("INSERT INTO casino_providers(code,name,enabled,mode,api_source) VALUES(:code,:name,:enabled,'PRODUCTION','GAMES2API') ON DUPLICATE KEY UPDATE name=VALUES(name),enabled=VALUES(enabled),mode='PRODUCTION'");
        $providerLookup=$pdo->prepare('SELECT id FROM casino_providers WHERE code=:code LIMIT 1');
        $gameUpsert=$pdo->prepare("INSERT INTO casino_games(provider_id,external_id,name,category,image_url,enabled,featured,sort_order,access_count,api_source,source_type,source_distribution,source_original,last_synced_at) VALUES(:provider,:external,:name,:category,:image,:enabled,0,100,0,'GAMES2API',:source_type,'GAMES2API',NULL,:synced) ON DUPLICATE KEY UPDATE name=VALUES(name),category=VALUES(category),image_url=COALESCE(VALUES(image_url),casino_games.image_url),enabled=VALUES(enabled),source_type=VALUES(source_type),source_distribution='GAMES2API',last_synced_at=VALUES(last_synced_at)");
        $pdo->beginTransaction();
        try{
            foreach($providers as $provider){if(!is_array($provider)){continue;}$rawCode=trim((string)($provider['provider_code']??$provider['code']??$provider['id']??''));if($rawCode==='')continue;$code=$this->providerCode($rawCode);$name=trim((string)($provider['provider_name']??$provider['name']??$rawCode));$enabled=$this->enabled($provider['status']??$provider['enabled']??1);$providerUpsert->execute(['code'=>$code,'name'=>mb_substr($name,0,120),'enabled'=>$enabled]);$providerLookup->execute(['code'=>$code]);$providerId=(int)$providerLookup->fetchColumn();if($providerId<=0)continue;$providerCount++;
                $games=$this->inlineGames($provider);
                if(!$games){$response=$this->postJson($url,['method'=>'game_list']+$auth+['provider_code'=>$rawCode]);$this->assertSuccess($response,'game_list');$games=$this->extractList($response,['data.games','data','games','result.games','result']);}
                foreach($games as $game){if(!is_array($game)){$skipped++;continue;}$external=trim((string)($game['game_code']??$game['code']??$game['id']??''));$gameName=trim((string)($game['game_name']??$game['name']??''));if($external===''||$gameName===''){$skipped++;continue;}$type=trim((string)($game['game_type']??$game['type']??'slot'));$image=trim((string)($game['image_url']??$game['image']??$game['banner']??$game['icon']??''));if($image!==''&&(!$this->validHttps($image)))$image='';$gameUpsert->execute(['provider'=>$providerId,'external'=>mb_substr($external,0,190),'name'=>mb_substr($gameName,0,190),'category'=>$this->category($type,$gameName),'image'=>$image!==''?mb_substr($image,0,500):null,'enabled'=>$this->enabled($game['status']??$game['enabled']??1),'source_type'=>mb_substr($type?:'slot',0,60),'synced'=>$now]);$gameCount++;}
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['providers_synced'=>$providerCount,'games_synced'=>$gameCount,'games_skipped'=>$skipped,'synced_at'=>$now];
    }
    private function multiSourceIndexReady(): bool { try { $rows=Database::connection()->query("SHOW INDEX FROM casino_games WHERE Key_name='uq_casino_game_external_source'")->fetchAll(); if(!$rows)return false; $cols=array_map(static fn($r)=>strtolower((string)($r['Column_name']??'')),$rows); return in_array('provider_id',$cols,true)&&in_array('external_id',$cols,true)&&in_array('api_source',$cols,true); } catch(\Throwable){ return false; } }
    private function postJson(string $url,array $payload): array { if(!function_exists('curl_init'))throw new RuntimeException('Extensão cURL do PHP não está habilitada.');$ch=curl_init(rtrim($url,'/'));$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','User-Agent: MZ90-Games2API/1.0'],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$raw=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$ct=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);if($raw===false)throw new RuntimeException('Falha de rede: '.$err);$d=json_decode((string)$raw,true,512,JSON_BIGINT_AS_STRING);if($status<200||$status>=300)throw new RuntimeException('HTTP '.$status.' no catálogo Games2API.');if(!is_array($d))throw new RuntimeException('Resposta inválida do catálogo Games2API ('.($ct?:'sem Content-Type').').');return $d; }
    private function assertSuccess(array $p,string $op): void {if(!array_key_exists('status',$p))return;$s=$p['status'];$ok=$s===true||$s===1||$s==='1'||strtolower((string)$s)==='success';if(!$ok)throw new DomainException('Games2API '.$op.': '.trim((string)($p['msg']??$p['message']??'falhou')));}
    private function extractList(array $p,array $paths): array {foreach($paths as $path){$v=$p;foreach(explode('.',$path) as $part){if(!is_array($v)||!array_key_exists($part,$v)){$v=null;break;}$v=$v[$part];}if(is_array($v)&&array_is_list($v))return $v;}return array_is_list($p)?$p:[];}
    private function inlineGames(array $provider): array {foreach(['games','game_list','items'] as $k)if(isset($provider[$k])&&is_array($provider[$k])&&array_is_list($provider[$k]))return $provider[$k];return [];}
    private function providerCode(string $v): string {$v=strtolower(trim($v));$v=preg_replace('/[^a-z0-9_-]+/','_',$v)??'';return trim(substr($v,0,60),'_-');}
    private function enabled(mixed $v): int {if(is_bool($v))return $v?1:0;if(is_numeric($v))return (int)$v===1?1:0;return in_array(strtolower(trim((string)$v)),['1','true','active','enabled','online','on'],true)?1:0;}
    private function validHttps(string $url): bool{return filter_var($url,FILTER_VALIDATE_URL)!==false&&strtolower((string)parse_url($url,PHP_URL_SCHEME))==='https';}
    private function category(string $type,string $name): string {$v=strtolower($type.' '.$name);if(str_contains($v,'roulette')||str_contains($v,'baccarat')||str_contains($v,'blackjack')||str_contains($v,'table'))return 'TABLE';if(str_contains($v,'live')||str_contains($v,'sport'))return 'LIVE';if(str_contains($v,'fish'))return 'OTHER';return 'SLOTS';}
}
