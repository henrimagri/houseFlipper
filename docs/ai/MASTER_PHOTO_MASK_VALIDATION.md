# Master ASTRA — máscara fotográfica manual, 21/10/2026

Estado: **implementado e testado** o pincel/borracha manual e o vínculo do histórico. Modalidade fotográfica comercial completa continua **parcial**. Continuação do marco publicado em `f831dc5`, exclusivamente em `Correção-visua/proteção`.

## O que mudou

O antigo “pincel” acumulava pontos de SAM e não pintava uma máscara. Agora a interface separa **Pontos com IA** de **Máscara manual**. O modo manual desenha/erasa traços reais, com largura, cursor, overlay, desfazer/refazer por traço, limpeza e bloqueio de máscara vazia. Imagens mantêm proporção original; coordenadas são calculadas sobre a própria imagem, sem esticá-la em 4:3. A prévia usa no máximo 1024 pixels no maior lado e lê RGBA somente ao concluir uma alteração, não em cada movimento.

O contrato validado por Laravel e FastAPI aceita até 64 traços, 256 pontos por traço e 16.384 pontos totais. Coordenadas normalizadas [0,1], raio [0,002;0,15] do menor lado, modos `paint`/`erase`. Pontos SAM não se misturam aos traços manuais. Pincel e borracha rasterizam no tamanho da foto orientada por EXIF; uma máscara totalmente apagada é rejeitada antes de criar arquivos. O fluxo manual não chama DETR, SAM ou Depth Anything e registra `manual_user_mask`, `model_used=false`. A composição preserva iluminação relativa e é uma aproximação 2D, não um material fisicamente validado.

Cada simulação agora se vincula aos próprios IDs de resultado/comparação dentro do resultado do job. Antes, o histórico procurava o último job da mesma foto e atribuía seus parâmetros a todas as versões. Dois testes novos reproduziram a ausência do contrato e validação insuficiente antes da correção; regressão integrada confirma cores/raios/comparações diferentes após duas execuções. Resultados legados ambíguos continuam visíveis com `metadata_available=false`; não recebem parâmetros inventados de outra execução. O estado `completed` só é salvo depois da importação da imagem processada. Original e arquivos anteriores são preservados.

## Teste real no produto

Origem: dataset já autorizado **Meta Eyeful Tower, playroom_small**, foto `images-jpeg-1k/40/40_DSC0001.jpg` (1082×723, JPEG com lente fisheye). Conferidos manifesto e licença MIT locais; hash SHA-256 `6ca67b203d84ef3b0fcd7817221f57e83e1df21107db13a3ba295971e40b690e`. É um ambiente real de demonstração, não residência do usuário. Nenhuma foto de portal foi utilizada. Aviso de copyright/licença acompanha as capturas em `evidence/master-editor/photo/LICENSE-EYEFUL-TOWER.txt`.

Pelo navegador oficial, a foto foi enviada ao imóvel de QA “QA Visão Neural Fase 4”. Foi desenhada uma faixa pequena na parede e depois apagado seu centro. Desfazer restaurou a faixa; refazer recuperou o recorte. A aplicação chamou Laravel e o worker real em loopback, importou resultado e comparação, exibiu a faixa azul somente na seleção e manteve a lacuna apagada. Após recarregar, escolher “Simulação 76” recuperou a mesma foto, cor `#336699`, modo manual e três traços (um deles sem interseção com a faixa). A seleção corrigida e o resultado foram novamente inspecionados.

Jobs reais de QA: `229dde3d-7bed-4086-af08-64ddb1be50d3` (primeira faixa), `8311100d-8b0b-4c26-acc0-ca81c3067fb3` (faixa com centro apagado). Artefatos privados locais em `services/ai-worker/artifacts/master-photo-qa/<job>/`: original lossless derivado, máscara, overlay, simulação, comparação e resultado JSON. Não foram incluídos no Git. O worker foi iniciado com diretório de QA isolado para não reconciliar/limpar artefatos anteriores.

Validação independente dos arquivos salvos, sem regenerar imagens:

```powershell
.\services\ai-worker\.venv\Scripts\python.exe services/ai-worker/scripts/validate_manual_photo.py --source storage/app/ai-real-datasets/eyeful-tower/playroom_small/images-jpeg-1k/40/40_DSC0001.jpg --result-dir services/ai-worker/artifacts/master-photo-qa/8311100d-8b0b-4c26-acc0-ca81c3067fb3 --output evidence/master-editor/photo/validation.json
```

| Medição real | Resultado |
|---|---:|
| Pixels selecionados | 7.343 |
| Pixels alterados | 7.343 |
| Pixels alterados fora da máscara | **0** |
| Cobertura da foto | 0,93866% |
| Original derivado versus JPEG decodificado/orientado | idêntico pixel a pixel |
| Segmentação neural no modo manual | não executada |
| Worker, working set após teste | 526,44 MiB |
| Worker, pico de working set desde inicialização | 636,51 MiB |

O pico é do processo do worker, inclui importação PyTorch/sondas e ambos os jobs; não é consumo total da aplicação. O log marcou 146,52 ms e 115,83 ms apenas para a etapa de carga/rasterização protegida pelo lock; **não são latências HTTP completas**, pois composição/exportação/importação ocorrem depois. Pico de VRAM não foi amostrado nestas execuções; o algoritmo manual é CPU. Não confundir CUDA disponível no PyTorch com COLMAP CUDA executado.

## Testes e evidências

- Python completo: **106 testes aprovados em 32,90 s**, um warning da fixture UV NaN inválida na suíte de reconstrução. Casos novos verificam limites, máscara vazia/apagada, bordas, borracha, pincel mínimo, original intacto, pixels externos exatos, ausência de chamadas neurais e fluxo SAM legado.
- Laravel completo: **89 testes, 457 assertions, 6.750 ms**; depois acrescentada rejeição entre tenants/asset de outro imóvel: suíte focada `PropertyVisualizationNeuralTest` **8/8, 77 assertions, 1.625 ms**. Nenhuma chamada ao worker nos pedidos negados.
- Frontend: **21 testes**, sendo 12 verificações estáticas existentes e 9 testes funcionais do helper de máscara/histórico/coordenadas. TypeScript + Vite aprovados (15,27 s); aviso conhecido dos chunks grandes permanece.
- Teste real do navegador descrito acima. Capturas: `manual-mask-before.jpg`, `manual-result.jpg`, `manual-reopened.jpg`; relatório numérico e hashes em `evidence/master-editor/photo/validation.json`. As capturas derivam apenas da fotografia pública licenciada e do projeto de QA.

## Pendências e próxima prioridade

Não houve nova inferência SAM real neste marco; sua compatibilidade foi testada com mocks. O manual começa com máscara vazia e não permite ainda retocar diretamente o raster produzido pelo SAM. A máscara no canvas é uma prévia reduzida: antialiasing pode diferir na borda por aproximadamente um pixel; o worker é a autoridade do resultado persistido. Há somente pintura e madeira procedural, sem reprojeção de piso/perspectiva física. Falta comparação deslizante, catálogo de materiais licenciado maior, seleção automática arquitetônica avaliada, QA amplo de superfícies/reflexos e mais fotografias residenciais autorizadas.

Próxima prioridade local: tornar a máscara automática revisável antes da composição e aproximar a qualidade de materiais/aberturas do editor paramétrico. Fila durável/limites de recurso, streaming da IA, cobrança e expansão dos produtos permanecem na matriz geral. Reconstrução densa depende do ensaio Linux/CUDA autorizado; a malha Fase 8 permanece ativa, sem novas métricas ou promoção.
