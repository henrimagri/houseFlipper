# Integrações — matriz honesta

Data: 19/10/2026. Classificação: `oficial` | `feed` | `contrato` | `manual` | `referência` | `não verificado` | `código local`.

Nenhuma linha abaixo autoriza scraping para republicar.

## Já no código

| Fonte | Classe | Status real |
|---|---|---|
| CKAN / ITBI SP | `SaoPauloItbiDatasetProvider`, `ItbiMunicipalProvider` | código local, disponível quando o CKAN responde |
| BCB SGS | `EconomicIndicatorService` | código local |
| OSM Nominatim | geocode / mapa | código local, política de uso Nominatim |
| ViaCEP | `PostalCodeController` | código local |
| Overpass | `NeighborhoodContextService` | código local |
| Node portal scraper | `frontend/scripts/portal-scraper.mjs` | descoberta; **não** API; **não** republicação |
| OpenAI / Gemini / Groq | `AiConversationService` | opcional via `.env`, chat interno |
| Meshy / Tripo / fal | config visualizations | opcional, 3D pago |
| Resend | mail | opcional |
| DataZAP / FipeZAP | status endpoint | `unavailable` |
| ONR | status | `not_wired` |
| Caixa / Mega / Superbid | status | `not_implemented` |

## Portais BR (catálogo do brief)

Kenlo, DWV, Jetimob, Vista, ImobiBrasil, Superlógica, ZAP, Viva Real, OLX Imóveis, Imovelweb, Chaves na Mão, QuintoAndar, Loft, Lello, Lopes, Brasil Brokers, RE/MAX, Century 21, Auxiliadora, EmCasa, Netimóveis, Imóveis-SC, 123i, Attria, Casa Mineira, Agente Imóvel, Secovi-SP, DataZAP, ImoFlow: **não verificado** neste repo. Sem credencial, sem cliente HTTP, sem contrato.

`OpportunitySource` tem labels OLX/ZAP/Viva/QuintoAndar apenas como enum de origem de **lead descoberto**.

## Leilões BR

Mega Leilões, Zuk, Superbid, Pestana, Freitas, Sold, Frazão, VIP, Biasi, Milan, Sodré Santoro, Lance Total, Lance Já, Judiciais, Sato, Hasta, Loop, Santander Imóveis, Seu Imóvel BB, Caixa, BB, Itaú, Bradesco, Inter, BTG: **não verificado**. Endpoint de status já admite `not_implemented` para caixa/mega/superbid.

## Dados e mapa

Google Maps, Mapbox, HERE, Geoapify, OpenCage, TomTom, Radar, Geocodio, RentCast, ATTOM, RESO, Trestle, etc.: **não verificado**. OSM+Nominatim é o único mapa em código. Escolher provedor de tiles na Fase 4 com custo/ToS.

## CRM / pagamento / doc

WhatsApp Cloud, Twilio, HubSpot, Pipedrive, Mercado Pago, Stripe, Clicksign, DocuSign: **não verificado**. Prioridade de avaliação: Mercado Pago e Stripe (Fase 1-billing), WhatsApp Cloud só com Meta Business.

## Regra de conector novo

1. Documentação oficial lida.
2. Auth conhecida.
3. Licença de armazenamento/republicação explícita.
4. Status no `DataSourceStatusController`.
5. Sem conector, a UI mostra “indisponível”, nunca um feed fake.
