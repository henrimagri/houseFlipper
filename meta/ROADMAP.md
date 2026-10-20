# Roadmap

Data: 19/10/2026. Só marcar feito o que o código faz. House Flipper operacional = base. Marketplace = trabalho novo.

## MVP comercializável (o que dá para vender agora)

**Produto 1 — House Flipper:** já vendável como operação de reforma (imóvel, projeto, custo, radar, análise, 3D photo_referenced). Falta checkout.

**Produto 2 — Corretor (MVP):** captação, carteira, página `/c/{slug}` e gate de autorização estão no código. Checkout Mercado Pago/Stripe continua `not_wired` (teste de 7 dias). Ainda não é o briefing completo (CRM avançado, 8 mapas, estúdio gráfico, filiais).

Mínimo cobrável já no repositório:

1. Snapshot da origem (não mutável).
2. Autorização de comercialização com evidência.
3. Carteira (`commercial_listings`) distinta do lead de investimento.
4. Publicar / despublicar.
5. Página `/c/{slug}` só com autorizados + assinatura ativa/graça.
6. Lead público → contato interno (formulário, sem WhatsApp).
7. Gate de plano (`public_listing`).

Fora do MVP corretor: estúdio, 8 mapas, CRM kanban, roleta, filiais, Elementor, leilão, Mercado Pago (pode vir em paralelo como Fase 1-billing).

## Backlog por dependência

### Fase 0 — Fundação (parcial)

Feito: auth, tenant, products.php, dashboard FlipControl.
Falta: billing real, RBAC por produto, UI admin.

**Incremento 0.1 (próximo código, se autorizado):** flags `capture` / `commercial_wallet` / `public_listing` no catálogo; middleware de entitlement nas rotas novas. Sem UI de marketplace ainda.

### Fase 1 — Billing SaaS

Checkout Mercado Pago **ou** Stripe após leitura da doc oficial. Webhook HMAC + `provider_event_id` unique. Estados trial/active/past_due/grace/suspended/cancelled. Não apagar dados na suspensão.

### Fase 2 — Imóveis e carteira (MVP corretor)

Tabelas `listing_sources`, `commercialization_authorizations`, `commercial_listings`, `broker_sites`, `publications`.
UI: no lead existente, ações Favoritar | Solicitar captação | Adicionar à carteira (só se granted).
Página pública read-only. Sem republicar foto de scraper.

### Fase 3 — CRM

Contatos, deals, visitas, propostas. Inbox e WhatsApp Cloud só com app Meta. Roleta e comissões depois do CRM simples.

### Fase 4 — Mapas

Modo 1 (imóveis da carteira + radar geocodificado OSM) primeiro. Modos 2–8 só com série suficiente; senão “sem dados”. Sem heatmap fake.

### Fase 5 — House Flipper avançado

Já existe o núcleo. Pendências: comparador de investimentos, leilão só com conector, 3D cotado se houver planta medida.

### Fase 6 — Imobiliária

`branches`, equipes, metas, site empresa. Depende de Fase 2+3.

### Fase 7 — Integrações

Conector por fonte verificada. Estúdio de marketing (imagens licenciadas). IA de texto comercial com campos reais, sem invenção.

## 3D / chat (rodada anterior, não reabrir)

Entregue: chat, maquete, cena photo_referenced, selos de fidelidade.
Próximo 3D: extrusão cotada se a planta tiver medida. Fora: fotogrametria, Gaussian Splatting.

## Ordem de código (executável)

1. Entitlements + tabelas de captação (Fase 0.1 + 2.1).
2. Serviço `CaptureService`: grant/revoke, recusa publicar sem grant.
3. API commercial-listings + policy tenant/user.
4. UI no lead FlipControl (não fork da tela).
5. `broker_sites` + rota pública `/c/{slug}`.
6. Billing.
7. CRM.
8. Mapa modo 1.

Critério de parada de cada incremento: um corretor do tenant A não lê carteira do tenant B; listing sem autorização retorna 403 no publish; URL pública some na suspensão e os dados privados permanecem.
