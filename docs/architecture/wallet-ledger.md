# Wallet & Ledger

## Modelo

`users` não possui coluna de saldo. Cada usuário possui uma `wallet`, que contém `wallet_accounts` como `CASH`, `BONUS` e futuramente `AFFILIATE`.

Valores monetários são inteiros em centavos (`BIGINT UNSIGNED`). R$ 10,50 é armazenado como `1050`.

## Movimentação

Uma operação financeira:

1. abre transação InnoDB;
2. reserva a idempotency key;
3. seleciona a conta com `SELECT ... FOR UPDATE`;
4. valida saldo;
5. cria `financial_transactions`;
6. atualiza `wallet_accounts.balance_minor`;
7. insere `ledger_entries`;
8. associa a idempotency key à transação;
9. faz commit.

Dois débitos simultâneos sobre a mesma conta são serializados pelo lock da linha.

## Imutabilidade

`ledger_entries` é append-only. Triggers MariaDB/MySQL bloqueiam `UPDATE` e `DELETE`. Correções financeiras futuras devem usar lançamentos compensatórios, nunca editar histórico.

## Idempotência

A chave é única por escopo. Repetir a mesma operação retorna o resultado financeiro anterior sem movimentar saldo novamente.
