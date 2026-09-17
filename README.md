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

### V12.11

- Estrutura de sincronização de catálogo PlayFiver.
- Upsert de provedores/jogos.
- Logos manuais preservadas.
- Jogos desaparecidos do remoto são preparados para desativação, não exclusão.
- Sincronização via Admin e CLI.

Migration:

```text
015_playfiver_catalog_sync.sql
```

**Observação:** a sincronização V12.11 ainda depende da confirmação dos endpoints oficiais de catálogo PlayFiver. O endpoint testado retornou HTML, portanto essa parte deve ser considerada **em validação**, não concluída.

---

# Status conhecido / próximos passos

Prioridades atuais do projeto:

1. Confirmar endpoints oficiais de catálogo PlayFiver.
2. Finalizar sincronização real de todos os provedores e jogos.
3. Implementar game launch real com autenticação correta.
4. Integrar callbacks/rodadas ao WalletService e ledger.
5. Implementar histórico de apostas.
6. Implementar RTP observado somente a partir de dados reais.
7. Implementar payout/saques reais após definição do gateway.
8. Persistir favoritos no backend caso seja desejado sincronizar entre dispositivos.

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

Este repositório representa o estado atual de desenvolvimento do MZ90. Recursos relacionados a dinheiro real, abertura de jogos, apostas e callbacks de provedores devem ser considerados disponíveis somente depois de integração, validação de segurança e testes no ambiente de produção.
