# MZ90 V13.1 — Benefícios VIP recorrentes

## Instalação

1. Faça backup do banco e da versão anterior. Preserve `.env`.
2. Substitua arquivos e execute `php bin/migrate.php` na raiz do projeto. A migração **020** cria tabelas novas, não apaga históricos.
3. Em **Admin → Promoções → Níveis VIP**, edite as metas mensais e bônus diário, semanal e mensal de cada nível. Os valores novos começam em zero; não há pagamentos recorrentes involuntários.
4. No painel do VIP, escolha **VIP vitalício** ou **redução mensal**, informe passos de downgrade e **ative o programa**.
5. Configure cron no servidor: `5 * * * * cd /CAMINHO/DO/PROJETO && /usr/bin/php bin/vip-process.php >> /CAMINHO/PRIVADO/vip-cron.log 2>&1`. Substitua caminhos, mantenha log fora da pasta pública. Também pode rodar manualmente para testar.

## Regras

- Volumes apurados somente no ledger de apostas PlayFiver confirmadas; exige integração financeira íntegra.
- Horário de Brasília: dia começa às 02h, semana começa segunda às 02h, mês começa dia 1 às 02h. O job provisiona direitos ao período atual; **o jogador resgata pelo botão Receber**, e somente então a carteira é creditada. Não existe pagamento automático sem resgate.
- Configuração inicial global desativada e bônus novos de cada nível iniciam em zero. O job também funciona ao consultar status ou solicitar resgate, caso cron atrase; não há provisionamento retroativo de períodos anteriores.
- Regra vitalícia: mantém nível, suspende bônus se não atingiu manutenção no mês encerrado; volta a liberar quando atinge volume exigido no mês atual. Regra downgrade: diminui o nível pelos passos configurados, podendo chegar a VIP 0; nova conquista de um patamar superior ou cumprimento da manutenção mensal do maior patamar conquistado recupera o nível.
- Primeira revisão somente após um mês civil completo posterior à ativação do programa e ao registro da associação VIP neste mecanismo, para não aplicar punição histórica retroativa. Revisões não executadas para meses mais antigos não são recalculadas automaticamente.
- Níveis sem meta de manutenção (zero) não sofrem punição. VIP desativado globalmente impede novas concessões; créditos/resgates já efetuados não são removidos.
- Direitos de bônus têm chave única `(user_id,kind,period_key)` e estado AVAILABLE/CLAIMED. Cada resgate é transação MySQL com lock de usuário/entitlement e lançamentos na carteira, usando o rollover da configuração congelada no direito.
- Histórico administrativo: **Promoções → Histórico de níveis** e **Histórico de bônus**, e no painel VIP a seção de revisões. A auditoria de alteração de política é registrada.
- Os benefícios correntes ficam resgatáveis apenas em seu respectivo período; direitos de períodos passados permanecem no histórico mas não são resgatáveis via endpoint corrente.

## Testes manuais em homologação antes da produção

- Registrar apostas confirmadas (incluindo timezone/limite de período) e conferir volume contra o ledger.
- Validar 02h Brasília, virada da semana e do mês, bônus não configurados, usuários bloqueados, modo vitalício/downgrade, reabilitação e níveis VIP 0.
- Tentar duplo clique e duas requisições simultâneas para a mesma recompensa e confirmar exatamente um crédito e um vínculo contábil.
- Verificar rollover e lançamento de crédito em bônus/cash, histórico, backup, logs de cron e permissões da hospedagem.

Esta entrega foi validada por sintaxe e testes unitários de períodos; testes reais MySQL/PlayFiver e testes visuais requerem ambiente de homologação.
