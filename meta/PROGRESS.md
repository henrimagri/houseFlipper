# Progresso real

Data: 19/10/2026.

## Implementado e em uso

- Auth Sanctum, conta ativa, e-mail, reset, tenant, Spatie Permission (só `itbi.import` de fato).
- Assinatura de produto por tenant (`tenant_product_subscriptions`), catálogo local, **sem checkout**.
- Dashboard, imóveis (`propriedades`), projetos, etapas, obras, despesas, fornecedores, documentos, tarefas, calendário, vendas, ledger.
- Leads (`opportunities`) por `user_id`: favoritar, arquivar, descartar, status de funil de **investimento** (novo → análise → visita → proposta → adquirido).
- Radar conversacional + scraper de portais (runtime Node). Não é API oficial.
- Análise de mercado: ITBI importado / CKAN, BCB SGS, OSM, ViaCEP. DataZAP/FipeZAP/ONR/leilão: `unavailable` ou `not_implemented`.
- Wizard financeiro, custos, financiamento, conversão análise → projeto.
- Visualização 3D: GLB fiel, maquete paramétrica, cena `photo_referenced` a partir de fotos locais autorizadas. Sem SfM.

## Entregue nesta rodada (MVP corretor)

- Captação com snapshot imutável da origem e autorização com evidência.
- Carteira comercial distinta do lead de investimento; publicar exige autorização vigente, foto licenciada e plano com `public_listing`.
- Página pública `/c/{slug}` e ficha `/c/{slug}/{listing}`; contato do proprietário não vai à vitrine.
- CRM de contatos do formulário público (plano corretor/imobiliária).
- Mapa modo 1 (pontos da própria conta). Modos 2–8 continuam indisponíveis.
- Assinatura: teste de 7 dias; webhooks Mercado Pago/Stripe só com segredo — sem gateway fingido.
- Estúdio de texto comercial só com campos reais do imóvel.

## Parcial

- Perfis corretor/imobiliária no catálogo, sem módulos de UI.
- `lead_matching` e `public_listing_3d` no catálogo, sem endpoint.
- Geocoding cache existe; mapa de 8 modos não.
- Calendário/tarefas servem obra, não CRM de visitas de comprador.

## Não implementado (brief)

Página pública `/corretor/{slug}`, editor de sites, estúdio de marketing, WhatsApp/Instagram nativos, CRM de clientes/proprietários, central de atendimento, roleta de leads, comissões, multi-filial, funil comercial distinto do de investimento, estados Descoberto→Autorizado→Publicado, Mercado Pago/Stripe, admin SaaS, 8 modos de mapa, leilões, construtor visual.

## Bloqueios

- Contrato/API oficial de portais e leiloeiros: inexistente no repo.
- Scraper: descoberta apenas; republicação de foto de terceiro é bloqueio jurídico, não técnico.
- Fila Redis: prevista em `.env.production.example`, PHP `-S` local é síncrono.
