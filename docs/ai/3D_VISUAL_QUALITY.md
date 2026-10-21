# Qualidade visual foto → cena 3D

Data da validação: 20/10/2026.

## Contrato do produto

A experiência separa três operações que possuem garantias diferentes:

1. **Editar fotografia** usa SAM 2 com pontos positivos/negativos e preserva luminância, sombras e textura na composição. A imagem original fica imutável e cada resultado é salvo como uma nova simulação.
2. **Criar ambiente 3D** produz uma cena paramétrica aproximada a partir dos objetos detectados, caixas 2D, profundidade relativa e dados do imóvel. As posições continuam estimativas editáveis, sem dimensão métrica inventada.
3. **Reconstruir em 3D** aceita captura multivista. Um GLB só pode ser promovido ao visualizador quando `geometry_quality.viewer_eligible` é verdadeiro. PLY esparso e malha rejeitada permanecem apenas como diagnóstico.

## Correções verificadas

- Ausência de classificação de cômodo é `null`; a interface deixou de transformar ausência de resultado em `0%`.
- Confiança de detecção de objetos e confiança inferida do cômodo são campos independentes.
- A inferência de cômodo usa a melhor ocorrência de cada classe detectada, evitando que cadeiras ou plantas repetidas inflem um candidato.
- Na fixture autorizada `phase5-semantic-room.png`, DETR detectou sofá, mesa, duas cadeiras e duas plantas; a regra explicável classificou `living` com 94% e manteve `is_neural_room_classifier=false`.
- A cena persistida selecionou `living` e criou sofá, mesa, duas cadeiras e duas plantas como objetos paramétricos aproximados.
- `GlbViewer` valida tamanho, parse e bounding box, enquadra a câmera pela geometria e apresenta erro explícito quando o arquivo não é utilizável.
- A antiga Delaunay esparsa não é tratada como sucesso final quando falha no portão de componentes, faces, degeneração, dimensões e conectividade.

## Evidência visual e geométrica

- Entrada semântica: `evidence/phase5-semantic-room.png`.
- Detecções: `evidence/phase5-semantic-detection.json`.
- Máscara de pintura: `evidence/phase6-photo-paint-mask.png`.
- Resultado editado: `evidence/phase6-photo-paint-result.png`.
- Comparação: `evidence/phase6-photo-paint-comparison.png`.
- Resultado e métricas: `evidence/phase6-photo-paint-result.json`.
- Diagnóstico multivista anterior: `evidence/phase4/reconstruction-sparse.ply`, `reconstruction-mesh.ply`, `reconstruction-preview.glb` e `reconstruction-result.json`. Esses artefatos históricos comprovam o experimento, mas o GLB não satisfaz o novo portão e deixou de ser oferecido como resultado final.

## Estado de qualidade

O simulador 2D foi executado de ponta a ponta no navegador, salvo como simulação 55 e reaberto. A máscara real cobre 25,1891% da imagem; SAM 2 levou 918,17 ms e atingiu 232,94 MB de VRAM. A mudança de cor preserva variação de luz da região, porém a fixture ainda revela vazamento pontual em arte/cortina; o pincel positivo e a borracha negativa corrigem a orientação enviada ao SAM 2. Refinamento automático de borda continua pendente.

A cena aproximada é reconhecível e editável como maquete, mas ainda usa primitivas próprias. Um catálogo comercial de móveis texturizados e segmentação arquitetônica rotulada são necessários antes de declarar qualidade visual final.

## Incremento do simulador profissional

- A máscara agora combina a orientação manual do usuário com exclusões automáticas de sofá, cadeira, mesa, planta, cama, porta e janela detectadas. Regiões desconectadas sem ponto positivo são removidas e as bordas passam por fechamento e abertura morfológica.
- As exclusões automáticas são pontos negativos para o SAM 2, e não retângulos pintados sobre a máscara. Um clique positivo dentro de um objeto prevalece para permitir correção manual.
- Cada ambiente possui paredes `south`, `north`, `west` e `east` independentes no grafo. A câmera usa uma vista em corte sem as paredes frontal e direita, mantendo todas as superfícies editáveis sem esconder a sala.
- O piso usa textura procedural repetível para madeira/laminado ou juntas para cerâmica/porcelanato. O renderer usa sRGB, ACES Filmic, materiais PBR, sombras suaves, luz hemisférica, luz direcional e luz de janela.
- A sala de demonstração contém sofá, mesa, cadeiras quando detectadas, plantas, janela, luminária e tapete. As dimensões permanecem rotuladas como aproximadas até o usuário informar medidas.
- Dois modelos reais foram incorporados ao catálogo: Sheen Chair (CC0) e Sheen Wood Leather Sofa (CC BY 4.0 sobre melhorias, original CC0). A atribuição integral está em `frontend/public/models/furniture/LICENSES.md`.
- O primeiro quadro usa os objetos paramétricos e não espera rede, fotografias protegidas ou GLBs. Os GLBs são carregados sob demanda no modo de decoração e possuem fallback procedural.

A inspeção no navegador confirmou que a cena deixa de ficar preta durante o carregamento e que um ativo fotográfico indisponível não cria mais um retângulo preto na parede. Esta é uma maquete paramétrica reconhecível, não uma reconstrução métrica da fotografia.
