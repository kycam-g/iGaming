# Atualização V12 — Cupons e Check-in (etapa 1 de premiações)

**Base:** V11. Home, menu lateral, Admin e navegação inferior preservados.

- Resgate autenticado de cupom por código privado, estoque global, uma utilização por usuário/campanha e limite de 10 tentativas/hora.
- Check-in de sequência diária com virada às 21h de Brasília, reset por ausência, depósitos PAID e apostas PlayFiver confirmadas como requisitos.
- Créditos transacionais no ledger, histórico do jogador e do Admin, rollover isolado em BONUS e transferência para CASH após apostas válidas **posteriores** ao resgate. Cada aposta só contribui uma vez para o rollover; duplo clique e repetição de callback não repetem premiações.
- Código de cupom não aparece na API pública, fica acessível apenas ao Admin; campanhas com resgates não podem ser excluídas (desative).
- Cabeçalho exibe apenas saldo CASH para não confundir bônus bloqueado com saldo de saque. README e `docs/V12_CUPONS_CHECKIN.md` documentam implantação e testes.

**Instalação obrigatória:** backup do banco/arquivos → preserve `.env` → execute `composer install --no-dev --optimize-autoloader` se necessário → `php bin/migrate.php` (migração `019_promotion_redemptions.sql`) → publique os arquivos → atualize com Ctrl+F5. Verifique a data/hora e o fuso da sessão MySQL (os limites de apuração são ajustados para a sessão). Cupons antigos devem receber um código no Admin antes de serem utilizados. **Primeiro em homologação!** Não há MySQL/Composer disponíveis para teste ponta a ponta nesta geração. Não ative bônus financeiros em produção sem testar os cenários em `docs/V12_CUPONS_CHECKIN.md`.

Os outros módulos ainda não creditam valores nesta versão. A integração financeira da PlayFiver depende de verificação real de callbacks e apostas.

---

# Atualização V10 — Logo ao lado do menu na Home

- Alinhamento do cabeçalho da Home: ícone de menu e logo juntos à esquerda; saldo, login e perfil continuam à direita.
- Alteração somente no CSS público, duplicada em `assets/app.css` e `public/assets/app.css`, com versão de cache atualizada no `public/app.php`.
- Menu lateral, painel administrativo e navegação inferior permanecem inalterados. Não requer migração de banco.
- Instalação: preserve o `.env`, substitua os arquivos e atualize a página com Ctrl+F5. Teste visual em diferentes larguras no navegador.

---

# Atualização V8 — Menu lateral da Home

- Ícone de menu no cabeçalho da Home abre drawer à esquerda, com fundo escurecido, fechar ao tocar fora, no botão × ou Escape; acessível por teclado, com foco contido.
- 12 atalhos levam diretamente aos módulos publicados na V7 em Promoções: Baú, Rebate, Agente, Troca, Nível/Check-in, Resgatar, Semana, VIP, Roleta de Saque, Boas-vindas, Envelope e Sorteio.
- Atalhos para perfil, convites, depósito e carteira aproveitam os controles já existentes. Menu inferior da Home e painel Admin não foram modificados.
- Arquivos de frontend estão presentes tanto em `assets/` (instalação raiz) quanto `public/assets/` (DocumentRoot na pasta public). Cache atualizado para V8.
- Continua sendo versão consultiva: prêmios, saques promocionais e créditos automáticos não foram ativados.
- Instalação: manter `.env`, backup do banco; substituir arquivos completos. Nenhuma migração nova para o drawer. Testar acesso em celular e desktop no ambiente de homologação.

---

# Atualização V7 — Módulos de Promoções (etapa 1: catálogo + configuração)

**Não é uma implementação financeira completa.** Esta entrega cria 12 páginas de consulta dentro da seção Promoções da Home, preserva o menu inferior original e expõe exclusivamente as regras `enabled=1` por `GET /api/promotions/configs`. Inclui CRUD administrativo para Agência, Rebate, Fundos de Resgate, Compensação Semanal, Roleta de Saque e Sorteio, reutilizando a tabela `promotion_configurations` da migração 018. Mantém os seis tipos existentes. Os novos tipos iniciam sem dados e devem ser cadastrados pelo administrador.

**Não incluído:** saldo real, cupom resgatável, motor de apostas, comissões calculadas, indicação individual, roletas/sorteio com premiação, gestão de bônus, liquidação, cash-out e histórico de recompensas. Os botões de resgate estão desabilitados intencionalmente para não simular transações. O endpoint público não revela registros desabilitados e o login Admin permanece no código da V6.

**Instalação:** backup do banco + arquivos; preservar `.env`; atualizar os arquivos; executar `php bin/migrate.php` se a migração 018 ainda não tiver rodado; depois atualizar com Ctrl+F5. **Não é necessário rodar migração adicional para os seis novos tipos**, pois usam a tabela existente. Validar primeiro em homologação; não liberar bônus reais em produção com esta entrega.

---

# MZ90 — Plataforma iGaming modular em PHP 8.2

Projeto iGaming modular desenvolvido em **PHP 8.2 puro**, com Composer/PSR-4, MySQL/MariaDB e foco em execução local via XAMPP. A plataforma possui área pública, autenticação de jogadores, carteira com ledger imutável, pagamentos PIX, painel administrativo, catálogo de cassino, aparência gerenciável, promoções, afiliados e base para integração com provedores de jogos.

> **Status atual:** a plataforma está funcional para autenticação, administração, catálogo local, carteira, Pixup cash-in, banners, promoções, aparência, categorias e demais módulos descritos abaixo. A integração PlayFiver para **abrir jogos reais / apostas / callbacks financeiros** ainda não está concluída. A sincronização de catálogo criada na V12.11 também depende da confirmação dos endpoints oficiais de listagem de provedores e jogos da PlayFiver.

---

## Sumário

- [Tecnologias](#tecnologias)
- [Recursos implementados](#recursos-implementados)
- [Instalação no XAMPP](#instalação-no-xampp)
- [Configuração do ambiente](#configuração-do-ambiente)
- [Migrations](#migrations)
- [Criar administrador](#criar-administrador)
- [Painel administrativo](#painel-administrativo)
- [Home / Cassino](#home--cassino)
- [Carteira e pagamentos](#carteira-e-pagamentos)
- [PlayFiver](#playfiver)
- [Segurança](#segurança)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Histórico de atualizações](#histórico-de-atualizações)
- [Status conhecido / próximos passos](#status-conhecido--próximos-passos)

---

## Tecnologias

- PHP **8.2+**
- Composer / PSR-4
- MySQL ou MariaDB
- PDO
- cURL
- OpenSSL
- HTML, CSS e JavaScript puro
- XAMPP no Windows
- Apache com VirtualHost

Dependências PHP declaradas em `composer.json`:

```json
{
  "php": "^8.2",
  "ext-pdo": "*",
  "ext-pdo_mysql": "*",
  "ext-json": "*",
  "ext-curl": "*",
  "ext-openssl": "*"
}
```

---

# Recursos implementados

## Autenticação de jogadores

- Cadastro com **CPF + celular + senha**.
- E-mail é opcional para contas novas.
- Login aceita CPF, telefone ou e-mail legado.
- CPF e telefone possuem validação e índice único.
- Sessão por token.
- Perfil do jogador com dados pessoais mascarados quando necessário.
- Perfil e carteira foram unificados em uma única página.

## Carteira / Ledger

- Ledger imutável.
- Saldos nunca são alterados diretamente na tabela de usuários.
- Contas separadas por tipo:
  - `CASH` → exibido ao jogador como **SALDO**.
  - `BONUS` → exibido como **BÔNUS**.
  - `AFFILIATE` → conta reservada para afiliados.
- Valores armazenados em **centavos inteiros**.
- Movimentações possuem idempotência.
- Tipos técnicos são traduzidos para o jogador:
  - `WITHDRAWAL_HOLD` → **SAQUE**.
  - operações de depósito → **DEPÓSITO**.
- A linha de movimentação exibe somente data/hora abaixo do valor, sem repetir “SALDO”.

## Pagamentos / Pixup

- Gateways configuráveis pelo banco.
- Prioridade separada para depósitos e saques.
- Pixup cash-in com OAuth.
- Geração de PIX copia-e-cola.
- QR Code gerado no navegador.
- Proteção contra duplo clique.
- Idempotency key reaproveitada durante a tentativa.
- Polling de status do depósito.
- Webhook idempotente.
- Credenciais criptografadas no banco.
- Simulação de confirmação disponível somente em ambiente local/sandbox.
- Saques ainda não estão liberados para produção.

---

# Instalação no XAMPP

Clone ou copie o projeto para:

```text
C:\xampp\htdocs\mz90
```

Instale as dependências:

```bat
cd C:\xampp\htdocs\mz90
composer install
```

Configure o Apache para usar a pasta `public` como DocumentRoot.

### VirtualHost recomendado

```apache
<VirtualHost *:80>
    ServerName mz90.local
    DocumentRoot "C:/xampp/htdocs/mz90/public"

    <Directory "C:/xampp/htdocs/mz90/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

No arquivo `hosts` do Windows:

```text
127.0.0.1 mz90.local
```

Depois reinicie o Apache.

Acessos principais:

```text
Site:   http://mz90.local
Admin:  http://mz90.local/admin
Health: http://mz90.local/health
```

---

# Configuração do ambiente

Copie `.env.example` para `.env` e ajuste os valores locais.

Exemplo:

```env
APP_NAME=MZ90
APP_ENV=local
APP_DEBUG=true
APP_KEY=change-me-to-a-long-random-secret
APP_URL=http://mz90.local
APP_BASE_PATH=

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=igaming
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

O arquivo `.env` **não deve ser enviado ao GitHub**. Ele já está listado no `.gitignore`.

Para instalação inicial automática:

```bat
C:\xampp\php\php.exe bin\setup.php
```

O setup cria um `APP_KEY` seguro quando ainda houver placeholder, cria o banco e executa as migrations.

---

# Migrations

Para atualizar um ambiente existente:

```bat
C:\xampp\php\php.exe bin\migrate.php
```

Migrations presentes atualmente:

```text
001_initial.sql
002_payments.sql
003_gateway_admin.sql
004_admin_dashboard.sql
005_user_admin_profile.sql
006_user_identity.sql
007_optional_email_username.sql
008_casino_catalog.sql
008_withdrawals.sql
009_playfiver_credentials.sql
009_withdrawal_rules.sql
010_platform_modules.sql
011_casino_api_source.sql
012_providers_announcements.sql
013_game_access_count.sql
014_casino_categories.sql
015_playfiver_catalog_sync.sql
```

> Há também um arquivo legado `003_gateway_admin.sql.tmp`; ele não representa uma migration adicional do fluxo normal.

---

# Criar administrador

Exemplo:

```bat
C:\xampp\php\php.exe bin\create-admin.php admin@local.test "Administrador" "TroqueEstaSenha123!"
```

---

# Painel administrativo

O Admin usa um tema **grafite / preto / vermelho**, seguindo o visual atual aprovado para a plataforma.

## Dashboard

- Usuários online.
- Total de cadastros.
- Cadastros do dia e período.
- Saldos totais.
- Métricas financeiras.
- Depósitos e saques.
- Gráficos e últimas movimentações.

## Usuários

- Busca e filtros.
- Perfil detalhado.
- CPF, celular e e-mail.
- Status da conta.
- Histórico de sessão.
- Carteira e ledger.
- Ações administrativas auditadas.
- Alterações de saldo passam pelo WalletService/ledger, nunca por edição direta.

## Financeiro

- Painel de depósitos.
- Filtros por gateway e status.
- Detalhes da transação.
- Histórico de webhooks.
- Conciliação.
- Regras e estrutura de saques preparadas, ainda sem payout de produção ativado.

## Gateways

- Ativar/desativar gateways.
- Separação de depósito e saque.
- Prioridade operacional.
- Valores mínimo/máximo.
- Credenciais criptografadas.
- Configuração Pixup.

## Jogos

O menu Jogos inicia recolhido e utiliza seta SVG para abrir/fechar submenus.

Submenus atuais:

- **Catálogo de jogos**.
- **Categorias**.
- **Provedores**.
- **Credenciais das APIs**.
- **Histórico de apostas** — em preparação.
- **RTP observado** — em preparação.

### Catálogo de jogos

- Busca por nome/código.
- Filtro por API.
- Filtro por provedor.
- Miniatura do jogo.
- Código externo.
- Provedor.
- Origem/API.
- Status ativo/inativo.
- Popular.
- Total de acessos configurável.
- Criar, editar e excluir usando modal.
- Paginação.
- Capas administrativas em tamanho controlado.
- Origem `MANUAL` ou `PLAYFIVER`.

### Categorias de jogos

Gerenciador próprio no Admin.

Permite:

- Criar categoria.
- Editar nome/código/ícone.
- Ativar/desativar.
- Definir ordem.
- Excluir categoria sem jogos vinculados.

Categorias padrão preparadas:

- Slots.
- Pescaria.
- SportBet.
- Roleta.

O catálogo público consome as categorias publicadas no Admin.

### Provedores

- Cadastro manual.
- Código e nome.
- Origem/API.
- Ativar/desativar.
- Upload manual da logo.
- Exclusão somente quando não houver jogos vinculados.
- Logos administradas manualmente e preservadas durante sincronizações.

### Credenciais PlayFiver

- Agent Code.
- Agent Token.
- Agent Secret.
- Base URL.
- Segredos armazenados de forma criptografada.
- Credenciais nunca retornam em texto puro ao browser.

## Promoções

- O menu Promoções mostra **somente conteúdo promocional**.
- Não mistura banners da home/lobby.
- Criar, editar, ativar/desativar e excluir promoções.
- Upload de imagem.
- Início/fim opcional.
- Exibição automática no site quando publicada.

## Afiliados

- Links/códigos de indicação.
- Ativação/desativação.
- Registro de visitas.
- Relatório básico.

## Aparência

Mantém cards e funcionalidades visuais já implementadas.

### Identidade visual

- Nome da plataforma.
- Logo.
- Favicon.
- Cor principal.
- E-mail de suporte.
- Telefone.
- Texto institucional do rodapé.
- Redes sociais.
- Modo manutenção.

O favicon cadastrado é utilizado como **favicon real da aba do navegador** tanto no site quanto no Admin/login.

### Banners

- Carrossel principal separado dos banners de lobby.
- Cadastro/edição em modal.
- Exclusão com confirmação.
- Ativação/desativação.
- Ordem.
- Upload de imagem.
- Slider automático.
- Apenas bolinhas inferiores no slider; setas laterais foram removidas.

### Novidades

- Cadastro de textos pelo Admin.
- Ordem e status.
- Exibição na faixa da Home.
- Uma mensagem atravessa completamente o marquee antes da próxima começar.

### Outros cards visuais

Estrutura preparada para:

- Temas e layout.
- Pop-ups e modais.
- Ícones flutuantes.
- Download do aplicativo.

## Auditoria

Alterações administrativas importantes são registradas pelo `AuditLogger`.

---

# Home / Cassino

A Home/Cassino usa o mesmo conceito visual do Admin: **preto, grafite e vermelho**.

## Banners

- Carrossel principal no topo.
- Auto-play.
- Dots de navegação.
- Sem setas laterais.
- Três posições para lobby:
  - esquerda grande;
  - direita superior;
  - direita inferior.

## Provedores

- Faixa horizontal rolável.
- Logos com card superior aproximadamente `130 × 50 px`.
- Nome não é exibido ao lado da logo.
- Botão “Todos” com altura compatível com os cards dos provedores.
- Clique na logo abre o catálogo daquele provedor.
- Nos blocos de jogos por provedor, a logo aparece sem background/borda adicional.
- Cada provedor exibe até **12 jogos na Home**.
- Botão **Ver todos** abre o catálogo completo daquele provedor.

## Jogos

- Cards responsivos.
- Capa clicável.
- Nome do jogo.
- Provedor.
- Indicador verde.
- Texto **Acessos:** com valor configurável pelo Admin.
- Botão de favoritos com coração.
- Favoritos salvos no navegador do jogador.
- Filtros no catálogo:
  - Todos.
  - Popular.
  - Recente.
  - Favoritos.
  - Categorias publicadas pelo Admin.

> Atualmente, clicar na capa abre a página interna do jogo. A sessão real do provedor ainda não é criada até a integração PlayFiver estar concluída.

## Destaques

- Jogos marcados como Popular/Destaque podem aparecer na área de destaque.
- Demais jogos ativos continuam sendo exibidos por provedor; a Home não é limitada somente aos destaques.

## Categorias públicas

Cards com ícones vetoriais no tema da plataforma.

Exemplos:

- Todos.
- Slots.
- Pescaria.
- SportBet.
- Roleta.

## Novidades

- Faixa com background compatível com o tema.
- Texto maior e legível.
- Mensagens administráveis.
- Uma mensagem por vez.

## Cabeçalho

- Logo cadastrada em Identidade visual.
- Saldo do jogador.
- Avatar SVG levando ao Perfil.

## Perfil + Carteira

Perfil e carteira ficam na mesma tela:

- Dados da conta.
- Saldo disponível.
- Conta SALDO.
- Conta BÔNUS.
- Depósito.
- Saque (estrutura visual; payout ainda pendente).
- Histórico de movimentações.
- Logout.

## Menu inferior

Ícones SVG dedicados para:

- Início / Topo.
- Promoção.
- Depósito (posição central).
- Convidar.
- Perfil.

Quando o jogador rola a Home, o rótulo **Início** muda para **Topo**.

## Footer

- Logo centralizada.
- Texto institucional configurável pelo Admin.
- Texto centralizado/justificado.
- Fale conosco.
- E-mail/telefone quando configurados.
- WhatsApp, Telegram, Instagram e Facebook.
- Informação 18+ e jogo responsável.
- Direitos autorais em duas linhas:

```text
© 2026 MZ90.
Todos os direitos reservados.
```

## Fundo visual

- Bordas externas escurecidas.
- Background externo com arte discreta/transparente em tons escuros para não competir com o conteúdo.

---

# Carteira e pagamentos

A carteira usa contas separadas e ledger imutável.

### Regras importantes

1. Nunca editar saldo diretamente no usuário.
2. Toda mutação passa pelo WalletService/ledger.
3. Valores monetários são armazenados em centavos.
4. Transações sensíveis usam idempotência.
5. Saques reais dependem de um gateway de payout configurado e validado.

Documentação complementar:

```text
docs/PAYMENTS.md
docs/WITHDRAWALS.md
docs/WITHDRAWAL-RULES.md
docs/RECONCILIATION.md
```

---

# PlayFiver

## O que já existe

- Tela de credenciais no Admin.
- Armazenamento criptografado.
- Origem `PLAYFIVER` nos provedores.
- Origem remota nos jogos.
- Estrutura de sincronização criada na V12.11.
- Serviço:

```text
src/Modules/Casino/PlayfiverCatalogSyncService.php
```

- Sincronização por CLI preparada:

```bat
C:\xampp\php\php.exe bin\sync-playfiver.php
```

ou:

```bat
composer casino:sync-playfiver
```

## Importante — estado atual da sincronização

A V12.11 implementou o fluxo de sincronização, mas os endpoints de **listagem de provedores e jogos** usados inicialmente não foram confirmados pela documentação oficial acessível.

No ambiente real foi recebido:

```text
Resposta inválida (esperado JSON; recebido text/html; charset=UTF-8)
```

Isso indica que a URL consultada retornou HTML em vez de uma API JSON.

Portanto, antes de considerar a sincronização de catálogo PlayFiver concluída, é necessário confirmar na documentação oficial:

```text
Base URL da API de catálogo
Endpoint para listar provedores
Endpoint para listar jogos
Método HTTP e autenticação exigida
Formato JSON das respostas
Paginação, se existir
```

Enquanto esses endpoints não forem confirmados:

- o catálogo manual/local continua funcionando;
- provedores e jogos existentes não devem ser apagados;
- logos continuam sendo adicionadas manualmente;
- não deve ser assumido que todos os jogos PlayFiver foram importados.

## Ainda pendente na integração real

- Launch real de jogos.
- Criação segura de sessão.
- Callbacks de aposta.
- Débito/crédito no ledger a partir das rodadas.
- Cancelamentos/reembolsos.
- Histórico real de apostas.
- RTP observado calculado a partir de apostas liquidadas.
- Contagem real de jogadores online.

---

# Segurança

- `.env` fora do Git.
- Senhas nunca armazenadas em texto puro.
- Segredos de gateways/APIs criptografados.
- Prepared statements/PDO.
- Ledger imutável.
- Idempotência para operações financeiras.
- Webhooks idempotentes.
- Rotas administrativas exigem autenticação separada.
- Upload limitado a formatos permitidos.
- Admin não possui edição direta de saldo.
- Conteúdo público só exibe registros ativos/publicados.

---

# Estrutura do projeto

```text
mz90/
├── bin/
│   ├── create-admin.php
│   ├── migrate.php
│   ├── setup.php
│   ├── test.php
│   ├── reconcile-payments.php
│   └── sync-playfiver.php
├── database/
│   └── migrations/
├── docs/
├── public/
│   ├── admin.php
│   ├── app.php
│   ├── index.php
│   ├── admin-assets/
│   ├── assets/
│   └── uploads/
├── src/
│   ├── Core/
│   ├── Integrations/
│   └── Modules/
├── storage/
│   └── logs/
├── tests/
├── themes/
├── vendor/
├── composer.json
├── .env.example
└── README.md
```

---

# Histórico de atualizações

## Fundação / primeiras versões

- PHP 8.2 puro + Composer/PSR-4.
- Auth de jogadores e Admin separados.
- WalletService + ledger imutável.
- Contas CASH/BONUS/AFFILIATE.
- Painel inicial.
- Gateways configuráveis.
- Pixup cash-in.
- QR PIX e proteção contra duplo clique.
- Dashboard administrativo.
- Gestão de usuários.
- Financeiro de depósitos.
- CPF e telefone obrigatórios no cadastro.
- Login múltiplo por CPF/celular/e-mail.

## Aparência / V10

- Design Admin grafite/preto/vermelho aprovado.
- Menu Jogos com submenu recolhível.
- Cards da área Aparência preservados.
- Identidade visual gerenciada dentro de Aparência.
- Logo e favicon com upload.
- Recomendações de dimensões.
- Banners principal/lobby separados.
- Exclusão de banners.
- Modais para edição sem troca de página.
- Transições nos modais.
- Promoções mantidas no menu lateral.

## V11

- Novo tema administrativo inspirado na referência grafite/vermelha.
- Catálogo substituído por **Gerenciamento de Jogos API**.
- Busca, filtros, status, Popular e paginação.
- Cadastro/edição/exclusão via modal.
- Promoções corrigidas para não misturar banners gerais.

### V11.1

- Capas do catálogo administrativo ajustadas para tamanho fixo.

### V11.2

- Correção definitiva das capas.
- Topo do Admin alinhado.
- Sidebar escondida na tela de login.

### V11.3

- Menu Promoções exibe somente conteúdo promocional.

## V12

- Home e Cassino integrados ao catálogo.
- Provedores na Home.
- Até 12 jogos por provedor.
- Novidades administráveis.
- Footer configurável.
- Menu inferior Início/Topo.

### V12.1

- Restauração visual da Home após regressão.
- Banners principal e lobby preservados.
- Fallback seguro quando migration não está aplicada.

### V12.2

- Exclusão de provedores vazios.
- Footer corrigido.
- Redes sociais/atendimento.
- Texto institucional configurável.
- Todos os jogos ativos passam a aparecer por provedor, não apenas destaques.

### V12.3

- Home/Cassino adotam o mesmo tema grafite/preto/vermelho do Admin.
- Footer centralizado.
- Texto extra do rodapé removido.

### V12.4

- Provedores passam a mostrar somente logo.
- Novidades com fundo próprio.
- Scroll interno do menu Admin removido no desktop.
- Direitos autorais divididos em duas linhas.

### V12.5

- Logos de provedores em formato maior.
- Marquee sequencial.
- Menu Jogos começa fechado.
- Logo real no cabeçalho.
- Avatar de perfil.
- Capa abre página do jogo.

### V12.6

- Cards menores na Home.
- Clique em provedor abre todos os jogos dele.
- Filtros Todos/Popular/Recente/Favoritos.
- Favoritos por coração.
- Slider sem setas.
- Total de acessos configurável no Admin.
- Ícones SVG nas categorias.
- Menu inferior com ícones SVG.
- Depósito movido para o centro.
- Convidar adicionado.

Migration:

```text
013_game_access_count.sql
```

### V12.7

- Logos dos blocos por provedor alinhadas à esquerda.
- Textos dos cards aumentados novamente.
- Perfil + carteira unificados.
- Tradução amigável das contas e movimentações.
- Gerenciador de categorias no Admin.

Migration:

```text
014_casino_categories.sql
```

### V12.8

- Logos dos blocos por provedor sem fundo extra.
- Faixa superior reduzida para aproximadamente 130×50.
- “Total de acessos” alterado para “Acessos:”.
- Histórico sem palavra SALDO na linha de data.
- Borda externa escurecida.
- Background visual discreto atrás da plataforma.

### V12.9

- Botão Todos equalizado com os provedores.
- Logos dos blocos sem background/borda/sombra.
- Removido texto técnico da página do jogo.

### V12.10

- Favicon cadastrado em Aparência passa a ser usado na aba do navegador.
- Funciona no site, Admin e login.

### V12.11–V12.13 — histórico corrigido

Essas versões foram experimentais na integração PlayFiver. Elas chegaram a testar uma sincronização externa de catálogo que misturava PlayFiver com Games2API/FiverScan. Essa premissa foi posteriormente identificada como incorreta e **foi removida integralmente na V12.14**.

Os arquivos históricos dessas versões são mantidos apenas para rastreabilidade. O estado atual do código **não depende de Games2API/FiverScan**.

### V12.14 — PlayFiver separada + catálogo local

- PlayFiver usa apenas `https://api.playfivers.com`, Agent Code, Agent Token e Agent Secret.
- Game launch em `POST /api/v2/game_launch`.
- Callback em `/api/webhooks/casino/playfiver` e `/playfiver/webhook`.
- Eventos `BALANCE`, `Bet`, `Win` e `WinBet` processados no `WalletService`/ledger.
- `txn_id` é idempotente; `user_after_balance` remoto nunca sobrescreve a carteira local.
- Catálogo passa a ser local e versionado por migration.
- `016_playfiver_documented_catalog.sql` cadastra **13 provedores e 879 jogos** da listagem documental usada como referência.
- Logos de provedores permanecem manuais; a migration nunca altera `logo_path`.
- Capas de jogos são adicionadas apenas quando há correspondência confiável com a base funcional fornecida; não são inventadas capas/códigos.
- Categorias adicionais `LIVE_CASINO` e `CRASH` evitam classificar cassino ao vivo como SportBet.

Migration:

```text
016_playfiver_documented_catalog.sql
```

---

# Status conhecido / próximos passos

Prioridades atuais do projeto:

1. Homologar a integração PlayFiver V12.14 com credenciais reais e callback em domínio HTTPS público.
2. Implementar histórico de apostas no Admin usando os eventos reais do ledger.
3. Implementar RTP observado somente a partir de apostas liquidadas reais.
4. Implementar payout/saques reais após definição do gateway.
5. Persistir favoritos no backend caso seja desejado sincronizar entre dispositivos.

---

# GitHub

Antes do primeiro push:

```bat
git init
git add .
git commit -m "MZ90 - estado atual da plataforma"
git branch -M main
git remote add origin SEU_REPOSITORIO_GITHUB
git push -u origin main
```

Não envie arquivos sensíveis:

```text
.env
storage/logs/*.log
credenciais privadas
backups de banco
```

A pasta `vendor/` já está ignorada e deve ser recriada no destino com:

```bat
composer install
```

---

## Observação final

Este repositório representa o estado atual de desenvolvimento do MZ90. Abertura de jogos e callbacks PlayFiver estão implementados, mas devem ser considerados homologados para dinheiro real somente após validação com credenciais reais, callback HTTPS público e testes de liquidação/idempotência no ambiente de produção.

### PlayFiver atual

Consulte `PLAYFIVER-STATUS.md` e `ATUALIZACAO-V12.14.md`. O estado atual não possui qualquer dependência de Games2API/FiverScan.


### Atualização 18/09/2026 — Submenu de promoções
- Adicionadas oito opções de navegação na área pública de Promoções: Níveis VIP, Cupons, Check-in diário, Roleta de boas-vindas, Envelope vermelho, Baús e indicações, Histórico de bônus e Histórico de níveis.
- Submenu responsivo com seleção ativa, acessibilidade por botões e painel contextual.
- **Escopo:** somente interface e navegação. Resgate de cupons, check-in, sorteios, premiações e históricos reais ainda exigem regras de negócio, endpoints e controles administrativos; nenhum prêmio ou saldo é concedido por esta alteração.

### Correção 21/09/2026 — Submenu vinculado à navegação
- As oito opções agora abrem em um menu suspenso acima do botão **Promoção** da barra inferior, em vez de aparecerem como grade dentro da página.
- O botão expande/recolhe o menu; selecionar uma opção navega até o conteúdo e fecha o menu. Escape e clique fora também fecham.

### Correção 21/09/2026 — Promoções visíveis nos dois menus
- Corrigido bug na área pública: `section('promotions')` fechava o submenu imediatamente após a abertura; agora o estado aberto é restaurado após a troca de seção.
- Adicionado submenu recolhível **também na barra lateral do painel administrativo**, com os oito itens solicitados, seleção e painéis informativos.
- Funcionalidades de prêmios, resgates e regras de bônus permanecem pendentes de backend; não são simuladas como operacionais.
- Cache busting do JavaScript administrativo atualizado.

### Correção V3 — navegação da home
A regra `.mobile-stage > *` aplicava `position: relative` a todos os filhos diretos, anulando o posicionamento fixo da barra inferior e do submenu. Adicionadas regras explícitas para restaurar ambos, com empilhamento correto e atualização de versão dos assets para invalidar cache.

### Correção V4 — submenus somente no painel administrativo (21/09/2026)
- Removido o submenu com oito itens da interface pública; **Promoção** volta a abrir somente a página pública original de campanhas.
- Restaurada a navegação original da home, preservando a correção V3 que mantém a barra inferior fixa.
- O menu lateral do **admin > Promoções** continua com os oito submenus recolhíveis e seus painéis informativos (sem premiações ativas).
- Eliminada a duplicidade do evento de clique do item pai no admin e atualizadas as versões de cache do CSS/JS públicos e administrativos.
- O pacote de distribuição não inclui `.env` nem arquivos de log; mantenha seu `.env` atual no servidor.


## V5 — Configurações administrativas de promoções (22/09/2026)

- Home e navegação pública permanecem como na V4. **Promoções →** oito submenus somente no Admin.
- CRUD persistido de Níveis VIP, Cupons, Check-in diário, Roleta de boas-vindas, Envelope vermelho e Baús e indicações. Editar, adicionar e excluir usam autenticação administrativa, validação no servidor e auditoria.
- Migration `018_promotion_configurations.sql` cria a tabela e cadastra 20 VIPs desabilitados. VIP 1–5 usam valores da referência; VIP 6–20 possuem metas ilustrativas e bônus zero, que exigem revisão antes da operação.
- Roleta: regra configurável de depósito mínimo, rodadas por depósito e por indicado cadastrado. Baús: meta de indicados, depósito mínimo do indicado, bônus ao indicador, rollover e limite de resgates.
- Os históricos são informativos enquanto não houver módulo de eventos/transações de bônus. **Nenhum crédito, recompensa, giro ou resgate automático foi conectado ao ledger/carteira nesta versão.** A configuração habilitada não libera pagamentos.
- Instalação: executar `php bin/migrate.php` depois de fazer backup do banco. Preservar o `.env` existente (não incluído no ZIP). Testar operações em homologação antes de usar em produção.


### V6 — Correção de inicialização do login do Admin
O modal de configurações promocionais possui botões próprios. Ele não deve ser inicializado pelo gerenciador genérico de modais `editor-*`, que exigia `.modal-cancel` e interrompia o JavaScript antes de registrar o formulário de login. Limitado o gerenciador aos modais `editor-*` e adicionado tratamento de Escape no novo modal. Não altera senhas, contas, tokens, configurações de banco nem dados existentes.


### V9 — Ajuste de cores do menu lateral (22/09/2026)
- O menu lateral da Home agora usa a paleta atual do projeto: fundo grafite, cartões cinza-escuro e destaques vermelhos.
- Cores baseadas nas variáveis do tema (`--bg`, `--surface`, `--surface2`, `--text`, `--muted`, `--line` e `--accent`), sem alteração de layout ou navegação.
- Atualizada a versão do CSS no `public/app.php` para invalidar cache do navegador.
- Admin, barra inferior, login e módulos não foram alterados. Preserve seu `.env` ao instalar.


### V11 — Ocultar categorias inativas da Home (22/09/2026)
- Quando nenhuma categoria está habilitada e possui jogos publicados, a seção de categorias da Home desaparece por completo, inclusive o cartão "Todos" e o espaço da faixa.
- Havendo ao menos uma categoria habilitada e com jogos, a faixa funciona como antes, mantendo "Todos" e as categorias publicadas.
- Os filtros da página Cassino não são modificados. O menu lateral, o cabeçalho, a barra inferior e o Admin permanecem inalterados.
- Versões do CSS e JavaScript atualizadas para evitar cache da versão anterior. Não exige migração.


### V12 — correção de migração 019 (MySQL 3780)

A migration `019_promotion_redemptions.sql` usa agora `utf8mb4_unicode_ci` nas três novas tabelas, igual às chaves `CHAR(36)` das tabelas de usuários, lançamentos e transações. A criação da coluna e do índice do cupom também é idempotente para permitir repetir `php bin/migrate.php` após uma falha parcial. Faça backup antes da migração; não apague tabelas ou dados.


### V12.2 — Correção da migração MySQL (erro 2014)

A migração `019_promotion_redemptions.sql` executa SQL dinâmico via `PREPARE`/`EXECUTE`.
Quando o campo ou índice já existe, a V12.1 usava `SELECT 1` como operação substituta,
que pode deixar resultados pendentes e causar `SQLSTATE[HY000] 2014` em conexões sem buffering.
Na V12.2, as operações substitutas são `DO 0` e o comando `bin/migrate.php`
consome e fecha os resultados de cada instrução e ativa buffering na conexão CLI.
A migração 019 continua executável após falha parcial; não é necessário remover tabelas.
Antes de atualizar em produção: faça backup do banco, preserve o `.env` e execute
`php bin/migrate.php`. Teste em homologação: não houve teste com banco MySQL conectado neste ambiente.


## V12.3 — Redesign do check-in na Home

- Layout vermelho e grafite, hero, sequência, barra de progresso e cards responsivos para cada dia habilitado no Admin.
- Dia atual, dias concluídos e bloqueados são calculados a partir do status autenticado e do histórico real, sem prêmios fictícios.
- Resgate existente e atualização da carteira preservados; sem mudanças no banco ou no painel administrativo.
- Em caso de requisitos de depósito/aposta, estes são exibidos no card disponível; regras e virada às 21h Brasília preservadas.
- Instalação: substituir arquivos preservando `.env`; nenhuma migração nova necessária.
- Validar o visual no servidor, tanto com usuário autenticado como deslogado, antes da publicação em produção.

## V12.4 — Check-in limpo e focado nos cards

- A tela do módulo **Nível e Check-in** agora abre em modo limpo: esconde a vitrine de promoções, o cabeçalho genérico “Benefícios” e a lista textual de regras, deixando apenas o botão de voltar e o bloco visual do check-in.
- Removido o histórico e os textos redundantes do módulo de check-in para priorizar somente os cards, progresso e ação de resgate.
- O card **Disponível** passou a usar destaque esverdeado; cards concluídos e bloqueados mantêm estados visuais próprios.
- O último card só vira “card especial” quando houver uma sequência maior (7+ dias), evitando distorções quando existem apenas 2 dias cadastrados.
- Atualizados os versionamentos de assets no `public/app.php` para forçar limpeza de cache após a substituição dos arquivos.
- Não exige nova migração. Recomenda-se substituir os arquivos, preservar o `.env` e limpar o cache do navegador com `Ctrl + F5`.


## V12.5 — Retorno estilizado e requisitos por dia

- Botão “Voltar às promoções” atualizado para o visual grafite/vermelho do MZ90, com hover e foco acessível.
- Cada card do check-in mostra depósito mínimo, apostas necessárias e rollover conforme o dia cadastrado no Admin, inclusive dias futuros ou bloqueados. Valores zerados são explicitamente mostrados, sem inventar requisitos.
- Grade responsiva: três colunas na maioria das telas, duas em celulares estreitos. Estados de resgate e cor verde do card disponível preservados.
- Não modifica banco, regras de elegibilidade ou transações. Não requer migração. Limpe o cache após substituir os arquivos.

## V13 — VIP: painel do cliente, metas e bônus de upgrade

- Novo painel VIP responsivo em **Home → menu lateral → VIP** com identidade visual vermelha/grafite, 20 níveis existentes sujeitos à ativação individual no Admin, volume de apostas registrado, progresso da próxima faixa e cartões de bônus por nível.
- O volume é calculado **no servidor**, a partir dos lançamentos de débito confirmados da PlayFiver; o navegador não envia valores de apostas. O volume mensal é exibido apenas como informação, sem penalidade de manutenção implementada.
- Resgate real e individual do **bônus de upgrade** quando a meta for atingida, com validação de usuário ativo, nível habilitado, prêmio positivo e histórico de resgate. O servidor bloqueia o registro do usuário durante o resgate, impedindo duas concessões simultâneas da mesma campanha. Crédito e rollover reutilizam a transação financeira da V12.
- No Admin, o rótulo da meta VIP foi alinhado à regra efetivamente utilizada: **apostas acumuladas**. As metas e bônus dos 20 níveis existentes não foram modificados nem ativados automaticamente. Os níveis 6–20 permanecem com bônus zero até configuração manual.
- **Não implementado nesta versão:** bônus diário/semanal/mensal, manutenção mensal e downgrade. Não há botões fictícios para essas ações; a interface informa essa limitação explicitamente.
- **Instalação:** backup do banco e dos arquivos; substituição mantendo `.env`; não exige migração adicional. Após instalar, habilite e configure os níveis desejados em Promoções → Níveis VIP, e atualize o navegador com `Ctrl + F5`.
- **Validação:** executar os cenários com MySQL real em homologação antes de liberar resgates em produção: conta sem apostas, meta atingida, bônus zerado, dupla solicitação, rollover pendente e nível desativado. Os testes de integração com MySQL e o navegador não foram executados no ambiente de geração.


## V13.1 — VIP recorrente e manutenção

Consulte `docs/V13_1_VIP.md` para migração obrigatória 020, configuração administrativa, automação cron, regras de nível, resgates e checklist de homologação.


## V13.2 — Regras visíveis em cada módulo da Home

- Todas as 12 páginas de Promoções exibem agora a seção recolhível **Como funciona • Regras e requisitos**, integrada ao tema vermelho/grafite.
- Textos descrevem o funcionamento e o estado real de disponibilidade de cada módulo: apenas cupons, check-in e VIP possuem resgates desta fase. Não é anunciada como funcional uma promoção ainda não implementada.
- Condições (metas, valores, percentuais, requisitos e rollover) são geradas dinamicamente a partir das configurações **ativas e públicas** do Admin; códigos de cupons não são expostos.
- Check-in mantém os cards como conteúdo principal e apresenta regras em seção opcional abaixo, sem restabelecer a antiga listagem de requisitos no topo.
- Nenhuma alteração no banco, nos créditos ou no processamento financeiro. Sem migração nova; preservar `.env` e atualizar os arquivos/cache.
- Recomenda-se validar a interface em dispositivos móveis e confirmar regras legais e comerciais antes de publicar campanhas em produção.


## V14 — Baú do Tesouro funcional por depósito confirmado

- Cadastro por link exclusivo de jogador, vínculo imutável e uma conta indicada por indicador.
- Baús na Home: link de convite, progresso por campanha, cards verdes quando disponíveis, resgate em transação segura com carteira e rollover.
- O bônus **só fica resgatável quando o número configurado de indicados realizar o depósito mínimo configurado no Admin**, contabilizando apenas pagamentos confirmados (`PAID`).
- Admin: cadastro e ativação de campanhas em **Promoções → Baús e indicações**, resumo de indicações e pagamentos e histórico de créditos em **Histórico de bônus**.
- Migração obrigatória: `php bin/migrate.php` aplica `021_referral_chests.sql`. Preserve seu `.env` e faça backup. Não há atribuição retroativa de usuários.
- Instruções completas, limitações e plano de testes em `docs/V14_REFERRAL_CHESTS.md`. Verificar com MySQL real em homologação antes de produção.

## V15 — Agência e Indicações

- Nova página da Agência na Home: link de convite, métricas reais, lista de indicados, faixas e histórico de comissões, com layout vermelho e grafite.
- Novo painel administrativo para ativação explícita, regras e relatório; gerenciamento das faixas continua no CRUD de promoções.
- Comissões de indicação direta calculadas por job sobre apostas PlayFiver confirmadas, com registro idempotente e crédito na conta AFFILIATE. Baús mantêm requisito próprio de depósito mínimo; agência não recebe por mero cadastro ou depósito.
- Migration obrigatória `022_agency_commissions.sql`. Agendar `php bin/process-agency.php` no cron; por segurança a agência nasce **desativada**.
- A integração não realiza transferências da conta AFFILIATE para CASH e não cobre estorno de aposta já paga. Antes de produção, testar saldos, reversões e idempotência em homologação com MySQL/PlayFiver.
- Veja `docs/V15_AGENCY.md` para instalação, política e testes.

## V16 — Rebate do Site (próximo módulo)

- Página Rebate na Home com volume de apostas, faixa atual, saldo disponível, histórico e botão Receber, respeitando o tema do projeto.
- Administração das faixas existentes, novo painel para habilitar/desabilitar a campanha, definir valor mínimo de resgate e consultar relatório.
- Migração `023_rebate.sql` cria configuração, contas de acúmulo, eventos de apostas idempotentes e histórico de resgates.
- Processador CLI `bin/process-rebate.php` contabiliza exclusivamente apostas PlayFiver confirmadas após a ativação; taxas por faixa, frações de centavos preservadas; resgate transacional para conta CASH sem rollover adicional.
- Campanha desativada por padrão. Configurar cron após migração e testar em homologação antes de produzir créditos reais.
- Manual detalhado: `docs/V16_REBATE.md` (inclui limitações de reversões/estornos).

### V16.1 — Rebate fixo (sem níveis)

- Taxa única em **Admin → Promoções → Rebate**; remoção da gestão de faixas e dos cards de progressão na Home.
- Migração 024: taxa fixa e histórico de alterações de porcentagem. Migração desativa a campanha anterior, preservando créditos/históricos; administrador deve configurar taxa e reativar.
- O processamento usa a taxa vigente no horário da aposta, não no horário do cron. O volume exibido é apenas informativo.
- Instruções, ressalvas sobre reativação e testes: `docs/V16_1_REBATE_FIXO.md`.


### V16.2 — Correção da tela de configuração do rebate fixo

- Corrigido o seletor do Admin que não chamava o formulário próprio de Rebate após retirar o módulo do CRUD de níveis.
- A tela mostra ativação, taxa fixa e resgate mínimo; a antiga tabela de faixas é ocultada apenas nesta página.
- Falhas no relatório não escondem o formulário: a consulta de configurações é independente e os erros são exibidos.
- Cache CSS/JS atualizado. Nenhuma migração adicional: exige a migração 024 da V16.1, se ainda pendente.
- Atualizar os arquivos da V16.2 preservando `.env` e banco. Validar ativação e créditos em homologação.

## V17 — Fundos de Resgate

- Nova tela do jogador com apostas, ganhos, perda líquida, fundo do dia anterior, status de resgate, faixas e histórico, no tema vermelho/grafite.
- Configuração administrativa para habilitar/desabilitar; faixas CRUD já disponíveis (perda mínima, taxa, rollover), relatório de apurações.
- Apuração diária idempotente a partir de lançamentos PlayFiver `COMPLETED` da conta CASH; resgate transacional pela carteira com rollover usando infraestrutura de bônus existente.
- Sem retroatividade: habilitar passa a valer às 00h do dia seguinte, em Brasília. Só se resgata no dia posterior ao da perda; valores não resgatados expiram.
- **Migração `025_rescue_daily.sql` obrigatória**: faça backup antes de executar `php bin/migrate.php` e preserve `.env`.
- Cron: `php bin/process-rescue.php`, uma vez ao dia após a virada de Brasília. Detalhes, limitações financeiras e plano de validação: [`docs/V17_FUNDOS_RESGATE.md`](docs/V17_FUNDOS_RESGATE.md).
- **Não liberar pagamentos em produção sem homologação real de callbacks, resultados tardios, estornos, timezone, concorrência e rollover**. Não houve integração com MySQL/PlayFiver neste ambiente.


## V18 — retirada da Compensação Semanal independente

- Mantido apenas **Fundos de Resgate diário** como programa de cashback sobre perdas.
- Removidos o item 'Semana' do menu lateral, o módulo da Central de Promoções e o submenu/formulário da campanha semanal no Admin.
- Rejeita criar/editar campanhas antigas do tipo `weekly` no backend e omite essas configurações na API pública.
- Migração 026 desativa configurações históricas semanais sem excluí-las ou alterar saldos/históricos.
- **O bônus VIP semanal não foi removido**. O Rebate fixo também não mudou.
- Backup, preservar `.env`, executar `php bin/migrate.php` e atualizar cache. Guia: `docs/V18_ORGANIZACAO.md`.

## V19 — Roleta de Boas-vindas

- Novo backend `WelcomeRouletteService` com rodadas originadas apenas de depósitos pagos ou cadastros por indicação após a ativação da campanha. Chaves únicas por origem impedem concessão duplicada.
- Giro autenticado transacional no servidor, prêmio aleatório uniforme em centavos, crédito em CASH ou BONUS e histórico/auditoria no ledger.
- Página da Home com roleta ilustrativa, saldo de giros, regras claras, botão de giro e histórico. Admin mantém CRUD e ganhou relatório dos giros.
- Migração **027_welcome_roulette.sql obrigatória**. Preserve o `.env`, faça backup e execute `php bin/migrate.php`.
- Veja `docs/V19_ROLETA_BOAS_VINDAS.md` para implantação, regras, limitações e roteiro de homologação. **Não ative premiações em produção antes de validar estornos e callbacks reais.**

## V19.3 — Giro da Sorte editável após emissão de rodadas

- Removida a trava que impedia editar o Giro da Sorte depois que alguma rodada já havia sido emitida.
- Créditos e giros já existentes permanecem preservados no histórico.
- Ao alterar regras da roleta, a nova configuração passa a valer apenas para novos depósitos/indicações a partir do momento da edição, evitando geração retroativa de rodadas.
- Prêmios, chance de ganho, rollover e requisitos podem ser editados diretamente no único Giro da Sorte cadastrado.
- Não exige nova migração.

## V20 — Envelope Vermelho funcional

- O próximo módulo funcional é o **Envelope Vermelho**.
- Quando a liberação diária estiver ativa, cada jogador pode abrir **1 envelope por dia**.
- O Admin configura valor base mínimo/máximo, multiplicador mínimo/máximo, rollover e mensagem exibida ao jogador.
- O prêmio final é calculado no servidor como `valor base sorteado × multiplicador sorteado`.
- O crédito usa a carteira financeira existente e respeita o rollover configurado.
- Foi criado histórico independente dos envelopes para preservar auditoria e impedir duplicidade diária.
- A página pública recebeu um card premium com animação de abertura, resultado e histórico.
- O Admin recebeu relatório dos envelopes abertos.
- Nova migration obrigatória: `028_red_envelope.sql`.

## V20.1 — Envelope Vermelho por popup, dia da semana e depósito

- O Envelope Vermelho deixou de ser uma página pública e foi removido do menu lateral da Home.
- O benefício aparece como popup automático ao entrar na conta somente quando o jogador estiver elegível.
- O Admin escolhe os dias da semana em que o envelope pode existir (segunda a domingo).
- O jogador precisa ter depósito `PAID` confirmado no próprio dia; sem depósito, o popup não aparece e não há direito ao benefício.
- A recompensa é calculada como uma porcentagem configurável sobre o total de depósitos confirmados do jogador naquele dia, respeitando um depósito mínimo configurável.
- Continua existindo no máximo um resgate por jogador/campanha/dia e os históricos financeiros permanecem preservados.
- O popup mostra apenas a experiência visual e o prêmio final; valores técnicos de base, multiplicador ou rollover não são exibidos ao jogador.
- A migration `029_red_envelope_popup.sql` desativa a liberação automática e zera os dias da semana para exigir revisão segura no Admin após a atualização.

Após atualizar, execute `php bin/migrate.php`, depois configure em **Admin → Promoções → Envelope vermelho**: dias permitidos, depósito mínimo, percentual do depósito, rollover, mensagem e ativação do popup.


## V20.2 — Envelope Vermelho com depósito mínimo semanal

- O campo administrativo **Depósito mínimo no dia** foi alterado para **Depósito mínimo semanal**.
- A soma elegível considera depósitos `PAID` da semana de segunda-feira 00:00 até a próxima segunda-feira 00:00, no horário de Brasília.
- Os dias marcados no Admin definem somente quando o popup pode aparecer (ex.: sexta, sábado e domingo).
- Cada jogador pode abrir **1 Envelope Vermelho por semana**, mesmo que vários dias da semana estejam habilitados.
- O prêmio continua sendo uma porcentagem configurável sobre o total depositado na semana.
- Não é necessária nova migration; a configuração existente `deposit_min_cents` passa a representar o mínimo semanal.

## V21 — Roleta de Saque funcional

- Implementada a **Roleta de Saque** como campanha de progresso separada da carteira principal.
- O Admin configura uma única campanha com:
  - meta para resgate;
  - validade da sessão em dias;
  - quantidade de rodadas gratuitas por dia;
  - prêmio mínimo e máximo por giro;
  - ajuda por indicação válida;
  - rollover aplicado apenas no resgate final.
- Cada giro aumenta somente o saldo interno da campanha. Nenhum giro individual credita CASH/BONUS diretamente.
- O jogador só pode resgatar quando o progresso atingir 100% da meta configurada.
- Indicações ativas feitas durante a sessão podem aumentar o progresso e cada indicado conta uma única vez por sessão.
- Sessões vencidas são marcadas como expiradas e não liberam prêmio.
- Adicionado histórico de giros e relatório administrativo das sessões.
- A nova migration obrigatória é `030_cashwheel.sql`.

### Atualização

```bash
php bin/migrate.php
```

Depois configure em **Admin → Promoções → Roleta de Saque** e ative a campanha após revisar os valores.

### V21.2 — Roleta de Saque: progressão controlada
- O saldo interno da Roleta de Saque **não é creditado na carteira antes da meta**.
- Exceção: resultado específico `CASH_BONUS`, com chance e valores configuráveis no Admin.
- Resultado `NO_WIN`: não acrescenta saldo e exibe "Não ganhou nada, tente novamente".
- Primeiro giro de progresso: faixa configurável de 60% a 90% da meta (máximo 90%).
- Giros seguintes: avanço menor, limitado por percentual configurável da meta.
- Nova migration: `032_cashwheel_no_win.sql`.


## V21.3 — Ajustes mobile e branding dinâmico
- Corrigido o layout mobile do módulo **Roleta de Saque** com wheel, labels e botões responsivos.
- Corrigido o layout mobile do módulo **Giro da Sorte** com wheel e textos adaptados para telas menores.
- A barra do topo da área pública agora permanece **fixa** durante a rolagem.
- O nome da bet no público e no admin agora usa como prioridade o **site_name** configurado em Aparência, evitando exibir o fallback antigo (ex.: NovaBet).
- Ajustado o rótulo de perda da Roleta de Saque para uma versão mais curta no disco da roleta, preservando a mensagem completa no resultado.
- Não há novas migrations nesta versão.


## V21.4 — Correção definitiva mobile
- Sincronizados os arquivos de `assets/` com `public/assets/`, evitando que o navegador carregue CSS/JS antigos.
- Giro da Sorte e Roleta de Saque agora usam dimensões específicas para 390–560px e ficam centralizados no card.
- Labels internas tiveram raio e fonte reduzidos para não invadir fatias vizinhas.
- Header público passou a `position: fixed` com z-index elevado e compensação de altura no conteúdo, permanecendo visível durante toda a rolagem.
- Atualizado cache-busting dos arquivos CSS/JS para V21.4.
- Sem nova migration.

## V21.5 — Alinhamento final da Roleta de Saque + header translúcido
- Refinado o posicionamento dos textos da **Roleta de Saque**, com raios independentes para fatias de prêmio, “não ganhou” e “bônus em moeda”.
- Reduzidos largura, altura e fonte dos labels no mobile para manter o conteúdo centralizado dentro de cada fatia.
- Aplicado visual translúcido no **header fixo**, com blur e transparência semelhantes ao menu inferior.
- Atualizado o cache-busting em `public/app.php` para forçar o carregamento do CSS/JS novos.
- Sem nova migration.

## V21.6 — Correção do centro das fatias da Roleta de Saque
- Corrigido o posicionamento dos textos da **Roleta de Saque**.
- O problema era o ângulo dos labels: eles estavam sendo posicionados na borda entre as fatias, e não no centro de cada segmento.
- Ajustado o offset angular em **22.5°** em todos os breakpoints (desktop e mobile), mantendo os textos centralizados dentro de cada fatia.
- Atualizado o versionamento estático em `public/app.php` para forçar recarga do CSS.
- Sem migration.

## V21.7 — Ajuste fino dos labels especiais da Roleta de Saque
- Reposicionados os labels especiais **"Não ganhou nada"** e **"Bônus em moeda"** para ficarem mais para fora, melhor centralizados nas fatias.
- Ajustado o raio desses dois labels no desktop e no mobile.
- Mantido o alinhamento dos demais valores da roleta.
- Atualizado o cache-buster dos assets em `public/app.php`.
- Sem migration.

## V21.8 — Roleta de Saque: labels todos mais para fora
- Ajustado o posicionamento de **todos os labels** da Roleta de Saque para ficarem mais externos e melhor alinhados nas fatias.
- Inclui os labels monetários, **"Não ganhou nada"** e **"Bônus em moeda"**.
- Refinados também os raios de posicionamento nas quebras responsivas (desktop e mobile).
- Atualizado o cache-buster dos assets em `public/app.php`.
- Sem migration.


## V22 — Sorteio de Cartas + topo do Admin
- Implementado o módulo **Sorteio de Cartas** com coleção `HAPPY`.
- Cada jogador recebe a quantidade de giros diários configurada no Admin.
- Cada giro entrega uma carta entre H, A, P, P e Y; cartas repetidas permanecem no histórico.
- O prêmio só pode ser resgatado após completar `H + A + P + P + Y`.
- Após o resgate, uma nova coleção é iniciada e o histórico é preservado.
- O prêmio da coleção e o rollover são configuráveis no Admin.
- Adicionado relatório administrativo com progresso da coleção e quantidade de coleções concluídas.
- Adicionada migration `033_lottery_cards.sql`.
- Ajustado o branding no topo/menu lateral do Admin: removida a logo, exibido somente o nome configurado da plataforma, com a primeira letra em vermelho.


## V22.1 — Sorteio HAPPY com dificuldade + topo do Admin
- Corrigido o nome da plataforma no topo do Admin para permanecer em uma única linha.
- A primeira letra continua vermelha e o subtítulo **ADMIN** voltou abaixo do nome.
- O Sorteio agora possui 6 resultados visuais: **H, A, P, P, Y e Não ganhou nada**.
- Adicionada configuração de dificuldade/chance de carta útil no Admin: Muito fácil (80%), Fácil (65%), Normal (50%), Difícil (35%) e Muito difícil (20%).
- Quando o giro acerta, o servidor entrega uma carta que ainda falta para HAPPY; quando falha, retorna Não ganhou nada.
- A chance é aplicada no servidor, não apenas no visual.
- Não exige nova migration: o resultado sem prêmio usa `N` no histórico existente e a dificuldade fica no JSON da campanha.


## V22.2 — Sorteio sem mensagens extras e sem piscar
- Removido da página pública o selo de dificuldade/chance de carta útil.
- Removida da tabela principal do Admin a coluna de chance de carta útil; a dificuldade continua configurável ao editar a campanha.
- O Sorteio não recarrega mais todo o card ao final do giro.
- A roleta mantém a posição final e atualiza rodadas, coleção e botões diretamente no DOM, evitando o piscar da janela.
- A animação do Sorteio passou a usar transição contínua, preservando o estado visual ao terminar.
- Sem nova migration.


## V22.3 — Chances ocultas na área pública
- Removidas da Home e páginas públicas todas as informações de probabilidade/chance de ganho dos módulos.
- **Giro da Sorte** não exibe mais percentual de vitória; mantém apenas informações de prêmio e requisitos de rodada.
- **Sorteio HAPPY** não exibe mais chance de carta útil nas regras ou condições publicadas.
- As probabilidades continuam configuráveis e aplicadas internamente pelo Admin/servidor; apenas deixaram de ser exibidas aos jogadores.
- As taxas que não são probabilidades (ex.: rebate, comissão, fundo de resgate) continuam visíveis normalmente.
- Sem nova migration.

## V22.4 — Sorteio HAPPY com letras acumulativas e última letra rara
- As letras H, A, P e Y agora podem se repetir indefinidamente e ficam acumuladas na coleção do jogador (ex.: H x10, A x4, P x7).
- A coleção é concluída quando o jogador possui pelo menos H x1, A x1, P x2 e Y x1.
- Quando falta apenas a última unidade necessária para completar HAPPY, essa letra passa a ter probabilidade interna extremamente baixa por giro; os demais resultados continuam podendo gerar letras repetidas ou “Não ganhou nada”.
- A interface pública agora exibe os contadores acumulados das letras em vez de apenas “Coletada/Faltando”.
- O relatório do Admin mostra os totais acumulados por letra.
- Campos internos de chance continuam ocultos da área pública e dos payloads públicos usados pela Home.
- Sem nova migration.


## V22.7 - Promoções ligadas aos módulos
- Página de promoções com banners premium clicáveis para cada módulo implementado.
- Badges de disponibilidade automática conforme configurações ativas publicadas em `promotion_configurations`.
- Grade de módulos com status `Disponível` / `Em breve`.
- Módulo Troca de Recompensas sem o bloco `Condições publicadas nesta campanha`.


## V22.8 — Ordem dos banners de promoções
- Ordem editorial: Giro da Sorte, Clube VIP, Roleta de Saque, Nível e Check-in, Baú do Tesouro, Troca de Recompensas, Rebate, Agência, Fundos de Resgate, Sorteio HAPPY.
- Campanhas publicadas aparecem antes das que não possuem configuração ativa, respeitando a ordem editorial dentro de cada grupo.
- O badge público diz «Campanha ativa» e não promete resgate/rodadas elegíveis por jogador; disponibilidade individual deve ser validada pelas APIs do módulo.
- Envelope Vermelho permanece exclusivo do popup automático, sem banner que levaria a uma página inexistente.
- Somente front-end; sem migration.

## V22.9 — Promoções com cards compactos e disponibilidade por jogador

### Ajustes realizados
- removidos os banners/quadrões grandes do topo da página de Promoções;
- mantidos apenas os cards compactos no estilo da grade menor;
- removido o texto `Campanha ativa` dos cards;
- cards agora exibem status por jogador:
  - `Disponível` em verde quando existe benefício/rodada/resgate liberado;
  - `Indisponível` em vermelho quando o jogador não possui benefício liberado;
- subtítulo do card passou a exibir o motivo/status real do módulo (ex.: `2 giro(s) disponível(is)`, `Sem rodadas disponíveis`, `Check-in de hoje disponível`, etc.);
- ocultado o bloco `managed-promotions` da interface;
- Promoções agora consultam status real dos módulos por API para refletir disponibilidade individual do jogador.

### Regras dinâmicas implementadas nos cards
- **Giro da Sorte:** disponível quando houver giros disponíveis;
- **Clube VIP:** disponível quando houver upgrade VIP ou benefício recorrente liberado;
- **Roleta de Saque:** disponível quando houver rodadas grátis ou resgate liberado por meta atingida;
- **Nível e Check-in:** disponível quando o check-in do dia estiver realmente elegível;
- **Baú do Tesouro:** disponível quando houver baú resgatável;
- **Troca de Recompensas:** indica uso de código promocional;
- **Rebate:** disponível quando houver saldo mínimo resgatável;
- **Agência e Indicações:** disponível quando houver saldo de afiliado;
- **Fundos de Resgate:** disponível quando houver fundo liberado no dia;
- **Sorteio de Cartas:** disponível quando houver rodadas ou prêmio da coleção liberado.

### Backend
- endpoint `/api/promotions/status` enriquecido com status de elegibilidade do Check-in (`available_today`, progresso e requisitos do dia), sem necessidade de migration.


## V22.10 — Correção da disponibilidade dos cards pós-login
- O status dos cards é atualizado após o login/restauração de sessão (evento `mz:auth-ready`). Antes, a primeira consulta era feita antes da autenticação e ficava congelada.
- Atualização ao entrar na página de Promoções, voltar ao índice, concluir giros/resgates e sair da conta.
- Consultas concorrentes agora usam um número de versão: respostas antigas não podem sobrescrever um estado mais recente.
- Em falha de consulta, não afirmar que o jogador tem zero giros; mostrar estado ainda não confirmado.
- Correção também para Sorteio HAPPY (rodadas diárias), Giro da Sorte, Roleta de Saque e demais cards.
- Arquivos JS e cache-busting sincronizados em `assets/` e `public/assets/`. Sem migration nova.
- Homologar com uma conta que possui rodadas, uma sem rodadas e uma recém-autenticada.
- Teste automatizado com DOM/API simulados: conta não autenticada → login → Sorteio HAPPY com 50 rodadas: card muda para `Disponível` (verde) e apresenta `50 rodada(s) disponível(is)`. Testes reais com banco e conta de produção ainda devem ser feitos em homologação.

## V23.1 — Etapa 1/4: Central de Recompensas

### Escopo implementado nesta entrega
- A página de Promoções ganhou uma **Central de Recompensas** acima dos cards compactos já existentes.
- Área personalizada após login: contagem de módulos com benefício disponível, lista de giros/resgates liberados com acesso direto aos módulos, outros módulos em área recolhível e últimos resgates.
- Históricos monetários são lidos do endpoint existente `/api/promotions/status` (tabela de resgates), sem contabilizar progresso interno de roleta como saldo de carteira.
- Indicadores reutilizam a consulta autenticada de status dos módulos da V22.10, inclusive giros disponíveis do Sorteio HAPPY.
- Atualização ocorre ao abrir Promoções, autenticar, sair da conta, atualizar carteira e após eventos de promoção. As respostas antigas de consultas concorrentes não podem sobrescrever o estado mais recente.
- Na visualização de detalhes, a Central desaparece para não competir com o módulo aberto.
- Responsividade mobile e desktop com componentes no tema escuro/vermelho/verde.
- Nenhuma migração nova ou mudança nas regras de concessão, carteira ou rollover.

### Limites desta etapa
- A Central mostra a situação consultada e **não executa resgates**, que continuam nos serviços atuais do servidor.
- O histórico consolidado representa os últimos resgates retornados por `/api/promotions/status` (máximo 8 nesta interface); não é um extrato integral de depósitos, saques ou apostas.
- Disponibilidade de código promocional exige informar um cupom válido; por privacidade, a Central não testa códigos hipotéticos.
- Os status existentes de cada módulo são reutilizados: homologação financeira, validação de concorrência e auditoria de ledger serão objeto da etapa 4.

### Testes e implantação
- `node tests/rewards-center-smoke.js` valida cenário de 50 rodadas HAPPY, acesso pela Central e histórico com resgate real.
- `node --check public/assets/promotions.js` e `php -l public/app.php` verificam sintaxe.
- Entrega apenas de interface e leitura de APIs existentes: **não exige migration**.

### Próximas etapas da V23
2. Padronização visual premium de todos os módulos e testes em diferentes tamanhos de tela.
3. Gestão administrativa: relatórios de campanhas, alterações e regras com validações.
4. Auditoria financeira: idempotência, concorrência, rollover, integrações e reconciliação antes de operação com dinheiro real.

## V23.2–V23.4 — Padronização premium, Admin e auditoria local
- V23.2: CSS compartilhado de promoções com ritmo visual consistente, foco por teclado e ajustes de mobile. Não altera regras de crédito.
- V23.3: nova Central administrativa de promoções com totais por campanha, status de resgates e últimos 40 registros, em `/admin/api/promotions/overview`.
- V23.4: diagnóstico financeiro administrativo protegido em `/admin/api/promotions/financial-audit`, **somente leitura**, com oito verificações de inconsistência; limite de 100 registros por verificação e sem consulta a gateways externos.
- Não há migration adicional. Fonte: tabelas existentes `wallet_accounts`, `ledger_entries`, `financial_transactions`, `promotion_redemptions`, `promotion_wager_allocations` e `promotion_configurations`.
- Teste de estrutura: `php tests/Unit/PromotionOversightStructureTest.php`. Arquivos JS e PHP sujeitos a lint. Detalhes e roteiro completo em `docs/V23_REFINAMENTO_E_HOMOLOGACAO.md`.
- **Não homologado para dinheiro real**: conciliação gateway/PlayFiver, testes simultâneos em MySQL e validação visual em navegador móvel real continuam obrigatórios.

## V24 — Experiência do jogador, operação e diagnóstico

### Navegação
- o botão inferior **Convidar** agora abre diretamente **Promoções → Agência e Indicações**;
- o atalho **Convidar amigos** do menu lateral também abre o módulo de Agência.

### Notificações e recompensas
- sino de recompensas no cabeçalho para usuários logados;
- badge numérico na aba **Promoção** do menu inferior;
- contador atualizado a partir da disponibilidade real dos módulos do jogador;
- painel rápido lista apenas benefícios/giros/resgates realmente disponíveis;
- atalhos do painel abrem diretamente o módulo correspondente.

### Perfil e carteira
- visão separada de saldo disponível, bônus e saldo de afiliado;
- filtros no histórico por depósitos, saques, promoções/bônus, cassino e afiliado;
- identificação mais clara da origem das movimentações.

### Administração de promoções
- dashboard ampliado com jogadores com resgates, atividade em 30 dias e valor nominal registrado;
- controles rápidos para ativar/desativar configurações sem editar seus valores;
- todas as alterações rápidas geram registro no audit log;
- ferramenta **Diagnóstico por jogador** por ID público ou UUID, exibindo disponibilidade e motivo por módulo.

### Auditoria financeira
Foram adicionadas consultas somente leitura para:
- saldos negativos de carteira;
- transações financeiras concluídas sem lançamento correspondente no ledger;
- divergência entre valor de resgate promocional e valor lançado no ledger;
- além das verificações já existentes de carteira x ledger, rollover, alocação de apostas e duplicidades.

### Segurança
- nenhuma rotina de diagnóstico corrige ou movimenta valores automaticamente;
- a elegibilidade final continua sendo validada no servidor no momento de cada resgate;
- não há migration nova nesta versão.

## V24.1 — Menu Admin A-Z + Central de Notificações

### Menu administrativo
- todos os menus existentes foram preservados;
- itens de cada grupo foram reorganizados em ordem alfabética;
- submenus de **Jogos** e **Promoções** também foram ordenados alfabeticamente;
- novo item **Notificações** adicionado à área Plataforma.

### Notificações da plataforma
O sino do cabeçalho deixou de representar a Central de Recompensas e agora é exclusivo das mensagens da plataforma.

Categorias iniciais:
- **Anúncios** — comunicados e novidades;
- **Sistema** — manutenção, mudanças técnicas e avisos operacionais;
- **Usuário** — mensagens destinadas a um jogador específico;
- **Suporte** — respostas/comunicados da equipe de atendimento.

O Admin permite cadastrar notificação para todos ou para um jogador específico por ID público, UUID, usuário ou e-mail, além de configurar período, link interno e status de publicação.

As notificações possuem controle de leitura individual. O sino exibe somente a quantidade de mensagens não lidas.

### Central de Recompensas
- removida do sino;
- ganhou um ícone flutuante de presente;
- o badge verde no ícone mostra quantas promoções/recompensas o jogador possui desbloqueadas;
- ao tocar no ícone, a página de Promoções abre diretamente na Central de Recompensas.

### Banco de dados
Nova migration obrigatória:
- `034_platform_notifications.sql`

Execute após atualizar:
`php bin/migrate.php`


## V24.2 — Admin alfabético + depósitos configuráveis
- Menu principal do Admin reorganizado em ordem alfabética sem remover páginas.
- Ícone de Notificações trocado por sino vetorial no mesmo padrão visual dos demais ícones.
- Botão Sair movido para o perfil no canto superior direito, abaixo do nome do administrador.
- Removida do Admin a seção antiga de campanhas/imagens promocionais (`+ Nova promoção`); os módulos de Promoções permanecem intactos.
- Configurações gerais agora permitem definir valores rápidos de depósito (ex.: 10, 30, 50, 100).
- Oferta de primeiro depósito configurável: ativação, valor mínimo, percentual e teto máximo opcional.
- O bônus de primeiro depósito é calculado somente após o primeiro depósito PAID elegível e creditado no saldo disponível com idempotência por pagamento.
- Modal de depósito exibe botões com valores predefinidos e prévia do bônus quando aplicável.
- Não há migration nova nesta versão: as novas regras usam `platform_settings`.

## V24.3 — Central de Promoções isolada e menu administrativo

- Dashboard voltou a ser o primeiro item do menu administrativo.
- Demais itens principais permanecem organizados alfabeticamente, sem remoção de páginas.
- Adicionado o submenu `Central de Promoções` dentro de `Promoções`.
- A Central administrativa de promoções, diagnóstico por jogador e diagnóstico financeiro agora aparecem somente em `Promoções > Central de Promoções`.
- Cada módulo de promoção exibe apenas suas próprias configurações, relatórios e informações específicas.
- Ao abrir um módulo como Giro da Sorte, VIP, Rebate, Sorteio etc., os painéis gerais da Central ficam ocultos para reduzir poluição visual.
- Cache de CSS/JS do Admin atualizado para V24.3.
- Não há migration nova nesta versão.


## V24.4 — Ícones flutuantes configuráveis
- Aparência → Ícones flutuantes agora permite criar vários atalhos promocionais.
- Imagem editável com PNG, JPG, WebP e GIF animado (até 5 MB).
- Link interno/HTTPS opcional.
- Associação direta a módulos de promoções ou à Central de Recompensas.
- Regras de exibição: sempre, quando houver qualquer recompensa ou quando o módulo associado estiver disponível ao jogador.
- Badge dinâmico mostra a quantidade na Central de Recompensas e sinaliza disponibilidade nos módulos.
- Ícones foram posicionados mais acima da barra inferior e empilham verticalmente quando houver vários.
- Sem migration nova: a configuração usa `platform_settings`.
