# Adendo visual — globo, mapa e tutorial

## Resultado

- `Globe3D` foi refeito sobre React Three Fiber/Drei seguindo a estrutura pública do Aceternity 3D Globe: Terra esférica com textura e relevo, atmosfera azul, iluminação, rotação automática, zoom, arrasto, toque, marcadores frontais e painel externo ao canvas.
- O globo pausa fora da viewport ou com a aba oculta, respeita `prefers-reduced-motion`, possui fallback de WebGL e permanece em chunk lazy.
- `WorldMap` foi posteriormente refinado para a geometria real do Brasil via `@svg-maps/brazil`, com 27 estados/capitais e conexões animadas via `motion/react`. As conexões são declaradas como demonstração institucional.
- O tutorial usa overlay modal, bloqueia cliques externos, move-se entre as rotas corretas, aguarda o alvo, recalcula em resize/scroll/mutação, mantém o painel na viewport, prende o foco, aceita Escape e persiste versão, etapa, conclusão ou dispensa.
- Foi corrigida a API de onboarding que zerava `dismissed` quando recebia `completed: false`.

## Proveniência dos assets

- `earth-blue-marble.jpg`: cópia local do asset de exemplo `three-globe`, correspondente à composição NASA Blue Marble.
- `earth-topology.png`: cópia local do bump map de exemplo `three-globe`.
- O uso é factual, sem logotipo e sem alegação de endosso. A NASA informa que seu conteúdo visual e mapas de textura geralmente não estão sujeitos a copyright nos Estados Unidos, sujeito às diretrizes de mídia; a fonte deve ser reconhecida. O projeto `three-globe` é distribuído sob MIT.
- Referências consultadas: [Aceternity 3D Globe](https://ui.aceternity.com/components/3d-globe), [Aceternity World Map](https://ui.aceternity.com/components/world-map), [NASA Blue Marble](https://visibleearth.nasa.gov/images/57723/the-blue-marble), [NASA Images and Media Usage Guidelines](https://www.nasa.gov/nasa-brand-center/images-and-media/) e [repositório `three-globe`](https://github.com/vasturiano/three-globe).

## Evidências visuais

- Globo: `artifacts/adendo/globe-before.png` e `artifacts/adendo/globe-after.png`.
- Mapa: `artifacts/adendo/world-map-before.png` e `artifacts/adendo/world-map-after.png`; a evidência atual do mapa do Brasil está em `artifacts/brasil-auth/home-*.png`.
- Landing anterior: `artifacts/adendo/landing-before-desktop.png` e `artifacts/adendo/landing-before-mobile.png`.
- Landing atual: capturas em 375×812, 430×932, 768×1024, 1366×768 e 1920×1080 em `artifacts/adendo/landing-after-*.png`.
- Tutorial atual: `artifacts/adendo/tutorial-after-desktop.png` e `artifacts/adendo/tutorial-after-mobile.png`.
- Não havia captura histórica do tutorial antes da troca; não foi criada uma reconstrução artificial para representar o estado anterior.

## Validação

- Adendo direcionado: 7/7 cenários, incluindo cinco viewports, tema escuro, WebGL, mapa, rotas do tutorial, bloqueio do fundo, colisão móvel, Escape e persistência.
- Regressão ampla: 232/232 verificações em rotas públicas, Corretor, Imobiliária e House Flipper.
- Matriz premium: 15/15 verificações.
- Backend: 14 testes, 69 asserções.
- Frontend: 5/5 testes; build de produção concluído.
- Permissões e assinaturas: 23/23 verificações.
- `npm audit --omit=dev`: 0 vulnerabilidades.

Resultados estruturados: `artifacts/adendo/adendo-results.json`, `comprehensive-results.json` e `premium-results.json`.
