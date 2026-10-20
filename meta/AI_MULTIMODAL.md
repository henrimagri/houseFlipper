# Análise multimodal

`POST /api/opportunities/{id}/photo-analysis`

Pipeline atual:

1. Permissão `photo_analysis`.
2. Fotos do próprio lead.
3. Classificação pelo **nome do arquivo**.
4. Registro de `origin` (`filename_heuristic` ou `inference`), `confidence` e limitações.

Não há modelo de visão treinado. Nenhuma medida é extraída da fotografia. Observação visual, dado do anunciante e inferência ficam separados no payload da subpágina 3D.

Formatos de foto: os já aceitos em oportunidades (jpg/png/webp).
