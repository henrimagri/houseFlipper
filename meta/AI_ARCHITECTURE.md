# Arquitetura da IA imobiliária

Data: 19/10/2026.

## O que já existia

A tela **IA Inteligente** não usava modelo generativo. O fluxo era:

1. Filtros estruturados na UI.
2. `GET /api/ai-property-search`.
3. `PropertyPortalScraper` (Puppeteer + fallback PHP) e APIs Nestoria/Mercado Livre.
4. `PropertyValuationService` (`comparables-v3`) com anúncios pares e transações ITBI.
5. Ranking por `score` e salvamento opcional em `opportunities`.

Não havia histórico conversacional, embeddings, visão computacional nem visualização 3D.

## O que foi acrescentado sem substituir o motor

| Camada | Serviço | Função |
|---|---|---|
| Interpretação | `PropertyQueryInterpreter` | Português → filtros obrigatórios/preferidos |
| Conversa | `AiConversationService` | Sessão por usuário, follow-up, comparação |
| Inventário | `InventoryListingSearch` | Propriedades e leads da conta |
| Mercado | `AIPropertySearchController` | Continua sendo a única coleta de portais |
| Ranking | `PropertyMatchRanker` | Compatibilidade explicável + recomendação |
| 3D | `PropertyVisualizationService` | Subpágina por imóvel, GLB ou fallback |
| Fotos | `PhotoSceneAnalyzer` | Heurística de nome de arquivo, origem explícita |
| Produtos | `ProductEntitlement` | House Flipper / Corretor / Imobiliária |

A conversa **não inventa anúncios**. Sem correspondência obrigatória, devolve o bloqueio e sugestões.

## Produtos

O repositório tinha um único produto operacional (FlipControl / House Flipper). Corretores e imobiliárias não tinham assinatura no código. Foi adicionada a tabela `tenant_product_subscriptions` e o catálogo em `config/products.php`. Contas sem linha continuam com `house_flipper` para não quebrar o tenant atual.

Dashboards separados dos três produtos **não foram fabricados**. A IA e o visualizador 3D são serviços compartilhados com feature flags.

## Provedores

Nenhum LLM externo foi ligado. Interpretação é determinística e testável. Visão é heurística até existir provedor autorizado. Modelos 3D entram só por upload GLB/glTF validado.
