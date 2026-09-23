<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Database\Database;
use PDO;

/** Relatórios de leitura. Nenhuma operação aqui concede, altera ou cancela crédito. */
final class PromotionOversightService
{
    public function overview(): array
    {
        $db=Database::connection();
        $campaigns=$db->query("SELECT type,COUNT(*) AS total,SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END) AS enabled FROM promotion_configurations GROUP BY type ORDER BY type")->fetchAll();
        $redemptions=$db->query("SELECT promotion_type, status, COUNT(*) AS total, COALESCE(SUM(amount_minor),0) AS amount_minor FROM promotion_redemptions GROUP BY promotion_type,status ORDER BY promotion_type,status")->fetchAll();
        $recent=$db->query("SELECT r.id,r.promotion_type,r.status,r.amount_minor,r.wager_required_minor,r.wager_progress_minor,r.created_at,c.title,u.public_id FROM promotion_redemptions r JOIN promotion_configurations c ON c.id=r.campaign_id JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC,r.id DESC LIMIT 40")->fetchAll();
        return ['campaigns'=>$campaigns,'redemptions'=>$redemptions,'recent'=>$recent,'scope'=>'LOCAL_ONLY','note'=>'Valores históricos registrados; não são caixa disponível nem prova de liquidação externa.'];
    }

    public function audit(): array
    {
        $db=Database::connection();
        $checks=[];
        $checks['wallet_ledger_mismatch']=$db->query("SELECT wa.id AS account_id,w.user_id,wa.type,wa.balance_minor,COALESCE(x.net_minor,0) AS ledger_minor FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id LEFT JOIN (SELECT account_id,COALESCE(SUM(CASE WHEN direction='CREDIT' THEN CAST(amount_minor AS SIGNED) ELSE -CAST(amount_minor AS SIGNED) END),0) AS net_minor FROM ledger_entries GROUP BY account_id) x ON x.account_id=wa.id WHERE CAST(wa.balance_minor AS SIGNED)<>COALESCE(x.net_minor,0) ORDER BY wa.id LIMIT 100")->fetchAll();
        $checks['invalid_ledger_transition']=$db->query("SELECT id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor FROM ledger_entries WHERE (direction='CREDIT' AND CAST(balance_after_minor AS SIGNED)-CAST(balance_before_minor AS SIGNED)<>CAST(amount_minor AS SIGNED)) OR (direction='DEBIT' AND CAST(balance_before_minor AS SIGNED)-CAST(balance_after_minor AS SIGNED)<>CAST(amount_minor AS SIGNED)) ORDER BY created_at DESC,id DESC LIMIT 100")->fetchAll();
        $checks['redemption_missing_transaction']=$db->query("SELECT r.id,r.user_id,r.promotion_type,r.status,r.amount_minor,r.financial_transaction_id FROM promotion_redemptions r LEFT JOIN financial_transactions ft ON ft.id=r.financial_transaction_id WHERE (r.amount_minor>0 AND r.financial_transaction_id IS NULL) OR (r.financial_transaction_id IS NOT NULL AND (ft.id IS NULL OR ft.status<>'COMPLETED')) ORDER BY r.created_at DESC,r.id DESC LIMIT 100")->fetchAll();
        $checks['invalid_rollover_progress']=$db->query("SELECT id,user_id,promotion_type,status,amount_minor,wager_required_minor,wager_progress_minor FROM promotion_redemptions WHERE wager_progress_minor>wager_required_minor OR (status='COMPLETED' AND wager_required_minor>0 AND wager_progress_minor<wager_required_minor) ORDER BY created_at DESC,id DESC LIMIT 100")->fetchAll();
        $checks['overallocated_bet']=$db->query("SELECT le.id AS ledger_id,le.amount_minor AS bet_minor,COALESCE(SUM(a.amount_minor),0) AS allocated_minor FROM promotion_wager_allocations a JOIN ledger_entries le ON le.id=a.ledger_entry_id GROUP BY le.id,le.amount_minor HAVING SUM(a.amount_minor)>le.amount_minor ORDER BY allocated_minor DESC LIMIT 100")->fetchAll();
        $checks['rollover_allocation_mismatch']=$db->query("SELECT r.id,r.user_id,r.promotion_type,r.wager_progress_minor,COALESCE(SUM(a.amount_minor),0) AS allocated_minor FROM promotion_redemptions r LEFT JOIN promotion_wager_allocations a ON a.redemption_id=r.id WHERE r.wager_required_minor>0 GROUP BY r.id,r.user_id,r.promotion_type,r.wager_progress_minor HAVING r.wager_progress_minor<>COALESCE(SUM(a.amount_minor),0) ORDER BY r.id LIMIT 100")->fetchAll();
        $checks['duplicate_promotion_credit_reference']=$db->query("SELECT type,reference_id,COUNT(*) AS occurrences FROM financial_transactions WHERE reference_type='PROMOTION' AND type LIKE 'PROMOTION_%' AND type<>'PROMOTION_UNLOCK' GROUP BY type,reference_id HAVING COUNT(*)>1 ORDER BY occurrences DESC LIMIT 100")->fetchAll();
        $checks['duplicate_redemption_transactions']=$db->query("SELECT financial_transaction_id,COUNT(*) AS occurrences FROM promotion_redemptions WHERE financial_transaction_id IS NOT NULL GROUP BY financial_transaction_id HAVING COUNT(*)>1 ORDER BY occurrences DESC LIMIT 100")->fetchAll();
        $summary=[];foreach($checks as $name=>$rows)$summary[$name]=['sample_count'=>count($rows),'truncated'=>count($rows)===100];
        return ['mode'=>'READ_ONLY','external_gateway_verified'=>false,'checks'=>$checks,'summary'=>$summary,'note'=>'Diagnóstico local e amostras de até 100 registros por tipo. Nenhum valor é corrigido automaticamente; executar reconciliação externa, testes concorrentes e revisão humana antes de produção.'];
    }
}
