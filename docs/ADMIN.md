# Admin panel

URL with the recommended XAMPP VirtualHost:

`http://mz90.local/admin`

After migrations, create or reset an administrator:

```powershell
C:\xampp\php\php.exe bin\create-admin.php admin@local.test "Administrador" "TroqueEstaSenha123!"
```

The gateway page can manage:

- global enabled state;
- deposits enabled;
- withdrawals enabled;
- separate deposit/withdrawal priority;
- min/max values;
- production/sandbox mode;
- Pixup Client ID / Client Secret;
- optional Pixup webhook secret;
- Pixup API base URL;
- explicit webhook URL;
- optional webhook signature validation.

Secrets are encrypted with AES-256-GCM using a key derived from `APP_KEY`. They are never returned by the admin API after save. Leaving a secret field blank preserves the existing value.

## Admin V2 dashboard

The dashboard now mirrors the legacy operational overview while using the new normalized schema. It exposes `/admin/api/dashboard` and shows: online users, registrations, balances, deposits/withdrawals attribution, access count, daily charts, and the latest approved payment movements.

`Lucro` remains zero with an explicit UI note until the game/bet settlement module exists; deposits minus withdrawals are not mislabeled as profit. Blogger values are derived from `payment_transactions.metadata.affiliate_id` and therefore remain zero until affiliate attribution is implemented.

Migration `004_admin_dashboard.sql` adds `site_visits`. Public storefront page loads are recorded without storing the raw IP address (only SHA-256). Location fields are optional and only populated if a trusted reverse proxy provides geo headers.

## Admin V2.1 - Usuários

O menu `Usuários` lista cadastros com busca por usuário/e-mail/ID, filtro de status, saldo CASH/BONUS, total depositado e último acesso.

Ao abrir um usuário, o painel mostra:
- saldos por carteira;
- pagamentos recentes;
- ledger recente;
- sessões recentes;
- alteração de status `ACTIVE`, `SUSPENDED` ou `BLOCKED`.

Suspender ou bloquear revoga imediatamente todas as sessões ainda ativas do usuário. A alteração de status é registrada em `audit_logs` com o administrador responsável.

Nenhum saldo é alterado pelo painel de usuários. Ajustes financeiros futuros deverão obrigatoriamente passar pelo `WalletService` e ledger.

## Gestão de usuários V2.2

- Usuários possuem `public_id` sequencial para uso no painel e suporte. O UUID continua como chave interna e nas FKs.
- A tela de detalhes usa abas: informações, contas de recebimento, depósitos, retiradas e edição.
- Bloqueio revoga sessões ativas.
- Senhas/tokens nunca são exibidos; o admin só pode redefinir a senha.
- Ajustes de saldo passam pelo `WalletService`, geram `financial_transactions` + `ledger_entries` e também `audit_logs`.
- Contas de recebimento ficam em `user_payout_accounts`, prontas para o módulo de saque.
