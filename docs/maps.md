# Mapa inteligente

## Provedor escolhido

**OpenFreeMap + MapLibre GL JS** (padrão).

| Critério | OpenFreeMap / MapLibre | Mapbox |
|---|---|---|
| Licença | OSM + estilo livre | Comercial |
| Chave | Não | Sim |
| Brasil 2D | Boa (OSM) | Boa |
| Satélite | Não no estilo liberty | Sim (pago) |
| Edifícios 3D | Extrusão de altura OSM | Extrusão / 3D buildings |
| Polígonos | Sim | Sim |
| Custo | $0 | Uso cobrado |

Não combinamos tiles comerciais com OSM em violação de termos de uso.

Troca de provedor: `MAP_PROVIDER` e `MAPBOX_ACCESS_TOKEN` em `config/maps.php`. Sem token, o frontend permanece em OpenFreeMap.

## 3D que existe de fato

- Pitch/rotação MapLibre.
- `fill-extrusion` na camada `building` do OpenMapTiles **quando o estilo carregar essa source**.
- Cidades sem altura OSM aparecem como blocos baixos ou só 2D.

O editor interno de apartamento (FlipControl / visualizações) é outro sistema. O mapa não inventa fachada nem pé-direito.

## Modos

Implementados sobre dados da conta: 1 imóveis, 2 calor agregado de leads (sem PII), 3 preço m² anunciado da carteira, 4 oportunidades, 5 liquidez (dias de anúncio), 7 flipper, 8 captação.

Modo 6 leilões: `available: false` até existir conector.
