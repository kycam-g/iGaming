# iGaming PHP — modular XAMPP edition

A lightweight modern PHP 8.2+ iGaming foundation without a full framework. Current modules: Auth, Users, Wallet/Ledger, Payments, Pixup cash-in and an initial Admin panel.

## XAMPP setup

Recommended VirtualHost DocumentRoot:

`C:/xampp/htdocs/mz90/public`

Your `.env` should use:

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

Then run:

```powershell
composer install
C:\xampp\php\php.exe bin\setup.php
```

`setup.php` creates a secure `APP_KEY` automatically when the `.env` still contains the placeholder, creates the database and applies all migrations.

Create an admin:

```powershell
C:\xampp\php\php.exe bin\create-admin.php admin@local.test "Administrador" "TroqueEstaSenha123!"
```

Open:

- Site: `http://mz90.local`
- Admin: `http://mz90.local/admin`
- Health: `http://mz90.local/health`

## Payment gateways

Gateways are database-driven. Multiple gateways can be active simultaneously, with separate switches and priorities for deposits and withdrawals. Pixup is seeded disabled; configure its credentials in the Admin and enable deposits when ready.

See `docs/PAYMENTS.md` and `docs/ADMIN.md`.

## Security rules

- Never store balance on `users`.
- Every wallet mutation goes through the immutable ledger.
- Secrets are never returned to the browser.
- Gateway credentials are encrypted in the database.
- Webhooks are idempotent.
- Sandbox credit simulation only works in `APP_ENV=local`.

## Correção PIX QR + duplo clique

- O botão **GERAR PIX** é bloqueado enquanto a cobrança está sendo criada e mostra estado de carregamento.
- A mesma chave de idempotência é reutilizada durante a tentativa, evitando duplicidade por clique repetido.
- O QR Code visual é gerado localmente no navegador a partir do PIX copia-e-cola retornado pelo gateway.
- O status do depósito é consultado automaticamente a cada 3 segundos em `/api/payments/status` enquanto o modal estiver aberto.
- Não há migration nova nesta correção.

### Admin V2.1

O painel administrativo agora inclui gestão de usuários e uma paleta grafite/preto/dourado coerente com o tema MZ90Gold. Não há migration nova nesta etapa.

## Admin V2.3 - CPF, celular e login múltiplo

A migration `006_user_identity.sql` adiciona `cpf` e `phone` ao cadastro de usuários com índices únicos. Novos cadastros exigem CPF válido e celular brasileiro (DDD + número). O login aceita e-mail, CPF ou celular. No Admin, CPF/celular podem ser pesquisados e editados no perfil do usuário. Usuários criados antes da migration permanecem com esses campos nulos até a atualização administrativa, evitando quebra de dados existentes.

## Admin Financeiro — Depósitos V1

O menu Financeiro agora possui painel operacional de depósitos com filtros, métricas, detalhes da transação e histórico de webhooks. Saques permanecem fora desta etapa. Não há migration nova.
