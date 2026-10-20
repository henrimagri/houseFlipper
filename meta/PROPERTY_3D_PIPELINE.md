# Pipeline 3D

Data: 19/10/2026.

## Entrada

Do anúncio (hash de scrape) ou do registro:

- `area_m2`, quartos, banheiros, vagas, tipo, texto (varanda/quintal/suíte)
- GLB e planta já armazenados

Terreno não gera interior. Comercial gera salão + sanitário.

## Passos atuais

1. Classificar fidelidade (GLB → real; planta 2D → reconstructed label; metadados → parametric; resto → illustrative).
2. Montar spec: envelope `sqrt(area)*aspect`, sala+cozinha na frente, circulação, quartos, banheiros, garagem anexa.
3. Exterior: casa com prisma+telhado; apartamento em pavimentos; lote e recuo.
4. Interior: paredes 0,12 m, vão de porta, janela na fachada, rótulos 3D.
5. Cache: spec é função pura do input; não persiste malha inventada; nunca copia de outro id.

## Ainda não feito

Extrusão de paredes a partir da imagem da planta. Job assíncrono de malha. Mobiliário.
