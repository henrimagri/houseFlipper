# Banco de dados

Data: 19/10/2026. Esquema alvo. Só as tabelas novas entram em migração quando a fase começar.

## Já existe (não misturar)

`tenants`, `users`, `tenant_product_subscriptions`, `propriedades`, `projetos`, `despesas`, `vendas`, `opportunities` (funil de investimento, `user_id`), `investment_analyses`, `documentos`, `tarefas`, `eventos_calendario`, `fornecedores`, visualizações 3D, ITBI, `geocoding_cache`, Spatie permission.

## Novas entidades (Fase 2+)

### organizations / branches

Tenant já é a empresa. `branches`: `tenant_id`, nome, slug, cidade, ativo. Fase 6.

### listing_sources (snapshot imutável)

`id`, `tenant_id` nullable (descoberto pelo usuário), `opportunity_id` nullable, `provider` (enum conhecido), `external_id`, `source_url`, `captured_at`, `title`, `price_cents`, `area_m2`, `payload_json`, `content_hash`. Unique `(provider, external_id)` quando ambos existem.

### commercialization_authorizations

`id`, `tenant_id`, `user_id`, `listing_source_id` nullable, `propriedade_id` nullable, `owner_name`, `owner_document_hash`, `owner_contact`, `granted_at`, `expires_at`, `scope` (divulgar | intermediar | fotografar), `evidence_documento_id`, `status` (`pending` | `granted` | `revoked` | `expired`).

### commercial_listings (carteira)

`id`, `tenant_id`, `user_id`, `branch_id` nullable, `authorization_id` **obrigatório** para `published=true`, `source_id` nullable, `slug`, `title`, `description` (texto do corretor), `asking_price_cents`, `status` (`draft` | `ready` | `published` | `paused` | `sold` | `rented` | `withdrawn`), `highlight`, `views_count`. Fotos só via media library com flag `licensed_for_publication`.

### publications

`id`, `listing_id`, `channel` (`site_corretor` | `site_imobiliaria`), `published_at`, `unpublished_at`, `reason`.

### broker_sites

`id`, `tenant_id`, `user_id`, `slug` unique, `theme_json` (cores/fontes, **sem scripts**), `status` (`trial` | `active` | `past_due` | `grace` | `suspended` | `cancelled`).

### crm_contacts, crm_deals, crm_activities

Fase 3. Contact: tipo `lead|buyer|owner`. Deal: funil configurável. Activity: visita, proposta, nota. Sem WhatsApp até conector oficial.

### commissions, lead_assignments

Fase 3/6. Cálculo ≠ liquidação.

### billing_customers, subscriptions_gateway, payment_events

Fase 1-billing. Webhook: `provider_event_id` unique, payload, `processed_at`.

## Índices mínimos

- `commercial_listings (tenant_id, status, user_id)`
- `authorizations (tenant_id, status, expires_at)`
- `listing_sources (content_hash)`
- `broker_sites (slug)`
- `payment_events (provider, provider_event_id)` unique

## Isolamento

Toda tabela nova: `tenant_id` + `TenantScope`. Publicação lê só `status=published` + site `active|trial|grace`. Nunca vazar contato de proprietário na vitrine.
