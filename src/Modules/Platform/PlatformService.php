<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class PlatformService
{
    private function db(): PDO { return Database::connection(); }
    public function settings(): array
    {
        $rows=$this->db()->query('SELECT setting_key,setting_value FROM platform_settings')->fetchAll();
        $data=['site_name'=>'MZ90','support_email'=>'','maintenance'=>'0','accent_color'=>'#e34324','footer_text'=>'','logo_path'=>'','favicon_path'=>'','footer_about'=>'','contact_phone'=>'','social_whatsapp'=>'','social_telegram'=>'','social_instagram'=>'','social_facebook'=>''];
        foreach($rows as $r) $data[$r['setting_key']]=$r['setting_value'];
        return $data;
    }
    public function saveSettings(array $input): array
    {
        $allowed=['site_name','support_email','maintenance','accent_color','footer_text','logo_path','favicon_path','footer_about','contact_phone','social_whatsapp','social_telegram','social_instagram','social_facebook'];
        $db=$this->db();
        $stmt=$db->prepare('INSERT INTO platform_settings (setting_key,setting_value) VALUES (:k,:v) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach($allowed as $key){
            if(!array_key_exists($key,$input)) continue;
            $value=trim((string)$input[$key]);
            if($key==='maintenance') $value=filter_var($input[$key],FILTER_VALIDATE_BOOLEAN)?'1':'0';
            if(in_array($key,['logo_path','favicon_path'],true)&&$value!==''&&!preg_match('~^/uploads/identity/[a-z0-9_-]+\.(png|jpg|webp)$~i',$value)) throw new DomainException('Imagem de identidade inválida.');
            if(str_starts_with($key,'social_') && $value!=='' && (!filter_var($value,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($value),'https://'))) throw new DomainException('A rede social exige uma URL HTTPS válida.');
            if($key==='contact_phone' && $value!=='' && !preg_match('/^\+?[0-9() .-]{8,24}$/',$value)) throw new DomainException('Telefone de contato inválido.');
            if($key==='accent_color'&&!preg_match('/^#[0-9a-fA-F]{6}$/',$value)) throw new DomainException('Cor inválida.');
            if($key==='support_email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL)) throw new DomainException('E-mail inválido.');
            if(strlen($value)>(in_array($key,['footer_text','footer_about'],true)?500:255)) throw new DomainException('Campo muito longo.');
            $stmt->execute(['k'=>$key,'v'=>$value]);
        }
        return $this->settings();
    }
    public function list(string $type,bool $public=false): array
    {
        $table=$this->table($type);
        $sql='SELECT * FROM '.$table.($public?' WHERE enabled=1':'');
        if($type==='promotions'&&$public) $sql.=' AND (ends_at IS NULL OR ends_at>=NOW())';
        $sql.=' ORDER BY '.($type==='banners'?'sort_order ASC, ':'').'id DESC LIMIT 200';
        return $this->db()->query($sql)->fetchAll();
    }
    private function table(string $type): string
    {
        return match($type){'banners'=>'platform_banners','promotions'=>'platform_promotions','affiliates'=>'affiliate_links',default=>throw new DomainException('Recurso inválido.')};
    }
    public function save(string $type,array $data): array
    {
        $table=$this->table($type); $id=max(0,(int)($data['id']??0));
        $fields=match($type){
            'banners'=>['title','subtitle','image_path','target_path','position','enabled','sort_order'],
            'promotions'=>['title','description','image_path','starts_at','ends_at','enabled'],
            'affiliates'=>['code','label','enabled']};
        $values=[];
        foreach($fields as $field){
            $v=$data[$field]??null;
            if($field==='enabled') $v=filter_var($v,FILTER_VALIDATE_BOOLEAN)?1:0;
            elseif($field==='sort_order') $v=(int)$v;
            elseif(in_array($field,['starts_at','ends_at'],true)) { $v=$v?str_replace('T',' ',(string)$v):null; if($v&&!preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d(?::\d\d)?$/',$v))throw new DomainException('Data inválida.'); }
            else { $v=trim((string)$v); if(strlen($v)>($field==='description'?3000:255))throw new DomainException('Campo muito longo.'); }
            $values[$field]=$v;
        }
        if($type==='affiliates'){
            $values['code']=strtoupper($values['code']);
            if(!preg_match('/^[A-Z0-9_-]{4,32}$/',$values['code']))throw new DomainException('Código de afiliado inválido.');
        }else{
            if($values['title']==='')throw new DomainException('Título obrigatório.');
            if($values['image_path']!==''&&!preg_match('~^/uploads/(banners|promotions)/[a-z0-9_-]+\.(png|jpg|webp)$~i',$values['image_path']))throw new DomainException('Imagem deve ser enviada pelo painel.');
            if($type==='banners'&&!preg_match('~^/(?:$|[a-z0-9/_-]+$)~i',$values['target_path']))throw new DomainException('Destino interno inválido.');
            if($type==='banners'&&!in_array($values['position'],['home','casino'],true))throw new DomainException('Posição inválida.');
            if($type==='promotions'&&$values['starts_at']&&$values['ends_at']&&$values['starts_at']>$values['ends_at'])throw new DomainException('Período inválido.');
        }
        $db=$this->db();
        if($id){$sql='UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=:'.$k,$fields)).' WHERE id=:id';$values['id']=$id;}
        else $sql='INSERT INTO '.$table.' ('.implode(',',$fields).') VALUES (:'.implode(',:',$fields).')';
        $stmt=$db->prepare($sql);$stmt->execute($values);
        if($id&&!$stmt->rowCount()){ $check=$db->prepare('SELECT id FROM '.$table.' WHERE id=?');$check->execute([$id]);if(!$check->fetch())throw new DomainException('Registro não encontrado.'); }
        return ['id'=>$id?: (int)$db->lastInsertId()];
    }
    public function delete(string $type,int $id): void
    {
        if($id<=0) throw new DomainException('Registro inválido.');
        $table=$this->table($type);
        $stmt=$this->db()->prepare('DELETE FROM '.$table.' WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        if(!$stmt->rowCount()) throw new DomainException('Registro não encontrado.');
    }
    public function announcements(bool $public=false): array
    {
        $sql='SELECT id,message,enabled,sort_order,created_at FROM platform_announcements';
        if($public) $sql.=' WHERE enabled=1';
        try { return $this->db()->query($sql.' ORDER BY sort_order ASC,id DESC LIMIT 100')->fetchAll(); }
        catch (\Throwable) { return []; }
    }
    public function saveAnnouncement(array $input): array
    {
        $id=(int)($input['id']??0);
        $message=trim((string)($input['message']??''));
        $order=(int)($input['sort_order']??100);
        if($id<0 || $message==='' || mb_strlen($message)>240 || $order<0 || $order>100000) throw new DomainException('Informe uma novidade de até 240 caracteres e uma ordem válida.');
        $args=['message'=>$message,'enabled'=>filter_var($input['enabled']??false,FILTER_VALIDATE_BOOLEAN)?1:0,'sort_order'=>$order];
        $db=$this->db();
        if($id){
            $args['id']=$id;
            $stmt=$db->prepare('UPDATE platform_announcements SET message=:message,enabled=:enabled,sort_order=:sort_order WHERE id=:id');
            $stmt->execute($args);
            $check=$db->prepare('SELECT id FROM platform_announcements WHERE id=:id');$check->execute(['id'=>$id]);
            if(!$check->fetchColumn()) throw new DomainException('Novidade não encontrada.');
        }else{
            $stmt=$db->prepare('INSERT INTO platform_announcements(message,enabled,sort_order) VALUES(:message,:enabled,:sort_order)');
            $stmt->execute($args);$id=(int)$db->lastInsertId();
        }
        return ['id'=>$id];
    }
    public function deleteAnnouncement(int $id): void
    {
        if($id<=0) throw new DomainException('Novidade inválida.');
        $stmt=$this->db()->prepare('DELETE FROM platform_announcements WHERE id=:id');$stmt->execute(['id'=>$id]);
        if(!$stmt->rowCount()) throw new DomainException('Novidade não encontrada.');
    }
    public function audits(array $filter): array
    {
        $page=max(1,min(100000,(int)($filter['page']??1)));$limit=30;
        $action=trim((string)($filter['action']??''));
        $sql='SELECT id,actor_type,actor_id,action,entity_type,entity_id,created_at FROM audit_logs';
        $params=[];if($action!==''){$sql.=' WHERE action LIKE :action';$params['action']='%'.substr($action,0,80).'%';}
        $sql.=' ORDER BY id DESC LIMIT '.$limit.' OFFSET '.(($page-1)*$limit);
        $stmt=$this->db()->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }
    public function affiliateReport(): array
    {
        return $this->db()->query('SELECT a.id,a.code,a.label,a.enabled,a.created_at,COUNT(v.id) AS visits FROM affiliate_links a LEFT JOIN affiliate_visits v ON v.affiliate_id=a.id GROUP BY a.id,a.code,a.label,a.enabled,a.created_at ORDER BY a.id DESC LIMIT 200')->fetchAll();
    }
    public function recordReferral(string $code,string $ip): void
    {
        $stmt=$this->db()->prepare('SELECT id FROM affiliate_links WHERE code=:code AND enabled=1');$stmt->execute(['code'=>strtoupper($code)]);$id=$stmt->fetchColumn();
        if(!$id)return;
        $hash=hash_hmac('sha256',$ip.'|'.date('Y-m-d'),(string)\App\Core\Support\Env::get('APP_KEY',''));
        $insert=$this->db()->prepare('INSERT INTO affiliate_visits (affiliate_id,visitor_hash) VALUES (:id,:hash)');$insert->execute(['id'=>$id,'hash'=>$hash]);
    }
}
