# Auditoria dos mapas

## Implementação

- Renderização MapLibre com estilo OpenFreeMap configurável.
- Modos por entitlement: imóveis, calor de leads, preço/m², oportunidades, liquidez, leilões, flipper e potencial de captação.
- Buscas salvas armazenam produto, modo, geometria e filtros.
- Filtros e geometrias são aplicados no backend.

## Procedência e privacidade

- Imóveis e oportunidades vêm da carteira do tenant.
- Calor de leads devolve somente agregados geográficos.
- Preço/m² informa amostra pequena; liquidez declara que usa tempo de anúncio interno.
- Leilões aparecem somente quando o catálogo oficial importado possui latitude e longitude válidas.
- Nenhuma coordenada é inventada para preencher o mapa.

## Provedores

OpenFreeMap é o padrão. Mapbox só é marcado como configurado quando sua credencial existe no backend; geocodificação e entorno usam OpenStreetMap.
