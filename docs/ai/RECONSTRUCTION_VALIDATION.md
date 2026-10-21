# Validação da reconstrução 3D

## Portão geométrico

`services/ai-worker/app/reconstruction.py` calcula vértices, faces, componentes conectados, proporção do maior componente, triângulos degenerados, bounding box, consistência de orientação e fechamento. O resultado contém `viewer_eligible`, razões de rejeição e método. A exportação/promulgação do GLB ocorre apenas quando esse campo é verdadeiro; Laravel repete a verificação antes de importar o modelo.

A reconstrução esparsa histórica da Fase 4 registra 18/18 câmeras, 4.794 pontos e erro médio de reprojeção de 0,179462 px. Esses valores comprovam SfM, mas não comprovam uma sala utilizável. O novo portão rejeita a malha Delaunay esparsa fragmentada, preserva PLY/logs para diagnóstico e oferece a cena paramétrica como alternativa.

## Pipeline denso preparado

O fluxo opt-in executa `image_undistorter`, `patch_match_stereo`, `stereo_fusion` e meshing Poisson/Delaunay, depois aplica o mesmo portão e exporta GLB somente se aprovado. `services/ai-worker/compose.gpu.yml` e `gpu.env.example` documentam o worker Linux/NVIDIA.

`dense_reconstruction_capability` exige Linux, PyTorch CUDA, GPU NVIDIA visível e build compatível. No ambiente atual Windows, COLMAP sem CUDA para densificação, a execução fica `unavailable` e não simula sucesso. Nenhuma GPU paga foi contratada.

## Critério para a próxima validação

Uma captura autorizada real deve produzir e comparar nuvem esparsa, nuvem densa, malha, malha texturizada e GLB. Além do portão automático, a aprovação requer revisão visual, cobertura, buracos, artefatos, orientação, escala, materiais e render no navegador. Texturização e referência geométrica ainda não foram executadas; portanto, fotogrametria densa e malha texturizada permanecem pendentes.

## Fallback multivista calibrado

Quando o Poisson está indisponível, a fusão calibrada preserva as faces de todas as vistas aceitas, remapeia vértices pelo voxel, normaliza normais com sinal consistente e remove faces degeneradas ou duplicadas. Componentes pequenos são filtrados, mas o pipeline não reduz mais toda a reconstrução ao único maior componente. O relatório `fusion-report.json` registra contagens antes e depois da fusão e participa dos hashes de artefatos.

Esse fallback possui limites próprios para exibição de prévia (`64` componentes no máximo e pelo menos `2%` das faces no maior componente). Esses limites não promovem a malha para uso final: `final_model_eligible` permanece falso, e a inspeção visual do teste real mostrou aberturas e fragmentação apesar da melhoria de completude de 12,71% para 55,41% até 10 cm.
# Gate volumétrico da Fase 9

O worker oferece `bounded_sparse_projective_tsdf` como método selecionável e limitado por blocos. A promoção exige simultaneamente elegibilidade interna, ausência de regressão importante de precisão/completude e inspeção visual. No `playroom_small`, os candidatos de 4 cm e 6 cm falharam por perda de completude e forte aumento de componentes/bordas; a Fase 8 foi preservada. Resultado rejeitado não é copiado para `model.glb` nem recebe atlas para apresentação.

O cancelamento é cooperativo: o endpoint autenticado cria `cancel.requested`, o job verifica o marcador entre etapas/vistas/blocos e persiste estado `cancelled`. Processos nativos COLMAP já iniciados continuam limitados pelos timeouts existentes e só observam o cancelamento no próximo ponto seguro.

## Gate multivista da Fase 10

Antes do dataset real, `test_sparse_tsdf.py` valida seis geometrias analíticas e as convenções de câmera/profundidade. A TSDF usa profundidade Z, extrínseca COLMAP `x_camera = R x_world + t`, inversa `x_world = Rᵀ(x_camera-t)`, centros de voxel arredondados, sinal positivo à frente da superfície observada e isosuperfície zero. A extração compartilha vértices globalmente entre blocos, ordena cruzamentos no plano do tetraedro e corrige orientação por adjacência. Um plano sintético não pode mais produzir componentes/não manifold em massa.

`multiview_depth.py` reprojeta profundidades métricas entre câmeras. Pontos fora do campo, ocluídos, consistentes e inconsistentes são separados; oclusão não vira automaticamente rejeição. Residual de calibração, repetição, erro entre vistas e descontinuidades formam o mapa de confiança. As categorias são evidência geométrica e não classes neurais. Os modos `none`, `moderate` e `strict` são configuráveis; `none` permanece padrão porque os modos filtrados não melhoraram o conjunto completo.

O relatório obrigatório de cada candidato contém:

- parâmetros/inliers/percentis por fotografia e motivo de rejeição;
- erros e contagens por par de câmeras;
- mapas compactados de confiança/estado e manifesto;
- convenções matemáticas da TSDF;
- topologia, tempo, RSS amostrado, pico CUDA e memória dos blocos;
- hashes dos artefatos produzidos.

O holdout é configurado por `AI_VALIDATION_HOLDOUT_PREFIX`. As vistas correspondentes são avaliadas depois, mas não participam da confiança nem da fusão. O ensaio `45_*` reservou três vistas e confirmou esse estado no relatório.

Mesmo com zero arestas não manifold, os candidatos da Fase 10 não substituem automaticamente a Fase 8. O melhor ganho de precisão TSDF a 6 cm manteve somente 50,09% de completude até 10 cm, abaixo dos 55,81% ativos. O candidato de 4 cm alcançou 52,02%, ainda insuficiente, e aumentou componentes/tempo. Todos ficaram sem textura e sem promoção; `final_model_eligible=false` continua obrigatório.

QA manual reproduzível enquanto a automação local estiver bloqueada:

1. iniciar Laravel em `127.0.0.1:8000` e Vite em `127.0.0.1:5173`;
2. autenticar a conta de QA autorizada e abrir `/app/house-flipper/imoveis/30/3d`;
3. confirmar que o GLB ativo continua sendo a Fase 8;
4. testar enquadramento, rotação, zoom e vistas frontal/lateral/superior;
5. selecionar uma região, observar destaque, pintar, desfazer, refazer e recarregar;
6. iniciar uma reconstrução de teste, cancelar e conferir o estado persistido;
7. confirmar que um resultado `rejected` não altera o modelo ativo.

HTTP 200 e build não equivalem a validação do canvas. Sem Playwright instalado e com a política do controlador oficial recusando a aba local, a inspeção visual nova permanece pendente e não é marcada como aprovada.
