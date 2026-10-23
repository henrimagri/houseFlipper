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
