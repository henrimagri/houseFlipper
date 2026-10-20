# Busca inteligente

Rotas:

- `GET /api/ai-property-search` — coleta estruturada original (agora também aceita `min_bedrooms`, `min_bathrooms`, `min_parking`, `min_area_m2`).
- `GET /api/ai/conversations` — sessão do usuário.
- `POST /api/ai/conversations` — mensagem em linguagem natural (`throttle:market-data` quando dispara coleta).

Ordem de consulta:

1. Carteira (`propriedades`) e leads do usuário.
2. Portais via scraper/API já existentes.
3. Filtros obrigatórios no ranker.
4. Preferências só reordenam.

Follow-up (`agora`, `parecidas`, `mas com`) preserva teto, tipo e cidade. Comparar/recomendar reutiliza `last_listings` e **não** dispara nova raspagem.

Critérios sem dado na base (ex.: valor de condomínio) viram `unknowns` e não viram filtro duro.
