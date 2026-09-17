# MZ90 V11 — Admin grafite/vermelho, jogos API e promoções

Esta versão parte do ZIP V10 recebido em 17/09/2026. O menu lateral e seus submenus mantêm a mesma estrutura da V10. O tema escuro da referência foi aplicado ao painel administrativo e aos formulários, sem alterar o layout público.

## Instalação no XAMPP

1. Faça backup dos arquivos do site e do banco de dados.
2. Extraia este ZIP sobre `C:\xampp\htdocs\mz90`. **Não sobrescreva o seu `.env`**; ele não é distribuído neste ZIP. Preserve também os arquivos existentes de `public/uploads/` (não exclua a pasta antes de atualizar).
3. Execute `C:\xampp\php\php.exe bin\migrate.php` a partir de `C:\xampp\htdocs\mz90`. A migration `011_casino_api_source.sql` adiciona a origem/API ao cadastro dos provedores; os registros anteriores ficam identificados como `MANUAL` até o administrador atribuir uma origem.
4. Abra o Admin e pressione `Ctrl + F5` para atualizar o CSS/JS.

## Escopo

- **Jogos → Catálogo de jogos:** busca por nome/código, filtros por API e provedor, miniatura, código externo, provedores, status e popular, edição/cadastro por modal, exclusão autenticada com auditoria, paginação (8/15/25/50). O cadastro de provedores permite escolher a origem `MANUAL` ou `PLAYFIVER`.
- **Promoções (menu lateral):** campanhas previamente cadastradas aparecem com imagem, editar, ativar/desativar e excluir; abaixo, lista adicional dos **mesmos banners** do módulo Aparência, com miniaturas, local, editar e excluir. Não duplica os registros nem remove os banners de Aparência.
- **Visual:** tons grafite, vermelho e botões laranja apenas para editar jogos; menu e submenus da V10 preservados, assim como os modais e suas transições.

**Limitações existentes:** definir a origem PlayFiver no provedor não sincroniza o catálogo com a API. A abertura de jogos reais, apostas e RTP continuam pendentes. O status/Popular atualiza somente o catálogo existente. Os dados de exemplo da imagem de referência **não são criados** no seu banco.

### V11.1 — Miniaturas do catálogo
- As capas dos jogos no Gerenciamento de Jogos API agora são exibidas em dimensão fixa de 100 × 140 px.
- A imagem usa `object-fit: cover` para manter sua proporção sem deformação; a altura da linha acompanha a miniatura.
- O cache da folha de estilos foi atualizado. Nenhuma migração ou mudança em menus/APIs.

### V11.2 — Correção de capas, topo e login do Admin
- As capas agora recebem as regras de CSS na classe correta (`game-api-cover-cell`), com **100 × 140 px fixos**, inclusive quando a imagem original for grande. Corrige a coluna e a altura das linhas.
- O cabeçalho usa apenas o avatar real e mantém "Ver site" e perfil separados/alinhados (foi removido um avatar fictício gerado pelo tema anterior).
- O menu lateral fica oculto **desde o HTML inicial** até a autenticação ser confirmada pela API; login e token expirado mostram somente o formulário centralizado.
- Somente `public/admin.php`, `public/admin-assets/admin.js`, `public/admin-assets/admin.css` e este documento foram alterados em relação à V11.1. Nenhuma mudança em menus, rotas, banco, promoções ou dados. Sem migration nova.
- Ao atualizar, preserve `.env` e os arquivos de `public/uploads/`. Recarregue com Ctrl+F5.

### V11.3 — Promoções: apenas conteúdo promocional
- O menu lateral Promoções exibe exclusivamente os registros de `platform_promotions` (campanhas, imagens e banners promocionais).
- Removida a tabela adicional que misturava todos os banners do carrossel principal e do lobby de `platform_banners`.
- Banners do carrossel e lobby continuam integralmente disponíveis em Aparência; nenhuma entrada do banco é excluída ou migrada.
- Cadastro, edição por modal, ativação/desativação e exclusão de promoções permanecem. Menus, autenticação, jogos e APIs não foram modificados.
- Cache do JavaScript atualizado; nenhuma migration necessária.
