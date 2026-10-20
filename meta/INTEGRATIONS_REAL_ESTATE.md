# Integrações imobiliárias — estado real

Data: 19/10/2026.

Legenda: **live** = código consulta HTTPS público; **local** = planilha/import; **optional_contract** = env vazio, não inventa dado; **not_wired** = não implementar até contrato/ToS.

| Família | Serviço | Estado | Uso honesto |
|---|---|---|---|
| Portais | Scraper OLX/Zap/VivaReal + Nestoria/ML | live | anúncios, não escritura |
| Geo | OSM Nominatim + Overpass | live | entorno |
| Geo | Google Geocoding/Places | optional_contract | se `GOOGLE_MAPS_API_KEY` |
| Geo | ViaCEP / IBGE municípios | live | endereço e lista de cidades |
| Registral aberto | CKAN ITBI (SP, Fortaleza, Rio, dados.gov.br) | live | vendas públicas quando o datastore responde |
| Registral local | ITBI importado | local | mesma semântica |
| Cartório nacional | ONR / RI Digital / SINTER / verificador de matrícula | not_wired | não há API pública nacional |
| Preço comercial | DataZAP / FipeZAP / FIPE | not_wired | substituído por BCB IVG-R + AVM + ITBI aberto |
| Macro | BCB SGS (SELIC, CDI, IPCA, INCC, IVG-R) | live | indicadores, não preço do imóvel |
| Leilão | CAIXA, VendasGov, BB, Mega, Superbid, Zuk, Santander | not_wired | UI de status `not_implemented` |

Regra da IA e do 3D: se a linha é `not_wired` ou `optional_contract` sem credencial, o texto diz que a fonte **não foi consultada**. Nunca “cartório confirmou”.
