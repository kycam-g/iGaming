# V14 — Baú do Tesouro (indicações por depósito confirmado)

## Instalação
1. Backup do banco e da versão anterior. Preserve o `.env` e uploads do servidor.
2. Substitua o projeto pela V14 e execute `php bin/migrate.php` na raiz. A migração nova é `021_referral_chests.sql`.
3. Em **Admin → Promoções → Baús e indicações**, cadastre uma campanha com nome, quantidade de indicados, **depósito mínimo acumulado por indicado** (R$), bônus do indicador e rollover. Marque **Habilitar**.
4. Acesse a Home com uma conta, abra **menu lateral → Baú do Tesouro**, copie o link e cadastre uma conta NOVA pelo link. Confirme no sistema financeiro um depósito real no valor mínimo configurado. Reabra o baú e confira progresso antes de resgatar.

## Regras aplicadas
- O vínculo indicador/indicado é gravado **na transação de cadastro** usando um código de indicação gerado no servidor. Cada conta nova só pode ter um indicador; contas existentes não são atribuídas retroativamente. Não se devem usar os antigos links genéricos de afiliado para atribuição.
- Apenas depósitos `payment_transactions` com `kind='DEPOSIT'` e `status='PAID'`, registrados após o vínculo, são somados POR INDICADO. Um depósito pendente, em análise, cancelado ou falho não conta. Não exige aposta do indicado nesta versão.
- Cada campanha/baú é resgatável **uma única vez por indicador**. Múltiplas campanhas configuradas pelo Admin têm metas e valores independentes; os mesmos indicados podem contar em campanhas distintas conforme seus requisitos.
- Resgate é validado novamente dentro de transação MySQL, bloqueando a linha do usuário e da campanha, antes do crédito, ledger e registro de resgate. Rollover positivo vai para conta BONUS bloqueada; zero segue a regra de CASH já existente na plataforma.
- Campanhas já resgatadas não permitem alterar os requisitos/recompensa: desative e cadastre outra. Histórico e transações anteriores permanecem. O campo legado `max_claims` permanece disponível por compatibilidade, mas o mecanismo garante **um resgate por campanha**, independentemente dele.
- O Admin possui resumo de indicadores e valor pago agregado em **Baús e indicações**; resgates individuais constam de **Histórico de bônus**.

## Cuidados de implantação
- O cálculo lê os registros oficiais do sistema de pagamentos. Não libera baús por clique, cadastro isolado ou depósito pendente.
- Verifique conciliação de depósitos no ambiente de homologação, autorização, eventuais estornos e segurança antifraude antes de habilitar em produção. A versão não implementa uma decisão automática de fraude/conta duplicada nem reverte bonificações em caso de estorno posterior a um depósito já marcado `PAID`; gerencie incidentes financeiros antes de disponibilizar fundos.
- Esta versão não permite migração retroativa de indicados cadastrados antes dela. Nenhum cron é necessário: progresso é calculado na consulta e resgate é sob demanda.
