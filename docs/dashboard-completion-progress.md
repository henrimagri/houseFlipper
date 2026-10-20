# Finalização visual e funcional

Atualizado: 2026-10-19

Imagens de referência 1–3 **não estão no workspace**. Comparação visual feita contra a descrição (sidebar marinho, conteúdo claro, cards, mapa em destaque), não pixel a pixel.

## Concluído nesta sessão

### Design system / shell
- Sidebar `#0b1b33`, ícones Lucide, grupos, recolher, rodapé de assinatura.
- Topbar: busca, localização, notificações, ajuda (Rever tutorial), tema, avatar.
- ProductSwitcher em cards com ícone, descrição e status vindo do catálogo (`trial` = teste).
- Arquivos: `ProductLayout.tsx`, `ProductSidebar.tsx`, `ProductSwitcher.tsx`, `lib/navIcons.tsx`.

### Dashboards
- House Flipper: KPIs reais, mapa embed, gráfico, oportunidades, projeto em destaque.
- Corretor: KPIs comerciais, mapa, funil derivado dos kpis, imóveis/captações/visitas.
- Imobiliária: KPIs, funil, origem, mapa, ranking `by_agent` (critério: negócios atribuídos).
- `DashboardMap.tsx` consome `/maps/listings`.

### Tutorial
- Overlay próprio (sem Joyride). IDs alinhados a `OnboardingDefinition`.
- Persistência `PUT /onboarding/{product}`. Reinício via `completed: false`.
- Não redireciona para o dashboard ao abrir outra rota; só navega no Próximo/Voltar.
- Checklist antigo vira detalhe em `/app/onboarding/:product`.

### CRM
- Coluna `perdido` no enum `DealStage`.
- Contagem, busca, modal, revert em falha de persistência, atalhos de etapa.
- Card criado na conta `imobiliaria@` visível no funil (ranking passou a 2 negócios).

### Atendimentos
- Filtros de caixa, prioritários, mobile 3 painéis, prioridade/status/encerrar/reabrir.
- WhatsApp permanece not_wired.

### Site imobiliária
- Causa raiz: `/i/:slug` usava `PublicBroker`. Agora `PublicAgency` + `/public/agencies/{slug}`.
- Editor por blocos, preview desktop/tablet/mobile, rascunho/publicar/despublicar.
- `BrokerThemeSanitizer` aceita sections/headline; `published` explícito.

### Mapa
- `resize()` + ResizeObserver (mapa em branco por container 0px).
- Clique no geocode voa até o ponto; raio km; lista sincroniza marcador.
- Leilões continuam `available:false`. 3D = fill-extrusion se houver layer building.

### Financeiro
- Redirect `/analises-financeiras/:id` → rota autenticada House Flipper.
- CentralFinanceira já recalcula/salva/cenários. Sem botão PDF morto.
- Auditoria profunda de cada controle: parcial (não percorrida clique a clique nesta sessão).

## Testes
- `vite build`: 2392 módulos, OK. `tsc -b` travou neste ambiente; bundle Vite passou.
- `php artisan suite:verify`: 2 falhas pré-existentes (`A bloqueia house_flipper`, `A não usa reforma`) — não alterado entitlement.
- Browser: dashboard House Flipper (Admin Demo), dashboard + CRM + site (Imobiliária Demo).
- Sem Playwright no package.json.

## Pendências
- Confirmar persistência do editor após Pular (clique interceptado pelo tour).
- Tiles OpenFreeMap visíveis: MapLibre inicializa (zoom), pontos 0 quando listing sem lat/lng.
- Tours contextuais por página (CRM/publicação/atendimento/projeto).
- Relatórios corretor/imobiliária ainda reusam dashboards.
- `suite:verify` A/reforma — investigar seed reuse, não afrouxar auth.

## Próxima etapa
Publicar site, abrir `/i/imobiliaria-demo-imob`, mover card CRM + reload, abrir `/analises-financeiras/1` autenticado, mapa com busca São Paulo.
