# Segurança

Data: 19/10/2026. Complementa `SECURITY_AUDIT.md` / `SECURITY_MATRIX.md` já no repo.

## Já no produto

- Sanctum, token com expiração, rate limit de login, conta/tenant inativos bloqueados.
- `TenantScope` falha fechado sem `tenant_id` (BUG 01).
- Policies nos recursos atuais; IDs de path validados.
- Upload de documento: scanner, MIME, disco privado, URL temporária.
- Fotos de opportunity: path policy; URL remota de capa não é baixada (SSRF).
- IA: comandos 3D recusam SQL/URL/script; não inventa cômodo.
- Headers de API; CORS por allowlist.
- ITBI compartilhado só com gate `itbi.import`.

## Lacunas do brief (ainda abertas)

| Risco | Situação |
|---|---|
| IDOR entre carteiras comerciais | N/A — tabela ainda não existe; policy obrigatória no primeiro PR |
| Página pública vazar dono | Não modelado; vitrine só nome comercial / CRECI / WhatsApp do corretor |
| Lead de formulário público sem base LGPD | Precisa checkbox + finalidade + retenção |
| Webhook de pagamento replay | Não existe billing |
| Foto de portal republicada | Scraper grava metadados; publicação comercial deve recusar media sem `licensed_for_publication` |
| Site builder XSS | Editor futuro: JSON de tema, sem HTML cru / sem JS do usuário |
| Filiais | Não existem; não misturar “visão consolidada” com vazamento |

## Regras para código novo

- Toda tabela: `tenant_id` + scope. Publicação: query explícita `published` + site ativo.
- Autorização no backend, nunca só no botão.
- Hash de documento do proprietário, não CPF em claro na vitrine.
- Auditoria: criar/grant/revoke/publish/unpublish em log.
- Webhooks: assinatura, idempotência, 200 em replay.
- Sem scraping para persistir foto de terceiro como se fosse do corretor.
