# Arquitetura 3D

Rotas de UI: `/imoveis/{id}/3d` e `/leads/{id}/3d`.

API:

- `GET /api/visualizations/{propriedades|opportunities}/{id}`
- `POST /api/visualizations/.../assets` (GLB/glTF/foto/planta)
- `GET /api/visualizations/.../assets/{id}/file` (stream autenticado)

Níveis:

| Nível | Estado nesta entrega |
|---|---|
| A — modelo existente | Implementado (GLB/glTF, magic `glTF`, disco privado) |
| B — planta baixa | Upload aceito; reconstrução paramétrica **não** gerada |
| C — fotogrametria / Gaussian Splatting | Não implementado; não é fingido |
| D / fallback | Galeria + aviso de simulação ilustrativa |

O visualizador usa Three.js + GLTFLoader + OrbitControls. Sem modelo, a página permanece no registro original e **nunca** reutiliza o GLB de outro imóvel.
