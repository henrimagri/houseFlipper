# Pipeline fotos → cena 3D

Data: 19/10/2026.

```
fotografias autorizadas
  → PhotoSceneAnalyzer
  → SceneBriefBuilder
  → SceneGraphFactory
  → PropertyVisualization.scene_graph
  → editor Three.js
```

`POST /api/visualizations/{tipo}/{id}/rebuild` executa o pipeline de forma síncrona (PHP built-in server não tem fila). Status: `analyzing` → `ready` | `insufficient`.

Camadas:

- A — plano fotográfico + paleta amostrada
- B — grafo editável (superfícies, móveis, cômodos)
- C — editor / comandos de IA

GLB e planta 2D continuam opcionais. Fotos bastam para iniciar a simulação. Fotogrametria, Gaussian Splatting e SfM não estão ligados.
