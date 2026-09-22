# V19 — Roleta de Boas-vindas

## Instalação

1. Faça backup do projeto e do MySQL. Preserve o `.env` no servidor.
2. Substitua os arquivos e execute `php bin/migrate.php` para aplicar `027_welcome_roulette.sql`.
3. Em **Admin → Promoções → Roleta de boas-vindas**, cadastre título, prêmio mínimo e máximo, depósito mínimo, giros por depósito, giros por indicação, exigência de cadastro pelo link (Sim) e rollover. Habilite apenas após revisão.
4. Se houver campanhas que já estavam ativas antes da migração, sua ativação para a V19 será marcada no momento da migração, **sem geração retroativa**.
5. Na Home, abra o menu lateral → Roleta de boas-vindas, faça login e confira os giros.

## Regras implementadas

- Um pagamento `DEPOSIT` `PAID` vale `spins_per_deposit` giros **somente** se o valor individual alcançar `deposit_min_cents` e o depósito tiver sido criado e confirmado após o horário de ativação da campanha. Não soma múltiplos depósitos inferiores ao mínimo.
- Cada novo jogador ativo vinculado ao link do indicador (`player_referrals`) e registrado após a ativação concede `spins_per_referral` giros ao indicador. Não há rodada para mero acesso ao link, nem para cadastros anteriores à ativação. O registro válido só é atribuído no cadastro.
- As origens possuem chave única por campanha, usuário, tipo e evento; consultar a página ou repetir requisições não gera giros extras. Os giros não transitam entre campanhas.
- O prêmio é sorteado no PHP com `random_int(min,max)` em **centavos**, com probabilidade uniforme entre os valores inteiros do intervalo. Não há setores com probabilidades/pesos diferentes. A roda da Home é decorativa e reproduz apenas a animação; não escolhe o resultado.
- Consumo de um giro, inserção do prêmio, lançamento financeiro, crédito na carteira e histórico ocorrem em uma transação. Em caso de falha, a operação é revertida. Créditos com rollover são depositados em `BONUS` e só passam a `CASH` após as apostas elegíveis do motor já existente.
- Limites por evento: de 0 a 10 giros por depósito/indicação, pelo menos uma fonte positiva, bônus entre R$ 0,01 e R$ 1.000,00 e rollover até 100x.
- Campanha com rodadas emitidas não pode alterar seus valores/requisitos nem ser excluída: desative e crie nova campanha. Desativar impede novos giros, mas preserva o histórico e os créditos já emitidos; reativar estabelece novo momento inicial para eventos elegíveis.
- Relatório de rodadas no Admin e histórico das últimas rodadas do jogador.

## Homologação necessária

Não foi possível conectar a um MySQL nem a um gateway de depósito real neste ambiente. Teste antes de produção: um pagamento confirmado, pagamento pendente, depósito abaixo do mínimo, dois cliques simultâneos no giro, repetição da API, cadastro por link válido, resgate com e sem rollover, saldo BONUS/CASH, suspensão e reativação de campanha, estorno/chargeback e replay de webhook. **Um estorno posterior ao giro não desfaz automaticamente o prêmio**; mantenha a campanha inativa até definir e validar a conciliação financeira e a política de reversão de prêmios.
