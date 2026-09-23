<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;

final class LotteryService
{
    private const WORD='HAPPY';
    private const REQUIRED=['H'=>1,'A'=>1,'P'=>2,'Y'=>1];

    private function campaign(PDO $db,bool $lock=false): ?array
    {
        $sql="SELECT * FROM promotion_configurations WHERE type='lottery' AND enabled=1 ORDER BY id DESC LIMIT 1".($lock?' FOR UPDATE':'');
        $row=$db->query($sql)->fetch();
        return $row?:null;
    }
    private function now(): DateTimeImmutable { return new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')); }
    private function difficultyLabel(float $chance): string
    {
        return match (true) {
            $chance >= 72 => 'Muito fácil',
            $chance >= 58 => 'Fácil',
            $chance >= 43 => 'Normal',
            $chance >= 28 => 'Difícil',
            default => 'Muito difícil',
        };
    }
    private function lockUser(PDO $db,string $userId):void
    {
        $stmt=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$stmt->execute([$userId]);
        if($stmt->fetchColumn()!=='ACTIVE')throw new DomainException('Conta indisponível.');
    }
    private function state(PDO $db,string $userId,int $campaignId,bool $lock=false):array
    {
        $db->prepare("INSERT IGNORE INTO lottery_states(user_id,campaign_id,collection_json) VALUES(?,?,JSON_OBJECT('H',0,'A',0,'P',0,'Y',0))")->execute([$userId,$campaignId]);
        $sql='SELECT * FROM lottery_states WHERE user_id=? AND campaign_id=?'.($lock?' FOR UPDATE':'');
        $stmt=$db->prepare($sql);$stmt->execute([$userId,$campaignId]);
        $row=$stmt->fetch();
        if(!$row)throw new DomainException('Não foi possível iniciar o Sorteio.');
        return $row;
    }
    private function collection(array $state):array
    {
        $raw=json_decode((string)($state['collection_json']??'{}'),true)?:[];
        return ['H'=>(int)($raw['H']??0),'A'=>(int)($raw['A']??0),'P'=>(int)($raw['P']??0),'Y'=>(int)($raw['Y']??0)];
    }
    private function complete(array $collection):bool
    {
        foreach(self::REQUIRED as $letter=>$required)if(($collection[$letter]??0)<$required)return false;
        return true;
    }
    private function slots(array $collection):array
    {
        return [
            ['letter'=>'H','have'=>min(1,$collection['H']),'need'=>1,'count'=>(int)$collection['H']],
            ['letter'=>'A','have'=>min(1,$collection['A']),'need'=>1,'count'=>(int)$collection['A']],
            ['letter'=>'P','have'=>min(1,$collection['P']),'need'=>1,'count'=>(int)$collection['P']],
            ['letter'=>'P','have'=>min(2,$collection['P']),'need'=>2,'count'=>(int)$collection['P']],
            ['letter'=>'Y','have'=>min(1,$collection['Y']),'need'=>1,'count'=>(int)$collection['Y']],
        ];
    }
    private function missingUnits(array $collection): array
    {
        $missing=[];
        foreach(self::REQUIRED as $letter=>$required){
            for($i=(int)($collection[$letter]??0);$i<$required;$i++)$missing[]=$letter;
        }
        return $missing;
    }
    private function selectedIndex(string $letter): int
    {
        return match($letter){
            'H'=>0,
            'A'=>1,
            'P'=>random_int(0,1)===0?2:3,
            'Y'=>4,
            default=>5,
        };
    }
    public function status(string $userId):array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);$campaign=$this->campaign($db,true);
            if(!$campaign)return ['active'=>false];
            $cfg=json_decode((string)$campaign['config'],true)?:[];
            $publicCfg=$cfg;unset($publicCfg['hit_chance_percent']);
            $state=$this->state($db,$userId,(int)$campaign['id'],true);$collection=$this->collection($state);
            $today=$this->now()->format('Y-m-d');
            $used=$db->prepare('SELECT COUNT(*) FROM lottery_spins WHERE user_id=? AND campaign_id=? AND day_key=?');$used->execute([$userId,$campaign['id'],$today]);
            $free=max(0,(int)($cfg['spins_per_day']??0)-(int)$used->fetchColumn());
            $history=$db->prepare('SELECT result_letter,created_at FROM lottery_spins WHERE user_id=? AND campaign_id=? ORDER BY id DESC LIMIT 20');$history->execute([$userId,$campaign['id']]);
            return ['active'=>true,'campaign'=>['id'=>(int)$campaign['id'],'title'=>$campaign['title'],'config'=>$publicCfg],'collection'=>$collection,'slots'=>$this->slots($collection),'complete'=>$this->complete($collection),'free_spins'=>$free,'completed_collections'=>(int)$state['completed_collections'],'history'=>$history->fetchAll(),'word'=>self::WORD];
        });
    }
    public function spin(string $userId):array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);$campaign=$this->campaign($db,true);if(!$campaign)throw new DomainException('Sorteio indisponível.');
            $cfg=json_decode((string)$campaign['config'],true)?:[];
            $spinsPerDay=(int)($cfg['spins_per_day']??0);if($spinsPerDay<1)throw new DomainException('Rodadas do Sorteio não configuradas.');
            $state=$this->state($db,$userId,(int)$campaign['id'],true);$collection=$this->collection($state);
            if($this->complete($collection))throw new DomainException('Sua coleção está completa. Resgate o prêmio antes de girar novamente.');
            $today=$this->now()->format('Y-m-d');
            $used=$db->prepare('SELECT COUNT(*) FROM lottery_spins WHERE user_id=? AND campaign_id=? AND day_key=?');$used->execute([$userId,$campaign['id'],$today]);
            $usedCount=(int)$used->fetchColumn();if($usedCount >= $spinsPerDay)throw new DomainException('Suas rodadas gratuitas de hoje acabaram.');
            // Letras podem repetir e continuam acumuladas na coleção.
            // Quando falta apenas a última unidade para completar HAPPY, essa letra passa a ser extremamente rara.
            $chance=max(1.0,min(95.0,(float)($cfg['hit_chance_percent']??50)));
            $letterPool=['H','A','P','P','Y'];
            $missing=$this->missingUnits($collection);
            $letter='N';
            if(count($missing)===1){
                $finalLetter=$missing[0];
                $roll=random_int(1,10000);
                $finalThreshold=100; // 1,00% por giro para a última letra/unidade necessária.
                $hitThreshold=(int)round($chance*100);
                if($roll<=$finalThreshold){
                    $letter=$finalLetter;
                }elseif($roll<=$hitThreshold){
                    $duplicates=array_values(array_filter($letterPool,static fn(string $candidate):bool=>$candidate!==$finalLetter));
                    if($duplicates!==[])$letter=$duplicates[random_int(0,count($duplicates)-1)];
                }
            }elseif(random_int(1,10000)<=((int)round($chance*100))){
                $letter=$letterPool[random_int(0,count($letterPool)-1)];
            }
            $selectedIndex=$this->selectedIndex($letter);
            if($letter!=='N'){
                $collection[$letter]=($collection[$letter]??0)+1;
                $db->prepare('UPDATE lottery_states SET collection_json=? WHERE user_id=? AND campaign_id=?')->execute([json_encode($collection,JSON_UNESCAPED_UNICODE),$userId,$campaign['id']]);
            }
            $db->prepare('INSERT INTO lottery_spins(user_id,campaign_id,day_key,result_letter) VALUES(?,?,?,?)')->execute([$userId,$campaign['id'],$today,$letter]);
            return ['letter'=>$letter,'reward_type'=>$letter==='N'?'NO_WIN':'LETTER','selected_index'=>$selectedIndex,'collection'=>$collection,'slots'=>$this->slots($collection),'complete'=>$this->complete($collection),'free_spins'=>max(0,$spinsPerDay-$usedCount-1)];
        });
    }
    public function claim(string $userId):array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);$campaign=$this->campaign($db,true);if(!$campaign)throw new DomainException('Sorteio indisponível.');
            $cfg=json_decode((string)$campaign['config'],true)?:[];
            $bonus=(int)($cfg['collection_bonus_cents']??0);if($bonus<1)throw new DomainException('Prêmio da coleção não configurado.');
            $state=$this->state($db,$userId,(int)$campaign['id'],true);$collection=$this->collection($state);
            if(!$this->complete($collection))throw new DomainException('Complete a palavra HAPPY antes de resgatar.');
            $number=(int)$state['completed_collections']+1;
            $awardCfg=['forced_reward_cents'=>$bonus,'reward_min_cents'=>$bonus,'reward_max_cents'=>$bonus,'rollover_x'=>(float)($cfg['rollover_x']??0)];
            $award=(new PromotionRedemptionService())->creditLottery($db,$userId,$campaign,$awardCfg,$bonus);
            $db->prepare('INSERT INTO lottery_claims(user_id,campaign_id,collection_number,redemption_id) VALUES(?,?,?,?)')->execute([$userId,$campaign['id'],$number,$award['id']]);
            $empty=['H'=>0,'A'=>0,'P'=>0,'Y'=>0];
            $db->prepare('UPDATE lottery_states SET collection_json=?,completed_collections=? WHERE user_id=? AND campaign_id=?')->execute([json_encode($empty),$number,$userId,$campaign['id']]);
            return ['award'=>$award,'completed_collections'=>$number,'slots'=>$this->slots($empty),'collection'=>$empty];
        });
    }
    public function report():array
    {
        $db=Database::connection();
        $stmt=$db->query("SELECT s.user_id,u.username,c.title,s.collection_json,s.completed_collections,s.updated_at FROM lottery_states s JOIN users u ON u.id=s.user_id JOIN promotion_configurations c ON c.id=s.campaign_id ORDER BY s.updated_at DESC LIMIT 100");
        return ['players'=>$stmt->fetchAll()];
    }
}
