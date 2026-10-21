# Validação com ambiente real multivista autorizado

Data: 20/10/2026

## Resultado executivo

Foi executada uma validação com a cena real `playroom_small` do dataset Meta Eyeful Tower. As fotografias pertencem ao mesmo ambiente, possuem calibração e vistas múltiplas, e foram disponibilizadas sob licença MIT. A licença permite uso comercial desde que os avisos de copyright e licença sejam preservados.

O teste comprovou a execução local de DETR, SAM 2 e Depth Anything V2 sobre fotografia real e comprovou que a malha de referência texturizada do dataset pode ser convertida para GLB, enviada, salva, reaberta e navegada no Three.js. Ele **não comprova uma reconstrução densa local completa**, pois o COLMAP instalado no Windows não possui suporte a GPU e o host não possui WSL2/Linux configurado. Nenhuma GPU externa paga foi contratada.

O ambiente é um espaço interno real de demonstração/playroom. Ele não é apresentado como residência nem como imóvel do proprietário do projeto. Para validar qualidade comercial em um imóvel residencial específico, ainda é necessária uma captura autorizada desse imóvel e uma execução densa em Linux/CUDA.

## Fotografias e autorização

| Item | Resultado |
|---|---|
| Origem | Meta Eyeful Tower, cena `playroom_small` |
| Conteúdo | ambiente real, mesmas instalações, múltiplas câmeras e posições |
| Imagens originais | 126 JPEG em 1K |
| Imagens COLMAP sem distorção | 126 |
| Calibração | `cameras.json`, `cameras.xml` e exportação COLMAP oficial |
| Licença | MIT; cópia preservada ao lado do GLB convertido |
| Manifesto local | `storage/app/ai-real-datasets/eyeful-tower/playroom_small/dataset-manifest.json`, com SHA-256 |
| Uso de portais | nenhum |

O manifesto inicial registra 148 arquivos e 137.839.088 bytes, baixados em 20,768 s. Imagens COLMAP e arquivos esparsos oficiais foram obtidos depois e mantidos na área local de dados, fora do versionamento.

## Análise neural executada

Fotografia analisada: `colmap/images_8/40_DSC0001_0.jpg`, 688 × 458.

| Modelo | Resultado | Tempo | Pico de VRAM |
|---|---|---:|---:|
| DETR ResNet-50 | `couch`, confiança 0,80736 | 173,22 ms | 578,72 MB |
| SAM 2.1 Hiera Tiny | uma região guiada; cobertura 5,9698% | 840,30 ms | 282,72 MB |
| Depth Anything V2 Small | profundidade relativa 0,107422–7,578125, média 2,720505 | 1.070,51 ms | 186,18 MB |

Tempo total observado da requisição: 5,587 s em CUDA. A GPU disponível é uma NVIDIA GeForce GTX 1660 Ti com 6.144 MiB. DETR reconheceu o conjunto de assentos como `couch`; suas classes COCO não classificam cômodos, materiais, portas ou janelas. SAM 2 segmentou apenas a região induzida pelo ponto. A profundidade é monocular e relativa, sem escala métrica.

## Geometria e processamento

1. As fotos reais, calibrações e exportação esparsa oficiais foram inventariadas.
2. A análise neural foi executada localmente e os artefatos foram preservados.
3. O pré-requisito para densificação foi auditado: COLMAP 4.2.1 local sem GPU; WSL2 ausente.
4. A malha de referência oficial foi aberta e validada: 437.239 vértices, 843.112 faces, uma geometria, dimensões aproximadas de 5,990 × 2,461 × 6,794 m.
5. A malha OBJ oficial foi convertida para GLB. A textura foi reduzida para 4096 × 4096 e incorporada ao GLB final de 42.307.864 bytes.
6. O GLB foi importado no cadastro local, passou pelo portão de elegibilidade, foi salvo e reaberto após recarregar a página.
7. Zoom e rotação manual foram validados no Three.js. sRGB, ACES Filmic e iluminação hemisférica/direcional foram aplicados.

Tempos de geometria: 506,132 s para abrir e inspecionar o OBJ, 2,856 s para exportar a primeira versão e 3,677 s para preparar a textura web. A primeira transferência do GLB de 29 MB pelo servidor PHP de desenvolvimento levou aproximadamente 167 s. O tempo da transferência final de 42 MB não foi medido com cronômetro isolado.

## Resultado visual

O ambiente é reconhecível no render: piso amarelo, assentos marrons, pilhas escuras, paredes e abertura aparecem em posições coerentes. O enquadramento inicial foi corrigido, e a órbita manual revela novos lados da cena. A textura permanece visível após salvar e reabrir.

A qualidade ainda não é comercial: a malha possui contorno fragmentado, vazios, regiões pretas e superfícies incompletas nas áreas sem cobertura. A malha não é estanque, embora tenha orientação de faces consistente. Ela é um único objeto, por isso o GLB importado não oferece seleção semântica individual de parede, piso e móveis. O editor paramétrico existente continua oferecendo seleção e edição de materiais, mas essa capacidade não foi transferida automaticamente para a malha monolítica importada.

## Artefatos

- `evidence/real-property-playroom-small-contact-sheet.jpg`: vistas reais originais.
- `evidence/real-property-playroom-small-undistorted-contact-sheet.jpg`: vistas usadas pelo COLMAP oficial.
- `evidence/real-property-playroom-small-vision/`: original, profundidade, máscara, sobreposição e JSON completo.
- `evidence/real-property-playroom-small-model/geometry-report.json`: topologia, dimensões, textura e tempos.
- `evidence/real-property-playroom-small-model/eyeful-playroom-small-textured-4k.glb`: malha de referência convertida.
- `evidence/real-property-playroom-small-threejs.png`: enquadramento inicial.
- `evidence/real-property-playroom-small-threejs-orbit.png`: órbita manual.
- `evidence/real-property-playroom-small-threejs-reopened.png`: persistência após recarga.
- `evidence/real-property-playroom-small-validation-panel.jpg`: fotografia, análise, profundidade e render lado a lado.

## Bloqueios e próximos critérios

A reconstrução de um imóvel real pelo pipeline do produto permanece **não concluída**. Para concluir sem extrapolar evidências, são necessários:

1. instalar e validar WSL2/Linux com COLMAP CUDA no hardware existente, ou obter autorização explícita para um worker remoto;
2. executar estimativa, densificação, fusão, malha e texturização a partir das fotografias, sem reutilizar a malha de referência;
3. repetir o teste com fotografias autorizadas de um imóvel residencial do proprietário do projeto;
4. separar ou rotular a malha em elementos editáveis antes de prometer edição individual de paredes, piso, aberturas e móveis;
5. comparar câmeras e renders contra várias fotografias, não apenas uma vista.

Esta etapa comprova integração neural, compatibilidade geométrica e visualização real. Ela não comprova fidelidade métrica, preenchimento de áreas ocultas ou reconstrução densa própria.

## Execução independente de 21/10/2026

Uma nova execução utilizou somente as 126 fotografias sem distorção, a calibração COLMAP e os pontos SfM do `playroom_small`. A malha OBJ oficial permaneceu fora do processo gerador e só foi aberta pelo script de comparação depois de o GLB novo estar finalizado.

| Item | Resultado |
|---|---|
| Método | fusão de profundidade monocular calibrada e superfície de profundidade |
| Vistas | 18 selecionadas; 17 aceitas |
| Profundidade | Depth Anything V2 Small, alinhada por vista com pontos SfM métricos |
| Nuvem | 104.633 pontos brutos; 87.862 após fusão por voxel |
| Malha | 6.208 vértices; 12.096 faces; um componente; zero degeneradas |
| Extensão | diagonal 4,622544443 m |
| GLB | 245.580 bytes; SHA-256 `5aa3fdfcf14b73aec35813a9c3667c38272dcdd85a06e6c47f87619cd3f60af8` |
| Tempo | 165,348 s |
| Referência durante geração | não lida |
| Three.js | upload pelo editor local concluído; modelo persistido no imóvel de QA |

O Poisson do COLMAP Windows ficou sem progresso útil sob pressão de memória: profundidade 13 chegou a aproximadamente 850 MB e não escreveu saída em mais de 18 minutos; profundidades menores também excederam o limite do teste. O código passou a limitar profundidade, threads e tempo. Ao expirar, exporta o maior componente de uma superfície triangulada diretamente do mapa de profundidade calibrado e preserva separadamente a nuvem fundida multivista.

A comparação isolada, sem ICP, preservou as coordenadas COLMAP originais. Entre os 6.208 vértices gerados e uma amostra da referência, o erro mediano foi 0,108643 m e 46,005% ficaram até 0,10 m. Na direção inversa, a completude até 0,10 m foi 12,68%, com mediana 1,16585 m. Esses números comprovam alinhamento local útil e também demonstram que grande parte do ambiente permanece ausente.

Artefatos principais:

- `storage/app/ai-real-results/playroom-small-independent/depth-fusion-18b/depth-fused.ply`;
- `storage/app/ai-real-results/playroom-small-independent/depth-fusion-18b/depth-poisson-clean.ply`;
- `storage/app/ai-real-results/playroom-small-independent/depth-fusion-18b/depth-fusion.glb`;
- `storage/app/ai-real-results/playroom-small-independent/depth-fusion-18b/result.json`;
- `storage/app/ai-real-results/playroom-small-independent/depth-fusion-18b/reference-comparison.json`.

Conclusão atualizada: existe agora geometria própria gerada das fotografias e enviada pelo fluxo real do SaaS. Ela é uma reconstrução técnica parcial baseada em profundidade calibrada, não uma reconstrução estéreo densa texturizada. A aprovação comercial continua bloqueada pela baixa completude, ausência de UV/textura própria e falta de validação visual multivista de uma malha densa.

### Validação visual da geometria independente

O GLB independente foi salvo no imóvel local de QA, reaberto após recarregar a rota autenticada e renderizado no visualizador do produto. A órbita manual respondeu ao arraste. A primeira abertura revelou dois problemas do viewer para superfícies abertas: a câmera usava uma direção diagonal fixa e materiais com cores por vértice podiam aparecer quase pretos. O viewer agora enquadra a câmera pelo eixo mais fino da caixa envolvente, renderiza superfícies abertas nos dois lados e usa material não metálico com as cores dos vértices.

A captura `evidence/real-property-independent-depth-fusion-threejs.png` registra o resultado próprio, e não a malha oficial. São visíveis uma superfície de parede, uma abertura e variações de profundidade/cor; ao orbitar, a espessura parcial também fica evidente. A cena ainda não é um cômodo completo nem uma reconstrução comercial: faltam grande parte do ambiente, textura UV e superfícies de outras vistas.

Testes finais desta execução: **4/4** testes focados do worker, **5/5** testes Laravel de visualização neural (36 assertions), **12/12** testes frontend, TypeScript e build Vite aprovados. O worker permaneceu saudável com PyTorch 2.8.0+cu126, CUDA 12.6 e GTX 1660 Ti.

## Fusão multivista de 21/10/2026

O fallback anterior foi corrigido para preservar faces de todas as vistas após a fusão por voxel. A malha oficial continuou ausente do processo de geração; ela foi aberta somente pelo comparador independente depois que o GLB e seu hash estavam gravados.

| Item | Resultado |
|---|---|
| Vistas | 42 selecionadas; 39 aceitas |
| Pontos | 141.376 brutos; 137.420 fundidos |
| Faces | 269.216 brutas; 267.722 após fusão e limpeza |
| Limpeza da fusão | 4 faces degeneradas e 305 duplicadas removidas |
| Malha | 139.033 vértices; 30 componentes significativos; diagonal 6,068805 m |
| Método | `calibrated_multiview_depth_surface` |
| Tempo | 257,447 s |
| Poisson | não tentado no Windows; fallback explícito e verificável |
| GLB | 5.438.200 bytes; SHA-256 `040fef6ef25c6bf8768e298c21b47bbe6c48abbafbc9fc355f342e3f74377056` |

Comparação determinística de até 10.000 vértices, sem ICP:

- baseline: precisão mediana 0,121785 m, 40,29% até 10 cm; completude mediana 1,166620 m, 12,71% até 10 cm;
- fusão multivista: precisão mediana 0,095919 m, 51,67% até 10 cm; completude mediana 0,081763 m, 55,41% até 10 cm;
- melhoria: 42,70 pontos percentuais de completude até 10 cm, equivalente a 4,36 vezes o baseline.

O arquivo foi enviado e reaberto na rota autenticada do imóvel de QA. As capturas em `evidence/multiview-fusion/` mostram o baseline, o novo enquadramento e uma segunda posição de câmera. A navegação funciona, mas o render ainda contém superfícies abertas e componentes visualmente separados. A maior cobertura não resolve texturização, watertightness nem continuidade de paredes; por isso o modelo permanece uma prévia técnica e `final_model_eligible=false`.

Uma tentativa independente de marching cubes em CPU foi interrompida por não concluir em tempo operacional útil. Nenhum resultado desse experimento foi promovido. O próximo passo de qualidade exige TSDF/Poisson em Linux/CUDA autorizado ou outro método volumétrico que seja medido e visualmente validado. A suíte completa do worker aprovou 30 testes; após migrar o FastAPI para `lifespan`, os 7 testes da API passaram sem os avisos de depreciação anteriores.

## Costura e fototexturização da Fase 8

A mesma malha multivista foi processada por uma costura conservadora de 1,25 cm condicionada pela orientação dominante das normais. O pós-processamento reduziu os componentes significativos de 30 para 14 e preservou 263.104 faces. A completude até 10 cm passou de 55,41% para 55,81%; a precisão até 10 cm passou de 51,67% para 50,73%. A troca é registrada como tal e não representa melhora uniforme.

A malha resultante possui 12.052 bordas abertas, 2.326 arestas não manifold, zero faces inválidas, zero faces degeneradas e não é watertight. Esses defeitos também aparecem nas renderizações e impedem aprovação comercial.

O atlas fotográfico usa 35 das 39 câmeras aceitas, projeção calibrada, ângulo de vista, nitidez, oclusão aproximada e compensação limitada de exposição. Ele cobre 151.307 das 263.104 faces (57,51%). As demais 111.797 faces recebem cor neutra e são contabilizadas explicitamente como sem textura válida. O atlas JPEG possui 1.286.537 bytes. A exportação final separada em cinco malhas/materiais PBR produziu GLB de 22.874.024 bytes, SHA-256 `eb201b5561f65483a93e0b3b05bb4b97b8fb7d19098f662104f0abca9721861f`, em 518,272 s de texturização. O pós-processamento equivalente anterior consumiu no máximo 1.103 MB de RAM observado no processo; ele não usou inferência neural e a VRAM não foi medida isoladamente.

As regiões `geometry-wall_candidate`, `geometry-floor_candidate`, `geometry-ceiling_candidate`, `geometry-horizontal_surface` e `geometry-other_surface` são evidências geométricas corrigíveis. Elas não classificam portas, janelas ou móveis. O GLB PBR final separa os materiais por esses IDs, permitindo seleção por raycast e edição isolada. As alterações ficam em `scene_graph.reconstruction_overrides`, com histórico de vinte operações e desfazer da última mudança. A validação automatizada comprova a ação estruturada e persistida; a fidelidade da seleção ainda exige revisão visual por imóvel.

Capturas reais do Three.js:

- `evidence/multiview-fusion/phase8-textured-front.png`;
- `evidence/multiview-fusion/phase8-textured-side.png`;
- `evidence/multiview-fusion/phase8-textured-top.png`;
- `evidence/multiview-fusion/phase8-stitched-textured-front.jpg`;
- `evidence/multiview-fusion/phase8-stitched-textured-side.jpg`.
- `evidence/multiview-fusion/phase8-final-app-edited.jpg`;
- `evidence/multiview-fusion/phase8-final-app-undo.jpg`.

A vista frontal confirma textura coerente em regiões observadas; as vistas lateral e superior expõem costuras, grandes lacunas e componentes ainda desconectados. No aplicativo autenticado, o GLB final abriu no Three.js, `geometry-ceiling_candidate` foi selecionado por raycast, o comando “Pinte esta parede de azul.” alterou somente essa região e o botão Desfazer restaurou a alteração com confirmação visível. Efeitos visuais não foram usados para ocultar os defeitos geométricos. O resultado é uma prévia texturizada e progressivamente editável, não uma reconstrução fiel final do ambiente completo.

## Fase 9 — avaliação TSDF volumétrica em CPU

A geração usou as mesmas 39 vistas aceitas, calibrações e profundidades da Fase 8. A malha oficial permaneceu inacessível ao gerador e foi aberta pelo comparador somente depois de cada PLY ter sido gravado. A implementação armazena blocos esparsos, integra distâncias assinadas truncadas por raio e extrai a isossuperfície por tetraedros com vértices compartilhados.

| Métrica | Fase 8 | TSDF 4 cm | TSDF 6 cm |
|---|---:|---:|---:|
| Precisão mediana | 9,8261 cm | 10,1926 cm | 9,3568 cm |
| Precisão até 5/10/20 cm | 25,11% / 50,73% / 77,52% | 22,93% / 49,28% / 79,14% | 22,46% / 52,99% / 81,81% |
| Completude mediana | 7,8980 cm | 11,3354 cm | 11,4979 cm |
| Completude até 5/10/20 cm | 32,43% / 55,81% / 66,06% | 28,55% / 47,90% / 57,94% | 25,72% / 47,44% / 58,56% |
| Componentes | 14 significativos | 2.505 totais | 728 totais |
| Bordas abertas | 12.052 | 119.278 | 49.412 |
| Arestas não manifold | 2.326 | 31.395 | 14.636 |
| Fototextura | 57,51% | não executada | não executada |
| Resultado | preservado | rejeitado | rejeitado |

Os candidatos volumétricos não foram texturizados nem enviados ao imóvel de QA porque falharam antes no gate geométrico. Isso evita transformar regressão em nova prévia. Os artefatos locais e relatórios estão em `storage/app/ai-real-results/playroom-small-independent/phase9-tsdf-*` e permanecem fora do Git.

A Fase 8 continua sendo a melhor prévia disponível, ainda sem qualidade comercial. O próximo avanço geométrico depende de mapas de profundidade multivista mais consistentes ou densificação estéreo real; suavizar/agrupar componentes deste TSDF sem essa evidência apenas ocultaria o erro.

O teste visual novo ficou bloqueado pela política de acesso da automação à aba localhost. Servidor/Vite foram verificados por HTTP 200, mas isso não substitui inspeção do canvas. As vistas da Fase 8 permanecem a referência visual; nenhuma captura nova foi declarada.

## Fase 10 — profundidade multivista e TSDF matematicamente corrigida

A referência oficial permaneceu fora da geração. O gerador leu somente as fotografias autorizadas, câmeras COLMAP, pontos SfM e profundidades Depth Anything V2. A referência foi aberta por `compare_reference_geometry.py` somente depois de cada PLY e `result.json` estarem gravados. As mesmas 10.000 amostras determinísticas, coordenadas COLMAP e ausência de ICP foram preservadas.

Falhas corrigidas:

- o valor TSDF era associado ao voxel obtido por `floor` da amostra de raio, causando viés de meio voxel;
- raios esparsos deixavam linhas não observadas mesmo para um plano completo;
- quadriláteros dos tetraedros eram triangulados na ordem das arestas, criando cruzamentos, componentes e não manifold;
- interseções exatamente no mesmo vértice de grade mantinham IDs diferentes;
- a cor da nuvem usava coordenadas da profundidade sobre RGB sem redimensionamento;
- confiança era apenas um escalar por câmera e não era aplicada por voxel/pixel.

O teste mínimo que reproduziu o defeito usou um plano analítico a 1,975 m e três câmeras. Antes: 6.241 vértices, 12.168 faces, 3.042 componentes, 9.360 bordas, 2.964 arestas não manifold e orientação inconsistente. Depois da integração projetiva e extração ordenada, as cenas sintéticas de plano, plano inclinado, canto, caixa, esfera e fronteira de bloco passaram sem não manifold nas superfícies significativas e com erros dentro das tolerâncias de voxel documentadas nos testes.

Resultados reais principais:

| Métrica | Fase 8 | Fase 9 6 cm | Fase 10 6 cm, sem filtro | Fase 10 4 cm, sem filtro | Fase 10 6 cm, holdout |
|---|---:|---:|---:|---:|---:|
| Vistas fundidas | 39 | 39 | 39 | 39 | 36; 3 reservadas |
| Vértices / faces | 132.538 / 263.104 | 37.154 / 67.686 limpos | 37.740 / 73.146 | 111.757 / 218.194 | 39.341 / 76.186 |
| Componentes | 14 significativos | 728 | 40 | 108 | 35 |
| Bordas / não manifold | 12.052 / 2.326 | 49.412 / 14.636 | 2.426 / 0 | 5.692 / 0 | 2.616 / 0 |
| Precisão mediana | 9,8261 cm | 9,3568 cm | 8,2092 cm | 9,8872 cm | 8,4702 cm |
| Precisão 5/10/20 cm | 25,11% / 50,73% / 77,52% | 22,46% / 52,99% / 81,81% | 30,87% / 57,43% / 83,39% | 26,03% / 50,50% / 79,32% | 29,87% / 56,72% / 82,52% |
| Completude mediana | 7,8980 cm | 11,4979 cm | 9,9226 cm | 8,8012 cm | 9,6256 cm |
| Completude 5/10/20 cm | 32,43% / 55,81% / 66,06% | 25,72% / 47,44% / 58,56% | 34,60% / 50,09% / 59,96% | 35,40% / 52,02% / 60,37% | 34,73% / 50,56% / 60,11% |
| Decisão | ativa | rejeitada | rejeitada | rejeitada | rejeitada |

O candidato de 6 cm demonstra que a maior parte da fragmentação da Fase 9 vinha da matemática TSDF: componentes, bordas e não manifold caíram sem suavização, preenchimento de regiões ocultas ou aumento indiscriminado do voxel. Ainda assim, sua completude fica 5,72 pontos percentuais abaixo da Fase 8. O candidato de 4 cm recupera parte da cobertura, mas perde precisão e aumenta componentes/tempo. Nenhum oferece ganho global suficiente.

Calibração por fotografia: 39/42 vistas aceitas, entre 29 e 526 âncoras e mediana de 111. A mediana dos resíduos relativos medianos é 2,20%; o pior valor mediano é 8,30% e o maior P90 é 19,82%. `53_DSC0001_117.jpg`, `53_DSC0043_120.jpg` e `53_DSC0085_123.jpg` foram rejeitadas com 2, 9 e 13 âncoras. Parâmetros e percentis completos permanecem em `phase10-tsdf-42-6cm-holdout-v2/fit-report.json`.

Consistência reservada: `45_DSC0001_45.jpg`, `45_DSC0043_48.jpg` e `45_DSC0085_51.jpg` não participaram da confiança nem da fusão. Seus relatórios registram respectivamente 14.014/279, 13.725/43 e 12.923/761 observações consistentes/inconsistentes. Isso demonstra coerência predominante, mas também regiões locais incompatíveis que um único ajuste global de escala não corrige com segurança.

Recursos do ensaio holdout reproduzido: 39,913 s, RSS amostrado 1,529 GB, pico CUDA alocado pelo PyTorch 195,220 MB, 432 blocos/4,424 MB estimados e zero descarte por orçamento. A configuração de 4 cm usou 962 blocos/9,851 MB e levou 74,734 s. A VRAM é do contador do PyTorch; o RSS é amostrado nas transições de estágio, não um medidor contínuo do sistema.

Artefatos locais ignorados pelo Git:

- `phase10-tsdf-42-none-v1`;
- `phase10-tsdf-42-moderate-v2`;
- `phase10-tsdf-42-strict-v1`;
- `phase10-tsdf-42-4cm-none-v1`;
- `phase10-tsdf-42-6cm-holdout-v2`;
- `phase10-calibrated-42-moderate-holdout-v1`.

Cada pasta válida contém PLY bruto/limpo, `result.json`, `fit-report.json`, relatório de consistência, mapas de confiança NPZ, manifesto, topologia, hashes e comparação posterior. Nenhum candidato rejeitado foi fototexturizado ou enviado ao imóvel. A Fase 8 e seu atlas de 57,51% permanecem ativos.
