# Master ASTRA — correção e execução real do SAM, 21/10/2026

Terceiro marco, após `f831dc5` e `3a084b7`. Segmentação neural executada com fotografia autorizada; qualidade comercial geral continua parcial.

## Falha e correção

`VisionPipeline._segment_locked` enviava cada ponto como um objeto independente. O contrato do `Sam2Processor` instalado (Transformers 5.20.2, `processing_sam2.py`) é imagem → objeto → pontos → coordenadas. Para três pontos, a forma antiga era `(1,3,1,2)` e labels `(1,3,1)`. A forma corrigida é `(1,1,3,2)` e labels `(1,1,3)`: inclusão e exclusões condicionam a mesma máscara. O código anterior unia as máscaras dos objetos; agora exige exatamente um objeto/uma máscara e rejeita saídas inesperadas.

Dois testes de regressão falharam antes da correção. Usam o processor realmente instalado, sem baixar pesos, e saída neural controlada. A suíte completa passou: **108 testes, 38,08 s**, um warning conhecido na fixture que injeta UV inválido. Laravel/frontend não foram modificados neste marco; resultados do marco anterior continuam registrados, sem alegação de nova execução dessas suítes.

## Comparação neural reproduzível em CPU

Foto real MIT Meta Eyeful Tower `playroom_small/images-jpeg-1k/40/40_DSC0001.jpg`, 1082×723, SHA-256 `6ca67b203d84ef3b0fcd7817221f57e83e1df21107db13a3ba295971e40b690e`. Origem/licença detalhadas em `MASTER_PHOTO_MASK_VALIDATION.md`. Pontos versionados em `evidence/master-editor/photo/sam-points.json`: parede positiva (370,300), móvel negativo (460,400), piso negativo (500,520).

Comando executado de `services/ai-worker`:

```powershell
.venv/Scripts/python.exe scripts/compare_sam_prompt_groups.py --image ../../storage/app/ai-real-datasets/eyeful-tower/playroom_small/images-jpeg-1k/40/40_DSC0001.jpg --points ../../evidence/master-editor/photo/sam-points.json --output ../../storage/app/ai-real-results/master-sam-prompt-groups --device cpu
```

Script usa somente cache offline, até oito pontos, quatro threads CPU por padrão e diretório novo; preserva o original e exporta hashes, máscaras e overlays. Não usa malha oficial ou qualquer referência geométrica. Aplica o mesmo refinamento às duas máscaras.

| Medida | Agrupamento antigo | Agrupamento corrigido |
|---|---:|---:|
| Objetos/máscaras retornados | 3 | 1 |
| Cobertura refinada | 6,8214% | 6,6249% |
| Inclusão positiva respeitada | sim | sim |
| Duas exclusões respeitadas | sim | sim |
| Tempo de inferência/pós-processamento | 3,0225 s | 2,5405 s |

Carregamento 0,5634 s. Execução CPU, zero alocação CUDA pelo processo; RAM desse experimento não amostrada. A ordem fixa tem primeira inferência fria, portanto estes tempos não demonstram ganho de desempenho. Ambos os overlays foram abertos e inspecionados: parede reconhecível, piso/pufes excluídos; agrupamento correto preservou mais tomadas/controles, com pequenas lacunas e contornos ainda imperfeitos. Sem máscara verdade anotada, não há IoU nem comprovação de fidelidade comercial. O JSON automático mantém `visual_quality_validated=false`; a inspeção qualitativa posterior está descrita aqui, sem alterar a evidência bruta.

## Execução integrada no navegador e GPU

Worker de QA reiniciado em loopback, com cache offline e diretório de artefatos isolado. Na aplicação, mesma foto, um ponto positivo e dois negativos posicionados por clique (371,299), (459,401), (501,521). Cor `#336699`. Laravel chamou DETR/SAM e importou a simulação no histórico: **Simulação 80**, job `c0809a2e-38b8-4b31-afce-c0d9b70f057e`. A captura real `evidence/master-editor/photo/sam-result.jpg` mostra antes/depois; pintura limitada à parede esquerda, piso/móveis/parede direita preservados. Diferenças de um ou dois pixels nos pontos decorrem do clique sobre a foto reduzida; este ensaio não é a comparação CPU controlada acima.

- SAM 2.1 Hiera Tiny: CUDA, 1.282,79 ms, pico PyTorch alocado reportado 396,62 MiB, uma máscara, cobertura 6,6318%.
- DETR ResNet50: CUDA, 1.170,70 ms, pico PyTorch alocado 429,61 MiB; **nenhum objeto acima do limiar 0,72**, portanto nenhuma exclusão automática acrescentada. Os pufes visíveis não foram detectados. Não houve chamada Depth Anything nesta simulação 2D.
- Worker após execução: working set 1.621.094.400 bytes; pico desde reinício 1.683.230.720 bytes. Inclui imports e carregamento dos dois modelos; não é RAM total da máquina. VRAM reportada é alocação do processo, não uso total da GPU. Tempos acima não incluem toda a requisição HTTP/importação.
- Comparação independente contra o JPEG original decodificado/orientado: 51.880 pixels selecionados e alterados; **zero pixels alterados fora da máscara**. A cópia de apresentação `original.jpg` é recomprimida pelo fluxo SAM legado; esta verificação usa a fotografia fonte preservada.
- Avisos do Transformers: configuração `sam2_video` carregada como `Sam2Model` e buffers BatchNorm inesperados no DETR. Os modelos executaram; os avisos foram registrados, não tratados como prova de erro nem omitidos. Não houve download ou instalação.

Relatórios pequenos versionados: `sam-grouping-report.json`, `sam-runtime-report.json`, `sam-pixel-check.json`. Máscaras e overlays completos ficam somente no armazenamento local ignorado. Licença da foto acompanha as evidências. Nenhum dataset, peso ou credencial incluído no commit.

## Limites

A correção resolve o contrato dos prompts. Não resolve classificação arquitetônica, reflexos, bordas finas ou ausência de detecção. O usuário ainda não pode retocar o raster SAM antes da composição; a máscara manual começa vazia. Próxima prioridade: prévia editável da seleção automática e avaliação em mais fotos autorizadas. Comparação funcional Suvinil com 13 itens registrada em `COMMERCIAL_READINESS_MATRIX.md`, distinguindo HTML observado de runtime não verificado. Fase 8 permanece ativa; nenhuma reconstrução densa, nova métrica geométrica ou promoção de malha neste marco.
