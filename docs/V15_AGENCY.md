# V15 — Agência e Indicações

## Preparação

1. Instale em ambiente de homologação e faça backup do banco. Preserve `.env` e credenciais.
2. Execute `php bin/migrate.php`, aplicando `022_agency_commissions.sql`.
3. No Admin → Promoções → Agência, crie e habilite pelo menos uma faixa com nível, volume mínimo e comissão (0–30%). Para comissão desde o início, a primeira faixa deve ter meta R$ 0.
4. Ative explicitamente **Pagamentos de comissão** no painel da Agência. A data/hora da ativação delimita quais apostas entram; nada é concedido retroativamente.
5. Na hospedagem, agende um cron (por exemplo, a cada 5 minutos) para `cd /CAMINHO/DO/PROJETO && /usr/bin/php bin/process-agency.php`. Ajuste o executável e a pasta conforme seu servidor. Sem cron não há crédito automático.

## Regras financeiras

- Somente indicações **diretas**, vinculadas durante cadastro pelo link exclusivo. Depósito mínimo é regra exclusiva dos baús, não do pagamento da agência.
- Somente apostas PlayFiver confirmadas (`CASINO_BET` ou `CASINO_WINBET`, lançamento de débito) realizadas após ativação e após cadastro do indicado. Sem fontes de apostas artificiais.
- Faixa é escolhida pelo volume acumulado de apostas diretas desde ativação até o evento processado; comissão = aposta × taxa / 100, arredondada para centavos. Apostas abaixo da primeira faixa são marcadas com comissão R$ 0 para não gerar pagamentos retroativos.
- Uma aposta só pode ser marcada uma vez, pela chave única do lançamento, com operação financeira e registro de comissão na mesma transação SQL. Pagamentos vão para a conta **AFFILIATE** e aparecem no relatório e no histórico; esta entrega não implementa transferência automática para CASH ou saque da conta AFFILIATE.
- Desativar interrompe novos pagamentos. Reativar cria nova data de corte; apostas anteriores à reativação que ainda não foram processadas não são pagas. Alterações nas faixas afetam as apostas processadas subsequentemente; comissões já pagas mantêm o percentual aplicado registrado.
- O job é bloqueado por arquivo para impedir duas execuções paralelas no mesmo host; a chave única e as travas no MySQL fornecem idempotência adicional. Não processa estornos retroativos; suspenda pagamentos até a integração de reversões, caso seus eventos PlayFiver possam ser revertidos após liquidação.

## Validação de homologação

- Cadastro sem código não forma vínculo; cadastro com código válido cria exatamente um indicador; repetir link não duplica.
- Sem ativação ou sem cron, não há crédito. Depósito do indicado não gera comissão; apenas aposta confirmada gera.
- Teste faixa R$0, uma aposta pequena e uma repetição do mesmo callback: deve existir exatamente uma linha de comissão e uma transação de crédito.
- Teste mudança de faixa, desligamento/reativação, vários indicadores e uma conta suspensa.
- Compare saldo AFFILIATE com a soma dos créditos em ledger e os relatórios; confirme que o saldo CASH não sofre alteração.
- Ainda não foram executados testes financeiros integrados com MySQL nem callback remoto neste ambiente: manter pagamentos desativados em produção até validar.
