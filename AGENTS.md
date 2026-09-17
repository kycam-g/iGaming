# AGENTS.md — iGaming PHP

## Purpose
This is a new iGaming platform written in modern PHP without a framework. Keep it small, explicit and modular.

## Non-negotiable rules
1. Never add balance columns to `users`.
2. All money movement goes through `WalletService` and the immutable ledger.
3. Money is integer minor units (`BIGINT`), never float/double.
4. Every external callback must be idempotent before it can affect money.
5. Replaying the same idempotency key must never create a second movement.
6. Every payment provider implements `PaymentGateway`; every game provider implements `GameProvider`.
7. Never place provider-specific logic in Core, Users or Wallet.
8. Never commit credentials, API keys, tokens or production secrets.
9. Use prepared statements for all SQL with external input.
10. Financial writes use InnoDB transactions and row locking where needed.
11. `ledger_entries` is append-only. Corrections are compensating transactions, never UPDATE/DELETE.
12. Authentication tokens are stored only as hashes. Never log raw tokens or password material.
13. User + credentials + initial wallet provisioning must remain atomic.
14. Do not copy architecture/files from the legacy MZ90 project. It is reference-only.
15. Add modules only when needed. Avoid empty architecture for hypothetical features.

## Folder boundaries
- `src/Core`: generic technical infrastructure only.
- `src/Modules`: business domains owned by this application.
- `src/Integrations`: external gateways/providers only.
- `database/migrations`: schema evolution.
- `public`: public entrypoint/assets only.

## Financial checklist
Before adding a money-moving path, verify:
- idempotency key exists and is scoped correctly;
- account row is locked when concurrency matters;
- operation runs in a DB transaction;
- financial transaction exists;
- ledger entry exists;
- correlation ID exists;
- no direct balance writes occur outside WalletService;
- retry returns/reconciles previous result rather than duplicating value.

## Before finishing a change
- Run `composer dump-autoload` when Composer is available.
- Run PHP syntax checks.
- Apply migrations to a clean MariaDB/MySQL database when schema changed.
- Run `composer test` against a disposable test DB.
- Exercise affected endpoints.
- Explain any security or financial tradeoff.

## Public user IDs
- Never replace the internal UUID user key in relationships. `users.id` remains the technical PK.
- Use `users.public_id` (auto-increment) for admin/support-facing IDs and searches.
- Never adjust balances with direct SQL; admin adjustments must go through WalletService + ledger + audit.
- Never expose password hashes or session token hashes in admin APIs.
