# Integração Aceternity UI — 3D Globe

## Verificação inicial

- TypeScript: presente e configurado em `frontend/tsconfig.json`.
- Tailwind CSS: versão 4.3.3, carregada com `@import 'tailwindcss'`.
- shadcn/ui: o projeto era compatível pelo alias `@/*`, mas ainda não possuía `components.json` nem a estrutura padrão `components/ui`.

## Implementação

- Adicionado `frontend/components.json` para uso do CLI shadcn em Vite, TypeScript e Tailwind v4.
- Adicionado o helper `cn` em `src/lib/utils.ts` com `clsx` e `tailwind-merge`.
- Instalado o componente-base do registro em `src/components/ui/3d-globe.tsx`.
- Instalado e conectado o demo em `src/components/3d-globe-demo.tsx`.
- A Home importa o novo demo de forma lazy.
- O componente foi adaptado para as 27 capitais brasileiras, curvas nacionais demonstrativas, foco inicial no Brasil, texturas locais, fallback WebGL, redução de movimento e pausa fora da viewport.
- O antigo componente duplicado `src/ui/Globe3D.tsx` foi removido.

Fonte: `https://ui.aceternity.com/registry/3d-globe-demo.json`.

## Validação

- Build TypeScript/Vite: aprovado.
- Testes frontend: 5/5.
- QA Brasil/autenticação: 21/21.
- Matriz premium: 15/15.
- QA visual e tutorial: 7/7.
- Auditoria npm: 0 vulnerabilidades.
- Inspeção visual: globo visível, Brasil centralizado, painel sem cortes e sem overflow nas cinco viewports.
## Refino de interação e temas

- O enquadramento inicial foi recalibrado para centralizar o Brasil e permanece parado até uma ação do usuário.
- A rotação está disponível apenas por arraste; o zoom continua bloqueado para preservar o enquadramento.
- Os marcadores HTML deixaram de usar transformação 3D; cada alvo agora mede 32×32 px e acompanha a posição visual do ponto.
- Os tooltips passaram a ser renderizados em coordenadas de tela, com tipografia nítida, fundo sólido e largura compacta.
- O palco do globo permanece escuro nos temas claro e escuro para preservar contraste.
- O halo claro foi substituído por atmosfera reduzida e uma malha ciano translúcida que representa um escudo de proteção.
- Evidências: `artifacts/globe-refinement/globe-light-hover.png`, `globe-dark-hover.png` e `globe-refinement-results.json`.
- O mapa do Brasil agora mostra tráfego contínuo por traços luminosos nas 27 conexões, sem pontos brancos móveis. As capitais usam marcadores ciano compactos e somente a capital ativa recebe um anel discreto.
- QA focal: 2/2, incluindo posição estática e rotação manual; QA visual/tutorial: 7/7; QA responsivo completo: 21/21.
