# Segurança da IA e do 3D

- Interpretação determinística: a mensagem do usuário é dado, não instrução de sistema.
- Coleta de portais permanece com allowlist, SSRF e sanitização de URL/imagem.
- Conversas e visualizações são por `user_id` / `TenantScope`.
- Upload 3D: extensão, tamanho, magic bytes, disco privado, `no-store`.
- Feature flags por produto; publicação 3D pública exige assinatura `corretor`/`imobiliaria` (não liberada pelo House Flipper).
- Sem treino em provedor externo com dados da conta.
