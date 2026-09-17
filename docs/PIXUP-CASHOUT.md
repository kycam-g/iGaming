# Pixup Cash-out — configuração MZ90

Esta integração usa o perfil de credencial fornecido para a conta MZ90:

- OAuth `client_id` + `client_secret` em `/v2/oauth/token`;
- cash-out em `POST /v2/transactions/cashout`;
- header `Authorization: Bearer <token>`;
- **sem Signing Key / HMAC** nesta conta;
- **sem Webhook Secret** nesta conta;
- `postback_url` identifica a rota que receberá o retorno do saque.

## Fluxo

1. O jogador solicita o saque.
2. O saldo CASH é reservado no ledger.
3. O admin aprova e a transação muda para `PROCESSING`.
4. O adapter obtém o token OAuth.
5. O cash-out é enviado com o mesmo `payment_id` em `external_id`.
6. Confirmação do gateway muda para `PAID`.
7. Falha confirmada muda para `FAILED` e o valor é devolvido ao CASH uma única vez via WalletService/ledger.

## Reconciliação

A conta usa `external_id` estável. A reconciliação reapresenta o mesmo saque, com os mesmos dados e o mesmo `external_id`, evitando gerar uma nova identidade local para o pagamento. O histórico da tentativa fica no metadata da transação.

> Nunca adicionar HMAC ou segredo de webhook a esta credencial sem uma mudança explícita no contrato fornecido pela Pixup para esta conta.
