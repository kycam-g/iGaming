# V18 — Fundos de Resgate como único cashback por perdas

Decisão: cancelar a campanha independente **Compensação Semanal**. O Fundo de Resgate diário continua operando conforme V17. O **bônus semanal VIP** é um benefício diferente e permanece intacto. Rebate fixo sobre apostas também permanece separado.

## Atualização

1. Faça backup do banco. Preserve `.env`.
2. Substitua os arquivos do projeto.
3. Execute `php bin/migrate.php` para aplicar a migração 026: ela **desativa configurações antigas** do tipo `weekly`, não apaga campanhas nem resgates históricos.
4. Limpe o cache do navegador (`Ctrl + F5`).

A Home deixa de oferecer o atalho Semana e a Central de Promoções deixa de listar Compensação Semanal. O Admin remove seu submenu/formulário. A API também bloqueia a criação, edição e reativação deste tipo antigo e oculta sua configuração do catálogo público. Os dados antigos são mantidos no banco para auditoria. Não existe rotina de pagamento semanal independente nesta base, portanto nenhuma rotina cron adicional deve ser configurada.

## Controle de duplicidade e histórico

- A migração 025 preserva `rescue_daily_awards` com índice exclusivo `uq_rescue_user_day (user_id, period_key)`. Não foi alterada; um prêmio diário não passa a ser pagável duas vezes nesta versão.
- O histórico financeiro e os registros históricos de `promotion_configurations` do tipo weekly **não são apagados**.
- A migração 026 só atualiza `enabled=0` nas campanhas antigas, não altera `vip_period_awards`, o módulo VIP, nem o Rebate.
- Esta versão organiza as campanhas; não substitui homologação financeira com MySQL/PlayFiver.
