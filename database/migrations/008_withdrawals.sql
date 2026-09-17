ALTER TABLE user_payout_accounts
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER key_value,
    ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

CREATE UNIQUE INDEX uq_user_payout_key ON user_payout_accounts (user_id,type,key_type,key_value);

UPDATE user_payout_accounts upa
JOIN users u ON u.id=upa.user_id
SET upa.is_verified=1
WHERE upa.type='PIX'
  AND upa.key_type='CPF'
  AND REPLACE(REPLACE(REPLACE(upa.key_value,'.',''),'-',''),' ','')=u.cpf;

UPDATE payment_gateways
SET withdrawal_enabled=1,
    min_withdrawal_minor=100,
    max_withdrawal_minor=NULL,
    priority_withdrawal=100
WHERE code='sandbox_pix';
