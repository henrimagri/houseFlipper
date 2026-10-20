# Arquitetura

Data: 19/10/2026.

## Stack atual (não reescrever)

- Backend: Laravel 13, PHP 8.4, Sanctum, Spatie Permission, Spatie Data.
- Frontend: React 18, Vite 6, Tailwind 4, Three.js.
- Persistência: SQLite local / Postgres em produção (exemplo). Redis previsto, não obrigatório no PHP `-S`.
- Isolamento: `BelongsToTenant` + `TenantScope` em domínio operacional. Opportunity fica em `user_id`.

## Decisão: um monólito, três produtos

Não criar três codebases. Um API, um SPA autenticado, sites públicos em rotas distintas.

```
SPA autenticada (/)
  ├ house_flipper  → FlipControl atual
  ├ corretor       → carteira + CRM individual + marketing
  └ imobiliaria    → equipe + filiais + atendimento
Público
  └ /c/{slug}      → vitrine (só imóveis autorizados + assinatura ativa)
```

`ProductEntitlement` já escolhe features pelo slug da assinatura do tenant. Estender o catálogo; não duplicar auth.

## Camadas de imóvel (obrigatório)

1. **ListingSource** — snapshot da descoberta (URL, portal, hash, preço visto). Imutável.
2. **Capture** — pedido de autorização, evidência, status jurídico.
3. **Listing** (carteira) — apresentação comercial do corretor/imobiliária, fotos próprias ou licenciadas.
4. **Publication** — o que vai à página pública, gated por assinatura.

Opportunity atual mistura 1 e um funil de investimento. Evoluir: adicionar colunas/tabelas, não apagar Opportunity.

Propriedade atual é o ativo do flipper (compra). Continua. Não é a vitrine do corretor.

## Integrações

Cada fonte = conector com status (`connected` | `available` | `unavailable` | `not_implemented` | `not_wired`), já o padrão de `DataSourceStatusController`. Novos conectores só com API/feed/contrato verificados.

## Filas

Importação, geração de mídia, e-mail e webhooks de pagamento: jobs. Localmente sync. Produção: Redis como em `.env.production.example`.

## O que não fazer agora

Reescrever o FlipControl. Inventar API ZAP/OLX. Gaussian Splatting. Editor tipo Elementor. WhatsApp não-oficial.
