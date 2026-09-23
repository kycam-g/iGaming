# V23 — Refinamento de módulos, administração e diagnóstico financeiro

## O que esta entrega realmente faz

- **V23.1:** Central de Recompensas da etapa anterior preservada.
- **V23.2:** componentes e estados visuais padronizados em CSS para desktop/mobile, acessibilidade básica, botões e cards. Os layouts devem ser conferidos em aparelhos reais, sobretudo roletas e safe-area.
- **V23.3:** visão de campanhas habilitadas/desabilitadas, agregações por tipo/status e últimos 40 resgates no Admin. Os totais são históricos nominais, não caixa/saldo disponível. Endpoints protegidos por autenticação administrativa.
- **V23.4:** painel administrativo de diagnóstico **somente leitura** que coleta até 100 registros por verificação. Ele não executa estorno, transferência, crédito ou correção automática e não consulta gateways externos.

## Diagnósticos incluídos

1. Saldo das contas de carteira x valor líquido no ledger (desde a criação da carteira).
2. Entradas de ledger cujo antes/depois não corresponde ao valor e à direção.
3. Resgates positivos sem transação associada ou associados a transação que não esteja concluída.
4. Progresso de rollover acima do requerido, ou recompensa concluída antes do requisito.
5. Transação financeira vinculada a mais de um resgate.
6. Aposta com alocações de rollover somando mais do que o valor debitado.
7. Progresso de rollover divergente da soma das alocações registradas.
8. Crédito promocional repetido para mesmo tipo e referência.

**Interpretação:** resultado vazio em uma consulta não comprova ausência de falhas. Os resultados são *amostras limitadas* e não abrangem conciliação externa. Tabelas muito grandes devem ser analisadas com planejamento de índices e uma janela de manutenção; relatórios leem o banco em produção e podem ter custo operacional.

## Procedimento de instalação

1. Faça backup completo do código e do MySQL e mantenha o `.env` original.
2. Instale o ZIP em homologação e atualize a página (cache está versionado em `public/app.php`/`public/admin.php`).
3. Execute `node tests/rewards-center-smoke.js` e todos os testes PHP de `tests/Unit/`.
4. Entre no Admin e abra Promoções; use **ATUALIZAR** no novo resumo. O diagnóstico pesado só é executado ao clicar em **EXECUTAR DIAGNÓSTICO**.
5. Valide a Central no navegador com contas distintas: sem bônus, com rodadas, com rollover bloqueado e com recompensa já resgatada.
6. Teste responsividade em larguras de 320, 360, 390, 430, 768 e 1280 px (menu, botões, giro e textos); confira em iOS Safari e Android Chrome.

**Não há migration nova.** Os relatórios usam tabelas existentes; eles exigem que as migrations anteriores tenham sido aplicadas.

## Casos financeiros obrigatórios antes de dinheiro real

- Duas requisições simultâneas de mesmo cupom/check-in/VIP/baú/giro: no máximo um resultado por entitlement/idempotency key; validar ledger e carteira após ambos retornarem.
- Mesmo callback PlayFiver entregue duas ou mais vezes e fora de ordem: nenhuma aposta ou recompensa duplicada; rejeitar mensagens não autenticadas.
- PIX PAID confirmado no gateway x transação local PENDING/PROCESSING: conferir no gateway antes de reconciliar. Cancelados/pendentes jamais devem liberar bônus elegível.
- Aposta com ganho e aposta sem ganho: contribuição ao rollover limitada **ao valor de aposta confirmado**, sem contar vitórias, depósitos ou bônus.
- Dois bônus LOCKED e aposta que não cobre ambos: soma de alocações <= valor da aposta e progresso de cada bônus <= requisito.
- Resgate logo na virada de 21h BRT, 00h BRT e mudança de semana/mês: mesma transação não deve contar para duas janelas; testar mudanças de horário.
- Recompensa expirada, manutenção VIP reduzida, alterações no Admin durante resgate e indisponibilidade de MySQL: preservar consistência e permitir reprocessamento idempotente.
- Garantir o saldo CASH não inclui progresso da Roleta de Saque nem BONUS bloqueado; CASH_BONUS imediato é exceção apenas quando a fatia específica for sorteada.

**Bloqueio de publicação:** não declarar ambiente apto a dinheiro real enquanto esses casos não forem executados contra MySQL transacional com dados de teste e integração externa de homologação. Sem acesso ao banco, gateway e PlayFiver, não foi possível aprovar esses cenários neste pacote.
