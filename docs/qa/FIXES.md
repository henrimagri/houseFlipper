# Correções verificadas — 2026-10-19

## App shell e ajuda

- **Problema:** três cards de produto permaneciam na sidebar; o tutorial usava backdrop modal e bloqueava a página.
- **Causa:** `ProductSwitcher` era renderizado por `ProductSidebar` e `ProductTour` ocupava toda a viewport com `aria-modal` e máscara.
- **Correção:** seletor acessível dentro do menu do avatar, menu móvel responsivo, sidebar apenas com navegação e painel de ajuda não modal com alvo, rolagem, retomada e progresso por produto.
- **Arquivos principais:** `frontend/src/components/ProductLayout.tsx`, `ProductSidebar.tsx`, `ProductSwitcher.tsx`, `ProductTour.tsx`.

## Mapa

- **Problema:** o browser bloqueava OpenFreeMap e o 3D usava uma source fixa mesmo quando o estilo fornecia outra.
- **Causa:** CSP sem os hosts do mapa e camada `fill-extrusion` ligada à source `openmaptiles` por suposição.
- **Correção:** CSP explícita para OpenFreeMap/Nominatim, detecção da source e source-layer reais, fallback 2D informado, erro de tiles visível, resize por `ResizeObserver` e cache de geocodificação por 30 minutos.
- **Segurança:** MapLibre atualizado de 5.x para 6.15.0 por vulnerabilidade XSS conhecida; Vite exclui o worker ESM do prebundle.
- **Arquivos principais:** `frontend/security-headers.ts`, `frontend/vite.config.ts`, `frontend/src/maps/MapCanvas.tsx`, `frontend/src/pages/MapaInteligente.tsx`, `app/Services/Maps/GeocodeService.php`.

## Configurações e sessões

- **Problema:** sessões eram uma lista longa e a única ação coletiva encerrava inclusive a sessão atual.
- **Correção:** navegação por seções, largura controlada, sessão atual em destaque, outros dispositivos paginados em blocos de cinco e endpoint para encerrar somente as outras sessões.
- **Arquivos principais:** `frontend/src/pages/Configuracoes.tsx`, `app/Services/AuthService.php`, `app/Http/Controllers/Api/AuthController.php`, `routes/api.php`.

## CRM, atendimentos e site

- **CRM:** criação, movimentação, persistência, histórico, edição e arquivamento estão ligados ao banco. Mudanças usam `lock_version` e conflito retorna 409.
- **Atendimentos:** criação, anotação, mensagem interna, prioridade, encerramento, reabertura e conversão para negócio foram exercitados pela API real.
- **Site:** salvar, publicar em `/i/{slug}`, carregar a página pública e despublicar foram exercitados no navegador.
- **Arquivos principais:** `AgencyWorkspaceController.php`, `AgencyCrmService.php`, `CrmDealDetail.tsx`, `CrmKanban.tsx`, `Atendimentos.tsx`, `Site.tsx`.

## Compilação e verificador de produtos

- Corrigida a tipagem da source-layer do MapLibre que impedia o build.
- `suite:verify` agora limpa entitlements residuais de execuções anteriores e é repetível.

## Cadastro, perfil e contratos

- Cadastro ampliado para cinco etapas com telefone, CEP, endereço completo, busca de CEP, consentimento e confirmação de e-mail.
- Configurações agora salvam foto pública por link, nome, telefone, preferências de notificação e permitem alterar a senha com confirmação da senha atual.
- Contratos da imobiliária deixou de ser placeholder e recebeu tabela tenant-scoped, API CRUD, busca, filtros e formulário responsivo.

## Leilões e diálogos

- Catálogo de leilões recebe somente arquivos oficiais CAIXA/VendasGov, guarda hash e histórico, deduplica e filtra URLs por domínio.
- `DialogShell` unifica foco, Escape, overlay, retorno de foco, rolagem e comportamento mobile/desktop.
- Clientes, CRM, imóveis, confirmações e contratos foram migrados para o padrão comum.

## Responsividade e temas

- Tokens semânticos de tema claro/escuro foram aplicados no shell e componentes centrais.
- Corrigido overflow da página de despesas em 375 px e a navegação canônica para projetos.
- Site do corretor recebeu preview responsivo em três larguras.
