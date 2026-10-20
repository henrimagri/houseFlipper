# Resultados de testes — 2026-10-19

## Automatizados

| Comando | Resultado |
|---|---|
| `php artisan test` | 12 testes, 57 asserções, todos passaram |
| `npm test` | 3 testes, todos passaram |
| `npm run build` | passou, 2393 módulos transformados |
| `php artisan suite:verify` | 23 verificações passaram |
| `npm audit --omit=dev` | 0 vulnerabilidades após atualização do MapLibre |
| lint PHP dos serviços/controladores alterados | sem erros de sintaxe |

O executável global do Composer não está disponível neste ambiente; `composer audit` não foi executado. O `composer.lock` não foi alterado por esta entrega.

## Browser real

O script `frontend/scripts/master-qa.mjs` usou Chrome instalado, backend Laravel e frontend Vite locais. Resultado: 17 verificações, nenhuma falha de console, rede, HTTP ou overflow.

- Viewports: 375×812, 768×1024, 1366×768 e 1920×1080.
- Dashboards: Corretor, Imobiliária e House Flipper.
- Rotas: configurações, CRM, atendimentos, editor do site, mapa e análise financeira `1`.
- Mapa: canvas MapLibre presente, tiles carregados, nenhum request falhou; repetido após upgrade para 6.15.0.
- CRM: POST 201, mudança de etapa 200, persistência e histórico confirmados.
- Atendimento: POST 201; mensagem, encerramento e reabertura 200; estado persistido.
- Site: publicação 200, página pública 200 e despublicação 200.

Dados estruturados: `docs/qa/browser-results.json` e `docs/qa/comprehensive-results.json`.

## Regressão ampliada

- Resultado final: 232 verificações, 0 falhas.
- 112 rotas frontend e 185 rotas API inventariadas.
- Matriz de páginas públicas e dos três produtos em 375 px, desktop claro e desktop escuro.
- Capturas adicionais de cadastro, assinaturas, sites, dashboard e leilões em cinco larguras.
- Verificação específica garante zero ProductSwitcher visível antes de abrir o avatar e exatamente um dentro do menu.

## Screenshots

Os arquivos estão em `docs/qa/artifacts/`, incluindo:

- `dashboard-imobiliaria-{375x812,768x1024,1366x768,1920x1080}.png`
- `dashboard-corretor-{375x812,1366x768}.png`
- `dashboard-house-flipper-{375x812,1366x768}.png`
- `crm-1366x768.png`
- `atendimentos-1366x768.png`
- `site-imobiliaria-1366x768.png`
- `mapa-imobiliaria-1366x768.png`
- `configuracoes-1366x768.png`
- `analise-financeira-1366x768.png`
