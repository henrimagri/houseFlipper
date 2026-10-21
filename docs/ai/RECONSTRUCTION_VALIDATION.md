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
## Fase 11 — protocolo e resultado

### Implementação verificável

- `calibrated_stereo.py`: pares por evidência SfM e captura; pose relativa COLMAP; retificação horizontal/vertical; SGBM CPU; consistência esquerda/direita; oclusão; erro fotométrico; confiança; profundidade Z; retorno ao grid original; validação SfM; correção local limitada e híbrida.
- `calibrated_depth_fusion.py`: estratégias `AI_STEREO_STRATEGY=original|corrected|hybrid`, limites de resolução/pares/confiança, arquivos de evidência, isolamento de `AI_PARAMETER_HOLDOUT_PREFIX` e `AI_VALIDATION_HOLDOUT_PREFIX`, cancelamento e integração com a TSDF corrigida.
- `multiview_depth.py`: confiança máxima requer a hipótese observada pela fonte e por pelo menos duas vistas-alvo.
- `reconstruction.py`: componentes totais e significativos são emitidos separadamente, com limiar documentado.
- Laravel: candidato elegível para visualização é importado para auditoria, mas não substitui um primário existente sem `final_model_eligible=true`.

### Casos sintéticos

Plano frontal recupera 2,0 m dentro de 8%; transformação world→camera e pose relativa são conferidas; baixa textura não produz profundidade densa; iluminação desigual reduz o suporte; pouca sobreposição e baseline insuficiente são rejeitados antes do matcher; faixa ocluída perde suporte; correção espacial preserva âncoras, descontinuidades e limite de 22%. Os testes TSDF anteriores de plano inclinado, canto, caixa, esfera e fronteira de bloco continuam ativos e foram executados junto com os novos testes direcionados.

### Experimento real

Configuração fixa: 42 vistas selecionadas, 33 de desenvolvimento após reservar `44_*` e `45_*`; TSDF 6 cm; truncamento 18 cm; peso 1,25; stride 7; 12 pares; largura 640; até dois pares por vista; confiança mínima 0,32; textura desativada. A/B/C levaram 39,832/42,257/42,088 s.

Resultado C: 56,63% de precisão e 51,32% de completude a 10 cm; medianas 8,39 e 9,08 cm; 49 componentes totais, 4 significativos, 2.828 bordas abertas, zero não manifold, zero face inválida e zero face degenerada no relatório topológico. A melhora controlada sobre A foi pequena e a completude permaneceu inferior aos 55,81% da Fase 8. Resultado: **rejeitado para promoção**, sem textura e sem substituição do modelo ativo.

### QA do produto

O canvas real abriu em `http://127.0.0.1:5173/app/house-flipper/imoveis/30/3d`, com 497×619 pixels, seleção de `Parede candidata`, confiança geométrica estimada de 62% e alternância confirmada das vistas lateral e superior. Não foi instalado Playwright no projeto; a inspeção utilizou a automação oficial do navegador disponível. A pintura, desfazer/refazer, persistência e isolamento por tenant continuam cobertos pelos testes já existentes; esta execução não alterou o modelo ativo nem dados do usuário.

### Gate

Fase 8 permanece ativa. A Fase 11 comprova que SGBM calibrado é operacional no dataset e melhora discretamente A, mas sua cobertura de 2,66–7,30% por par é insuficiente para recuperar a completude perdida. Nenhuma região desconhecida foi promovida como estéreo. `final_model_eligible=false` continua obrigatório até avaliação geométrica e visual superior.


## Gate da Fase 12

A Fase 12 corrige a ROI correspondida, usa intervalo de disparidade específico por par, registra funil completo, seleciona pares por cobertura incremental e mede voxels mundiais realmente novos. O conjunto ampliado de 24 pares elevou voxels únicos de 14.370 para 18.410 e suporte independente de 4.075 para 6.818, com erro mediano SfM de 0,951%.

O melhor TSDF novo atingiu 57,85% de precisão e 52,33% de completude a 10 cm, 48 componentes totais/4 significativos, 2.964 bordas e zero não manifold. Ele melhora o TSDF da Fase 11, mas permanece abaixo dos 55,81% de completude da Fase 8. A fusão calibrada híbrida atingiu 49,29%/57,10%, porém apresentou 26 componentes significativos, 25.314 bordas e 1.141 arestas não manifold. Ambos foram rejeitados para promoção; nenhuma textura foi produzida.

O plane-sweep CPU de três ou mais vistas funcionou em teste sintético, mas no conjunto real obteve erro mediano independente de 16,17% e somente 33,33% das âncoras dentro de 10%. Não foi integrado à reconstrução. O resultado sustenta encerrar otimizações baseadas apenas em SGBM/limiares e preparar MVS denso autorizado ou o produto híbrido paramétrico com regiões geométricas verificadas.

Candidatos `viewer_eligible` podem ser abertos por diagnóstico sem alterar o modelo primário. O backend exige tenant, produto, propriedade do imóvel e asset pertencente à mesma visualização; `final_model_eligible=false` continua impedindo promoção. QA de canvas novo não foi realizado porque a ferramenta oficial recusou acesso à aba localhost. Fase 8 e a cobertura fototexturizada de 57,51% permanecem ativas.

## Gate denso e operacional da Fase 13

O capability gate inspeciona a lista real de comandos e a declaração de suporte GPU do executável. A sequência densa exige `mesh_texturer`; geometria aprovada sem atlas fotográfico verificável perde `viewer_eligible` e não gera GLB.

Cada reconstrução usa UUID idempotente e escopo HMAC por visualização. O hash acompanha progresso e resultado final, sem retornar na API. Consulta, cancelamento e artefatos recusam outro escopo. Reinícios encerram jobs órfãos com `WorkerRestarted`, e a retenção remove apenas jobs terminais expirados.

Neste host a sonda retornou COLMAP 4.2.1 `without GPU support`; o gate denso permaneceu fechado. Não há nova comparação geométrica ou visual. Fase 8 permanece ativa e `final_model_eligible=false` continua obrigatório até execução Linux/CUDA, benchmark independente e inspeção visual.
