# Cassino V1 — catálogo administrativo

Migration `008_casino_catalog.sql`: provedores e jogos. Admin autenticado pode cadastrar/editar, ativar/desativar e ordenar. Alterações auditadas. Endpoint público `/api/casino/games` retorna apenas jogos ativos de provedores ativos em modo DEMO. Nenhuma integração com API de jogo, sessão de jogo, apostas, saldo ou launch real foi criada. Modo PRODUCTION é apenas metadado e nunca habilita launch. Não inserir credenciais em campos de catálogo. O frontend legado ainda tem cartões demonstrativos estáticos; integração do catálogo visual será uma etapa posterior.

V3: Submenu de cassino separa catálogo/provedores; credenciais, histórico e RTP permanecem desabilitados até existir integração real. Botão de jogo demonstra detalhes e informa explicitamente que não há launch/API; DEMO no cadastro é modo de catálogo, não jogo executável. CSS dos botões foi padronizado.
