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
