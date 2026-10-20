# Auditoria UI/UX — 2026-10-19

- Sidebar azul-marinho estável no desktop, recolhível com títulos, e drawer no mobile.
- ProductSwitcher compacto no cabeçalho; status de assinatura e link “Gerenciar meus produtos” ficam no menu.
- Ajuda guiada não bloqueia cliques, não usa backdrop, destaca o alvo e vira painel inferior rolável em mobile.
- Configurações usam cabeçalho, atalhos de seção, cards compactos e paginação progressiva de dispositivos.
- Dashboards dos três produtos usam KPIs e estados vazios vindos das APIs; não foram adicionados números fictícios.
- CRM mantém quadro horizontal deliberado por colunas; a página inteira não apresentou overflow nos quatro viewports.
- Atendimentos usa três painéis no desktop e alternância por painel no mobile.
- Editor do site oferece preview desktop/tablet/mobile e publicação real.
- Mapa informa disponibilidade 3D e usa fallback 2D quando a camada `building` não existe.

As capturas em `docs/qa/artifacts/` e os resultados em `docs/qa/browser-results.json` sustentam estas observações.
