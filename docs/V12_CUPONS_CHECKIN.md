# V12 — Cupons e check-in: implantação e critérios de aceite

Esta versão parte do ZIP V11, preserva barra inferior, menu lateral, Home e login do Admin.

## Antes de instalar

1. Faça backup dos arquivos e do MySQL. Faça o primeiro deploy em homologação; créditos promocionais geram transações financeiras reais.
2. Preserve o `.env` da sua instalação; o ZIP não inclui segredos. Garanta dependências do Composer (`composer install --no-dev --optimize-autoloader`).
3. **A migração 019 é obrigatória**: no diretório do projeto, execute `php bin/migrate.php` e confirme a linha `Applied 019_promotion_redemptions.sql`. A migração altera a tabela de configurações e cria histórico, tentativas e alocações. Não execute com o banco sem backup; MySQL faz autocommit de DDL.
4. Publique os arquivos **somente após** a migração. Se usar DocumentRoot na raiz, `/assets` é servido da pasta raiz; os arquivos em `assets/` e `public/assets/` precisam ter conteúdo idêntico (estão sincronizados neste ZIP).
5. Confira o fuso do MySQL e a data/hora do servidor. O dia de check-in começa às **21h de Brasília**; os limites são convertidos do fuso de Brasília para o fuso atual da sessão MySQL, usando o deslocamento medido no próprio servidor.
6. Faça login no Admin, abra Promoções → Cupons, crie ou edite um cupom e informe **código privado** (4–64 caracteres A–Z/0–9/_/-), estoque, faixa de valores, rollover e marque Habilitar. Cupons antigos não têm código; precisam ser editados para se tornarem resgatáveis.
7. Abra Promoções → Check-in e cadastre dias 1…7, valores positivos, depósitos/apostas mínimos e rollover; habilite todos os dias da sequência. O botão de check-in só aparece ativo quando a conta está autenticada e ainda não resgatou o dia.

## Regras de backend

- Cupom: código consultado exclusivamente no servidor, 10 tentativas por conta/hora, limite global de estoque, no máximo um resgate por campanha e usuário. Código não sai no endpoint público de configurações. Admin consulta resgates em Promoções → Histórico de bônus.
- Check-in: uma concessão por usuário e dia de Brasília. Se o dia anterior não foi resgatado, retorna ao dia 1; ao completar o maior dia configurado, reinicia no dia 1. Um dia ausente na sequência impede o próximo resgate, para não pagar regras inadvertidas.
- Depósito: soma de depósitos `PAID` no período (não basta solicitação ou comprovante). Aposta: débitos confirmados e idempotentes de transações PlayFiver, não cliques no jogo.
- Valor fixo: `reward_min_cents`. Aleatório: uniformemente entre mínimo e máximo. Recompensa extra soma ao resultado do check-in.
- Recompensas sem rollover entram na conta `CASH`. Recompensas com rollover entram em `BONUS` e **não são sacáveis** até completar o requisito. Apostas válidas posteriores à concessão abatem o requisito uma única vez, da recompensa mais antiga à mais recente; ao completar, uma transferência contábil BONUS→CASH é registrada na mesma transação da aposta. A interface mostra progresso/histórico e o saldo do cabeçalho passa a refletir apenas CASH.
- Campanhas com resgate não podem ser excluídas; desative-as para preservar as referências e auditoria.

## Casos de teste de homologação (MySQL real)

1. Administrador autenticado consegue criar, editar, desabilitar código; visitante recebe 401 no endpoint de resgate; o endpoint público não revela códigos.
2. Cupom habilitado/estoque 1: usuário A resgata 1 vez; segunda tentativa falha; usuário B recebe esgotado; o ledger credita exatamente 1 vez.
3. Código incorreto retorna erro, tentativas são persistidas após falha, após 10/h novos palpites bloqueados.
4. Check-in sem depósito ou aposta exigidos falha; depósito `PENDING` não serve; `PAID` no intervalo sim. Se exigidas apostas, só contam débitos PlayFiver efetivamente liquidados.
5. Check-in repetido no mesmo dia falha; no próximo dia segue para dia 2; após faltar um dia volta para o dia 1; validado na virada das 21h.
6. Resgate com rollover >0 entra em BONUS; o saldo CASH não é inflado. Aposta válida posterior preenche progresso; replay do mesmo evento PlayFiver não duplica abatimento nem crédito; liquidação converte exatamente o valor do bônus a CASH.
7. Várias recompensas bloqueadas consomem aposta uma única vez; histórico admin reflete transação e status.
8. Login Admin, Home, drawer e barra inferior seguem funcionando; confirme telas em navegador mobile real.

## Limites/validação pendente

Não há banco MySQL nem dependências Composer no ambiente desta geração: apenas verificações de sintaxe PHP/JS e testes de regras independentes de banco foram executados. **Não ativar em produção sem executar os oito cenários acima em homologação**. O callback real da PlayFiver continua dependente de configuração e validação no seu servidor. Rebate, VIP, roletas e demais módulos permanecem consultivos nesta etapa.
