# API — contratos

Data: 19/10/2026. Prefixos atuais: `/api` Sanctum.

## Existente (manter)

Auth, dashboard, propriedades, projetos, despesas, vendas, oportunidades, análises, visualizações 3D, documentos, tarefas, calendário, fornecedores, ITBI, CEP, data-sources, AI search/chat.

Oportunidade: CRUD + `favorite` + `archive`. Status: `new|analyzing|interested|visit_scheduled|negotiating|proposal_sent|purchased|discarded|archived`.

## Novos (não existem — desenho)

Autenticação igual. Todo recurso novo: policy + `tenant_id`.

### Captação

```
POST   /api/listing-sources                 # snapshot a partir de opportunity_id ou payload manual
GET    /api/listing-sources/{id}
POST   /api/captures                        # pede autorização
POST   /api/captures/{id}/grant             # anexa evidência
POST   /api/captures/{id}/revoke
GET    /api/commercial-listings
POST   /api/commercial-listings             # exige capture granted
PATCH  /api/commercial-listings/{id}
POST   /api/commercial-listings/{id}/publish    # 403 se sem autorização ou assinatura
POST   /api/commercial-listings/{id}/unpublish
```

Publish recusa se: autorização ausente/expirada, foto sem `licensed_for_publication`, site suspenso, plano sem `public_listing`.

### Site do corretor (público, sem Sanctum)

```
GET /c/{slug}                    # HTML/SPA público
GET /api/public/brokers/{slug}   # perfil + listings published
GET /api/public/listings/{slug}  # detalhe sem telefone do proprietário
POST /api/public/listings/{slug}/leads  # cria crm_contact no tenant dono; rate limit
```

### Billing (Fase 1-billing)

```
POST /api/billing/checkout
POST /api/billing/webhooks/{provider}  # assinatura HMAC, idempotente
GET  /api/billing/subscription
```

### CRM (Fase 3) — não especificar payloads até a fase.

## Erros

`403` autorização/plano. `409` duplicata de source hash. `422` validação. Webhook replay: `200` sem reprocessar.
