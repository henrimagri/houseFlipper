# Validação da reconstrução 3D

## Portão geométrico

`services/ai-worker/app/reconstruction.py` calcula vértices, faces, componentes conectados, proporção do maior componente, triângulos degenerados, bounding box, consistência de orientação e fechamento. O resultado contém `viewer_eligible`, razões de rejeição e método. A exportação/promulgação do GLB ocorre apenas quando esse campo é verdadeiro; Laravel repete a verificação antes de importar o modelo.

A reconstrução esparsa histórica da Fase 4 registra 18/18 câmeras, 4.794 pontos e erro médio de reprojeção de 0,179462 px. Esses valores comprovam SfM, mas não comprovam uma sala utilizável. O novo portão rejeita a malha Delaunay esparsa fragmentada, preserva PLY/logs para diagnóstico e oferece a cena paramétrica como alternativa.

## Pipeline denso preparado

O fluxo opt-in executa `image_undistorter`, `patch_match_stereo`, `stereo_fusion` e meshing Poisson/Delaunay, depois aplica o mesmo portão e exporta GLB somente se aprovado. `services/ai-worker/compose.gpu.yml` e `gpu.env.example` documentam o worker Linux/NVIDIA.

`dense_reconstruction_capability` exige Linux, PyTorch CUDA, GPU NVIDIA visível e build compatível. No ambiente atual Windows, COLMAP sem CUDA para densificação, a execução fica `unavailable` e não simula sucesso. Nenhuma GPU paga foi contratada.

## Critério para a próxima validação

Uma captura autorizada real deve produzir e comparar nuvem esparsa, nuvem densa, malha, malha texturizada e GLB. Além do portão automático, a aprovação requer revisão visual, cobertura, buracos, artefatos, orientação, escala, materiais e render no navegador. Texturização e referência geométrica ainda não foram executadas; portanto, fotogrametria densa e malha texturizada permanecem pendentes.
