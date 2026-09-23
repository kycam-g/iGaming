<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;
use DateTimeImmutable;
use DateTimeZone;

/** Operações financeiras atômicas, sem confiar em cálculos enviados pelo navegador. */
final class PromotionRedemptionService
{
    public static function uuid(): string {
        $hex=bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20));
    }
    private static function window(): array {
        $tz=new DateTimeZone('America/Sao_Paulo');
        $now=new DateTimeImmutable('now',$tz);
        $start=$now->setTime(21,0);
        if ($now<$start) $start=$start->modify('-1 day');
        return [$start->format('Y-m-d'),$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$start->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
    }
    public function status(string $userId): array {
        $db=Database::connection();
        [$day,$from,$until]=self::window();
        $stmt=$db->prepare('SELECT r.id,r.promotion_type,r.campaign_id,r.day_key,r.sequence_day,r.amount_minor,r.rollover_x,r.wager_required_minor,r.wager_progress_minor,r.status,r.created_at,c.title FROM promotion_redemptions r JOIN promotion_configurations c ON c.id=r.campaign_id WHERE r.user_id=? ORDER BY r.created_at DESC LIMIT 40');
        $stmt->execute([$userId]);
        $history=$stmt->fetchAll();
        $stmt=$db->prepare('SELECT sequence_day,day_key FROM promotion_redemptions WHERE user_id=? AND promotion_type=\'checkin\' ORDER BY day_key DESC LIMIT 1');
        $stmt->execute([$userId]);$last=$stmt->fetch();
        $current=$last && $last['day_key']===$day;
        $yesterday=(new DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d');
        $next=$current?null:($last && $last['day_key']===$yesterday?(int)$last['sequence_day']+1:1);
        $stmt=$db->prepare('SELECT id,title,config FROM promotion_configurations WHERE type=\'checkin\' AND enabled=1');
        $stmt->execute();$days=[];
        foreach($stmt->fetchAll() as $row){$cfg=json_decode((string)$row['config'],true)?:[];$n=(int)($cfg['day']??0);if($n>0)$days[$n]=['id'=>(int)$row['id'],'title'=>$row['title'],'config'=>$cfg];}
        ksort($days);
        if($next!==null && $days && $next>max(array_keys($days)))$next=1;
        $requiredDeposit=0;$requiredBet=0;$depositProgress=0;$betProgress=0;$availableToday=false;$availabilityMessage='Sem check-in disponível no momento.';
        if($next!==null && isset($days[$next])){
            $cfg=$days[$next]['config']??[];
            $requiredDeposit=(int)($cfg['deposit_min_cents']??0);
            $requiredBet=(int)($cfg['bet_min_cents']??0);
            if($requiredDeposit>0){
                $stmt=$db->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM payment_transactions WHERE user_id=? AND kind='DEPOSIT' AND status='PAID' AND updated_at>=? AND updated_at<?");
                $stmt->execute([$userId,$from,$until]);
                $depositProgress=(int)$stmt->fetchColumn();
            }
            if($requiredBet>0){
                $stmt=$db->prepare("SELECT COALESCE(SUM(le.amount_minor),0) FROM ledger_entries le JOIN financial_transactions ft ON ft.id=le.transaction_id WHERE ft.user_id=? AND ft.type IN ('CASINO_BET','CASINO_WINBET') AND ft.status='COMPLETED' AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER' AND ft.created_at>=? AND ft.created_at<?");
                $stmt->execute([$userId,$from,$until]);
                $betProgress=(int)$stmt->fetchColumn();
            }
            $availableToday=!$current && $depositProgress>=$requiredDeposit && $betProgress>=$requiredBet;
            if($current)$availabilityMessage='Check-in de hoje já resgatado.';
            elseif($availableToday)$availabilityMessage='Recompensa diária disponível para resgate.';
            else $availabilityMessage='Requisitos pendentes para o check-in de hoje.';
        }
        return ['today'=>$day,'claimed_today'=>(bool)$current,'next_day'=>$next,'days'=>array_values($days),'history'=>$history,'timezone'=>'America/Sao_Paulo','cutoff_hour'=>'21:00','available_today'=>$availableToday,'required_deposit_minor'=>$requiredDeposit,'required_bet_minor'=>$requiredBet,'deposit_progress_minor'=>$depositProgress,'bet_progress_minor'=>$betProgress,'availability_message'=>$availabilityMessage];
    }
    /** VIP: volume real do ledger de apostas PlayFiver, nunca valores fornecidos pelo cliente. */
    private static function vipVolume(PDO $db,string $userId): array {
        $stmt=$db->prepare("SELECT COALESCE(SUM(le.amount_minor),0) AS total, COALESCE(SUM(CASE WHEN ft.created_at >= ? THEN le.amount_minor ELSE 0 END),0) AS month_total FROM ledger_entries le JOIN financial_transactions ft ON ft.id=le.transaction_id WHERE ft.user_id=? AND ft.type IN ('CASINO_BET','CASINO_WINBET') AND ft.status='COMPLETED' AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER'");
        $first=(new DateTimeImmutable('first day of this month 00:00:00',new DateTimeZone('America/Sao_Paulo')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        // financial_transactions.created_at é DATETIME no fuso da sessão MySQL.
        $offset=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW())')->fetchColumn();
        $first=gmdate('Y-m-d H:i:s',strtotime($first)+$offset);
        $stmt->execute([$first,$userId]);$row=$stmt->fetch();
        return ['total_minor'=>(int)($row['total']??0),'month_minor'=>(int)($row['month_total']??0)];
    }
    private static function vipLevels(PDO $db): array {
        $rows=$db->query("SELECT id,title,config FROM promotion_configurations WHERE type='vip' AND enabled=1 ORDER BY id")->fetchAll();
        $levels=[];
        foreach($rows as $row){$config=json_decode((string)$row['config'],true)?:[];$level=(int)($config['level']??0);$goal=(int)($config['goal_cents']??0);
            if($level<1||$goal<1)continue;
            $levels[]=['id'=>(int)$row['id'],'title'=>(string)$row['title'],'level'=>$level,'goal_minor'=>$goal,'bonus_minor'=>(int)($config['bonus_cents']??0),'rollover_x'=>(float)($config['rollover_x']??0)];
        }
        usort($levels,static fn($a,$b)=>$a['goal_minor']<=>$b['goal_minor'] ?: $a['level']<=>$b['level']);
        return $levels;
    }
    public function vipStatus(string $userId): array {
        $db=Database::connection();$volume=self::vipVolume($db,$userId);$levels=self::vipLevels($db);
        $stmt=$db->prepare("SELECT campaign_id,status,amount_minor,created_at FROM promotion_redemptions WHERE user_id=? AND promotion_type='vip' ORDER BY created_at DESC");$stmt->execute([$userId]);$awards=[];
        foreach($stmt->fetchAll() as $award)$awards[(int)$award['campaign_id']]=['status'=>$award['status'],'amount_minor'=>(int)$award['amount_minor'],'created_at'=>$award['created_at']];
        $current=null;$next=null;
        foreach($levels as &$level){$level['reached']=$volume['total_minor'] >= $level['goal_minor'];$level['award']=$awards[$level['id']]??null;
            if($level['reached'])$current=$level;
            elseif($next===null)$next=$level;
        }unset($level);
        return ['volume'=>$volume,'levels'=>$levels,'current_level'=>$current['level']??0,'next_goal_minor'=>$next['goal_minor']??null];
    }
    public function claimVip(string $userId,int $campaignId): array {
        if($campaignId<1)throw new DomainException('Nível VIP inválido.');
        return Database::transaction(function(PDO $db)use($userId,$campaignId):array{
            $this->lockUser($db,$userId);
            $stmt=$db->prepare("SELECT * FROM promotion_configurations WHERE id=? AND type='vip' AND enabled=1 FOR UPDATE");$stmt->execute([$campaignId]);$row=$stmt->fetch();
            if(!$row)throw new DomainException('Nível VIP indisponível.');
            $cfg=json_decode((string)$row['config'],true)?:[];
            if((int)($cfg['goal_cents']??0)<1||(int)($cfg['bonus_cents']??0)<1)throw new DomainException('Recompensa deste nível não está configurada.');
            $stmt=$db->prepare("SELECT id FROM promotion_redemptions WHERE user_id=? AND campaign_id=? AND promotion_type='vip' LIMIT 1");$stmt->execute([$userId,$campaignId]);
            if($stmt->fetchColumn())throw new DomainException('Bônus deste nível VIP já resgatado.');
            $volume=self::vipVolume($db,$userId);
            if($volume['total_minor']<(int)$cfg['goal_cents'])throw new DomainException('Meta de apostas do nível VIP ainda não atingida.');
            return $this->award($db,$userId,$row,$cfg,'vip',null,null);
        });
    }

    /** Usado somente na transação que bloqueia o entitlement e o usuário. */
    public function creditVipRecurring(PDO $db,string $userId,array $row,array $cfg,string $type):array {
        if(!in_array($type,['vip_daily','vip_weekly','vip_monthly'],true))throw new DomainException('Tipo VIP inválido.');
        return $this->award($db,$userId,$row,$cfg,$type,null,null);
    }

    /** O consumo da rodada, o prêmio e o ledger pertencem à mesma transação. */
    public function creditRoulette(PDO $db,string $userId,array $campaign,array $config):array {
        if(($campaign['type']??'')!=='roulette')throw new DomainException('Campanha de roleta inválida.');
        return $this->award($db,$userId,$campaign,$config,'roulette',null,null);
    }


    /** Crédito da Roleta de Saque somente após atingir a meta interna da campanha. */
    public function creditCashwheel(PDO $db,string $userId,array $campaign,array $config,int $amountMinor):array {
        if(($campaign['type']??'')!=='cashwheel')throw new DomainException('Campanha de Roleta de Saque inválida.');
        $config['forced_reward_cents']=$amountMinor;
        return $this->award($db,$userId,$campaign,$config,'cashwheel',null,null);
    }

    /** Crédito do prêmio do Sorteio após completar a coleção HAPPY. */
    public function creditLottery(PDO $db,string $userId,array $campaign,array $config,int $amountMinor):array {
        if(($campaign['type']??'')!=='lottery')throw new DomainException('Campanha de Sorteio inválida.');
        $config['forced_reward_cents']=$amountMinor;
        return $this->award($db,$userId,$campaign,$config,'lottery',null,null);
    }

    /** Crédito do Envelope Vermelho após o entitlement diário ser reservado. */
    public function creditEnvelope(PDO $db,string $userId,array $campaign,array $config,int $amountMinor):array {
        if(($campaign['type']??'')!=='envelope')throw new DomainException('Campanha de envelope inválida.');
        $config['forced_reward_cents']=$amountMinor;
        return $this->award($db,$userId,$campaign,$config,'envelope',null,null);
    }

    /** Usado somente após travar usuário e entitlement diário em transação atômica. */
    public function creditRescue(PDO $db,string $userId,array $campaign,array $config):array {
        if(($campaign['type']??'')!=='rescue')throw new DomainException('Campanha de resgate inválida.');
        return $this->award($db,$userId,$campaign,$config,'rescue',null,null);
    }

    private function throttleCoupon(string $userId):void {
        Database::transaction(function(PDO $db)use($userId):void {
            $db->prepare('INSERT IGNORE INTO promotion_coupon_attempts(user_id,window_started_at,attempts) VALUES (?,NOW(6),0)')->execute([$userId]);
            $stmt=$db->prepare('SELECT attempts,window_started_at FROM promotion_coupon_attempts WHERE user_id=? FOR UPDATE');$stmt->execute([$userId]);$old=$stmt->fetch();
            $expired=$db->prepare('SELECT window_started_at < DATE_SUB(NOW(6),INTERVAL 1 HOUR) FROM promotion_coupon_attempts WHERE user_id=?');$expired->execute([$userId]);
            if((bool)$expired->fetchColumn()){$stmt=$db->prepare('UPDATE promotion_coupon_attempts SET window_started_at=NOW(6),attempts=1 WHERE user_id=?');$stmt->execute([$userId]);return;}
            if((int)$old['attempts']>=10)throw new DomainException('Limite de tentativas atingido. Aguarde uma hora.');
            $stmt=$db->prepare('UPDATE promotion_coupon_attempts SET attempts=attempts+1 WHERE user_id=?');$stmt->execute([$userId]);
        });
    }
    /** Marco único por campanha; só depósitos PAID de contas indicadas ativas qualificam. */
    public function claimChest(string $userId,int $campaignId):array {
        if($campaignId<1)throw new DomainException('Baú inválido.');
        return Database::transaction(function(PDO $db)use($userId,$campaignId):array {
            $this->lockUser($db,$userId);
            $stmt=$db->prepare("SELECT * FROM promotion_configurations WHERE id=? AND type='chests' AND enabled=1 FOR UPDATE");
            $stmt->execute([$campaignId]);$row=$stmt->fetch();
            if(!$row)throw new DomainException('Baú indisponível.');
            $cfg=json_decode((string)$row['config'],true)?:[];
            $required=(int)($cfg['referral_count']??0);$minimum=(int)($cfg['referred_deposit_min_cents']??0);
            if($required<1||$minimum<1||(int)($cfg['bonus_cents']??0)<1)throw new DomainException('Configure meta, depósito mínimo e bônus no Admin.');
            $old=$db->prepare("SELECT id FROM promotion_redemptions WHERE user_id=? AND campaign_id=? AND promotion_type='chests' LIMIT 1");$old->execute([$userId,$campaignId]);
            if($old->fetchColumn())throw new DomainException('Este baú já foi resgatado.');
            $stmt=$db->prepare("SELECT COUNT(*) FROM (SELECT pr.referred_user_id FROM player_referrals pr JOIN users u ON u.id=pr.referred_user_id AND u.status='ACTIVE' JOIN payment_transactions pt ON pt.user_id=pr.referred_user_id AND pt.kind='DEPOSIT' AND pt.status='PAID' AND pt.created_at>=pr.created_at WHERE pr.referrer_user_id=? GROUP BY pr.referred_user_id HAVING SUM(pt.amount_minor)>=?) qualified");
            $stmt->execute([$userId,$minimum]);
            if((int)$stmt->fetchColumn()<$required)throw new DomainException('Ainda não há indicados com depósito confirmado suficientes para este baú.');
            return $this->award($db,$userId,$row,$cfg,'chests',null,null);
        });
    }
    public function claimCoupon(string $userId,string $code): array {
        $code=strtoupper(trim($code));
        if(!preg_match('/^[A-Z0-9_-]{4,64}$/D',$code))throw new DomainException('Digite um código válido (4 a 64 letras, números, _ ou -).');
        $this->throttleCoupon($userId);
        return Database::transaction(function(PDO $db) use($userId,$code):array {
            $this->lockUser($db,$userId);
            $stmt=$db->prepare("SELECT * FROM promotion_configurations WHERE type='coupons' AND coupon_code=? FOR UPDATE");$stmt->execute([$code]);
            $row=$stmt->fetch();if(!$row || !(int)$row['enabled'])throw new DomainException('Cupom inválido ou indisponível.');
            $cfg=json_decode((string)$row['config'],true)?:[];
            $used=$db->prepare('SELECT COUNT(*) FROM promotion_redemptions WHERE campaign_id=?');$used->execute([$row['id']]);
            if((int)$used->fetchColumn()>=(int)($cfg['quantity']??0))throw new DomainException('Cupom esgotado.');
            $existing=$db->prepare('SELECT id FROM promotion_redemptions WHERE campaign_id=? AND user_id=?');$existing->execute([$row['id'],$userId]);
            if($existing->fetchColumn())throw new DomainException('Você já resgatou este cupom.');
            return $this->award($db,$userId,$row,$cfg,'coupons',null,null);
        });
    }
    public function claimCheckin(string $userId): array {
        return Database::transaction(function(PDO $db) use($userId):array {
            $this->lockUser($db,$userId);
            [$day,$from,$until]=self::window();
            // DATETIME usa o fuso da sessão MySQL; ajustar limites UTC ao mesmo fuso.
            $offset=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW())')->fetchColumn();
            $from=gmdate('Y-m-d H:i:s',strtotime($from)+$offset);
            $until=gmdate('Y-m-d H:i:s',strtotime($until)+$offset);
            $stmt=$db->prepare("SELECT sequence_day,day_key FROM promotion_redemptions WHERE user_id=? AND promotion_type='checkin' ORDER BY day_key DESC LIMIT 1");$stmt->execute([$userId]);$last=$stmt->fetch();
            if($last && $last['day_key']===$day)throw new DomainException('Check-in de hoje já foi resgatado.');
            $yesterday=(new DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d');
            $next=$last && $last['day_key']===$yesterday?(int)$last['sequence_day']+1:1;
            $stmt=$db->query("SELECT * FROM promotion_configurations WHERE type='checkin' AND enabled=1 ORDER BY id FOR UPDATE");$rows=$stmt->fetchAll();$byDay=[];
            foreach($rows as $row){$cfg=json_decode((string)$row['config'],true)?:[];$n=(int)($cfg['day']??0);if($n>0 && isset($byDay[$n]))throw new DomainException('Existem dias duplicados no Admin. Corrija antes de ativar o check-in.');if($n>0)$byDay[$n]=[$row,$cfg];}
            if(!$byDay)throw new DomainException('Check-in não está disponível.');
            if($next>max(array_keys($byDay)))$next=1;
            if(!isset($byDay[$next]))throw new DomainException('O próximo dia da sequência não está configurado.');
            [$row,$cfg]=$byDay[$next];
            $minDeposit=(int)($cfg['deposit_min_cents']??0);
            if($minDeposit>0){
                $stmt=$db->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM payment_transactions WHERE user_id=? AND kind='DEPOSIT' AND status='PAID' AND updated_at>=? AND updated_at<?");
                $stmt->execute([$userId,$from,$until]);
                if((int)$stmt->fetchColumn()<$minDeposit)throw new DomainException('Depósitos confirmados insuficientes neste dia de check-in.');
            }
            $minBet=(int)($cfg['bet_min_cents']??0);
            if($minBet>0){
                $stmt=$db->prepare("SELECT COALESCE(SUM(le.amount_minor),0) FROM ledger_entries le JOIN financial_transactions ft ON ft.id=le.transaction_id WHERE ft.user_id=? AND ft.type IN ('CASINO_BET','CASINO_WINBET') AND ft.status='COMPLETED' AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER' AND ft.created_at>=? AND ft.created_at<?");
                $stmt->execute([$userId,$from,$until]);
                if((int)$stmt->fetchColumn()<$minBet)throw new DomainException('Apostas válidas insuficientes neste dia de check-in.');
            }
            return $this->award($db,$userId,$row,$cfg,'checkin',$day,$next);
        });
    }
    public function adminHistory(string $type): array {
        if($type!=='' && !in_array($type,['coupons','checkin','chests','vip','vip_daily','vip_weekly','vip_monthly'],true))throw new DomainException('Tipo inválido.');
        $db=Database::connection();
        $where=$type!==''?' WHERE r.promotion_type=?':'';
        $stmt=$db->prepare('SELECT r.id,r.user_id,u.username,r.promotion_type,r.day_key,r.sequence_day,r.amount_minor,r.rollover_x,r.wager_required_minor,r.wager_progress_minor,r.status,r.created_at,c.title FROM promotion_redemptions r JOIN users u ON u.id=r.user_id JOIN promotion_configurations c ON c.id=r.campaign_id'.$where.' ORDER BY r.created_at DESC LIMIT 100');
        $stmt->execute($type!==''?[$type]:[]);
        return $stmt->fetchAll();
    }

    /** Chamado APENAS dentro da transação da aposta PlayFiver já autenticada e liquidada. */
    public static function applyCasinoWager(PDO $db,string $userId,string $transactionId,int $betMinor): void {
        if($betMinor<=0)return;
        $stmt=$db->prepare("SELECT le.id FROM ledger_entries le WHERE le.transaction_id=? AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER' LIMIT 1");
        $stmt->execute([$transactionId]);$ledgerId=$stmt->fetchColumn();
        if(!$ledgerId)return;
        $stmt=$db->prepare("SELECT * FROM promotion_redemptions WHERE user_id=? AND status='LOCKED' AND created_at<=NOW(6) ORDER BY created_at,id FOR UPDATE");
        $stmt->execute([$userId]);$awards=$stmt->fetchAll();$remaining=$betMinor;
        foreach($awards as $award){
            if($remaining<1)break;
            $due=(int)$award['wager_required_minor']-(int)$award['wager_progress_minor'];
            if($due<1){self::releaseIfPossible($db,$award);continue;}
            $use=min($remaining,$due);$remaining-=$use;
            $insert=$db->prepare('INSERT INTO promotion_wager_allocations(ledger_entry_id,redemption_id,amount_minor) VALUES (?,?,?)');
            $insert->execute([$ledgerId,$award['id'],$use]);
            $award['wager_progress_minor']=(int)$award['wager_progress_minor']+$use;
            $stmt=$db->prepare('UPDATE promotion_redemptions SET wager_progress_minor=? WHERE id=?');$stmt->execute([$award['wager_progress_minor'],$award['id']]);
            if((int)$award['wager_progress_minor']>=(int)$award['wager_required_minor'])self::releaseIfPossible($db,$award);
        }
    }

    private static function releaseIfPossible(PDO $db,array $award):void {
        if((int)$award['wager_progress_minor']<(int)$award['wager_required_minor'])return;
        $stmt=$db->prepare("SELECT wa.id,wa.type,wa.balance_minor FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id WHERE w.user_id=? AND wa.type IN ('CASH','BONUS') ORDER BY FIELD(wa.type,'CASH','BONUS') FOR UPDATE");
        $stmt->execute([$award['user_id']]);$accounts=[];foreach($stmt->fetchAll() as $a)$accounts[$a['type']]=$a;
        $amount=(int)$award['amount_minor'];
        if(!isset($accounts['BONUS'],$accounts['CASH']) || (int)$accounts['BONUS']['balance_minor']<$amount)return;
        $tx=self::uuid();$corr=self::uuid();
        $stmt=$db->prepare("INSERT INTO financial_transactions(id,user_id,type,status,reference_type,reference_id,correlation_id,metadata) VALUES (?,?,'PROMOTION_UNLOCK','COMPLETED','PROMOTION',?,?,'{}')");
        $stmt->execute([$tx,$award['user_id'],$award['id'],$corr]);
        foreach(['BONUS','CASH'] as $type){
            $acct=$accounts[$type];$before=(int)$acct['balance_minor'];$debit=$type==='BONUS';$after=$debit?$before-$amount:$before+$amount;
            $stmt=$db->prepare('UPDATE wallet_accounts SET balance_minor=? WHERE id=?');$stmt->execute([$after,$acct['id']]);
            $stmt=$db->prepare("INSERT INTO ledger_entries(id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor,reference_type,reference_id,correlation_id) VALUES (?,?,?,?,?,?,?,'PROMOTION',?,?)");
            $stmt->execute([self::uuid(),$tx,$acct['id'],$debit?'DEBIT':'CREDIT',$amount,$before,$after,$award['id'],$corr]);
        }
        $stmt=$db->prepare("UPDATE promotion_redemptions SET status='COMPLETED' WHERE id=? AND status='LOCKED'");$stmt->execute([$award['id']]);
    }

    private function lockUser(PDO $db,string $id):void {
        $stmt=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$stmt->execute([$id]);
        if($stmt->fetchColumn()!=='ACTIVE')throw new DomainException('Conta não está ativa.');
    }
    private function award(PDO $db,string $userId,array $row,array $cfg,string $type,?string $day,?int $sequence):array {
        $fixedBonus=($type==='chests'||str_starts_with($type,'vip'));
        $min=(int)($cfg[$type==='coupons'?'bonus_min_cents':($fixedBonus?'bonus_cents':'reward_min_cents')]??0);
        $max=$fixedBonus?$min:(int)($cfg[$type==='coupons'?'bonus_max_cents':'reward_max_cents']??0);
        if($min<0 || $max<$min || $max>100000000)throw new DomainException('Valores da recompensa inválidos no Admin.');
        if(array_key_exists('forced_reward_cents',$cfg)){
            $value=(int)$cfg['forced_reward_cents'];
        }else{
            $value=$type==='checkin' && empty($cfg['random_reward']) ? $min : ($max===$min?$min:random_int($min,$max));
        }
        if($type==='checkin')$value+=(int)($cfg['extra_cents']??0);
        if($value<0 || $value>100000000)throw new DomainException('Recompensa indisponível: configure um valor válido.');
        $rollover=$value>0?(float)($cfg['rollover_x']??0):0.0;
        if($rollover<0 || $rollover>100)throw new DomainException('Rollover inválido no Admin.');
        $required=(int)ceil($value*$rollover);
        $status=$required>0?'LOCKED':'COMPLETED';
        $id=self::uuid();
        $stmt=$db->prepare('INSERT INTO promotion_redemptions (id,user_id,campaign_id,coupon_campaign_id,promotion_type,day_key,sequence_day,amount_minor,rollover_x,wager_required_minor,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$id,$userId,$row['id'],$type==='coupons'?$row['id']:null,$type,$day,$sequence,$value,$rollover,$required,$status]);
        if($value>0){
            // Saldo sem rollover é sacável; bônus com rollover fica isolado na conta BONUS.
            $transaction=$this->ledgerCredit($db,$userId,$required>0?'BONUS':'CASH',$value,'PROMOTION_'.$type,$id,'promotion:'.$id);
            $stmt=$db->prepare('UPDATE promotion_redemptions SET financial_transaction_id=? WHERE id=?');$stmt->execute([$transaction,$id]);
        }
        return ['id'=>$id,'type'=>$type,'amount_minor'=>$value,'rollover_x'=>$rollover,'wager_required_minor'=>$required,'status'=>$status,'account_type'=>$value>0?($required>0?'BONUS':'CASH'):null];
    }
    private function ledgerCredit(PDO $db,string $userId,string $accountType,int $amount,string $type,string $ref,string $idem): string {
        $stmt=$db->prepare('SELECT wa.id,wa.balance_minor FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id WHERE w.user_id=? AND wa.type=? FOR UPDATE');$stmt->execute([$userId,$accountType]);$account=$stmt->fetch();
        if(!$account)throw new DomainException('Conta de carteira inexistente.');
        $before=(int)$account['balance_minor'];$after=$before+$amount;$tx=self::uuid();$corr=self::uuid();
        $stmt=$db->prepare("INSERT INTO financial_transactions(id,user_id,type,status,reference_type,reference_id,correlation_id,metadata) VALUES (?,?,?,'COMPLETED','PROMOTION',?,?,'{}')");$stmt->execute([$tx,$userId,$type,$ref,$corr]);
        $stmt=$db->prepare('UPDATE wallet_accounts SET balance_minor=? WHERE id=?');$stmt->execute([$after,$account['id']]);
        $stmt=$db->prepare("INSERT INTO ledger_entries(id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor,reference_type,reference_id,correlation_id) VALUES (?,?,?,'CREDIT',?,?,?,'PROMOTION',?,?)");
        $stmt->execute([self::uuid(),$tx,$account['id'],$amount,$before,$after,$ref,$corr]);
        return $tx;
    }
}
