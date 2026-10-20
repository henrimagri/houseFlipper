# Arquitetura da busca conversacional

Data: 19/10/2026.

A conversa interpreta português em filtros, consulta a carteira (`InventoryListingSearch`) e reutiliza `GET /api/ai-property-search` (scraper). Não inventa anúncios. Recomendação e comparação usam o ranking já existente.

Cada card de mercado leva `visualization_path` para `/ia-inteligente/analise/{id}`. Imóvel da conta leva `/imoveis/{id}/3d`. Lead leva `/leads/{id}/3d`.

A resposta textual declara fonte (carteira, scraper) e que ONR/DataZAP/leilão não foram consultados.
