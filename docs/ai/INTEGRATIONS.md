# Fontes imobiliárias e integrações — política para IA

## Regra central

Cada integração precisa declarar **capacidades comprovadas**, não apenas um nome de portal. Publicação de anúncios para portal não equivale à API para pesquisar todos os anúncios do portal.

## Classes

| Classe | Exemplos para investigar | Consulta nacional garantida? |
|---|---|---|
| Imóveis próprios e parceiros | banco do SaaS, feeds API/XML autorizados | depende da carteira/contrato |
| Portais de classificados | ZAP, VivaReal, OLX, Imovelweb, Chaves na Mão, Loft, QuintoAndar, Mercado Livre | não; verificar acesso de leitura para cada contrato |
| Leilões/vendas bancárias | CAIXA Imóveis, VendasGov, leiloeiros contratados | arquivos/consultas oficiais quando permitidos; APIs variam |
| Registros e cartórios | ONR / RI Digital, SINTER | sem pressupor API pública nacional de consulta massiva |
| Indicadores de preço | FipeZAP, DataZAP, bases públicas municipais | são indicadores; nem sempre preços efetivos de venda |
| Geografia | IBGE, MapLibre, OpenStreetMap, geocoding licenciado | cobertura e licença dependem do serviço |

## Capabilities a modelar

```json
{
  "provider": "nome",
  "can_search_public_listings": false,
  "can_import_authorized_inventory": false,
  "can_publish_owned_listings": false,
  "can_read_auction_catalog": false,
  "requires_contract": true,
  "status": "unverified"
}
```

Preencher somente após consultar documentação/contrato e testar credenciais. Não classificar indisponível como “0 resultados”.

## Qualidade e direitos

Guardar origem, ID externo, data da coleta, última atualização, tipo de preço, fotos licenciadas, status e direito de exibição/republicação. Deduplicação entre fontes é obrigatória. Não exibir localização precisa protegida do proprietário.

## Importação de fontes oficiais

Caso dados de leilões estejam disponíveis como CSV/XLSX oficial e a reutilização seja permitida, implementar pipeline idempotente de download/importação/normalização, com rastreabilidade, validação e expiração. Não inventar endpoint REST nem misturar venda direta com leilão.

## Erros visíveis para IA e UI

- `not_configured`: credenciais ausentes.
- `restricted`: contrato/permissão não concedida.
- `unavailable`: falha temporária/timeout.
- `no_results`: consulta executada corretamente e sem resultados.
- `ok`: consulta executada, contagem e proveniência verificadas.

## Próxima ação

Após inventário dos conectores existentes, atualizar esta tabela no repositório com documentação oficial, URL autorizada, custos, quotas, capacidade de leitura e teste real de cada conector.
