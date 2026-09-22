# V17 — Fundos de Resgate diário

## Instalação em homologação

1. Faça **backup do banco e do projeto**, preserve o `.env` e instale os arquivos desta versão.
2. Execute `php bin/migrate.php` para aplicar a migração `025_rescue_daily.sql`. Não exclua nem recrie tabelas antigas.
3. Em **Admin → Promoções → Fundos de Resgate**, cadastre faixas com nível, perda mínima, percentual e rollover; habilite ao menos uma faixa.
4. Ative a campanha no formulário superior. A data efetiva é o **dia seguinte às 00h (Brasília)**, nunca há bônus retroativo.
5. Configure o cron para executar `php /CAMINHO/DO/PROJETO/bin/process-rescue.php` diariamente **após 00h05, no horário de Brasília**. Exemplo de cron em servidor configurado em `America/Sao_Paulo`: `5 0 * * * /usr/bin/php /CAMINHO/DO/PROJETO/bin/process-rescue.php >> /CAMINHO/DO/PROJETO/rescue-cron.log 2>&1`. Se o servidor estiver em outro fuso, converta a hora.
6. Na área do jogador, abra **Fundos de Resgate** para consultar o resultado do dia anterior, as faixas e o botão **Receber**.

## Regras técnicas implementadas

- Janela diária: meia-noite a meia-noite em `America/Sao_Paulo`, segundo `financial_transactions.created_at` convertido para o fuso da sessão MySQL.
- Fonte: somente lançamentos PlayFiver `COMPLETED` na conta `CASH`, tipos `CASINO_BET`, `CASINO_WIN` e `CASINO_WINBET`. Débitos são apostas; créditos são ganhos. Perda elegível = `max(0, apostas - ganhos)`. **Não é calculada a perda global da conta nem abatido todo bônus já recebido**; essa política ainda deve ser conciliada com as regras do operador.
- Aplica somente **a maior faixa de perda mínima alcançada**. Cálculo inteiro em centavos com porcentagem em basis points, arredondado para baixo; benefício inferior a R$ 0,01 não é resgatável.
- Uma linha por `(user_id, period_key)` preserva o snapshot da apuração, taxa, campanha e rollover. Repetições do cron ou consultas não criam duplicação.
- O crédito só é efetuado mediante resgate autenticado e transacional, **somente no dia seguinte** à perda. Recompensas não solicitadas expiram. Com rollover, os créditos entram em `BONUS`; sem rollover, em `CASH`. O resgate gera lançamentos e histórico existentes na plataforma.
- Desativar a campanha impede novos cálculos e novos resgates. Reativar marca uma nova data de início no dia seguinte, sem retroatividade.
- Uma faixa usada em apuração não pode ser alterada ou excluída; desative e crie outra para preservar o histórico. O cron também marca recompensas antigas como `EXPIRED`.
- As campanhas de rebate e Fundo de Resgate são separadas. A regra de sobreposição com **Compensação Semanal** não está implementada e precisa ser definida antes de ativar as duas em produção.

## Validação financeira obrigatória antes de produção

Esta entrega **não foi testada em MySQL real nem com callbacks reais PlayFiver**. Verifique: timezone PHP/MySQL e mudanças de horário; apostas e ganhos emitidos em callbacks separados; ganhos atrasados após a apuração; cancelamento/estorno e correção de resultados; resgate simultâneo; encerramento do período; carteira, rollover e histórico. Um ganho lançado tardiamente não recalcula automaticamente uma recompensa já provisionada ou resgatada. Por isso, **não ative créditos reais até validar reconciliação e política de estornos**. Em produção pode ser necessário aguardar liquidação definitiva dos eventos em vez de disponibilizar a recompensa imediatamente à meia-noite.

## Testes locais

`php tests/Unit/RescueTest.php` verifica matemática, rejeição de valores inválidos e guardas da migração, sem precisar conectar ao banco. Validação de sintaxe: `php -l` nos arquivos PHP alterados e `node --check` nos JavaScripts. Essas verificações não substituem testes de integração.
