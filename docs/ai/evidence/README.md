# Evidências multimodais

- `phase4/`: profundidade, segmentação, COLMAP esparso, malha de prévia e GLB da Fase 4.
- `phase5-semantic-room.png`: sala sintética criada para este projeto, sem pessoas, marcas ou fotografia de terceiros. Uso exclusivo como fixture autorizada de validação.
- `phase5-semantic-detection.json`: execução real do DETR ResNet-50 na fixture, com caixas, classes, confiança, latência e VRAM.

As fixtures demonstram execução e integração técnica. Não medem fidelidade em um imóvel comercial real.

## Fase 6 — pintura e qualidade 3D

- `phase6-photo-paint-mask.png`: máscara SAM 2 gerada com pontos positivos e negativos.
- `phase6-photo-paint-result.png`: composição de cor que preserva luminância/textura da superfície.
- `phase6-photo-paint-comparison.png`: entrada e resultado lado a lado.
- `phase6-photo-paint-result.json`: pontos, modelo, latência, VRAM, cobertura e limitações.
- `photo-simulation-runs/`: saídas completas reproduzíveis do worker, incluindo original e overlay.

Os arquivos da pasta `phase4` permanecem como evidência geométrica histórica. O `reconstruction-preview.glb` não passa pelo portão de qualidade atual e não é apresentado ao usuário como reconstrução final.
