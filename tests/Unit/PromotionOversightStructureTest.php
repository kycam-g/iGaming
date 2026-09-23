<?php
// Verificações estáticas sem MySQL para os relatórios V23; não substituem integração.
declare(strict_types=1);
$root=dirname(__DIR__,2);
$service=file_get_contents($root.'/src/Modules/Admin/PromotionOversightService.php');
$routes=file_get_contents($root.'/public/index.php');
$admin=file_get_contents($root.'/public/admin.php');
$js=file_get_contents($root.'/public/admin-assets/admin.js');
foreach (['wallet_ledger_mismatch','invalid_ledger_transition','redemption_missing_transaction','invalid_rollover_progress','duplicate_redemption_transactions','overallocated_bet','rollover_allocation_mismatch','duplicate_promotion_credit_reference'] as $check) {
    if (!str_contains($service,$check)) throw new RuntimeException('Falta a verificação: '.$check);
}
foreach (['/admin/api/promotions/overview','/admin/api/promotions/financial-audit'] as $route) {
    $start=strpos($routes,'$router->get(\'' . $route . '\'');
    if ($start===false || !str_contains(substr($routes,$start,330),'$adminAuth->authenticate')) throw new RuntimeException('Endpoint sem proteção Admin: '.$route);
}
if (!str_contains($service,"'mode'=>'READ_ONLY'") || !str_contains($service,"'external_gateway_verified'=>false")) throw new RuntimeException('Diagnóstico não identificado como leitura local.');
if (!str_contains($admin,'id="mz-promo-audit-refresh"') || !str_contains($js,"/admin/api/promotions/financial-audit")) throw new RuntimeException('Painel de auditoria indisponível.');
if (preg_match('/\$db->(?:exec|prepare)\s*\(\s*["\']\s*(?:INSERT|UPDATE|DELETE|TRUNCATE|ALTER|DROP)\b/i',$service)) throw new RuntimeException('Serviço de leitura contém operação de escrita.');
echo "OK: relatórios protegidos, 8 verificações locais e painel somente leitura. Teste MySQL ainda pendente.\n";
