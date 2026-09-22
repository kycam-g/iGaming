<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;

final class CashWheelService
{
    private function campaign(PDO $db,bool $lock=false): ?array
    {
        $sql="SELECT * FROM promotion_configurations WHERE type='cashwheel' AND enabled=1 ORDER BY id DESC LIMIT 1".($lock?' FOR UPDATE':'');
        $row=$db->query($sql)->fetch();
        return $row?:null;
    }
    private function lockUser(PDO $db,string $userId): void
    {
        $stmt=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$stmt->execute([$userId]);
        if($stmt->fetchColumn()!=='ACTIVE')throw new DomainException('Conta indisponível.');
    }
    private function now(): DateTimeImmutable { return new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')); }
    private function session(PDO $db,string $userId,array $campaign,bool $lock=false): array
    {
        $now=$this->now();
        $stmt=$db->prepare("SELECT * FROM cashwheel_sessions WHERE user_id=? AND campaign_id=? AND status='ACTIVE' ORDER BY id DESC LIMIT 1".($lock?' FOR UPDATE':''));
        $stmt->execute([$userId,$campaign['id']]);$row=$stmt->fetch();
        if($row && new DateTimeImmutable((string)$row['expires_at'],new DateTimeZone('America/Sao_Paulo')) <= $now){
            $db->prepare("UPDATE cashwheel_sessions SET status='EXPIRED' WHERE id=? AND status='ACTIVE'")->execute([$row['id']]);$row=false;
        }
        if($row)return $row;
        $cfg=json_decode((string)$campaign['config'],true)?:[];
        $days=max(1,min(30,(int)($cfg['duration_days']??3)));
        $expires=$now->modify('+'.$days.' days');
        $stmt=$db->prepare("INSERT INTO cashwheel_sessions(user_id,campaign_id,started_at,expires_at,status) VALUES(?,?,NOW(6),?,'ACTIVE')");
        $stmt->execute([$userId,$campaign['id'],$expires->format('Y-m-d H:i:s.u')]);
        $id=(int)$db->lastInsertId();
        $stmt=$db->prepare('SELECT * FROM cashwheel_sessions WHERE id=?');$stmt->execute([$id]);return $stmt->fetch();
    }
    private function accrueReferrals(PDO $db,array $session,array $cfg): void
    {
        $bonus=(int)($cfg['referral_bonus_cents']??0);if($bonus<1)return;
        $stmt=$db->prepare("SELECT pr.referred_user_id FROM player_referrals pr JOIN users u ON u.id=pr.referred_user_id AND u.status='ACTIVE' WHERE pr.referrer_user_id=? AND pr.created_at>=?");
        $stmt->execute([$session['user_id'],$session['started_at']]);
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $ref){
            $ins=$db->prepare('INSERT IGNORE INTO cashwheel_referral_credits(session_id,user_id,referred_user_id,amount_minor) VALUES(?,?,?,?)');
            $ins->execute([$session['id'],$session['user_id'],$ref,$bonus]);
            if($ins->rowCount()){$target=(int)($cfg['target_cents']??0);$db->prepare('UPDATE cashwheel_sessions SET progress_minor=LEAST(progress_minor+?,?) WHERE id=?')->execute([$bonus,$target,$session['id']]);}
        }
    }
    public function status(string $userId): array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);$campaign=$this->campaign($db,true);
            if(!$campaign)return ['active'=>false];
            $cfg=json_decode((string)$campaign['config'],true)?:[];
            $session=$this->session($db,$userId,$campaign,true);$this->accrueReferrals($db,$session,$cfg);
            $stmt=$db->prepare('SELECT * FROM cashwheel_sessions WHERE id=?');$stmt->execute([$session['id']]);$session=$stmt->fetch();
            $today=$this->now()->format('Y-m-d');
            $used=$db->prepare('SELECT COUNT(*) FROM cashwheel_spins WHERE session_id=? AND day_key=?');$used->execute([$session['id'],$today]);
            $free=max(0,(int)($cfg['free_spins_per_day']??0)-(int)$used->fetchColumn());
            $history=$db->prepare('SELECT reward_type,prize_minor,created_at FROM cashwheel_spins WHERE session_id=? ORDER BY id DESC LIMIT 20');$history->execute([$session['id']]);
            return ['active'=>true,'campaign'=>['id'=>(int)$campaign['id'],'title'=>$campaign['title'],'config'=>$cfg],'session'=>$session,'free_spins'=>$free,'history'=>$history->fetchAll()];
        });
    }
    public function spin(string $userId): array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);$campaign=$this->campaign($db,true);if(!$campaign)throw new DomainException('Roleta de Saque indisponível.');
            $cfg=json_decode((string)$campaign['config'],true)?:[];$session=$this->session($db,$userId,$campaign,true);$this->accrueReferrals($db,$session,$cfg);
            $stmt=$db->prepare('SELECT * FROM cashwheel_sessions WHERE id=? FOR UPDATE');$stmt->execute([$session['id']]);$session=$stmt->fetch();
            $target=(int)($cfg['target_cents']??0);$min=(int)($cfg['spin_min_cents']??0);$max=(int)($cfg['spin_max_cents']??0);
            if($target<1||$min<1||$max<$min)throw new DomainException('Configure corretamente a meta e os avanços da Roleta de Saque.');
            if((int)$session['progress_minor'] >= $target)throw new DomainException('A meta já foi atingida. Solicite o resgate.');
            $today=$this->now()->format('Y-m-d');
            $used=$db->prepare('SELECT COUNT(*) FROM cashwheel_spins WHERE session_id=? AND day_key=?');$used->execute([$session['id'],$today]);
            if((int)$used->fetchColumn() >= (int)($cfg['free_spins_per_day']??0))throw new DomainException('Suas rodadas gratuitas de hoje acabaram.');

            $totalSpinsStmt=$db->prepare('SELECT COUNT(*) FROM cashwheel_spins WHERE session_id=?');$totalSpinsStmt->execute([$session['id']]);
            $spinNumber=(int)$totalSpinsStmt->fetchColumn()+1;
            $roll=random_int(1,10000)/100;
            $cashChance=(float)($cfg['cash_bonus_chance_percent']??0);
            $noWinChance=(float)($cfg['no_win_chance_percent']??20);

            if($cashChance>0 && $roll <= $cashChance){
                $cashMin=(int)($cfg['cash_bonus_min_cents']??0);$cashMax=(int)($cfg['cash_bonus_max_cents']??0);
                if($cashMin<1||$cashMax<$cashMin)throw new DomainException('Configure corretamente o bônus em moeda da Roleta de Saque.');
                $prize=$cashMin===$cashMax?$cashMin:random_int($cashMin,$cashMax);
                $awardCfg=['forced_reward_cents'=>$prize,'rollover_x'=>(float)($cfg['cash_bonus_rollover_x']??0),'reward_min_cents'=>$prize,'reward_max_cents'=>$prize];
                $award=(new PromotionRedemptionService())->creditCashwheel($db,$userId,$campaign,$awardCfg,$prize);
                $db->prepare("INSERT INTO cashwheel_spins(session_id,user_id,campaign_id,day_key,prize_minor,reward_type,redemption_id) VALUES(?,?,?,?,?,'CASH_BONUS',?)")->execute([$session['id'],$userId,$campaign['id'],$today,$prize,$award['id']]);
                $status=$this->statusSnapshot($db,$userId,$campaign,$session['id'],$cfg);
                return ['reward_type'=>'CASH_BONUS','prize_minor'=>$prize,'award'=>$award,'selected_index'=>1,'spin_number'=>$spinNumber]+$status;
            }

            if($noWinChance>0 && $roll <= ($cashChance+$noWinChance)){
                $db->prepare("INSERT INTO cashwheel_spins(session_id,user_id,campaign_id,day_key,prize_minor,reward_type) VALUES(?,?,?,?,0,'NO_WIN')")->execute([$session['id'],$userId,$campaign['id'],$today]);
                $status=$this->statusSnapshot($db,$userId,$campaign,$session['id'],$cfg);
                return ['reward_type'=>'NO_WIN','prize_minor'=>0,'selected_index'=>0,'spin_number'=>$spinNumber]+$status;
            }

            $current=(int)$session['progress_minor'];$remaining=max(0,$target-$current);
            if($spinNumber===1){
                // O primeiro avanço é propositalmente grande: aproxima o jogador da meta, mas nunca ultrapassa 90%.
                $firstMin=max(1,min(90,(float)($cfg['first_spin_min_percent']??60)));
                $firstMax=max($firstMin,min(90,(float)($cfg['first_spin_max_percent']??90)));
                $low=max(1,(int)floor($target*($firstMin/100)));
                $high=min($remaining,max($low,(int)floor($target*($firstMax/100))));
            }else{
                $laterMax=max(0.01,min(25,(float)($cfg['later_spin_max_percent']??8)));
                $low=min($remaining,$min);
                $high=min($remaining,$max,max($low,(int)floor($target*($laterMax/100))));
            }
            if($remaining<1){throw new DomainException('A meta já foi atingida. Solicite o resgate.');}
            $low=max(1,min($low,$remaining));$high=max($low,min($high,$remaining));
            $prize=$low===$high?$low:random_int($low,$high);
            $db->prepare("INSERT INTO cashwheel_spins(session_id,user_id,campaign_id,day_key,prize_minor,reward_type) VALUES(?,?,?,?,?,'PROGRESS')")->execute([$session['id'],$userId,$campaign['id'],$today,$prize]);
            $db->prepare('UPDATE cashwheel_sessions SET progress_minor=LEAST(progress_minor+?,?) WHERE id=?')->execute([$prize,$target,$session['id']]);
            $status=$this->statusSnapshot($db,$userId,$campaign,$session['id'],$cfg);
            $progressIndex=2;
            if($high>$low){$progressIndex=2+(int)round((($prize-$low)/($high-$low))*5);}
            $progressIndex=max(2,min(7,$progressIndex));
            return ['reward_type'=>'PROGRESS','prize_minor'=>$prize,'selected_index'=>$progressIndex,'spin_number'=>$spinNumber]+$status;
        });
    }
    public function claim(string $userId): array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);$campaign=$this->campaign($db,true);if(!$campaign)throw new DomainException('Roleta de Saque indisponível.');
            $cfg=json_decode((string)$campaign['config'],true)?:[];$session=$this->session($db,$userId,$campaign,true);
            $stmt=$db->prepare('SELECT * FROM cashwheel_sessions WHERE id=? FOR UPDATE');$stmt->execute([$session['id']]);$session=$stmt->fetch();
            $target=(int)($cfg['target_cents']??0);if($target<1||(int)$session['progress_minor']<$target)throw new DomainException('A meta de saque ainda não foi atingida.');
            if($session['claimed_at'])throw new DomainException('Este prêmio já foi resgatado.');
            $awardCfg=['forced_reward_cents'=>$target,'rollover_x'=>(float)($cfg['rollover_x']??0),'reward_min_cents'=>$target,'reward_max_cents'=>$target];
            $award=(new PromotionRedemptionService())->creditCashwheel($db,$userId,$campaign,$awardCfg,$target);
            $db->prepare("UPDATE cashwheel_sessions SET status='CLAIMED',claimed_at=NOW(6),redemption_id=? WHERE id=?")->execute([$award['id'],$session['id']]);
            return ['award'=>$award];
        });
    }
    private function statusSnapshot(PDO $db,string $userId,array $campaign,int $sessionId,array $cfg): array
    {
        $stmt=$db->prepare('SELECT * FROM cashwheel_sessions WHERE id=?');$stmt->execute([$sessionId]);$session=$stmt->fetch();
        $today=$this->now()->format('Y-m-d');$used=$db->prepare('SELECT COUNT(*) FROM cashwheel_spins WHERE session_id=? AND day_key=?');$used->execute([$sessionId,$today]);
        return ['session'=>$session,'free_spins'=>max(0,(int)($cfg['free_spins_per_day']??0)-(int)$used->fetchColumn()),'target_minor'=>(int)($cfg['target_cents']??0)];
    }
    public function report(): array
    {
        $db=Database::connection();$stmt=$db->query("SELECT s.id,u.username,c.title,s.progress_minor,s.status,s.started_at,s.expires_at,s.claimed_at FROM cashwheel_sessions s JOIN users u ON u.id=s.user_id JOIN promotion_configurations c ON c.id=s.campaign_id ORDER BY s.id DESC LIMIT 100");
        return ['items'=>$stmt->fetchAll()];
    }
}
