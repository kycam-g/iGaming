<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

/** Rebate server-side: 1 real = 100 centavos; unidades = centavos * basis points. */
final class RebateService
{
    /** Arredondamento de recompensas é aplicado somente no resgate, nunca por aposta. */
    public static function unitsForBet(int $betMinor,int $basisPoints):int
    {
        if($betMinor<0||$basisPoints<0||$basisPoints>1000||$betMinor>1000000000000)throw new DomainException('Valor da aposta ou taxa de rebate inválidos.');
        return $betMinor*$basisPoints;
    }
    public static function availableMinor(int $pendingUnits):int
    {
        if($pendingUnits<0)throw new DomainException('Saldo de rebate inválido.');
        return intdiv($pendingUnits,10000);
    }
    private function settings(PDO $db,bool $lock=false):array
    {
        $row=$db->query('SELECT enabled,activated_at,min_claim_minor,rate_basis_points FROM rebate_settings WHERE id=1'.($lock?' FOR UPDATE':''))->fetch();
        return ['enabled'=>(bool)($row['enabled']??false),'activated_at'=>$row['activated_at']??null,'min_claim_minor'=>(int)($row['min_claim_minor']??100),'rate_basis_points'=>(int)($row['rate_basis_points']??0)];
    }
    /** Taxa vigente na data da aposta, não na data de execução do cron. */
    private function rateForBet(PDO $db,string $createdAt,string $activatedAt):int
    {
        $stmt=$db->prepare('SELECT rate_basis_points FROM rebate_rate_periods WHERE started_at<=? AND started_at>=? ORDER BY started_at DESC,id DESC LIMIT 1');
        $stmt->execute([$createdAt,$activatedAt]);
        return (int)($stmt->fetchColumn()?:0);
    }
    public function adminSettings():array{return $this->settings(Database::connection());}
    public function saveSettings(bool $enabled,int $min,int $rate):array
    {
        if($min<1||$min>100000000)throw new DomainException('Resgate mínimo deve ficar entre R$ 0,01 e R$ 1.000.000.');
        if($rate<0||$rate>1000)throw new DomainException('Taxa fixa deve ficar entre 0% e 10%.');
        if($enabled&&$rate<1)throw new DomainException('Configure uma taxa fixa positiva antes de ativar.');
        return Database::transaction(function(PDO $db)use($enabled,$min,$rate):array{
            $db->exec('INSERT IGNORE INTO rebate_settings(id,enabled,min_claim_minor,rate_basis_points) VALUES(1,0,100,0)');
            $row=$db->query('SELECT enabled,activated_at,rate_basis_points FROM rebate_settings WHERE id=1 FOR UPDATE')->fetch();
            $wasEnabled=(bool)$row['enabled'];$changed=$rate!==(int)$row['rate_basis_points'];
            $now=(string)$db->query('SELECT NOW(6)')->fetchColumn();
            if($enabled&&!$wasEnabled){
                $db->prepare('UPDATE rebate_settings SET enabled=1,activated_at=?,min_claim_minor=?,rate_basis_points=? WHERE id=1')->execute([$now,$min,$rate]);
            }else{
                $db->prepare('UPDATE rebate_settings SET enabled=?,min_claim_minor=?,rate_basis_points=? WHERE id=1')->execute([(int)$enabled,$min,$rate]);
            }
            if($enabled&&(!$wasEnabled||$changed)){
                $db->prepare('INSERT INTO rebate_rate_periods(started_at,rate_basis_points) VALUES(?,?)')->execute([$now,$rate]);
            }
            return $this->settings($db);
        });
    }
    public function status(string $userId):array
    {
        $db=Database::connection();$settings=$this->settings($db);
        $stmt=$db->prepare('SELECT volume_minor,pending_units,claimed_minor,activation_epoch FROM rebate_accounts WHERE user_id=?');$stmt->execute([$userId]);$acct=$stmt->fetch()?:[];
        $volume=($acct&&$acct['activation_epoch']===$settings['activated_at'])?(int)$acct['volume_minor']:0;
        $stmt=$db->prepare('SELECT amount_minor,created_at FROM rebate_claims WHERE user_id=? ORDER BY created_at DESC LIMIT 30');$stmt->execute([$userId]);
        return ['settings'=>$settings,'volume_minor'=>$volume,'available_minor'=>self::availableMinor((int)($acct['pending_units']??0)),'claimed_minor'=>(int)($acct['claimed_minor']??0),'history'=>$stmt->fetchAll()];
    }
    public function report():array
    {
        $db=Database::connection();$users=$db->query('SELECT a.user_id,u.username,a.volume_minor,a.pending_units,a.claimed_minor FROM rebate_accounts a JOIN users u ON u.id=a.user_id ORDER BY a.updated_at DESC LIMIT 100')->fetchAll();
        $claims=$db->query('SELECT c.user_id,u.username,c.amount_minor,c.created_at FROM rebate_claims c JOIN users u ON u.id=c.user_id ORDER BY c.created_at DESC LIMIT 100')->fetchAll();
        return ['settings'=>$this->settings($db),'users'=>$users,'claims'=>$claims];
    }
    public function runBatch(int $limit=200):array
    {
        $db=Database::connection();$settings=$this->settings($db);
        if(!$settings['enabled']||!$settings['activated_at']||!$settings['rate_basis_points'])return ['disabled'=>true,'processed'=>0,'accrued_minor'=>0];
        $limit=max(1,min(500,$limit));$processed=0;$rawTotal=0;
        $lastDate='1000-01-01 00:00:00';$lastId='';
        while(true){
            $stmt=$db->prepare("SELECT le.id AS ledger_id,le.amount_minor AS bet_minor,ft.user_id,ft.created_at FROM ledger_entries le JOIN financial_transactions ft ON ft.id=le.transaction_id JOIN users u ON u.id=ft.user_id AND u.status='ACTIVE' WHERE ft.type IN ('CASINO_BET','CASINO_WINBET') AND ft.status='COMPLETED' AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER' AND ft.created_at>=? AND (ft.created_at>? OR (ft.created_at=? AND le.id>?)) AND NOT EXISTS(SELECT 1 FROM rebate_events e WHERE e.bet_ledger_id=le.id) ORDER BY ft.created_at,le.id LIMIT {$limit}");
            $stmt->execute([$settings['activated_at'],$lastDate,$lastDate,$lastId]);$rows=$stmt->fetchAll();if(!$rows)break;
            foreach($rows as $row){
                $lastDate=(string)$row['created_at'];$lastId=(string)$row['ledger_id'];
                $units=Database::transaction(function(PDO $tx)use($row,$settings):?int{
                    $lock=$tx->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$lock->execute([$row['user_id']]);if($lock->fetchColumn()!=='ACTIVE')return null;
                    $state=$this->settings($tx,true);if(!$state['enabled']||$state['activated_at']!==$settings['activated_at'])return null;
                    $existing=$tx->prepare('SELECT id FROM rebate_events WHERE bet_ledger_id=?');$existing->execute([$row['ledger_id']]);if($existing->fetchColumn())return null;
                    $tx->prepare('INSERT IGNORE INTO rebate_accounts(user_id,activation_epoch) VALUES(?,?)')->execute([$row['user_id'],$settings['activated_at']]);
                    $acct=$tx->prepare('SELECT activation_epoch,volume_minor FROM rebate_accounts WHERE user_id=? FOR UPDATE');$acct->execute([$row['user_id']]);$current=$acct->fetch();
                    $volume=$current['activation_epoch']===$settings['activated_at']?(int)$current['volume_minor']:0;
                    $volume+=(int)$row['bet_minor'];$rate=$this->rateForBet($tx,(string)$row['created_at'],(string)$settings['activated_at']);
                    // Entire integer calculation; sub-cent residuals persist in the account.
                    $units=self::unitsForBet((int)$row['bet_minor'],$rate);
                    $tx->prepare('UPDATE rebate_accounts SET activation_epoch=?,volume_minor=?,pending_units=pending_units+? WHERE user_id=?')->execute([$settings['activated_at'],$volume,$units,$row['user_id']]);
                    $tx->prepare('INSERT INTO rebate_events(bet_ledger_id,user_id,campaign_id,volume_minor,rate_basis_points,reward_units) VALUES(?,?,?,?,?,?)')->execute([$row['ledger_id'],$row['user_id'],null,$row['bet_minor'],$rate,$units]);
                    return $units;
                });
                if($units!==null){$processed++;$rawTotal+=$units;}
            }
            if(count($rows)<$limit)break;
        }
        return ['disabled'=>false,'processed'=>$processed,'accrued_minor'=>intdiv($rawTotal,10000)];
    }
    public function claim(string $userId):array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $lock=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$lock->execute([$userId]);if($lock->fetchColumn()!=='ACTIVE')throw new DomainException('Conta não está ativa.');
            $settings=$this->settings($db);if(!$settings['enabled'])throw new DomainException('Rebate temporariamente desativado.');
            $acct=$db->prepare('SELECT pending_units FROM rebate_accounts WHERE user_id=? FOR UPDATE');$acct->execute([$userId]);$pending=(int)($acct->fetchColumn()?:0);
            $amount=self::availableMinor($pending);if($amount<$settings['min_claim_minor'])throw new DomainException('Saldo de rebate abaixo do mínimo de resgate.');
            $wallet=$db->prepare("SELECT wa.id,wa.balance_minor FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id WHERE w.user_id=? AND wa.type='CASH' FOR UPDATE");$wallet->execute([$userId]);$cash=$wallet->fetch();if(!$cash)throw new DomainException('Carteira não encontrada.');
            $claim=PromotionRedemptionService::uuid();$txid=PromotionRedemptionService::uuid();$corr=PromotionRedemptionService::uuid();$before=(int)$cash['balance_minor'];$after=$before+$amount;
            $stmt=$db->prepare("INSERT INTO financial_transactions(id,user_id,type,status,reference_type,reference_id,correlation_id,metadata) VALUES (?,?,'REBATE_CLAIM','COMPLETED','REBATE',?,?,'{}')");$stmt->execute([$txid,$userId,$claim,$corr]);
            $db->prepare('UPDATE wallet_accounts SET balance_minor=? WHERE id=?')->execute([$after,$cash['id']]);
            $stmt=$db->prepare("INSERT INTO ledger_entries(id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor,reference_type,reference_id,correlation_id) VALUES (?,?,?,'CREDIT',?,?,?,'REBATE',?,?)");$stmt->execute([PromotionRedemptionService::uuid(),$txid,$cash['id'],$amount,$before,$after,$claim,$corr]);
            $db->prepare('UPDATE rebate_accounts SET pending_units=pending_units-?,claimed_minor=claimed_minor+? WHERE user_id=?')->execute([$amount*10000,$amount,$userId]);
            $db->prepare('INSERT INTO rebate_claims(id,user_id,amount_minor,financial_transaction_id) VALUES (?,?,?,?)')->execute([$claim,$userId,$amount,$txid]);
            return ['id'=>$claim,'amount_minor'=>$amount,'account_type'=>'CASH','status'=>'COMPLETED'];
        });
    }
}
