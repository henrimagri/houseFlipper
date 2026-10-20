# Auditoria visual

Data: 2026-10-19.

## Cobertura

- 112 rotas explícitas inventariadas; matriz automatizada com páginas públicas, conta, Corretor, Imobiliária e House Flipper.
- Viewports usados: 375×812, 430×932, 768×1024, 1366×768 e 1920×1080.
- Temas claro e escuro verificados nas rotas autenticadas; capturas em `docs/qa/artifacts/comprehensive`.
- Verificações: overflow horizontal, erros JavaScript, respostas HTTP 5xx, carregamento preso e abertura do menu de perfil.

## Correções aplicadas

- Cores de canvas, superfície, texto, borda, foco e sidebar passaram a usar tokens semânticos em claro/escuro.
- ProductSwitcher removido do cabeçalho/sidebar e colocado no menu do avatar.
- Despesas recebeu contenção de largura, botões empilháveis e tabela com rolagem interna no mobile.
- Formulários extensos, tabelas e previews receberam limites responsivos.
- Site do corretor ganhou preview em desktop, tablet e celular; site da imobiliária mantém edição e preview lado a lado.

## Resultado

A regressão visual final está registrada em `comprehensive-results.json`: 232 verificações e 0 falhas. Rotas dinâmicas sem registro correspondente exibem estado 404 próprio, em vez de quebrar a aplicação.
