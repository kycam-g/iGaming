<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

/** Configurações administrativas. Nenhuma operação concede saldo ou rodada. */
final class PromotionConfigService
{
    private const FIELDS = [
        'vip'=>['level'=>'int','goal_cents'=>'money','bonus_cents'=>'money','daily_bonus_cents'=>'money','weekly_bonus_cents'=>'money','monthly_bonus_cents'=>'money','maintenance_cents'=>'money','rollover_x'=>'decimal'],
        'coupons'=>['code'=>'code','quantity'=>'int','bonus_min_cents'=>'money','bonus_max_cents'=>'money','rollover_x'=>'decimal'],
        'checkin'=>['day'=>'int','reward_min_cents'=>'money','reward_max_cents'=>'money','deposit_min_cents'=>'money','bet_min_cents'=>'money','extra_cents'=>'money','random_reward'=>'bool','rollover_x'=>'decimal'],
        'roulette'=>['reward_min_cents'=>'money','reward_max_cents'=>'money','deposit_min_cents'=>'money','spins_per_deposit'=>'int','spins_per_referral'=>'int','referral_requires_signup'=>'bool','rollover_x'=>'decimal'],
        'envelope'=>['reward_min_cents'=>'money','reward_max_cents'=>'money','multiplier_min'=>'decimal','multiplier_max'=>'decimal','auto_enabled'=>'bool','rollover_x'=>'decimal'],
        'chests'=>['referral_count'=>'int','referred_deposit_min_cents'=>'money','bonus_cents'=>'money','rollover_x'=>'decimal','max_claims'=>'int'],
        'agency'=>['level'=>'int','team_bet_min_cents'=>'money','commission_percent'=>'decimal'],
        'rebate'=>['level'=>'int','bet_volume_cents'=>'money','rebate_percent'=>'decimal'],
        'rescue'=>['level'=>'int','loss_min_cents'=>'money','refund_percent'=>'decimal','rollover_x'=>'decimal'],
        'weekly'=>['level'=>'int','loss_min_cents'=>'money','refund_percent'=>'decimal','rollover_x'=>'decimal'],
        'cashwheel'=>['target_cents'=>'money','duration_days'=>'int','free_spins_per_day'=>'int','referral_bonus_cents'=>'money'],
        'lottery'=>['spins_per_day'=>'int','collection_bonus_cents'=>'money','rollover_x'=>'decimal'],
    ];
    private function db(): PDO { return Database::connection(); }
    private function fields(string $type): array {
        if (!isset(self::FIELDS[$type])) throw new DomainException('Tipo de promoção inválido.');
        return self::FIELDS[$type];
    }
    public function list(string $type): array {
        $this->fields($type);
        $stmt=$this->db()->prepare('SELECT id,type,title,config,coupon_code,enabled,created_at,updated_at FROM promotion_configurations WHERE type=:type ORDER BY id ASC LIMIT 300');
        $stmt->execute(['type'=>$type]);
        return array_map(static function(array $row):array { $row['config']=json_decode($row['config'],true) ?: [];if($row['type']==='coupons')$row['config']['code']=(string)($row['coupon_code']??'');unset($row['coupon_code']);return $row; },$stmt->fetchAll());
    }
    public function publicList(): array {
        $stmt=$this->db()->query('SELECT type,title,config FROM promotion_configurations WHERE enabled=1 ORDER BY id ASC LIMIT 300');
        return array_map(static function(array $row):array {
            $row['config']=json_decode($row['config'],true) ?: [];
            if($row['type']==='coupons')unset($row['config']['code']);
            return $row;
        },$stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    public function save(string $type,array $body): array {
        $fields=$this->fields($type);
        $id=(int)($body['id']??0);
        if($id<0)throw new DomainException('ID inválido.');
        $title=trim((string)($body['title']??''));
        if($title==='' || mb_strlen($title)>120)throw new DomainException('Informe um nome de até 120 caracteres.');
        if(!is_array($body['config']??null))throw new DomainException('Configuração inválida.');
        $raw=$body['config'];$config=[];
        foreach($fields as $name=>$kind){
            if(!array_key_exists($name,$raw)){if($type==='vip' && in_array($name,['daily_bonus_cents','weekly_bonus_cents','monthly_bonus_cents','maintenance_cents'],true))$raw[$name]=0;else throw new DomainException('Campo obrigatório: '.$name);}
            $value=$raw[$name];
            if($kind==='code'){if(!is_string($value)||!preg_match('/^[A-Z0-9_-]{4,64}$/D',strtoupper(trim($value))))throw new DomainException('Código do cupom: use 4 a 64 letras, números, _ ou -.');$config[$name]=strtoupper(trim($value));continue;}
            if($kind==='bool'){$config[$name]=filter_var($value,FILTER_VALIDATE_BOOLEAN)?1:0;continue;}
            if(!is_scalar($value) || !is_numeric((string)$value) || !is_finite((float)$value))throw new DomainException('Valor numérico inválido: '.$name);
            $number=(float)$value;
            if($number<0 || $number>1000000000000)throw new DomainException('Valor fora do intervalo: '.$name);
            if($kind!=='decimal' && floor($number)!==$number)throw new DomainException('Informe um número inteiro: '.$name);
            $config[$name]=$kind==='decimal'?round($number,2):(int)$number;
        }
        foreach([['bonus_min_cents','bonus_max_cents'],['reward_min_cents','reward_max_cents'],['multiplier_min','multiplier_max']] as [$min,$max]) {
            if(isset($config[$min],$config[$max]) && $config[$min]>$config[$max])throw new DomainException('O mínimo não pode exceder o máximo.');
        }
        if($type==='vip' && ($config['level']<1 || $config['level']>1000))throw new DomainException('Nível VIP deve estar entre 1 e 1000.');
        if($type==='vip'){ $dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='vip' AND JSON_EXTRACT(config,'$.level')=? AND id<>? LIMIT 1");$dup->execute([$config['level'],$id]);if($dup->fetchColumn())throw new DomainException('Este nível VIP já está cadastrado.'); }
        if($type==='checkin' && ($config['day']<1 || $config['day']>365))throw new DomainException('Dia deve estar entre 1 e 365.');
        if($type==='coupons' && ($config['quantity']<1 || $config['bonus_min_cents']<1))throw new DomainException('Informe estoque e bônus de cupom positivos.');
        if($type==='checkin' && $config['reward_min_cents']<1)throw new DomainException('Configure a recompensa do check-in acima de zero.');
        if($type==='checkin'){$dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='checkin' AND JSON_EXTRACT(config,'$.day')=? AND id<>? LIMIT 1");$dup->execute([$config['day'],$id]);if($dup->fetchColumn())throw new DomainException('Este dia do check-in já existe.');}
        if($type==='chests' && $config['referral_count']<1)throw new DomainException('A meta de indicados deve ser positiva.');
        if($type==='roulette' && $config['spins_per_deposit']===0 && $config['spins_per_referral']===0)throw new DomainException('Defina pelo menos uma forma de ganhar rodadas.');
        $enabled=filter_var($body['enabled']??false,FILTER_VALIDATE_BOOLEAN)?1:0;
        $db=$this->db();
        if($id){
            $check=$db->prepare('SELECT id FROM promotion_configurations WHERE id=? AND type=?');$check->execute([$id,$type]);
            if(!$check->fetchColumn())throw new DomainException('Registro não encontrado para esta seção.');
            $sql='UPDATE promotion_configurations SET title=:title,config=:config,enabled=:enabled,coupon_code=:coupon_code WHERE id=:id AND type=:type';
            $params=['id'=>$id,'type'=>$type];
        }else{
            $sql='INSERT INTO promotion_configurations(type,title,config,enabled,coupon_code) VALUES(:type,:title,:config,:enabled,:coupon_code)';
            $params=['type'=>$type];
        }
        $stmt=$db->prepare($sql);$stmt->execute($params+['title'=>$title,'config'=>json_encode($config,JSON_THROW_ON_ERROR),'enabled'=>$enabled,'coupon_code'=>$type==='coupons'?$config['code']:null]);
        return ['id'=>$id?: (int)$db->lastInsertId()];
    }
    public function delete(string $type,int $id): void {
        $this->fields($type);
        if($id<1)throw new DomainException('Registro inválido.');
        $claims=$this->db()->prepare('SELECT id FROM promotion_redemptions WHERE campaign_id=? LIMIT 1');$claims->execute([$id]);if($claims->fetchColumn())throw new DomainException('Campanha já utilizada: desative-a para preservar o histórico.');
        if($type==='vip'){$claims=$this->db()->prepare('SELECT id FROM vip_period_awards WHERE campaign_id=? LIMIT 1');$claims->execute([$id]);if($claims->fetchColumn())throw new DomainException('Nível já possui períodos VIP: desative-o para preservar o histórico.');}
        $stmt=$this->db()->prepare('DELETE FROM promotion_configurations WHERE id=:id AND type=:type');
        $stmt->execute(['id'=>$id,'type'=>$type]);
        if(!$stmt->rowCount())throw new DomainException('Registro não encontrado.');
    }
}
