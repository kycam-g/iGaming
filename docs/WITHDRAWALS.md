# Saques V1

Fluxo implementado:

1. Usuário solicita o saque pela carteira.
2. O sistema utiliza uma conta PIX CPF vinculada ao CPF cadastrado.
3. O valor é debitado da conta CASH imediatamente como `WITHDRAWAL_HOLD`, dentro da mesma transação SQL que cria o saque.
4. A transação de pagamento fica em `REVIEW` para análise administrativa.
5. Aprovação altera para `PROCESSING` e chama o adapter de payout. O sandbox conclui como `PAID`.
6. Reprovação altera para `CANCELLED` e credita o valor de volta como `WITHDRAWAL_REFUND`, também de forma transacional/idempotente.
7. Webhooks de withdrawal nunca chamam o crédito de depósito; confirmação de cash-out apenas conclui o saque.

## Segurança financeira

- Não existe alteração direta de saldo.
- Hold e refund passam pelo `WalletService` e geram `financial_transactions` + `ledger_entries`.
- Solicitação e estorno usam idempotência.
- Duplo clique na aprovação não dispara dois payouts: somente a requisição que conquistou o estado `REVIEW -> PROCESSING` chama o gateway.
- Saque real Pixup continua bloqueado no adapter até a autenticação/contrato de cash-out ser validado para a conta de produção. O gateway sandbox serve para validar o fluxo completo localmente.
