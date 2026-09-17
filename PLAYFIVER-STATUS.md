# PlayFiver — escopo desta entrega

Implementado: migration 009, formulário no Admin, API autenticada, criptografia AES-256-GCM com APP_KEY, preservação de segredos ao deixar campos vazios, auditoria sem segredos e integração forçada a desativada.

**NÃO implementado:** autenticação remota, sincronização de jogos, abertura de sessões, callback de aposta/ganho, conciliação e testes com credenciais reais. A documentação pública fornecida respondeu HTTP 403 neste ambiente; o callback legado foi examinado apenas como referência, não reutilizado. Não habilite apostas reais. Não informe credenciais no chat. Faça backup antes da migration e preserve o APP_KEY original.
