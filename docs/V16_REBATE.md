# V16 — Rebate do site

## Instalação em homologação

1. Faça backup do banco e preserve `.env`.
2. Substitua o projeto completo e execute `php bin/migrate.php` (migração 023).
3. Em **Admin → Promoções → Rebate**, cadastre as faixas com nível, volume acumulado de apostas e percentual. Habilite as faixas que desejar.
4. Configure um valor mínimo de resgate e só então ative a campanha na caixa de configuração exibida no topo da seção.
5. Configure o agendador da hospedagem para chamar `php /CAMINHO/DO/PROJETO/bin/process-rebate.php` periodicamente (por exemplo, a cada 5 minutos). Verifique logs e permissões do PHP CLI. O processo evita execução simultânea via bloqueio de arquivo e utiliza chaves únicas no banco contra créditos duplicados.
6. Teste com apostas PlayFiver de homologação e transações confirmadas. Consulte Home → menu lateral → Rebate para verificar o volume e o botão **Receber**. Teste resgates repetidos/concomitantes e confirme saldo e lançamentos.

## Regras aplicadas

- Desativado até ativação explícita no Admin; não concede rebate sobre apostas anteriores à ativação. Ao reativar, um novo marco temporal é iniciado para o volume de progressão (saldos ainda pendentes são preservados).
- Considera exclusivamente lançamento de débito da PlayFiver associado a transação `CASINO_BET` ou `CASINO_WINBET` com status `COMPLETED`, posterior à ativação. Depósitos não contam.
- A faixa é obtida pelo volume cumulativo de apostas processadas desde a ativação, incluindo a aposta corrente. A taxa dessa faixa é aplicada à aposta atual, não retroativamente ao volume anterior. Se nenhuma faixa for alcançada, a aposta é registrada com taxa zero.
- Frações de centavo são conservadas na conta (`pending_units`) e só arredondadas **para baixo** no resgate. Saldo abaixo do mínimo não é sacável; o restante fracionário permanece para novos acúmulos.
- Resgate atômico e autenticado na conta `CASH`, **sem rollover adicional**. Histórico de resgates e registro de lançamentos (`financial_transactions` e `ledger_entries`).
- Configurações e faixas históricas utilizadas não podem ser excluídas ou ter critérios alterados; desative a faixa e cadastre outra para novos eventos. Desativar a campanha impede novos cálculos e resgates até reativá-la.
- A rotina de rebate é independente da comissão da Agência. É possível que uma aposta válida gere comissão ao indicador e rebate ao jogador, se ambos estiverem habilitados.

## Limitações / validação necessária antes de produção

- **Não houve teste integrado com MySQL/PlayFiver nesta entrega.** Validar migração, idempotência, carteira, precisão das taxas, contestação/estorno de apostas, e proteção contra pagamentos indevidos em homologação.
- O processador considera os registros marcados `COMPLETED` pelo sistema. Uma reversão posterior da aposta não estorna automaticamente rebates já concedidos; confirme a política de estornos com o provedor antes de ativar o processamento financeiro real.
- A consulta exibe histórico recente; relatórios do Admin são limitados aos 100 registros mais recentes.
