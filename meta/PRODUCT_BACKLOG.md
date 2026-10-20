# Evolução solicitada — 16/10/2026

Escopo adicional à auditoria; não substitui as pendências de segurança e QA.

1. [ ] Criar tela própria de erro: página não encontrada, falha inesperada, recuperação e navegação acessível; preservar respostas de API.
2. [ ] Criar cadastro completo: dados pessoais/empresa, endereço com CEP, validação no servidor, confirmação de senha, confirmação de e-mail com link assinado/expiração/reenvio limitado e feedback de envio. Entrega real de e-mail depende da configuração do provedor; testar com notificações simuladas/local sem afirmar entrega externa.
3. [ ] Melhorar IA em /ia-inteligente: revisar fontes, comparáveis, qualidade dos dados, confiança, ranking e avaliação com exemplos verificáveis. Identificar primeiro se há modelo treinável; não chamar ajuste de heurística de treinamento de modelo.
4. [ ] Corrigir abertura da lista de bairros: verificar cidade/UF, opções ausentes, seleção por mouse/teclado, busca, estado vazio e persistência de filtros.
5. [ ] Adicionar tema escuro: preferência persistida, respeito ao sistema, alternância acessível e revisão de formulários, tabelas, gráficos, modais e contraste.

## Ordem de execução

Tela de erro e seleção de bairros; estrutura de tema; cadastro/validação/confirmação; revisão de IA e avaliação. Testar cada entrega e atualizar seu status somente com evidência. Continuar a auditoria dos módulos e controles pendentes.

## Evidência a investigar da auditoria

Arquivo fictício em public/storage retornou redirecionamento para frontend em vez de conteúdo no servidor PHP local e no proxy Vite. Causa ainda em investigação; fixture removida. Não foi declarado corrigido nem atribuído a um bug definitivo. Verificação conjunta de arquivos internos foi bloqueada pela revisão automática; não realizada.

## Progresso — primeiro incremento

- Tela 404 própria implementada e renderizada no navegador; link de início testado. ErrorBoundary instalado para falhas de renderização, mas injeção de erro inesperado ainda não exercitada. Item 1 permanece em andamento até validar esse caminho e responsividade.
- Cidade/bairro agora usam lista explícita com botão, busca sem acentos/caixa, seleção por mouse e setas/Enter. Browser confirmou Moema por clique e Tatuapé pesquisando tatuape, inclusive cidade sao paulo sem acento. Campinas sem sugestões exibiu mensagem e aceitou texto livre. Tabela geográfica ainda parcial; item 4 permanece em andamento quanto à cobertura de dados.
- Build TypeScript/Vite aprovado. Cadastro, confirmação por e-mail, CEP, melhorias de IA e tema escuro ainda pendentes; não foram declarados entregues.

## Tema visual — incremento solicitado

Implementados ThemeProvider e ThemeSelector com Claro/Escuro/Sistema. Preferência flipcontrol:theme em localStorage; inicialização antes da montagem React, acompanhamento de prefers-color-scheme e sincronização por evento storage. Em armazenamento indisponível, tema funciona na aba, sem promessa de persistência. Não altera permissões nem dados de negócio.

Adicionadas variantes dark nas páginas/componentes existentes, estilos de formulários e gráficos. Seletor disponível no cabeçalho, login e Aparência das configurações. Cores funcionais e avisos recebem variantes escuras; não foi usada inversão de imagens.

Verificação real em navegador: escolher Escuro, navegar/recarregar e confirmar seleção preservada; escolher Claro pelas configurações, recarregar e confirmar controles sincronizados; retornar a Escuro. Renderizados painel, formulário de IA/lista de bairros aberta, configurações, login, menu móvel e 404. Build TypeScript/Vite e cinco testes existentes de frontend aprovados. Nenhuma alteração PHP neste incremento; suíte PHP anterior permanece 107/621.

Evidências visuais em outputs/auditoria: tema-escuro-bairros.jpg, tema-claro-configuracoes.jpg e tema-escuro-erro.jpg. Limitações: falta revisão de todos os estados/tabelas/modais e múltiplos viewports; mudança real da preferência do sistema e sincronização entre abas não foram exercitadas. Revisão formal de contraste/acessibilidade ainda pendente. Item 5 segue em andamento até completar essa cobertura.

## Cadastro e confirmação — incremento solicitado com Resend

Implementados /cadastro e /confirmar-email com dados pessoais/empresa, telefone, senha/confirmacão, endereço e consulta CEP. Backend gera identificador da operação se omitido, valida campos aninhados, UF, CEP e limite de bytes da senha. Novas contas ficam com requires_email_verification e módulos bloqueados até confirmação; contas existentes não são bloqueadas retroativamente. Migração 2026_10_16_000003 aplicada localmente.

E-mail com link assinado relativo e expiração de 60 minutos; confirmação pública exige assinatura e hash do e-mail. Conta/empresa inativa não é reabilitada. Reenvio exige autenticação e limites de 1/minuto, 5/hora por usuário e 10/hora por IP. Falha de envio preserva conta e expõe estado failed; mailer log/array é apresentado como local, sem alegação de envio externo.

Resend escolhido pelo usuário. SDK resend/resend-php 1.16.0 instalado; transporte com endpoint fixo, timeout e TLS verificado. Configuração real continua pendente por ausência de chave/remetente. Guia em RESEND_SETUP.md. Composer audit atual sem avisos.

Verificações: 116 testes PHP/674 asserções aprovados, incluindo bloqueio antes da confirmação, link alterado/expirado, conta inativa, reenvio, falha do provedor, campos indevidos e limite de senha. Transporte Resend simulado comprova requisição/mensagem sem envio a terceiros. Build TS/Vite aprovado.

UI real: cadastro fictício criado com endereço manual durante falha de TLS do CEP; tela informou envio apenas local. Link gerado pela notificação no scratch confirmou a conta pela interface e liberou o painel. Sessão fictícia encerrada; sessão demo original restaurada. Consulta real do CEP voltou a preencher Praça da Sé/Sé/São Paulo/SP após confiar no PHP no bundle de raízes públicas do Windows; nenhuma verificação TLS foi desativada. Screenshot cadastro-cep-validado.jpg em outputs/auditoria.

Pendências: entrega real pelo Resend, revisão completa da tela e dos novos endpoints, mais cenários concorrentes/abuso, dados cadastrais opcionais na API legada e política de confirmação retroativa. Nenhum token, senha ou chave foi copiado para relatórios. Item 2 permanece em andamento até concluir essas validações.

## IA — primeiro incremento de qualidade
BUG 42 corrigido em cinco cenários sintéticos: referência própria/duplicada, incompatibilidade de cidade/tipo/área e mistura ITBI entre municípios. Pontuação e faixa são apresentadas como heurísticas, sem calibração estatística. 121 testes/689 asserções e build aprovados. Banco local: zero transações ITBI; treinamento real e avaliação fora da amostra permanecem pendentes. Ver AI_EVALUATION.md.


## IA — qualidade temporal e geográfica
Avaliador v3: BUGs 43–46 corrigidos e testados. Janela de datas, número do endereço, distância sem funções SQL e contagem após outliers. 130 testes/704 asserções e build aprovados. Não houve treinamento, importação de dados reais nem benchmark de precisão. Item 3 continua em andamento.


## Decisão do usuário — 16/10/2026
Fotos de oportunidades permanecem públicas por link, conforme escolha explícita. Proposta privada não adotada; nenhuma foto existente movida.

Projetos: corrigido per_page=200 incompatível com limite 100 da API; carregamento percorre páginas, descarta respostas de seleção antiga e informa falhas. Modal de despesa permanece aberto quando POST falha; atualização após salvar preserva o modal montado e distingue falha de atualização de falha de gravação. Build TypeScript/Vite aprovado. Validação visual pendente.

## Projetos — regressão da paginação e totais
BUGs 48/49 documentados em QA_REPORT.md. 135 testes PHP/734 asserções, 12 helpers Node e build aprovados. Helpers cobrem páginas, resposta atrasada, cancelamento e total sem canceladas; a integração cobre API e isolamento real em banco de teste. Modal recebeu dialog nativo e erro interno, mas validação visual continua pendente: aquisição de aba recusada pela política da ferramenta de navegador; não contornar por outra superfície. Próxima revisão deve abranger ações do cabeçalho, Fornecedor/Filtros, falha de resumos e conversão análise→projeto em duas gravações independentes (possível propriedade órfã/duplicação, ainda sem teste de falha/concor­rência).

## Conversão financeira — atualização de evidência
BUG 50 implementado/testado: um POST transacional com vínculo persistido, idempotência, validação e autorização; migração aditiva aplicada ao SQLite local com snapshot anterior. 10 testes específicos/80 asserções; suíte 145/814; build aprovado. Dois workers MariaDB sobrepostos comprovam um só imóvel/projeto e sete etapas. Reconciliação de projetos legados não executada: descrição editável não vira vínculo automaticamente; 409 evita duplicação. Recuperação/validação visual continuam pendentes, assim como demais módulos do backlog.

## Cabeçalho e projeto manual — atualização do BUG 14
Três controles globais agora têm função real: projeto manual, atividades do dashboard e central de ajuda. Badge 3 removido. Estado sem imóvel abre Nova oportunidade por query, com limpeza ao cancelar. Validação estreita/teclado aprovada; screenshots preservadas. ManualProjectTest 2/17, suíte 147/831, Node 14 e build aprovados. Continuam pendentes CRUD visual completo de projetos, falhas de rede e módulos demonstrativos listados no relatório.

## Projetos/despesas — incremento de confiabilidade (16/10/2026)
BUGs 51/52 documentados em QA_REPORT.md. Projetos deixou de publicar somas parciais e zeros quando um resumo falha; percorre todas as páginas e permite nova tentativa. Despesas ganhou filtros, edição e pagamento reais; elementos sem backend foram retirados. ExpenseUpdateWorkflowTest, projectSummary.test.mjs, suíte 148/841, Node 16 e build aprovados. Pendente: fluxo visual completo com projeto/despesa descartável, falhas offline, foco, edição/exclusão de projeto e integração futura de fornecedores/anexos caso seja criada no backend.

## IA — correção do 502 no Windows (16/10/2026)
BUG 53 corrigido. O servidor web filtrava SystemRoot antes de iniciar Node, causando aborto CSPRNG e fallback bloqueado. Resolvedores explícitos e ambiente allowlist corrigem o subprocesso sem propagar dados HTTP. Busca real Perdizes: 36 anúncios, 20 renderizados na primeira página. Testes 154/866, Node 16 e build aprovados. Continuam pendentes estabilidade dos portais, execução de produção, benchmark de precisão e treinamento estatístico real.

BUG 54 corrigido durante o smoke final: cliente anônimo sem cabeçalho JSON recebia 500 por tentativa de redirecionar à rota backend `login`. A API agora responde 401 em ambos os formatos e preserva o redirecionamento web. Regressão automatizada e HTTP local aprovados.

BUG 55 corrigido: `/despesas` agora reflete a API e não uma coleção de demonstração. Totais, filtros, categorias, projetos, paginação, detalhe e pagamento usam dados persistidos; lançamento encaminha ao fluxo de projetos. CSV filtrado inclui proteção contra fórmula de planilha. Build e 18 testes Node aprovados; estado vazio/tema escuro/viewport estreito/console e navegação foram verificados. Cliques com despesa descartável e falha offline continuam pendentes.

BUG 56 corrigido: dez fornecedores/contatos fixos foram removidos e substituídos por CRUD tenant-aware. API, migração, validação, paginação, soft delete e UI real foram implementados. Quatro testes/36 asserções específicos, suíte 158/908, build e 18 Node aprovados. Estado vazio e formulário foram verificados sem gravar na conta demo; fluxo visual completo com fixture descartável continua pendente.

BUG 57 corrigido: `/documentos` deixou de exibir 128 arquivos/KPIs fictícios e ganhou CRUD tenant-aware com armazenamento privado, download autenticado, validação de arquivo, throttle e limpeza de órfão. `DocumentoTest` 6/61; suíte 164/976; build e smoke de dialog/foco/Escape aprovados. Pendente: fluxo visual com fixture descartável, antivírus/quarentena, criptografia/backup e storage de produção.

BUG 58 corrigido: `/relatorios` deixou de mostrar métricas milionárias, séries de 2024 e projetos fictícios. A página usa `/api/dashboard`, explicita escopo temporal, trata falha/vazio e exporta CSV seguro. Frontend 20/20 e build aprovados; conta vazia mostrou zeros/datas atuais e console limpo. BUG 185 acrescentou a trilha filtrada; reconciliação visual com fixture descartável continua pendente.

BUG 59 corrigido: calendário de maio/2024 e 26 eventos fictícios removidos. Novo CRUD tenant-aware, janela temporal/paginação, projeto da conta, intervalos e UI mensal real foram implementados. Específico 4/52; consolidado 168/1.034; build e smoke aprovados. Pendente: fixture visual completa, recorrência/lembretes/timezone e conectores externos.

BUG 60 encerrado por decisão de produto: tela Mensagens removida completamente, junto com rota, menu, badge e código backend não aplicado. URL antiga retorna 404. Não existe pendência de implementar mensageria neste escopo, salvo nova solicitação explícita.

BUG 61 corrigido: `/obras` não usa mais obras, custos, prazos ou etapas simulados. A tela usa projetos/resumos tenant-aware, oculta totais incompletos, atualiza etapas e encaminha ao cadastro real. Pendente: executar edição visual completa com projeto descartável e simular falha de resumo no navegador.

BUGs 62/63 corrigidos: `/imoveis` deixou de simular uma análise e agora executa CRUD real. A API limita coleção/campos, bloqueia usuário sem tenant e mantém isolamento. Pendente: CRUD visual completo com fixture descartável e falha offline. A exclusão com vínculos ativos foi bloqueada no BUG 76.

BUG 64 corrigido: `/financeiro` deixou de ser PageStub e usa o resumo financeiro tenant-aware, com estados reais e atalhos funcionais. Pendente: reconciliar a UI com fixture financeira descartável, simular falha/recovery e revisar acessibilidade da tabela em leitores de tela.

BUGs 65/66 corrigidos: `/tarefas` deixou de ser PageStub e ganhou CRUD tenant-aware com paginação, filtros, relações validadas, conclusão e exclusão lógica. Projeto agora serializa tarefas pelo resource explícito e omite excluídas/campos internos. Pendente: CRUD visual com fixture, falha offline, concorrência e revisão por leitor de tela.

BUG 67 corrigido: Configurações não declara mais integrações por constantes. Endpoint autenticado deriva runtime, credencial, HTTPS e dados ITBI sem expor valores sensíveis ou sondar terceiros; UI distingue sete estados e todos os totais. Pendente: health checks assíncronos, falha offline/viewport e implementação real das sete fontes marcadas como não implementadas.

BUG 68 corrigido: login ganhou recuperação de senha completa e resistente a enumeração. Token hashado/expirável/uso único, URL com fragmento, validação bcrypt, rate limits e revogação de sessões foram testados. Pendente: entrega ponta a ponta com Resend real, alertas de troca, bounce/complaint e telemetria de abuso.

BUG 69 corrigido: logout não assume mais token pessoal. Bearer remove só o token atual; sessão web é encerrada/invalida e não autentica a próxima chamada. Pendente: lista/revogação de dispositivos, “Sair de todos” voluntário e validação de cookies no proxy HTTPS de produção.

BUG 70 corrigido: a SPA recebe CSP, bloqueio de frame, nosniff, política de referência, COOP e Permissions Policy em Vite dev/preview; o build inclui manifesto `_headers`. 34 testes frontend, build, HTTP real e recarga autenticada sem console errors passaram. Pendente: confirmar a aplicação dos headers e HSTS no proxy/CDN HTTPS público.

BUG 71 corrigido: toda exceção em `/api/*` retorna JSON sem depender de `Accept`; validação não redireciona mais à SPA, diagnóstico interno é removido e 5xx permanece genérico mesmo com debug local. `ApiHeadersTest` 4/54, suíte 188/1.240, Pint específico e HTTP real passaram. Pendente: confirmar debug desativado no deploy.

BUG 72 corrigido: o roteador local não entrega mais dotfiles/configuração nem extensões não permitidas; `X-Powered-By` foi removido dos caminhos testados. Apache/PHP receberam defesa equivalente. Cinco testes/29 asserções, suíte 189/1.252, Composer validate, Pint e HTTP real passaram. Pendente: validar as mesmas regras e `APP_DEBUG=false` no proxy/Nginx/CDN público.

BUG 73 corrigido: removido endpoint sem uso que publicava limites de upload/memória do PHP a qualquer conta verificada. Regressão e HTTP real retornam 404; inventário atualizado contém 90 rotas. `ImportSecurityTest` 6/34 e suíte 190/1.254 passaram. Pendente: alinhar o limite do proxy de produção ao contrato de upload.

BUG 74 corrigido: excluir oportunidade agora remove fotos locais públicas e seus metadados com quarentena privada e restauração em falhas. Caminhos fora da área gerenciada são recusados atomicamente. Três regressões, `OpportunityTest` 15/75 e suíte 193/1.270 passaram. Pendente: validar lifecycle, CDN/cache e retenção no storage escolhido para produção e concluir a galeria visual.

BUG 75 corrigido: exclusão de documento agora mantém banco e storage consistentes por lock, transação e quarentena privada. Falha de leitura preserva o registro e retorna 503 neutro; falha de banco restaura o arquivo; caminho não gerenciado é recusado. `DocumentoTest` 9/76 e suíte 196/1.285 passaram. Pendente: validar storage/CDN, retenção, backup e scanner antimalware do ambiente de produção.

BUG 76 corrigido: propriedade com projeto, venda ou despesa ativa não pode mais ser ocultada; DELETE retorna 409 e preserva todos os registros. Após exclusão lógica das dependências, a remoção é permitida. `PropriedadeTest` 6/51 e suíte 198/1.299 passaram. Pendente: fluxo visual completo com fixture e simulação de falha de rede.

BUG 77 corrigido: excluir projeto agora encerra etapas, tarefas e eventos na mesma transação, impedindo itens operacionais órfãos. Despesas e documentos continuam preservados como histórico. `DomainIntegrityTest` 10/94 e suíte 200/1.312 passaram. Pendente: validar visualmente as três coleções com fixture descartável.

BUG 78 corrigido: filtros de projetos, vendas, despesas, categorias e oportunidades rejeitam enums/booleanos desconhecidos e faixa de preço invertida com 422. Testes específicos 2/27, relacionados 25/248 e suíte 202/1.339 passaram. Pendente: revisar semanticamente filtros das demais integrações e executar casos visuais de erro.

BUG 79 corrigido: as rotas assinadas, porém não utilizadas, de leitura/upload do disco privado foram desativadas. O inventário caiu para 88 rotas; GET/PUT retornam 404 sem criar arquivo e o disco público continua 200. `SecurityRegressionTest` 24/141 e suíte 203/1.342 passaram. Pendente operacional: validar storage/CDN e política de URLs públicas no ambiente definitivo.

BUG 80 corrigido: o contexto de bairro não fabrica mais estação, escola, hospital, comércio ou distância quando mapas falham, nem completa grupos reais vazios com valores sintéticos. Falha retorna indisponível; somente fonte externa ou cache de coleta válida gera pontos. `NeighborhoodContextTest` 2/20 e suíte 205/1.362 passaram. O item 3 continua em andamento até importar dados reais suficientes e executar benchmark de precisão; nenhum treinamento estatístico é alegado.

BUG 81 corrigido: links e miniaturas vindos dos portais passam por allowlist HTTP/HTTPS na saída da busca; `javascript:`, `data:` e demais esquemas são eliminados. `AIPropertySearchTest` 3/17 e suíte 206/1.366 passaram. Pendente: observabilidade contínua de mudanças no HTML/contrato dos provedores.

BUG 82 corrigido: o Chromium do scraper bloqueia redes locais/privadas/link-local/metadados e hostnames que resolvem para qualquer IP interno, em páginas e redirecionamentos. Frontend 38/38, sintaxe, build e `npm audit --omit=dev` passaram. Pendente operacional: firewall de egress e monitoramento de bloqueios no ambiente implantado.

BUG 83 corrigido: o fallback PHP não segue mais redirects automaticamente; cada salto precisa permanecer em HTTPS, host explícito de portal e DNS inteiramente público. Testes específicos 11/15 e suíte 217/1.381 passaram. Pendente operacional: egress e DNS confiáveis no ambiente implantado.

BUG 84 corrigido: HTML de portal no fallback PHP tem teto de 5 MiB em headers, progresso e verificação final; excesso recebe falha neutra. Testes específicos 3/9 e suíte 218/1.384 passaram. Pendente operacional: limites de processo/container para PHP e Chromium.

BUG 85 corrigido: a Central Financeira não exibe mais benchmarks sem fonte, metas “ideais”, confiança estatística ou percentil fictício. Cenários de sensibilidade expõem venda/custos/prazo e usam os valores informados como base; atrasos simulados foram removidos. `InvestmentAnalysisTest` 8/55, suíte 218/1.393, frontend 38/38, Pint e build passaram. Pendente: validar pesos e limiares com dataset histórico e especialista antes de alegar acurácia ou recomendação personalizada.

BUG 86 corrigido: removidas dez taxas bancárias estáticas, período fixo e falsa atribuição ao BCB. A simulação usa somente instituição/taxa informadas e mostra anual equivalente/limitações. `InvestmentAnalysisTest` 9/67, suíte 219/1.405, frontend 38/38, Pint e build passaram. Pendente: integração oficial/licenciada com CET, produto e data antes de reintroduzir comparador automático.

BUG 87 corrigido: a prévia financeira deixou de dividir ROI linearmente pelos meses e passou a usar a mesma equivalência composta do backend. Cenários de 100%/12 meses e 21%/6 meses, perda total e entrada inválida têm regressões. `InvestmentAnalysisTest` 10/73, suíte 220/1.411, frontend 40/40, Pint e build com 2.308 módulos passaram.

BUG 88 corrigido: áreas persistidas agora respeitam a capacidade `DECIMAL(10,2)`; análise de mercado limita área/preço/cômodos e rejeita chaves desconhecidas. Frontend envia payload mínimo. Reprodução anterior retornou 201/200; correção retorna 422. Testes específicos 2/16, relacionados 26/159 e suíte 222/1.427 passaram; Pint e build foram aprovados.

BUG 89 corrigido: PUT/PATCH de projeto não responde mais 200 para `etapas`/`orcamento_categorias` que seriam descartados. O request devolve 422 sem alterar o projeto; criação aninhada e endpoints próprios de etapa continuam disponíveis. `ManualProjectTest` 3/24, relacionados 25/209 e suíte 223/1.434 passaram.

BUG 90 corrigido: término de etapa, fechamento e escritura agora respeitam a ordem cronológica. Atualização parcial de venda cruza o novo valor com proposta/fechamento já persistidos. Reproduções anteriores retornaram 201/200; agora retornam 422, e o caso válido continua 200. Relacionados 18/146 e suíte 226/1.450 passaram.

BUG 91 corrigido localmente: múltiplas propostas permanecem permitidas, mas somente uma venda ativa pode estar fechada/escriturada por imóvel. Serviço bloqueia propriedades e retorna 422 no conflito; movimentação sincroniza origem/destino. Relacionados 16/142 e suíte 227/1.459 passaram. A disputa real foi posteriormente aprovada com dois processos MariaDB sobrepostos: 201/422, uma venda final e imóvel vendido.

BUG 92 corrigido: resumo de projeto não trata mais proposta/negociação/cancelamento como receita realizada; apenas venda fechada/escriturada alimenta ROI. Regressão antes/depois da finalização, relacionados 13/123, suíte 228/1.468, Pint e build passaram.

BUG 93 corrigido: venda fechada/escriturada exige data de fechamento e venda escriturada exige data de escritura, inclusive ao mudar estado em atualização. A inconsistência anterior foi reproduzida com 201; relacionados 17/151, suíte 229/1.480 e Pint global passaram.

BUG 94 corrigido: etapas excluídas não reaparecem no detalhe/resumo nem são percorridas pelo recálculo de custos. A reprodução devolvia duas etapas para uma ativa; relacionados 16/152, suíte 229/1.486 e Pint global passaram.

BUG 95 corrigido: conclusão de projeto persiste data real informada ou automática, preserva-a em edições e limpa-a na reabertura; combinações temporais inválidas retornam 422. Relacionados 20/181, suíte 230/1.497 e Pint global passaram.

BUG 96 corrigido: editar uma tarefa já concluída sem reenviar a data não altera mais o marco histórico; primeira conclusão e reabertura continuam normalizadas. Relacionados 16/179, suíte 230/1.500 e Pint global passaram.

BUG 97 corrigido: despesas pagas recebem/preservam data de pagamento e estados não pagos limpam o marco; o fluxo padrão da tela deixa de criar pagamento sem data. Relacionados 15/148, suíte 231/1.509 e Pint global passaram.

BUG 98 corrigido: custos detalhados pagos recebem/preservam `paid_at`, e retorno a pendente/atrasado limpa o marco. Relacionados 16/127, suíte 232/1.518 e Pint global passaram.

BUG 99 corrigido: atualização parcial de projeto cruza início/término enviados com os valores persistidos e bloqueia cronograma invertido nos dois sentidos. Relacionados 21/190, suíte 233/1.527 e Pint global passaram.

BUG 100 corrigido localmente: `vendida` só pode corresponder a uma venda final ativa; criação e atualizações divergentes retornam 422 sob lock compartilhado com o serviço de vendas. Relacionados 20/196, suíte 234/1.543 e Pint global passaram. A disputa multiprocesso MariaDB foi posteriormente aprovada para esse fluxo.

BUG 101 corrigido: status e percentual das etapas são normalizados pelos três caminhos de escrita; pares contraditórios retornam 422. Criação concluída resulta em 100%, mudança apenas de status/progresso sincroniza o outro campo. Relacionados 17/176, suíte 235/1.561 e Pint passaram.

BUG 102 corrigido: exclusão de projeto com despesa ou documento ativo retorna 409 e preserva todos os registros; após arquivamento explícito, a exclusão segue normalmente. Foco 3/21, suíte 236/1.569 e Pint passaram. Corrida multiprocesso de criação/exclusão permanece pendente.

BUG 103 corrigido no código: escritas revalidam imóveis/projetos sob locks compartilhados com a exclusão; movimentações bloqueiam origem/destino em ordem estável e falhas de documento limpam o arquivo. Relacionados 31/283, suíte 238/1.573 e Pint passaram. Pendente executar a disputa em processos separados num banco transacional.

BUG 104 corrigido no código: tarefas e eventos participam de transações, bloqueiam projetos antigos/novos e revalidam o destino antes da escrita. Foco 2/10, suíte 239/1.575 e Pint passaram. Prova concorrente real continua agrupada na pendência de banco transacional.

BUG 105 corrigido no código: atualização de projeto bloqueia imóveis de origem/destino e o projeto, revalidando o destino dentro da transação. Relacionados 26/237, suíte 239/1.576 e Pint passaram. PUT/DELETE simultâneos permanecem pendentes.

BUG 106 corrigido: seis campos de texto livre persistido agora têm teto de 5.000 caracteres e retornam 422 sem escrita quando excedidos. Teste específico 3/34, suíte 240/1.594 e Pint passaram.

BUG 107 corrigido: ordem de etapa respeita `INTEGER` e `max_price` respeita o teto monetário; excesso retorna 422 antes de banco ou provedor. Teste específico 4/42, suíte 241/1.602 e Pint passaram.

BUG 108 corrigido: paginação comum rejeita `page` acima de 1.000.000 antes de calcular OFFSET; o valor máximo de 64 bits deixou de retornar 200. Relacionados 13/142, suíte 242/1.605 e Pint passaram.

BUG 109 corrigido: PATCH de projeto aceita somente os campos alterados, preservando os demais; POST/PUT continuam completos e regras temporais permanecem efetivas. Relacionados 10/78, suíte 243/1.611 e Pint passaram.

BUG 110 corrigido: PATCH de venda não exige reenvio de imóvel/valor e preserva campos omitidos; PUT incompleto continua 422. Relacionados 24/224, suíte 244/1.622 e Pint passaram.

BUG 111 corrigido: PATCH de imóvel aceita alteração isolada e preserva apelido, valores e endereço; PUT incompleto continua 422. Relacionados 25/236, suíte 245/1.631 e Pint passaram.

BUG 112 corrigido: PATCH de despesa é parcial e valida propriedade/projeto/etapa/tarefa efetivos, bloqueando mudança isolada para etapa externa. Relacionados 21/211, suíte 246/1.647 e Pint passaram.

BUG 113 corrigido: PATCH de tarefa preserva campos/status/data omitidos e valida etapa isolada contra o projeto persistido; PUT continua completo. Teste 5/76, suíte 247/1.662 e Pint passaram.

BUG 114 corrigido: PATCH de evento preserva campos omitidos e valida início/fim contra o intervalo persistido nos dois sentidos; PUT continua completo. Teste 5/68, suíte 248/1.678 e Pint passaram.
BUG 115 corrigido: PATCH de documento aceita metadado isolado e preserva categoria, projeto, status e identidade do arquivo; PUT continua completo e upload na edição permanece proibido. Teste 11/92, suíte 249/1.692 e Pint passaram.
BUG 116 corrigido: PATCH de fornecedor aceita alteração isolada e preserva campos omitidos; POST/PUT continuam exigindo nome e categoria. Teste 5/47, suíte 250/1.703 e Pint passaram.
BUG 117 corrigido: PATCH de oportunidade aceita alteração isolada e preserva cadastro e galeria omitidos; PUT continua exigindo título, tipo e fonte. Teste 16/89, suíte 251/1.717 e Pint passaram.
BUG 118 corrigido: contexto de bairro rejeita chaves desconhecidas e área acima do domínio antes de chamar provedores externos. Teste 3/25, suíte 252/1.722 e Pint passaram.
BUG 119 corrigido: páginas carregam sob demanda; entrada caiu de 1.123,42 kB para 269,46 kB e nenhum chunk excede 500 kB. Frontend 40/40 e build com 2.309 módulos passaram.
BUG 120 corrigido: grade dos filtros da IA não cria mais rolagem horizontal; 375/768/1265/1440 px, tema escuro, bairros e menu móvel foram validados em navegador real. Frontend 40/40 e build passaram.

BUG 121 corrigido: oportunidades legadas sem timestamps não derrubam mais a coleção; regressão e suíte 253/1.726 passaram.

BUG 122 corrigido: tabelas extensas permanecem contidas no próprio painel em telas móveis. A carga isolada com 28 registros por módulo foi exercitada em 375/768/1440 px; frontend 40/40 e build passaram.

BUG 123 corrigido: `npm test` agora executa a suíte frontend convencionalmente; 40/40 testes e build passaram.

BUG 124 corrigido: os seis modais principais têm nome acessível e devolvem o foco ao disparador após Escape. Validação real 6/6, frontend 40/40 e build passaram. Pendente: auditoria formal de contraste e leitor de tela completo.
Revalidação visual de 17/10/2026: cadastro, 404 de `/mensagens` e recuperação de senha passaram nos breakpoints exercitados, em tema escuro e sem erros de console. Nenhum novo bug confirmado.
Varredura responsiva de 17/10/2026: 16 rotas × 3 viewports passaram em estados vazios, sem overflow ou console; tabelas extensas/populadas continuam pendentes.
