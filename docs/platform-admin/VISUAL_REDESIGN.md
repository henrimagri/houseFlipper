# Redesign visual da central FlipControl

Data: 23/10/2026. Branch: `Correção-visua/proteção`.

## Escopo e sequência

O prompt pede começar pela Visão Geral, validar no navegador e somente depois aplicar o sistema visual às demais telas. Este checkpoint implementa a Visão Geral; **não representa o redesign integral concluído**. Backend, rotas, filtros, MFA e ações administrativas não foram alterados.

A referência disponível nesta solicitação é a descrição textual de uma interface escura, compacta, com bordas discretas e acentos roxos. A pasta do anexo contém somente `Texto colado.txt`; nenhuma imagem Quantix foi recebida neste anexo. Não houve cópia de marca, imagens ou números do exemplo.

## Implementação

- `PlatformOverview.tsx`: composição executiva, quatro métricas, área de atividade, distribuição de severidades, cards dos três produtos, atividade observada, receita e prontidão. Cards de produto contam **assinaturas**, não pessoas/contas únicas. O vínculo abre o módulo existente de assinaturas, sem alegar aplicar filtro automático por produto.
- Componentes reutilizáveis `PlatformCard`, `MetricCard`, `SectionHeader`, `EmptyState` e `OverviewSkeleton`.
- `platform-theme.css`: tokens locais, fundo `#070a10`, cards `#0d121d`, bordas `rgba(148,163,184,.12)`, texto `#f7f8fc`, secundário `#9ca8bd`, accent `#a68aff`. Raios de 18px, espaçamento de 16/20/24px e animação de skeleton respeitando redução de movimento.
- Recharts existente para área com grid horizontal discreto, tooltip escuro e pontos apenas no hover. Lucide existente para ícones. Nenhuma nova dependência, fonte remota ou import de Three.js.
- `PlatformWidgets.tsx` preserva sua exportação `Overview`, delegando a composição ao novo componente. Os demais widgets mantêm a apresentação anterior até o gate visual.
- `PlatformConsole.tsx` importa o CSS e ativa fundo, saudação com nome real e skeleton somente na Visão Geral. Busca, filtros, atualização, exportações e confirmação de ações continuam nos fluxos existentes.

## Fidelidade aos dados

Não há comparação histórica inventada, receita inferida de assinatura, presença online presumida ou declaração de plataforma protegida. Incidentes críticos abertos são explicitamente uma contagem **global**, pois esse é o contrato do endpoint. Distribuição dos sinais usa o período/escopo do endpoint. Ausência de dataset não vira zero; um único dia não gera tendência.

O overview não contém fonte para feed, saúde, latência p95, jobs em processamento ou trials próximos de expirar. Esses widgets não foram preenchidos com dados inventados nem novos endpoints foram adicionados nesta etapa. O indicador de prontidão continua sendo o checklist do servidor, sem certificação comercial.

## Responsividade e acessibilidade

Grade prevista: quatro métricas no desktop largo, duas abaixo de 1280px e uma abaixo de 640px; produtos em três colunas, uma em celular; gráficos com largura flexível. Links e controles recebem foco visível; skeleton tem status acessível e animação desativada com `prefers-reduced-motion`.

**Ainda não validado visualmente** em 1920×1080, 1440×900, 1280×800, 768px ou 390×844. CSS responsivo e SSR não comprovam ausência de overflow ou qualidade visual.

## Validação e captura

O navegador da ferramenta está disponível nesta sessão. Ao abrir, a página estava com erro de import dinâmico, após o servidor de desenvolvimento ter parado. Laravel em loopback8000 e Vite5173 foram reiniciados; recarregar abriu a tela real de login. O acesso à central aguarda autenticação/MFA do operador. Nenhuma credencial foi solicitada no chat, nenhuma sessão privada foi extraída e o gate não foi contornado.

Não existem capturas novas do dashboard neste checkpoint, nem comparação antes/depois na mesma resolução. As imagens anteriores em `evidence/platform-admin/` são históricas e não comprovam este redesign. O bloqueio atual é acesso autenticado à central, **não indisponibilidade do navegador**.

Verificação final: `npm run build` (TypeScript `tsc -b` seguido de Vite) e `npm test`, saída integrada0. Vite:3.414módulos,5min14s, heap limitado por comando a1536MiB. Frontend:58/58, zero falhas/skips,20.494,7395ms. Nenhum backend foi modificado; PHP não foi reexecutado nesta tarefa. `git diff --check` aprovado. Tentativas intermediárias foram interrompidas para validar a versão final integrada; não são aprovações adicionais.

Bundle por manifesto: entrada administrativa47.773→63.847bytes; grafo estático JS incluindo dependências compartilhadas780.743→786.221bytes (+5.478). A composição aumentou a entrada própria e redistribuiu os módulos de gráficos; não houve redução do bundle. CSS administrativo emitido5,70kB/1,72kBgzip. Nenhuma chave estática Three/GLTF/MapCanvas/globo no grafo da central. A rota administrativa continua lazy em App. Evidência sanitizada em `evidence/platform-admin/visual-overview-bundle-audit.json`. Medição estática não equivale à inspeção de rede ou desempenho no navegador. Warnings existentes de Three746,49kB e MapCanvas1.077,85kB preservados.

## Pendências para conclusão

1. Autenticar operador de QA e inspecionar a Visão Geral real em desktop/mobile, registrar capturas e corrigir problemas observados. O antes desta mudança não foi capturado; não substituir por mockup nem atribuir screenshot antigo a esta sessão.
2. Após o gate visual, aplicar o sistema ao shell (sidebar72/232px persistente, ícones, topbar, CtrlK), filtros compactos/avançados e atalhos de período.
3. Refinar tabelas, badges, perfil360, drawer de incidentes, Security Center, IA, jobs, health, receita e demais estados. Preservar todos os módulos/ações, sem criar destino ou métrica sem fonte.
4. Registrar screenshots reais de overview, cliente, segurança, requests, IA, jobs e mobile; auditar bundle final.

O workflow preexistente `.github/workflows/platform-ci.yml` permanece fora deste checkpoint, preservado localmente, com a restrição de autenticação GitHub documentada na Fase2.

## 23/10/2026 — Diagnóstico real do acesso

Após autorização do proprietário para entrar, o formulário já preenchido autenticou normalmente a persona Combo; `/platform` retornou ao início por ausência de privilégio de plataforma. Em seguida, o login da conta administrativa local já definida no seeder funcionou. Não foram criadas contas, alteradas senhas ou extraídas sessões.

O bloqueio real do admin local é e-mail não verificado; também não há MFA confirmado. Inspeção local restrita a booleanos confirmou existência, conta ativa, papel administrativo, e-mail não verificado e MFA não cadastrado. Nenhum segredo, hash ou token foi impresso. A resposta de autorização foi validada na tela real.

Correção de UX em `PlatformMfaGate.tsx`: apresentar o motivo devolvido pelo servidor em vez de esconder a recusa atrás de erro genérico; diferenciar timeout; sem status confirmado não afirmar que o cadastro do autenticador é a próxima etapa. A mensagem observada após reload foi **“Confirme seu e-mail para acessar a central.”** Captura real saneada em `evidence/platform-admin/admin-access-email-required.jpg`, sem formulário de senha, e-mail, token, chave TOTP ou código de recuperação.

Verificação deste ajuste: `npm test`,58/58,482,9734ms; `npm run build`,TypeScript/Vite aprovados,3.414módulos,12,14s, saída0; `git diff --check` aprovado. Warnings3D/mapa preexistentes mantidos. Backend e decisões de autorização preservados. Isso valida somente a correção da tela de acesso, **não o gate visual da Visão Geral**.

Solicitada decisão entre usar conta administrativa já verificada ou autorizar um operador temporário de QA local com limpeza ao final. O operador temporário ainda não foi criado; o cadastro de um novo autenticador deverá ser realizado pelo proprietário no navegador. Não houve envio externo de e-mail, verificação manual de conta existente ou alteração de MFA para liberar acesso.

### Autorização e preparação do operador temporário

Em seguida, o proprietário autorizou expressamente o operador temporário. Provisionado somente em APP_ENV=local/SQLite, com nome de QA, endereço aleatório example.test, senha aleatória e e-mail verificado **exclusivamente como fixture local**, sem alegar entrega/confirmação por e-mail real. Não houve alteração da conta Combo ou do admin existente. Credenciais e registro de lifecycle ficam em arquivo ignorado de storage/logs, fora do Git e dos relatórios; remover com o operador ao finalizar o QA. Operador temporário local ID30, sem assinatura dos produtos e sem MFA cadastrado.

Login normal pelo formulário aprovado; preferência de lembrar e-mail desmarcada. `/platform` abriu **“Cadastre seu autenticador”**, sem o bloqueio de e-mail anterior. Formulário preparado com a senha atual do próprio operador, sem exibi-la em logs/chat. Cadastro de novo fator entregue ao proprietário; o agente não clicou em Gerar chave nem leu segredo TOTP/recuperação. Evidência real anterior ao preenchimento do campo: `evidence/platform-admin/qa-operator-mfa-handoff.jpg`. Nenhuma captura da Visão Geral ou aprovação de seu gate nesta etapa.

### Pedido posterior: dispensa do MFA exclusivamente para QA local

O proprietário pediu remover essa exigência para a conta temporária neste PC. Implementada exceção por identidade exata (ID+fingerprint), local/SQLite e expiração obrigatória; configurada por24h no `.env` ignorado. Backend mantém login, token próprio, conta ativa/verificada, papel administrativo, senha/confirmar/auditar ações. Demais contas/produção não são dispensadas. Detalhes e reversão em `MFA.md`.

Após reload, a Visão Geral real abriu sem cadastro TOTP e com banner explícito de QA local. Captura `evidence/platform-admin/local-qa-mfa-exemption-access.jpg`, sem segredos ou dados pessoais. Durante a verificação o proprietário navegou na sua aba; a evidência foi obtida em aba separada, encerrada depois. Viewport temporário restaurado. Isso comprova o acesso e a renderização observada, mas não conclui o gate completo de cinco resoluções, a sidebar recolhível nem o redesign das demais páginas.


## 23/10/2026 — Continuação do redesign: shell e módulos reais

O gate inicial foi executado na Visão Geral autenticada em 1920×1080, 1440×900, 1280×800, 768×1024 e 390×844. As capturas overview-gate-* registram o estado anterior ao novo shell nesta execução, não um mockup nem o estado anterior de outro commit. O acesso usa exclusivamente o operador temporário local autorizado, com a dispensa de MFA já documentada; nenhuma conta existente recebeu dispensa.

### Alterações implementadas

- Shell único para todos os destinos administrativos existentes. Sidebar com ícones, grupos Operação/Segurança/Tecnologia/IA e Simulador/Financeiro/Sistema, 232 px expandida e 72 px recolhida. Preferência local persistida e confirmada após recarga. Navegação e conteúdo possuem rolagem independente. Nenhum destino GPU/configuração inexistente foi criado.
- Topbar com breadcrumb, busca real, Ctrl+K, atualização, hostname da interface e operador real. O indicador de tempo mede recebimento da consulta, não atualidade da fonte ou presença online.
- Filtros compactos, datas legíveis, atalhos inclusivos Hoje/7/30/90 dias e filtros avançados em disclosure. Aplicar/Limpar e paginação permanecem no servidor. Cada resposta fica vinculada à seção, filtros e página que a originaram; uma composição nova não recebe o payload da consulta anterior.
- Primitives sem Recharts, tabela semântica com rolagem interna, badges HTTP/severidade/saúde, skeletons e estados vazios compartilhados. Tema aplicado também aos formulários e ações existentes, não somente à Visão Geral.
- Perfil 360 em drawer, cabeçalho real e contagens explicitamente limitadas aos arrays disponíveis; nove abas preservadas, incluindo Financeiro/Suporte. Home/End e Escape exercitados no navegador.
- Funil usa somente as etapas e valores devolvidos pelo servidor. Ausência de visitantes/checkout pago continua Não medido. Segurança e jobs resumem somente a página carregada, sem apresentar as contagens como totais do período.
- IA possui métricas reais de conversas/mensagens, metadados de ferramentas e configuração identificada como configuração. Jobs mostram método efetivo, duração e progresso somente quando válidos; não inventam VRAM, fotos ou aprovação geométrica. Não houve job ativo do operador para QA de progresso em execução.
- Health distingue online, offline, timeout, desabilitado e não configurado. Billing sem evidência financeira apresenta estado vazio explícito; MRR/ARR globais continuam não medidos. Prontidão conserva os critérios e pesos do servidor, sem certificação falsa.
- Drawer de incidentes e busca compactados. O QA encontrou que o formulário inicializava investigating mesmo em falso positivo; agora preserva status/resolução do registro aberto. Nenhum incidente existente foi atualizado durante essa verificação.
- O QA também registrou security.map is not a function durante recarga. O contrato PHP inspecionado devolve listas; a origem exata daquele payload não foi isolada. O componente agora distingue lista ausente/malformada de lista vazia, informa indisponibilidade e conserva os outros cards. Regressão cobre listas malformadas; a recarga normal voltou a funcionar. Não se alega ter corrigido uma origem de dados ainda não demonstrada.
- Leituras concorrentes de status MFA são coalescidas no componente; invalidação e alterações exigem leitura nova e descartam respostas antigas. Mantidos timeout, autenticação, autorização e contratos de confirmação. Esse ajuste não é apresentado como solução geral para a lentidão local.
- Gráfico da Visão Geral continua isolado em import dinâmico. Desativada a animação inicial da área para renderização imediata dos dados e captura estável em aba em segundo plano. Microinterações e skeleton respeitam redução de movimento.

### Evidências de navegador

Arquivos em evidence/platform-admin/. Todas as imagens são capturas reais de 23/10/2026; métricas podem variar entre consultas e incluem fixtures locais de QA. Clientes/perfil/requests foram restritos ao operador 30; não há senhas, TOTP, recuperação, tokens ou conteúdo de conversas nas imagens.

| Caso | Evidência / observação |
| --- | --- |
| Visão Geral, cinco tamanhos | central-overview-{1920,1440,1280,768,390}.jpg; largura do documento igual ao viewport. Em 1280 px, corrigida a regra sobrescrita e confirmadas duas colunas. |
| Recarga da Visão Geral | central-overview-reloaded.jpg; consulta normal após falha de outro módulo. |
| Sidebar | central-sidebar-collapsed.jpg; 72 px medidos e preferência preservada após recarga. |
| Clientes / perfil | central-clients-qa.jpg e central-profile-qa.jpg; filtro do operador e navegação de abas por teclado. |
| Trials / conversão | central-trials-qa.jpg e central-funnel-qa.jpg; estado vazio legítimo e checkout indisponível, respectivamente. |
| Segurança / incidente | central-security-qa.jpg e central-incident-qa.jpg; fixture informativa já existente, somente leitura. |
| Requests | central-requests-qa.jpg; paginação 1→2 confirmada, metadados do operador, sem payload privado. |
| IA / jobs | central-ai-qa.jpg e central-jobs-qa.jpg; metadados e ausência de jobs no escopo de QA. |
| Health / billing / prontidão | central-health-qa.jpg, central-billing-qa.jpg e central-readiness-qa.jpg. Timeouts e bloqueios reais não foram ocultados. |
| Busca global | central-search-qa.jpg; busca por ID30 devolveu o operador real, demais grupos vazios. |
| Mobile | central-mobile-menu.jpg e central-mobile-table-qa.jpg; drawer de 260 px, fundo inert, Escape/foco de retorno e fechamento ao navegar. Tabela: viewport390, contêiner350, conteúdo971, scroll interno605; documento390. |
| Erro de consulta | central-timeout-state-1440.jpg, estado real que permite tentar novamente. |

Em 390 px, a captura fullPage apresentou erro/imagem vazia na ferramenta. As imagens mobile aprovadas são capturas do viewport efetivamente inspecionadas; a imagem vazia foi substituída, não usada como evidência. Capturas desktop foram conferidas nas dimensões esperadas. Os screenshots de módulos registram o QA progressivo; o estado após recarga demonstra a composição final da Visão Geral.

### Verificação integrada

- npm test: 65/65, zero falhas/skips, 467,884 ms no último comando integrado.
- npm run build: TypeScript e Vite aprovados, 3.418 módulos, 10,14 s, exit 0. Heap limitado somente no comando a1536 MiB. Warnings preexistentes de chunks Three.js/MapCanvas acima de500 kB permanecem; avisos de useLayoutEffect vêm do harness SSR com MemoryRouter, não de uma aplicação SSR entregue.
- git diff --check: exit0; avisos de normalização CRLF/LF não são erro de whitespace.
- Nenhum PHP/contrato/backend foi alterado neste marco; a suíte PHP não foi reexecutada como se fosse uma validação nova desta entrega.
- Testes adicionais cobrem períodos inclusivos/leap day, estados ausentes versus zero, HTTP/health, resumos paginados, progresso inválido, funil, destinos/rota ativa, preservação do incidente e listas inválidas da Visão Geral.

### Bundle: medição estática, não benchmark de rede

Artefato central-redesign-bundle-audit.json, a partir do manifesto Vite final, comparado a visual-overview-bundle-audit.json (04df906). Entrada própria: 63.847→57.185 bytes. Grafo estático JS da Central, incluindo dependências compartilhadas: 786.221→397.677 bytes. Overview é entrada dinâmica; nenhuma chave estática Three/GLTF/MapCanvas/globo/generateCategoricalChart foi encontrada. CSS administrativo:17.689 bytes /4.121 gzip.

O grafo completo da Visão Geral, ao carregar os gráficos, é maior que o grafo inicial sem gráficos (cerca de802 kB). Portanto, não se afirma redução de metade no download total da Visão Geral nem melhora de tempo de resposta da API. A melhoria demonstrada é adiar os gráficos nos módulos que não os utilizam. O manifesto pode nomear a entrada dinâmica como _PlatformConsole-<hash>.js; a auditoria identifica a entrada por nome/isDynamicEntry, sem presumir uma chave estável pelo caminho fonte.

### Limites e continuidade

O QA exercitou os14 grupos de telas pedidos e a navegação descrita, não todas as combinações de dados/ações destrutivas. Tooltip por hover, exportação de dados privados, todos os fatores MFA e alterações operacionais reais não receberam uma aprovação manual nova. Contratos e confirmações foram preservados; não foram simulados pagamentos, incidentes críticos, jobs ou métricas para preencher telas.

A máquina apresentou timeouts de15s, especialmente durante pressão de recursos; causa geral não isolada. Health observou Laravel/banco online, FastAPI/Qdrant em timeout e fila offline; Ollama esteve online na consulta inicial e em timeout na captura posterior; isso é snapshot, não certificação de uptime. Ledger/checkout real, MySQL, MFA dos operadores definitivos, backups e densificação CUDA continuam com as pendências anteriores.

Operador temporário e arquivo privado de lifecycle permanecem ignorados durante o QA em andamento; a dispensa tem expiração obrigatória e deve ser removida junto com a fixture ao encerrar o QA. Não houve inclusão de .env, credenciais, dataset, pesos ou GLB no marco. O workflow local preexistente .github/workflows/platform-ci.yml permanece fora do commit.

Próximo trabalho autorizado: iniciar o produto Imobiliária por inventário do CRM existente e uma fatia vertical, sem duplicar entidades nem reabrir a ponte automática ChatGPT↔Codex. O novo prompt não foi confundido com conclusão do ERP/WhatsApp/financeiro.
