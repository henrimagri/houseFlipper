# Modelos de visão

Estado: **implementado e testado com fixture autorizada**.

## Modelos ativos

| Operação | Modelo | Revisão | Licença | Evidência local |
|---|---|---|---|---|
| Profundidade monocular relativa | `depth-anything/Depth-Anything-V2-Small-hf` | `5426e4f0f36572d16453bbda7a8389317b1bef99` | Apache-2.0 | 984,06 ms; pico de 186,18 MB VRAM |
| Segmentação orientada por pontos | `facebook/sam2.1-hiera-tiny` | `de431c4043854a71d8101e17995dfe596bf101a5` | Apache-2.0 | 811,05 ms; pico de 282,72 MB VRAM |
| Detecção semântica COCO | `facebook/detr-resnet-50` | pesos registrados no cache local | Apache-2.0 | 441,96 ms; pico de 429,65 MB VRAM na primeira execução isolada |

Os modelos usam PyTorch 2.8.0 + CUDA 12.6 por meio do worker FastAPI privado. A carga é preguiçosa e os pesos permanecem no cache local do Hugging Face. SAM 2 produz regiões a partir de pontos; DETR nomeia apenas classes cobertas pelo COCO. Depth Anything produz profundidade relativa, sem escala métrica.

`POST /v1/vision/analyze` valida formato, tamanho e pixels, executa os dois modelos e cria um job UUID isolado. O resultado contém original normalizado, mapa de profundidade, máscara, sobreposição, modelos, licença, latência, VRAM e limitações. `PropertyVisualizationService` baixa os artefatos com a credencial interna, grava no disco privado do tenant e associa tudo ao imóvel.

A fixture `authorized-room.jpg` e `phase5-semantic-room.png` são imagens sintéticas criadas para o projeto e permitidas para teste. A segunda foi gerada especificamente para validar objetos domésticos sem usar fotografia de terceiros. Elas não representam imóvel real nem são dados de treinamento. As evidências reproduzíveis estão em `docs/ai/evidence/`.

## Limites

- DETR detecta objetos COCO, mas não classifica cômodo, parede, piso ou material. A máscara SAM não recebe classe inventada.
- Uma fotografia isolada não gera geometria métrica.
- Fotos de portais continuam proibidas sem autorização/licença específica.

## Complemento de classificação

`NeuralVisionEvidenceMapper` normaliza caixas e confiança do DETR, separa a confiança dos objetos da confiança do cômodo e aplica regras auditáveis sobre evidências únicas por classe. Isso classificou a fixture semântica como sala com 94%, mas não é um classificador neural de cômodos. Segmentação arquitetônica rotulada continua ausente até existir checkpoint com classes e licença comercial validadas.

O endpoint `POST /v1/vision/simulate` usa pontos positivos e negativos no SAM 2 e compõe a cor preservando luminância e detalhes locais. Na execução registrada: 918,17 ms, 232,94 MB VRAM e cobertura de máscara de 25,1891%.
