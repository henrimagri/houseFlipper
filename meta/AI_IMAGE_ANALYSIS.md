# Análise visual de fotografias

Data: 19/10/2026.

## Implementado

`PhotoSceneAnalyzer` classifica cada foto autorizada do registro (lead ou imóvel).

Entrada: nome do arquivo local permitido + bytes se o arquivo existir no disco `public`/`visualizations`.

Saída por foto:

- `room_guess` (sala, quarto, banheiro, cozinha, varanda, garagem, fachada, externo)
- `elements` só quando o nome contém evidência (`sofa`, `cama`, `janela`, `porta`, `pia`)
- `palette` e `pixel_stats` via `PhotoPixelSampler` (histograma GD 48px)
- `confidence` 0–72, rotulada como estimativa
- `origin`: `filename_heuristic` | `filename_and_pixels` | `pixel_palette` | `inference`
- limitações explícitas: sem medida métrica, sem modelo de visão treinado

`SceneBriefBuilder` agrupa fotos no JSON de ambientes. Cômodo `unknown` não vira planta. Sem fotos identificadas, cai num ambiente conceitual `living` com `desconhecido`.

## Não implementado

Segmentação semântica, detecção de objetos, OCR de planta, correspondência multi-vista, profundidade monocular.

## Isolamento

Só lê caminhos aprovados por `OpportunityPhotoPathPolicy`. URL remota não é baixada.
