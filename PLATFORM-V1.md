# MZ90 Plataforma V1 — seis áreas

Migration: `010_platform_modules.sql`. Instalar preservando `.env` e `APP_KEY`; executar `C:\\xampp\\php\\php.exe bin\\migrate.php` no Windows.

1. Configurações: nome, e-mail de suporte, cor, rodapé e manutenção. Manutenção retorna HTTP 503 apenas no site público; Admin e APIs permanecem acessíveis.
2. Aparência: upload de JPG/PNG/WebP (máximo 2 MB), banners com ordem, posição e destino interno. Upload requer autenticação administrativa; imagens ficam em `/public/uploads/`.
3. Promoções: publicação informativa por período; não aplica bônus nem altera saldos.
4. Afiliados: criação de links `/?ref=CODIGO` e contagem de visitas. Não atribui cadastro nem calcula comissão: funções financeiras deliberadamente não implementadas.
5. Auditoria: leitura paginada dos registros existentes, sem exibir metadata sensível.
6. Frontend/mobile: banners e promoções reais carregados da API, componentes responsivos e botões padronizados.

Sem saque, aposta real, integração PlayFiver ou ajuste financeiro nesta atualização. Não testado no banco XAMPP do usuário; fazer backup antes de migrar.
