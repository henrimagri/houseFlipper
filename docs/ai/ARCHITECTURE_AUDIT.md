# Auditoria da arquitetura de inteligência artificial

**Data da evidência:** 20/10/2026
**Branch inspecionada:** `feat/flipcontrol-redesign-integracoes-qa`
**Escopo:** código e configuração presentes no diretório de trabalho, incluindo a evolução de IA ainda não commitada desta etapa.

> **Atualização posterior:** este documento registra o baseline anterior às Fases 3 e 4. O estado corrente inclui Ollama/Qwen local, Qdrant com embeddings, worker PyTorch/FastAPI, LoRA experimental, Depth Anything V2 Small, SAM 2.1 Hiera Tiny e COLMAP esparso com exportação GLB. Consulte `IMPLEMENTATION_PROGRESS.md`, `VISION_MODELS.md` e `PHOTOGRAMMETRY.md`. As linhas “ausente” abaixo são achados históricos do baseline.

## Resumo executivo

O produto é um monólito Laravel com frontend React/TypeScript. A IA efetivamente ativa no ambiente auditado é um pipeline determinístico: interpreta pedidos em português, consulta inventário e fontes imobiliárias, ranqueia resultados, executa ferramentas autorizadas e persiste conversas. Não existe modelo neural local em execução. O adaptador para OpenAI Responses API está implementado, mas permanece inativo porque `AI_PROVIDER=deterministic` e não há chave configurada no ambiente examinado.

Esta etapa acrescentou ao pipeline agentes lógicos por produto, autorização de ferramentas, telemetria, RAG textual com citações, memória opcional, feedback e avaliações. Esses agentes são rotas de orquestração dentro do Laravel; não são processos autônomos nem modelos treinados. O RAG usa busca lexical no banco, sem embeddings ou banco vetorial. Não existe fine-tuning.

O projeto possui visualização 3D funcional com Three.js, importação GLB, maquete paramétrica e editor de cena referenciado por fotos. A análise de fotos no backend usa metadados, nome do arquivo e amostragem de pixels por GD. Não há visão neural, segmentação, profundidade, fotogrametria, NeRF, Gaussian Splatting nem geração de malha 3D.

## Stack comprovada

| Camada | Estado real | Evidência |
|---|---|---|
| PHP 8.4.24 | Executado | `php -v`; backend em `app/` |
| Laravel 13.37.0 | Executado | `php artisan --version`; `composer.json` |
| React 18.3.1, TypeScript 5.9.3, Vite 6.4.4 | Executado e compilado | `frontend/package.json`, `npm ls --depth=0`, `npm run build` |
| Tailwind CSS 4.3.3 | Instalado e compilado | `frontend/package.json` |
| Three.js 0.186.1, React Three Fiber 8.18.0, Drei 9.122.0 | Instalado e usado | `frontend/src/components/GlbViewer.tsx`, `PropertyMaquetteViewer.tsx`, `PropertyPhotoSceneEditor.tsx` |
| Banco relacional via Eloquent | Executado | migrations e modelos em `database/migrations/` e `app/Models/` |
| Node.js 24.22.0 | Executado | `node --version`; scraper headless e build frontend |
| Python 3.14.7 | Disponível na máquina, ausente da aplicação | `python --version`; nenhum `.py`, `pyproject.toml` ou `requirements*.txt` rastreado |
| Go | Fora do runtime do SaaS | arquivos somente em `meta/concurrency-harness/`, um harness auxiliar preexistente |

## Componentes de IA e maturidade

| Tecnologia/capacidade | Implementado | Configurado | Funcionando/testado | Situação e evidência |
|---|---:|---:|---:|---|
| Interpretação imobiliária PT-BR | Sim | Sim | Sim | regras em `app/Services/Ai/PropertyQueryInterpreter.php`; avaliação 6/6 em `docs/ai/evaluation-results.json` |
| Busca e ranqueamento de imóveis | Sim | Sim | Sim, com dependência das fontes externas | `InventoryListingSearch.php`, `PropertyMatchRanker.php`, `AIPropertySearchController.php`; conectores externos podem retornar indisponibilidade e não são modelos de IA |
| Conversa persistente | Sim | Sim | Sim | `AiConversationService.php`, `AiSearchSession.php`, `AiSearchMessage.php`; testes HTTP e inspeção no navegador |
| Agentes por produto | Sim, como orquestração lógica | Sim | Parcialmente testado | catálogo e allowlists em `AiAgentCatalog.php` e `config/ai.php`; busca, resumo de CRM, carteira, RAG e síntese estão ligados; `visualization_context` está catalogado, mas ainda não é uma ferramenta conversacional executada |
| Tools autorizadas | Sim | Sim | Sim para bloqueio | `assertToolAllowed()` e teste `test_agent_tool_allowlist_blocks_unapproved_actions`; não existe acesso arbitrário a shell ou SQL |
| RAG | Sim, lexical | Sim | Sim | `AiKnowledgeService.php`, tabelas de documentos/chunks, `ai:knowledge:sync`, citações e teste de separação entre tenants |
| Embeddings/banco vetorial | Não | Não | Não | nenhum SDK/modelo de embedding, `pgvector`, Qdrant, Pinecone, Weaviate ou índice vetorial encontrado |
| Memória de sessão | Sim | Sim | Sim | critérios e resultados na sessão; múltiplas conversas e histórico |
| Memória persistente do usuário | Sim, opt-in | Sim | Sim | `AiUserPreference.php`, endpoints/UI e teste de isolamento; somente preferências permitidas são aceitas |
| Feedback e observabilidade | Sim | Sim | Sim | `AiResponseFeedback.php`, `AiToolRun.php`, painel administrativo e teste de propriedade do feedback |
| OpenAI Responses API | Adaptador implementado | Modelo padrão `gpt-5-mini`; chave ausente e provider determinístico | Não validado ao vivo | `AiResponseSynthesizer.php`; o fallback preserva a resposta verificada |
| Outros LLMs (Anthropic, Gemini, Mistral, Ollama) | Não | Não | Não | nenhum cliente, chave ou chamada encontrada |
| Modelos multimodais | Não | Não | Não | MediaPipe aparece apenas como dependência transitiva de Drei e não é importado pelo código da aplicação |
| Fine-tuning | Não | Não | Não | não há dataset de treino, job, pesos, adaptadores LoRA ou métricas de treinamento; o JSON de avaliação não é dataset de fine-tuning |
| Avaliação offline | Sim | Sim | Sim | `EvaluateAiCommand.php`, `resources/ai/evaluations/core.json`; resultado atual 6/6 |
| PyTorch | Não | Não | Não | pacote Python não instalado e nenhuma importação/configuração no repositório |
| TensorFlow | Não | Não | Não | pacote Python não instalado e nenhuma importação/configuração no repositório |
| FastAPI | Não | Não | Não | pacote Python não instalado, sem serviço ASGI ou contrato de API |
| Pi | Não incorporado | Não | Não | nenhum pacote `@earendil-works/pi-*`; somente estudo de padrões registrado em `REPOSITORY_RESEARCH.md` |
| Awesome LLM Apps | Não incorporado | Não | Não | nenhum código Agno/ADK/Streamlit copiado; exemplos foram avaliados como referência arquitetural |
| Visualização 3D | Sim | Sim | Sim, no frontend | GLB, maquete paramétrica e cena editável via Three.js |
| IA generativa 3D | Não | Não | Não | o backend documenta explicitamente quando a visualização é paramétrica/fotográfica e não fotogrametria |

## Arquitetura observada

1. O endpoint recebe a mensagem autenticada e o contexto do produto.
2. `PropertyQueryInterpreter` converte português em filtros estruturados.
3. `AiAgentCatalog` escolhe o fluxo e aplica a lista de agentes e ferramentas permitidos para Corretor, Imobiliária ou House Flipper.
4. `AiConversationService` executa busca de inventário/mercado, comparação, resumo operacional ou recuperação de conhecimento.
5. `AiToolTelemetry` registra ferramenta, duração, estado, modelo e uso informado pelo provedor.
6. `AiResponseSynthesizer` mantém a resposta determinística ou, quando habilitado, pede ao modelo apenas que redija fatos já verificados.
7. Sessão, mensagens, citações, feedback e preferências são persistidos com escopo de usuário, tenant e produto.

O isolamento dos três produtos permanece no `ProductEntitlement`, no contexto de rota e nas allowlists. Uma sessão de outro usuário ou de outro produto é rejeitada. O teste automatizado cobre isolamento entre usuários e tenants; a matriz completa de testes por papel/produto ainda deve crescer.

## Adequação às necessidades

### Pesquisa imobiliária

A arquitetura atual é adequada para filtros, inventário próprio, agregação de portais, deduplicação, ranqueamento explicável e comparação. O maior risco não é ausência de PyTorch: é disponibilidade, licença, estabilidade e qualidade das fontes externas. Embeddings podem ajudar em busca textual por descrição, mas não devem substituir filtros SQL de preço, localização, quartos e permissões.

### Agentes

Os agentes Laravel atuais cobrem a primeira necessidade de roteamento, autorização e auditabilidade com baixo custo operacional. Falta um executor de workflow com orçamento realmente aplicado por turno, cancelamento propagado às ferramentas, tentativas controladas e testes de integração por produto. Os padrões do Pi são úteis para isso, mas instalar o runtime Node do Pi agora duplicaria sessão, autorização e observabilidade já existentes.

### RAG e memória

O RAG lexical serve ao corpus pequeno atual e já fornece fonte/citação. Antes de introduzir embeddings, deve-se medir recall em um conjunto maior e implementar remoção/reindexação e tratamento de documentos desatualizados. A memória opt-in guarda preferências estruturadas; ela não deve armazenar conversa privada para treinamento.

### Imagens e reforma

As heurísticas atuais não reconhecem com confiabilidade cômodos, objetos, defeitos, medidas ou profundidade. Um serviço Python passa a ser tecnicamente justificável quando houver requisito mensurável para segmentação, estimativa de profundidade, detecção de elementos ou comparação visual de reforma. Para geração conceitual de imagens, uma API multimodal hospedada tende a ser mais simples do que operar GPU local.

### Geração 3D

Three.js atende visualização, interação e edição de cenas, mas não gera geometria fiel a partir de fotos. Reconstrução 3D exige pipeline próprio de visão/fotogrametria, múltiplas imagens calibradas e GPU. A GTX 1660 Ti detectada tem 6 GB de VRAM; é uma limitação provável para modelos modernos de reconstrução ou difusão em produção, conclusão que precisa de benchmark do modelo escolhido. Arquivos GLB enviados e maquetes paramétricas existentes devem continuar sendo o caminho principal.

## Integração recomendada de Python/PyTorch, sem reescrever Laravel

Python não é necessário para busca estruturada, RAG textual, memória, tool calling ou síntese via API. Ele é recomendado somente para uma capacidade de visão que ultrapasse as heurísticas atuais.

Quando essa capacidade for aprovada, criar um serviço isolado:

- FastAPI como camada HTTP fina, com contrato versionado (`POST /v1/vision/analyze`) e OpenAPI.
- Worker assíncrono para jobs demorados; Laravel mantém autenticação, tenant, upload, autorização e estado do job.
- Armazenamento por URL assinada ou objeto privado; não enviar credenciais do tenant ao worker.
- Resposta estruturada com versão do modelo, licença, confiança, limitações e artefatos derivados.
- PyTorch somente se o modelo selecionado exigir PyTorch. Fixar versão compatível com Python/CUDA depois de um protótipo reprodutível.
- Implantação CPU para testes pequenos ou GPU hospedada para produção; não assumir que a GPU local é suficiente.
- Timeouts, idempotência, fila, callback assinado, exclusão dos artefatos e telemetria de custo.

Antes da instalação: escolher tarefa e modelo, confirmar licença dos pesos e do dataset, uso comercial, política de fotos, memória mínima/VRAM, versões suportadas de Python/CUDA, custo por imóvel e qualidade em um conjunto brasileiro autorizado. TensorFlow não agrega valor se o modelo escolhido já usar PyTorch; manter apenas um framework reduz imagem, CVEs e operação.

## Matriz de decisão final

| Tecnologia | Existente | Recomendação | Justificativa |
|---|---|---|---|
| Laravel/Eloquent | Sim, produção | Manter como orquestrador e autoridade | já concentra autenticação, tenants, produtos, banco e regras |
| Pipeline determinístico | Sim e testado | Manter para filtros, cálculos e fallback | resultados auditáveis e baixo custo |
| OpenAI Responses API | Implementado, inativo | Ativar apenas com chave backend e teste de custo/qualidade | melhora redação sem entregar ao modelo a decisão sobre fatos |
| Agentes Laravel | Sim, parciais | Completar executor e testes por produto | evita um segundo runtime enquanto conserva autorização existente |
| Pi | Ausente | Reusar padrões; não instalar agora | lifecycle, hooks e tool calling são úteis, mas a adoção duplicaria infraestrutura |
| Awesome LLM Apps | Ausente | Usar como catálogo de padrões | exemplos não trazem isolamento multi-tenant pronto e usam stacks diferentes |
| RAG lexical | Sim e testado | Manter no corpus pequeno; medir recall | solução simples e já isolada por tenant |
| Embeddings + vetor | Ausente | Prototipar depois de corpus/eval maiores | útil para linguagem semântica, mas adiciona custo e governança |
| Memória opt-in | Sim e testada | Expandir apenas com campos estruturados | reduz risco de privacidade e vazamento |
| Fine-tuning | Ausente | Não iniciar | ainda não existe falha mensurada que exija alterar pesos |
| FastAPI | Ausente | Adotar apenas para worker de visão | fronteira clara entre SaaS e computação ML |
| PyTorch | Ausente | Condicional ao modelo visual escolhido | ecossistema forte para visão; custo/GPU/licença devem ser validados primeiro |
| TensorFlow | Ausente | Não adicionar em paralelo | nenhum requisito ou modelo selecionado o exige |
| Modelo multimodal hospedado | Ausente | Avaliar para análise/geração conceitual | reduz operação inicial; exige política de dados e orçamento |
| Three.js/GLB | Sim e funcional | Manter | resolve visualização e edição interativa já entregues |
| Reconstrução 3D neural | Ausente | Pesquisa isolada, não integrar ainda | demanda dados, GPU, validação geométrica e licença específicas |

## Lacunas verificadas

- O limite `AI_MAX_TOOL_CALLS` está configurado, porém ainda não é um contador aplicado pelo executor; os fluxos atuais são estruturalmente finitos.
- O adaptador OpenAI não possui teste real com credencial nesta auditoria.
- A suíte de RAG ainda não cobre remoção, reindexação e documento obsoleto.
- Não há avaliação de precisão visual porque não existe modelo visual.
- Não há benchmark de custo/latência das fontes externas nem de um futuro worker GPU.
- A cobertura de segurança comprova casos centrais de usuário/tenant/tool, mas não substitui uma matriz completa por papel e produto.
