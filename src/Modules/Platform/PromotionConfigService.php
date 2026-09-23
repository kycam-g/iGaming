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
        'roulette'=>['reward_min_cents'=>'money','reward_max_cents'=>'money','win_chance_percent'=>'decimal','deposit_min_cents'=>'money','spins_per_deposit'=>'int','spins_per_referral'=>'int','referral_requires_signup'=>'bool','rollover_x'=>'decimal'],
        'envelope'=>['auto_enabled'=>'bool','monday'=>'bool','tuesday'=>'bool','wednesday'=>'bool','thursday'=>'bool','friday'=>'bool','saturday'=>'bool','sunday'=>'bool','deposit_min_cents'=>'money','deposit_percent'=>'decimal','rollover_x'=>'decimal','message'=>'text'],
        'chests'=>['referral_count'=>'int','referred_deposit_min_cents'=>'money','bonus_cents'=>'money','rollover_x'=>'decimal','max_claims'=>'int'],
        'agency'=>['level'=>'int','team_bet_min_cents'=>'money','commission_percent'=>'decimal'],
        'rebate'=>['level'=>'int','bet_volume_cents'=>'money','rebate_percent'=>'decimal'],
        'rescue'=>['level'=>'int','loss_min_cents'=>'money','refund_percent'=>'decimal','rollover_x'=>'decimal'],
        'cashwheel'=>['target_cents'=>'money','duration_days'=>'int','free_spins_per_day'=>'int','spin_min_cents'=>'money','spin_max_cents'=>'money','first_spin_min_percent'=>'decimal','first_spin_max_percent'=>'decimal','later_spin_max_percent'=>'decimal','no_win_chance_percent'=>'decimal','cash_bonus_chance_percent'=>'decimal','cash_bonus_min_cents'=>'money','cash_bonus_max_cents'=>'money','cash_bonus_rollover_x'=>'decimal','referral_bonus_cents'=>'money','rollover_x'=>'decimal'],
        'lottery'=>['spins_per_day'=>'int','hit_chance_percent'=>'decimal','collection_bonus_cents'=>'money','rollover_x'=>'decimal'],
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
        $stmt=$this->db()->query('SELECT type,title,config FROM promotion_configurations WHERE enabled=1 AND type<>\'weekly\' ORDER BY id ASC LIMIT 300');
        return array_map(static function(array $row):array {
            $row['config']=json_decode($row['config'],true) ?: [];
            if($row['type']==='coupons')unset($row['config']['code']);
            if($row['type']==='lottery')unset($row['config']['hit_chance_percent']);
            if($row['type']==='roulette')unset($row['config']['win_chance_percent']);
            if($row['type']==='cashwheel')unset($row['config']['no_win_chance_percent'],$row['config']['cash_bonus_chance_percent']);
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
            if(!array_key_exists($name,$raw)){
                if($type==='vip' && in_array($name,['daily_bonus_cents','weekly_bonus_cents','monthly_bonus_cents','maintenance_cents'],true))$raw[$name]=0;
                elseif($type==='cashwheel' && $name==='first_spin_min_percent')$raw[$name]=60;
                elseif($type==='cashwheel' && $name==='first_spin_max_percent')$raw[$name]=90;
                elseif($type==='cashwheel' && $name==='later_spin_max_percent')$raw[$name]=8;
                elseif($type==='cashwheel' && $name==='no_win_chance_percent')$raw[$name]=20;
                elseif($type==='lottery' && $name==='hit_chance_percent')$raw[$name]=50;
                else throw new DomainException('Campo obrigatório: '.$name);
            }
            $value=$raw[$name];
            if($kind==='code'){if(!is_string($value)||!preg_match('/^[A-Z0-9_-]{4,64}$/D',strtoupper(trim($value))))throw new DomainException('Código do cupom: use 4 a 64 letras, números, _ ou -.');$config[$name]=strtoupper(trim($value));continue;}
            if($kind==='text'){if(!is_string($value))throw new DomainException('Texto inválido: '.$name);$value=trim($value);if(mb_strlen($value)>240)throw new DomainException('Texto muito longo: '.$name);$config[$name]=$value;continue;}
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
        if($type==='rebate'){
            if($config['level']<1 || $config['level']>100 || $config['rebate_percent']>10)throw new DomainException('Nível entre 1 e 100 e rebate entre 0% e 10%.');
            $dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='rebate' AND JSON_EXTRACT(config,'$.level')=? AND id<>? LIMIT 1");$dup->execute([$config['level'],$id]);
            if($dup->fetchColumn())throw new DomainException('Este nível de rebate já existe.');
        }
        if($type==='agency'){
            if($config['level']<1 || $config['level']>100 || $config['commission_percent']>30)throw new DomainException('Faixa entre 1 e 100 e comissão entre 0% e 30%.');
            $dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='agency' AND JSON_EXTRACT(config,'$.level')=? AND id<>? LIMIT 1");$dup->execute([$config['level'],$id]);
            if($dup->fetchColumn())throw new DomainException('Essa faixa da agência já existe.');
        }
        if($type==='chests' && ($config['referral_count']<1 || $config['referred_deposit_min_cents']<1 || $config['bonus_cents']<1))throw new DomainException('Meta, depósito mínimo por indicado e bônus devem ser maiores que zero.');
        if($type==='chests' && $config['rollover_x']>100)throw new DomainException('Rollover não pode ultrapassar 100x.');
        if($type==='rescue'){
            if($config['level']<1 || $config['level']>100 || $config['loss_min_cents']<1 || $config['refund_percent']<=0 || $config['refund_percent']>100 || $config['rollover_x']>100)throw new DomainException('Configure faixa, perda mínima, taxa de 0,01% a 100% e rollover até 100x.');
            $dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='rescue' AND JSON_EXTRACT(config,'$.level')=? AND id<>? LIMIT 1");$dup->execute([$config['level'],$id]);
            if($dup->fetchColumn())throw new DomainException('Esta faixa dos Fundos de Resgate já existe.');
        }
        if($type==='envelope'){
            $days=['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
            $hasDay=false;foreach($days as $day){if(!empty($config[$day])){$hasDay=true;break;}}
            if(!$hasDay)throw new DomainException('Selecione pelo menos um dia da semana para o Envelope Vermelho.');
            if($config['deposit_min_cents']<1)throw new DomainException('Configure um depósito mínimo semanal positivo para participar.');
            if($config['deposit_percent']<=0 || $config['deposit_percent']>100)throw new DomainException('A porcentagem do Envelope Vermelho deve ficar entre 0,01% e 100%.');
            if($config['rollover_x']>100)throw new DomainException('Rollover não pode ultrapassar 100x.');
            if(!$id){
                $dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='envelope' LIMIT 1");$dup->execute();
                if($dup->fetchColumn())throw new DomainException('O Envelope Vermelho aceita apenas uma configuração. Edite a configuração existente.');
            }
        }
        if($type==='lottery'){
            if($config['spins_per_day']<1 || $config['spins_per_day']>50)throw new DomainException('Configure entre 1 e 50 rodadas por dia no Sorteio.');
            if($config['collection_bonus_cents']<1 || $config['collection_bonus_cents']>100000000)throw new DomainException('Configure um prêmio válido para completar a coleção.');
            if($config['rollover_x']<0 || $config['rollover_x']>100)throw new DomainException('Rollover do Sorteio deve ficar entre 0x e 100x.');
            if(!$id){$dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='lottery' LIMIT 1");$dup->execute();if($dup->fetchColumn())throw new DomainException('O Sorteio aceita apenas uma configuração. Edite a existente.');}
        }
        if($type==='cashwheel'){
            if($config['target_cents']<100)throw new DomainException('A meta da Roleta de Saque deve ser de pelo menos R$ 1,00.');
            if($config['duration_days']<1 || $config['duration_days']>30)throw new DomainException('A validade deve ficar entre 1 e 30 dias.');
            if($config['free_spins_per_day']<1 || $config['free_spins_per_day']>20)throw new DomainException('Configure entre 1 e 20 rodadas gratuitas por dia.');
            if($config['spin_min_cents']<1 || $config['spin_max_cents']<$config['spin_min_cents'] || $config['spin_max_cents']>$config['target_cents'])throw new DomainException('Configure corretamente o avanço mínimo e máximo por giro.');
            if($config['first_spin_min_percent']<1 || $config['first_spin_max_percent']>90 || $config['first_spin_min_percent']>$config['first_spin_max_percent'])throw new DomainException('O primeiro giro deve usar uma faixa válida, com máximo de 90% da meta.');
            if($config['later_spin_max_percent']<=0 || $config['later_spin_max_percent']>25)throw new DomainException('O avanço máximo dos giros seguintes deve ficar entre 0,01% e 25% da meta.');
            if($config['no_win_chance_percent']<0 || $config['no_win_chance_percent']>100)throw new DomainException('A chance de não ganhar nada deve ficar entre 0% e 100%.');
            if($config['cash_bonus_chance_percent']<0 || $config['cash_bonus_chance_percent']>100)throw new DomainException('A chance de bônus em moeda deve ficar entre 0% e 100%.');
            if(($config['no_win_chance_percent']+$config['cash_bonus_chance_percent'])>95)throw new DomainException('A soma das chances de bônus direto e sem prêmio não pode ultrapassar 95%.');
            if($config['cash_bonus_chance_percent']>0 && ($config['cash_bonus_min_cents']<1 || $config['cash_bonus_max_cents']<$config['cash_bonus_min_cents']))throw new DomainException('Configure corretamente o bônus mínimo e máximo em moeda.');
            if($config['cash_bonus_rollover_x']>100 || $config['rollover_x']>100)throw new DomainException('Rollover não pode ultrapassar 100x.');
            if($config['referral_bonus_cents']>$config['target_cents'])throw new DomainException('A ajuda por indicação não pode ser maior que a meta.');
            if(!$id){$dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='cashwheel' LIMIT 1");$dup->execute();if($dup->fetchColumn())throw new DomainException('A Roleta de Saque aceita apenas uma configuração. Edite a existente.');}
        }
        if($type==='lottery'){
            if($config['spins_per_day']<1 || $config['spins_per_day']>100)throw new DomainException('Configure entre 1 e 100 rodadas do Sorteio por dia.');
            if($config['hit_chance_percent']<1 || $config['hit_chance_percent']>95)throw new DomainException('A chance de carta útil deve ficar entre 1% e 95%.');
            if($config['collection_bonus_cents']<1)throw new DomainException('Configure um prêmio positivo para completar HAPPY.');
            if($config['rollover_x']>100)throw new DomainException('Rollover não pode ultrapassar 100x.');
            if(!$id){$dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='lottery' LIMIT 1");$dup->execute();if($dup->fetchColumn())throw new DomainException('O Sorteio aceita apenas uma configuração. Edite a existente.');}
        }
        if($type==='roulette'){
            if($config['spins_per_deposit']===0 && $config['spins_per_referral']===0)throw new DomainException('Defina pelo menos uma forma de ganhar rodadas.');
            if($config['reward_min_cents']<1 || $config['reward_max_cents']>100000 || $config['rollover_x']>100 || $config['spins_per_deposit']>10 || $config['spins_per_referral']>10)throw new DomainException('Prêmios entre R$ 0,01 e R$ 1.000, no máximo 10 giros por evento e rollover até 100x.');
            if($config['win_chance_percent']<=0 || $config['win_chance_percent']>100)throw new DomainException('A chance de ganho deve estar entre 0,01% e 100%.');
            if($config['spins_per_deposit']>0 && $config['deposit_min_cents']<1)throw new DomainException('Configure depósito mínimo positivo.');
            if($config['spins_per_referral']>0 && !$config['referral_requires_signup'])throw new DomainException('Indicação exige cadastro pelo link para gerar rodada.');
            if(!$id){
                $dup=$this->db()->prepare("SELECT id FROM promotion_configurations WHERE type='roulette' LIMIT 1");$dup->execute();
                if($dup->fetchColumn())throw new DomainException('O Giro da Sorte aceita apenas uma configuração. Edite a roleta existente.');
            }
        }
        $enabled=filter_var($body['enabled']??false,FILTER_VALIDATE_BOOLEAN)?1:0;
        $db=$this->db();
        $rouletteConfigChanged=false;
        if($id){
            $check=$db->prepare('SELECT id FROM promotion_configurations WHERE id=? AND type=?');$check->execute([$id,$type]);
            if(!$check->fetchColumn())throw new DomainException('Registro não encontrado para esta seção.');
        if($type==='roulette'){
                // O Giro da Sorte é uma configuração única e pode ser editado mesmo após emitir rodadas.
                // Créditos e giros já existentes são preservados; novas regras passam a valer para eventos futuros.
                $prior=$db->prepare('SELECT config FROM promotion_configurations WHERE id=?');$prior->execute([$id]);
                $old=json_decode((string)$prior->fetchColumn(),true)?:[];
                $rouletteConfigChanged=($old!==$config);
            }
            if($type==='rebate'){
                $used=$db->prepare('SELECT id FROM rebate_events WHERE campaign_id=? LIMIT 1');$used->execute([$id]);
                if($used->fetchColumn()){
                    $prior=$db->prepare("SELECT config FROM promotion_configurations WHERE id=? AND type='rebate'");$prior->execute([$id]);$old=json_decode((string)$prior->fetchColumn(),true)?:[];
                    if($old!==$config)throw new DomainException('Faixa com pagamentos apurados: desative e crie outra faixa para alterar valores históricos.');
                }
            }
            if($type==='rescue'){
                $used=$db->prepare('SELECT id FROM rescue_daily_awards WHERE campaign_id=? LIMIT 1');$used->execute([$id]);
                if($used->fetchColumn()){
                    $prior=$db->prepare("SELECT config FROM promotion_configurations WHERE id=? AND type='rescue'");$prior->execute([$id]);$old=json_decode((string)$prior->fetchColumn(),true)?:[];
                    if($old!==$config)throw new DomainException('Faixa já apurada: desative e crie outra faixa para preservar as regras históricas.');
                }
            }
            if($type==='chests'){
                $used=$db->prepare("SELECT id FROM promotion_redemptions WHERE campaign_id=? AND promotion_type='chests' LIMIT 1");$used->execute([$id]);
                if($used->fetchColumn()){
                    $prior=$db->prepare("SELECT config FROM promotion_configurations WHERE id=? AND type='chests'");$prior->execute([$id]);$old=json_decode((string)$prior->fetchColumn(),true)?:[];
                    if($old!==$config)throw new DomainException('Baú já resgatado: desative e cadastre uma nova campanha para alterar os requisitos.');
                }
            }
            $sql='UPDATE promotion_configurations SET title=:title,config=:config,enabled=:enabled,coupon_code=:coupon_code WHERE id=:id AND type=:type';
            $params=['id'=>$id,'type'=>$type];
        }else{
            $sql='INSERT INTO promotion_configurations(type,title,config,enabled,coupon_code) VALUES(:type,:title,:config,:enabled,:coupon_code)';
            $params=['type'=>$type];
        }
        $stmt=$db->prepare($sql);$stmt->execute($params+['title'=>$title,'config'=>json_encode($config,JSON_THROW_ON_ERROR),'enabled'=>$enabled,'coupon_code'=>$type==='coupons'?$config['code']:null]);
        $savedId=$id?: (int)$db->lastInsertId();
        if($type==='roulette'){
            $prior=$db->prepare('SELECT activated_at FROM roulette_campaign_state WHERE campaign_id=?');$prior->execute([$savedId]);$hasState=(bool)$prior->fetchColumn();
            if($enabled && !$hasState){
                $db->prepare('INSERT INTO roulette_campaign_state(campaign_id,activated_at) VALUES(?,NOW(6))')->execute([$savedId]);
            }elseif($enabled && $hasState && $rouletteConfigChanged){
                // Evita aplicar requisitos novos retroativamente a depósitos/indicações antigos.
                $db->prepare('UPDATE roulette_campaign_state SET activated_at=NOW(6) WHERE campaign_id=?')->execute([$savedId]);
            }elseif(!$enabled && $hasState){
                $db->prepare('DELETE FROM roulette_campaign_state WHERE campaign_id=?')->execute([$savedId]);
            }
        }
        return ['id'=>$savedId];
    }
    public function delete(string $type,int $id): void {
        $this->fields($type);
        if($id<1)throw new DomainException('Registro inválido.');
        $claims=$this->db()->prepare('SELECT id FROM promotion_redemptions WHERE campaign_id=? LIMIT 1');$claims->execute([$id]);if($claims->fetchColumn())throw new DomainException('Campanha já utilizada: desative-a para preservar o histórico.');
        if($type==='roulette'){
            $credits=$this->db()->prepare('SELECT id FROM roulette_spin_credits WHERE campaign_id=? LIMIT 1');$credits->execute([$id]);
            if($credits->fetchColumn())throw new DomainException('Roleta com rodadas emitidas: desative para preservar o histórico.');
            $this->db()->prepare('DELETE FROM roulette_campaign_state WHERE campaign_id=?')->execute([$id]);
        }
        if($type==='vip'){$claims=$this->db()->prepare('SELECT id FROM vip_period_awards WHERE campaign_id=? LIMIT 1');$claims->execute([$id]);if($claims->fetchColumn())throw new DomainException('Nível já possui períodos VIP: desative-o para preservar o histórico.');}
        if($type==='rebate'){$claims=$this->db()->prepare('SELECT id FROM rebate_events WHERE campaign_id=? LIMIT 1');$claims->execute([$id]);if($claims->fetchColumn())throw new DomainException('Faixa com rebate apurado: desative para preservar o histórico.');}
        if($type==='rescue'){$claims=$this->db()->prepare('SELECT id FROM rescue_daily_awards WHERE campaign_id=? LIMIT 1');$claims->execute([$id]);if($claims->fetchColumn())throw new DomainException('Faixa com fundos apurados: desative para preservar o histórico.');}
        if($type==='agency'){$claims=$this->db()->prepare('SELECT id FROM agency_commissions WHERE campaign_id=? LIMIT 1');$claims->execute([$id]);if($claims->fetchColumn())throw new DomainException('Faixa já utilizada para comissões: desative-a para preservar o histórico.');}
        $stmt=$this->db()->prepare('DELETE FROM promotion_configurations WHERE id=:id AND type=:type');
        $stmt->execute(['id'=>$id,'type'=>$type]);
        if(!$stmt->rowCount())throw new DomainException('Registro não encontrado.');
    }
}
