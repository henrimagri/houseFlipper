# QA — Onboarding, site do corretor e Kanban comercial

Data: 2026-10-20

## Escopo validado

- Onboarding do Corretor em tema escuro, sem cartões claros nem perda de contraste.
- Texto do tutorial sem endereço técnico.
- Editor do site com máscara automática de endereço, três modelos, cinco cores, tipografia, estilos de cabeçalho/cards/botões, conteúdo, visibilidade e ordem das seções.
- Vitrine pública em `/corretor/{nome-profissional}`, renderizando o tema salvo.
- Kanban comercial com sete etapas, arrastar e soltar, controles por teclado/botões e persistência no CRM.
- Layout desktop e mobile sem overflow global.

## Evidências

- `npm test`: 10/10 testes.
- `npm run build`: compilação TypeScript e Vite concluída.
- `php artisan test --compact`: 17 testes e 87 assertions.
- `node scripts/broker-site-kanban-qa.mjs`: 7/7 cenários visuais.
- Capturas e resultado: `docs/qa/artifacts/broker-site-kanban/`.

Nenhuma integração de pagamento aparece no editor ou na vitrine pública.
