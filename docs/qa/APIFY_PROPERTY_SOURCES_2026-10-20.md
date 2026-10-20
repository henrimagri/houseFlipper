# QA — fontes Apify da IA

## Entrega

- ZAP Imóveis, VivaReal, ImovelWeb, Loft, QuintoAndar e Facebook Marketplace integrados por atores Apify configuráveis.
- Mercado Livre preservado pela API pública já existente.
- Casa Mineira preservada pelo coletor local; não foi definido um ator dedicado sem evidência de um conector confiável.
- Execução Apify paralela, limite por fonte, isolamento de falhas, normalização, filtro geográfico e deduplicação.
- Painel responsivo com modo e contagem por portal em `/app/corretor/ia`.
- O coletor local consulta todos os portais, distribui o limite entre as fontes e não encerra após a primeira resposta.
- URLs por bairro corrigidas para ZAP, VivaReal, Casa Mineira, Loft, QuintoAndar e Mercado Livre.
- QuintoAndar normalizado a partir do estado estruturado da página, com preço, área, quartos, banheiros e vagas.
- Links genéricos, páginas institucionais e resultados fora da localização deixaram de ser tratados como anúncios.
- Bloqueios de proteção do portal aparecem no painel como falha daquela busca, sem provocar erro 502 quando outras fontes respondem.

## Evidências

- Backend: 22 testes, 109 asserções, todos aprovados.
- Frontend: 11 testes, todos aprovados.
- Build de produção aprovado.
- QA visual headless: desktop 1366 px e mobile 390 px, 2/2 aprovados, sem overflow horizontal.
- Capturas e resultado: `docs/qa/artifacts/apify-sources/`.
- Consulta real de apartamento em Perdizes, São Paulo: 16 anúncios válidos em seis fontes — ZAP Imóveis, VivaReal, OLX, Chaves na Mão, Loft e QuintoAndar.

## Dependências externas observadas

- `APIFY_API_TOKEN` está vazio no ambiente local. ImovelWeb e Facebook Marketplace dependem desse token porque bloqueiam a coleta direta.
- A API atual do Mercado Livre respondeu 403 sem `MERCADO_LIVRE_ACCESS_TOKEN`.
- Casa Mineira apresentou proteção automatizada e não possui ator dedicado configurado; o sistema não contorna essa proteção nem inventa anúncios.
- Essas fontes permanecem configuradas e mostram o estado real no painel, sem derrubar os resultados das fontes disponíveis.

## Configuração operacional

Definir `APIFY_API_TOKEN` no `.env`. O limite padrão é 25 resultados por portal e pode ser alterado por `APIFY_MAX_RESULTS_PER_PROVIDER`.
