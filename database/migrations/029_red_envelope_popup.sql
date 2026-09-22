-- V20.1: Envelope Vermelho passa a ser popup condicionado a dia da semana e depósito confirmado.
-- A liberação automática é desativada na migração para obrigar revisão segura no Admin.
UPDATE promotion_configurations
SET config = JSON_SET(
    COALESCE(NULLIF(config,''), JSON_OBJECT()),
    '$.auto_enabled', 0,
    '$.monday', 0,
    '$.tuesday', 0,
    '$.wednesday', 0,
    '$.thursday', 0,
    '$.friday', 0,
    '$.saturday', 0,
    '$.sunday', 0,
    '$.deposit_min_cents', 100,
    '$.deposit_percent', 1
)
WHERE type='envelope';
