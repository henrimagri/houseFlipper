# Evidências da Fase 4

Arquivos produzidos por execuções reais em 20/10/2026:

- `vision-original.jpg`: fixture sintética autorizada.
- `vision-depth.png`: profundidade relativa do Depth Anything V2 Small.
- `vision-mask.png`: máscara binária do SAM 2.1 Hiera Tiny.
- `vision-overlay.png`: máscara sobre a entrada.
- `vision-result.json`: modelos, tempos, VRAM e resultado estruturado.
- `reconstruction-sparse.ply`: 4.794 pontos triangulados pelo COLMAP.
- `reconstruction-mesh.ply`: malha Delaunay esparsa de prévia.
- `reconstruction-preview.glb`: mesma prévia exportada para Three.js.
- `reconstruction-result.json`: métricas e limitações do pipeline.

As fixtures são sintéticas e pertencem ao projeto. Servem somente para provar o pipeline; não demonstram fidelidade sobre um imóvel comercial real.
