# Fluxos

Data: 19/10/2026.

## A — Investidor (já no produto)

Login → Radar/IA → salva opportunity → análise → projeto → obra → venda. Isolamento: tenant nas propriedades, user no lead.

## B — Corretor, MVP (a construir)

1. Descobre anúncio no radar **ou** cadastra manual.
2. Favorita (não publica).
3. Inicia captação: registra proprietário + evidência (doc/upload).
4. Status `granted` → cria `commercial_listing` (copia campos editáveis; source congelado).
5. Sobe fotos próprias ou marca licença.
6. Publish → aparece em `/c/{slug}` se assinatura `trial|active|grace`.
7. Lead público vira contato no CRM do tenant. Sem WhatsApp nesta fase.

Bloqueios do fluxo: pular 3 e 4. Publicar foto do scraper. Expor telefone do dono.

## C — Imobiliária (Fase 6)

Admin cria filiais e corretores. Lead entra na roleta. Comissão calculada no fechamento, paga em lote separado.

## D — Assinatura (Fase billing)

Checkout → webhook → `tenant_product_subscriptions`. Expiração: graça N dias → `suspended` no `broker_sites` (robots noindex, 404/410 na URL pública, dados privados intactos). Reativação restaura o slug.

## E — Admin da plataforma (não existe UI)

Gestão de tenants, planos, chaves de conector, auditoria. Fora do MVP.
