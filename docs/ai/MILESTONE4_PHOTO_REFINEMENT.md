# Marco 4 — seleção neural com retoque e pintura (21/10/2026)

Estado: fluxo implementado e testado; qualidade comercial geral **parcial**. Base `94e4dc5`, branch `Correção-visua/proteção`. Não altera a reconstrução ativa da Fase 8.

## Alterações efetivas

- `vision/segment` executa somente SAM 2.1 Hiera Tiny, com um grupo de pontos positivos/negativos. Preserva pequenos buracos e detalhes, sem fechamento morfológico agressivo no novo fluxo.
- Laravel importa a máscara como asset privado, associado ao imóvel/foto e a um job concluído. Retoque exige esse vínculo e checksum; máscara de outro tenant, foto ou composição não serve como base neural.
- Editor raster conserva a base neural ao alternar pincel/borracha, oferece zoom/pan, largura, opacidade, mostrar/ocultar, undo/redo, limpar ajustes/restaurar base e confirmação da seleção. Coordenadas são normalizadas pela fotografia orientada, não pela tela.
- Prévia separada do histórico. Somente a confirmação cria versão visível; idempotência impede confirmação duplicada. Alterar cor/seleção invalida a prévia. Recupera original, base, traços, prompts, dimensões, cor, acabamento e resultado; não sobrescreve o original.
- Composição refinada usa a mediana de luminância da superfície selecionada. A fórmula anterior dividia pela luminância da cor alvo, clareando indevidamente cores escuras. Teste reproduziu a falha antes da correção. Fosco/acetinado/semibrilho modulam somente destaques já presentes; são aproximações, não refletância física nem certificação de tinta. Fluxo SAM direto legado preserva seu baseline.

## Fotografias e autorização

Manifesto e hashes: `evidence/milestone4/photo-sources.json`. Três imóveis/ambientes distintos usados **separadamente** para pintura 2D, nunca combinados para reconstrução:

1. Sala de brincar Meta Eyeful Tower, `playroom_small/40/40_DSC0001.jpg`, MIT, 1082×723. Autorização histórica em `MASTER_PHOTO_MASK_VALIDATION.md`.
2. Cozinha de NeONBRAND, 1280×853, [Commons / CC0](https://commons.wikimedia.org/wiki/File:Blue_white_kitchen_interior_(Unsplash).jpg).
3. Quarto de Nenad Stojković (Shixart1985), 1280×854, [Commons / CC BY 2.0](https://commons.wikimedia.org/wiki/File:Cozy_bedroom_with_a_large_bed_and_simple_decor_in_a_modern_home.jpg), [licença](https://creativecommons.org/licenses/by/2.0/). Captura do quarto inclui recorte/redução, overlay de seleção e retoques de QA; atribuição também em `evidence/milestone4/ATTRIBUTION.md`.

Originais/datasets/artefatos integrais permanecem no armazenamento ignorado, não no Git. Sem máscaras verdade anotadas: avaliação qualitativa, **sem IoU/Dice/precisão de segmentação**. Banheiro, escritório, corredor, fachada e exterior não foram avaliados neste marco.

## Ensaios reais reproduzíveis

Executados na raiz do repositório:

```powershell
services/ai-worker/.venv/Scripts/python.exe services/ai-worker/scripts/evaluate_detr_painting.py --images storage/app/ai-real-datasets/eyeful-tower/playroom_small/images-jpeg-1k/40/40_DSC0001.jpg storage/app/ai-real-datasets/painting-qa/kitchen.jpg storage/app/ai-real-datasets/painting-qa/bedroom.jpg --output evidence/milestone4/detr-evaluation.json
services/ai-worker/.venv/Scripts/python.exe services/ai-worker/scripts/evaluate_photo_refinement.py --cases evidence/milestone4/refinement-cases.json --output storage/app/ai-real-results/milestone4-refinement
services/ai-worker/.venv/Scripts/python.exe services/ai-worker/scripts/evaluate_photo_refinement.py --cases evidence/milestone4/refinement-cases.json --output storage/app/ai-real-results/milestone4-refinement-normalized
```

Scripts exigem saída nova, cache offline e até oito fotos. Segundo ensaio aplica a correção de composição, sem mudar os prompts. Círculo apagado e pequena inclusão são retoques diagnósticos deliberados, não falhas atribuídas ao SAM.

| Foto | SAM CUDA (ms) | Pico alocado PyTorch (MiB) | SAM + 3 composições/exportações (s) | Cobertura base |
|---|---:|---:|---:|---:|
| Sala de brincar | 1490,85 | 232,94 | 4,7060 | 6,6123% |
| Cozinha | 2780,90 | 264,94 | 6,4780 | 4,3538% |
| Quarto | 2117,94 | 264,94 | 6,4307 | 12,7292% |

Nove composições (três acabamentos por foto): **zero pixels alterados fora da máscara**, base neural imutável e exclusão manual respeitada. Composição CPU sem nova inferência. O cabeçalho inicial do script dizia CPU por intenção, mas `CUDA_VISIBLE_DEVICES=''` não desabilitou CUDA neste Windows. Corrigidos script e cabeçalhos dos relatórios, preservando a telemetria CUDA real por caso; não se apresentou essa execução como CPU. RAM não amostrada nesses ensaios. Worker integrado após QA: working set 1.348.407.296 bytes, pico desde reinício 1.567.891.456 bytes; inclui runtime/modelo, não RAM total do sistema.

DETR ResNet50 em CPU, quatro threads, mesmo tensor para comparar limiares, sem alterar produção:

| Foto | Detecções ≥0,30 / ≥0,50 / ≥0,72 | Inferência (s) |
|---|---|---:|
| Sala de brincar | 0 / 0 / 0 | 1,9528 |
| Cozinha | 42 / 29 / 19 | 1,7534 |
| Quarto | 6 / 4 / 4 | 1,6607 |

Quarto: cadeira 0,998142, cama 0,963885 e 0,883828 (duas caixas da cama), planta 0,806376. Tigelas abaixo de 0,50 visualmente ambíguas. Score não é precisão medida. Taxonomia COCO não fornece paredes, portas, janelas, cortinas nem pufes como classes próprias. Novo fluxo não exclui caixas DETR automaticamente: elas não são máscaras arquitetônicas e podem remover parede válida. Pontos negativos e borracha são o controle explícito.

## QA efetivo no navegador

Conta local autorizada de QA, imóvel 30, Laravel → FastAPI → SAM CUDA → armazenamento privado → React. Nenhum cookie de navegador externo reutilizado.

- Quarto real: inclusão na parede, exclusões na cama/cadeira/cortina, máscara exibida; zoom 125%, pincel e borracha sobre a mesma base; dois traços, undo para um e redo para dois. Prévia acetinada e salvamento **Simulação 87**. Recarga/reabertura recuperou base/traços/acabamento. Pan, overlay e restaurar base também exercitados.
- Cozinha real: segmentação, interrupção do acompanhamento, nova segmentação, acabamento semibrilho. Mudança de cor depois da prévia desabilitou salvar até recalcular. Resultado corrigido `#58738a` confirmado como **Simulação 97**. Recarga recuperou a fotografia correta, máscara, cor e semibrilho. Histórico anterior preservado.
- Desktop 1440×900: comparação lado a lado na mesma escala e controles acessíveis. Tela estreita: ferramentas junto à foto, sem exigir rolar até um painel distante; máscara reaberta inspecionada. Override de viewport restaurado.
- Capturas reais: `bedroom-refined-zoom.jpg`, `kitchen-desktop-saved.jpg`, `kitchen-mask-reopened.jpg` em `evidence/milestone4/`. Captura desktop apresenta redução/embaçamento do capturador; imagens locais de resultado também foram abertas para inspeção em resolução nativa.

Resultado visual: pintura respeita a região escolhida e preserva variações de luz; a normalização reduz o clareamento indevido. Cozinha seleciona apenas parte da parede direita, com recorte irregular junto ao teto; quarto tem transição parcial no meio da parede e detalhes que exigem ajuste. **Não há aprovação de pintura comercial automática**, de detecção arquitetônica geral ou de máscaras perfeitas.

## Testes e limitações

- Worker: `.venv/Scripts/python.exe -m pytest -q` em `services/ai-worker`: **146 aprovados, 32,19 s**. Warning conhecido da fixture UV inválido. Regressões de prompts, PNG inválido/dimensões/EXIF, traços, base imutável, fora da máscara, acabamentos, erros e limites.
- Laravel: PHP 8.4 com SQLite, `vendor/bin/phpunit`: **95 testes, 517 assertions, 9.603 ms**. Foto/base/tenant incorretos, worker falhando, preview/confirm/discard, bloqueio concorrente, persistência/prompts; sem autorizar outro produto.
- Frontend: `npm test`: **26 testes, 131 ms**, incluindo helpers comportamentais de máscara/coordenadas/histórico e verificações estáticas legadas. `npm run build`: TypeScript + Vite aprovados, **15,06 s**, aviso conhecido de chunks grandes.
- Interrupção de acompanhamento usa AbortSignal e ignora resposta tardia; **não interrompe inferência nativa em andamento**. Prévia conhecida descartada fica privada e fora do histórico. Aborto antes de receber seu id pode deixar artefato privado órfão até retenção; quota continua aplicada. Não declarar cancelamento duro da GPU concluído.
- Bordas de raster reduzido podem diferir aproximadamente um pixel do worker; dimensões são conferidas antes de usar base. EXIF/proporções cobertos por testes, não por ensaio visual de cada orientação em cada navegador.
- Sem treinamento, modelos novos, dependências pesadas, alteração de drivers ou densificação COLMAP. Próxima prioridade iniciada separadamente: aberturas reais no cômodo paramétrico, materiais e catálogo, preservando a malha observada ativa.
