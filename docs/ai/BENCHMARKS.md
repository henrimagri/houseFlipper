# Benchmarks

| Recurso | Resultado local |
|---|---|
| Qwen2.5 3B, primeira inferência | 28.461 ms; 303 tokens entrada; 44 saída |
| Qwen2.5 3B, conversa já aquecida | resposta completa pelo `AiConversationService` em aproximadamente 5 s |
| RAG lexical, 3 paráfrases | Recall@4 0; MRR 0; 12 ms |
| RAG híbrido, 3 paráfrases | Recall@4 1,0; MRR 1,0; 168 ms |
| LoRA, treino | 6,805 s de treino; 15,76 s incluindo carga/avaliação; pico VRAM 1.291 MB |
| LoRA, validação | loss 2,924 → 2,819 |
| LoRA, inferência recarregada | 2,25 s; qualidade insuficiente para promoção |
| Depth Anything V2 Small | 984,06 ms; pico de 186,18 MB VRAM; 960 × 640 |
| SAM 2.1 Hiera Tiny | 811,05 ms; pico de 282,72 MB VRAM; máscara orientada por ponto |
| DETR ResNet-50 FP32 | 441,96 ms e 429,65 MB VRAM isolado; execução integrada aquecida 130,99 ms e 577,87 MB; 7 objetos acima de 0,72 |
| COLMAP SfM, 18 vistas | 18/18 câmeras; 4.794 pontos; erro de 0,179462 px |
| COLMAP CPU, etapas principais | SIFT 1,80 s; matching 4,53 s; mapping 4,69 s |
| SAM 2 + composição de pintura | 918,17 ms; pico de 232,94 MB VRAM; máscara com 25,1891% da imagem |

Os conjuntos são pequenos e medem integração, não qualidade de produção. A imagem de visão e o conjunto multivista são fixtures sintéticas autorizadas do projeto. O build frontend continua alertando sobre chunks grandes de Three.js/MapLibre.

A malha esparsa histórica deixou de ser elegível como resultado final após a introdução do portão geométrico. O pipeline denso está configurado, mas não foi medido neste host porque a capacidade Linux/NVIDIA/COLMAP CUDA necessária não está presente.
