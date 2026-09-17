# Payments Engine

## Gateway configuration

Gateway runtime settings are stored in `payment_gateways`. The application can keep more than one gateway enabled at the same time and separates availability for deposits and withdrawals.

Important fields:

- `enabled`: master switch.
- `deposit_enabled` / `withdrawal_enabled`: switches per operation.
- `priority_deposit` / `priority_withdrawal`: lower number = higher priority.
- min/max amounts are stored in cents.
- `credentials_encrypted`: encrypted server-side using `APP_KEY`.
- `settings`: private non-secret adapter settings.
- `public_config`: values that may be returned to the frontend.

When the frontend sends no gateway (or `auto`), the engine chooses the active gateway with the best priority for that operation. The current frontend also allows the player to choose among enabled deposit gateways.

## Pixup

Implemented now: PIX cash-in.

Flow:

1. OAuth token using Basic `client_id:client_secret` at `/v2/oauth/token`.
2. `POST /v2/transactions/cashin` using Bearer token.
3. Local payment UUID is sent as Pixup `external_id` for idempotency.
4. QR copy/paste code is saved in the local payment transaction.
5. Pixup webhook `cashin.confirmed` locates the local payment and credits CASH through `WalletService`.
6. The wallet credit uses its own idempotency key (`payment-credit:<payment-id>`).

No HMAC is used in the cash-in request. Webhook signature verification is configurable in the admin because the current Pixup webhook documentation describes HMAC validation. It is disabled by default in the seeded Pixup configuration.

For a local XAMPP environment, Pixup cannot call `http://mz90.local`; use a public HTTPS tunnel or deploy to an HTTPS staging domain and set the webhook URL in Admin > Gateways > Pixup.

## Cash-out

The schema and gateway flags support withdrawals, but Pixup cash-out is intentionally not enabled yet. It should be implemented only after withdrawal business rules (KYC, limits, review, reserve/hold and refund behavior) are defined.
