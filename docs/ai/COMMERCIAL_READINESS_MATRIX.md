# Matriz de prontidão comercial — diagnóstico incremental

Data: 21/10/2026. Referência inspecionada: `ee279f7ff7db58f035294087b456900fd0adce7b`, branch `Correção-visua/proteção`.

Esta matriz é o inventário inicial do prompt master ASTRA. Registra **leitura de código e testes existentes**, não uma nova aprovação de execução nem um laudo de segurança. Alterações em andamento no mesmo diretório precisam ser relacionadas às evidências de execução em `IMPLEMENTATION_PROGRESS.md` antes de atualizar o estado. Nenhum teste foi executado pela tarefa de inventário. Nenhum dataset, segredo ou peso foi lido ou copiado para este documento.

Prioridades: P0 = impede o principal fluxo comercial ou pode perder resultados; P1 = requisito importante antes de lançamento; P2 = expansão após estabilização. Estados seguem o `AGENTS.md`: implementado e testado; implementado, mas não validado; parcial; bloqueado; não iniciado. A presença de um teste no arquivo **não** equivale a sua aprovação atual.

## Ambiente e componentes a preservar

| Evidência | O que estabelece | O que não estabelece |
|---|---|---|
| `git branch --show-current` retornou `Correção-visua/proteção`; `git status --short` inicialmente vazio | Checkout correto e inicialmente limpo | Não comprova runtime nem publicação das próximas mudanças |
| `composer.json`: PHP `^8.3`, Laravel `^13.7`, Sanctum `^4.0`, Resend `^1.0` | Restrições declaradas do backend | Versão exata do processo PHP em execução |
| `frontend/package.json`: React `^18.3.1`, TypeScript `^5.6.3`, Vite `^6.4.4`, Tailwind `^4.3.3`, Three `^0.186.1`, R3F `^8.18.0`, Drei `^9.122.0` | Dependências declaradas; manter React/Vite existentes | Compatibilidade comprovada pelo build ou qualidade visual |
| `services/ai-worker/requirements.txt`: PyTorch `2.8.0+cu126`, FastAPI `0.156.1`, OpenCV headless `4.13.0.92`, trimesh `4.9.0`, transformers `5.20.2` | Dependências fixadas do worker | CUDA operacional, modelos carregados ou COLMAP denso executado |
| `services/ai-worker/app/vision.py` | DETR ResNet-50, SAM 2.1 Hiera Tiny e Depth Anything V2 Small realmente referenciados na inferência | Classificador neural arquitetônico completo, métricas físicas ou treinamento visual |
| `docs/ai/IMPLEMENTATION_PROGRESS.md`, seção Fase 13 | Execuções históricas reportadas: Python 64, Laravel 70/303 assertions, frontend 12 e build | Aprovação fresca das mudanças atuais ou validação do canvas |

Não duplicar `PropertyVisualizationService`, `SceneGraphFactory`, `SceneCommandInterpreter`, `PropertyPhotoSceneEditor`, `PropertyPhotoEditor`, `GlbViewer`, `AiConversationService`, `AiKnowledgeService` ou `ProductEntitlement`.

## Marco 1 — simulador editável, prioridade máxima

| Requisito / produto | Implementação e arquivos reais | Testes existentes / evidência | Lacuna e critério de aceite | Prioridade | Estado inicial |
|---|---|---|---|---|---|
| Criar ambiente sem fotos / House Flipper | `frontend/src/pages/PropertyViewer.tsx`; `app/Services/Visualization/SceneGraphFactory.php::fromBrief` | Leitura do botão `rebuild`: desabilitado quando `availablePhotos.length === 0` | Oferecer criação explícita de ambiente vazio/template com dimensões do usuário; não depender de visão, fotos ou worker | P0 | Parcial |
| Seis tipos de ambiente e dimensões / House Flipper | `SceneGraphFactory.php` guarda `rooms`, `room_dimensions`; `PropertyPhotoSceneEditor.tsx` edita largura/comprimento/altura | `tests/Unit/Ai/SceneCommandInterpreterTest.php` contém teste de dimensões | Faltam fluxo de criação e prova visual separada de sala, quarto, cozinha, banheiro, escritório e externo; defaults 5,2×4,2×2,65 m são estimados | P0 | Parcial |
| Seleção individual de parede, piso e teto por clique | `PropertyPhotoSceneEditor.tsx::buildInterior`, `highlight`; grafo tem quatro paredes + piso/teto | Leitura: superfícies criadas com `selectable=false`, sem inclusão na lista de raycast; móveis entram nela | Ligar IDs das superfícies ao raycast e destaque; distinguir corte visual de ausência física; duas paredes são omitidas no corte atual | P0 | Parcial |
| Pintura, materiais e acabamentos | `SceneGraphFactory.php`; `PropertyPhotoSceneEditor.tsx::createFloorTexture`, `floorMaterial` | Testes de comandos/materiais em `SceneCommandInterpreterTest.php` | Piso tem textura procedural; paredes usam roughness fixo. Faltam acabamentos persistidos e paletas próprias; comprovar alteração somente na seleção | P1 | Parcial |
| Portas/janelas sustentadas pela cena editável | `PropertyPhotoSceneEditor.tsx::addArchitecturalWindow`; catálogo porta/janela em `frontend/src/lib/furnitureCatalog.ts` | Leitura: janela decorativa adicionada ao interior, sem abertura correspondente na parede | A janela padrão deve ser explícita como template, corrigível; compatibilizar aberturas, dimensões e seleção, sem declarar medição fotográfica | P1 | Parcial |
| Móveis licenciados e fallback | `furnitureCatalog.ts`; `frontend/public/models/furniture/LICENSES.md`; `loadCatalogModels` | Dois GLBs declarados (cadeira e sofá Khronos); sete entradas procedurais originais | Ampliar categorias gradualmente; registrar dimensões, origem, licença, escala, performance; não substituir todo catálogo por primitivas | P1 | Parcial |
| Transformação, ocultar, remover, duplicar, bloquear | `PropertyPhotoSceneEditor.tsx`; `SceneGraphFactory.php` ações `transform`, `hide`, `remove_object`, `add_object` | `SceneCommandInterpreterTest.php` cobre transformação limitada | Duplicação, bloqueio, snap, alinhamento e alertas de sobreposição não foram identificados no editor inspecionado; exigir ações reais e persistidas | P1 | Parcial |
| Desfazer/refazer correto | `PropertyPhotoSceneEditor.tsx::invertActions`, `push`, `undoLast`, `redoLast`; histórico separado da reconstrução no backend | Inversão manual cobre alguns tipos; comandos de reconstrução têm testes | Baseline não inverte `add_object`/`set_room_dimensions`; `paint_kind` restaura só a primeira cor anterior. Provar restauração exata para objetos, dimensões e superfícies heterogêneas | P0 | Parcial |
| Salvamento, versões, reabertura | `routes/api.php`: scene PATCH, versions POST/restore; `PropertyVisualizationService.php`; editor `onSaveVersion` | `tests/Feature/PropertyVisualizationNeuralTest.php`; testes unitários de mutações | Fluxo existe; falta QA integrado de conflito, falha no salvamento, recarga e histórico do editor. Não confundir undo local em memória com versão persistida | P1 | Implementado, mas não validado |
| Câmera, iluminação, sombras, enquadramento | `PropertyPhotoSceneEditor.tsx`; `GlbViewer.tsx` | Código de Three.js e verificações de bounding box; evidência visual histórica em `3D_VISUAL_QUALITY.md` | QA atual de canvas e múltiplos ambientes; vistas ocultas em corte; perfis de performance mensurados em GPU integrada/mobile | P1 | Parcial |
| Antes/depois e exportação para apresentação | `PropertyViewer.tsx` alterna modos; `PropertyPhotoEditor.tsx` exibe imagens lado a lado; versões existentes | Nenhuma suíte de navegador executada nesta auditoria | Alternar modos não é comparação de duas versões 3D com mesma câmera. Exportação completa e apresentação precisam de fluxo verificável | P1 | Parcial |
| Modo A foto / B ambiente editável / C reconstrução | `PropertyViewer.tsx` já tem três modos internos | `frontend/tests/app-shell.test.mjs` verifica textos/estrutura por regex | Harmonizar nomes com prompt atual; preservar metodologia real usada e dimensões estimadas. Teste estático não prova interação | P0 | Parcial |

## Marcos 2–3 — fotografia e reconstrução densa

| Requisito / produto | Implementação e arquivos reais | Testes existentes / evidência | Lacuna e critério de aceite | Prioridade | Estado inicial |
|---|---|---|---|---|---|
| Pintura fotográfica sem alterar original | `PropertyPhotoEditor.tsx`; `vision.py` composição com luminância; `PropertyVisualizationService.php::simulatePhoto` | `PropertyVisualizationNeuralTest.php::test_authorized_user_can_create_a_private_masked_photo_simulation`; `services/ai-worker/tests/test_api.py` | UI envia pontos positivos/negativos, salva resultado separado e oferece histórico; requer nova inspeção de fotos reais autorizadas | P1 | Parcial |
| Refinar máscara e excluir decoração | `vision.py::refine_surface_mask`, exclusões DETR; `PropertyPhotoEditor.tsx` | Código usa componentes conectados/morfologia e pontos SAM; evidências históricas `phase6-photo-paint-*` | “Pincel”/“borracha” atuais são pontos, não edição direta raster; limite de 16 pontos. Não garante proteção de janela/cortina/quadro nem ausência de vazamento | P1 | Parcial |
| Reconstrução observada e gate | `reconstruction.py`, `calibrated_depth_fusion.py`, `multiview_depth.py`, `sparse_tsdf.py`, `calibrated_stereo.py`; `PropertyVisualizationService.php` | Testes Python respectivos; teste Laravel de candidato inferior não substituir primário | Fase 8 permanece melhor prévia histórica, não aprovação comercial. Comparar nova malha somente após geração independente | P0 | Parcial |
| Atlas UV verdadeiro | `services/ai-worker/app/photo_texturing.py`; `reconstruction.py::run_dense_reconstruction` | `test_photo_texturing.py`; testes simulados de reconstrução | Baseline denso verifica existência de arquivo de imagem e OBJ/PLY, mas isso não comprova UV/imagem efetivamente vinculados ao GLB. Validar atlas/material após exportação | P0 | Parcial |
| COLMAP denso Linux/CUDA | `reconstruction.py`; `services/ai-worker/compose.gpu.yml`; `GPU_DENSE_WORKER.md` | Sonda/testes existentes; último relatório local: COLMAP 4.2.1 sem GPU, Docker indisponível | Execução real PatchMatch/Fusion/meshing/UV em infraestrutura autorizada ainda não ocorreu. Imagem de implantação, limites reais e benchmark pendentes | P0 | Bloqueado |
| Jobs UUID, progresso, cancelamento e recuperação | `async_reconstruction.py`; `main.py`; `AiWorkerClient.php`; `PropertyVisualizationService.php` | `test_async_reconstruction.py`, `test_api.py`, teste Laravel de reconstrução | Revisar terminal vs progresso no reinício, idempotência por payload e subprocesso cancelável. Fila executora em memória não é fila distribuída durável | P0 | Parcial |
| QA visual e métricas independentes | `REAL_PROPERTY_RECONSTRUCTION_VALIDATION.md`; `RECONSTRUCTION_VALIDATION.md`; `compare_reference_geometry.py` e scripts do worker | Suítes topológicas/sintéticas presentes; documentação contém métricas históricas | Não houve nova inspeção por esta auditoria. Nova aprovação exige canvas real, comparação de vistas e registro de recursos; HTTP 200 não basta | P0 | Bloqueado |

## Marco 4 — IA, pesquisa e aprendizagem

| Requisito / produto | Implementação e arquivos reais | Testes existentes / evidência | Lacuna e critério de aceite | Prioridade | Estado inicial |
|---|---|---|---|---|---|
| Comandos do editor alteram cena autorizada | `SceneCommandInterpreter.php`, `SceneGraphFactory.php`, `PropertyVisualizationController.php` | `SceneCommandInterpreterTest.php`; `PropertyVisualizationNeuralTest.php` | Revalidar seleção/ambiguidade e contexto espacial; não presumir parede por posição fixa; não emitir custos sem premissas. Manter operações estruturadas | P0 | Parcial |
| Assistente geral e por produto | `AiConversationService.php`, `AiAgentCatalog.php`, `AiResponseSynthesizer.php`, `AiOperationalTools.php`, `OllamaClient.php` | `AiGeneralAssistantTest.php`, `AiPlatformTest.php`, `OllamaClientTest.php` | Generalista e ferramentas existem; não reescrever. Medir qualidade/latência em runtime local e pedidos representativos | P1 | Implementado, mas não validado |
| Streaming real e cancelamento LLM | `OllamaClient.php::chat` envia `stream => false`; rota POST de conversas retorna resultado | Teste de cliente existe, sem evidência SSE | Implementar streaming e cancelamento backend, sem simular tokens com texto já completo; persistir resposta incompleta/cancelada honestamente | P1 | Não iniciado |
| Histórico, feedback, contexto e memória opt-in | `AiConversationService.php`; `AiConversationController.php`; models/preferences | `AiPlatformTest.php::test_conversations_feedback_and_memory_are_isolated_by_user` | Há histórico/sessões/preferências; fluxo completo de arquivar/reabrir e cancelamento ainda precisa validação UX | P1 | Parcial |
| RAG híbrido e autorização | `AiKnowledgeService.php`, `QdrantVectorStore.php`, `OllamaClient.php::embed` | `AiPlatformTest.php` rejeita recuperação entre tenants e pós-filtra vetores; `QdrantVectorStoreTest.php` | Arquitetura lexical/vetorial existe; corpus pequeno histórico não prova qualidade geral; ampliar benchmark autorizado sem vazamento | P1 | Implementado, mas não validado |
| Pesquisa externa rastreável | `ApifyPropertySearchService.php`, `InventoryListingSearch.php`, `PropertyQueryInterpreter.php`, `PropertyMatchRanker.php` | `ApifyPropertySearchServiceTest.php`; `AiPlatformTest.php` | Não houve chamada de portais nesta auditoria. Disponibilidade/licença/custos de cada actor precisam de comprovação; indisponível deve continuar explícito | P1 | Parcial |
| Avaliações IA representativas | `app/Console/Commands/EvaluateAiCommand.php`; `docs/ai/evaluation-results.json`; `rag-benchmark.json` | Avaliador determinístico e histórico existentes | Expandir além da suíte histórica com conversação, recuperação, falhas, ambiguidade espacial e revisão humana separada | P1 | Parcial |
| Treinamento visual e LoRA | `services/ai-worker/training/vision/prepare_dataset.py`; `VISION_TRAINING_PLAN.md`; requisitos PEFT | `test_vision_dataset.py` valida licença/divisão por cena | Treinamento visual não foi executado; LoRA textual não treina visão nem prova melhoria do Qwen principal. Manter sem treino automático | P2 | Parcial |

## Marco 5 — produtos, autenticação e assinaturas

| Requisito / produto | Implementação e arquivos reais | Testes existentes / evidência | Lacuna e critério de aceite | Prioridade | Estado inicial |
|---|---|---|---|---|---|
| Independência dos três produtos | `ProductEntitlement.php`, `EnsureProductAccess.php`, `routes/api.php`, `frontend/src/lib/products.ts`, dashboards próprios | `tests/Unit/ProductEntitlementTest.php::test_products_remain_independent` | Não redesenhar como três papéis. Executar jornadas completas e combinar assinaturas sem vazamento | P0 | Implementado, mas não validado |
| Corretor: captação → autorização → carteira → publicação | `CommercialListingService.php`, controllers Capture/CommercialListing, `Carteira.tsx` e ficha | Backend exige autorização vigente e foto licenciada para publicação | Percurso de criação/revogação/publicação, contato e lead ainda precisa QA integrado; manter fiscalização backend | P1 | Parcial |
| Imobiliária: CRM, equipes, filiais e contratos | `Workspace/AgencyCrmService.php`, `OrganizationService.php`, `LeadRouletteService.php`, `AgencyWorkspaceController.php`, páginas `imobiliaria/` | `AgencyCrmSecurityTest.php`, `AgencyContractTest.php` | Funcionalidades existem; ampliar fluxo ponta a ponta e permissões de gerente/corretor/filial, sem confundir gerente com Super Admin | P1 | Parcial |
| House Flipper: cálculo financeiro, custos, projetos | `InvestmentAnalysisCalculatorService.php`, `AnalysisProjectService.php`, controllers de investimento, `WizardFinanceiro.tsx`, `AnaliseImovel.tsx` | Código determinístico localizado; não foi executado benchmark financeiro nesta leitura | Validar premissas/taxas/cenários com fixtures e integração; IA explica, não calcula valores inventados | P1 | Parcial |
| Site profissional de corretor e agência | `BrokerSiteService.php`, `SiteCorretor.tsx`, `PublicBroker.tsx`, `PublicAgency.tsx`, `imobiliaria/Site.tsx` | `BrokerThemeSanitizerTest.php`; autorização de catálogo no serviço | Templates/personalização já existem. Não recomeçar; evolução comercial ocorre depois do simulador | P2 | Parcial |
| Analytics real de conversão | Não localizados eventos de pageview/UTM/WhatsApp na busca em `app`, `database`, `frontend/src` | Busca de texto, não prova de ausência absoluta em código externo | Implementar métricas reais com exclusão de visitas administrativas/consentimento/retensão; não chamar clique de conversa | P2 | Não iniciado |
| Cadastro, confirmação, sessões | `AuthService.php`, `EnsureVerifiedAccount.php`, `SessionSecurityTest.php`, dependência Resend | Testes de revogação própria/entre usuários e conta não verificada | Sem envio real de e-mail nesta auditoria; verificar serviço configurado sem expor credenciais. `frontend/src/lib/authToken.ts` armazena bearer em localStorage: avaliar migração de sessão no Marco 6 | P0 | Parcial |
| Teste e assinatura separados por produto | `Marketplace/BillingService.php`, `TenantProductSubscription`, `BillingController.php`, configuração produtos | `ProductEntitlementTest.php`; teste local de 7 dias e cancelamento estão implementados | Contratação de um produto não pode liberar outro; matriz de suspensão/renovação/up/downgrade pendente | P0 | Parcial |
| Cobrança efetiva e webhooks do provedor | `BillingController.php::checkout` responde HTTP 503 `not_wired`; `BillingService.php::handleWebhook` tem contrato HMAC próprio | Leitura direta, sem transação externa | Não operacional. Requer integração/sandbox e assinatura real de cada provedor, idempotência e reprocessamento; cobrança real depende de autorização | P0 | Bloqueado |
| Administração central | `EnsurePlatformAdmin.php`, `PlatformAdminController.php`, páginas `platform/` | Rotas protegidas separadas | Apenas overview/leads localizados no recorte; painel operacional completo de jobs/custos/assinaturas exige inventário e implementação posteriores | P2 | Parcial |

## Marcos 6–8 — operação, segurança e aceite final

| Requisito | Implementação e arquivos reais | Evidência / teste existente | Lacuna e critério de aceite | Prioridade | Estado inicial |
|---|---|---|---|---|---|
| Isolamento tenant e ferramentas | Middleware produto, serviços autorizados, allowlist/budget IA | `AiPlatformTest.php`, `AgencyCrmSecurityTest.php`, `PropertyVisualizationNeuralTest.php` | Executar regressões dos caminhos alterados agora; auditoria defensiva abrangente permanece Marco 6, sem atestado geral neste inventário | P0 | Parcial |
| Limites operacionais e recursos | `config/visualizations.php`; `async_reconstruction.py`; `compose.gpu.yml`; `.env.example` | Limites por imagem/upload, execução concorrente e quota parcial | Limites globais/tenant, RAM/VRAM/tempo/armazenamento e recuperação precisam validação com concorrência/reinício real | P0 | Parcial |
| CI, backup/restore, rollout/rollback, supervisão | `composer.json` possui comandos dev/queue; compose GPU preparado | Nenhuma pasta `.github` na raiz inspecionada; não houve exercício de restore | Preparação de produção não é validada por execução local. Definir CI e runbooks, testar backup/restore; deploy depende de autorização | P1 | Não iniciado |
| Qualidade frontend e regressões | `frontend/tests/app-shell.test.mjs` usa Node/assert e regex sobre arquivos; build `tsc -b && vite build` | Testes presentes de shell e conteúdo | Não cobre clicks, canvas, persistência, responsividade ou performance. Acrescentar testes comportamentais do editor e QA real autorizado | P0 | Parcial |
| Aceite visual comercial | Evidências históricas em `docs/ai/evidence`; telas Three.js existentes | Documentação relata bloqueios de ferramenta em fases recentes | Nenhuma aprovação nova nesta auditoria. Inspecionar canvas e fotografias em várias vistas; registrar screenshots reais, mobile e desktop | P0 | Bloqueado |

## Três defeitos de maior impacto no fluxo prioritário

1. **Não se cria uma sala editável sem fotos pela tela atual.** `PropertyViewer.tsx` condiciona a criação a fotografias, embora o principal objetivo seja funcionar sem reconstrução/IA. O grafo existente deve receber uma entrada explícita de template/dimensões, sem novo subsistema paralelo.
2. **O raycast não seleciona arquitetura.** `buildInterior` registra móveis como selecionáveis, mas não paredes/piso/teto; duas paredes são omitidas no corte. A correção deve usar IDs persistidos das superfícies e tornar claro o corte da vista.
3. **O desfazer paramétrico não restaura o estado completo.** `invertActions` omite adição e dimensões, e restaura `paint_kind` a partir de uma única superfície. Aceite exige estado anterior exato, incluindo cores distintas, objetos adicionados e dimensões.

Riscos operacionais adjacentes encaminhados para revisão específica: recuperação de jobs concluídos no reinício e validação real de textura/UV no GLB denso. Não são resolvidos por este documento e não se promove candidato novo por existir um arquivo de imagem.

## Atualização incremental

A implementação da etapa deve manter esta matriz como inventário, atualizar o estado **somente** com o comando/resultado correspondente e registrar no progresso o commit, evidência visual, pendência externa e próximo passo. Correções dos agentes em andamento não contam como aprovadas até integração/testes. A Fase 8 permanece prévia ativa histórica; nenhum número geométrico novo foi produzido nesta auditoria.

## Atualização executada — marco do editor (21/10/2026)

As tabelas acima preservam o diagnóstico do commit-base; não representam o estado posterior às correções. Evidência nova em `MASTER_EDITOR_VALIDATION.md`:

| Item corrigido | Estado após execução | Limite restante |
|---|---|---|
| Criação sem fotos; seis templates e medidas | Implementado e testado na API; sala inspecionada no canvas | Outros cinco templates ainda sem QA visual individual |
| Seleção por raycast, pintura individual, acabamentos | Implementado e testado; seleção/pintura verificadas no navegador | Cenas legadas sem seis IDs exigem migração; catálogo PBR limitado |
| Histórico persistido, undo/redo, versões e comparação | Implementado e testado; recarga e comparação sem escrita observadas | 12 snapshots; criação de ambiente inicia nova sequência e preserva versão anterior |
| Câmera e responsividade | Vistas e mouse testados; celular 390×844 corrigido | Sem benchmark de GPU integrada; renderer ainda recriado por mudança |
| Modos A foto / B editável / C multivista | Implementado e testado | Não implica conclusão das modalidades |
| Worker, cancelamento e atlas | Regressões reproduzidas e corrigidas; 83 testes Python aprovados | CUDA real, orçamento total e fila durável pendentes |
| Custos/ambiguidade em comandos | Removida estimativa monetária arbitrária e escolha implícita de parede | Interpretador restrito; não é agente multimodal geral |

Os demais estados iniciais continuam válidos até evidência específica nova. Frontend estático não substitui QA. Referência Suvinil inspecionada somente via controles HTML públicos; sem cópia de assets e sem paridade comercial declarada.

## Atualização executada — máscara manual e histórico fotográfico

`MASTER_PHOTO_MASK_VALIDATION.md` registra implementação e execução real de pincel/borracha raster, correção da proporção/coordenadas, undo/redo de traços, validação de limites e vínculo independente entre simulação e seus parâmetros. Estado desses itens: **implementado e testado**, incluindo foto real autorizada, composição no worker e recarga no navegador. Pixels fora da máscara ficaram idênticos. O modo SAM por pontos foi preservado; seu resultado automático ainda não pode ser retocado diretamente como máscara raster. Segmentação arquitetônica avaliada, simulação física/PBR sobre a foto, catálogo avançado e qualidade comercial geral permanecem parciais.

## Comparação funcional solicitada — referência Suvinil

Referências públicas consultadas: [criar ambiente](https://www.simulador3d.com.br/suvinil2.3/?cena=criarAmbienteNovo&id_cliente=) e [cena](https://www.simulador3d.com.br/suvinil2.3/?cena=cena03&id_cliente=). A coluna Suvinil distingue controles encontrados no HTML de funções não verificadas no runtime; não é aprovação de paridade visual. Nenhum asset ou código foi copiado.

| Capacidade | Referência Suvinil observada | House Flipper atual | Estado e evidência |
|---|---|---|---|
| Ambientes prontos | Categorias de cômodo no HTML | Seis templates paramétricos | Parcial; sala inspecionada, demais sem QA individual |
| Criar por dimensões | Campos de largura/comprimento/altura | Criação sem foto e medidas informadas | Implementado e testado; API e canvas |
| Pintar paredes | Controles de cor/código | Parede independente por ID, clique e seletor | Implementado e testado; pintura/undo/recarga |
| Pisos e teto | Comportamento não verificado | Seis superfícies e ações específicas | Parcial; seleção do teto inspecionada, materiais completos pendentes |
| Materiais/acabamentos | Controles fosco/acetinado/brilhante | Acabamento persistido e materiais procedurais | Parcial; catálogo físico/PBR reduzido |
| Móveis editáveis | Mover/girar/escalar no HTML | Transformação, ocultar, duplicar/remover; dois GLBs licenciados | Parcial; faltam catálogo amplo, snap/bloqueio/colisão |
| Iluminação | Runtime não verificado | Ambiente/direcional, sombras, comandos estruturados | Parcial; sem validação física ou benchmark amplo |
| Navegação 3D | Runtime não verificado | Órbita, zoom e vistas frontal/lateral/superior | Implementado e testado no editor; primeira pessoa pendente |
| Salvar cena | Controle de salvar/login no HTML | Persistência, versões e comparação somente leitura | Implementado e testado com recarga |
| Editar fotografia | Entrada fotográfica no HTML | SAM por pontos e máscara manual verdadeira | Manual implementado/testado em foto real; seleção neural ainda em avaliação |
| Histórico/undo/redo | Não verificado | Cena persistida e histórico de traços; resultados por job | Implementado e testado; histórico da cena limitado a 12 passos |
| Assistente IA | Não verificado | Operações estruturadas e autorização Laravel | Parcial; não é agente geral com streaming completo |
| Reconstrução multivista | Não verificado | Fusão parcial ativa e pipeline denso preparado | Parcial/bloqueado para execução densa local; Fase 8 preservada |
