ALTER TABLE platform_notifications ADD COLUMN source_key VARCHAR(190) NULL AFTER user_id;
ALTER TABLE platform_notifications ADD UNIQUE KEY uq_platform_notifications_source (source_key);

CREATE TABLE IF NOT EXISTS notification_automation_settings (
    event_key VARCHAR(80) PRIMARY KEY,
    category VARCHAR(24) NOT NULL,
    priority VARCHAR(16) NOT NULL DEFAULT 'normal',
    title_template VARCHAR(120) NOT NULL,
    message_template VARCHAR(1000) NOT NULL,
    link_path VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notification_automation_settings(event_key,category,priority,title_template,message_template,link_path,enabled) VALUES
('deposit_created','financial','normal','PIX gerado','Seu depósito de {amount} foi criado. Conclua o pagamento para receber o saldo.','/carteira',1),
('deposit_paid','financial','high','Depósito confirmado','Seu depósito de {amount} foi confirmado e creditado.','/carteira',1),
('deposit_failed','financial','normal','Depósito não concluído','O depósito de {amount} não foi concluído. Você pode gerar um novo PIX.','/carteira',1),
('first_deposit_bonus','promotion','high','Bônus de primeiro depósito','Seu bônus de {amount} do primeiro depósito foi creditado.','/promocoes',1),
('withdrawal_created','financial','normal','Saque solicitado','Sua solicitação de saque de {amount} foi recebida e está em análise.','/carteira',1),
('withdrawal_processing','financial','high','Saque em processamento','Seu saque de {amount} foi aprovado e está sendo processado.','/carteira',1),
('withdrawal_paid','financial','high','Saque concluído','Seu saque de {amount} foi concluído.','/carteira',1),
('withdrawal_failed','financial','urgent','Saque não concluído','Seu saque de {amount} não foi concluído. Consulte os detalhes da carteira.','/carteira',1),
('promo_roulette_available','promotion','high','Giro da Sorte disponível','Você tem {count} giro(s) disponível(is) no Giro da Sorte.','/promocoes?modulo=roulette',1),
('promo_cashwheel_available','promotion','high','Roleta de Saque disponível','Você tem {count} rodada(s) disponível(is) na Roleta de Saque.','/promocoes?modulo=cashwheel',1),
('promo_lottery_available','promotion','high','Sorteio disponível','Você tem {count} rodada(s) disponível(is) no Sorteio de Cartas.','/promocoes?modulo=lottery',1),
('promo_chest_available','promotion','high','Baú desbloqueado','Você possui {count} baú(s) disponível(is) para resgate.','/promocoes?modulo=chests',1),
('promo_rebate_available','promotion','normal','Rebate disponível','Você tem {amount} de rebate disponível para resgate.','/promocoes?modulo=rebate',1),
('promo_rescue_available','promotion','high','Fundo de Resgate disponível','Você tem {amount} disponível no Fundo de Resgate hoje.','/promocoes?modulo=rescue',1),
('vip_available','promotion','high','Benefício VIP disponível','Você possui {count} benefício(s) VIP disponível(is) para resgate.','/promocoes?modulo=vip',1);
