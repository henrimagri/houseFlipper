# Geometria e integração 3D

O Three.js existente agora recebe quatro classes distintas de conteúdo:

1. fotografias originais e resultados neurais;
2. maquete paramétrica editável, baseada na ficha;
3. GLB enviado pelo usuário;
4. `photogrammetry_preview`, GLB produzido da malha Delaunay esparsa do COLMAP.

O backend não classifica a prévia esparsa como reconstrução densa ou fiel. O payload inclui `fidelity: sparse_preview`, limitações e origem. Profundidade, máscara, nuvem PLY, malha PLY e GLB ficam como assets privados do mesmo `PropertyVisualization`.

TRELLIS, Hunyuan3D e NeRF não foram instalados: a Fase 4 precisava de geometria fotogramétrica, e esses modelos excedem ou pressionam os 6 GB de VRAM, têm exigências de licença/infraestrutura adicionais e não substituem uma captura multivista correta.

Para uma entrega densa de produção, o próximo worker deve executar COLMAP CUDA em Linux com armazenamento privado, limites por job e monitoramento de custo. O Laravel e o frontend já preservam o contrato de job e a distinção de fidelidade.
