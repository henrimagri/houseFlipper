# Site institucional, funil comercial e onboarding

Atualizado: 2026-10-19

## Implementado

- Área pública independente dos dashboards (`/`, soluções, funcionalidades, comparar, preços, sobre, contato, demonstração, ajuda, termos, privacidade).
- Header e footer institucionais; Super Admin fora da navegação pública.
- Catálogo e preços a partir de `GET /api/public/catalog` (`config/products.php`). Checkout permanece `not_wired`.
- Cadastro em etapas com produto pré-selecionado (`/cadastro?produto=`).
- Login com redirecionamento por assinaturas (`/entrar`).
- Teste de 7 dias por produto, uma vez por conta; segundo produto não remove o primeiro.
- Onboarding persistido por produto (`product_onboardings`, `/app/onboarding/:product`).
- Leads comerciais do SaaS (`saas_leads`) distintos do CRM de imóveis; fila em `/platform/leads`.
- SEO: title, description, canonical, Open Graph, robots, sitemap.
- Rate limit e honeypot nos formulários públicos.

## Testado

- `php artisan suite:verify` — PASS (isolamento + catálogo + trial único + segundo produto + lead).
- `npm run build` — PASS.
- `GET /api/public/catalog` — 3 produtos, checkout `not_wired`.
- Funil API: cadastro 201 → verificação → teste de 7 dias (início/fim persistidos) → onboarding persiste após GET → segundo produto House Flipper sem liberar Imobiliária (403).
- Navegador 127.0.0.1:5173:
  - T1 `/` carrega sem login; hero, 3 produtos, FAQ, footer.
  - T2 `/solucoes/corretor` — captação, carteira, planos oficiais R$ 149.
  - T3 `/solucoes/imobiliaria` — formulário de demonstração registrou “Solicitação registrada.”
  - T4 `/solucoes/house-flipper` — simulador, 3D honesto, leilões não ligados, R$ 249.
  - T5 conta autenticada em `/cadastro?produto=corretor` foi para o catálogo (não duplica conta).
  - T9 `/app/imobiliaria/dashboard` com Admin Demo (só House Flipper) redirecionou para `/app/produtos`.
  - T11 viewport 390×844: menu “Abrir menu” com soluções e cadastro.
- T12: rate limit `saas-leads`, honeypot, consentimento, 403 de produto sem entitlement.

## Bloqueado

- Checkout Mercado Pago / Stripe: `not_wired`. Planos pagos seguem proposta comercial; não há ativação paga fictícia.
- E-mail transacional: se `mail.default` for `log`/`array`, o site não afirma que o e-mail foi enviado.
- Dados legais (CNPJ, telefone, endereço): não cadastrados; páginas legais não inventam.
- Conector de leilões e WhatsApp: não ligados.
- Aplicativo nativo: inexistente.

## Próximos passos

1. Ligar checkout sem duplicar o motor de assinaturas.
2. Publicar dados societários oficiais quando existirem.
3. Refinamento visual dos dashboards (fora desta fase).
