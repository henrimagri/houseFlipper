# Progresso da implementação de IA

## Estado atual

| Fase | Estado | Evidência / bloqueio |
|---|---|---|
| 0 — Diagnóstico e baseline | Testado | `ARCHITECTURE_AUDIT.md`; stack, provedores, GPU, Python e repositórios externos verificados |
| 1 — Busca confiável | Implementado, testado parcialmente | interpretador e ranker avaliados 6/6; inventário e agregadores existem; disponibilidade externa varia |
| 2 — Tools e autorização | Implementado e testado | allowlist por agente/produto, telemetria e limite efetivo de chamadas por solicitação |
| 3 — Memória e conversa | Implementado e testado | sessões, histórico, arquivamento, preferência opt-in e isolamento de usuário |
| 4 — RAG | Implementado e testado para corpus pequeno | embeddings reais, Qdrant, recuperação híbrida, citações, reindexação/remoção e pós-filtro de tenant; benchmark 3/3 |
| 5 — Agentes por produto | Implementado parcialmente | roteamento e fluxos de busca/CRM/carteira/RAG; visão continua heurística |
| 6 — Visão/reforma/3D | Implementado e testado parcialmente | Depth Anything V2 Small + SAM 2.1 executados; COLMAP esparso, malha de prévia e GLB integrados; etapa densa requer worker CUDA |
| 7 — Avaliação/treinamento | LoRA executado, não promovido | adapter real de 2.175.168 bytes carregado na GPU; loss melhorou, qualidade gerada insuficiente |

## Última execução

- **Data:** 20/10/2026.
- **Objetivo:** auditar e evoluir a plataforma de IA sem duplicar funcionalidades existentes.
- **Branch:** `feat/flipcontrol-redesign-integracoes-qa`.
- **Implementação:** catálogo de agentes, allowlists, tools operacionais, telemetria, RAG lexical, memória opt-in, feedback, histórico, UI do consultor, métricas administrativas e avaliação offline.
- **Migração:** `2026_10_20_002000_create_ai_platform_tables` aplicada com sucesso no ambiente local.
- **Conhecimento:** `php artisan ai:knowledge:sync` sincronizou 3 documentos.
- **Avaliação:** `php artisan ai:evaluate` aprovou 6/6 casos; artefato em `evaluation-results.json`.
- **Testes focados:** `AiPlatformTest` aprovou 3 testes e 13 assertions antes da rodada final completa.
- **QA visual:** consultor House Flipper verificado no navegador com resumo de carteira, feedback, memória, histórico e timeline de ferramentas.
- **Limitações:** OpenAI não validado com chave; sem embeddings, modelo multimodal, fine-tuning ou geração 3D neural; conectores externos dependem de disponibilidade/licença.

## Inventário encontrado no código

- **Backend:** PHP 8.4.24, Laravel 13.37.0, Sanctum/Eloquent.
- **Frontend:** React 18.3.1, TypeScript 5.9.3, Vite 6.4.4, Tailwind 4.3.3.
- **LLM atual/provedor:** determinístico; adaptador OpenAI implementado e inativo no ambiente auditado.
- **Orquestrador:** `AiConversationService` + `AiAgentCatalog`, dentro do Laravel.
- **Modelo de imóveis:** inventário tenant-scoped, leads, oportunidades e resultados normalizados de fontes externas.
- **Rotas:** sessão, mensagens, histórico, arquivamento, feedback, preferências e busca em `routes/api.php`.
- **Memória:** sessão persistida e preferências estruturadas opt-in.
- **Busca:** banco local, Apify/portais headless e Mercado Livre conforme configuração/disponibilidade.
- **Test runners:** PHPUnit/Laravel, Vitest e comando `ai:evaluate`.
- **Pi:** não instalado; padrões de lifecycle recomendados, runtime adicional rejeitado nesta fase.
- **Awesome LLM Apps:** exemplos avaliados; nenhum agente ou pacote copiado.

## Registro incremental

```text
2026-10-20 — Etapas 0 a 7 (primeiro incremento)
Objetivo: diagnosticar o código real e entregar a primeira plataforma auditável de agentes/RAG/memória/eval.
Arquivos modificados: serviços e modelos Ai*, migration de plataforma, rotas/controller, painel React, comandos e recursos de conhecimento/avaliação, documentação.
Risco/decisão: manter Laravel como autoridade; não instalar Pi, Python, PyTorch, TensorFlow ou FastAPI sem caso mensurável.
Comandos/testes: migration OK; knowledge sync 3; eval 6/6; AiPlatformTest 3/3; rodada completa registrada abaixo após a validação final.
Evidência: ARCHITECTURE_AUDIT.md, evaluation-results.json e tests/Feature/AiPlatformTest.php.
Pendências: budget executável, RAG lifecycle, matriz RBAC, validação real opcional do provedor e protótipo visual somente após aprovação técnica/econômica.
Próxima ação: ampliar testes de autorização e medir recall do corpus antes de escolher embeddings.
```

## Resultado da validação final

- `php artisan test`: **25/25 testes aprovados, 122 assertions**.
- `npm test -- --run`: **11/11 testes aprovados**.
- `npm run build`: compilação TypeScript/Vite aprovada; o Vite manteve avisos de chunks grandes já identificados.
- `php artisan ai:evaluate`: **6/6 avaliações aprovadas**.
- `php artisan route:list --path=api/ai --except-vendor`: **9 rotas de IA registradas**.
- `php artisan migrate:status`: migration da plataforma de IA no batch 19, estado **Ran**.
- Laravel Pint: arquivos PHP alterados formatados com sucesso.

## 20/10/2026 — Fase 3 neural

| Recurso | Estado | Evidência |
|---|---|---|
| LLM local | Implementado e testado | Ollama 0.40.6 + Qwen2.5 3B; probe e conversa real por `AiConversationService`; fallback determinístico preservado |
| RAG vetorial | Implementado e testado | Qwen3 Embedding 0.6B + Qdrant 1.19.2; 6 chunks; benchmark híbrido Recall@4/MRR 1,0/1,0 |
| LoRA/fine-tuning | Implementado e testado, não promovido | PyTorch 2.8 CUDA 12.6; Qwen2.5 0.5B; adaptador safetensors real, SHA e avaliação registradas |
| Visão neural | Bloqueado por dados | nenhum conjunto real autorizado; endpoints retornam indisponibilidade explícita, sem fingir GD como neural |
| Reconstrução 3D | Bloqueado por dados/infraestrutura | sem fotos multiângulo e sem COLMAP; worker rejeita captura inadequada; Three.js preservado |

Execuções novas:

- `php artisan ai:local:probe`: provider local, digest registrado, 28.461 ms no primeiro carregamento.
- `php artisan ai:knowledge:reindex --force`: 6 chunks, 0 falhas.
- `php artisan ai:rag:benchmark`: lexical 0/0; híbrido 1,0/1,0.
- `php artisan ai:worker:probe`: FastAPI autenticado, PyTorch/CUDA/GPU e adaptador confirmados.
- `train_lora.py`: treino real na GTX 1660 Ti, 15,76 s total, pico VRAM 1.291,21 MB; validação 2,924 → 2,819.
- `evaluate_adapter.py`: adaptador carregado, SHA `f4ec9941fe2e19fd3b4d6ea75b2f2b0ddc61c7c66789455a09d66f8c5771beb7`; não promovido por resposta qualitativa ruim.
- PHP: 32/32 testes e 136 assertions.
- Worker Python: 3/3 testes.
- Frontend: 11/11 testes e build aprovado.

Este era o bloqueio ao final da Fase 3. A Fase 4 abaixo removeu o bloqueio técnico com fixtures próprias autorizadas; a validação comercial com fotos reais continua pendente.

## 20/10/2026 — Fase 4: visão neural e fotogrametria

| Recurso | Estado | Evidência |
|---|---|---|
| Profundidade neural | Implementado e testado | Depth Anything V2 Small, Apache-2.0; 984,06 ms e 186,18 MB VRAM na execução integrada Laravel → FastAPI |
| Segmentação neural | Implementado e testado | SAM 2.1 Hiera Tiny, Apache-2.0; 811,05 ms e 282,72 MB VRAM; máscara e overlay persistidos |
| Jobs e isolamento | Implementado e testado | jobs UUID, token interno, tenant/imóvel/asset validados, armazenamento privado e quota de 512 MB por visualização |
| Captura multivista | Implementado e testado | upload múltiplo, instruções, aceite e portão de qualidade para 8–120 imagens |
| SfM COLMAP | Implementado e testado | COLMAP 4.2.1 BSD-3-Clause; 18/18 câmeras e 4.794 pontos, erro médio 0,179462 px |
| Malha e Three.js | Implementado e testado | PLY Delaunay esparso e GLB íntegro; frontend identifica `photogrammetry_preview` e não declara fidelidade densa |
| Reconstrução densa | Bloqueado no worker local | build oficial sem CUDA; contrato preparado para worker Linux/CUDA externo |
| Avaliação ampliada | Implementado e testado | corpus determinístico ampliado de 6 para 12 casos, 12/12 aprovados |
| Budget de ferramentas | Implementado e testado | contador interrompe a solicitação com 429 antes da chamada excedente |
| Filtro vetorial | Verificado | Qdrant recebe filtro de tenant/produto antes da busca; pós-filtro relacional permanece ativo |

Execuções e artefatos:

- Evidências visuais e geométricas em `docs/ai/evidence/phase4/`.
- Fixture visual sintética própria: 960 × 640; original, profundidade, máscara, overlay e JSON.
- Fixture multivista própria: 18 vistas de 960 × 720; manifest de licença e posições de câmera.
- PLY esparso: 72.098 bytes; malha PLY: 27.714 bytes; GLB: 26.896 bytes.
- PHP: 36/36 testes, 151 assertions.
- Avaliações da IA: 12/12.
- Worker Python: 3/3 testes.
- Frontend: 11/11 testes; build Vite aprovado.
- Fluxo no navegador: lote de 18 fotos selecionadas, confirmação de autorização, reconstrução COLMAP concluída e GLB aberto no visualizador autenticado.

Limitação remanescente: uma execução com fotografias reais autorizadas do mesmo imóvel ainda é necessária para medir fidelidade comercial. As fixtures comprovam o pipeline e os formatos, não a qualidade de uma captura real.

## 20/10/2026 — Assistente multimodal e empresarial

| Recurso | Estado | Evidência / limite |
|---|---|---|
| Conversação geral | Implementado e testado | roteamento `general` evita busca imobiliária; Qwen2.5 3B recebe histórico recente saneado e fallback determinístico permanece ativo |
| Assistente empresarial | Implementado e testado | intenções `business`, `marketing` e `text_creation`, prompts próprios e nenhuma consulta privada sem ferramenta autorizada |
| Agentes por produto | Implementado e testado | allowlists preservam Corretor, Imobiliária e House Flipper; ferramentas continuam validadas pelo Laravel |
| Continuidade e memória | Implementado e testado | pedidos de continuação/revisão reaproveitam contexto limitado; preferências continuam opt-in e isoladas |
| Chat central | Implementado e testado | textarea expansível, Enter/Shift+Enter, cancelamento local, copiar, feedback e reescritas no mesmo endpoint |
| Streaming de tokens | Parcialmente implementado | indicador e cancelamento de requisição existem; o endpoint Laravel ainda devolve a resposta concluída, sem SSE/token streaming real |
| Avaliações | Implementado e testado | 104 casos revisados em 13 categorias, 104/104 determinísticos aprovados; julgamentos subjetivos permanecem separados |
| Detecção semântica | Implementado e testado | DETR ResNet-50 Apache-2.0 em CUDA; 7 objetos detectados em fixture sintética própria, integrados à análise privada do imóvel |
| Editor 3D inteligente | Implementado e testado | ações estruturadas para cores, materiais, móveis, mover, girar, escala, iluminação, versões e desfazer/refazer; sem JavaScript arbitrário |
| Reconstrução densa/texturizada | Bloqueado por infraestrutura e dados | COLMAP Windows sem CUDA e ausência de captura imobiliária real autorizada; pipeline esparso/GLB continua funcional |
| Fine-tuning | Experimental, não promovido | adaptador 0.5B incompatível com o modelo ativo 3B e qualidade anterior insuficiente |

Execuções desta etapa:

- `php artisan ai:evaluate`: 104/104 casos revisados aprovados; o conjunto revelou e validou a correção de `R$ 1,2 milhão`.
- DETR FP32: sofá, mesa de jantar, duas cadeiras, duas plantas em vaso e uma tigela; confiança entre 94,93% e 99,80%.
- Integração no navegador: upload privado, profundidade, segmentação e objetos persistidos no imóvel local de QA.
- PHP: 51/51 testes e 201 assertions; Python/FastAPI: 3/3; frontend: 11/11 e build Vite aprovado.
- A avaliação automática cobre regras observáveis; qualidade editorial, persuasão, aconselhamento jurídico, fidelidade visual e fidelidade 3D exigem revisão humana.

## 20/10/2026 — Correção do pipeline foto → cena 3D

| Capacidade | Implementada | Execução neural/geométrica real | Qualidade visual validada | Bloqueios |
|---|---|---|---|---|
| Classificação de cômodos | Parcial | DETR real + regra explicável por objetos; sala 94% na fixture | Fluxo Laravel/FastAPI/UI validado | Falta classificador neural especializado e corpus autorizado |
| Segmentação arquitetônica | Não | SAM 2 refina região por pontos, sem rótulo arquitetônico | Não | Checkpoint/classes/licenças comerciais ainda não aprovados |
| Detecção de objetos | Sim | DETR ResNet-50 real | 7 objetos conferidos na fixture | Limitada às classes COCO |
| Profundidade | Sim | Depth Anything V2 real | Mapa e integração conferidos | Profundidade relativa, sem escala métrica |
| Pintura em fotografias | Parcial funcional | SAM 2 + composição real | Antes/depois salvo e reaberto no navegador | Refinamento de bordas e materiais avançados pendentes |
| Ambiente 3D aproximado | Parcial funcional | Cena paramétrica gerada das evidências reais | Sala e seis objetos conferidos no navegador | Primitivas; falta catálogo texturizado e superfícies semânticas |
| Fotogrametria esparsa | Sim, diagnóstico | COLMAP real, 18/18 câmeras | Não aprovada como resultado comercial | Nuvem esparsa não representa superfície completa |
| Fotogrametria densa | Configurada | Não executada neste host | Não | Requer Linux, NVIDIA e COLMAP CUDA ou infraestrutura autorizada |
| Malha texturizada | Não | Não | Não | Depende da reconstrução densa aprovada e texturização |
| Editor 3D | Parcial | Three.js com seleção, transformação, materiais, iluminação, histórico e persistência | Cena aproximada aberta no navegador | Catálogo profissional e QA visual multiambiente pendentes |
| Comandos de IA no editor | Sim | Ações estruturadas validadas pelo Laravel | Alterações persistíveis cobertas por testes | Linguagem e casos espaciais complexos ainda limitados |
| Treinamento especializado | Preparação apenas | Pipeline de dataset validado; nenhum fine-tuning visual | Não | Requer dataset licenciado, anotado e revisão humana |

Correções e evidências:

- A UI deixou de mostrar 0% quando não há classificador de cômodo; usa estados ausente, pendente, falha, sem objetos, não classificado e disponível.
- A inferência não soma duplicatas da mesma classe. `phase5-semantic-room.png` resultou em `living`, confiança do cômodo 94%, confiança máxima de objeto 100%, com sofá, mesa, duas cadeiras e duas plantas na cena.
- Evidência neural antiga com origem `neural` ou nova `neural_object_detection` é preservada ao reconstruir a cena.
- Simulação real em `docs/ai/evidence/phase6-photo-paint-*`: 918,17 ms, pico de 232,94 MB VRAM e cobertura de 25,1891%.
- O portão geométrico mede topologia e bounding box. A malha Delaunay esparsa fragmentada foi rejeitada e não aparece como GLB final.
- O fluxo denso opt-in e o compose GPU foram preparados, sem executar serviço pago ou declarar sucesso sem GPU compatível.

Validação final: PHP **58/58**, 247 assertions; worker **21/21**; frontend **12/12**; TypeScript/Vite aprovados; avaliação da IA **104/104**. No navegador, análise neural, pintura com histórico/reabertura, sala aproximada e fallback de reconstrução insuficiente foram conferidos.

Detalhes: `3D_VISUAL_QUALITY.md`, `VISION_TRAINING_PLAN.md` e `RECONSTRUCTION_VALIDATION.md`.

## 20/10/2026 — Simulador visual profissional (incremento)

| Capacidade | Estado | Evidência / limite |
|---|---|---|
| Máscara fotográfica | Implementado e testado parcialmente | SAM 2 recebe pontos positivos/negativos, centros de exclusão derivados das caixas DETR, seleção por componentes conectados e fechamento/abertura morfológica; 23 testes do worker aprovados. A validação com fotos imobiliárias reais autorizadas continua necessária. |
| Catálogo 3D | Implementado parcialmente | catálogo tipado com dimensões, materiais, âncora, licença, autoria e origem; `SheenChair` e `SheenWoodLeatherSofa` são GLB redistribuíveis do Khronos glTF Sample Assets. Os demais tipos continuam paramétricos até seleção de novos ativos licenciados. |
| Cena paramétrica | Implementado e testado | quatro superfícies de parede independentes no grafo, dimensões editáveis e identificadas como estimadas, piso/teto, janela, luz natural, tapete, luminária e móveis derivados das evidências. A vista usa corte frontal para não iniciar atrás de uma parede opaca. |
| Renderização | Implementado e validado visualmente | sRGB, ACES Filmic, PBR, sombras PCF, texturas procedurais de piso, iluminação por presets e carregamento progressivo. O primeiro quadro aparece antes de fotos/GLBs; modelos pesados são carregados sob demanda em `Decorar`. |
| Comandos espaciais | Implementado e testado | “parede atrás do sofá” altera só `living-wall-south`; substituição exige seleção/confirmação; piso, iluminação e decoração de alto padrão geram ações estruturadas. |
| Dimensões e persistência | Implementado e testado | largura, comprimento e pé-direito validados no Laravel e persistidos com origem `user`; versões continuam usando o fluxo existente. |
| Treinamento arquitetônico | Avaliado, não executado | nenhum checkpoint/dataset especializado foi instalado: ADE20K possui restrição não comercial e checkpoints encontrados não forneceram licença comercial suficientemente clara. Não há checkpoint fictício. |
| Reconstrução densa | Preservado, bloqueado | compose Linux/CUDA, COLMAP e portões de geometria permanecem preparados; nenhuma GPU externa paga foi acionada. |

Validação desta etapa:

- Laravel direcionado: **10/10 testes, 55 assertions**; suíte completa: **61/61 testes, 256 assertions**. Após a adaptação para cenas legadas, `SceneCommandInterpreterTest` foi executado novamente: **7/7 testes, 24 assertions**.
- Worker FastAPI/PyTorch: **23/23 testes** em 329,48 s.
- Frontend: **12/12 testes**.
- TypeScript: `tsc -b` aprovado; build Vite de produção aprovado nesta etapa, com o aviso já conhecido de chunks grandes.
- Avaliação da IA: **104/104** casos aprovados.
- Navegador: cena abriu com carregamento imediato, piso texturizado, paredes em corte, janela, sofá, mesa e plantas; a sobreposição preta de foto indisponível foi removida. Após executar o servidor PHP local sem limite artificial de 30 s, o comando `Pinte apenas a parede atrás do sofá de azul.` concluiu pela UI, retornou a confirmação específica e a parede foi renderizada em azul. A compatibilidade com cenas legadas que armazenavam uma única parede também foi conferida. Captura: `evidence/phase7-scene-editor-blue-wall.png`; comparação fotográfica preservada em `evidence/phase6-photo-paint-comparison.png`.

Pendências reais: ampliar o catálogo licenciado por categoria, avaliar máscaras em um conjunto imobiliário autorizado e executar reconstrução densa em Linux/CUDA com captura multivista adequada.

## 20/10/2026 — Validação com ambiente real multivista autorizado

Foi usado o Meta Eyeful Tower `playroom_small`: 126 fotografias reais do mesmo ambiente e 126 versões sem distorção, calibração e malha de referência oficiais. O conteúdo foi relicenciado sob MIT, com cópia da licença preservada. Nenhuma imagem de portal imobiliário foi usada. O espaço é um ambiente interno real de demonstração, não um imóvel residencial do proprietário.

| Etapa | Estado | Evidência e limite |
|---|---|---|
| Dataset e licença | Concluído | manifesto SHA-256 em `storage/app/ai-real-datasets`; contact sheets em `docs/ai/evidence` |
| DETR | Executado em fotografia real | `couch` 80,736%; classes COCO não identificam cômodo ou material |
| SAM 2 | Executado em fotografia real | máscara guiada com 5,9698% de cobertura; não cria semântica arquitetônica |
| Depth Anything V2 | Executado em fotografia real | CUDA, profundidade relativa; sem escala métrica |
| COLMAP esparso | Dados oficiais inspecionados | calibração/exportação oficial disponíveis; não apresentados como execução local |
| COLMAP denso local | Bloqueado | Windows COLMAP sem GPU e WSL2 ausente; nenhum worker pago acionado |
| Malha e textura | Validada com referência oficial | 437.239 vértices, 843.112 faces, textura 4K; conversão local para GLB de 42.307.864 bytes |
| Three.js | Validado no navegador | sRGB/ACES, enquadramento, zoom, órbita, salvamento e reabertura aprovados |
| Edição semântica do GLB | Não atendida | malha oficial monolítica; editor paramétrico continua editável, mas o GLB não separa paredes, piso e móveis |
| Qualidade comercial | Não aprovada | ambiente reconhecível, porém há bordas fragmentadas, vazios, regiões pretas e superfícies incompletas |

Tempos/recursos: requisição neural 5,587 s; DETR 173,22 ms e 578,72 MB VRAM; SAM 2 840,30 ms e 282,72 MB; Depth Anything V2 1.070,51 ms e 186,18 MB; abertura do OBJ 506,132 s; exportação 2,856 s; preparação da textura 3,677 s. GPU local: GTX 1660 Ti, 6 GB.

Correções implementadas durante a validação:

- limites separados de upload: 25 MB para imagens e 64 MB para modelos 3D, mantendo compatibilidade com `VISUALIZATION_MAX_KB`;
- viewer com sRGB, ACES Filmic, iluminação mais legível, descarte correto de recursos e enquadramento inicial para malhas de reconstrução;
- teste Laravel confirma que GLB real pode usar o limite dedicado; testes frontend confirmam configuração do viewer.

Validação final desta etapa: frontend **12/12**, TypeScript aprovado e teste Laravel direcionado **1/1, 3 assertions**. O GLB texturizado foi importado, aberto, rotacionado, salvo e reaberto no navegador.

Conclusão honesta: a reconstrução densa própria de um imóvel real continua **não concluída**. Esta etapa comprova análise neural em fotografia real autorizada e integração de uma malha real de referência. A conclusão exige executar o pipeline denso em Linux/CUDA e repetir com captura autorizada de um imóvel residencial. Relatório completo: `REAL_PROPERTY_RECONSTRUCTION_VALIDATION.md`.

## 21/10/2026 — Estabilidade do worker e primeira geometria independente

O worker passou a ativar `faulthandler` antes de importar PyTorch/Transformers, gravar eventos JSONL por processo e registrar início, conclusão, duração e falha das operações nativas. A inferência compartilhada por DETR, SAM 2 e Depth Anything foi serializada por `RLock`, e foi adicionado descarregamento explícito de modelos com sincronização/limpeza CUDA.

A investigação do erro `python.exe` encontrou APPCRASH `0xc0000005` no executável CPython 3.12.10, offset `0xA462`. O mesmo código e offset também ocorreram no `pwsh.exe` empacotado. No relatório WER do Python, `python312.dll`, PyTorch e DLLs CUDA ainda não constavam entre os módulos carregados. Não existe dump/stack nativo disponível e, portanto, não há evidência para atribuir a falha a PyTorch ou à GPU. O processo neural longo permaneceu estável durante a reconstrução. O ambiente virtual aponta para o Python global 3.12.10; a substituição integral do ambiente não foi feita sem causa demonstrada.

A reprodução controlada estreitou o sintoma: `python -c "print(...)"`, `import numpy` e `import torch` terminaram sem novo evento; um processo curto que executa `from PIL import Image` termina com código 0, mas é seguido por um novo APPCRASH com o mesmo offset. O relatório desse processo contém somente o executável, DLLs do Windows/VC Runtime e a pilha gráfica DXGI, ainda sem `python312.dll`, Pillow, PyTorch ou CUDA. O worker FastAPI de longa duração continuou saudável com PyTorch 2.8.0+cu126 e CUDA 12.6. A mitigação operacional é manter um único worker persistente e evitar criar processos Python por requisição; a causa nativa externa ao interpretador continua sem stack conclusivo.

Foi implementado `calibrated_depth_fusion.py`, que lê fotos, poses e pontos SfM, executa Depth Anything V2, alinha a profundidade inversa à escala métrica por vista com ajuste robusto e funde a nuvem por voxel. A malha oficial não é lida durante a geração. O Poisson do COLMAP em profundidade 13 consumiu cerca de 850 MB e permaneceu mais de 18 minutos sem produzir saída. Profundidades 10 e 8 também não terminaram dentro do limite operacional. O pipeline agora limita profundidade/threads/tempo e usa uma superfície triangulada diretamente do mapa de profundidade calibrado quando o Poisson expira.

Resultado reproduzível em `storage/app/ai-real-results/playroom-small-independent/depth-fusion-18b`:

- 126 vistas disponíveis, 18 selecionadas e 17 aceitas;
- 104.633 pontos brutos e 87.862 pontos fundidos;
- 6.208 vértices, 12.096 faces, um componente, zero faces degeneradas, diagonal de 4,6225 m;
- GLB de 245.580 bytes, SHA-256 `5aa3fdfcf14b73aec35813a9c3667c38272dcdd85a06e6c47f87619cd3f60af8`;
- tempo total 165,348 s;
- comparação posterior e isolada com a referência: erro mediano gerado→referência 0,1086 m, 46,01% até 10 cm; completude referência→gerado 12,68% até 10 cm.

O baixo valor de completude confirma que esta saída é uma superfície parcial, baseada no maior componente de uma vista calibrada, acompanhada de nuvem multivista. Ela é adequada para validação técnica no visualizador, mas não substitui estéreo denso, não tem atlas UV, não é watertight e não foi aprovada para uso comercial.

A reconstrução HTTP também foi convertida em job assíncrono persistente: fila de um worker, estados `queued/processing/completed/failed`, estágio, percentual e erro consultáveis. O Laravel mantém a autoridade, valida tenant/usuário e importa os artefatos apenas após conclusão; a UI faz polling sem manter uma requisição pesada aberta. Testes focados: **4/4 Python aprovados**. O GLB independente foi enviado com sucesso para o cadastro local pelo fluxo real do navegador.

Após recarregar a página autenticada, o modelo persistiu e abriu novamente. A inspeção visual encontrou e corrigiu o enquadramento diagonal e o escurecimento de malhas abertas com cores por vértice. A câmera inicial agora usa o eixo mais fino da caixa envolvente, as faces são visíveis dos dois lados e o material de reconstruções coloridas é não metálico. A órbita manual foi validada. Captura: `evidence/real-property-independent-depth-fusion-threejs.png`. O render confirma uma parede e abertura reconhecíveis, mas também confirma a cobertura parcial; ele não foi promovido a resultado comercial.

Validação final desta etapa: worker direcionado **4/4**, Laravel de visualização neural **5/5 com 36 assertions**, frontend **12/12**, TypeScript e build Vite aprovados. O health check autenticado confirmou o worker em execução, CUDA disponível e VRAM alocada/reservada em 0 MB fora de inferência.

## 21/10/2026 — Consolidação na branch de proteção

O trabalho existente foi preservado e consolidado em `Correção-visua/proteção`, mantendo o histórico anterior por avanço direto da branch. Nenhum reset, descarte de arquivos ou alteração em `main` foi realizado.

Verificações executadas sobre o estado integrado:

- Laravel: **63/63 testes**, 261 assertions;
- worker FastAPI/PyTorch: **27/27 testes**; permanecem quatro avisos de depreciação de `on_event`, sem falha funcional;
- frontend: **12/12 testes**;
- TypeScript e build Vite: aprovados; permanece o aviso de chunks maiores que 500 kB para Three.js e mapa;
- `git diff --check`: aprovado.

A preparação do commit exclui por `.gitignore` o `.env`, datasets e resultados reais em `storage/app`, ambientes virtuais, logs do worker, pesos/tokenizer locais, malhas e texturas de referência do Eyeful Tower e duplicatas de execuções fotográficas. Os dois GLBs do catálogo de móveis permanecem porque são ativos redistribuíveis do produto, acompanhados por `frontend/public/models/furniture/LICENSES.md`. As fixtures multivista versionadas são sintéticas e possuem manifesto próprio.

Pendências registradas: migrar os eventos de inicialização do FastAPI para `lifespan`, dividir os chunks grandes do frontend e executar reconstrução estéreo densa em ambiente Linux/CUDA antes de qualquer aprovação comercial.

## 21/10/2026 — Ponte local ChatGPT ↔ Codex CLI no Windows

A ponte local foi estabilizada em `docs/ai/bridge.ps1`. A raiz é descoberta a partir da localização do script e validada por `.git`, Laravel e frontend; caminhos relativos não dependem mais do diretório atual. Git usa `git -C`, a branch `Correção-visua/proteção` é comparada exatamente e o script UTF-8 com BOM foi validado no Windows PowerShell 5.1.26100.9711 e PowerShell 7.6.5.

Controles implementados: validação da CLI e do login sem expor credenciais, esquema e limite de 32 KiB para a tarefa, origem local humana, aprovação explícita, lock exclusivo, bloqueio de `Task-ID`/SHA-256 duplicado, timeout com encerramento da árvore de processos, código de saída preservado, estado local e relatórios ignorados pelo Git, além de filtragem de padrões comuns de segredos. Os atalhos em `tools/ai-bridge` permitem verificar, consultar e executar sem digitar comandos no PowerShell.

Evidências executadas:

- `codex-cli 0.163.0-alpha.10.2` encontrado e autenticado via ChatGPT;
- `check` e `status` aprovados a partir de `C:\WINDOWS\system32` em PowerShell 7 e Windows PowerShell 5.1;
- caminho relativo `RepoPath=..\..` resolvido em relação ao script e `TaskPath` resolvido em relação ao repositório;
- tarefa sem cabeçalho rejeitada e execução sem `-Approve` bloqueada;
- E2E real com `codex exec`, `workspace-write` e tarefa diagnóstica: código 0 em 35,923 s, sem alteração de arquivos pelo agente;
- segunda execução da mesma tarefa rejeitada por ID/hash;
- timeout de 5 s terminou com código 124, encerrou a árvore do processo e produziu relatório local;
- GitHub remoto e push em modo `--dry-run` aprovados para a branch autorizada; a indisponibilidade anterior de escrita em issues (HTTP 403) permanece registrada;
- `git diff --check` aprovado.

Arquivos locais preservados e não versionados por `.git/info/exclude`: `NEXT_TASK.md`, `Novo(a) Documento de Texto.txt`, `bridge.ps1.backup` e `bridge.ps1.bak2`. Nenhum foi apagado.

Limite real: não existe no repositório um canal oficial autenticado capaz de receber automaticamente uma conversa privada do ChatGPT. A entrada operacional permanece um arquivo local revisado e aprovado por uma pessoa. O retorno ocorre pelos commits enviados à branch que o monitor configurado consulta; cookies e tokens de sessão não são usados. O desenho de um receptor automático fica pendente até existir um canal oficial com credencial de serviço e permissão explícita.

## 21/10/2026 — Fusão multivista e cancelamento da ponte automática

As tentativas de integração automática ChatGPT ↔ Codex foram encerradas por decisão do proprietário. Os arquivos já existentes da ponte foram preservados sem novas alterações, tarefas remotas, leitura de sessões, verificações periódicas ou criação de conversas de coordenação. O desenvolvimento voltou ao pipeline de IA e 3D na branch `Correção-visua/proteção`.

O diagnóstico da reconstrução independente encontrou a causa principal da cobertura de 12,68%: quando o Poisson não concluía no Windows, o pipeline gerava superfícies para todas as vistas, mas exportava somente o maior componente. A correção remapeia faces durante a fusão por voxel, alinha sinais opostos de normais, remove triângulos degenerados/duplicados e preserva os componentes significativos de todas as vistas. O Poisson fica desativado por padrão no Windows, onde o subprocesso não encerra de forma confiável, e continua opt-in por `AI_POISSON_ENABLED`.

Execução real autorizada `playroom_small`, sem leitura da malha de referência durante a geração:

| Métrica | Baseline | Fusão multivista |
|---|---:|---:|
| Vistas selecionadas/aceitas | 18/17 | 42/39 |
| Vértices | 6.208 | 139.033 |
| Faces | 12.096 | 267.722 |
| Componentes | 1 (apenas o maior mantido) | 30 significativos |
| Precisão mediana gerado → referência | 0,121785 m | 0,095919 m |
| Gerado até 10 cm da referência | 40,29% | 51,67% |
| Completude mediana referência → gerado | 1,166620 m | 0,081763 m |
| Referência coberta até 10 cm | 12,71% | 55,41% |
| GLB | 245.580 bytes | 5.438.200 bytes |

As duas comparações usam agora amostragem determinística de até 10.000 vértices em cada direção, sem ICP e nas coordenadas COLMAP originais. O ganho de completude é de **42,70 pontos percentuais**, ou **4,36×**. O GLB novo possui SHA-256 `040fef6ef25c6bf8768e298c21b47bbe6c48abbafbc9fc355f342e3f74377056` e foi importado pelo fluxo autenticado do imóvel de QA.

Validação no navegador: o upload, carregamento, persistência e controles de órbita foram exercitados no Three.js. As capturas `evidence/multiview-fusion/baseline-viewer.png`, `multiview-front.png` e `multiview-orbit.png` registram antes, depois e segunda posição de câmera. A avaliação visual confirma cobertura maior, mas também grandes aberturas e superfícies desconectadas; `final_model_eligible` permanece `false` e qualidade comercial não foi declarada. Um teste de marching cubes em CPU não concluiu em tempo operacional útil e foi interrompido sem integrar artefatos ou dependências. O worker FastAPI também migrou de `on_event` para `lifespan`, descarrega modelos no encerramento e eliminou os quatro avisos de depreciação nos testes da API.

Verificações desta etapa: worker completo **30/30** antes da migração de ciclo de vida e API FastAPI **7/7** depois dela, sem avisos de `on_event`; Laravel de visualização neural **5/5, 36 assertions**; frontend **12/12**; TypeScript e build Vite aprovados. O build mantém o aviso conhecido de chunks maiores que 500 kB. O experimento, dataset, GLBs, pesos e resultados permanecem ignorados em `storage/app`; somente código, testes, documentação e capturas sem credenciais são preparados para commit.

Pendências: fechar as lacunas por TSDF/Poisson em runtime Linux/CUDA autorizado, gerar atlas UV/textura coerente, reduzir componentes sem descartar cobertura e repetir a avaliação visual em várias câmeras antes de promover qualquer reconstrução como modelo final.

## 21/10/2026 — Fase 8: costura conservadora, atlas fotográfico e regiões editáveis

O baseline foi repetido com a mesma amostragem determinística de 10.000 vértices, sem ICP e nas coordenadas COLMAP originais. A faixa de 20 cm foi acrescentada sem alterar os resultados anteriores: precisão 25,51%/51,67%/78,14% em 5/10/20 cm e completude 31,28%/55,41%/65,83%. A malha oficial continuou ausente da geração e foi aberta somente pelo comparador posterior.

A investigação confirmou que a fusão anterior unia vértices coincidentes, mas mantinha folhas trianguladas por vista e não resolvia oclusão nem continuidade volumétrica. Um marching cubes anterior já havia excedido o limite operacional. Nesta etapa não foi repetido Poisson/marching cubes sem limite e nenhuma superfície distante foi conectada para melhorar métricas.

Foi implementada costura limitada a **1,25 cm**, com chave espacial e família dominante de normal. Ela preserva paredes/pisos perpendiculares como regiões distintas, elimina faces colapsadas/duplicadas e mantém somente componentes que atendem ao mesmo limiar significativo usado no baseline. O candidato maior de 1,5 cm foi rejeitado por aumentar os componentes totais e não melhorar o candidato de 1,25 cm.

| Métrica | Fusão anterior | Fase 8 costurada |
|---|---:|---:|
| Vértices | 139.033 | 132.538 |
| Faces | 267.722 | 263.104 |
| Componentes significativos | 30 | 14 |
| Precisão mediana gerado → referência | 0,095919 m | 0,098261 m |
| Precisão até 5/10/20 cm | 25,51% / 51,67% / 78,14% | 25,11% / 50,73% / 77,52% |
| Completude mediana referência → gerado | 0,081763 m | 0,078980 m |
| Completude até 5/10/20 cm | 31,28% / 55,41% / 65,83% | 32,43% / 55,81% / 66,06% |
| Faces inválidas/degeneradas | 0 / 0 | 0 / 0 |
| Bordas abertas | não registrado | 12.052 |
| Arestas não manifold | não registrado | 2.326 |
| Watertight | não | não |

A redução de componentes trouxe um pequeno ganho de completude e uma pequena perda de precisão. O resultado foi mantido porque a continuidade melhorou sem alterar os limites espaciais, mas ele permanece uma prévia técnica. Buracos, arestas não manifold e folhas separadas continuam visíveis; `final_model_eligible` não é promovido.

### Textura e edição

Foi criado um atlas UV **4092 × 4092** a partir das fotografias autorizadas. Cada face é projetada nas câmeras calibradas e avaliada por enquadramento, ângulo, nitidez e um z-buffer de baixa resolução para oclusão. A exposição recebe correção mediana limitada a 0,72–1,38. Na malha costurada, 151.307 de 263.104 faces receberam fotografia válida (**57,51%**); 111.797 faces usam bloco neutro e não são declaradas fototexturizadas. O atlas tem 1.286.537 bytes. O GLB final possui 22.874.024 bytes, cinco malhas/materiais PBR identificados e SHA-256 `eb201b5561f65483a93e0b3b05bb4b97b8fb7d19098f662104f0abca9721861f`; a texturização medida levou 518,272 s. O pico de RAM observado na execução anterior equivalente foi 1.103 MB; essa etapa não executou inferência CUDA, e a VRAM não foi medida separadamente.

Cinco regiões candidatas são exportadas com origem, confiança, intervalos de faces e correção manual: parede, piso, teto, superfície horizontal e outra superfície. São inferências geométricas; portas, janelas e móveis não recebem classe arquitetônica fictícia. Materiais PBR carregam IDs `geometry-*`, o viewer usa raycast para selecionar somente a região atingida e o Laravel persiste `reconstruction_overrides` e histórico no `scene_graph`, sempre sob a autorização existente por tenant/usuário. O comando “pinte esta parede de azul” gera `set_reconstruction_material`; “desfaça a última alteração” restaura o estado anterior. Ambiguidade sem cor/seleção retorna confirmação em vez de executar.

O fallback calibrado foi conectado ao job assíncrono real no Windows. Quando o COLMAP denso opt-in não conclui, o worker converte o modelo esparso para texto, executa profundidade calibrada, costura e texturização, e publica `dense.ply`, `dense-mesh.ply` e `dense-model.glb` nos nomes já autorizados pela API. O Laravel continua sendo a autoridade que importa o GLB somente após `viewer_eligible` e persiste o resultado do job.

Validação:

- worker completo: **33/33 testes** em 235,30 s;
- Laravel completo: **64/64 testes**, 266 assertions em 217,768 s;
- frontend: **12/12 testes**;
- TypeScript/Vite: build aprovado em 8 min 34 s; permanece o aviso conhecido de chunks grandes;
- navegador: fotografia fonte e GLB próprio foram comparados nas vistas frontal, lateral e superior. O GLB final foi enviado pelo fluxo Laravel, aberto no Three.js, teve `geometry-ceiling_candidate` selecionado por raycast, recebeu o comando real “Pinte esta parede de azul.” somente na região selecionada e restaurou a última alteração pelo botão Desfazer. A textura torna objetos observados reconhecíveis, mas as capturas também registram buracos, folhas separadas e áreas neutras. Evidências em `evidence/multiview-fusion/phase8-*`, incluindo `phase8-final-app-edited.jpg` e `phase8-final-app-undo.jpg`.

Próxima prioridade: integração TSDF realmente volumétrica e limitada por blocos, com extração de superfície em runtime compatível. A etapa deve reduzir as 12.052 bordas abertas e 2.326 arestas não manifold sem usar a referência como entrada e sem perder a cobertura observada. A densificação COLMAP CUDA continua não executada neste host.

## 21/10/2026 — Fase 9: TSDF limitado, gate de regressão e comandos espaciais

Foi implementado `bounded_sparse_projective_tsdf`, um volume TSDF em CPU organizado em blocos NumPy. Cada atualização usa fotografia autorizada, profundidade Depth Anything V2 alinhada aos pontos SfM, intrínsecas escaladas e extrínsecas COLMAP `world → camera`. Somente a banda truncada ao redor de cada observação é alocada; espaço desconhecido não vira superfície. Voxel, truncamento, resolução e limite de blocos, peso mínimo, passo de pixels e máximo de vistas são configuráveis. O worker registra memória estimada, observações, blocos descartados, tempo, topologia e aceita cancelamento cooperativo por marcador de job.

O Open3D oficial 0.20.0 e, como contraprova, 0.19.0 foram instalados apenas no ambiente virtual local e testados no Python 3.12.10/Windows. Ambos falharam ao importar `open3d.cpu.pybind`; `Open3D.dll` retornou WinError 1114, embora `tbb12.dll` carregasse e a CPU suportasse AVX2. Nenhuma dessas versões foi adicionada aos requisitos. O pacote experimental foi removido do ambiente após o diagnóstico; sistema, driver, WSL2 e BIOS não foram alterados.

Ensaios reais, sempre gerados antes de abrir a referência:

| Configuração | Vistas | Blocos / memória estimada | Vértices / faces extraídos | Componentes | Precisão 10 cm | Completude 10 cm | Decisão |
|---|---:|---:|---:|---:|---:|---:|---|
| pequeno, voxel 5 cm | 8/8 | 542 / 5,55 MB | 2.794 / 3.948 | fragmento retido 1 | não comparado | não comparado | prova operacional, não promovido |
| voxel 4 cm | 39/42 | 940 / 9,63 MB | 91.734 / 167.176 | 2.505 | 49,28% | 47,90% | rejeitado |
| voxel 6 cm | 39/42 | 427 / 4,37 MB | 38.328 / 73.472 | 728 | 52,99% | 47,44% | rejeitado |
| Fase 8 preservada | 39/42 | — | 132.538 / 263.104 | 14 significativos | 50,73% | 55,81% | melhor prévia disponível |

O candidato de 6 cm melhorou a precisão isolada, mas perdeu 8,37 pontos percentuais de completude a 10 cm e permaneceu muito mais fragmentado. Ele possui 49.412 bordas abertas e 14.636 arestas não manifold, contra 12.052 e 2.326 na Fase 8. O candidato de 4 cm foi ainda pior: 119.278 bordas abertas e 31.395 arestas não manifold. Os dois foram rejeitados; nenhum GLB ou atlas TSDF foi promovido. `final_model_eligible` permanece falso. A causa dominante observada é a inconsistência entre mapas monoculares calibrados por vista: aumentar o voxel melhora a sobreposição, mas não elimina folhas e cruzamentos incompatíveis.

O fluxo existente agora permite selecionar `auto`, `calibrated`, `tsdf` ou `dense_colmap`, informa método solicitado/efetivo e oferece cancelamento. Resultado TSDF reprovado não é copiado para o modelo público. O fallback calibrado existente continua sendo a melhor prévia e a reconstrução densa permanece disponível somente quando o runtime confirma suporte.

A edição espacial foi corrigida no Laravel e revalidada no backend. Teto selecionado + “Pinte esta parede de azul” retorna confirmação e nenhuma ação; teto + “Pinte este teto de azul” produz ação estruturada com `expected_kind=ceiling_candidate`; “esta superfície” usa a região atual. IDs geométricos desconhecidos e divergência de tipo são recusados pela fábrica da cena. Desfazer/refazer mantêm pilhas limitadas e persistíveis.

O viewer realça a região selecionada, permite desmarcar, informa que a confiança é heurística geométrica, expõe vistas frontal/lateral/superior, luz, edição isolada e desfazer/refazer em um painel lateral que não cobre o centro do modelo.

Validação desta etapa: worker completo **38/38**; Laravel completo **67/67, 276 assertions** usando o PHP com SQLite; frontend **12/12**; TypeScript e build Vite aprovados. Uma tentativa com outro binário PHP falhou por ausência de `pdo_sqlite`, defeito do runtime e não do código. Resultados grandes permanecem ignorados em `storage/app`.

O backend local respondeu HTTP 200 e o Vite iniciou normalmente. A automação visual não conseguiu adquirir a aba `http://127.0.0.1:5173/app/house-flipper/imoveis/30/3d`: a política interna do controlador recusou a URL local apesar do protocolo HTTP. Nenhuma captura da Fase 9 foi fabricada e a barreira não foi contornada. A inspeção visual nova permanece pendente; as capturas reais da Fase 8 continuam sendo a última evidência visual válida.

## 21/10/2026 — Fase 10: matemática TSDF e consistência multivista

O diagnóstico começou com geometria sintética, antes de alterar os modelos neurais. Um plano frontal perfeito revelou uma falha matemática reproduzível: a extração da Fase 9 ligava os quatro cruzamentos de um tetraedro na ordem incidental das arestas e a integração arredondava amostras de raios para voxels. O plano produzia 3.042 componentes, 2.964 arestas não manifold, orientação inconsistente e deslocamento de meio voxel. A correção ordena o polígono no plano da isossuperfície, deduplica interseções que caem exatamente em um vértice, orienta faces adjacentes, representa coordenadas inteiras como centros de voxel e faz integração projetiva: os raios escolhem blocos candidatos, cada centro de voxel é reprojetado na câmera e `TSDF=(z_observado-z_voxel)/truncamento` é integrado. As consultas e os IDs de arestas continuam globais entre blocos.

Os testes sintéticos cobrem plano frontal, plano inclinado, canto de duas paredes, caixa fechada, esfera, superfície atravessando blocos, transformação COLMAP `world → camera`, profundidade no eixo Z e cancelamento/orçamento. Todas as oito cenas/checks TSDF passaram; planos produzem um componente significativo, orientação consistente e zero arestas não manifold dentro das tolerâncias de 0,5–1,5 voxel. Caixa e esfera usam profundidades analíticas renderizadas de câmeras conhecidas, sem a malha oficial do dataset.

Foi adicionado `multiview_depth.py`. Para cada amostra métrica, o worker reconstrói o ponto 3D, projeta nas câmeras vizinhas, separa fora do campo, oclusão, observação consistente e inconsistente, mede erro relativo e produz mapas densos de confiança/estado. Os estados são `não observado`, `incerto`, `inconsistente` e `confiável`; a confiança combina residual de calibração, repetição, consistência e descontinuidade. O mapa é usado como peso no TSDF e como filtro opcional na fusão calibrada. `none` permanece padrão porque nenhuma configuração filtrada superou o baseline completo.

O relatório de calibração agora inclui âncoras, inliers, razão de inliers, mediana/P90/P95 relativa, mediana/P90 absoluta, faixa Z, pixels válidos, parâmetros de escala/deslocamento e motivo de rejeição. No ensaio de 42 vistas, 39 foram aceitas: 29–526 âncoras, mediana 111; mediana dos erros medianos por vista 2,20%, máximo 8,30% e P90 máximo 19,82%. As três vistas `53_*` foram rejeitadas por somente 2, 9 e 13 âncoras. Isso e os resíduos espacialmente localizados indicam que um novo fator global por câmera poderia afastar âncoras SfM já boas; nenhuma otimização conjunta arbitrária foi aplicada.

Comparação real, gerada sem ler a referência e avaliada depois com a mesma amostragem determinística/sem ICP:

| Candidato | Vistas de fusão | Voxel | Componentes totais | Bordas / não manifold | Precisão 10 cm | Completude 10 cm | Decisão |
|---|---:|---:|---:|---:|---:|---:|---|
| Fase 8 ativa | 39 | — | 14 significativos | 12.052 / 2.326 | 50,73% | 55,81% | preservada |
| Fase 9 TSDF 6 cm | 39 | 6 cm | 728 | 49.412 / 14.636 | 52,99% | 47,44% | rejeitada |
| Fase 10, sem filtro | 39 | 6 cm | 40 | 2.426 / 0 | 57,43% | 50,09% | rejeitada: cobertura inferior à Fase 8 |
| Fase 10, moderado | 39 | 6 cm | 41 | 2.748 / 0 | 57,88% | 49,83% | rejeitada |
| Fase 10, rigoroso | 39 | 6 cm | 34 | 2.692 / 0 | 57,64% | 49,72% | rejeitada |
| Fase 10, sem filtro | 39 | 4 cm | 108 | 5.692 / 0 | 50,50% | 52,02% | rejeitada |
| Fase 10, holdout `45_*` | 36 + 3 reservadas | 6 cm | 35 | 2.616 / 0 | 56,72% | 50,56% | rejeitada |

As três vistas `45_*` do último ensaio ficaram fora da estimativa de confiança e da fusão. Elas somaram 40.662 observações consistentes, 1.083 inconsistentes e 597 ocluídas em 41.745 comparações válidas. O P90 dos pares reservados variou conforme a câmera e chegou a 16,84%; os detalhes por par estão em `multiview-consistency-report.json`.

O candidato holdout reproduzido levou 39,913 s, usou 1.529.221.120 bytes de RSS amostrado, pico CUDA PyTorch de 195.219.968 bytes e 4.423.680 bytes estimados para os blocos TSDF. Não houve bloco descartado. A fusão calibrada moderada com holdout foi testada separadamente e também regrediu: precisão 45,36%, completude 54,78%, 22 componentes, 24.019 bordas e 2.628 arestas não manifold. Nenhum candidato recebeu textura, substituiu o GLB ativo ou alterou os 57,51% de cobertura fotográfica da Fase 8. `final_model_eligible` continua falso.

A aplicação e a API local responderam HTTP 200. Playwright não está instalado. O controlador oficial do navegador encontrou apenas a aba interna de falha `data:` e recusou adquirir autoridade por política; nenhuma alternativa CDP ou contorno foi tentada. Logo, não há nova aprovação visual do canvas. O roteiro manual permanece: abrir o imóvel 30 em `/app/house-flipper/imoveis/30/3d`, confirmar a Fase 8, testar enquadramento/órbita/zoom/vistas, selecionar e pintar uma região, desfazer/refazer, recarregar para conferir persistência, iniciar/cancelar um job e verificar que candidatos rejeitados não substituem o GLB.

Estado da Fase 10: matemática TSDF **implementada e testada**; consistência/mapas de confiança **implementados e testados**; experimentos real/holdout **executados e rejeitados pelo gate**; QA visual novo **bloqueado**. Próxima prioridade: correção espacial de baixa frequência dos mapas monoculares usando correspondências densas verificadas ou estéreo multivista, sem alterar as âncoras SfM já consistentes e sem treinar outro modelo antes de demonstrar necessidade.

Validação final da Fase 10: worker Python/FastAPI **48/48** em 22,82 s; Laravel **67/67, 276 assertions** em 4,485 s; frontend **12/12**; TypeScript/Vite aprovado em 10,66 s. O aviso conhecido de chunks acima de 500 kB permanece, sem regressão funcional associada nesta fase.
## 21/10/2026 — Fase 11: estéreo calibrado e correção local conservadora

Estado: **implementado e testado; candidato híbrido rejeitado para promoção**.

O runtime existente foi preservado: Python 3.12.10, OpenCV 4.13.0 no Windows e GTX 1660 Ti de 6 GB. Não houve instalação de Open3D, WSL2, driver, peso neural ou serviço pago. O OpenCV presente fornece `stereoRectify`, `initUndistortRectifyMap`, `StereoSGBM` e `reprojectImageTo3D`; `ximgproc` não está instalado e não foi necessário.

Foi criado `calibrated_stereo.py` com seleção determinística de pares por pontos SfM compartilhados, baseline, ângulo, nitidez e diferença de iluminação; transformação relativa COLMAP; retificação horizontal e vertical; SGBM em CPU; consistência esquerda/direita; máscara de oclusão; erro fotométrico; confiança por textura; profundidade em Z da câmera; retorno ao grid original; validação contra pontos SfM; e correção local regularizada com limite de 22%, preservação de bordas e das âncoras SfM. O dataset usa pares predominantemente verticais: girar temporariamente as imagens retificadas para o matcher horizontal foi necessário e é desfeito antes da reprojeção 3D.

Testes sintéticos cobrem transformação world→camera, plano frontal métrico, baixa textura, diferença de iluminação, pouca sobreposição, baseline insuficiente, oclusão e correção local. A confiança multivista só classifica uma hipótese como confiável depois de duas vistas-alvo consistentes, isto é, três vistas no total. As divisões ficaram isoladas: `44_*` para seleção de parâmetros, `45_*` para avaliação final e as demais para desenvolvimento/fusão. Nenhuma vista reservada participou dos pares, correções ou TSDF.

### Evidência real autorizada

Foram executados 12 pares de `playroom_small`, todos com disparidade válida. A retificação teve erro ortogonal mediano entre 0,0286 e 0,0492 px; a cobertura válida por par ficou entre 2,66% e 7,30%. Foram preservados 155.298 pixels estéreo válidos em 12 vistas e 732 comparações independentes com pontos SfM. A mediana entre as medianas relativas por par foi 1,1387%. Um par (`41_DSC0043_12`/`40_DSC0043_3`) teve P90 de 134,62%; seus outliers foram rejeitados pelo limite local, em vez de promovidos como medida.

Pares: `41_DSC0085_15↔40_DSC0085_6`, `41_DSC0001_9↔40_DSC0001_0`, `42_DSC0001_18↔41_DSC0001_9`, `42_DSC0085_24↔41_DSC0085_15`, `43_DSC0001_27↔42_DSC0001_18`, `41_DSC0043_12↔40_DSC0043_3`, `43_DSC0085_33↔42_DSC0085_24`, `42_DSC0043_21↔41_DSC0043_12`, `43_DSC0043_30↔42_DSC0043_21`, `47_DSC0001_63↔46_DSC0001_54`, `47_DSC0085_69↔46_DSC0085_60` e `49_DSC0085_87↔48_DSC0085_78`.

### Comparação controlada

Mesmos 33 mapas de desenvolvimento, voxel TSDF de 6 cm, truncamento de 18 cm, peso mínimo 1,25, sem filtro e sem fototexturização:

| Estratégia | Vértices / faces | Componentes total / significativos | Bordas / não manifold | Precisão 5/10/20 cm | Completude 5/10/20 cm | Erro mediano precisão / completude |
|---|---:|---:|---:|---:|---:|---:|
| A — monocular original | 39.552 / 76.392 | 52 / 4 | 2.820 / 0 | 30,41% / 56,16% / 82,34% | 35,33% / 50,99% / 60,21% | 8,48 / 9,43 cm |
| B — correção local | 40.071 / 77.472 | 52 / 4 | 2.834 / 0 | 30,65% / 56,46% / 82,70% | 36,76% / 51,23% / 60,15% | 8,40 / 9,14 cm |
| C — híbrida | 39.953 / 77.236 | 49 / 4 | 2.828 / 0 | 30,36% / 56,63% / 82,58% | 36,97% / 51,32% / 60,08% | 8,39 / 9,08 cm |

O candidato C melhorou A em 0,47 ponto percentual de precisão e 0,33 ponto de completude a 10 cm, reduziu três componentes totais e manteve zero aresta não manifold. Ainda perde 4,49 pontos de completude para a Fase 8 ativa (55,81%); por isso não recebeu textura, não substituiu o GLB ativo e `final_model_eligible` permanece falso. A Fase 8 continua como melhor prévia.

Consistência de três ou mais vistas em C: 338.489 amostras consistentes, 18.129 inconsistentes, 17.917 ocluídas, 59.792 amostras de desenvolvimento confiáveis e 2.001 sustentadas por apenas um par, mantidas como incertas. As seis vistas reservadas permaneceram fora da geração e foram avaliadas somente depois.

Recursos A/B/C: 39,832/42,257/42,088 s; RSS amostrado 1,519/1,660/1,653 GB; pico PyTorch CUDA 195.219.968 bytes; memória estimada dos blocos TSDF cerca de 4,43 MB. Artefatos locais, fora do Git: `phase11-A-original-v1`, `phase11-B-corrected-v1` e `phase11-C-hybrid-v1`, com `stereo-report.json`, `stereo-evidence.npz`, hashes, topologia e comparação posterior à referência.

O gate Laravel agora importa um candidato auditável sem retirar `is_primary` do modelo existente quando `final_model_eligible` é falso. O frontend apresenta se o método efetivo é monocular calibrado, correção estéreo local ou fusão híbrida. QA oficial no navegador abriu o GLB ativo em canvas 497×619, selecionou uma `Parede candidata` com confiança estimada de 62% e alternou as vistas lateral e superior. O único log observado foi a depreciação conhecida de `THREE.Clock`; não houve erro de carregamento. Como C foi reprovado antes do gate final, não houve captura nem alegação visual dele.

Verificações desta etapa: worker completo **57/57**; Laravel completo **68/68, 282 assertions**; frontend **12/12**; TypeScript e build Vite aprovados; permanece somente o aviso conhecido de chunks maiores que 500 kB. A comparação real A/B/C e o QA do canvas foram executados.

Próxima prioridade: aumentar a cobertura estéreo confiável com mais pares geometricamente úteis e consistência explícita entre dois pares independentes por vista, sem relaxar o gate de outliers. O limite principal continua sendo cobertura: o SGBM mede apenas 2,66–7,30% por par neste conjunto.


## 21/10/2026 — Fase 12: cobertura estéreo e decisão de arquitetura

**Estado: implementado e testado; nenhum candidato promovido.** A geração permaneceu isolada da malha oficial. O conjunto controlado reutilizou as 33 vistas de desenvolvimento da Fase 11; `44_*` permaneceu reservado para parâmetros e `45_*` para avaliação final histórica.

A máscara de ROI foi corrigida: a validade da ROI direita agora é consultada na coordenada realmente correspondida pela disparidade, inclusive após a rotação temporária de pares verticais. A faixa de disparidade passou a ser estimada por par com percentis das correspondências SfM. O relatório registra um funil de aceitação completo e separa cobertura da imagem inteira, região geometricamente elegível, grid original e pixels realmente entregues à correção/fusão.

| Funil acumulado | 12 pares | 24 pares |
|---|---:|---:|
| Pixels processados | 3.269.120 | 6.540.800 |
| Sobreposição geométrica | 3.173.824 | 6.317.630 |
| Disparidade válida | 2.271.026 | 4.182.207 |
| Consistência esquerda/direita | 1.514.868 | 2.627.279 |
| Confiança final | 427.572 | 653.037 |
| Usados na correção/fusão | 293.874 | 443.051 |
| Cobertura da imagem / elegível | 13,08% / 13,47% | 9,98% / 10,34% |

O maior descarte ocorre no teste de confiança composto depois da consistência esquerda/direita; relaxá-lo elevaria cobertura sem comprovar geometria. A seleção passou a comparar pontuação, diversidade e cobertura incremental. Em 33 vistas foram encontrados 28 pares candidatos. Os 12 pares observaram 14.370 voxels de 10 cm; 24 pares observaram 18.410 (+28,11%). Voxels sustentados por dois pares independentes e três ou mais câmeras passaram de 4.075 para 6.818 (+67,31%). O erro mediano estéreo/SfM permaneceu baixo: 0,848% com 12 pares e 0,951% com 24. O custo estéreo isolado passou de 2,26 s para 3,83 s. Nos últimos seis pares, o ganho caiu de 17.296 para 18.410 voxels; isso registra saturação mensurável.

Foi implementado um protótipo CPU limitado de plane-sweep com agregação mediana de custos, 320×213, 48 planos e quatro câmeras independentes. O teste sintético recuperou um plano conhecido. No `playroom_small`, produziu 4.876 pixels válidos (7,15%) em 0,429 s, porém nas 39 âncoras SfM reservadas teve erro relativo mediano de 16,17%, P90 de 43,41% e apenas 33,33% dentro de 10%. Ele não foi integrado ao TSDF.

| Candidato controlado, 33 vistas | Precisão 10 cm | Completude 10 cm | Componentes total/significativo | Bordas / não manifold | Decisão |
|---|---:|---:|---:|---:|---|
| Fase 11 C, TSDF híbrida 12 | 56,63% | 51,32% | 49 / 4 | 2.828 / 0 | histórico rejeitado |
| D, TSDF híbrida 24 | 57,85% | 52,33% | 48 / 4 | 2.964 / 0 | rejeitado |
| E, fusão calibrada original | 47,11% | 56,41% | 22 / 22 | 20.812 / 3.317 | controle, rejeitado |
| F, fusão calibrada híbrida 24 | 49,29% | 57,10% | 26 / 26 | 25.314 / 1.141 | diagnóstico, rejeitado |

D melhorou a Fase 11 em 1,22 ponto de precisão e 1,01 ponto de completude, mas permaneceu 3,48 pontos abaixo da completude da Fase 8. F recuperou 1,29 ponto de completude sobre a Fase 8, mas perdeu 1,44 ponto de precisão, dobrou bordas abertas e aumentou componentes significativos de 14 para 26. Nenhum resultado recebeu atlas ou substituiu a Fase 8. O backend agora permite abrir um candidato elegível e não promovido por `diagnostic_asset_id`, sempre dentro do mesmo imóvel/tenant, com aviso explícito e retorno ao modelo ativo; o id do candidato é persistido no resultado do job.

Validação: worker Python **61/61**; Laravel **68/68, 292 assertions**; frontend **12/12**; TypeScript e build Vite aprovados. O build manteve apenas o aviso conhecido de chunks grandes. O frontend local respondeu HTTP 200. O controlador oficial do navegador recusou adquirir a aba localhost por sua política de URL; nenhum contorno foi usado e não há aprovação visual nova do canvas. Artefatos versionados: `evidence/phase12-stereo-coverage-summary.json` e `evidence/phase12-stereo-coverage-funnel.png`. Artefatos geométricos grandes permanecem ignorados em `storage/app/ai-real-results`.

**Decisão:** novas rodadas de relaxamento do StereoSGBM não apresentam custo-benefício suficiente para qualidade comercial. A próxima prioridade técnica deve ser MVS/densificação COLMAP em Linux/CUDA autorizado ou uma abordagem híbrida paramétrica editável, usando a geometria estéreo somente onde verificada. A Fase 8 permanece ativa.

## Fase 13 — worker denso preparado e simulador comercial

O baseline foi preservado: Fase 8 ativa com 50,73% de precisão, 55,81% de completude e 57,51% de fototexturização; Fase 12 TSDF com 57,85%/52,33% e zero não manifold; fusão calibrada com 49,29%/57,10% e topologia inferior. Nenhum candidato mudou o asset primário.

O host possui GTX 1660 Ti de 6 GB e COLMAP 4.2.1 `bd1fcf6`, mas o executável informa `without GPU support`; Docker não está instalado. A ajuda real confirmou os comandos e opções usados. Não houve PatchMatch CUDA nem atlas real. A reconstrução densa permanece pendente de worker Linux/CUDA autorizado.

O pipeline agora sonda o binário, executa `mesh_texturer` depois do meshing e rejeita candidato sem atlas verificável. O worker ganhou UUID idempotente, escopo HMAC opaco por tenant/visualização, consulta/cancelamento/download vinculados ao escopo, estado atômico, reconciliação após reinício, concorrência unitária e retenção configurável. Laravel continua controlando autorização, cota, armazenamento privado e promoção.

O produto agora separa A) reconstrução observada, B) ambiente editável aproximado e C) simulação de reforma. Um fluxo de seis etapas cobre imóvel, fotos, modalidade, processamento, editor e apresentação. A cena paramétrica conserva medidas do usuário, PBR, ACES, sombras, catálogo licenciado, transformações, versões e desfazer/refazer.

Comandos estruturados foram ampliados para estilo escandinavo, proposta preliminar, texto de apresentação e estimativa conceitual por área. Notas ficam no grafo com `source=structured_ai_action`; nenhuma operação executa JavaScript. DETR, SAM 2 e Depth Anything V2 não foram retreinados. As lacunas mensuráveis continuam classes arquitetônicas, materiais e correspondência densa; nenhum fine-tuning visual foi iniciado sem dataset autorizado, licença e benchmark.

Validação: Python completo **64/64**; Laravel **70/70, 303 assertions**; frontend **12/12**, TypeScript e build Vite. Não há nova aprovação de canvas: o navegador oficial recusou adquirir a aba local pela política de URL, e nenhum contorno foi usado. Detalhes: `docs/ai/GPU_DENSE_WORKER.md`.

## 21/10/2026 — Master ASTRA: criação editável e validação efetiva do canvas

Marco implementado e testado, prontidão comercial global ainda parcial. Criar ambientes sem fotografias, seis templates com medidas informadas, seleção individual de seis superfícies, acabamentos, histórico persistido de 12 operações, comparação de versões sem escrita e restauração. Câmera responsiva, rolagem de tela cheia, liberação de recursos Three.js e comandos espaciais conservadores corrigidos. Laravel mantém autorização e bloqueio transacional. Estimativas monetárias arbitrárias foram removidas.

Worker: staging de retry preservado, recuperação de resultados terminais, escopo falhando fechado, cancelamento propagado, Poisson limitado por depth e atlas com vínculo UV/material verificado após round-trip GLB. Sem execução densa CUDA nem promoção de malha. Fase 8 preservada; nenhuma métrica geométrica nova.

Testes: Laravel 87/87, 438 assertions; Python 83/83 (warning da fixture UV inválido); frontend 12/12 verificações estáticas; TypeScript/Vite aprovados. QA real no navegador: criação, pintura isolada, seleção por clique, rotação/zoom, vistas frontal/lateral/superior, undo/redo, recarga, comparação de versão e rejeição de parede com teto selecionado. Tela móvel 390×844 inspecionada e corrigida. Evidências, caminhos, comandos e limites: `MASTER_EDITOR_VALIDATION.md`. Inventário amplo: `COMMERCIAL_READINESS_MATRIX.md`.

Próximo marco: máscara manual verdadeira sobre fotografia, sem chamar pontos SAM de pincel raster. Catálogo, aberturas, performance, streaming, cobrança e demais requisitos permanecem parciais; não houve declaração de sistema comercial concluído.

## 21/10/2026 — Master ASTRA: máscara manual real e histórico fotográfico

Segundo marco implementado e testado: modos SAM por pontos e máscara manual separados; pincel/borracha raster, largura, undo/redo, prévia proporcional e bloqueio de seleção vazia. Laravel/FastAPI validam traços limitados e persistem parâmetros por job. Corrigida associação de versões antigas ao último resultado da mesma foto; versões ambíguas recebem aviso em vez de parâmetros inventados. Original permanece preservado.

QA real: foto autorizada MIT Meta Eyeful Tower `40_DSC0001.jpg`, enviada pelo navegador ao imóvel de QA. Dois jobs reais, borracha/desfazer/refazer, resultado e reabertura inspecionados. Avaliação independente: 7.343 pixels selecionados/alterados, zero alteração fora da máscara, original derivado idêntico ao JPEG decodificado. Fluxo manual não chamou modelos neurais. Relatório e hashes: `MASTER_PHOTO_MASK_VALIDATION.md` e `evidence/master-editor/photo/validation.json`.

Testes: Python completo 106/106; Laravel completo 89/89, 457 assertions, seguido de teste focado 8/8, 77 assertions com negativas entre tenants; frontend 21/21 (9 helpers funcionais, 12 verificações estáticas), TypeScript/Vite aprovados. Pincel manual funcional não equivale a seleção arquitetônica automática ou fidelidade física de tinta. Retocar máscara SAM antes da composição, catálogo/PBR/aberturas, performance e demais requisitos continuam pendentes; reconstrução Fase 8 preservada.

## 21/10/2026 — Master ASTRA: correção dos prompts SAM e inferência real

Corrigido o agrupamento dos pontos de inclusão/exclusão como um único objeto no SAM 2; antes cada ponto gerava uma máscara independente posteriormente unida. Saídas com múltiplos objetos agora são rejeitadas. Dois testes reproduziram a falha; suíte Python completa 108/108 em 38,08 s. Script offline compara agrupamentos com os mesmos pontos/foto/refinamento e gera hashes.

Foto MIT real playroom_small 40_DSC0001.jpg: comparação CPU concluída e overlays inspecionados; parede selecionada e melhor preservação qualitativa de tomadas/controles, sem ground truth para medir IoU. Fluxo real Laravel → worker CUDA → histórico → navegador também executado: Simulação 80, SAM uma máscara/6,6318% de cobertura; DETR executou mas não detectou objetos acima de 0,72. Verificação independente: 51.880 pixels alterados, zero fora da máscara. Captura real e relatórios em evidence/master-editor/photo/sam-*. Detalhes, tempos, recursos, avisos e limitações em MASTER_SAM_VALIDATION.md.

Fase 8 preservada, sem novas métricas de reconstrução nem promoção. Refinamento manual da máscara SAM e qualidade comercial continuam parciais; comparação funcional solicitada com Suvinil adicionada à COMMERCIAL_READINESS_MATRIX.md. Não houve novo download de modelos, instalação pesada ou alteração da infraestrutura.

## 21/10/2026 — ASTRA Marco 4: retoque da máscara SAM e prévia confirmável

Implementado e testado: SAM-only gera base privada preservada; pincel/borracha retocam essa base, com zoom/pan, overlay, undo/redo/restauração. Prévia não entra no histórico antes da confirmação; alteração invalida prévia; reabertura recupera foto/base/traços/prompts/dimensões/cor/acabamento. Proteções por foto/tenant e bloqueio concorrente no Laravel. Composição corrigida usa luminância da superfície para evitar clareamento artificial.

QA real em quarto e cozinha licenciados, versões 87 e 97 salvas/reabertas. Três fotos, nove composições: zero pixels externos alterados; avaliação visual qualitativa aponta máscaras parciais e bordas que exigem retoque. DETR avaliado em três limiares sem alterar produção. SAM executou CUDA (cabeçalho inicialmente CPU corrigido), composição/DETR CPU. Python 146/146; Laravel 95/95, 517 assertions; frontend 26/26, TypeScript/Vite aprovados.

Relatório completo, comandos, recursos, licenças e screenshots: [MILESTONE4_PHOTO_REFINEMENT.md](MILESTONE4_PHOTO_REFINEMENT.md). Interromper acompanhamento não cancela inferência nativa; ambientes adicionais e aprovação comercial seguem parciais. Fase 8 preservada. Próxima prioridade: aberturas/PBR/catálogo no paramétrico.

## 21/10/2026 — Marco 4, continuação P1: vãos, materiais e catálogo

Pintura publicada em `cd2b17b`; continuação no mesmo branch. Implementados recortes reais de portas/janelas, seleção/medidas/validação, histórico/versões e persistência autorizada. Seis materiais procedurais com cor, relevo e rugosidade; catálogo com pesquisa, licença, origem e fallback explícito. Reutilizados os dois GLBs existentes, sem novos downloads.

QA real criou janela e porta, rejeitou sobreposição/altura incompatível, editou largura com undo/redo, mudou piso, adicionou/moveu cadeira, salvou versão e reabriu/comparou após recarga. Vistas frontal/lateral/superior e desktop/estreito inspecionadas. Corrigidos três defeitos encontrados: móveis novos fora do piso por contagem global, campo de medida desatualizado após 422 e destaque residual por callback assíncrono com seleção antiga.

Verificação integrada: Laravel **108/108, 712 assertions, 8.058 ms**; frontend **44/44**, TypeScript/Vite (build final 15,35 s) e Pint aprovados. Python **146/146** no incremento anterior desta sessão, sem mudanças Python posteriores. Capturas e limitações em [MILESTONE4_PARAMETRIC_EDITOR.md](MILESTONE4_PARAMETRIC_EDITOR.md). Nada promovido na reconstrução; Fase 8 preservada.

Estado P1 parcial: aberturas apenas em modos internos; catálogo pequeno e geometria de alguns móveis simplificada; inserção limitada ao cômodo sem resolução de colisão; sem benchmark de GPU integrada ou aprovação fotorealista. Próxima prioridade é corpus fotográfico autorizado mais amplo, acabamento das seleções e colocação/catálogo do editor, mantendo cancelamento nativo e infraestrutura como pendências explícitas.


## 22/10/2026 — SOL, central administrativa e observabilidade

Implementação em oito etapas na branch Correção-visua/proteção, preservando a base c2ea84a e os produtos independentes. Checkpoints locais: dfc2ad6 (eventos/autorização), e22b849 (clientes/analytics), 63e9c25 (segurança/ações), 0738fbf (saúde/performance), a2c0735 (IA/RAG), a23757c (jobs/storage/filas), 9a512b5 (billing/e-mails/exportação). A etapa final integra interface própria em /platform, alertas deduplicados, pausa confirmada de novos jobs e regressões. Não há ponte automática entre chats nem alteração da reconstrução Fase 8.

Backend entrega filtros e paginação reais, perfil 360, trials/coortes limitados à cobertura observada, telemetria sem corpos/credenciais, IP HMAC/mascarado, incidentes e auditoria transacional. Leituras administrativas falham fechadas antes de consultar dados; administrador de organização e assinaturas de produtos não concedem acesso à plataforma. Rate limits próprios, confirmação/senha/motivo para ações sensíveis, CSV limitado com proteção contra fórmulas e testes de isolamento. Configuração não comprova modelo/worker ativo. Receita, tokens/custos não medidos e integrações ausentes não recebem números fictícios.

QA efetivo em navegador: visão geral, cliente, trials, conversão, sinal heurístico inerte, API, IA, jobs e auditoria. Encontrados/corrigidos colunas relevantes escondidas, perfil extenso convertido em abas e ator de login perdido pelo clone de LoginRequest. Capturas em evidence/platform-admin. Em 390×844, menu abre/fecha e filtros se reorganizam. Na continuação, lentidão do PHP/host impediu concluir todas as interações; timeout recuperável foi observado e escritas passaram a informar resultado incerto sem retry automático. Não declarar QA integral nem performance aprovada.

Frontend 48/48 na regressão executada (45.431 ms nesta continuação); resultados finais adicionais e comandos em docs/platform-admin/VALIDATION.md. Fechamento: PHP 131/131, 1.047 assertions; frontend 49/49; TypeScript e Vite aprovados (10,03 s), Pint em 28 arquivos. Smoke repetido com 29 checks aprovados. Primeiro smoke HTTP local: 25 endpoints e perfil aprovados, incidente 201/200 e logout 200, sem imprimir credenciais; operador temporário removido. Migration aditiva inicial aplicada localmente; índices adicionais aplicados localmente (18,63 ms). Nenhum dado privado, peso ou dataset destinado ao commit.

Estado: central funcional com validação parcial de operação. Pendências: MFA, ledger comercial/checkout/webhooks nativos, UTM consentido, entrega de e-mails, uptime/APM/heartbeat, quotas e métricas GPU por job, homologação MySQL e benchmark de performance e validação das integrações ausentes. Após o runtime voltar a responder, incidentes, confirmação cancelada, abas/teclado e health foram inspecionados; operador e tokens temporários removidos. Scheduler definido, daemon não presumido ativo. Ver docs/platform-admin/README.md, VALIDATION.md e COMMERCIAL_READINESS_MATRIX.md.


## 22/10/2026 — SOL Fase 2, marco P0: MFA obrigatório

Base 2e375c6, mesma branch. Implementado TOTP com vetores RFC6238, segredo cifrado, confirmação inicial, códigos de recuperação hash/uso único, janela por token de 8h e recent_auth de 10min para ações sensíveis. Replays bloqueados por transação e versão CAS; reset exige senha + fator e não permite central sem novo cadastro. Interface libera a central somente após validação; gestão em /platform/mfa. Sem mudanças no login dos produtos. Fixtures anteriores provisionam token/credencial explicitamente; não há bypass em produção.

Testes MFA 8/8,72 assertions,1.370ms; Platform antes dos dois testes adicionais 34/34,438 assertions; TypeScript/Vite aprovados (10,81s). Docs em platform-admin/MFA.md. Não foram cadastradas credenciais MFA de operadores reais. Marcos seguintes: ledger e operação de filas, com integrações externas condicionadas a configuração/validação real.


## 22/10/2026 — SOL Fase 2, ledger imutável

Fundação implementada/testada: lançamentos verificados com idempotência, ownership de assinatura/tenant/produto, transições append-only e conservação de refunds. Valores agrupados por moeda/produto/plano; MRR/ARR somente com evidência recorrente e período explícito. Sem pagamento real inserido e sem promoção de assinatura por estado financeiro arbitrário. IDs externos binários no MySQL; homologação real continua bloqueada.

Teste focal do ledger: 10/10, 63 assertions. Integração ampla executada com exit 0: 180 testes, 1.498 assertions, 3 contratos MySQL ignorados, 15:10.104, heap PHP 92MiB. A rodada final com ajustes adicionais de e-mail e contratos MySQL está separada; não é atribuída a este resultado. P0 já publicado em 920379f.


## 22/10/2026 — SOL Fase 2, operação integrada

Implementados: heartbeats observados e APM incremental por minuto com cursor protegido pela retenção; diagnóstico opt-in sem SQL/bindings; alertas agrupados/correlação opt-in; incidentes com notas/responsável/prioridade; quotas de admissão IA/reconstrução; inventário dry-run limitado; checklist objetivo. Callback Stripe/Resend nativo verifica assinatura/tempo/idempotência e não aceita payload comercial genérico; recepção não ativa produto. Telemetria de email distingue requested/accepted/delivered e atribui o destinatário autorizado, não o operador. Exportações privadas em fila dedicada, cancelamento/retry controlado, workflow LGPD e proteção do último administrador. UTM somente após consentimento explícito, vínculo limitado ao cadastro, retenção de 90 dias e relatório sem pessoas únicas/causalidade.

Verificação final congelada: PHP 183 testes, 1.506 assertions, 5 skips exclusivos do contrato MySQL, exit 0, 12:30.076, 92 MiB. Nenhum Python alterado. Correlação 3/3, 18 assertions no foco anterior. Migrations 004–009 aditivas aplicadas sem substituir dados. TOTP/ledger já publicados em 920379f/f5d3687. Revisão corrigiu filtros financeiros conflitantes, corrida de callback precoce, empate temporal de entrega, atribuição histórica de plano não comprovada, comparação case-sensitive e métricas de quota/heartbeat que poderiam induzir aprovação indevida.

Diagnóstico real isolado: HTTP401 sem autenticação em 17.977/12.254 ms; bootstrap 10.606,54/7.631,14 ms, SQL observado 1.045,15/106,96 ms. Pico PHP 28 MiB. Evidência sanitizada em evidence/platform-admin/phase2-request-timing.json; router auxiliar encerrado. Bootstrap domina essas amostras, mas a causa subjacente não foi estabelecida. Benchmark com 100 mil eventos/50 mil API/10 mil usuários e limitações em platform-admin/MYSQL_VALIDATION.md; não houve aprovação de performance.

Pendências: MySQL real/CI remota, providers e checkout ao vivo, daemons scheduler/queue, heartbeat de processo AI/3D, backup/restore, quotas adicionais/custos/GPU por job, inventário completo, purge físico de exports e reserva concorrente atômica de reconstruções. Browser local desabilitado no Work: nenhum QA visual novo aprovado. Fase 8 e três produtos preservados, sem credenciais/datasets/pesos/GLBs preparados para commit.

Pint final: 57 arquivos PHP inspecionados; apenas o router auxiliar precisou de estilo. Arquivo corrigido e rechecado com --test aprovado. Nenhuma mudança funcional na aplicação após a rodada PHP final.


## 22/10/2026 — SOL Fase 2, CI e homologação isolada

Marco operacional publicado como 6d7c91b. Preparados três jobs de CI sem deploy (PHP/Pint, MySQL 8 isolado, frontend/TypeScript/Vite), permissões mínimas e sem credenciais de produção. Cinco contratos MySQL incluem JSON/datas/índices/paginação/transações, IDs case-sensitive e quotas idempotentes isoladas. Na execução local final, os cinco foram ignorados deliberadamente; não equivalem a cinco aprovações. O opt-in anterior teve três erros por conexão recusada, antes das migrations; servidor/Docker não instalados. Benchmark exclusivamente SQLite em memória com fixtures sintéticas, sem carga na base local. Resultados e limitações em platform-admin/MYSQL_VALIDATION.md. A execução remota será consultada depois da publicação, se houver acesso disponível.


## 22/10/2026 — SOL Fase 2, interface e bundle

Interface integrada a APM, heartbeats, quotas, inventário, prontidão, administradores, privacidade, exports e origem consentida. Perfil com financeiro, revisão humana de sinais e incidentes com notas/atribuição/eventos. Dashboard mostra checklist vindo do servidor e não interpreta um único dia observado como ausência de dados. Flags críticas exibem responsável/ambiente. Nenhum segredo MFA ou senha é persistido no frontend.

Frontend final: 55/55, saída 0, 52.989,7725 ms. TypeScript retornou 0 na etapa tsc -b do pipeline; Vite com heap experimental de 768 MiB falhou por OOM, depois Vite com limite local de 1536 MiB aprovou, saída 0, 3.412 módulos, 13m48s. Sem alteração global do Node/Windows e sem dependências novas. A duração local não é SLA nem aprovação de performance.

Manifesto auditado: PlatformConsole 47.773 bytes/15.316 gzip; imports estáticos administrativos sem Three/MapCanvas/GLTF, entrada comum sem import imediato de admin. Warning de chunks grandes 3D/mapa continua visível. Não há baseline anterior comparável para afirmar redução. Evidência em evidence/platform-admin/phase2-bundle-audit.json. QA visual bloqueado pelo navegador local desabilitado; nenhum screenshot novo foi inventado.

Publicação parcial: backend e contratos em 6d7c91b/25a3860 publicados; workflow recusado por escopo OAuth workflow ausente, SSH sem chave autorizada e conector403. Nenhuma permissão foi ampliada. O arquivo permanece local; testes MySQL reais/CI remota não foram aprovados.
