# Auditoria técnica — relatório parcial consolidado

Data: 19/10/2026. Estado: EM ANDAMENTO. Aplicação ainda não aprovada para produção.

## Evidência atual

- Suíte PHP: 313 testes aprovados, 2.793 asserções. Baseline: 20 testes/89 asserções.
- IA/avaliação: 14 testes específicos, 30 asserções. Versão comparables-v3, baseada em regras e comparáveis.
- Build TypeScript/Vite aprovado com 2.314 módulos e 77 testes de frontend aprovados. As páginas usam chunks sob demanda; entrada 269,81 kB e maior chunk 421,58 kB, sem alerta de 500 kB.
- Composer audit repetido em 18/10/2026 e npm audit preservado de 17/10/2026: nenhum advisory, pacote abandonado ou vulnerabilidade conhecida; npm contabilizou 249 dependências. O `composer.json` também passou em validação estrita. Há duas atualizações diretas de versão principal disponíveis (`phpunit` 13 e `spatie/laravel-permission` 8), sem advisory associado; exigem migração deliberada em vez de atualização automática. Evidências em `composer-audit-current.json`, `composer-outdated-current.json`, `composer-tool-current.json`, `npm-audit-current.json` e `dependency-audit-current.json`. Não equivalem a certificação contínua.
- Vinte e cinco cenários de concorrência real em MariaDB isolado passaram: os nove fluxos financeiros/tarefa/etapa anteriores e dezesseis disputas de criação/atualização contra exclusão de pais, cobrindo os dois ordenamentos. Cinquenta requisições/processos concorrentes; servidores de teste encerrados. Aplicação principal usa SQLite.
- Harness HTTPS/cURL real local aprovado: 4096 bytes aceitos, anúncio de 65536 bloqueado antes do corpo e chunked interrompido após 4352 bytes. Evidências JSON preservadas; servidor temporário encerrado.

## Problemas e correções

184 identificadores de bugs estão documentados em QA_REPORT.md, abrangendo segurança, lógica, funcionalidades, interface e qualidade da automação. Há 185 seções de ocorrência porque um identificador possui atualização histórica; não apresentar esses números como vulnerabilidades. A classificação final por severidade será reconciliada na revisão final. BUG 13 (responsividade) e BUG 14 (demonstrações/ações incompletas) continuam parcialmente corrigidos; os outros registros descrevem correções e respectivos limites de evidência.

Proteções implementadas incluem isolamento por tenant, conta/empresa ativa, expiração/revogação de tokens, limites de acesso, autorização de escrita ITBI, campos aninhados permitidos, erros externos neutros, validação de planilhas e headers de API. Integridade melhorada com transações financeiras, recálculos, estados/exclusões, identidade de ITBI e rollback de arquivos novos.

BUGs 43–46 corrigem evidências temporais inválidas, precisão de prédio sem número, erro de raio no SQLite e contagem de outliers descartados. A avaliação continua heurística; não é probabilidade calibrada nem garantia de valor de mercado.

## Evoluções solicitadas

- Tela 404 própria e ErrorBoundary implementados; 404 de `/mensagens` revalidada em 375 px sem overflow, e navegação de retorno testada. Injeção de falha inesperada ainda pendente.
- Lista explícita de cidades/bairros com seleção por mouse/teclado e busca sem acentos, validada na UI. Cobertura geográfica da tabela ainda parcial.
- Varredura responsiva real passou em 16 rotas e 375/768/1440 px (48 combinações), sem overflow horizontal, alertas visíveis ou erros/avisos de console nos estados vazios. Tabelas e formulários extensamente populados continuam pendentes.
- Tema claro/escuro/sistema com persistência. Troca e recarga testadas; painel, busca, login, configurações, menu e 404 renderizados. Mudanças reais da preferência do sistema e sincronização entre abas têm listeners com cleanup e regressão. Revisão formal de contraste e todos os estados visuais permanecem pendentes.
- Cadastro com endereço/CEP e confirmação assinada implementado, incluindo expiração e limite de reenvio. Fluxo fictício criado/confirmado pela interface; CEP real consultado com TLS verificado. Cadastro revalidado em 375/768/1440 px, com 13 controles acessíveis, feedback de CEP incompleto e console limpo.
- Resend escolhido e preparado. Transporte simulado validado; chave/remetente ainda ausentes e entrega externa não comprovada. Guia em RESEND_SETUP.md.
- IA v3 com critérios locais, temporais e geográficos melhorados. Leitura agregada local continua mostrando zero transações ITBI; não houve treinamento de modelo estatístico nem benchmark real. Ver AI_EVALUATION.md.

## Cobertura efetiva

Laravel/PHP, React/TypeScript, SQLite, Sanctum, Spatie, requests/policies/controllers, serviços financeiros, importação CSV/ODS/XLSX e parte dos provedores. Matriz em SECURITY_MATRIX.md; testes negativos incluem anonimato, isolamento entre contas, mutações alheias, suspensão, expiração, tipos, rollback e limites específicos.

A navegação e interações registradas não comprovam CRUD completo, acessibilidade ou todos os estados de rede em todos os módulos. Tarefas e Calendário receberam fluxos CRUD completos com fixtures, viewport móvel, tema escuro e falha de rede simulada. A rodada atual de IA foi executada em SQLite; resultados financeiros anteriores de concorrência MariaDB não comprovam compatibilidade dos novos critérios de avaliação.

## Trabalho ainda obrigatório

- Completar casos negativos por endpoint e evidência individual da matriz; segunda revisão de todas as mudanças.
- CRUD visual, botões, filtros, paginação, foco/teclado, viewports e estados de rede por módulo; corrigir demos e ações incompletas preservando funções.
- Importação após interrupção abrupta, atualização remota, arquivos próximos dos limites e formatos amplos. A concorrência do mesmo lote foi comprovada no MariaDB; isso não prova recuperação de queda de host.
- Fotos: usuário escolheu manter públicas por link; revisar ciclo de exclusão e possíveis órfãos históricos. Entrega pelo servidor local corrigida e testada no BUG 47.
- Integrações/scraper: egress/DNS, redirects/downloads, isolamento, proxy de deploy e capacidade. Sandbox local não prova toda a integração.
- Quedas abruptas do processo/host e capacidade sob volume representativo ainda precisam de ambiente de produção; as disputas transacionais mapeadas possuem prova multiprocesso em MariaDB.
- IA: dados reais autorizados, equivalência de áreas/tipos, deduplicação entre fontes, formatos amplos de endereço, desempenho em volume e compatibilidade de produção. Treino/validação/teste e métricas fora da amostra continuam pendentes.
- Entrega Resend com chave/remetente configurados e recebimento em caixa de teste.
- Produção: HTTPS, debug, cookies, CORS/CSRF conforme arquitetura, aplicação dos headers no proxy/CDN escolhido, logs, permissões, backup e monitoramento.

## Avaliação de prontidão

Os checks listados como executados passaram. Cenários ausentes/limitados não são considerados aprovados. Há módulos com dados demonstrativos e ações incompletas, além de integrações e deploy ainda não validados; produção não foi aprovada. Não há alegação de conformidade ASVS nem de ausência de vulnerabilidades.

Históricos foram preservados em meta/history. QA_REPORT.md, FIXES.md e PRODUCT_BACKLOG.md mantêm detalhes e evolução. Relatórios não incluem senhas, chaves ou tokens.


## Entrega de fotos no servidor local
BUG 47 corrigido: public/storage entrega PNG com MIME e hash corretos no PHP/Windows e proxy Vite. HEAD validado; a suíte consolidada atual tem 158 testes/908 asserções. Fixtures removidas e aplicação restaurada nas portas 8000/5173. Política pública atual preservada por decisão explícita do usuário; alternativa privada não adotada em PHOTO_ACCESS_PROPOSAL.md. Auditoria não concluída.


## Despesas em Projetos
BUGs 48/49/51/52: paginação completa dentro do limite 100, cancelamento de respostas antigas, erro explícito e preservação do modal em falha; canceladas fora dos totais e status correto. Filtros agora abrem/fecham; edição e “Marcar como pago” persistem pela API e recalculam etapa/projeto. Campos fictícios de fornecedor, vencimento e anexos foram retirados; forma de pagamento e observação reais são exibidas. Falha de resumo deixa os totais como indisponíveis e oferece nova tentativa, sem apresentar zero ou soma parcial como definitiva. Integração PHP, 16 helpers Node e build aprovados. A conta local sem projeto permitiu somente smoke da rota/console; fluxos visuais com despesa existente, falha offline e foco continuam pendentes.

## Conversão em projeto — BUG 50
Um endpoint protegido substitui duas gravações frontend. Transação integral, lock e vínculo persistido evitam órfãos e duplicação em retry. Dez testes específicos/80 asserções e concorrência MariaDB real aprovados; migration local com backup consistente. Legados preservados sem associação automática; reconciliação e validação visual pendentes. Evidências em conversion-invariants.json e QA_REPORT.md. Auditoria continua em andamento.

## Ações globais e projeto manual
BUG 14 foi reduzido: Novo Projeto abre cadastro manual real, sino lê atividades reais sem badge inventado e Ajuda tem rota própria. Estado sem imóvel encaminha ao formulário real de oportunidade. Navegador validou tema escuro em viewport estreito, dialog, Escape, URLs e estados vazios; API/manual payload têm regressão automatizada. Evidências ajuda-mobile.png e novo-projeto-mobile.png. Outros módulos demonstrativos, CRUD visual completo e falhas de rede permanecem pendentes; aplicação ainda não está aprovada para produção.

## Confiabilidade de projetos e despesas
Projetos agora consome todas as páginas da API. Se qualquer resumo falhar, cartões, detalhe e KPIs financeiros mostram “Indisponível”, o aviso informa quantos projetos faltaram e há nova tentativa; etapas dependentes não são apresentadas como vazias. O rótulo foi corrigido de “ativos” para “cadastrados”, pois a lista inclui outros estados. Totais monetários do helper usam centavos. Despesas ganhou filtros funcionais, edição real, pagamento real e apresentação apenas de campos persistidos. ExpenseUpdateWorkflowTest confirma edição, pagamento e recálculo do custo da etapa/projeto. Suíte consolidada atual: 158/908; Node: 18; build aprovado. A interação visual completa requer uma conta com projeto/despesa descartável e continua pendente.

## Busca da IA no servidor Windows — BUG 53
A chamada de Perdizes reproduziu 502. O Node iniciado pelo servidor PHP perdia variáveis essenciais do Windows e abortava na inicialização criptográfica; o fallback HTTP era bloqueado pelos portais. O subprocesso agora recebe binários absolutos e somente variáveis de runtime permitidas, sem cabeçalhos/dados da requisição. Diagnósticos da coleta e do pipeline são preservados. A mesma busca retornou 36 anúncios e renderizou 20 na primeira página, sem 502; evidência ia-perdizes-corrigida.png. Quatro testes do resolvedor/ambiente e dois testes do endpoint foram adicionados. Consolidado atual: 158/908, Node 18 e build aprovado. Portais externos permanecem sujeitos a bloqueios e mudanças.

## Resposta anônima da API — BUG 54
Uma requisição sem sessão e sem cabeçalho JSON tentava redirecionar para a rota Laravel `login`, inexistente, e retornava 500. O bootstrap agora desativa redirecionamento somente para `/api/*`; a mesma rota retorna 401 tanto para clientes JSON quanto genéricos. Regressão integrada ao SecurityRegressionTest e HTTP real confirmado. Suíte consolidada atual: 158 testes/908 asserções.

## Página autônoma de despesas — BUG 55
A rota `/despesas` deixou de exibir 12 gastos, fornecedores, anexos, vencimentos, totais e paginação inventados. Ela agora carrega todas as páginas permitidas das APIs de despesas e projetos, calcula os cartões em centavos, filtra por projeto/categoria/período/status/texto, mostra somente campos persistidos, pagina localmente, detalha o registro e atualiza pagamento por PUT. Exportação CSV usa os dados filtrados e neutraliza células iniciadas por fórmulas. Estado vazio, tema escuro, viewport estreito, console e navegação para lançamento em projeto foram verificados no navegador; conta demo não tinha registro descartável para clicar em pagamento/exportação. Build e 18 testes Node aprovados.

## Fornecedores fictícios — BUG 56
A tela exibia dez empresas e contatos inventados sem qualquer persistência. Foi criado CRUD completo em `/api/fornecedores`, com nova tabela, validação, exclusão lógica, paginação, TenantScope e bloqueio de usuário sem tenant. A interface agora lista somente registros da conta, filtra, cadastra, edita, exclui e mostra detalhes reais. A migração aditiva 000005 foi aplicada ao SQLite local. Quatro testes/36 asserções específicos cobrem fluxo legítimo, paginação, mass assignment e acesso entre tenants; suíte completa 158/908. Navegador validou estado vazio, formulário, campos obrigatórios, foco e Escape sem gravar dados demo.

## Documentos privados — BUG 57
A tela deixou de apresentar 128 arquivos e métricas inventadas. Foi implementado CRUD real com armazenamento local privado, caminhos UUID, download autenticado, TenantScope, validação de formato/tamanho/projeto, throttle e respostas sem caminho interno. Falhas de banco limpam arquivo órfão. Seis testes/61 asserções específicos e suíte completa 164/976 foram aprovados; build Vite passou e a migração 000006 foi aplicada. Navegador confirmou estado vazio, diálogo, foco, Escape e console limpo sem modificar a conta demo. Scanner antimalware, criptografia/backup e storage de produção permanecem pendentes; aplicação continua sem aprovação para produção.

## Relatórios fictícios — BUG 58
A página deixou de exibir R$ 2,3 milhões, séries de 2024 e projetos inventados para contas vazias. Agora usa o resumo tenant-aware do Dashboard, identifica métricas acumuladas e janela de seis meses, mostra estados vazios/erro e exporta CSV protegido contra fórmulas. Conta demo confirmou zeros e datas atuais sem erro de console. Dois testes novos elevaram a suíte frontend para 20; build aprovado. Filtros por período e relatórios detalhados continuam pendentes e não são simulados.

## Calendário fictício — BUG 59
A grade fixa de maio/2024 e 26 compromissos inventados foram substituídos por CRUD tenant-aware. A tela usa o mês corrente, navega, filtra e persiste eventos/projetos reais; intervalos multidiários e todas as páginas da janela são considerados. Quatro testes/52 asserções específicos elevaram a suíte a 168/1.034; build/Pint passaram e a migração foi aplicada. Além do estado vazio, uma automação com fixture validou criação, recarga, navegação, edição, exclusão, evento multidiário, preservação do formulário após HTTP 503, tema escuro e viewport 375x812. Evidência: `meta/calendar-ui-crud-checks.json`. Recorrência, lembretes e integrações externas permanecem pendentes.

## Mensagens removida — BUG 60
Por solicitação explícita do usuário, a tela inteira foi excluída. A rota React, import, item do menu e contador fixo foram removidos; `/mensagens` agora apresenta o 404 próprio. O backend que começava a ser preparado ainda não tinha endpoint nem migração aplicada e foi descartado sem resíduo. Build e verificação real no navegador passaram. O produto não alega mais oferecer mensageria.

## Obras fictícias — BUG 61
A tela `/obras` deixou de apresentar projetos, custos, etapas, alertas e datas inventados. Ela agora lista todos os projetos reais da conta, carrega resumos, deriva prazo/status, filtra e permite atualizar etapas pelo endpoint protegido. Falha parcial oculta totais financeiros; criação usa o fluxo real de projetos. Build e 23 testes Node passaram. Conta demo vazia e encaminhamento ao cadastro foram validados no navegador sem erro de console. A edição visual com fixture descartável continua pendente; o backend já possui regressões de validação e isolamento.

## Imóveis fictícios e API sem limites — BUGs 62/63
`/imoveis` não apresenta mais casa, cenários, ROI, cronograma ou comparáveis inventados. O módulo lista e mantém imóveis reais da conta, com busca, filtros, detalhes, cadastro, edição e exclusão lógica. A coleção e os campos receberam limites; usuário sem tenant é bloqueado e testes demonstram isolamento. Suíte PHP 172/1.071, frontend 25/25 e build aprovados. Conta demo confirmou estado vazio, formulário/foco/validação/Escape e console limpo; CRUD visual com fixture continua pendente.

## Financeiro funcional — BUG 64
`/financeiro` deixou de ser “Em breve”. O painel usa `/api/dashboard` para exibir posição acumulada, composição, seis meses, projetos e despesas recentes, com escopo explícito, estados de falha/vazio e atalhos operacionais. Conta demo mostrou zeros/datas atuais; três atalhos foram navegados e o console permaneceu limpo. Build e 27 testes Node passaram. Reconciliação com fixture financeira e falha offline continuam pendentes.

## Tarefas reais e contrato seguro — BUGs 65/66

`/tarefas` deixou de ser a última página provisória. A tela e os cinco endpoints implementam CRUD, filtros, paginação, conclusão e exclusão lógica com projeto, etapa e responsável validados dentro do tenant. O recurso de projeto não serializa mais models crus de tarefa e não inclui itens excluídos. Quatro testes PHP/58 asserções específicos, 30/30 testes frontend, build e validação real no navegador passaram. Uma automação posterior com fixture validou criação, recarga, busca, filtro, exclusão, entrada nativa de data e preservação do formulário após HTTP 503 em tema escuro e viewport 375x812. Evidência: `meta/task-ui-crud-checks.json`. A aplicação continua sem aprovação para produção.

## Configurações baseadas no ambiente real — BUG 67

Estados fixos de integrações foram substituídos por diagnóstico autenticado. O backend distingue configuração, capacidade local, ausência de dados e funcionalidades não implementadas sem expor segredos nem fazer chamadas externas ao abrir a página. A conta demo mostrou 2 fontes utilizáveis/configuradas, 5 exigindo atenção e 7 não implementadas, com zero ITBI real. Três testes/25 asserções específicos, suíte 179/1.161, frontend 30/30, build e navegador passaram. Saúde contínua dos provedores e implementação das fontes pendentes continuam fora do escopo concluído.

## Recuperação segura de senha — BUG 68

Login agora oferece solicitação e redefinição completas. Resposta anti-enumeração, elegibilidade de conta/tenant, token hashado/expirável/uso único, fragmento de URL, limites de abuso, contrato bcrypt e revogação de sessões foram implementados. Cinco testes PHP/39 asserções específicos, dois testes frontend, suíte 184/1.200, frontend 32/32, build e validação visual passaram. A entrega real pelo Resend segue pendente por ausência de chave/remetente e a aplicação continua sem aprovação para produção.

## Logout consistente — BUG 69

O endpoint deixou de falhar com 500 quando Sanctum autentica sem token pessoal. Bearer remove somente o token corrente; autenticação web encerra o guard, invalida a sessão e regenera CSRF. Três testes/8 asserções de logout e suíte consolidada 186/1.204 passaram. Lista de dispositivos, revogação seletiva e validação dos cookies no proxy HTTPS continuam pendentes.

## Cabeçalhos defensivos do frontend — BUG 70

O documento da SPA era entregue sem CSP, proteção contra iframe, `nosniff`, política de referência ou restrição de recursos do navegador. Vite dev/preview agora aplica uma política centralizada; o build inclui `_headers` para hosts estáticos compatíveis. A produção restringe scripts à própria origem, objetos e enquadramento são bloqueados e apenas Google Fonts/IBGE e imagens HTTPS são permitidos conforme o uso atual. Dois testes elevaram o frontend a 34/34; build com 2.307 módulos, headers HTTP reais em dev/preview e recarga autenticada sem erros de console passaram. O proxy/CDN de produção precisa ser validado para garantir que preserve o arquivo ou replique os headers.

## Erros API independentes de `Accept` — BUG 71

Validação sem `Accept` redirecionava para a aplicação web e uma rota `/api/*` inexistente devolvia HTML; com debug local, a conversão inicial para JSON ainda expôs stack trace e caminhos absolutos. O bootstrap agora força JSON em toda a superfície API, remove metadados internos, usa mensagem genérica em 5xx e reaplica headers defensivos inclusive em exceções fora de uma rota reconhecida. Quatro testes específicos/54 asserções e a suíte completa então em 188/1.240 passaram; HTTP real confirmou 422/404 JSON sem redirecionamento nem trace. A identificação do PHP foi removida no BUG 72.

## Arquivos de configuração expostos pelo servidor local — BUG 72

O roteador entregava qualquer arquivo resolvido dentro de `public`; requisições reais a `/.user.ini` e `/.htaccess` retornavam 200 e conteúdo. A regra agora bloqueia qualquer segmento oculto, PHP/HTML/SQL e extensões fora de uma allowlist web; `/storage` mantém sua allowlist própria. Arquivos existentes negados recebem 404 sem passar à SPA. Apache também nega dotfiles, `.user.ini` desativa `expose_php`, e os scripts locais iniciam PHP com essa opção. HTTP real confirmou os bloqueios, ativos legítimos e ausência de `X-Powered-By`; a fixture pública foi removida. Cinco testes específicos/29 asserções e a suíte completa 189/1.252 passaram. Configuração equivalente ainda deve ser conferida no proxy/Nginx escolhido para produção.

## Endpoint interno de limites PHP — BUG 73

Uma rota protegida, mas disponível a qualquer conta verificada, devolvia `upload_max_filesize`, `post_max_size` e `memory_limit`. O frontend não possuía consumidor e já aplica o contrato de upload de 250 MB. A rota foi eliminada, reduzindo fingerprinting e superfície sem remover função visível. Regressão autenticada, HTTP real e inventário de 90 rotas confirmam 404 e ausência dos campos. `ImportSecurityTest` passou 6/34 e a suíte completa 190/1.254. Limites efetivos do proxy ainda precisam ser configurados e monitorados no deploy, sem publicá-los via API.

## Fotos órfãs após excluir oportunidade — BUG 74

A exclusão de oportunidade agora elimina fotos locais do disco público e metadados, usando quarentena privada para permitir restauração se o banco ou storage falhar. Caminhos locais fora do namespace gerenciado são recusados sem alteração; URLs externas perdem somente o metadado. Três regressões específicas, `OpportunityTest` 15/75, Pint e suíte completa 193/1.270 passaram. Fotos ativas continuam públicas por link conforme decisão do usuário. Cache/CDN, lifecycle e retenção do storage de produção permanecem pendentes.

## Exclusão inconsistente de documentos — BUG 75

Documentos não são mais ocultados quando a remoção física falha. A exclusão usa quarentena privada, lock e transação; o arquivo volta ao caminho original se o banco falhar e caminhos fora da pasta do tenant são recusados. O controller retorna 503 neutro para erro de storage. Nove testes/76 asserções específicos, Pint e suíte completa 196/1.285 passaram. Scanner antimalware, backup, retenção e comportamento do storage de produção continuam pendentes.

## Propriedade ocultada com vínculos ativos — BUG 76

A API deixou de excluir logicamente imóveis ainda referenciados por projetos, vendas ou despesas ativos. O serviço usa lock e transação; vínculos ativos retornam 409 e preservam o imóvel, enquanto vínculos já excluídos permitem a limpeza. Dois cenários novos, `PropriedadeTest` 6/51, Pint e suíte completa 198/1.299 passaram. A validação visual com fixture e falha offline permanece pendente.

## Dependentes ativos após excluir projeto — BUG 77

Projetos excluídos não deixam mais tarefas e eventos visíveis nem etapas ativas. O serviço encerra os três conjuntos e o projeto em uma única transação com lock; documentos e despesas permanecem como histórico. A regressão demonstrou a tarefa órfã antes da correção. `DomainIntegrityTest` 10/94, Pint e suíte completa 200/1.312 passaram. O fluxo visual com fixture permanece pendente; rollback transacional já possui regressão forçada.

## Filtros de coleção permissivos — BUG 78

Estados, tipos e origens desconhecidos, booleanos inválidos e faixa de preço invertida deixaram de retornar coleções vazias com 200. As rotas afetadas agora usam allowlists e coerência de intervalo, devolvendo 422. Dois testes/27 asserções específicos, 25/248 relacionados, Pint e suíte completa 202/1.339 passaram. A revisão semântica das demais integrações segue no backlog.

## Rotas não utilizadas do disco privado — BUG 79

O framework registrava GET e PUT assinados para o disco `local`, embora o produto não gere nem consuma URLs temporárias desse disco. Requisições sem assinatura já eram negadas, mas os endpoints não tinham função no fluxo atual. `serve` foi desativado somente no storage privado; documentos continuam pelo controller autenticado e fotos permanecem no disco público. O inventário agora tem 88 rotas e nenhuma `storage.local*`; HTTP real confirmou 404 sem criação de arquivo privado e 200 no disco público. `SecurityRegressionTest` 24/141, Pint e suíte completa 203/1.342 passaram.

## Pontos de entorno fabricados pela IA — BUG 80

Quando mapas externos falhavam, a análise apresentava estabelecimentos e distâncias genéricos como pontos próximos encontrados e completava categorias ausentes de respostas parciais. Esses geradores foram removidos. O backend agora aceita apenas Overpass/Nominatim, cache de uma coleta real anterior ou retorna indisponível com zero pontos. Dois testes/20 asserções comprovam falha total e resposta parcial sem preenchimento sintético; Pint e suíte completa 205/1.362 passaram. A IA continua sem modelo estatístico treinado ou benchmark com dataset real, conforme já documentado.

## URLs externas sem allowlist final — BUG 81

Links e imagens dos provedores eram repassados pelo scraper à API e usados em `href`/`src`. A resposta agora aceita somente URLs HTTP/HTTPS válidas e anula esquemas de script, dados, arquivo ou outros protocolos. Uma regressão controlada confirma remoção de `javascript:`/`data:` e o cenário HTTPS legítimo permanece funcional. `AIPropertySearchTest` 3/17, Pint e suíte completa 206/1.366 passaram.

## SSRF no navegador dos portais — BUG 82

Páginas externas podiam induzir o Chromium do servidor a requisitar loopback, redes privadas, link-local ou metadados. Uma política de egress agora verifica esquema, credenciais, IP e todas as respostas DNS antes de continuar cada requisição, inclusive redirecionamentos e páginas opcionais de detalhe. Quatro testes cobrem destinos públicos e internos; frontend 38/38, sintaxe Node, build com 2.307 módulos e auditoria npm de produção sem vulnerabilidades passaram. Firewall de egress continua recomendado como camada operacional independente.

## Redirecionamento SSRF no fallback PHP — BUG 83

O cliente alternativo de HTML começava em portais fixos, mas seguia `Location` automaticamente, permitindo salto para rede interna. O fluxo agora processa no máximo três redirecionamentos e valida HTTPS, host exato, porta/credenciais e todos os endereços DNS antes de cada requisição. Onze testes/15 asserções comprovam bloqueios e o salto legítimo; Pint e suíte completa 217/1.381 passaram.

## HTML externo sem limite — BUG 84

O fallback PHP carregava integralmente qualquer resposta do portal. O corpo agora tem teto de 5 MiB aplicado no header, no progresso e antes do parse, sem descompressão automática. Uma fixture acima do limite é rejeitada com falha neutra. Três testes/9 asserções, Pint e suíte completa 218/1.384 passaram.

## Projeções financeiras sem proveniência — BUG 85

A Central Financeira combinava resultados persistidos com benchmarks fixos, metas “ideais”, aumento de ROI e score contrafactual sem fonte ou recálculo demonstrável. A medida de preenchimento era exibida como confiança e o score como percentil de mercado. Esses elementos foram removidos ou renomeados conforme sua evidência. Cenários agora partem do valor informado, expõem cada variação e são descritos como sensibilidade hipotética. A interface declara o cálculo baseado em regras, elimina atrasos que simulavam processamento e usa faixas neutras em vez de “Comprar/Não comprar”. O campo de recomendação permanece nulo. Nove testes/68 asserções relacionados, suíte PHP 219/1.406, frontend 38/38, Pint e build passaram. Validação empírica dos pesos e limiares permanece obrigatória antes de alegar acurácia financeira.

## Taxas bancárias sem integração real — BUG 86

Dez taxas estáticas eram apresentadas como referência do Banco Central, com período fixo, embora a integração BCB esteja não implementada. A lista foi eliminada. O simulador agora usa apenas taxa e instituição informadas pelo usuário, calcula anual equivalente e deixa explícita a ausência de oferta em tempo real. Oito testes/58 asserções específicos, suíte 219/1.405, frontend 38/38, Pint e build passaram. Comparação automática só deve retornar após integração oficial/licenciada com produto, CET, data e atualização verificáveis.

## ROI periódico divergente entre interface e backend — BUG 87

A prévia da Central Financeira dividia o ROI total pelo número de meses e depois capitalizava esse quociente para obter o anualizado. O backend e o resultado persistido já usavam equivalência composta. Para 100% em 12 meses, a interface projetava aproximadamente 161% ao ano enquanto o resultado correto era 100%. A prévia agora compartilha a fórmula composta equivalente: `(1 + ROI total)^(1/meses) - 1`, e o anualizado usa expoente `12/meses`. Rótulos residuais de aconselhamento foram neutralizados. Regressões cobrem 100% em 12 meses, 21% em seis meses, perda total e entrada inválida. `InvestmentAnalysisTest` passou 10/73, a suíte PHP 220/1.411, o frontend 40/40, Pint e o build com 2.308 módulos passaram.

## Limites numéricos divergentes do esquema — BUG 88

As APIs de propriedades e oportunidades aceitavam `area_m2` até 999.999.999.999, mas as colunas são `DECIMAL(10,2)` e suportam no máximo 99.999.999,99. A reprodução automatizada confirmou resposta 201 e persistência no SQLite para 100.000.000 m²; em banco estrito esse payload pode falhar ou ser truncado. A análise de mercado também aceitava área, cômodos e preço sem teto e campos desconhecidos no objeto selecionado. As validações agora respeitam a capacidade da coluna e os limites do domínio; o contrato da análise usa allowlist estrita, e o frontend envia apenas os campos autorizados. Duas regressões/16 asserções, 26 testes relacionados/159 asserções e a suíte completa 222/1.427 passaram; Pint e build com 2.308 módulos foram aprovados.

## Atualização de projeto ignorava campos validados — BUG 89

O request de projeto aceitava `etapas` e `orcamento_categorias` tanto na criação quanto na atualização, mas `ProjetoService::atualizar` removia os dois campos antes do `update`. A API respondia 200, aplicava os campos simples e descartava silenciosamente as alterações aninhadas. Como o payload não possui identificadores suficientes para substituir etapas com segurança e existem endpoints próprios para editar/criar etapas, `PUT/PATCH /api/projetos/{id}` agora rejeita esses campos com 422. A regressão confirma que projeto e etapa permanecem inalterados. `ManualProjectTest` passou 3/24, testes relacionados 25/209 e suíte completa 223/1.434; Pint foi aprovado.

## Cronologias invertidas em projetos e vendas — BUG 90

Etapas aceitavam término previsto anterior ao início, e vendas aceitavam fechamento anterior à proposta e escritura anterior aos marcos precedentes. A reprodução recebeu 201 nos dois fluxos; atualização parcial de uma venda existente também aceitou escritura anterior com 200. A etapa agora usa comparação por índice do array. A venda calcula a cronologia efetiva combinando campos enviados com datas persistidas, impedindo que PATCH/PUT parcial contorne a regra; atualização cronologicamente válida continua 200. Três regressões passaram, assim como 18 testes relacionados/146 asserções e a suíte completa 226/1.450; Pint foi aprovado.

## Múltiplas vendas finais para o mesmo imóvel — BUG 91

A API permitia várias vendas ativas `fechada/escriturada` para uma propriedade. Dashboard deduplicava, ROI selecionava uma venda e a listagem mantinha todas, gerando resultados incompatíveis. Propostas e negociações continuam múltiplas; antes de finalizar/criar/mover uma venda, o serviço bloqueia as propriedades envolvidas em ordem determinística e recusa outra venda final ativa com 422. Ao mover a única venda final, o imóvel anterior volta a `a_venda` e o novo vira `vendida`. A regressão cobre criação duplicada, duas propostas e disputa de finalização. Testes relacionados 16/142 e suíte completa 227/1.459 passaram; Pint foi aprovado. A disputa específica foi posteriormente comprovada com dois processos MariaDB sobrepostos: 201/422, uma venda final e imóvel vendido.

## Proposta/cancelamento contabilizados como venda no projeto — BUG 92

`ProjetoService::roiProjetado` selecionava a venda ativa mais recente sem filtrar status, ao contrário do dashboard e do ROI da propriedade. Uma proposta ou venda cancelada podia definir `tem_venda=true`, receita, lucro e ROI do resumo de obra. A reprodução com proposta de R$ 160 mil e cancelamento de R$ 999.999 confirmou o falso positivo. O resumo agora considera apenas `fechada/escriturada`, com ordenação determinística; após finalizar a proposta válida, reconhece R$ 160 mil. Testes relacionados 13/123, suíte 228/1.468, Pint e build com 2.308 módulos passaram.

## Venda final sem marco temporal obrigatório — BUG 93

Uma venda podia receber status `fechada` sem `data_fechamento` ou `escriturada` sem `data_escritura`. A reprodução retornou 201 e criou uma venda realizada que entrava nos KPIs, mas não podia ser atribuída a nenhum mês da série de desempenho. `VendaRequest` agora calcula estado e datas efetivos com o registro persistido, exige fechamento para `fechada/escriturada` e escritura para `escriturada`, inclusive em transições por atualização. A regressão cobre duas criações inválidas, transição inválida e fluxos finais válidos. Testes relacionados 17/151, suíte completa 229/1.480 e Pint global passaram.

## Etapas excluídas reapareciam no projeto — BUG 94

`Projeto::etapas()` não filtrava `deletado_flg`, embora os endpoints individuais e os totais de orçamento tratassem somente etapas ativas. A reprodução inseriu uma etapa excluída e uma ativa; o detalhe retornou ambas, e o resumo podia exibir e recalcular o registro arquivado. A relação central agora restringe etapas ativas e o recálculo aplica o mesmo escopo. Detalhe e resumo retornam somente a etapa ativa. Testes relacionados 16/152, suíte completa 229/1.486 e Pint global passaram.

## Projeto concluído sem data real — BUG 95

A API serializava `data_termino_real`, mas descartava esse campo no request e permitia `status=concluido` com a data nula. A reprodução atualizou um projeto com data real e recebeu 200, porém a resposta manteve `null`. O request agora aceita e valida o marco, e o serviço mantém a invariante: ao concluir, preserva a data existente, usa a informada ou registra o dia corrente; ao reabrir, limpa a data. Um término informado exige estado concluído e não pode preceder o início. Testes relacionados 20/181, suíte completa 230/1.497 e Pint global passaram.

## Edição de tarefa concluída alterava sua data — BUG 96

Ao atualizar uma tarefa já concluída sem reenviar `data_conclusao`, `TarefaService` substituía o marco histórico pelo dia atual. A reprodução concluiu em 10/10/2026, editou apenas o título e observou a data mudar para 17/10/2026. A normalização agora recebe o registro persistido: preserva o marco de uma tarefa já concluída, usa a data explícita ou cria uma somente na primeira conclusão; reabertura continua limpando-a. Testes relacionados 16/179, suíte completa 230/1.500 e Pint global passaram.

## Despesa paga sem data de pagamento — BUG 97

O formulário de despesas do projeto inicia novos lançamentos como `pago`, mas não envia `data_pagamento`; o backend persistia o estado financeiro com data nula. Além disso, não havia regra uniforme para preservar o marco em edições ou removê-lo ao cancelar/reabrir. `DespesaService` agora normaliza criação e atualização: primeiro pagamento sem data usa o dia atual, edição de item já pago preserva a data histórica, data explícita é respeitada e qualquer estado não pago limpa o campo. Testes relacionados 15/148, suíte completa 231/1.509 e Pint global passaram.

## Custo detalhado pago sem data — BUG 98

Custos da análise financeira repetiam a inconsistência: `payment_status=paid` aceitava `paid_at=null`, inclusive no wizard, e edições não tinham regra para preservar ou limpar o marco. `InvestmentCostRequest` agora normaliza antes da validação em ambos os endpoints: primeira quitação define o dia atual, data explícita é respeitada, edição paga preserva o valor persistido e estados `pending/overdue` limpam o campo. Testes relacionados 16/127, suíte completa 232/1.518 e Pint global passaram.

## Atualização parcial contornava cronologia do projeto — BUG 99

`after_or_equal:data_inicio` comparava apenas campos presentes no mesmo payload. Em um projeto iniciado em 10/11 e previsto para 20/11, atualizar somente o término para 09/11 retornava 200; atualizar somente o início para 21/11 também podia ignorar o término persistido. `ProjetoRequest` agora calcula início e término efetivos com o registro tenant-scoped e recusa ambos os sentidos antes da escrita. Testes relacionados 21/190, suíte completa 233/1.527 e Pint global passaram.

## Status vendido divergente da venda final — BUG 100

A API de imóveis permitia criar `status=vendida` sem venda final e rebaixar manualmente para `a_venda` um imóvel com venda fechada. A reprodução recebeu 201 no primeiro caso; isso fazia listagem, dashboard e ROI discordarem. `PropriedadeService` agora rejeita `vendida` na criação, bloqueia o imóvel na atualização e exige equivalência entre o status solicitado e uma venda ativa `fechada/escriturada`. Como `VendaService` usa o mesmo lock, transições concorrentes são serializadas. Edição legítima mantendo `vendida` continua 200. Testes relacionados 20/196, suíte completa 234/1.543 e Pint global passaram.

## CRUD visual de projetos — BUG 125

O detalhe do projeto não oferecia edição nem exclusão, embora as operações existissem na API. A interface agora permite editar os dados principais e excluir com confirmação acessível. O navegador real validou criação vinculada a imóvel, recarga, edição, orçamento em centavos, exclusão lógica e bloqueio de exclusão quando existem despesas ativas. O banco confirmou isolamento no tenant 3 e os dois estados de exclusão. Frontend 40/40 e build TypeScript/Vite passaram. Evidência: `meta/project-ui-crud-checks.json`.

## Confirmação própria nas exclusões operacionais — BUG 126

Fornecedor, documento, evento, oportunidade e a central financeira dependiam de `window.confirm`, sem semântica controlada pela aplicação, descrição vinculada ou restauração verificável do foco. Um diálogo reutilizável agora apresenta título e descrição acessíveis, estado pendente e retorno do foco ao botão disparador. O CRUD visual do fornecedor descartável foi concluído e o banco confirmou exclusão lógica; abertura e cancelamento em fornecedor, documento e evento confirmaram preservação. A regressão estrutural impede o retorno do diálogo nativo nas seis páginas, incluindo tarefas. Frontend 43/43, suíte PHP 264/1.787 e build TypeScript/Vite com 2.311 módulos passaram.

## Gestão de sessões ausente — BUG 127

Usuários não conseguiam revisar tokens ativos, encerrar outro dispositivo ou revogar todos os acessos. A API agora lista somente os tokens do usuário, identifica o token atual, revoga uma sessão tenant-safe e oferece logout global. `/configuracoes` exibe dispositivo, criação, último uso e expiração, com confirmação antes da revogação. Três regressões/17 asserções cobrem isolamento e invalidação; suíte completa 256/1.747, frontend 43/43 e build passaram. Evidência: `meta/session-management-checks.json`.



## Datas HTTP sem limite de tamanho — BUG 128

Os campos de data validavam o conteúdo, mas não limitavam o tamanho da entrada antes do parser. Todos os Form Requests, os filtros compartilhados e o calendário agora impõem até 40 caracteres, preservando datas ISO e recusando payloads excessivos. Uma regressão estrutural percorre toda a superfície de validação HTTP e falha se uma nova regra de data surgir sem limite. Pint e a suíte completa de 264 testes/1.787 asserções passaram. Evidência: `meta/request-bound-validation-checks.json`.


## Falha ao criar lote ITBI deixava upload órfão — BUG 129

O arquivo era gravado antes do registro do lote e uma falha de banco nessa janela deixava conteúdo sem referência. O upload agora distingue arquivos novos de caminhos já existentes pelo hash: não regrava conteúdo preexistente e remove somente o arquivo recém-criado se a criação do lote falhar. Dois testes comprovam limpeza e preservação, inclusive sem transações importadas. Suíte completa: 264 testes/1.787 asserções; Pint aprovado. Evidência: `meta/transaction-file-rollback-checks.json`.

## CSV codificado e XLSX real perdiam cabeçalhos — BUG 130

O parser tratava BOM como parte do primeiro cabeçalho, não convertia streams UTF-16 antes de detectar o delimitador e lia atributos de células XLSX namespaced de forma incorreta. Isso podia produzir chaves vazias ou valores numéricos no lugar de shared strings. A leitura agora detecta BOM UTF-8/UTF-16, converte o stream, preserva aspas/delimitadores e acessa namespaces, atributos, shared strings e rich text do XLSX. Testes de planilha/pipeline 16/33 e suíte completa 264/1.787 passaram. Evidência: `meta/spreadsheet-format-compatibility-checks.json`.

## Mesmo lote ITBI podia iniciar duas importações — BUG 131

A transição para `processing` era uma atualização comum, permitindo que dois workers iniciassem o mesmo lote. O serviço agora adquire o lote com atualização condicional atômica e recusa uma segunda execução sem alterar contadores ou timestamps. A busca da identidade de cada transação usa lock de banco. O comando ganhou recuperação explícita `--recover-stale=N`, que libera somente lotes com idade mínima confirmada; lotes recentes continuam intocados. Testes focados 10/48 e suíte completa 264/1.787 passaram. Evidência: `meta/itbi-import-recovery-checks.json`.

## Filtro municipal combinava cidade OU bairro — BUG 132

O provedor ITBI municipal aceitava uma linha quando apenas a cidade ou apenas o bairro coincidia com a consulta. Isso contaminava os comparáveis com imóveis de outro bairro ou município. O filtro agora exige simultaneamente todos os critérios geográficos informados. A regressão inclui registros com cidade correta/bairro errado e bairro correto/cidade errada, além de respostas de sucesso, erro HTTP, exceção, ausência de configuração e dataset para os três provedores externos. Nenhuma exceção privada é exposta. Testes focados 5/37, suíte completa 266/1.804 e Pint passaram. Evidência: `meta/market-provider-response-checks.json`.

## Navegador headless aceitava redirecionamento para domínio público arbitrário — BUG 133

A interceptação bloqueava redes privadas, mas permitia que uma navegação principal iniciada em portal autorizado terminasse em qualquer domínio público; o enriquecimento também aceitava qualquer URL pública. O scraper agora exige HTTPS e host de portal permitido na navegação principal e nos detalhes, preserva a resolução DNS pública por requisição e nega downloads em cada página via CDP. O sandbox permanece habilitado por padrão, com desativação apenas por configuração explícita. Frontend 46/46, sintaxe Node e build de 2.311 módulos passaram. O firewall/egress do host continua como controle obrigatório de produção. Evidência: `meta/headless-browser-egress-checks.json`.

## Falha de banco após download ITBI deixava arquivo órfão — BUG 134

O downloader gravava o conteúdo antes de atualizar o lote. Se essa atualização falhasse, o arquivo novo permanecia sem referência; além disso, os atributos sujos do modelo poderiam atravessar a tentativa posterior de marcar o lote como falho. O serviço agora identifica conteúdo preexistente, evita sobrescrita, remove somente o arquivo criado pela tentativa, restaura o modelo persistido antes de registrar a falha e nunca apaga um caminho content-addressed anterior. Testes focados 9/36, suíte completa 268/1.814 e Pint passaram. Interrupção abrupta do host e comportamento do storage/proxy implantado continuam como ensaios de produção. Evidência: `meta/remote-download-rollback-checks.json`.

## Estimativa do imóvel era apresentada como média municipal — BUG 135

A central financeira rotulava `estimated_market_price` do imóvel como “preço médio da cidade” e apresentava liquidez, valorização e tempo de venda inexistentes como “em breve”, dentro de um painel chamado histórico regional. Isso confundia estimativas e valores informados com observações de mercado. Os rótulos agora distinguem valor de mercado estimado, valor médio vendido e preço médio por m²; métricas sem fonte aparecem como não disponíveis, sem promessa de dados reais. Duas regressões impedem o retorno dessas alegações. Frontend 48/48 e build de 2.311 módulos passaram. Evidência: `meta/financial-market-label-checks.json`.

## Botão de projeto no dashboard não executava ação — BUG 136

A coluna de ação da tabela “Projetos em andamento” renderizava um botão de três pontos sem evento, menu ou destino. Ele foi substituído por um link acessível que leva a `/projetos?projeto={id}`; a página de projetos valida o identificador contra a lista carregada e abre o registro correspondente. Uma regressão analisa a AST de todos os arquivos TSX e recusa botões nativos sem ação nem função de submit. Frontend 49/49 e build de 2.311 módulos passaram. A execução visual de todos os handlers permanece separada. Evidência: `meta/interactive-control-checks.json`.

## Link de anúncio ausente ou com esquema inseguro era renderizado — BUG 137

Os resultados da IA exibiam “Abrir” com `href="#"` quando não havia anúncio e as duas telas ligavam diretamente qualquer `source_url` recebida. A interface agora normaliza URLs externas em uma função comum, aceita somente HTTPS sem credenciais, oculta a ação ausente e usa `noopener noreferrer`. Regressões cobrem URL válida, valor vazio, fragmento, HTTP, credenciais, `javascript:`, `data:` e integração nas duas páginas. Frontend 51/51 e build de 2.312 módulos passaram. Evidência: `meta/external-listing-link-checks.json`.

## Análise de mercado declarava hooks após retorno condicional — BUG 138

A página retornava “análise não encontrada” antes de declarar estados e efeitos. Se o parâmetro/contexto mudasse sem desmontar o componente, a ordem e a quantidade de hooks podiam variar entre renderizações e causar erro do React. Os hooks agora são sempre declarados, limpam o estado quando o contexto desaparece e não chamam as APIs de mercado/bairro sem item válido. A regressão verifica a ordem e as guardas. Frontend 52/52 e build de 2.312 módulos passaram. Evidência: `meta/market-analysis-hook-checks.json`.

## Verificação concorrente da venda final no MariaDB

Um MariaDB 11.4.10 portátil, isolado em `127.0.0.1:13308`, recebeu duas requisições PHP em processos distintos liberados pela mesma barreira para finalizar o mesmo imóvel fictício. Os intervalos se sobrepuseram. Uma requisição retornou 201 e a outra 422; a verificação independente encontrou exatamente uma venda final ativa, valor único de R$ 150.000 e propriedade em `vendida`. O servidor foi encerrado e os arquivos com senha/token foram removidos. O arquivo oficial utilizado teve SHA-256 conferido. Evidência: `meta/final-sale-concurrency-mariadb.json`; harness reproduzível em `meta/concurrency-harness`.

## Verificação concorrente do mesmo lote ITBI no MariaDB

Dois processos PHP liberados pela mesma barreira chamaram o importador para o mesmo lote de 500 linhas fictícias no MariaDB 11.4.10. Os intervalos se sobrepuseram: um worker concluiu e o outro recebeu a recusa específica `Este lote já está sendo processado.`. O lote terminou `completed`, com 500 importadas, zero atualizadas/falhas e exatamente 500 transações. O servidor foi encerrado, o CSV e todos os segredos transitórios foram removidos. Evidência: `meta/itbi-concurrency-mariadb.json`; harness em `meta/concurrency-harness/run-itbi.ps1`.

## Logs ITBI copiavam detalhes privados de exceção e nome de arquivo — BUG 139

Falhas por linha registravam `exception->getMessage()` e falhas gerais gravavam também o nome original do upload. Exceções de banco/parser podem conter SQL ou valores do imóvel, e nomes de arquivo podem conter dados pessoais. Os logs agora mantêm apenas lote, linha, hash SHA-256 do nome e classe da exceção. Uma regressão injeta marcadores privados em mensagem e nome e comprova sua ausência no contexto registrado e na resposta. Testes focados 9/48, suíte completa 269/1.819 e Pint passaram. Evidência: `meta/itbi-log-redaction-checks.json`.

## Falha do Chromium copiava stderr privado para logs — BUG 140

Quando o processo headless encerrava com erro, até 500 caracteres do `stderr` eram gravados literalmente. Saídas do navegador podem incluir caminhos internos, URLs e termos pesquisados. O evento agora preserva somente código de saída, tamanho em bytes e SHA-256 do diagnóstico, permitindo correlação sem armazenar o conteúdo. Um processo real em teste escreve URL e marcador privados no `stderr`; cinco asserções comprovam metadados corretos e ausência do conteúdo. Suíte completa 270/1.824 e Pint passaram. Evidência: `meta/scraper-log-redaction-checks.json`.

## Falha global do lote ITBI expunha detalhes internos — BUG 141

Mensagens de exceções de parser, filesystem ou banco podiam ser persistidas no lote e impressas pela CLI. O serviço agora persiste e lança apenas uma mensagem pública fixa, registra somente o ID e a classe e preserva a causa original exclusivamente na cadeia interna. O comando orienta a consulta pelo ID. Uma regressão com marcador privado comprova ausência no banco, mensagem pública e contexto de log. Suíte completa 271/1.832 e Pint passaram. Evidência: `meta/itbi-batch-error-redaction-checks.json`.

## Capa externa aceitava destinos inseguros — BUG 142

O navegador podia receber como imagem uma URL HTTP, com credenciais, localhost ou IP privado. Uma política comum no request e no serviço agora exige HTTPS público, porta padrão e host válido. O resource também omite URLs externas inseguras já existentes. A regressão cobre sete destinos recusados, um HTTPS público aceito e um registro legado filtrado. Suíte completa 273/1.863 e Pint passaram. DNS não é fixado para imagens carregadas diretamente pelo navegador; a implantação ainda deve aplicar CSP e isolamento de rede. Evidência: `meta/external-cover-url-checks.json`.


## Verificação visual de Configurações

Falhas 503 simuladas de fontes e sessões foram renderizadas em Chromium real, tema escuro e 375×812. Cada seção apresentou alerta e retry próprios; após os cliques, ambas recuperaram dados sem reload, overflow ou exceção JavaScript. O ensaio fecha estado de rede e viewport móvel desta tela. Leitor de tela real permanece pendente. Evidência: `meta/configuration-ui-checks.json` e duas capturas móveis.

## Verificação de falha de rede no cadastro de fornecedor

Uma resposta 503 foi injetada no POST em Chromium real, tema escuro e 375×812. O formulário preservou todos os campos e permitiu nova tentativa; o retry 201 fechou o diálogo e mostrou exatamente um fornecedor, sem overflow ou exceção JavaScript. A falha específica durante exclusão permanece pendente. Evidência: `meta/supplier-ui-network-checks.json` e duas capturas móveis.

## Falha de exclusão de fornecedor ocultava o alerta — BUG 143

Quando o DELETE retornava erro, a confirmação continuava modal e escondia a mensagem renderizada na página. O erro agora fecha a confirmação, exibe alerta/retry e preserva o cartão. Chromium real em 375×812, frontend 57/57 e build com 2.312 módulos passaram. Evidência: `meta/supplier-ui-network-checks.json`.

## Erros destrutivos ocultos por modal — BUG 144

Calendário, Documentos e Tarefas repetiam o defeito do fornecedor: o modal permanecia aberto após DELETE falhar e escondia o alerta. Os caminhos de erro agora desmontam a confirmação. Uma regressão cobre nove handlers destrutivos em sete páginas; frontend 57/57 e build de 2.312 módulos passaram. Evidência: `meta/destructive-error-visibility-checks.json`.

## Ações remotas de Oportunidades sem feedback — BUG 145

Visualizar, editar, favoritar e abrir/criar análise financeira deixavam rejeições da API sem tratamento. Os quatro fluxos agora exibem mensagem pública, restauram pending e impedem ações concorrentes; erros do detalhe aparecem dentro do modal. Frontend 55/55 e build de 2.312 módulos passaram. Evidência: `meta/opportunity-action-error-checks.json`.

## Ações financeiras sem tratamento de erro — BUG 146

Recarregar referência e escolher simulação deixavam falhas da API sem feedback e não bloqueavam cliques simultâneos. Os dois fluxos agora exibem erro, restauram pending e desabilitam os controles financeiros relacionados. Frontend 57/57 e build de 2.312 módulos passaram. Evidência: `meta/financial-action-error-checks.json`.

## Wizard financeiro e orçamento de etapa falhavam sem recuperação — BUG 147

O recálculo automático do wizard deixava a rejeição escapar, e a edição do orçamento da etapa não mostrava erro, não bloqueava chamadas simultâneas e preservava visualmente um valor recusado. Ambos agora capturam a falha e restauram pending; a etapa também desabilita o campo e volta ao valor persistido. Frontend 59/59 e build de 2.312 módulos passaram. Evidência: `meta/wizard-project-action-error-checks.json`.

## Gravações financeiras aceitavam duplo envio — BUG 148

Adicionar simulação, gasto e resultado não ativava estado pendente, deixando seus controles disponíveis durante o POST. Os três fluxos agora bloqueiam ações concorrentes, preservam o formulário em erro e restauram a interface em `finally`; o resultado salvo mostra confirmação. Frontend 60/60 e build de 2.312 módulos passaram. Evidência: `meta/financial-create-pending-checks.json`.

## Recarga financeira falha ainda confirmava sincronização — BUG 149

O carregador capturava a falha do GET pós-gravação e resolvia normalmente; financiamento, simulação e resultado podiam anunciar sucesso sobre dados antigos. O loader agora retorna resultado explícito, e confirmação/avanço dependem de recarga comprovada. Frontend 61/61 e build de 2.312 módulos passaram. Evidência: `meta/financial-refresh-outcome-checks.json`.

## Regressão completa após os BUGs 147–149

A suíte PHP completa foi repetida em processo independente: 273/273 testes e 1.863 asserções passaram. O frontend permaneceu em 61/61, o build transformou 2.312 módulos e o `npm audit` atual encontrou zero vulnerabilidades em 249 dependências. Naquela execução o Composer não estava disponível, limitação preservada na evidência histórica. Em 18/10/2026, Composer 2.10.3 foi obtido da distribuição oficial com SHA-256 conferido e `composer audit --locked` foi repetido: zero advisories e zero pacotes abandonados; `composer validate --strict --no-check-publish` também passou. Evidências: `meta/post-financial-fixes-regression.json`, `meta/composer-audit-current.json` e `meta/composer-tool-current.json`.

## Busca da IA aceitava concorrência e resposta tardia — BUG 150

O botão de busca continuava ativo durante o scraper e respostas não eram associadas à consulta que as originou. A tela agora aborta a solicitação anterior, ignora conclusões obsoletas, bloqueia repetição e cancela a chamada ao desmontar. Chromium real em 375×812 confirmou 502 público seguido de retry 200, máximo de uma busca simultânea, resultado recuperado e ausência de overflow/erro JavaScript. Frontend 63/63 e build com 2.312 módulos passaram. Evidência: `meta/ai-search-network-checks.json` e duas capturas móveis.

## Miniaturas da IA aceitavam destinos internos — BUG 151

Miniaturas vindas de scraper/API aceitavam HTTP, localhost e IPs privados e eram entregues a `<img>`, permitindo requisições do navegador a destinos internos. A API agora aplica a política comum de imagens externas e só preserva HTTPS público sem credenciais e em porta padrão. A regressão cobre oito destinos recusados e um aceito; testes focados 3/20 e Pint passaram. Evidência: `meta/ai-thumbnail-url-policy-checks.json`.

## Links externos da IA aceitavam destinos internos — BUG 152

O botão “Abrir” podia receber HTTPS para localhost, IP privado/link-local, `.local` ou porta alternativa. Uma política reutilizável agora filtra esses valores no endpoint, e o helper frontend repete a regra antes de renderizar o link. Frontend 63/63, endpoint 3/20, Pint e build de 2.312 módulos passaram. Evidência: `meta/ai-external-link-policy-checks.json`.

## IP ofuscado contornava a política de URL externa — BUG 153

Notações decimal inteira, hexadecimal, octal e abreviada de loopback não eram reconhecidas como IP pelo backend e passavam como domínio. A política agora rejeita host numérico ambíguo e normaliza IPv6 antes de classificar a faixa. A reprodução falhou em seis casos antes da correção; depois, política 9/9, integrações 22/144, frontend focado 3/3, Pint e suíte PHP completa 282/1.876 passaram. Evidência: `meta/external-url-obfuscated-ip-checks.json`.

## Oportunidades aceitavam URL de origem insegura — BUG 154

O cadastro manual aceitava origem HTTP/privada, o resource expunha registros legados e o cache local de capas não revalidava URLs. Gravação, saída e restauração do cache agora usam a política pública HTTPS. PHP 283/1.905, frontend 64/64, Pint e build de 2.312 módulos passaram. Evidência: `meta/opportunity-source-url-policy-checks.json`.

## Diagnóstico da busca expunha texto externo — BUG 155

Mensagens arbitrárias do scraper podiam ser propagadas em `diagnostics`, incluindo caminhos, URLs e detalhes operacionais. A API agora publica somente campos estruturados limitados e troca qualquer mensagem por texto fixo. A regressão confirma que caminho, loopback e marcador secreto não atravessam a resposta. Suíte PHP 283/1.909 e Pint passaram. Evidência: `meta/ai-diagnostic-redaction-checks.json`.


## Provedores de mercado aceitavam redirects e corpos ilimitados — BUG 156

Os conectores configuráveis podiam seguir redirect para rede interna e carregar uma resposta externa sem teto de bytes. A política agora exige HTTPS público antes do egress, recusa redirect e aplica 2 MiB nos headers, progresso e corpo final, sem descompressão implícita. A regressão cobre HTTP, loopback, metadata link-local e excesso de tamanho. Suíte PHP 287/1.940 e Pint passaram. Evidência: `meta/market-provider-egress-limits-checks.json`.



## Egress geográfico sem política e limite — BUG 157

O contexto de bairro aceitava endpoints configurados privados e respostas externas com redirect e tamanho irrestritos. Todas as chamadas Google/OpenStreetMap agora exigem destino HTTPS público, recusam redirects e aplicam teto de 2 MiB no header, progresso e corpo final. A regressão confirma ausência de egress para loopback, metadata link-local e HTTP. Suíte PHP 291/1.952 e Pint passaram. Evidência: `meta/neighborhood-egress-limits-checks.json`.


## Consulta de CEP sem limite de resposta — BUG 158

O endpoint público de CEP seguia redirects e carregava resposta sem teto, além de aceitar campos incompatíveis com o cadastro. A integração agora desativa redirects/descompressão, limita 64 KiB em header, progresso e corpo final e valida os comprimentos e a UF. Suíte PHP 293/1.962 e Pint passaram. Evidência: `meta/postal-code-provider-guards.json`.


## Fallbacks da IA sem limite de transferência — BUG 159

Nestoria e Mercado Livre ainda seguiam redirects e carregavam respostas sem teto quando o scraper não retornava anúncios. O transporte agora verifica TLS, recusa redirects/descompressão e limita 2 MiB em header, progresso e corpo final. Suíte PHP 295/1.984 e Pint passaram. Evidência: `meta/ai-provider-transfer-guards.json`.


## Nome do arquivo ITBI sem autorização de campo — BUG 160

A leitura compartilhada da base ITBI revelava o nome original do último upload a qualquer usuário verificado. O campo agora depende de `itbi.import`; os dados públicos de transação permanecem acessíveis. A regressão compara usuário comum e operador sobre o mesmo lote. Suíte PHP 296/1.991 e Pint passaram. Evidência: `meta/itbi-filename-authorization-checks.json`.


## Status de fonte divergia da política de egress — BUG 161

O painel aceitava qualquer HTTPS como configurado, inclusive destinos que o cliente seguro bloqueia. A validação agora usa `ExternalUrlPolicy`; loopback, link-local, `.local`, localhost e rede privada aparecem como indisponíveis. Suíte PHP 297/1.996 e Pint passaram. Evidência: `meta/data-source-policy-alignment-checks.json`.



## Invariante automatizada da fronteira de autenticação — 18/10/2026

O inventário executável percorre todas as rotas `/api`: somente cadastro, login, recuperação/redefinição, CEP e confirmação assinada podem ser públicas; seis rotas de sessão/confirmação exigem autenticação e conta ativa antes da verificação; todas as demais exigem também e-mail confirmado. Upload ITBI, upload de documento e três rotas externas mantêm suas permissões/throttles específicos. A revisão estática não encontrou gravação com `$request->all()` ou payload bruto. SecurityRegressionTest 29/415 e suíte completa 299/2.249 passaram. Evidência: `meta/api-auth-boundary-checks.json`.


## Imagens com dimensões extremas — BUG 162

Fotos de até 5 MiB podiam declarar dimensões capazes de causar consumo excessivo na decodificação, e seus nomes eram expostos sem normalização. O upload agora limita lados/pixels e higieniza o nome antes da persistência. Suíte PHP 300/2.259 e Pint passaram. Evidência: `meta/opportunity-image-dimension-checks.json`.

## Armazenamento da análise podia interromper a navegação — BUG 163

Uma exceção em qualquer escrita de `sessionStorage` ou `localStorage` encerrava o clique em “Ver análise”. A persistência agora isola os dois mecanismos, continua quando um funciona e mostra erro acionável quando ambos falham. Frontend 65/65 e build de 2.312 módulos passaram. Evidência: `meta/ai-analysis-storage-resilience-checks.json`.

## Cache de notas podia derrubar o Wizard Financeiro — BUG 164

JSON local corrompido fazia uma análise válida parecer indisponível, e falhas de escrita podiam acionar o Error Boundary. O cache agora valida estrutura e limites, usa o dado remoto como fallback e trata indisponibilidade do armazenamento. Frontend 66/66 e build de 2.312 módulos passaram. Evidência: `meta/wizard-note-storage-checks.json`.

## localStorage bloqueado quebrava a autenticação — BUG 165

O token era manipulado diretamente por oito consumidores e qualquer `SecurityError` interrompia o fluxo. Um helper central não lançante mantém fallback em memória, centraliza limpeza e permite operar na aba atual quando a persistência é recusada. Frontend 69/69 e build de 2.313 módulos passaram. Evidência: `meta/auth-token-storage-checks.json`.

## Preferência local derrubava a Central Financeira — BUG 166

Consultar diretamente o estado do guia de score durante o render podia acionar o Error Boundary; uma falha de gravação também impedia fechá-lo. Os dois acessos agora são protegidos e o estado da interface permanece funcional. Frontend 70/70 e build de 2.313 módulos passaram. Evidência: `meta/score-guide-storage-checks.json`.

## Cache local inválido podia derrubar a análise — BUG 167

A leitura abandonava o fallback persistente quando a sessão lançava e aceitava qualquer JSON como contexto. Cada armazenamento agora é isolado e somente contexto com ID, itens, metadados e limites válidos chega ao render. Frontend 72/72 e build de 2.313 módulos passaram. Evidência: `meta/ai-analysis-cache-validation-checks.json`.

## Miniatura adulterada permitia egress local — BUG 168

A página de análise entregava a URL da miniatura armazenada diretamente ao navegador. O último ponto de uso agora exige HTTPS público e recusa destinos locais/privados, credenciais e portas alternativas. Frontend 73/73 e build de 2.313 módulos passaram. Evidência: `meta/ai-analysis-thumbnail-policy-checks.json`.

## Caminho local arbitrário era publicado como foto — BUG 169

Qualquer caminho não HTTP persistido em foto era transformado em `/storage/...`, inclusive traversal e namespaces alheios. A política compartilhada agora restringe leitura e exclusão ao namespace `opportunities/`. Suíte PHP 301/2.266 e Pint passaram. Evidência: `meta/opportunity-photo-path-policy-checks.json`.

## Resource financeiro contornava a política da capa — BUG 170

A Central e o Wizard recebiam uma capa serializada por regra permissiva própria. O resource agora exige HTTPS público para origem externa e namespace `opportunities/` para arquivo local. Suíte PHP 302/2.280 e Pint passaram. Evidência: `meta/investment-cover-url-policy-checks.json`.

## Resource financeiro expunha campos internos — BUG 171

A resposta de análise incluía `user_id`, `deleted_at` e chaves estrangeiras das relações por serialização ampla dos modelos. O contrato agora possui listas explícitas de campos para análise, financiamento, custos e resultado; dados usados pela interface foram preservados. Análises 12/106, suíte PHP 303/2.299 e Pint passaram. Evidência: `meta/investment-analysis-data-minimization-checks.json`.

## Etapas de projeto expunham campos internos — BUG 172

Projetos e endpoints de etapa serializavam modelos completos, incluindo tenant, vínculo interno e exclusão lógica. Um resource único agora define o contrato público em leitura, criação e edição, mantendo os campos operacionais usados pelas telas. Projetos relacionados 33/316, suíte PHP 304/2.320 e Pint passaram. Evidência: `meta/project-stage-data-minimization-checks.json`.

## CORS ignorava o frontend configurado — BUG 173

O deploy podia definir `FRONTEND_URL`, mas o CORS permanecia preso a origens locais fixas. A allowlist agora deriva dessa origem e de uma lista adicional explícita, rejeitando wildcard, credenciais, caminhos e esquemas incompatíveis. Preflight autorizado/negado, política 8 casos, cache de configuração, API 6/64, suíte PHP 306/2.330 e Pint passaram. Evidência: `meta/cors-origin-configuration-checks.json`.

## Patches compatíveis do frontend estavam atrasados — BUG 174

O lockfile mantinha `@types/react` 18.3.28 e `postcss` 8.5.30 embora as faixas declaradas aceitassem 18.3.31 e 8.5.31. Os patches foram aplicados, uma instalação limpa com scripts desativados foi validada, e audit, 73 testes e build de 2.313 módulos passaram. Nove migrações de versão principal permanecem registradas sem serem tratadas como vulnerabilidades. Evidência: `meta/frontend-dependency-refresh-checks.json`.

## Matriz omitia rotas implementadas — BUG 175

A reconciliação final detectou 16 caminhos da API sem linha própria na matriz, embora as proteções e testes já existissem. As rotas de autenticação complementar, fontes, documentos, calendário e tarefas foram documentadas com método, acesso, validação, limite e evidência. A comparação atual cobre 59/59 caminhos distintos, sem ausência ou entrada obsoleta. A segunda revisão repetiu 306/306 testes PHP, 73/73 frontend, build de 2.313 módulos, Pint, lint de 191 arquivos PHP, Composer/npm audit, cache de configuração e `git diff --check`. Evidência: `meta/final-regression-review-checks.json`.

## Identificador inválido de tarefa retornava 500 — BUG 176

O parâmetro `{tarefa}` não fazia parte da validação numérica compartilhada. Texto, negativo ou inteiro excessivo chegavam ao argumento `int` do controller; o caso textual foi reproduzido como HTTP 500. O middleware agora rejeita `tarefa` e valida redundantemente `tokenId` antes do controller. A regressão inicial passou 1/42; a revisão posterior ampliou a mesma prova para dez recursos, relações financeiras e 75 asserções. A suíte completa atual passou 308/2.388 e o Pint aprovou os arquivos. Evidências: `meta/task-route-identifier-checks.json` e `meta/resource-identifier-coverage-checks.json`.

## Linhas ITBI fora do schema variavam por banco — BUG 177

SQLite aceitava textos e decimais além das colunas declaradas; MariaDB/MySQL estrito poderia recusar as mesmas linhas. Um CSV com rua de 181 caracteres foi reproduzido como lote integralmente concluído antes da correção. O normalizador agora aplica todos os limites persistidos antes do banco, e esse arquivo termina de forma determinística como parcial, preservando a linha válida e contabilizando a inválida. Importação/normalizador 37/154, suíte PHP 308/2.355, frontend 73/73, build 2.313 módulos e Pint passaram. Evidência: `meta/itbi-schema-bound-normalization-checks.json`.

## Cobertura negativa complementar de filtros

Arrays enviados em filtros escalares de sete coleções foram rejeitados com 422 e erro de campo. A regressão permanente comprova a fronteira antes do query builder em projetos, vendas, despesas, categorias, fornecedores, documentos e oportunidades. Evidência: `meta/collection-filter-type-checks.json`.

## Binding e autorização permitiam enumeração — BUG 178

Contas não verificadas conseguiam distinguir IDs inexistentes de recursos existentes porque o model binding rodava antes da barreira de confirmação. Contas confirmadas também recebiam 403 para oportunidades/análises alheias e 404 para IDs ausentes. Conta ativa e confirmação agora precedem throttle, binding e `can`; policies e Form Request preservam 404 em negações de propriedade. A regressão dinâmica cobre todos os métodos empresariais, e a matriz negativa confirma nenhuma escrita alheia. Testes focados 57/801, suíte completa 311/2.761 e Pint passaram. Evidência: `meta/prebinding-authorization-checks.json`.

## Login público apagava sessão válida — BUG 179

O bearer atual era anexado inclusive ao login e qualquer 401 limpava a sessão. Um erro de senha em outra aba podia desconectar uma sessão válida, além de enviar a credencial sem necessidade a fluxos públicos. Login, cadastro, recuperação, CEP e confirmação agora não recebem bearer; somente 401 de chamada protegida que efetivamente o enviou limpa a sessão. Frontend 74/74 e build com 2.314 módulos passaram. Evidência: `meta/public-auth-session-isolation-checks.json`.

## Login distribuído contornava o limite por conta — BUG 180

O limite de login por conta incluía o IP na chave, permitindo novos orçamentos ao alternar origens. Um teto adicional de 30 tentativas por conta/hora agora usa o hash do e-mail normalizado, preservando também os limites de 10/IP/minuto e 5/conta+IP/minuto. A reprodução com 31 IPs passou a bloquear a última tentativa. Suíte 312/2.792 e Pint passaram. Evidência: `meta/distributed-login-rate-limit-checks.json`.

## Feedback após mutação confirmada — BUG 183

Dezesseis fluxos em Calendário, Documentos, Fornecedores, Despesas, Imóveis, Tarefas e Oportunidades não distinguiam a mutação já confirmada de uma falha na recarga subsequente. Os loaders agora retornam resultado explícito e cada ação informa que a gravação/exclusão ocorreu, mas a visão ficou desatualizada, orientando atualizar antes de repetir. A regressão específica 2/2, a suíte frontend 76/76, o build de 2.314 módulos e as automações visuais de Tarefas e Calendário passaram. Evidência: `meta/mutation-refresh-feedback-checks.json`.

## Feedback pós-mutação nos fluxos complexos — BUG 184

O mesmo defeito foi eliminado em mais 14 fluxos de Projetos, Central Financeira, importação ITBI e assistente financeiro. Criação/edição/exclusão de projeto, despesas e etapas, financiamento, simulações, custos, resultado e importação agora preservam a confirmação da escrita quando apenas a recarga falha. A interface orienta atualizar antes de repetir, evitando duplicidade e feedback falso. Verificação focada 11/11, frontend 77/77 e build de 2.314 módulos aprovados. Evidência: `meta/mutation-refresh-feedback-batch-2-checks.json`.

## Relatórios avançados sem trilha filtrada — BUG 185

O resumo acumulado de `/api/dashboard` foi preservado. A trilha contábil passou a ser `GET /api/reports/ledger`, com período, projeto da conta, paginação e totais do conjunto filtrado. Exportação da trilha percorre as mesmas páginas do filtro. Tenant ausente recebe 403; projeto alheio, 422. PHP 321/2.881, frontend 81/81 e build de 2.314 módulos. Evidência: `meta/financial-ledger-checks.json`. Reconciliação visual com fixture continua pendente.
