# V16.1 — Rebate fixo sem níveis

## Instalação

1. Faça backup do banco de dados, mantenha o `.env` e substitua os arquivos do projeto.
2. Execute `php bin/migrate.php` para aplicar a migração `024_rebate_fixed_rate.sql`.
3. A migração **desativa** a campanha antiga para evitar pagamento com faixas legadas; preserva eventos, saldos pendentes e histórico de resgates. As configurações antigas permanecem no banco para auditoria, mas deixam de ser usadas na Home e no cálculo.
4. Em Admin → Promoções → Rebate, informe uma porcentagem fixa (0,01%–10%), o mínimo de resgate e ative a campanha. Não é necessário cadastrar faixas.
5. Mantenha o cron `php bin/process-rebate.php`; valide apostas, saldos e resgates em homologação.

## Regras

- O rebate por aposta válida é `valor da aposta × porcentagem fixa / 100`, independentemente do resultado. Exemplo: R$ 100 × 0,5% = R$ 0,50.
- Apostas PlayFiver confirmadas após ativação; depósitos, apostas anteriores à ativação e apostas sem lançamento elegível não contam.
- Alterações de taxa passam a valer para apostas a partir do horário da alteração; o histórico de taxas armazena cada período, evitando que um cron atrasado aplique a taxa nova a apostas anteriores. Desativar/reativar reinicia o marco de elegibilidade e o volume exibido; **não apaga o saldo já acumulado**, porém apostas ainda não processadas antes da reativação não entram no novo ciclo. Processe a fila antes de desativar.
- Eventos individuais mantêm taxas e recompensas históricas e têm chave única por lançamento de aposta. Frações de centavo permanecem acumuladas até o resgate. Crédito CASH sem rollover adicional.

## Limitação de homologação

Não houve integração com MySQL/PlayFiver neste ambiente. Valide migrações, mudança de taxa durante fila pendente, diferenças de fuso horário da sessão MySQL, corridas entre alterações administrativas/cron, estornos e saldo antes da publicação. Estornos posteriores à liquidação continuam **sem ajuste automático**, como na V16.
