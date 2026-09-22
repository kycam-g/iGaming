-- V18: apos a decisao de manter apenas Fundos de Resgate diarios.
-- Nao excluir campanhas ou movimentacoes historicas; apenas desativar este tipo independente.
-- Bonus VIP semanal usa o tipo 'vip' e permanece inalterado.
UPDATE promotion_configurations SET enabled=0 WHERE type='weekly' AND enabled<>0;
