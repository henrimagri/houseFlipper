# Multiproduto — progresso

Data: 2026-10-19 (dashboards operacionais)

## Entregue nesta sessão (dashboards + CRM)

- Design system compartilhado (`PageHeader`, `EmptyState`, `ErrorState`, `LoadingSkeleton`, `StatusBadge`, `SearchInput`) e shell com grupos, sidebar recolhível, breadcrumbs e notificações por produto.
- Dashboard Corretor com KPIs reais, definições e filtro de período.
- Carteira com busca, filtros, ordenação, paginação, grade/tabela e links `/app/{produto}/...`.
- CRM individual: cadastro, ficha, status, notas e próxima ação.
- Dashboard Imobiliária executivo com período, filial e corretor; funil e urgências.
- Kanban com HTML5 drag-and-drop, etapa `sugeridos` e `lock_version` (HTTP 409).
- Ficha do negócio, roleta configurável com motivo da atribuição, comissões com máquina de estados (nunca auto-paga).
- Central de atendimento em 3 painéis (interno; WhatsApp `not_wired`).
- Dashboard House Flipper distinto: estimado vs realizado, alertas de orçamento/tarefas, FlipControl preservado.
- APIs: `POST/GET /crm/contacts`, `GET /imobiliaria/deals/{id}`, `GET/PUT /imobiliaria/roulette`, `PATCH /imobiliaria/commissions/{id}`, `GET /notifications`.
- Migração `2026_10_19_000500` (notas/preferências do contato, prioridade e lock do deal).

## Entregue anteriormente (multiproduto)

- Três produtos independentes: `corretor`, `imobiliaria`, `house_flipper`.
- Entitlements **sem** merge automático de House Flipper.
- Layouts, rotas `/app/{produto}` e `ProductSwitcher`.
- Assinaturas por produto (teste de 7 dias). Checkout pago: `not_wired`.
- Base Imobiliária: organização, filiais, kanban persistente, atendimentos, roleta round-robin, comissões projetadas (nunca auto-pagas).
- Mapa MapLibre + OpenFreeMap: 2D, pitch 3D, extrusão de edifícios quando o tileset tiver `building`, polígono, raio, geocode Nominatim, modos gated.
- Super Admin em `/platform` via tabela `platform_admins` (não reutiliza admin de tenant).
- Páginas públicas `/c/{slug}` preservadas; `/i/{slug}` para imobiliária.

## Contas locais (seeder)

| Email | Produto |
|---|---|
| admin@houseflipper.local | House Flipper |
| corretor@houseflipper.local | Corretor (trial) |
| imobiliaria@houseflipper.local | Imobiliária (trial) |
| combo@houseflipper.local | Corretor + House Flipper |
| expirado@houseflipper.local | Corretor cancelado |
| platform@houseflipper.local | Super Admin |

Senha: `password`

## URLs

- Conta: `/app/inicio` `/app/produtos` `/app/assinaturas`
- Corretor: `/app/corretor/dashboard`
- Imobiliária: `/app/imobiliaria/dashboard`
- House Flipper: `/app/house-flipper/dashboard`
- Operação: `/platform`

## Ainda não ligado (de propósito)

- Mercado Pago / Stripe checkout
- WhatsApp Cloud (canal interno + `not_wired`)
- Conectores de leilão / portais oficiais
- Editor visual tipo Elementor
- Google Photorealistic 3D (exige `GOOGLE_MAPS_API_KEY`)
- Mapbox (exige `MAPBOX_ACCESS_TOKEN`)

## Mapa

Provedor padrão: OpenFreeMap (OSM, sem chave). 3D de edifícios = fill-extrusion, não fachada fotorrealista. Modo leilões: `available: false`.
