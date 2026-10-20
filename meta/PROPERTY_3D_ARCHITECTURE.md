# Arquitetura 3D do imóvel

Data: 19/10/2026. Estado: **implementado na maquete paramétrica**.

## Diagnóstico original

`ListingLayoutViewer` desenhava volumes soltos. `/imoveis/{id}/3d` só mostrava GLB. Planta era upload sem extrusão.

## O que existe agora

```
metadados do anúncio/ficha ─► PropertySceneSpec ─► ExteriorScene | InteriorScene | Planta
GLB enviado (se houver)     ─► aba Modelo no PropertyViewer
planta 2D (se houver)       ─► aba Fotos + selo “Planta 2D + maquete”
```

`frontend/src/lib/propertySceneSpec.ts` monta o spec. `PropertyMaquetteViewer` gera a malha. `ListingLayoutViewer` é fachada da maquete na análise do anúncio.

## Hierarquia

1. **Real** — GLB/glTF validado, disco privado, um `subject_id`. Aba Modelo.
2. **Planta 2D** — arquivo `floorplan`. O 3D continua paramétrico até haver extrusão cotada.
3. **Paramétrico** — tipo, m², quartos, banheiros, vagas, varanda/quintal no texto.
4. **Ilustrativo** — ficha incompleta, mesmo pipeline, selo explícito.

Fotogrametria / Gaussian Splatting / tour 360 gerado de fotos **não entra**.

## Fora desta entrega

Decoração, mobiliário catalogado, antes/depois sem par de modelos, WebXR, reutilizar GLB do imóvel A no anúncio B.
