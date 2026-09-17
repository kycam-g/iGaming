# Reconciliação de saques Pixup

O fluxo principal continua sendo o webhook. A reconciliação existe como rede de segurança para saques que ficam em `PROCESSING` quando a confirmação não chega.

## Como funciona

A API pública v2 da Pixup documenta o `external_id` do cash-out como idempotente: reenviar o mesmo `external_id` retorna a mesma transação. Por isso a reconciliação do MZ90 não inventa um endpoint GET não documentado; ela repete o mesmo cash-out assinado, com o mesmo `payment_id` usado como `external_id`, e interpreta o status retornado.

- `paid`, `confirmed`, `completed` -> `PAID`
- `pending`, `processing` ou desconhecido -> continua `PROCESSING`
- `failed`, `cancelled`, `rejected` -> `FAILED` e o saldo reservado é devolvido uma única vez

O estorno usa a mesma chave idempotente do webhook: `withdrawal-gateway-refund:<payment-id>`. Assim, webhook e reconciliação podem correr próximos sem duplicar crédito.

## Admin

Em **Financeiro > Saques**, abra um saque Pixup em `PROCESSING` e clique em **RECONCILIAR PIXUP**.

Cada tentativa fica registrada dentro de `payment_transactions.metadata.reconciliation`, incluindo horário, origem, status remoto e erros.

## Rotina automática

Execute manualmente:

```powershell
C:\xampp\php\php.exe bin\reconcile-payments.php --minutes=5 --limit=50
```

O comando procura somente saques Pixup em `PROCESSING` sem atualização há pelo menos 5 minutos.

No Windows, ele pode ser cadastrado no Agendador de Tarefas para executar a cada 5 minutos. O webhook continua sendo a fonte principal; a rotina é apenas contingência.
