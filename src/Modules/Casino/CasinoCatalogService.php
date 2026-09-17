<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class CasinoCatalogService
{
    public function providers(): array
    {
        $pdo=Database::connection();
        try { return $pdo->query('SELECT id,code,name,logo_path,enabled,mode,api_source,created_at FROM casino_providers ORDER BY name')->fetchAll(); }
        catch (\Throwable) { return $pdo->query("SELECT id,code,name,'' AS logo_path,enabled,mode,api_source,created_at FROM casino_providers ORDER BY name")->fetchAll(); }
    }

    public function publicProviders(): array
    {
        $pdo=Database::connection();
        try { return $pdo->query("SELECT id,code,name,logo_path FROM casino_providers WHERE enabled=1 ORDER BY name ASC,id ASC")->fetchAll(); }
        catch (\Throwable) { return $pdo->query("SELECT id,code,name,'' AS logo_path FROM casino_providers WHERE enabled=1 ORDER BY name ASC,id ASC")->fetchAll(); }
    }

    public function categories(bool $public = false): array
    {
        if (!$this->categoriesTableExists()) {
            return [
                ['id'=>0,'code'=>'SLOTS','name'=>'Slots','icon_key'=>'slots','enabled'=>1,'sort_order'=>10],
                ['id'=>0,'code'=>'OTHER','name'=>'Pescaria','icon_key'=>'fish','enabled'=>1,'sort_order'=>20],
                ['id'=>0,'code'=>'LIVE','name'=>'SportBet','icon_key'=>'sport','enabled'=>1,'sort_order'=>30],
                ['id'=>0,'code'=>'TABLE','name'=>'Roleta','icon_key'=>'roulette','enabled'=>1,'sort_order'=>40],
            ];
        }
        $where=$public ? ' WHERE enabled=1' : '';
        return Database::connection()->query('SELECT id,code,name,icon_key,enabled,sort_order,created_at FROM casino_categories'.$where.' ORDER BY sort_order ASC,id ASC')->fetchAll();
    }

    public function saveCategory(array $data): array
    {
        if (!$this->categoriesTableExists()) throw new DomainException('Execute as migrations para habilitar categorias de jogos.');
        $id=(int)($data['id']??0);
        $code=strtoupper(trim((string)($data['code']??'')));
        $name=trim((string)($data['name']??''));
        $icon=(string)($data['icon_key']??'slots');
        $sort=(int)($data['sort_order']??100);
        $enabled=!empty($data['enabled'])?1:0;
        $icons=['slots','fish','sport','roulette','live','table','other'];
        if(!preg_match('/^[A-Z0-9_]{2,60}$/',$code)||$name===''||mb_strlen($name)>120||!in_array($icon,$icons,true)||$sort<0||$sort>100000) throw new DomainException('Dados da categoria inválidos.');
        $pdo=Database::connection();
        if($id>0){
            $stmt=$pdo->prepare('UPDATE casino_categories SET name=:name,icon_key=:icon,enabled=:enabled,sort_order=:sort WHERE id=:id');
            $stmt->execute(['name'=>$name,'icon'=>$icon,'enabled'=>$enabled,'sort'=>$sort,'id'=>$id]);
            $check=$pdo->prepare('SELECT id FROM casino_categories WHERE id=:id');$check->execute(['id'=>$id]);if(!$check->fetchColumn())throw new DomainException('Categoria não encontrada.');
        }else{
            $stmt=$pdo->prepare('INSERT INTO casino_categories(code,name,icon_key,enabled,sort_order) VALUES(:code,:name,:icon,:enabled,:sort)');
            $stmt->execute(['code'=>$code,'name'=>$name,'icon'=>$icon,'enabled'=>$enabled,'sort'=>$sort]);$id=(int)$pdo->lastInsertId();
        }
        $stmt=$pdo->prepare('SELECT id,code,name,icon_key,enabled,sort_order FROM casino_categories WHERE id=:id');$stmt->execute(['id'=>$id]);return $stmt->fetch();
    }

    public function deleteCategory(int $id): void
    {
        if($id<=0)throw new DomainException('Categoria inválida.');
        if(!$this->categoriesTableExists())throw new DomainException('Execute as migrations para habilitar categorias de jogos.');
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT code FROM casino_categories WHERE id=:id');$stmt->execute(['id'=>$id]);$code=$stmt->fetchColumn();
        if(!$code)throw new DomainException('Categoria não encontrada.');
        $games=$pdo->prepare('SELECT COUNT(*) FROM casino_games WHERE category=:code');$games->execute(['code'=>$code]);$total=(int)$games->fetchColumn();
        if($total>0)throw new DomainException('Esta categoria possui '.$total.' jogo(s). Transfira os jogos antes de excluir.');
        $delete=$pdo->prepare('DELETE FROM casino_categories WHERE id=:id');$delete->execute(['id'=>$id]);
    }

    public function games(bool $public = false): array
    {
        $pdo=Database::connection();
        $where=$public ? " WHERE g.enabled=1 AND p.enabled=1" : '';
        $order=' ORDER BY g.featured DESC,g.sort_order ASC,g.id DESC';
        $accessSelect=$this->gameAccessColumnExists() ? 'g.access_count' : '0 AS access_count';
        $providerLogoSelect=$this->providerLogoColumnExists() ? 'p.logo_path AS provider_logo' : "'' AS provider_logo";
        $gameApiSelect=$this->gameApiSourceColumnExists() ? 'g.api_source AS api_source' : 'p.api_source AS api_source';
        $sql='SELECT g.id,g.provider_id,g.external_id,g.name,g.category,g.image_url,g.enabled,g.featured,g.sort_order,'.$accessSelect.',p.code AS provider_code,p.name AS provider_name,'.$providerLogoSelect.','.$gameApiSelect.',p.enabled AS provider_enabled,p.mode AS provider_mode FROM casino_games g JOIN casino_providers p ON p.id=g.provider_id'.$where.$order;
        return $pdo->query($sql)->fetchAll();
    }

    public function saveProvider(array $data): array
    {
        $code = strtolower(trim((string)($data['code'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        $mode = (string)($data['mode'] ?? 'DEMO');
        $apiSource = strtoupper(trim((string)($data['api_source'] ?? 'MANUAL')));
        if (!preg_match('/^[a-z0-9_-]{2,60}$/', $code) || mb_strlen($name)>120 || $name==='' || !in_array($mode,['DEMO','PRODUCTION'],true) || !in_array($apiSource,['MANUAL','PLAYFIVER'],true)) throw new DomainException('Dados do provedor inválidos.');
        $id = (int)($data['id'] ?? 0);
        $logo=trim((string)($data['logo_path']??''));
        if($logo!=='' && !preg_match('~^/uploads/providers/[a-f0-9]{32}\.(?:jpg|png|webp)$~i',$logo)) throw new DomainException('Logo inválida. Envie pelo painel.');
        $pdo = Database::connection();
        $hasLogo=$this->providerLogoColumnExists();
        if ($logo!=='' && !$hasLogo) throw new DomainException('Execute as migrations para habilitar logo de provedores.');
        if ($id) {
            $sql=$hasLogo?'UPDATE casino_providers SET name=:name,logo_path=:logo_path,enabled=:enabled,mode=:mode,api_source=:api_source WHERE id=:id':'UPDATE casino_providers SET name=:name,enabled=:enabled,mode=:mode,api_source=:api_source WHERE id=:id';
            $stmt=$pdo->prepare($sql);
            $args=['name'=>$name,'enabled'=>!empty($data['enabled'])?1:0,'mode'=>$mode,'api_source'=>$apiSource,'id'=>$id];if($hasLogo)$args['logo_path']=$logo?:null;$stmt->execute($args);
            if (!$stmt->rowCount() && !$this->providerExists($id)) throw new DomainException('Provedor não encontrado.');
        } else {
            $sql=$hasLogo?'INSERT INTO casino_providers(code,name,logo_path,enabled,mode,api_source) VALUES(:code,:name,:logo_path,:enabled,:mode,:api_source)':'INSERT INTO casino_providers(code,name,enabled,mode,api_source) VALUES(:code,:name,:enabled,:mode,:api_source)';
            $stmt=$pdo->prepare($sql);$args=['code'=>$code,'name'=>$name,'enabled'=>!empty($data['enabled'])?1:0,'mode'=>$mode,'api_source'=>$apiSource];if($hasLogo)$args['logo_path']=$logo?:null;$stmt->execute($args);$id=(int)$pdo->lastInsertId();
        }
        $select=$hasLogo?'SELECT id,code,name,logo_path,enabled,mode,api_source FROM casino_providers WHERE id=:id':"SELECT id,code,name,'' AS logo_path,enabled,mode,api_source FROM casino_providers WHERE id=:id";$stmt=$pdo->prepare($select);$stmt->execute(['id'=>$id]);return $stmt->fetch();
    }

    private function providerLogoColumnExists(): bool
    {
        try {
            $stmt=Database::connection()->query("SHOW COLUMNS FROM casino_providers LIKE 'logo_path'");
            return (bool)$stmt->fetch();
        } catch (\Throwable) { return false; }
    }

    private function gameAccessColumnExists(): bool
    {
        try {
            $stmt=Database::connection()->query("SHOW COLUMNS FROM casino_games LIKE 'access_count'");
            return (bool)$stmt->fetch();
        } catch (\Throwable) { return false; }
    }

    private function gameApiSourceColumnExists(): bool
    {
        try {
            $stmt=Database::connection()->query("SHOW COLUMNS FROM casino_games LIKE 'api_source'");
            return (bool)$stmt->fetch();
        } catch (\Throwable) { return false; }
    }

    private function categoriesTableExists(): bool
    {
        try {
            $stmt=Database::connection()->query("SHOW TABLES LIKE 'casino_categories'");
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) { return false; }
    }

    private function categoryExists(string $code): bool
    {
        $code=strtoupper(trim($code));
        if($this->categoriesTableExists()){
            $stmt=Database::connection()->prepare('SELECT 1 FROM casino_categories WHERE code=:code');$stmt->execute(['code'=>$code]);return (bool)$stmt->fetchColumn();
        }
        return in_array($code,['SLOTS','LIVE','TABLE','OTHER'],true);
    }

    private function providerExists(int $id): bool
    {
        $s=Database::connection()->prepare('SELECT 1 FROM casino_providers WHERE id=:id');$s->execute(['id'=>$id]);return (bool)$s->fetchColumn();
    }

    public function saveGame(array $data): array
    {
        $id=(int)($data['id']??0);$provider=(int)($data['provider_id']??0);
        $external=trim((string)($data['external_id']??''));$name=trim((string)($data['name']??''));
        $category=(string)($data['category']??'SLOTS');$image=trim((string)($data['image_url']??''));$sort=(int)($data['sort_order']??100);$access=max(0,(int)($data['access_count']??0));
        if (!$this->providerExists($provider) || $external==='' || strlen($external)>190 || $name==='' || mb_strlen($name)>190 || !$this->categoryExists($category) || strlen($image)>500 || ($image!=='' && (!filter_var($image,FILTER_VALIDATE_URL) || !str_starts_with($image,'https://'))) || $sort<0 || $sort>100000 || $access>999999999) throw new DomainException('Dados do jogo inválidos. Imagens exigem URL HTTPS.');
        $args=['provider'=>$provider,'external'=>$external,'name'=>$name,'category'=>$category,'image'=>$image?:null,'enabled'=>!empty($data['enabled'])?1:0,'featured'=>!empty($data['featured'])?1:0,'sort'=>$sort,'access'=>$access];
        $pdo=Database::connection();
        $hasAccess=$this->gameAccessColumnExists();
        if ($id) {
            $args['id']=$id;
            $sql=$hasAccess
                ? 'UPDATE casino_games SET provider_id=:provider,external_id=:external,name=:name,category=:category,image_url=:image,enabled=:enabled,featured=:featured,sort_order=:sort,access_count=:access WHERE id=:id'
                : 'UPDATE casino_games SET provider_id=:provider,external_id=:external,name=:name,category=:category,image_url=:image,enabled=:enabled,featured=:featured,sort_order=:sort WHERE id=:id';
            $s=$pdo->prepare($sql);if(!$hasAccess)unset($args['access']);$s->execute($args);
            $s=$pdo->prepare('SELECT id FROM casino_games WHERE id=:id');$s->execute(['id'=>$id]);if (!$s->fetch())throw new DomainException('Jogo não encontrado.');
        } else {
            $sql=$hasAccess
                ? 'INSERT INTO casino_games(provider_id,external_id,name,category,image_url,enabled,featured,sort_order,access_count) VALUES(:provider,:external,:name,:category,:image,:enabled,:featured,:sort,:access)'
                : 'INSERT INTO casino_games(provider_id,external_id,name,category,image_url,enabled,featured,sort_order) VALUES(:provider,:external,:name,:category,:image,:enabled,:featured,:sort)';
            $executeArgs=$args; if(!$hasAccess)unset($executeArgs['access']);
            $s=$pdo->prepare($sql);$s->execute($executeArgs);$id=(int)$pdo->lastInsertId();
        }
        $select=$hasAccess
            ? 'SELECT id,provider_id,external_id,name,category,image_url,enabled,featured,sort_order,access_count FROM casino_games WHERE id=:id'
            : 'SELECT id,provider_id,external_id,name,category,image_url,enabled,featured,sort_order,0 AS access_count FROM casino_games WHERE id=:id';
        $s=$pdo->prepare($select);$s->execute(['id'=>$id]);return $s->fetch();
    }

    /** Remove somente provedores vazios; jogos e apostas nunca são apagados em cascata. */
    public function deleteProvider(int $id): void
    {
        if ($id <= 0) throw new DomainException('Provedor inválido.');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $provider = $pdo->prepare('SELECT id FROM casino_providers WHERE id=:id FOR UPDATE');
            $provider->execute(['id'=>$id]);
            if (!$provider->fetchColumn()) throw new DomainException('Provedor não encontrado.');
            $games = $pdo->prepare('SELECT COUNT(*) FROM casino_games WHERE provider_id=:id');
            $games->execute(['id'=>$id]);
            $total = (int)$games->fetchColumn();
            if ($total > 0) throw new DomainException('Este provedor possui '.$total.' jogo(s). Exclua ou transfira os jogos antes de excluir o provedor.');
            $delete = $pdo->prepare('DELETE FROM casino_providers WHERE id=:id');
            $delete->execute(['id'=>$id]);
            if (!$delete->rowCount()) throw new DomainException('Provedor não encontrado.');
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public function deleteGame(int $id): void
    {
        if ($id <= 0) throw new DomainException('Jogo inválido.');
        $stmt=Database::connection()->prepare('DELETE FROM casino_games WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        if (!$stmt->rowCount()) throw new DomainException('Jogo não encontrado.');
    }
}
