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
        $configurations=$db->query("SELECT id,type,title,enabled,created_at,updated_at FROM promotion_configurations WHERE type<>'weekly' ORDER BY type,id")->fetchAll();
        $metrics=$db->query("SELECT COUNT(*) AS redemptions,COUNT(DISTINCT user_id) AS players,COALESCE(SUM(amount_minor),0) AS nominal_minor,COALESCE(SUM(CASE WHEN status='COMPLETED' THEN amount_minor ELSE 0 END),0) AS completed_minor FROM promotion_redemptions")->fetch()?:[];
        $metrics['players_30d']=(int)$db->query("SELECT COUNT(DISTINCT user_id) FROM promotion_redemptions WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
        $metrics['available_vip']=(int)$db->query("SELECT COUNT(*) FROM vip_period_awards WHERE state='AVAILABLE'")->fetchColumn();
        $metrics['available_rescue']=(int)$db->query("SELECT COUNT(*) FROM rescue_daily_awards WHERE state='AVAILABLE'")->fetchColumn();
        return ['campaigns'=>$campaigns,'redemptions'=>$redemptions,'recent'=>$recent,'configurations'=>$configurations,'metrics'=>$metrics,'scope'=>'LOCAL_ONLY','note'=>'Valores históricos registrados; não são caixa disponível nem prova de liquidação externa.'];
    }

    public function diagnostic(string $identifier): array
    {
        $db=Database::connection();$identifier=trim($identifier);
        if($identifier==='')return ['found'=>false,'message'=>'Informe o ID público ou UUID do jogador.'];
        $sql=ctype_digit($identifier)?'SELECT id,public_id,username,status FROM users WHERE public_id=? LIMIT 1':'SELECT id,public_id,username,status FROM users WHERE id=? LIMIT 1';
        $stmt=$db->prepare($sql);$stmt->execute([$identifier]);$user=$stmt->fetch();
        if(!$user)return ['found'=>false,'message'=>'Jogador não encontrado.'];
        $uid=(string)$user['id'];$modules=[];
        $stmt=$db->prepare("SELECT COALESCE(SUM(total_spins-used_spins),0) FROM roulette_spin_credits WHERE user_id=?");$stmt->execute([$uid]);$roulette=(int)$stmt->fetchColumn();
        $modules[]=['id'=>'roulette','title'=>'Giro da Sorte','available'=>$roulette>0,'detail'=>$roulette>0?$roulette.' giro(s) disponível(is)':'Sem giros emitidos ou todos já utilizados'];
        $stmt=$db->prepare("SELECT c.config,COALESCE((SELECT COUNT(*) FROM lottery_spins s WHERE s.user_id=? AND s.campaign_id=c.id AND s.day_key=CURDATE()),0) used FROM promotion_configurations c WHERE c.type='lottery' AND c.enabled=1 ORDER BY c.id DESC LIMIT 1");$stmt->execute([$uid]);$lot=$stmt->fetch();$lottery=0;if($lot){$cfg=json_decode((string)$lot['config'],true)?:[];$lottery=max(0,(int)($cfg['spins_per_day']??0)-(int)$lot['used']);}
        $modules[]=['id'=>'lottery','title'=>'Sorteio de Cartas','available'=>$lottery>0,'detail'=>$lot?$lottery.' rodada(s) restante(s) hoje':'Campanha desativada'];
        $stmt=$db->prepare("SELECT s.progress_minor,c.config,(SELECT COUNT(*) FROM cashwheel_spins x WHERE x.session_id=s.id AND x.day_key=CURDATE()) used FROM cashwheel_sessions s JOIN promotion_configurations c ON c.id=s.campaign_id AND c.type='cashwheel' AND c.enabled=1 WHERE s.user_id=? AND s.status='ACTIVE' ORDER BY s.id DESC LIMIT 1");$stmt->execute([$uid]);$cash=$stmt->fetch();$cashAvailable=false;$cashDetail='Sem sessão ativa';if($cash){$cfg=json_decode((string)$cash['config'],true)?:[];$target=(int)($cfg['target_cents']??0);$spins=max(0,(int)($cfg['free_spins_per_day']??0)-(int)$cash['used']);$claim=$target>0&&(int)$cash['progress_minor']>=$target;$cashAvailable=$claim||$spins>0;$cashDetail=$claim?'Meta concluída; resgate liberado':$spins.' rodada(s) restante(s) hoje';}
        $modules[]=['id'=>'cashwheel','title'=>'Roleta de Saque','available'=>$cashAvailable,'detail'=>$cashDetail];
        $stmt=$db->prepare("SELECT COUNT(*) FROM vip_period_awards WHERE user_id=? AND state='AVAILABLE'");$stmt->execute([$uid]);$vip=(int)$stmt->fetchColumn();
        $modules[]=['id'=>'vip','title'=>'Clube VIP','available'=>$vip>0,'detail'=>$vip>0?$vip.' benefício(s) recorrente(s) disponível(is)':'Sem benefício recorrente liberado'];
        $stmt=$db->prepare("SELECT amount_minor,state,period_key FROM rescue_daily_awards WHERE user_id=? ORDER BY period_key DESC LIMIT 1");$stmt->execute([$uid]);$res=$stmt->fetch();$rescue=$res&&$res['state']==='AVAILABLE'&&(int)$res['amount_minor']>0;
        $modules[]=['id'=>'rescue','title'=>'Fundos de Resgate','available'=>$rescue,'detail'=>$rescue?'Fundo disponível: '.$res['amount_minor'].' centavos':($res?'Último estado: '.$res['state']:'Nenhum período apurado')];
        $stmt=$db->prepare("SELECT pending_units FROM rebate_accounts WHERE user_id=?");$stmt->execute([$uid]);$units=(int)($stmt->fetchColumn()?:0);$stmt=$db->query("SELECT enabled,min_claim_minor FROM rebate_settings WHERE id=1");$rs=$stmt->fetch()?:[];$rebateMinor=intdiv($units,10000);$rebate=(bool)($rs['enabled']??false)&&$rebateMinor>=(int)($rs['min_claim_minor']??PHP_INT_MAX);
        $modules[]=['id'=>'rebate','title'=>'Rebate','available'=>$rebate,'detail'=>$rebate?'Saldo mínimo atingido':'Saldo resgatável atual: '.$rebateMinor.' centavos'];
        $now=new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo'));$cutoff=$now->setTime(21,0);if($now<$cutoff)$cutoff=$cutoff->modify('-1 day');$checkDay=$cutoff->format('Y-m-d');$stmt=$db->prepare("SELECT COUNT(*) FROM promotion_redemptions WHERE user_id=? AND promotion_type='checkin' AND day_key=?");$stmt->execute([$uid,$checkDay]);$checkClaimed=(int)$stmt->fetchColumn()>0;
        $modules[]=['id'=>'checkin','title'=>'Nível e Check-in','available'=>!$checkClaimed,'detail'=>$checkClaimed?'Check-in do período já resgatado':'Ainda não resgatado; requisitos devem ser verificados no módulo'];
        $stmt=$db->prepare("SELECT COALESCE(wa.balance_minor,0) FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id WHERE w.user_id=? AND wa.type='AFFILIATE'");$stmt->execute([$uid]);$aff=(int)($stmt->fetchColumn()?:0);
        $modules[]=['id'=>'agency','title'=>'Agência e Indicações','available'=>$aff>0,'detail'=>$aff>0?'Saldo de afiliado disponível':'Sem saldo de afiliado'];
        $stmt=$db->prepare("SELECT COUNT(*) FROM promotion_redemptions WHERE user_id=?");$stmt->execute([$uid]);$redemptions=(int)$stmt->fetchColumn();
        return ['found'=>true,'user'=>['id'=>$uid,'public_id'=>(int)$user['public_id'],'username'=>$user['username'],'status'=>$user['status']],'modules'=>$modules,'redemptions'=>$redemptions,'note'=>'Diagnóstico somente leitura. Regras finais de elegibilidade continuam validadas pelo serviço de cada módulo no momento do resgate.'];
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
        $checks['negative_wallet_balance']=$db->query("SELECT wa.id AS account_id,w.user_id,wa.type,wa.balance_minor FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id WHERE CAST(wa.balance_minor AS SIGNED)<0 ORDER BY wa.id LIMIT 100")->fetchAll();
        $checks['completed_financial_without_ledger']=$db->query("SELECT ft.id,ft.user_id,ft.type,ft.reference_type,ft.reference_id,ft.created_at FROM financial_transactions ft LEFT JOIN ledger_entries le ON le.transaction_id=ft.id WHERE ft.status='COMPLETED' AND ft.type NOT IN ('CASINO_BET','CASINO_WIN','CASINO_WINBET') GROUP BY ft.id,ft.user_id,ft.type,ft.reference_type,ft.reference_id,ft.created_at HAVING COUNT(le.id)=0 ORDER BY ft.created_at DESC LIMIT 100")->fetchAll();
        $checks['promotion_transaction_amount_mismatch']=$db->query("SELECT r.id AS redemption_id,r.amount_minor,ft.id AS transaction_id,COALESCE(SUM(le.amount_minor),0) AS ledger_minor FROM promotion_redemptions r JOIN financial_transactions ft ON ft.id=r.financial_transaction_id LEFT JOIN ledger_entries le ON le.transaction_id=ft.id WHERE r.amount_minor>0 GROUP BY r.id,r.amount_minor,ft.id HAVING ledger_minor<>r.amount_minor ORDER BY r.id DESC LIMIT 100")->fetchAll();
        $summary=[];foreach($checks as $name=>$rows)$summary[$name]=['sample_count'=>count($rows),'truncated'=>count($rows)===100];
        return ['mode'=>'READ_ONLY','external_gateway_verified'=>false,'checks'=>$checks,'summary'=>$summary,'note'=>'Diagnóstico local e amostras de até 100 registros por tipo. Nenhum valor é corrigido automaticamente; executar reconciliação externa, testes concorrentes e revisão humana antes de produção.'];
    }
}
