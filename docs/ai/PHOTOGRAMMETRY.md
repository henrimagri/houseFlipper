# Fotogrametria

Estado: **SfM esparso, malha de prévia e GLB implementados e testados; reconstrução densa bloqueada no worker local**.

## Runtime validado

- COLMAP 4.2.1 oficial para Windows x64, build sem GPU, licença BSD-3-Clause.
- ZIP SHA-256: `C493D88CB4A43F21AFBA42CB55BF786E5434A0420E73DB0BA1454C5D8B3686AE`.
- OpenCV 4.13.0 para portão de qualidade e fixture.
- Trimesh 4.9.0 para conversão da malha PLY em GLB.

## Conjunto de validação

O projeto gera 18 vistas sintéticas determinísticas de três planos texturizados por `tests/fixtures/generate_multiview.py`. O conjunto é de propriedade do projeto e liberado para este teste, mas não representa um imóvel real. Todas as vistas têm 960 × 720 pixels e posição de câmera conhecida.

## Resultado medido

| Etapa | Resultado |
|---|---|
| Gate de qualidade | 18/18 aceitas; nenhuma escura ou borrada |
| Extração SIFT | 1.801,47 ms, CPU |
| Correspondência exaustiva | 4.528,61 ms, CPU |
| Mapeamento/SfM | 4.685,28 ms, CPU |
| Câmeras registradas | 18/18 |
| Pontos 3D | 4.794 |
| Observações | 37.268 |
| Erro médio de reprojeção | 0,179462 px |
| Nuvem esparsa | `reconstruction-sparse.ply`, 72.098 bytes |
| Malha | `reconstruction-mesh.ply`, 27.714 bytes |
| Three.js | `reconstruction-preview.glb`, 26.896 bytes |

A malha é gerada pelo `delaunay_mesher` sobre a reconstrução esparsa e fica marcada como `sparse_delaunay_preview`. Ela é geometria real derivada das correspondências, mas não é superfície densa pronta. A UI exibe essa distinção e mantém a maquete conceitual separada.

O usuário envia entre 8 e 120 imagens privadas, confirma autorização/mesmo ambiente/sobreposição e recebe instruções sobre foco, luz, movimento e separação de imóveis. Antes do COLMAP, o worker rejeita capturas insuficientes, escuras, borradas ou com mistura excessiva de resoluções. Laravel valida tenant, produto, imóvel e asset IDs; o worker recebe apenas arquivos e um token interno.

## Etapa densa

O build instalado não tem GPU. Por isso `patch_match_stereo`, fusão densa, Poisson e textura final não foram declarados concluídos. O contrato e os artefatos esparsos continuam funcionais; produção densa deve usar o mesmo endpoint em worker Linux/CUDA com armazenamento privado equivalente.

## Profundidade calibrada multivista

Como alternativa de diagnóstico no Windows, `calibrated_depth_fusion.py` estima profundidade monocular, ajusta a escala de cada vista aos pontos SfM e preserva faces durante a fusão por voxel. Normais opostas no mesmo voxel são alinhadas antes da média; faces degeneradas e duplicadas são removidas. O método aumentou a completude até 10 cm de 12,71% para 55,41% no `playroom_small`, mas a malha continuou aberta e fragmentada no Three.js.

`AI_POISSON_ENABLED` controla a tentativa de Poisson: padrão falso no Windows e verdadeiro em Linux. `AI_POISSON_TIMEOUT` limita a execução. A superfície calibrada é uma prévia técnica com cores por vértice, sem atlas UV, e não substitui estéreo denso ou texturização.
