INSERT INTO payment_gateways
(code,name,enabled,deposit_enabled,withdrawal_enabled,mode,priority_deposit,priority_withdrawal,min_deposit_minor,max_deposit_minor,min_withdrawal_minor,max_withdrawal_minor,credentials_encrypted,settings,public_config)
VALUES
('bspay','BSPAY',0,1,1,'PRODUCTION',30,30,100,NULL,100,NULL,NULL,
 JSON_OBJECT('base_url','https://api.bspay.co','withdrawal_description','Saque da plataforma'),
 JSON_OBJECT('method','PIX'))
ON DUPLICATE KEY UPDATE name=VALUES(name), settings=VALUES(settings), public_config=VALUES(public_config);
