<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

/** Giros originados de depósitos pagos/indicados registrados, com prêmio calculado no servidor. */
final class WelcomeRouletteService
{
    private function campaigns(PDO $db,int $campaignId=0,bool $lock=false):array
    {
        $sql="SELECT c.*,s.activated_at FROM promotion_configurations c JOIN roulette_campaign_state s ON s.campaign_id=c.id WHERE c.type='roulette' AND c.enabled=1";
        if($campaignId>0)$sql.=' AND c.id=?';
        $sql.=' ORDER BY c.id'.($lock?' FOR UPDATE':'');
        $stmt=$db->prepare($sql);$stmt->execute($campaignId>0?[$campaignId]:[]);
        return $stmt->fetchAll();
    }
    private function lockUser(PDO $db,string $userId):void
    {
        $stmt=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$stmt->execute([$userId]);
        if($stmt->fetchColumn()!=='ACTIVE')throw new DomainException('Conta indisponível.');
    }
    private function accrue(PDO $db,string $userId,array $campaign):void
    {
        $cfg=json_decode((string)$campaign['config'],true)?:[];
        $id=(int)$campaign['id'];$start=(string)$campaign['activated_at'];
        $perDeposit=(int)($cfg['spins_per_deposit']??0);
        $minimum=(int)($cfg['deposit_min_cents']??0);
        if($perDeposit>0 && $minimum>0){
            // Created_at garante que depósitos iniciados antes da ativação não qualificam.
            $stmt=$db->prepare("INSERT IGNORE INTO roulette_spin_credits(campaign_id,user_id,source_type,source_id,total_spins) SELECT ?,?,'DEPOSIT',pt.id,? FROM payment_transactions pt WHERE pt.user_id=? AND pt.kind='DEPOSIT' AND pt.status='PAID' AND pt.amount_minor>=? AND pt.created_at>=? AND pt.updated_at>=?");
            $stmt->execute([$id,$userId,$perDeposit,$userId,$minimum,$start,$start]);
        }
        $perReferral=(int)($cfg['spins_per_referral']??0);
        if($perReferral>0){
            $stmt=$db->prepare("INSERT IGNORE INTO roulette_spin_credits(campaign_id,user_id,source_type,source_id,total_spins) SELECT ?,?,'REFERRAL',pr.referred_user_id,? FROM player_referrals pr JOIN users u ON u.id=pr.referred_user_id AND u.status='ACTIVE' WHERE pr.referrer_user_id=? AND pr.created_at>=?");
            $stmt->execute([$id,$userId,$perReferral,$userId,$start]);
        }
    }
    private function prizeValues(array $cfg): array
    {
        $min=(int)($cfg['reward_min_cents']??0);
        $max=(int)($cfg['reward_max_cents']??0);
        if($min<1 || $max<$min)throw new DomainException('Prêmios não configurados corretamente.');
        if($min===$max)return [$min,$min,$min,$min];
        $values=[];
        for($i=0;$i<4;$i++)$values[]=(int)round($min+(($max-$min)*$i/3));
        return $values;
    }
    private function wheelSegments(array $cfg): array
    {
        $prizes=$this->prizeValues($cfg);
        return [
            ['kind'=>'win','amount_minor'=>$prizes[0],'label'=>$this->moneyLabel($prizes[0])],
            ['kind'=>'lose','amount_minor'=>0,'label'=>'Não ganhou'],
            ['kind'=>'win','amount_minor'=>$prizes[1],'label'=>$this->moneyLabel($prizes[1])],
            ['kind'=>'lose','amount_minor'=>0,'label'=>'Não ganhou'],
            ['kind'=>'win','amount_minor'=>$prizes[2],'label'=>$this->moneyLabel($prizes[2])],
            ['kind'=>'lose','amount_minor'=>0,'label'=>'Não ganhou'],
            ['kind'=>'win','amount_minor'=>$prizes[3],'label'=>$this->moneyLabel($prizes[3])],
            ['kind'=>'lose','amount_minor'=>0,'label'=>'Não ganhou'],
        ];
    }
    private function moneyLabel(int $cents): string
    {
        return 'R$ '.number_format($cents/100,2,',','.');
    }
    private function spinResult(array $cfg): array
    {
        $segments=$this->wheelSegments($cfg);
        $chance=(float)($cfg['win_chance_percent']??50);
        if($chance<=0 || $chance>100)throw new DomainException('Chance do Giro da Sorte inválida.');
        $wins=[];$losses=[];
        foreach($segments as $i=>$segment){
            if($segment['kind']==='win')$wins[]=$i; else $losses[]=$i;
        }
        $won=((random_int(1,10000)/100) <= $chance);
        $selectedPool=$won?$wins:$losses;
        $selectedIndex=$selectedPool[array_rand($selectedPool)];
        return [
            'segments'=>$segments,
            'selected_index'=>$selectedIndex,
            'selected'=>$segments[$selectedIndex],
            'won'=>$won,
            'delay_ms'=>4600,
            'win_chance_percent'=>$chance,
        ];
    }
    public function status(string $userId):array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);
            $campaigns=[];
            foreach($this->campaigns($db) as $campaign){
                $this->accrue($db,$userId,$campaign);
                $stmt=$db->prepare('SELECT COALESCE(SUM(total_spins),0) total,COALESCE(SUM(used_spins),0) used FROM roulette_spin_credits WHERE user_id=? AND campaign_id=?');
                $stmt->execute([$userId,$campaign['id']]);$count=$stmt->fetch();
                $config=json_decode((string)$campaign['config'],true)?:[];
                $config['segments']=$this->wheelSegments($config);
                $campaigns[]=['id'=>(int)$campaign['id'],'title'=>$campaign['title'],'total_spins'=>(int)$count['total'],'used_spins'=>(int)$count['used'],'available_spins'=>(int)$count['total']-(int)$count['used'],'config'=>$config];
            }
            $history=$db->prepare('SELECT s.id,s.campaign_id,s.prize_minor,s.created_at,r.status,r.wager_required_minor FROM roulette_spins s JOIN promotion_redemptions r ON r.id=s.redemption_id WHERE s.user_id=? ORDER BY s.id DESC LIMIT 30');
            $history->execute([$userId]);
            return ['campaigns'=>$campaigns,'history'=>$history->fetchAll()];
        });
    }
    public function spin(string $userId,int $campaignId):array
    {
        if($campaignId<1)throw new DomainException('Campanha inválida.');
        return Database::transaction(function(PDO $db)use($userId,$campaignId):array{
            $this->lockUser($db,$userId);
            $campaigns=$this->campaigns($db,$campaignId,true);
            if(!$campaigns)throw new DomainException('Giro da Sorte desativado ou não configurado.');
            $campaign=$campaigns[0];$cfg=json_decode((string)$campaign['config'],true)?:[];
            if((int)($cfg['reward_min_cents']??0)<1||(int)($cfg['reward_max_cents']??0)<(int)$cfg['reward_min_cents'])throw new DomainException('Prêmios não configurados corretamente.');
            $this->accrue($db,$userId,$campaign);
            $credit=$db->prepare('SELECT id,total_spins,used_spins FROM roulette_spin_credits WHERE user_id=? AND campaign_id=? AND used_spins<total_spins ORDER BY id LIMIT 1 FOR UPDATE');
            $credit->execute([$userId,$campaignId]);$source=$credit->fetch();
            if(!$source)throw new DomainException('Você não possui rodadas disponíveis.');
            $db->prepare('UPDATE roulette_spin_credits SET used_spins=used_spins+1 WHERE id=? AND used_spins<total_spins')->execute([$source['id']]);
            $result=$this->spinResult($cfg);
            $awardCfg=$cfg;
            $awardCfg['forced_reward_cents']=(int)$result['selected']['amount_minor'];
            $award=(new PromotionRedemptionService())->creditRoulette($db,$userId,$campaign,$awardCfg);
            $db->prepare('INSERT INTO roulette_spins(credit_id,user_id,campaign_id,redemption_id,prize_minor) VALUES(?,?,?,?,?)')->execute([$source['id'],$userId,$campaignId,$award['id'],$award['amount_minor']]);
            $remaining=$db->prepare('SELECT COALESCE(SUM(total_spins-used_spins),0) FROM roulette_spin_credits WHERE user_id=? AND campaign_id=?');
            $remaining->execute([$userId,$campaignId]);
            return [
                'award'=>$award,
                'campaign_id'=>$campaignId,
                'remaining_spins'=>(int)$remaining->fetchColumn(),
                'display'=>[
                    'segments'=>$result['segments'],
                    'selected_index'=>$result['selected_index'],
                    'win_chance_percent'=>$result['win_chance_percent'],
                    'delay_ms'=>$result['delay_ms'],
                    'label'=>$result['selected']['label'],
                    'won'=>$result['won'],
                ],
            ];
        });
    }
    public function report():array
    {
        $db=Database::connection();
        $stmt=$db->query("SELECT s.id,s.created_at,u.username,c.title,s.prize_minor,r.status,r.wager_required_minor FROM roulette_spins s JOIN users u ON u.id=s.user_id JOIN promotion_configurations c ON c.id=s.campaign_id JOIN promotion_redemptions r ON r.id=s.redemption_id ORDER BY s.id DESC LIMIT 100");
        return ['spins'=>$stmt->fetchAll()];
    }
}
