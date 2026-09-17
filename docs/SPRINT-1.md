# Sprint 1 — XAMPP/MariaDB

A Sprint 1 entrega Auth, Users, Wallet/Ledger, idempotência e audit log em PHP puro moderno.

A edição XAMPP usa MariaDB/MySQL com InnoDB. Operações financeiras continuam atômicas e usam `SELECT ... FOR UPDATE` para serializar alterações na mesma conta.

O registro cria usuário, credencial, wallet, CASH e BONUS na mesma transação. Sessões usam tokens aleatórios, armazenando somente o hash no banco.
