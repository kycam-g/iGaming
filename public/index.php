<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Audit\AuditLogger;
use App\Core\Exceptions\AuthenticationException;
use App\Core\Exceptions\ConflictException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Router;
use App\Core\Security\SecretBox;
use App\Core\Support\Env;
use App\Modules\Admin\AdminAuthService;
use App\Modules\Admin\AdminDashboardService;
use App\Modules\Admin\AdminFinanceService;
use App\Modules\Admin\AdminUserService;
use App\Modules\Analytics\AnalyticsService;
use App\Modules\Auth\AuthService;
use App\Modules\Casino\CasinoCatalogService;
use App\Modules\Casino\PlayfiverConfigService;
use App\Modules\Casino\PlayfiverGameService;
use App\Modules\Payments\GatewayConfigRepository;
use App\Modules\Payments\GatewayRegistry;
use App\Modules\Payments\PaymentService;
use App\Modules\Users\UserRepository;
use App\Modules\Wallet\WalletService;
use App\Modules\Platform\PlatformService;
use App\Modules\Platform\PromotionConfigService;
use App\Modules\Platform\PromotionRedemptionService;
use App\Modules\Platform\VipBenefitService;

Env::load(dirname(__DIR__) . '/.env');
$router = new Router();
$users = new UserRepository();
$wallet = new WalletService();
$audit = new AuditLogger();
$auth = new AuthService($users, $wallet, $audit);
$gatewayConfigs = new GatewayConfigRepository(new SecretBox());
$payments = new PaymentService(new GatewayRegistry(), $gatewayConfigs, $wallet);
$adminAuth = new AdminAuthService();
$adminDashboard = new AdminDashboardService();
$adminFinance = new AdminFinanceService();
$adminUsers = new AdminUserService($wallet);
$analytics = new AnalyticsService();
$casino = new CasinoCatalogService();
$playfiverConfig = new PlayfiverConfigService(new SecretBox());
$playfiverGames = new PlayfiverGameService($playfiverConfig, $wallet, $users);
$platform = new PlatformService();
$promotionConfigs = new PromotionConfigService();
$promotionRedemptions = new PromotionRedemptionService();
$vipBenefits = new VipBenefitService();

$router->get('/health', fn() => ['status' => 'ok', 'service' => 'igaming-php']);

$frontend = function (Request $request) use ($analytics,$platform): never {
    $settings=$platform->settings();
    if (($settings['maintenance']??'0')==='1') Response::html('<html lang="pt-BR"><meta name="viewport" content="width=device-width"><title>Manutenção</title><body style="background:#121010;color:#fff;font:18px sans-serif;display:grid;place-items:center;min-height:90vh"><main><h1>Voltamos em breve</h1><p>Estamos realizando melhorias na plataforma.</p></main></body></html>',503);
    if (isset($request->query['ref'])) $platform->recordReferral(substr((string)$request->query['ref'],0,32),$request->clientIp());
    $analytics->recordPublicVisit($request);
    ob_start(); require __DIR__ . '/app.php'; Response::html((string) ob_get_clean());
};
$adminFrontend = function (): never {
    ob_start(); require __DIR__ . '/admin.php'; Response::html((string) ob_get_clean());
};

$router->get('/', $frontend);
$router->get('/cassino', $frontend);
$router->get('/promocoes', $frontend);
$router->get('/carteira', $frontend);
$router->get('/admin', $adminFrontend);
$router->get('/api/platform/public', fn() => ['settings'=>array_intersect_key($platform->settings(),array_flip(['site_name','accent_color','footer_text','footer_about','support_email','contact_phone','social_whatsapp','social_telegram','social_instagram','social_facebook','logo_path','favicon_path'])),'banners'=>$platform->list('banners',true),'promotions'=>$platform->list('promotions',true),'announcements'=>$platform->announcements(true)]);
$router->get('/admin/api/platform', function(Request $request) use($adminAuth,$platform){if(!$adminAuth->authenticate($request->bearerToken()))Response::json(['error'=>'unauthorized'],401);return ['settings'=>$platform->settings(),'banners'=>$platform->list('banners'),'promotions'=>$platform->list('promotions'),'affiliates'=>$platform->affiliateReport(),'announcements'=>$platform->announcements()];});
$router->post('/admin/api/platform/settings',function(Request $request) use($adminAuth,$platform,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$settings=$platform->saveSettings($request->body);$audit->record('ADMIN',(string)$admin['id'],'platform.settings_updated','platform','settings',$request->clientIp(),['keys'=>array_keys($request->body)]);return ['settings'=>$settings];});
foreach(['banners','promotions','affiliates'] as $kind){
 $router->post('/admin/api/platform/'.$kind.'/save',function(Request $request) use($adminAuth,$platform,$audit,$kind){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$result=$platform->save($kind,$request->body);$audit->record('ADMIN',(string)$admin['id'],'platform.'.$kind.'_saved',$kind,(string)$result['id'],$request->clientIp(),['id'=>$result['id']]);return $result;});
}
$router->post('/admin/api/platform/promotions/delete',function(Request $request) use($adminAuth,$platform,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$id=(int)($request->body['id']??0);$platform->delete('promotions',$id);$audit->record('ADMIN',(string)$admin['id'],'platform.promotions_deleted','promotions',(string)$id,$request->clientIp(),['id'=>$id]);return ['ok'=>true];});
$router->post('/admin/api/platform/banners/delete',function(Request $request) use($adminAuth,$platform,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$id=(int)($request->body['id']??0);$platform->delete('banners',$id);$audit->record('ADMIN',(string)$admin['id'],'platform.banners_deleted','banners',(string)$id,$request->clientIp(),['id'=>$id]);return ['ok'=>true];});
$router->post('/admin/api/platform/announcements/save',function(Request $request) use($adminAuth,$platform,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$result=$platform->saveAnnouncement($request->body);$audit->record('ADMIN',(string)$admin['id'],'platform.announcement_saved','platform_announcements',(string)$result['id'],$request->clientIp());return $result;});
$router->post('/admin/api/platform/announcements/delete',function(Request $request) use($adminAuth,$platform,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$id=(int)($request->body['id']??0);$platform->deleteAnnouncement($id);$audit->record('ADMIN',(string)$admin['id'],'platform.announcement_deleted','platform_announcements',(string)$id,$request->clientIp());return ['ok'=>true];});
// Configuração administrativa isolada dos fluxos de carteira / pagamentos.
$router->get('/api/promotions/configs',fn() => ['items'=>$promotionConfigs->publicList()]);
$router->get('/api/promotions/status',function(Request $request)use($auth,$promotionRedemptions){$user=$auth->authenticate($request->bearerToken());if(!$user)Response::json(['error'=>'unauthorized'],401);return $promotionRedemptions->status((string)$user['id']);});
$router->get('/api/promotions/vip/status',function(Request $request)use($auth,$promotionRedemptions){$user=$auth->authenticate($request->bearerToken());if(!$user)Response::json(['error'=>'unauthorized'],401);return $promotionRedemptions->vipStatus((string)$user['id']);});
$router->get('/api/promotions/vip/benefits',function(Request $request)use($auth,$vipBenefits){$user=$auth->authenticate($request->bearerToken());if(!$user)Response::json(['error'=>'unauthorized'],401);return $vipBenefits->status((string)$user['id']);});
$router->post('/api/promotions/vip/benefits/redeem',function(Request $request)use($auth,$vipBenefits){$user=$auth->authenticate($request->bearerToken());if(!$user)Response::json(['error'=>'unauthorized'],401);return ['award'=>$vipBenefits->claim((string)$user['id'],(string)($request->body['kind']??''))];});
$router->get('/admin/api/promotions/vip/settings',function(Request $request)use($adminAuth,$vipBenefits){if(!$adminAuth->authenticate($request->bearerToken()))Response::json(['error'=>'unauthorized'],401);return ['settings'=>$vipBenefits->adminSettings()];});
$router->post('/admin/api/promotions/vip/settings',function(Request $request)use($adminAuth,$vipBenefits,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$settings=$vipBenefits->saveSettings($request->body);$audit->record('ADMIN',(string)$admin['id'],'vip.settings_updated','vip','settings',$request->clientIp(),$settings);return ['settings'=>$settings];});
$router->get('/admin/api/promotions/vip/reviews',function(Request $request)use($adminAuth,$vipBenefits){if(!$adminAuth->authenticate($request->bearerToken()))Response::json(['error'=>'unauthorized'],401);return ['items'=>$vipBenefits->history()];});
$router->post('/api/promotions/vip/redeem',function(Request $request)use($auth,$promotionRedemptions){$user=$auth->authenticate($request->bearerToken());if(!$user)Response::json(['error'=>'unauthorized'],401);return ['award'=>$promotionRedemptions->claimVip((string)$user['id'],(int)($request->body['campaign_id']??0))];});
$router->post('/api/promotions/coupons/redeem',function(Request $request)use($auth,$promotionRedemptions){$user=$auth->authenticate($request->bearerToken());if(!$user)Response::json(['error'=>'unauthorized'],401);return ['award'=>$promotionRedemptions->claimCoupon((string)$user['id'],(string)($request->body['code']??''))];});
$router->post('/api/promotions/checkin/redeem',function(Request $request)use($auth,$promotionRedemptions){$user=$auth->authenticate($request->bearerToken());if(!$user)Response::json(['error'=>'unauthorized'],401);return ['award'=>$promotionRedemptions->claimCheckin((string)$user['id'])];});
$router->get('/admin/api/promotions/redemptions',function(Request $request)use($adminAuth,$promotionRedemptions){if(!$adminAuth->authenticate($request->bearerToken()))Response::json(['error'=>'unauthorized'],401);return ['items'=>$promotionRedemptions->adminHistory((string)($request->query['type']??''))];});

$router->get('/admin/api/promotion-configs',function(Request $request) use($adminAuth,$promotionConfigs){if(!$adminAuth->authenticate($request->bearerToken()))Response::json(['error'=>'unauthorized'],401);return ['items'=>$promotionConfigs->list((string)($request->query['type']??''))];});
$router->post('/admin/api/promotion-configs/save',function(Request $request) use($adminAuth,$promotionConfigs,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$type=(string)($request->body['type']??'');$result=$promotionConfigs->save($type,$request->body);$audit->record('ADMIN',(string)$admin['id'],'promotion.configuration_saved',$type,(string)$result['id'],$request->clientIp());return $result;});
$router->post('/admin/api/promotion-configs/delete',function(Request $request) use($adminAuth,$promotionConfigs,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$type=(string)($request->body['type']??'');$id=(int)($request->body['id']??0);$promotionConfigs->delete($type,$id);$audit->record('ADMIN',(string)$admin['id'],'promotion.configuration_deleted',$type,(string)$id,$request->clientIp());return ['ok'=>true];});
$router->get('/admin/api/platform/audit',function(Request $request) use($adminAuth,$platform){if(!$adminAuth->authenticate($request->bearerToken()))Response::json(['error'=>'unauthorized'],401);return ['items'=>$platform->audits($request->query)];});
$router->post('/admin/api/platform/upload',function(Request $request) use($adminAuth,$audit){$admin=$adminAuth->authenticate($request->bearerToken());if(!$admin)Response::json(['error'=>'unauthorized'],401);$kind=(string)($_POST['kind']??'');if(!in_array($kind,['banners','promotions','identity','providers'],true))Response::json(['error'=>'invalid_kind'],422);$file=$_FILES['image']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']>2097152)Response::json(['error'=>'invalid_file'],422);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>null};if(!$ext||!getimagesize($file['tmp_name']))Response::json(['error'=>'invalid_image'],422);$dir=__DIR__.'/uploads/'.$kind;if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))Response::json(['error'=>'upload_failed'],500);$name=bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))Response::json(['error'=>'upload_failed'],500);$audit->record('ADMIN',(string)$admin['id'],'platform.image_uploaded',$kind,$name,$request->clientIp());return ['path'=>'/uploads/'.$kind.'/'.$name];});


$router->post('/api/auth/register', function (Request $request) use ($auth) {
    return $auth->register((string)($request->body['cpf']??''),(string)($request->body['phone']??''),(string)($request->body['password']??''),$request->clientIp());
});
$router->post('/api/auth/login', function (Request $request) use ($auth) {
    return $auth->login((string)($request->body['identifier']??$request->body['email']??''),(string)($request->body['password']??''),$request->clientIp());
});
$router->post('/api/auth/refresh', fn(Request $request) => $auth->refresh($request->bearerToken(), $request->clientIp()));
$router->post('/api/auth/logout', function (Request $request) use ($auth) { $auth->logout($request->bearerToken(),$request->clientIp()); return ['success'=>true]; });
$router->get('/api/me', function (Request $request) use ($auth) { $user=$auth->authenticate($request->bearerToken()); if(!$user)Response::json(['error'=>'unauthorized'],401); return ['user'=>$user]; });
$router->get('/api/wallet', function (Request $request) use ($auth,$wallet) { $user=$auth->authenticate($request->bearerToken()); if(!$user)Response::json(['error'=>'unauthorized'],401); return ['accounts'=>$wallet->balance($user['id'])]; });
$router->get('/api/wallet/transactions', function (Request $request) use ($auth,$wallet) { $user=$auth->authenticate($request->bearerToken()); if(!$user)Response::json(['error'=>'unauthorized'],401); return ['transactions'=>$wallet->transactions($user['id'],isset($request->query['limit'])?(int)$request->query['limit']:50)]; });

$router->get('/admin/api/casino/playfiver', function (Request $request) use ($adminAuth,$playfiverConfig) { if (!$adminAuth->authenticate($request->bearerToken())) Response::json(['error'=>'unauthorized'],401); return ['config'=>$playfiverConfig->publicConfig()]; });
$router->post('/admin/api/casino/playfiver', function (Request $request) use ($adminAuth,$playfiverConfig,$audit) { $admin=$adminAuth->authenticate($request->bearerToken()); if (!$admin) Response::json(['error'=>'unauthorized'],401); $result=$playfiverConfig->save($request->body); $audit->record('ADMIN',(string)$admin['id'],'casino.playfiver_credentials_saved','casino_api_credentials','playfiver',$request->clientIp(),['configured'=>$result['configured'],'enabled'=>(bool)$result['enabled']]); return ['config'=>$result]; });
$router->post('/api/casino/playfiver/launch', function (Request $request) use ($auth,$playfiverGames) {
    $user=$auth->authenticate($request->bearerToken());
    if(!$user) Response::json(['error'=>'unauthorized'],401);
    return $playfiverGames->launch($user,(int)($request->body['game_id']??0));
});
$playfiverCallback = function (Request $request) use ($playfiverGames) {
    try {
        return $playfiverGames->handleCallback($request->body);
    } catch (DomainException $error) {
        $message=$error->getMessage();
        $status=match($message){'INVALID_CREDENTIALS'=>401,'USER_NOT_FOUND'=>404,'PLAYFIVER_DISABLED'=>503,default=>400};
        Response::json(['msg'=>$message],$status);
    }
};
$router->post('/api/webhooks/casino/playfiver', $playfiverCallback);
$router->post('/playfiver/webhook', $playfiverCallback);
$router->get('/api/casino/providers', fn() => ['providers'=>$casino->publicProviders()]);
$router->get('/api/casino/categories', fn() => ['categories'=>$casino->categories(true)]);
$router->get('/api/casino/games', fn() => ['games'=>$casino->games(true)]);
$router->get('/admin/api/casino/catalog', function (Request $request) use ($adminAuth,$casino) { if (!$adminAuth->authenticate($request->bearerToken())) Response::json(['error'=>'unauthorized'],401); return ['providers'=>$casino->providers(),'categories'=>$casino->categories(),'games'=>$casino->games()]; });
$router->post('/admin/api/casino/providers/save', function (Request $request) use ($adminAuth,$casino,$audit) { $admin=$adminAuth->authenticate($request->bearerToken()); if (!$admin) Response::json(['error'=>'unauthorized'],401); $provider=$casino->saveProvider($request->body); $audit->record('ADMIN',(string)$admin['id'],'casino.provider_saved','casino_provider',(string)$provider['id'],$request->clientIp(),['code'=>$provider['code'],'enabled'=>$provider['enabled'],'mode'=>$provider['mode']]); return ['provider'=>$provider]; });
$router->post('/admin/api/casino/providers/delete', function (Request $request) use ($adminAuth,$casino,$audit) { $admin=$adminAuth->authenticate($request->bearerToken()); if (!$admin) Response::json(['error'=>'unauthorized'],401); $id=(int)($request->body['id']??0); $casino->deleteProvider($id); $audit->record('ADMIN',(string)$admin['id'],'casino.provider_deleted','casino_provider',(string)$id,$request->clientIp(),['id'=>$id]); return ['ok'=>true]; });
$router->post('/admin/api/casino/categories/save', function (Request $request) use ($adminAuth,$casino,$audit) { $admin=$adminAuth->authenticate($request->bearerToken()); if (!$admin) Response::json(['error'=>'unauthorized'],401); $category=$casino->saveCategory($request->body); $audit->record('ADMIN',(string)$admin['id'],'casino.category_saved','casino_category',(string)$category['id'],$request->clientIp(),['code'=>$category['code'],'enabled'=>$category['enabled']]); return ['category'=>$category]; });
$router->post('/admin/api/casino/categories/delete', function (Request $request) use ($adminAuth,$casino,$audit) { $admin=$adminAuth->authenticate($request->bearerToken()); if (!$admin) Response::json(['error'=>'unauthorized'],401); $id=(int)($request->body['id']??0); $casino->deleteCategory($id); $audit->record('ADMIN',(string)$admin['id'],'casino.category_deleted','casino_category',(string)$id,$request->clientIp(),['id'=>$id]); return ['ok'=>true]; });
$router->post('/admin/api/casino/games/save', function (Request $request) use ($adminAuth,$casino,$audit) { $admin=$adminAuth->authenticate($request->bearerToken()); if (!$admin) Response::json(['error'=>'unauthorized'],401); $game=$casino->saveGame($request->body); $audit->record('ADMIN',(string)$admin['id'],'casino.game_saved','casino_game',(string)$game['id'],$request->clientIp(),['provider_id'=>$game['provider_id'],'enabled'=>$game['enabled'],'featured'=>$game['featured']]); return ['game'=>$game]; });

$router->post('/admin/api/casino/games/delete', function (Request $request) use ($adminAuth,$casino,$audit) { $admin=$adminAuth->authenticate($request->bearerToken()); if (!$admin) Response::json(['error'=>'unauthorized'],401); $id=(int)($request->body['id']??0); $casino->deleteGame($id); $audit->record('ADMIN',(string)$admin['id'],'casino.game_deleted','casino_game',(string)$id,$request->clientIp(),['id'=>$id]); return ['ok'=>true]; });
$router->get('/api/payments/gateways', fn(Request $request) => ['gateways'=>$payments->availableGateways('DEPOSIT')]);
$router->get('/api/payments', function (Request $request) use ($auth,$payments) { $user=$auth->authenticate($request->bearerToken()); if(!$user)Response::json(['error'=>'unauthorized'],401); return ['payments'=>$payments->listForUser($user['id'],isset($request->query['limit'])?(int)$request->query['limit']:30)]; });
$router->get('/api/payments/status', function (Request $request) use ($auth,$payments) { $user=$auth->authenticate($request->bearerToken()); if(!$user)Response::json(['error'=>'unauthorized'],401); return ['payment'=>$payments->getForUser($user['id'],(string)($request->query['id']??''))]; });
$router->post('/api/payments/deposits', function (Request $request) use ($auth,$payments) { $user=$auth->authenticate($request->bearerToken()); if(!$user)Response::json(['error'=>'unauthorized'],401); return ['payment'=>$payments->createDeposit($user['id'],isset($request->body['gateway_code'])?(string)$request->body['gateway_code']:null,(int)($request->body['amount_minor']??0),(string)($request->body['idempotency_key']??''))]; });
$router->post('/api/payments/sandbox/confirm', function (Request $request) use ($auth,$payments) { $user=$auth->authenticate($request->bearerToken()); if(!$user)Response::json(['error'=>'unauthorized'],401); return ['payment'=>$payments->confirmSandboxDeposit($user['id'],(string)($request->body['payment_id']??''))]; });
$router->post('/api/webhooks/payments/pixup', fn(Request $request) => $payments->processWebhook('pixup',$request->rawBody,$request->headers,$request->body));

$router->post('/admin/api/login', fn(Request $request) => $adminAuth->login((string)($request->body['email']??''),(string)($request->body['password']??'')));
$router->get('/admin/api/me', function (Request $request) use ($adminAuth) { $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401); return ['admin'=>$admin]; });
$router->get('/admin/api/dashboard', function (Request $request) use ($adminAuth,$adminDashboard) { $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401); return $adminDashboard->data(); });
$router->get('/admin/api/finance/deposits', function (Request $request) use ($adminAuth,$adminFinance) { $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401); return $adminFinance->deposits($request->query); });
$router->get('/admin/api/finance/reconciliation', function (Request $request) use ($adminAuth,$adminFinance) { $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401); return $adminFinance->reconciliation(); });
$router->get('/admin/api/finance/deposits/detail', function (Request $request) use ($adminAuth,$adminFinance) { $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401); return $adminFinance->deposit((string)($request->query['id']??'')); });
$router->get('/admin/api/users', function (Request $request) use ($adminAuth,$adminUsers) {
    $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401);
    return $adminUsers->list($request->query);
});
$router->get('/admin/api/users/detail', function (Request $request) use ($adminAuth,$adminUsers) {
    $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401);
    return $adminUsers->detail((string)($request->query['id']??''));
});
$router->post('/admin/api/users/status', function (Request $request) use ($adminAuth,$adminUsers,$audit) {
    $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401);
    $result=$adminUsers->updateStatus((string)($request->body['id']??''),(string)($request->body['status']??''));
    $audit->record('ADMIN',(string)$admin['id'],'user.status_updated','user',(string)$result['id'],$request->clientIp(),['old_status'=>$result['old_status'],'status'=>$result['status']]);
    return ['user'=>$result];
});

$router->post('/admin/api/users/profile', function (Request $request) use ($adminAuth,$adminUsers,$audit) {
    $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401);
    $result=$adminUsers->updateProfile((string)($request->body['id']??''),(string)($request->body['username']??''),(string)($request->body['email']??''),(string)($request->body['cpf']??''),(string)($request->body['phone']??''),(string)($request->body['status']??''));
    $audit->record('ADMIN',(string)$admin['id'],'user.profile_updated','user',(string)$result['id'],$request->clientIp(),['public_id'=>$result['public_id'],'username'=>$result['username'],'email'=>$result['email'],'status'=>$result['status']]);
    return ['user'=>$result];
});
$router->post('/admin/api/users/password', function (Request $request) use ($adminAuth,$adminUsers,$audit) {
    $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401);
    $result=$adminUsers->resetPassword((string)($request->body['id']??''),(string)($request->body['password']??''));
    $audit->record('ADMIN',(string)$admin['id'],'user.password_reset','user',(string)$result['id'],$request->clientIp(),['public_id'=>$result['public_id']]);
    return ['user'=>$result];
});
$router->post('/admin/api/users/balance-adjustment', function (Request $request) use ($adminAuth,$adminUsers,$audit) {
    $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401);
    $result=$adminUsers->adjustBalance((string)($request->body['id']??''),(string)$admin['id'],(string)($request->body['account_type']??''),(string)($request->body['direction']??''),(int)($request->body['amount_minor']??0),(string)($request->body['reason']??''));
    $audit->record('ADMIN',(string)$admin['id'],'user.balance_adjusted','user',(string)$result['id'],$request->clientIp(),['public_id'=>$result['public_id'],'account_type'=>$result['account_type'],'direction'=>$result['direction'],'amount_minor'=>$result['amount_minor'],'reason'=>$result['reason']]);
    return ['adjustment'=>$result];
});
$router->post('/admin/api/logout', function (Request $request) use ($adminAuth) { $adminAuth->logout($request->bearerToken()); return ['success'=>true]; });
$router->get('/admin/api/gateways', function (Request $request) use ($adminAuth,$gatewayConfigs) { $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401); return ['gateways'=>$gatewayConfigs->adminList()]; });
$router->post('/admin/api/gateways/save', function (Request $request) use ($adminAuth,$gatewayConfigs,$audit) {
    $admin=$adminAuth->authenticate($request->bearerToken()); if(!$admin)Response::json(['error'=>'unauthorized'],401);
    $gateway=$gatewayConfigs->save($request->body);
    $audit->record('ADMIN',(string)$admin['id'],'payment_gateway.updated','payment_gateway',(string)$gateway['code'],$request->clientIp(),['enabled'=>(bool)$gateway['enabled'],'deposit_enabled'=>(bool)$gateway['deposit_enabled'],'withdrawal_enabled'=>(bool)$gateway['withdrawal_enabled']]);
    return ['gateway'=>$gateway];
});

try {
    $router->dispatch(Request::capture());
} catch (AuthenticationException $e) {
    Response::json(['error'=>'unauthorized','message'=>$e->getMessage()],401);
} catch (ConflictException $e) {
    Response::json(['error'=>'conflict','message'=>$e->getMessage()],409);
} catch (DomainException $e) {
    Response::json(['error'=>'domain_error','message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    error_log($e->__toString());
    Response::json(['error'=>'server_error','message'=>Env::bool('APP_DEBUG')?$e->getMessage():'Internal server error'],500);
}
