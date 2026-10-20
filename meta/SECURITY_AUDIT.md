# Auditoria de segurança — em andamento

## Método e superfície

Inspeção de código real e testes locais isolados com SQLite em memória, dados fictícios, HTTP fakes e Storage fakes. Referências: [OWASP API1 BOLA](https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization/) e [OWASP Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html). São referências metodológicas, não certificação.

Entradas: cadastro/login; filtros e paginação; CRUD imobiliário, projetos, despesas e vendas; oportunidades/fotos; análises, financiamento, custos e resultados; upload de planilhas; consulta de mercado e entorno. Dados sensíveis: hashes de senha, bearer tokens, compradores, finanças e endereços. APIs usam bearer token; CSRF do fluxo de cookie não substitui autenticação de tokens. Frontend guarda token em localStorage: risco residual em caso de XSS.

## Problemas confirmados por testes antes da correção

1. ALTA: usuário autenticado sem tenant acessava imóveis de todos os tenants (TenantScope). Corrigido com consulta vazia para esse caso. security-before.json.
2. ALTA: conta desativada podia obter e usar token (AuthService/middleware). Login e chamadas existentes bloqueados; tenant desativado também bloqueado. security-before.json e security-before-extra.txt.
3. MÉDIA: login sem limitação; tentativas repetidas não recebiam 429. Limitadores por conta/IP e IP; cadastro e API também limitados.
4. MÉDIA: tokens sem expiração. Novos tokens expiram em 12 horas, e Sanctum aplica idade máxima aos anteriores.
5. MÉDIA: paginação inválida aceita. Validação central de paginação e filtros.
6. ALTA: qualquer conta podia alcançar importação da base ITBI compartilhada. Agora exige permissão itbi.import no backend; operador autorizado testado com CSV real fictício.
7. ALTA: endereço aninhado aceitava tenant_id e outros campos extras. Lista explícita de chaves e limites de coordenadas. Também aplicada a etapas e categorias aninhadas.
8. MÉDIA: exceções dos provedores eram devolvidas diretamente. Teste com marcador privado comprova remoção na resposta; mensagens neutras nos provedores e importador.

## Hardening implementado

Verificação TLS restaurada na consulta Nestoria; limites monetários e de senha; senha de demonstração retirada da tela e seeder restringido a local/testing; proteção de XML contra DTD, expansão excessiva e índice de coluna fora dos limites. Esses controles não foram classificados como exploração externa comprovada.

Falhas de recarga após mutações confirmadas passaram a ser diferenciadas em 16 ações de sete módulos. Isso reduz repetição acidental de POSTs e ações destrutivas quando a escrita foi aceita, mas a consulta seguinte falhou. O BUG 183 e `mutation-refresh-feedback-checks.json` registram a cobertura; trata-se de integridade e feedback, não de vulnerabilidade de autorização.

## Dependências

Arquivos composer-audit-before/after.json e npm-audit-before/after.json preservam o resultado dos scanners. PHP atualizado sem troca de versão principal do framework. Router, Puppeteer e Vite atualizados com build aprovado; a integração real do scraper ainda não foi validada após atualização. Sete alertas npm permanecem e exigem tratamento.

## Modelo de autorização

Recursos legados são compartilhados entre usuários do mesmo tenant; oportunidades e análises são privadas por user_id. Não há perfil administrativo global implícito. Manutenção da base pública ITBI exige concessão explícita pelo operador no terminal: php artisan itbi:authorize EMAIL. Não foi concedida permissão automaticamente à conta de demonstração. Leitura de transações públicas continua disponível a contas autenticadas. A matriz de rotas lista a cobertura e as verificações pendentes.

## Atualização de dependências e resposta API

A pendência histórica de 7 avisos npm está resolvida: npm-audit-after-tailwind4.json contém contagem total zero. Migração Tailwind 4 executada e compilada; não se confunde resultado do scanner com garantia de segurança total. Middleware ApiSecurityHeaders impede armazenamento em cache das respostas API, incluindo login; regressão automatizada aprovada. Os cabeçalhos de frontend/servidor de produção exigem validação específica do deploy.

## Revisão de integridade de planilhas
BUG 37 confirmado e corrigido: cabeçalhos duplicados normalizados e colunas não vazias excedentes rejeitados. Orçamento cumulativo ODS configurável adicionado como hardening; não há alegação de teste de exaustão. Detalhes e reprodução em QA_REPORT.md.


## Pipeline de arquivos ITBI — BUG 39
Recusa de gravação e exceções de conexão testadas com mocks. Metadados deixam de avançar sem arquivo gravado; mensagens externas são neutras. Downloads válidos preservam bytes/hash. Política de URLs, redirecionamentos, tamanho remoto e atomicidade filesystem/banco permanecem pendentes.


## Destinos de descoberta/download ITBI — BUG 40
HTTPS, hosts exatos configurados e cada redirecionamento passam por ItbiRemoteFileClient. Seis testes adicionais, todos com HTTP fake; não foi demonstrada exploração de SSRF contra rede real. Fonte configurada e download relativo legítimos permanecem funcionais. DNS/pinagem, proxy, egress e tamanho remoto continuam pendentes. Detalhes em QA_REPORT.md.


## Limites de transferência e base de descoberta
BUG 41 corrigido: links usam URL final validada após redirecionamento. Hardening inclui limites independentes de download/HTML em headers, progresso e tamanho final, com descompressão HTTP automática desativada. Testes fake exercitam callbacks diretamente; transferência real/proxy/egress e capacidade continuam pendentes. Ver QA_REPORT.md para escopo e reprodução.


## Verificação real — interrupção de transferência HTTPS

Data: 16/10/2026. Servidor Node HTTPS temporário, escutando exclusivamente em 127.0.0.1:18443; certificado fictício audit.test gerado localmente, confiado somente por esta chamada. Cliente PHP/Laravel/Guzzle/cURL real, sem Http::fake. CURLOPT_CONNECT_TO direcionou audit.test:443 para o listener local; proxy desativado somente no harness. Nenhum DNS externo, chamada a terceiros ou modificação da confiança do sistema.

Limite configurado no harness: 4096 bytes. /small entregou exatamente 4096 e foi aceito. /headers anunciou 65536 e foi interrompido pelo callback on_headers: servidor registrou zero bytes de corpo enviados. /chunked não declarou Content-Length; callback progress interrompeu a resposta após o servidor enviar 4352 dos 65536 bytes planejados. O valor excede o limite por um bloco de 256 bytes; o controle aborta ao observar excesso, não promete limite exato de bytes na rede. Resultado agregado passed=true.

Evidências: remote-transfer-client-results.json e remote-transfer-server-results.json. Scripts locais em work/remote-transfer; chave privada de teste permanece no scratch e não foi copiada para relatórios. Processo temporário identificado pelo comando e encerrado após o teste. Banco da aplicação não foi modificado.

Escopo: comprova callbacks de headers/progresso com o handler cURL instalado e conexão TLS local verificada. Não comprova comportamento de proxy/deploy, DNS/egress, outros handlers, carga, memória total ou processamento completo de arquivos no limite padrão. As pendências correspondentes continuam abertas.

## Cadastro e confirmação — incremento solicitado com Resend

Implementados /cadastro e /confirmar-email com dados pessoais/empresa, telefone, senha/confirmacão, endereço e consulta CEP. Backend gera identificador da operação se omitido, valida campos aninhados, UF, CEP e limite de bytes da senha. Novas contas ficam com requires_email_verification e módulos bloqueados até confirmação; contas existentes não são bloqueadas retroativamente. Migração 2026_10_16_000003 aplicada localmente.

E-mail com link assinado relativo e expiração de 60 minutos; confirmação pública exige assinatura e hash do e-mail. Conta/empresa inativa não é reabilitada. Reenvio exige autenticação e limites de 1/minuto, 5/hora por usuário e 10/hora por IP. Falha de envio preserva conta e expõe estado failed; mailer log/array é apresentado como local, sem alegação de envio externo.

Resend escolhido pelo usuário. SDK resend/resend-php 1.16.0 instalado; transporte com endpoint fixo, timeout e TLS verificado. Configuração real continua pendente por ausência de chave/remetente. Guia em RESEND_SETUP.md. Composer audit atual sem avisos.

Verificações: 116 testes PHP/674 asserções aprovados, incluindo bloqueio antes da confirmação, link alterado/expirado, conta inativa, reenvio, falha do provedor, campos indevidos e limite de senha. Transporte Resend simulado comprova requisição/mensagem sem envio a terceiros. Build TS/Vite aprovado.

UI real: cadastro fictício criado com endereço manual durante falha de TLS do CEP; tela informou envio apenas local. Link gerado pela notificação no scratch confirmou a conta pela interface e liberou o painel. Sessão fictícia encerrada; sessão demo original restaurada. Consulta real do CEP voltou a preencher Praça da Sé/Sé/São Paulo/SP após confiar no PHP no bundle de raízes públicas do Windows; nenhuma verificação TLS foi desativada. Screenshot cadastro-cep-validado.jpg em outputs/auditoria.

Pendências: entrega real pelo Resend, revisão completa da tela e dos novos endpoints, mais cenários concorrentes/abuso, dados cadastrais opcionais na API legada e política de confirmação retroativa. Nenhum token, senha ou chave foi copiado para relatórios. Item 2 permanece em andamento até concluir essas validações.

## Router de arquivos locais — BUG 47
Entrega da junction public/storage corrigida com raiz canônica autorizada, fronteira de diretório e tipos permitidos. HTTP real de PNG/HEAD validado; quatro testes unitários e suíte 134/721 aprovados naquela rodada. Nenhum arquivo privado real foi solicitado ou foto real alterada. Fotos do disco public continuam públicas por decisão explícita do usuário; a alternativa documentada em PHOTO_ACCESS_PROPOSAL.md não foi adotada.


## Atualização de política e regressão — 16/10/2026
Fotos públicas por link foram mantidas por decisão explícita do usuário. A alternativa privada não foi adotada e não existe migração de fotos aguardando aprovação. Isso não muda a necessidade de revisar exclusão, órfãos e servidor de produção. ExpensePaginationTest comprova que paginação dentro do limite não atravessa tenants; limite 100 preservado. BUGs 48/49/51/52 são achados funcionais/lógicos de UI, sem nova vulnerabilidade de autorização confirmada. Suíte PHP consolidada: 158 testes/908 asserções; validação visual de despesas com registros continua pendente e está delimitada em QA_REPORT.md.

## Integridade da conversão de análise — BUG 50
Protocolo frontend com duas gravações independentes substituído por operação de backend autorizada/transacional/idempotente. Novo vínculo protegido de overposting; dono sem tenant recebe 403. Achado classificado como lógica/funcional de severidade média; não há evidência de acesso indevido entre contas nessa conversão. Testes negativos e concorrência real descritos em QA_REPORT.md. FKs e índice único adicionados por migração local não destrutiva; nenhum projeto legado adotado por descrição. Escopo de UI continua pendente; suíte consolidada atual 158 testes/908 asserções aprovada.

## Ambiente do subprocesso da busca de imóveis — BUG 53
O servidor web local filtrava o ambiente herdado antes de iniciar Node; no Windows isso removia SystemRoot e causava aborto CSPRNG/502. ExecutableLocator resolve Node/Chrome explicitamente e childEnvironment repassa somente variáveis de runtime em allowlist mais CHROME_PATH/SCRAPER_DISABLE_SANDBOX controladas. Cabeçalhos HTTP, autorização, URI e demais dados da requisição não são propagados ao scraper. Teste unitário comprova a exclusão. O endpoint mantém autenticação, verificação de conta e rate limit market-data. Isso corrige o servidor local testado; configuração e isolamento do subprocesso no deploy continuam pendentes. Suíte consolidada atual: 158 testes/908 asserções.

## Autenticação de clientes API genéricos — BUG 54
O middleware de autenticação tentava gerar uma URL para a rota nomeada `login` quando um cliente anônimo não enviava `Accept: application/json`. Como a SPA não registra essa rota Laravel, a API devolvia 500 em vez de 401. O callback de convidados agora retorna `null` para qualquer caminho `/api/*` e mantém `/login` para fluxos web. Testes cobrem ambos os formatos; uma chamada HTTP real sem sessão confirmou 401. Não houve relaxamento de autenticação nem criação de rota pública.

## Exportação de despesas
A exportação adicionada ao substituir a página demonstrativa não replica HTML nem concatena fórmulas executáveis. Campos iniciados, após espaços, por `=`, `+`, `-` ou `@` recebem apóstrofo, aspas são duplicadas e todas as células ficam delimitadas. Testes cobrem fórmula, aspas, separador, UTF-8 e centavos. O arquivo continua sendo conteúdo controlado pelo usuário e deve ser tratado como dado ao ser aberto; a neutralização reduz o risco de CSV injection nos aplicativos de planilha usuais.

## Isolamento do novo módulo de fornecedores — BUG 56
As cinco rotas exigem Sanctum, conta/tenant ativos e e-mail verificado pelo grupo existente. O model usa TenantScope e atribuição automática do tenant autenticado. Request rejeita tipos/formats fora do contrato; campos de tenant, exclusão e privilégios não são preenchíveis pelo payload validado. Testes negativos demonstram coleção vazia e 404 para leitura, atualização e exclusão de outro tenant, além de 403 para criação sem tenant. A exclusão lógica preserva rastreabilidade. Não há perfis internos distintos para fornecedores; qualquer usuário válido do tenant possui CRUD, coerente com o modelo atual mas sujeito a futura política de papéis.

## Documentos privados por tenant — BUG 57
A tela fictícia foi substituída por armazenamento privado e seis endpoints protegidos por Sanctum, conta ativa e confirmação de e-mail. TenantScope limita coleção e IDs alheios retornam 404 em leitura, download, atualização e exclusão. O caminho físico é UUID sob diretório do tenant e não integra o resource; nome original é normalizado. Upload valida allowlist de formatos e 10 MB e aplica `document-upload` (10/min usuário, 20/min IP). O download autenticado usa `no-store`, `private` e `nosniff`. Erros de storage não expõem detalhes e falha após gravação limpa o arquivo. Testes negativos cobrem mass assignment, projeto alheio, tipo executável, excesso de tamanho e arquivo órfão. Isso não comprova scanner antimalware, criptografia em repouso, armazenamento distribuído, backup ou servidor de produção; esses pontos permanecem residuais. Suíte atual: 164 testes/976 asserções.

## Integridade dos relatórios — BUG 58
A página de relatórios consome `/api/dashboard` para o painel acumulado e `GET /api/reports/ledger` para a trilha filtrada. Ambos exigem Sanctum, conta ativa/verificada e TenantScope. O ledger ainda exige tenant; projeto alheio retorna 422. Os CSVs de resumo e da trilha são gerados localmente a partir das respostas autorizadas e neutralizam células iniciadas por `=`, `+`, `-` ou `@`. A exportação da trilha percorre as páginas do mesmo filtro. `DashboardTest` e `FinancialLedgerTest` comprovam isolamento; testes Node cobrem os dois exportadores.

## Isolamento do calendário — BUG 59
Cinco endpoints de eventos exigem Sanctum, conta ativa e confirmação de e-mail. EventoCalendario usa TenantScope; autor/tenant vêm da sessão e projeto opcional precisa existir no mesmo tenant. IDs alheios retornam 404 e a coleção fica vazia. Intervalos, categorias, status, comprimentos e paginação são validados. A consulta por janela inclui eventos que se sobrepõem ao período. Testes negativos cobrem mass assignment, tenant ausente e IDOR entre duas contas. Não há integração externa, lembretes ou recorrência, portanto não há tokens de calendários de terceiros neste módulo.

## Remoção do módulo demonstrativo Mensagens — BUG 60
Por decisão explícita do usuário, a rota e a UI foram removidas integralmente. Isso elimina a apresentação de contatos, mensagens, telefone/e-mail e anexos fictícios como se fossem dados da conta. Nenhuma API/tabela de mensagens existia no estado aplicado; a implementação interna iniciada durante a auditoria foi descartada antes de criar rota ou executar migração. A URL antiga cai no 404 da SPA e não há link no menu. Não há alegação de mensageria disponível.

## Integridade da tela de Obras — BUG 61
A substituição dos dados fictícios não criou rotas novas. A tela usa a coleção `/api/projetos`, o resumo e o PATCH de etapa já incluídos no grupo Sanctum/conta ativa/e-mail confirmado. O backend localiza a etapa e o projeto dentro do tenant autenticado, rejeita ID alheio como 404 e valida status em allowlist e progresso inteiro entre 0 e 100. `DomainIntegrityTest` cobre IDOR e valores inválidos. O frontend não publica totais financeiros quando falta qualquer resumo, reduzindo risco de decisão baseada em resposta parcial. Ainda falta repetir a edição pela UI com fixture descartável e reavaliar concorrência de atualizações de etapa; isso não é alegado como aprovado.

## Endurecimento de propriedades — BUG 63
A coleção de propriedades agora usa FormRequest próprio: usuário autenticado sem tenant recebe 403; status é allowlist; busca tem 120 caracteres; página precisa ser positiva e `per_page` fica entre 1 e 100. O CRUD limita contagens físicas e observações para evitar overflow/consumo desnecessário. `deletado_flg` não é mais mass assignable e só o serviço o altera explicitamente. Os controllers continuam usando `validated()`, portanto `tenant_id` enviado pelo cliente é ignorado e o trait atribui o tenant autenticado. `PropriedadeTest` demonstra coleção própria, 404 em leitura/PUT/DELETE alheios, campos privilegiados ignorados e limites 422. O teste de segurança foi fortalecido de coleção vazia 200 para bloqueio 403 sem tenant. Ainda não há papéis internos diferentes para CRUD de imóveis; qualquer usuário válido da conta possui acesso, conforme o modelo atual.

## Superfície do painel Financeiro — BUG 64
O novo frontend não adiciona endpoint, permissão ou mutação. Ele reutiliza `GET /api/dashboard`, protegido por Sanctum, conta ativa, e-mail confirmado e TenantScope nos modelos consultados. O browser recebe somente o mesmo resumo já usado por Dashboard/Relatórios. O helper normaliza valores não finitos para evitar quebra visual, mas não substitui os cálculos do servidor. `DashboardTest` comprova isolamento entre duas contas, exclusão de despesa cancelada e métricas principais. A validação atual não prova segregação por papéis internos nem trilha contábil completa; esses riscos permanecem documentados.

## Tarefas tenant-aware e serialização explícita — BUGs 65/66

As cinco rotas de tarefas exigem Sanctum, conta ativa e confirmação de e-mail pelo grupo protegido. `TarefaIndexRequest` e `TarefaRequest` exigem tenant e validam paginação, filtros, textos, datas, estados, prioridades, custos e IDs relacionados. Projeto, etapa e responsável precisam pertencer ao tenant; a etapa também precisa pertencer ao projeto. `TarefaService` deriva o tenant da sessão, normaliza a data de conclusão e executa exclusão lógica explícita. IDs alheios resultam em 404 pelo TenantScope.

O recurso de projeto deixou de serializar models de tarefa diretamente. `TarefaResource` limita os campos públicos e relações; `Projeto::tarefas()` exclui registros logicamente removidos. Isso impede vazamento de `tenant_id`/`deletado_flg` e o reaparecimento de tarefa excluída. `TarefaTest` demonstra CRUD próprio, paginação, filtros, limites, mass assignment, usuário sem tenant, relações alheias e IDOR de leitura/PUT/DELETE. Suíte atual: 176 testes/1.135 asserções. Não há papéis internos distintos para tarefas; qualquer usuário válido do tenant possui CRUD conforme o modelo atual.

## Diagnóstico seguro de integrações — BUG 67

`GET /api/data-sources/status` exige Sanctum, conta ativa, e-mail confirmado e tenant. O endpoint realiza apenas inspeção local; não dispara chamadas a terceiros ao abrir Configurações. A resposta não contém valores de credenciais, tokens, URLs de endpoints ou caminhos de executáveis. Configurado significa somente presença de credencial ou endpoint HTTPS; disponibilidade externa continua sendo verificada pelas operações que usam cada provedor. Endpoints ausentes, inválidos ou HTTP não recebem estado configurado. `DataSourceStatusTest` demonstra bloqueios de autenticação/conta, estados derivados e ausência de quatro valores sensíveis injetados. Suíte atual: 179 testes/1.161 asserções. Ainda não existe controle de papéis para restringir o diagnóstico a administradores da conta.

## Recuperação de senha resistente a enumeração — BUG 68

`POST /api/auth/password/forgot` valida e normaliza e-mail, mas sempre responde 202 com corpo idêntico; apenas conta/tenant ativos recebem notificação. Rate limiting combina IP e hash do e-mail normalizado. O broker Laravel gera token aleatório, armazena apenas hash e aplica expiração/throttle. A notificação customizada coloca token/e-mail no fragmento da URL frontend, reduzindo exposição em request inicial, logs HTTP e referrer. Falha do mailer é neutralizada e o log contém somente ID interno e tipo da exceção.

`POST /api/auth/password/reset` valida token/e-mail e senha confirmada de 12–72 caracteres/72 bytes, aplica limites por IP/conta e devolve erro genérico para token ou conta inválidos. Conta/tenant são reavaliados antes da gravação. Sucesso gira `remember_token`, consome o token e revoga todos os tokens Sanctum. Regressões confirmam hash, expiração, uso único, suspensão, anti-enumeração, falha de entrega, limite e invalidação de sessão. Suíte atual: 184 testes/1.200 asserções. Risco residual: entrega real e comportamento do provedor Resend não foram validados sem credenciais; e-mail, como todo canal de recuperação, depende da segurança da caixa postal do usuário.

## Invalidação do logout por bearer e sessão — BUG 69

O logout agora identifica `PersonalAccessToken` antes de excluir. Bearer encerra somente o dispositivo atual; tokens paralelos permanecem válidos conforme o comportamento documentado. Autenticação por sessão executa logout do guard web, invalida o armazenamento da sessão e regenera o token CSRF. Isso elimina o 500 causado por token nulo e evita responder sucesso mantendo cookie autenticado. Regressões demonstram o 401 posterior em ambos os mecanismos. Suíte atual: 186 testes/1.204 asserções. Encerramento global de todos os dispositivos continua disponível somente pela redefinição de senha; não existe ainda uma ação voluntária separada “Sair de todos”.

## Política do documento frontend — BUG 70

A entrega da SPA não possuía cabeçalhos de segurança, apesar de a API já aplicar um middleware próprio. A política adicionada bloqueia framing por CSP e X-Frame-Options, restringe script/objeto/base/formulário, desativa câmera, microfone, geolocalização, pagamento e USB, elimina referrer e ativa `nosniff`/COOP. As origens externas permitidas correspondem ao código atual: Google Fonts, IBGE e imagens HTTPS. A política de produção não inclui script inline nem `unsafe-eval`; o modo dev admite inline somente para React Refresh e `ws`/`wss` para HMR.

HTTP real confirmou os headers em Vite dev e preview; a página autenticada recarregou sem erro de console. O arquivo `_headers` é copiado ao `dist`, mas não é um padrão universal: o proxy/CDN/servidor público precisa consumi-lo ou configurar os mesmos valores. HSTS não foi enviado em HTTP local e deve ser habilitado somente no terminador HTTPS de produção após validação do domínio e subdomínios.

## Neutralização global de exceções da API — BUG 71

O handler não classificava o caminho `/api/*` como suficiente para JSON. Clientes sem `Accept` recebiam redirects ou páginas HTML; a primeira reprodução confirmou 302 na validação de login e HTML no 404. O bootstrap agora força JSON para toda a superfície API. O pós-processamento remove classe da exceção, arquivo, linha e trace, além de substituir qualquer 5xx por mensagem genérica, inclusive com debug ativo. Erros 4xx mantêm somente dados públicos necessários, como campos de validação.

O middleware de headers passou a expor uma aplicação reutilizável para que exceções ocorridas antes do reconhecimento da rota também recebam `no-store`, `nosniff`, `no-referrer` e `DENY`. Regressão com RuntimeException e marcador privado comprova ausência do texto e do caminho base. Isso não substitui a exigência de `APP_DEBUG=false` em produção; evita que uma configuração de debug acidental exponha detalhes pela API. Suíte atual: 188 testes/1.240 asserções.

## Allowlist de arquivos públicos e ocultos — BUG 72

O roteador PHP local tratava todo arquivo dentro de `public` como ativo estático permitido. A exploração controlada leu `.user.ini` e `.htaccess` sem autenticação; não foi necessário traversal. A correção diferencia ativos web da raiz e formatos públicos de `/storage`, bloqueia segmentos ocultos e extensões executáveis/configuração e devolve 404 para arquivos físicos negados. Symlinks continuam limitados ao diretório público autorizado de storage por caminho canônico.

Defesa complementar foi adicionada para Apache (`FilesMatch` de dotfiles e remoção do header), PHP CGI/FPM (`expose_php=Off`) e servidor local (`-d expose_php=0` e `header_remove`). Teste HTTP confirmou ausência do header em estático, API e health. Nginx/CDN não estão presentes no repositório; o deploy deve reproduzir as regras e impedir dotfiles, backups, mapas/fontes não pretendidos e indexação. Suíte atual: 189 testes/1.252 asserções.

## Minimização da configuração exposta — BUG 73

`GET /api/itbi-imports/upload-limits` serializava três diretivas PHP para qualquer conta verificada. A rota não era usada pelo frontend e não controlava a validação efetiva do upload, portanto foi removida em vez de apenas restringida. A API retorna 404 sem os nomes dos parâmetros; o upload permanece protegido por permissão, throttle e validação própria. O inventário atual contém 90 rotas. Limites de PHP/proxy continuam sendo configuração operacional privada.

## Remoção atômica de fotos de oportunidades — BUG 74

A exclusão lógica deixava fotos locais no disco público sem registro ativo, mantendo acesso por URL e criando conteúdo órfão. O serviço agora restringe caminhos ao namespace gerenciado, transfere os arquivos para quarentena privada, remove a cópia pública e conclui metadados/soft delete na mesma unidade lógica. Qualquer falha anterior ao commit restaura o arquivo público e o banco; caminho inesperado recebe erro neutro e nenhuma alteração. URLs externas não são tratadas como arquivos locais. Três regressões cobrem sucesso, caminho não gerenciado e rollback forçado; `OpportunityTest` passou 15/75 e a suíte completa 193/1.270. Fotos de oportunidades ativas continuam públicas por link conforme decisão do usuário; CDN/storage de produção e políticas de retenção ainda precisam de validação operacional.

## Exclusão atômica de documentos privados — BUG 75

A exclusão marcava o documento como apagado antes da remoção física e não revertia o banco quando o storage falhava. Isso ocultava o único metadado gerenciável e deixava bytes órfãos. O serviço agora valida o caminho contra o tenant, bloqueia o registro, transfere o arquivo para quarentena privada e atualiza o banco em transação; falhas restauram arquivo e registro. O controller devolve 503 neutro para falhas esperadas de storage. Nove testes/76 asserções do módulo e a suíte 196/1.285 passaram. O storage/CDN definitivo, backup, antivírus e retenção operacional continuam fora da evidência local.

## Integridade referencial na exclusão de propriedade — BUG 76

A exclusão lógica escondia a propriedade mesmo com projeto, venda ou despesa ativo. A operação agora bloqueia a linha em transação, consulta dependências não excluídas dentro do tenant e devolve 409 sem alteração quando alguma existe. Vínculos já excluídos logicamente não bloqueiam a limpeza posterior. Isso preserva a entidade raiz dos registros financeiros e operacionais sem apagar histórico. `PropriedadeTest` passou 6/51 e a suíte consolidada 198/1.299.

## Encerramento transacional dos dependentes de projeto — BUG 77

A exclusão lógica do projeto deixava tarefas e eventos visíveis e etapas ativas no banco. O serviço agora usa lock e transação para encerrar tarefas, etapas e eventos antes do projeto. A prova prévia encontrou tarefa órfã na coleção; a regressão final confirma ausência nas APIs e flags consistentes. Despesas e documentos permanecem como histórico. `DomainIntegrityTest` passou 10/94 e a suíte consolidada 200/1.312.

## Contrato estrito dos filtros de coleção — BUG 78

Coleções importantes aceitavam enums arbitrários, booleanos inválidos e faixa de preço invertida, retornando 200 enganoso. O middleware comum agora aplica allowlists específicas por rota e verifica a ordem da faixa de oportunidade. O ORM já impedia injeção; a correção garante contrato previsível e reduz consultas inúteis. Dois testes/27 asserções específicos, 25/248 relacionados e suíte 202/1.339 passaram.

## Rotas diretas do disco privado desativadas — BUG 79

O disco privado `local` habilitava o servidor temporário do framework e registrava GET/PUT em `/storage/{path}`. As rotas exigiam assinatura válida: requisições sem assinatura retornaram 403 antes da correção, portanto não foi comprovada exposição ou escrita anônima. A busca no código não encontrou geração de URL temporária nem consumidor desses endpoints; documentos usam download autenticado próprio.

`serve` foi desativado no disco privado, eliminando os dois endpoints sem alterar o disco público. O inventário caiu para 88 rotas. Regressão impede o retorno dos nomes `storage.local*`; HTTP real confirmou 404 para GET/PUT, ausência de arquivo privado e 200 para fixture pública descartável. `SecurityRegressionTest` passou 24/141 e a suíte completa 203/1.342.

## Integridade de proveniência no entorno da IA — BUG 80

O serviço de contexto geográfico convertia falha externa em dados sintéticos com nomes, categorias e distâncias fixas, marcados como conectados e somados pelo frontend. Também completava silenciosamente categorias vazias de uma resposta real. A interface não separava esses itens da coleta confirmada, podendo influenciar avaliação de liquidez com evidência fabricada.

Todos os geradores locais de pontos foram removidos. Somente respostas reais de Overpass/Nominatim ou cache de uma coleta real anterior podem produzir locais. Falha total retorna `unavailable` e grupos vazios; resposta parcial não é completada. Dois testes/20 asserções cobrem falha e parcialidade, e a suíte 205/1.362 passou. Coordenadas de fallback não são convertidas em estabelecimentos; cobertura e atualidade continuam dependentes dos provedores.

## Esquemas seguros para links de anúncios — BUG 81

Itens externos do scraper chegavam à resposta sem uma allowlist final de `source_url` e `thumbnail`; esses campos alimentam `href` e `src` no React. Os destinos de coleta são fixos e não houve execução comprovada, mas uma página externa comprometida poderia injetar esquema incompatível ou executável. A API agora normaliza todos os provedores e aceita somente HTTP/HTTPS válidos; qualquer outro esquema vira `null`. Regressão com `javascript:` e `data:` passou, assim como o caso HTTPS legítimo. `AIPropertySearchTest` 3/17 e suíte 206/1.366 foram aprovados.

## Egress do navegador headless — BUG 82

O interceptador do scraper bloqueava apenas tipos de recurso pesados; HTML remoto podia induzir conexões do servidor a loopback, rede privada, link-local ou metadados de nuvem. Uma política central agora valida esquema/credenciais/hostname/IP, resolve DNS e recusa o hostname se qualquer resposta não for pública. Ela roda em todas as requisições das páginas principais e do enriquecimento opcional, inclusive redirecionamentos.

Testes cobrem IPv4, IPv6, representação decimal de loopback, metadados e DNS misto. Os 38 testes frontend, sintaxe e build passaram; auditoria npm de produção retornou zero vulnerabilidades conhecidas. Controle de aplicação reduz o risco, mas firewall de egress e bloqueio de metadados na infraestrutura continuam necessários como defesa independente e contra DNS rebinding.

## Redirecionamentos do fallback HTML — BUG 83

Quando o Chromium não encontrava itens, o fallback PHP requisitava os portais fixos, mas permitia que Guzzle seguisse qualquer `Location`. Um portal comprometido podia transformar a origem confiável em SSRF contra rede interna. O cliente agora não segue redirecionamentos automaticamente: cada um dos até três saltos é resolvido e precisa ser HTTPS, sem credenciais/fragmento/porta alternativa, host exato da lista e DNS exclusivamente público.

Testes unitários verificam IPs privados/reservados, DNS misto, host e esquema; testes HTTP falsos comprovam bloqueio antes da segunda requisição e preservação do redirecionamento autorizado. O conjunto passou 11/15 e a suíte 217/1.381. Essa checagem complementa a política do Chromium, mas egress de infraestrutura permanece necessário.

## Transferência limitada do HTML externo — BUG 84

O fallback PHP mantinha timeout, mas não limite de corpo. Um portal podia obrigar o worker a acumular resposta arbitrariamente grande antes do parse. O cliente agora limita 5 MiB pelo header, progresso e tamanho final, solicita `identity` e desativa expansão automática. Um corpo de 5 MiB + 1 foi neutralizado sem chegar ao parser. `PortalScraperSecurityTest` passou 3/9 e a suíte 218/1.384. Limites de memória/CPU do processo e do Chromium continuam sendo responsabilidade complementar do ambiente.

## Integridade das recomendações financeiras — BUG 85

A Central Financeira misturava cálculos reais com percentuais estáticos de produtos financeiros, metas contrafactuais sem recálculo e uma medida de preenchimento chamada de confiança. Embora não fosse uma falha de acesso, o comportamento comprometia a integridade de uma decisão financeira e podia ser interpretado como análise de IA ou comparação de mercado comprovada.

A interface agora apresenta somente fatos calculados e cenários de sensibilidade com todas as premissas expostas. O backend identifica o método como `rules_based_financial_calculation`, usa o valor de venda informado como base e publica completude dos dados, sem probabilidade ou confiança estatística. Benchmarks sem fonte, percentil fictício, metas “ideais”, aconselhamento direto de compra e esperas simuladas foram removidos. Testes relacionados 9/68, suíte 219/1.406, frontend 38/38, Pint e build passaram. Os limiares e pesos do índice continuam regras de produto e precisam de validação empírica antes de qualquer alegação de acurácia ou aconselhamento financeiro.

## Proveniência das taxas de financiamento — BUG 86

O simulador atribuía uma tabela estática de dez bancos ao Banco Central e a um período específico, apesar de a integração BCB estar explicitamente não implementada. Esses valores alimentavam parcela, saldo, quitação e ROI. A tabela e a seleção automática foram removidas; somente taxa e instituição fornecidas pelo usuário entram no cálculo, com anual equivalente derivada e aviso explícito sobre CET/TR/seguros. Testes específicos 9/67, suíte 219/1.405, frontend 38/38, Pint e build passaram. Consulta oficial e atualização temporal permanecem ausentes e não são alegadas.

## Consistência dos retornos financeiros — BUG 87

A prévia React calculava ROI mensal por divisão linear e anualizava esse resultado, enquanto o backend usava equivalência composta. A divergência podia superestimar materialmente o retorno apresentado antes da persistência: 100% em 12 meses resultava em cerca de 161% anualizado na interface contra 100% no backend. Um helper único de frontend agora aplica a mesma fórmula composta do PHP e trata perda total sem gerar valor não finito. Os rótulos residuais de aconselhamento também foram neutralizados. Regressões passaram 40/40 no frontend, 10/73 no módulo PHP e 220/1.411 na suíte completa; Pint e build foram aprovados.

## Limites de entrada coerentes com o armazenamento — BUG 88

Campos de área persistidos em `DECIMAL(10,2)` aceitavam pela validação valores quatro ordens de grandeza acima da capacidade da coluna. SQLite mascarava a divergência e respondeu 201 para 100.000.000 m²; um banco estrito pode gerar erro operacional ou truncamento. O endpoint de análise também aceitava números sem teto e propriedades extras no objeto selecionado. A validação agora bloqueia o excesso antes da gravação ou do acesso aos provedores, restringe o objeto às chaves necessárias e mantém o frontend compatível com payload explícito. Regressões específicas 2/16 e suíte 222/1.427 passaram; não foi executada gravação destrutiva em banco de produção.

## Sucesso parcial silencioso em projeto — BUG 89

O contrato aceitava estruturas aninhadas de etapas na atualização, mas o serviço as removia antes da persistência e ainda retornava 200. Embora não fosse escalonamento de privilégio, isso comprometia integridade e auditabilidade: o cliente não conseguia distinguir alteração aplicada de conteúdo descartado, e campos simples podiam mudar no mesmo request. Atualizações agora recusam as estruturas sem semântica segura de substituição e não modificam nada. Criação aninhada e endpoints de etapa permanecem separados. Regressão específica e suíte completa 223/1.434 passaram.

## Integridade temporal — BUG 90

Relações cronológicas não eram verificadas nas etapas e vendas. O backend aceitava término anterior ao início, fechamento anterior à proposta e escritura anterior ao fechamento. Atualizações parciais poderiam contornar uma comparação limitada ao request. A validação agora usa o par correto em cada etapa e, na venda, combina os campos recebidos com o registro existente dentro do tenant. Cenários inválidos retornam 422 antes da escrita; cenário parcial válido permanece funcional. Suíte completa 226/1.450 passou.

## Venda final única por imóvel — BUG 91

Várias vendas finais ativas podiam coexistir para o mesmo imóvel, enquanto dashboard e ROI aplicavam deduplicações diferentes. O serviço agora serializa a transição pela linha da propriedade e recusa uma segunda venda `fechada/escriturada`. Ao mover uma venda, as duas propriedades são bloqueadas em ordem determinística para reduzir risco de deadlock e seus estados são recalculados. Testes funcionais e transacionais passaram na suíte 227/1.459. A disputa específica foi executada depois com dois processos sobrepostos no MariaDB 11.4.10: 201/422, uma venda final ativa e imóvel vendido. A eficácia desse lock está comprovada nesse cenário isolado.

## Critério de venda no ROI do projeto — BUG 92

O resumo de projeto considerava qualquer venda não excluída, inclusive proposta e cancelamento, enquanto outras superfícies exigiam estado final. Isso permitia integridade financeira divergente sem adulterar permissões. A consulta foi restringida a `fechada/escriturada` e coberta por regressão antes/depois da finalização. A suíte 228/1.468 e o build passaram.

## Estados finais sem datas correspondentes — BUG 93

O contrato permitia declarar uma venda realizada sem fechamento ou escritura. Como os KPIs selecionavam pelo estado e a série temporal dependia da data, a mesma venda podia aparecer como receita realizada e faltar no desempenho mensal. A validação agora exige os marcos correspondentes ao estado efetivo tanto na criação quanto em atualizações, preservando a comparação cronológica existente. A reprodução anterior retornou 201; após a correção, 17 testes relacionados/151 asserções e a suíte 229/1.480 passaram, e o Pint global foi aprovado.

## Exclusão lógica inconsistente nas etapas — BUG 94

A relação de projeto expunha etapas marcadas como excluídas e o recálculo ainda as percorria. A falha não rompe isolamento entre tenants, mas violava a política de exclusão lógica e podia republicar dados e custos obsoletos. O filtro foi centralizado na relação e repetido no recálculo. A reprodução anterior retornou duas etapas em vez de uma; após a correção, detalhe e resumo retornam somente o registro ativo. Testes relacionados 16/152, suíte 229/1.486 e Pint global passaram.

## Estado de projeto sem marco real — BUG 95

Projetos podiam ser concluídos sem data real, e o campo enviado era descartado por falta de regra no request. Isso não oferece elevação de privilégio, mas enfraquece a integridade temporal e a auditabilidade. A invariante agora é aplicada no backend: conclusão define/preserva o marco, reabertura o remove, e datas incompatíveis retornam 422. A reprodução anterior recebeu 200 com data nula; testes relacionados 20/181, suíte 230/1.497 e Pint global passaram.

## Marco de conclusão mutável em edição de tarefa — BUG 96

Uma edição comum de tarefa concluída podia substituir sua data histórica pelo dia atual porque o serviço não consultava o valor persistido. A correção preserva o marco em edições, mantendo criação automática somente na primeira conclusão e limpeza na reabertura. A reprodução alterou 10/10 para 17/10 antes da correção; testes relacionados 16/179, suíte 230/1.500 e Pint global passaram.

## Estado de pagamento sem marco contábil — BUG 97

Despesas pagas podiam persistir sem `data_pagamento`, inclusive pelo fluxo padrão da tela de projeto, e estados cancelados podiam manter uma data antiga. A invariante foi centralizada no serviço para todos os clientes: pagamento cria/preserva o marco e qualquer estado não pago o remove. A reprodução recebeu 201 com data nula antes da correção; testes relacionados 15/148, suíte 231/1.509 e Pint global passaram.

## Quitação sem data nos custos da análise — BUG 98

Custos detalhados aceitavam estado pago sem `paid_at`, reduzindo a rastreabilidade das projeções e resultados. A normalização passou ao request comum de criação/edição e considera o registro autorizado pelo controller: primeira quitação cria, edição preserva e reversão limpa o marco. A reprodução anterior persistiu nulo; testes relacionados 16/127, suíte 232/1.518 e Pint global passaram.

## Bypass temporal por atualização parcial de projeto — BUG 99

A comparação declarativa das datas não incluía o valor persistido ausente do request. Um cliente autorizado podia, portanto, inverter o cronograma por atualização parcial e receber 200. A validação agora cruza payload e registro tenant-scoped nos dois sentidos. A regressão confirma 422 e banco inalterado; testes relacionados 21/190, suíte 233/1.527 e Pint global passaram.

## Estado vendido manipulável fora do fluxo de venda — BUG 100

O status do imóvel podia ser alterado independentemente da venda final, rompendo uma invariante financeira entre recursos. A correção aplica a regra no serviço sob lock da propriedade: somente uma venda final ativa autoriza `vendida`, e sua existência impede qualquer outro status solicitado. O mesmo lock é usado pelo serviço de vendas. Três desvios retornam 422 e a edição legítima permanece funcional; testes relacionados 20/196, suíte 234/1.543 e Pint global passaram. A prova multiprocesso específica continua pendente conforme BUG 91.

## Estado contraditório nas etapas de projeto — BUG 101

Os campos de estado e progresso podiam contradizer-se em três endpoints, afetando integridade e auditabilidade do andamento. A correção centraliza a invariante no serviço: estado final exige 100%, pendente exige 0% e estados intermediários ficam abaixo de 100%. Payloads explicitamente incompatíveis recebem 422, e atualizações parciais são sincronizadas. A reprodução anterior criou `concluida/0`; testes relacionados 17/176, suíte 235/1.561 e Pint passaram.

## Projeto excluído com histórico ativo — BUG 102

A exclusão lógica de projeto deixava despesas e documentos ativos ligados a um recurso oculto. O serviço agora consulta ambos dentro da transação e recusa a operação com 409, preservando o projeto e o histórico. Dependentes operacionais continuam arquivados somente quando não há registros protegidos. A reprodução recebeu 200 antes da correção; foco 3/21, suíte 236/1.569 e Pint passaram. Concorrência entre criação de vínculo e exclusão permanece como teste operacional pendente.

## TOCTOU entre validação e exclusão do pai — BUG 103

Projeto, despesa e documento confiavam na existência verificada pelo request antes da transação. A exclusão concorrente podia vencer antes da escrita. Os serviços agora compartilham locks de imóvel/projeto com as exclusões, ordenam múltiplos IDs e revalidam o destino ativo sob o lock. Falha de upload também limpa o arquivo preparado. Testes sequenciais de revalidação passaram com a suíte 238/1.573; a eficácia em processos realmente concorrentes continua explicitamente pendente.

## Dependente operacional criado após limpeza do projeto — BUG 104

Tarefas e eventos também tinham uma janela entre FormRequest e escrita. Como a exclusão arquiva apenas registros já existentes, uma criação tardia permanecia ativa. Os serviços agora usam transações e o mesmo lock de projeto, inclusive em movimentações. Testes determinísticos bloqueiam ambos os registros com pai excluído; suíte 239/1.575 e Pint passaram. Falta a execução simultânea contra banco transacional para comprovar a espera real entre processos.

## Projeto movido para imóvel excluído — BUG 105

A edição de projeto não compartilhava os locks adicionados à criação e à exclusão de imóvel. O serviço agora bloqueia origem/destino, revalida o destino e bloqueia o projeto dentro de uma única transação. A simulação com estado alterado rejeita o destino e preserva a origem; suíte 239/1.576 e Pint passaram. Ainda falta executar PUT/DELETE simultâneos num banco com locks reais.

## Campos TEXT sem limite de entrada — BUG 106

Seis campos persistidos aceitavam texto arbitrariamente grande. A diferença entre SQLite e MySQL podia converter um request válido na aplicação em falha/truncamento no banco, além do custo de processamento. Os requests agora limitam cada campo a 5.000 caracteres. Regressão cobre todos, confirma 422 e nenhuma escrita; suíte 240/1.594 e Pint passaram.

## Valores numéricos fora da capacidade — BUG 107

Ordem de etapa podia exceder `INTEGER` e preço máximo de busca não tinha teto. A validação agora usa a capacidade da coluna e o teto monetário do domínio, bloqueando os valores antes de banco/scraper. Regressão específica 4/42, suíte 241/1.602 e Pint passaram.

## Paginação extrema sem teto — BUG 108

Todas as coleções aceitavam páginas positivas até o limite do runtime. Isso produzia offsets sem utilidade e dependentes do driver. O middleware central passou a limitar página a 1.000.000; o valor máximo de 64 bits que retornava 200 agora recebe 422. Relacionados 13/142, suíte 242/1.605 e Pint passaram.

## PATCH de projeto não era parcial — BUG 109

Embora publicado como PATCH, o endpoint exigia nome e propriedade. Isso forçava clientes a reenviar estado não alterado e ampliava a chance de sobrescrita. O request agora distingue PATCH de POST/PUT, preservando campos omitidos e todas as regras cruzadas. Regressão recebeu 422 antes e 200 após; suíte 243/1.611 e Pint passaram.

## PATCH de venda forçava reenvio financeiro — BUG 110

Editar um campo secundário exigia reenviar imóvel e valor, ampliando risco de overwrite. PATCH agora é parcial; PUT continua completo, e validações usam o estado persistido para datas e finalização. A regressão preserva cinco campos e confirma PUT inválido; suíte 244/1.622 e Pint passaram.

## PATCH de imóvel forçava reenvio do apelido — BUG 111

O contrato parcial exigia um campo não alterado. O request agora diferencia PATCH de POST/PUT, e o serviço preserva todas as chaves omitidas. A regressão recebeu 422 antes, depois confirmou 200 com endereço e valores intactos; suíte 245/1.631 e Pint passaram.

## PATCH de despesa e vínculo parcial — BUG 112

O endpoint forçava reenvio financeiro e suas relações eram comparadas apenas quando ambos os IDs apareciam no request. A validação agora combina valores enviados e persistidos, impedindo etapa/tarefa incompatível mesmo num PATCH mínimo. Regressão confirma preservação, 422 no desvio e PUT completo; suíte 246/1.647 e Pint passaram.

## PATCH de tarefa e estado persistido — BUG 113

O contrato exigia o recurso quase completo e não cruzava uma etapa isolada com o projeto persistido. Request e serviço agora usam projeto/status efetivos, preservando o marco de conclusão e bloqueando etapa externa. Regressão cobre PATCH legítimo, desvio relacional e PUT incompleto; suíte 247/1.662 e Pint passaram.

## PATCH de evento e intervalo persistido — BUG 114

O contrato exigia quase todo o evento e a validação cronológica só enxergava datas reenviadas juntas. O request agora aceita PATCH parcial e compara as datas efetivas, combinando o payload com o registro autorizado. A regressão cobre preservação de campos, inversão por cada extremidade e PUT incompleto; suíte 248/1.678 e Pint passaram.
## PATCH de documento e preservação de arquivo — BUG 115

A atualização parcial exigia categoria e induzia clientes a reenviar estado. O request agora distingue PATCH de POST/PUT sem permitir upload na edição. A regressão compara banco e armazenamento para provar que metadados privilegiados e arquivo físico permanecem intactos; suíte 249/1.692 e Pint passaram.
## PATCH de fornecedor e preservação cadastral — BUG 116

A atualização parcial exigia os campos centrais e induzia reenvio de estado persistido. O request agora diferencia PATCH de POST/PUT; a regressão prova preservação dos demais dados e rejeição do PUT incompleto. Suíte 250/1.703 e Pint passaram.
## PATCH de oportunidade e preservação de mídia — BUG 117

O request de atualização reutilizava integralmente o contrato de criação. Agora somente PATCH torna título, tipo e fonte opcionais, sem relaxar PUT, autorização ou regras de fotos. A regressão prova a preservação do cadastro e da galeria; suíte 251/1.717 e Pint passaram.
## Entrada do contexto de bairro antes de egress — BUG 118

A rota aceitava chaves arbitrárias e área sem teto antes de consultar serviços geográficos. A validação agora fecha a estrutura e alinha a área ao limite do domínio. A regressão capturou 200 antes, 422 após e ausência comprovada de egress; suíte 252/1.722 e Pint passaram.
## Revalidação atual de dependências — 17/10/2026

`composer audit --locked` foi repetido em 18/10/2026 com Composer 2.10.3 oficial e SHA-256 conferido: zero advisories e zero pacotes abandonados. `composer validate --strict --no-check-publish` aprovou o manifesto/lockfile. `composer outdated --direct --locked` encontrou duas versões principais posteriores: `phpunit/phpunit` 13.4.3 e `spatie/laravel-permission` 8.3.0; nenhuma está associada a advisory, e ambas ficam fora das restrições principais atuais, portanto não foram promovidas automaticamente. `npm audit` retornou zero vulnerabilidades conhecidas nas 249 dependências contabilizadas. Os resultados foram preservados em `composer-audit-current.json`, `composer-outdated-current.json`, `composer-tool-current.json`, `npm-audit-current.json` e `dependency-audit-current.json`. Esta é uma fotografia temporal dos lockfiles, não monitoramento contínuo.
## Divisão do bundle de frontend — BUG 119

Importações estáticas carregavam todas as páginas no primeiro acesso e produziam 1.123,42 kB de JavaScript principal. O roteador agora carrega páginas sob demanda, reduzindo a entrada para 269,46 kB e mantendo o maior chunk em 421,58 kB. Build sem alertas e 40 testes Node passaram. A medição é de tamanho de artefato; métricas reais de Web Vitals em dispositivos representativos continuam pendentes.
## Responsividade dos filtros da IA — BUG 120

A grade rígida de sete colunas ultrapassava a área útil e cortava controles. Breakpoints progressivos agora mantêm todos os campos dentro da viewport. Navegador real validou 375/768/1265/1440 px, tema escuro, lista de bairros, menu móvel e console limpo; frontend 40/40 e build passaram.

## Falha global do lote ITBI persistia detalhes internos — BUG 141

O serviço gravava a mensagem integral da exceção no lote e a CLI a imprimia. Isso criava um segundo canal de exposição mesmo depois da neutralização da resposta HTTP e dos eventos de log. O lote agora recebe uma mensagem pública fixa; o log registra somente ID e tipo, a CLI referencia o ID, e a exceção original fica apenas como causa encadeada para diagnóstico interno. A regressão usa um host privado fictício como marcador e comprova sua ausência em todos os canais persistentes e públicos. Testes focados 11/56, suíte completa 271/1.832 e Pint passaram. Evidência: `meta/itbi-batch-error-redaction-checks.json`.

## Capa externa permitia conteúdo misto e destinos locais — BUG 142

A URL de capa era exibida diretamente como imagem e aceitava HTTP, credenciais, localhost e IPs privados. Um registro manipulado podia induzir navegadores da conta a realizar requisições GET inseguras ou locais e causar conteúdo misto. A nova política aceita somente HTTPS público sem credenciais e porta imprópria, aplicada no request e novamente no serviço. O resource elimina imagens externas legadas que violem a política. Sete destinos inseguros, um público válido e um registro legado têm regressão. Testes focados 19/124, suíte completa 273/1.863 e Pint passaram. A validação não fixa DNS; CSP e isolamento de rede no cliente permanecem controles complementares. Evidência: `meta/external-cover-url-checks.json`.


## Erro de exclusão de fornecedor ficava atrás do diálogo — BUG 143

O DELETE 503 atualizava o alerta da página sem desmontar o modal, ocultando a mensagem do usuário. O tratamento agora limpa o alvo da confirmação no erro, revela o alerta com retry e conserva o cartão. O cenário foi reproduzido e validado em Chromium real a 375×812, sem exceção JavaScript ou overflow. Frontend 52/52 e build de 2.312 módulos passaram. Evidência: `meta/supplier-ui-network-checks.json` e `meta/supplier-ui-delete-error.png`.

## Diálogos ocultavam erros destrutivos em três módulos — BUG 144

Calendário, Documentos e Tarefas mantinham a confirmação modal aberta após falha do DELETE e, por isso, escondiam o alerta atualizado no conteúdo de fundo. Os três caminhos agora desmontam o diálogo no erro. Uma regressão cobre os nove handlers destrutivos das sete páginas que usam o componente compartilhado. Frontend 53/53 e build de 2.312 módulos passaram. Evidência: `meta/destructive-error-visibility-checks.json`.

## Ações de Oportunidades rejeitavam promises sem feedback — BUG 145

Quatro ações remotas não possuíam caminho de erro. Falhas de rede/servidor podiam virar rejeições JavaScript sem mensagem acionável; no detalhe, um alerta externo ao modal também ficaria encoberto. Todas agora capturam a falha, exibem mensagem pública, restauram pending e desabilitam ações simultâneas. Frontend 55/55 e build de 2.312 módulos passaram. Evidência: `meta/opportunity-action-error-checks.json`.

## Ações financeiras rejeitavam promises sem tratamento — BUG 146

A recarga da referência e a seleção de simulação não possuíam caminho de erro nem bloqueio durante a chamada. Agora ambas publicam a falha pelo alerta existente, restauram pending e impedem submissões concorrentes. Frontend 57/57 e build de 2.312 módulos passaram. Evidência: `meta/financial-action-error-checks.json`.

## Integridade de ações financeiras e orçamento de etapa — BUG 147

O recálculo do wizard e a edição de meta de etapa tinham caminhos de rejeição sem recuperação completa. Agora as falhas são capturadas, os estados pendentes são finalizados e ações concorrentes são bloqueadas; um valor de orçamento recusado volta ao dado persistido. Frontend 59/59 e build aprovados. Evidência: `meta/wizard-project-action-error-checks.json`.

## Integridade das gravações na Central Financeira — BUG 148

Três POSTs financeiros permaneciam acionáveis durante o processamento. A interface agora aplica pending do início ao `finally`, bloqueia reenvios nos controles relacionados e mantém os dados para correção quando o servidor rejeita a operação. Frontend 60/60 e build aprovados. Evidência: `meta/financial-create-pending-checks.json`.

## Estado financeiro confiável após gravação — BUG 149

Falhas na recarga pós-gravação eram absorvidas e ainda permitiam uma confirmação positiva sobre dados antigos. O retorno booleano do loader agora impede confirmação e avanço de aba sem sincronização comprovada. Frontend 61/61 e build aprovados. Evidência: `meta/financial-refresh-outcome-checks.json`.

## Concorrência e integridade da busca da IA — BUG 150

Buscas simultâneas podiam consumir scraper desnecessariamente e publicar uma resposta obsoleta. O frontend agora mantém um `AbortController`, identifica a sequência atual, rejeita resultados tardios, bloqueia repetição e aborta no unmount. O ensaio real 502 → retry 200 comprovou concorrência máxima 1 e recuperação visual. Evidência: `meta/ai-search-network-checks.json`.

## Egress induzido por miniaturas da IA — BUG 151

Valores externos de `thumbnail` eram enviados diretamente ao navegador após apenas validar o esquema HTTP/HTTPS. Agora a resposta exige HTTPS público sem credenciais, portas alternativas ou hosts locais/privados; valores inseguros viram `null`. Isso impede que um provedor externo use a imagem para sondar serviços da rede do usuário. Testes focados 3/20 e Pint passaram. Evidência: `meta/ai-thumbnail-url-policy-checks.json`.

## Navegação externa induzida pela busca da IA — BUG 152

Links de anúncios eram filtrados contra esquemas ativos, mas ainda podiam apontar para hosts internos ou portas alternativas. `ExternalUrlPolicy` agora aplica HTTPS público no backend, e `safeExternalHttpsUrl` repete a restrição no cliente. Destinos recusados não recebem botão “Abrir”. Evidência: `meta/ai-external-link-policy-checks.json`.

## Bypass da política por IP ofuscado — BUG 153

Seis formas alternativas de loopback foram comprovadamente aceitas porque não passavam como IP canônico em `FILTER_VALIDATE_IP`. A política agora remove colchetes IPv6 antes da validação e recusa hosts numéricos ambíguos, incluindo decimal, hexadecimal, octal e abreviado. A suíte completa passou com 282 testes e 1.876 asserções. Evidência: `meta/external-url-obfuscated-ip-checks.json`.

## Bypass por oportunidade manual, legado e cache local — BUG 154

A política da busca não alcançava `source_url` criado manualmente, registros anteriores nem miniaturas restauradas do `sessionStorage`. Agora o request valida, o resource filtra e o cliente revalida os dois valores do cache. O conjunto completo passou com 283 testes PHP/1.905 asserções e 64 testes frontend. Evidência: `meta/opportunity-source-url-policy-checks.json`.

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

## Falha controlada do armazenamento temporário da IA — BUG 163

A abertura de uma análise dependia de duas escritas não protegidas no armazenamento do navegador. Cota cheia ou acesso bloqueado causava exceção não tratada e interrompia a navegação. As duas tentativas agora são isoladas; uma escrita válida é suficiente, e a falha total produz mensagem controlada. Frontend 65/65 e build de 2.312 módulos passaram. Evidência: `meta/ai-analysis-storage-resilience-checks.json`.

## Validação do estado local do Wizard Financeiro — BUG 164

O cache de notas aceitava JSON arbitrário sem validação e propagava erros de leitura/escrita. O wizard agora trata indisponibilidade, valida objeto, tipos, quantidade e comprimentos e recorre ao dado retornado pela API. Frontend 66/66 e build de 2.312 módulos passaram. Evidência: `meta/wizard-note-storage-checks.json`.

## Resiliência do token no navegador — BUG 165

A autenticação acessava `localStorage` diretamente em oito pontos, permitindo que uma política de navegador interrompesse inicialização, login, cadastro ou logout. Um helper não lançante agora centraliza o ciclo do token e mantém fallback em memória para a aba atual. O bearer token ainda é persistido em `localStorage` quando disponível; migrar para cookie HttpOnly exigiria alteração coordenada do contrato Sanctum/CSRF e permanece recomendação arquitetural. Frontend 69/69 e build de 2.313 módulos passaram. Evidência: `meta/auth-token-storage-checks.json`.

## Preferência local da Central Financeira — BUG 166

A leitura direta de uma preferência durante o render podia tornar a Central indisponível quando o navegador bloqueava armazenamento. A leitura agora falha para um estado seguro e a gravação opcional nunca impede dispensar o guia. Frontend 70/70 e build de 2.313 módulos passaram. Evidência: `meta/score-guide-storage-checks.json`.

## Validação do cache de análise da IA — BUG 167

O contexto local era confiado após `JSON.parse`, permitindo que estado adulterado ou incompatível chegasse ao render; uma exceção na sessão também impedia o fallback persistente. A leitura agora isola cada origem e aplica esquema e limites antes de aceitar o objeto. React continua escapando o texto e URLs externas permanecem na política HTTPS pública. Frontend 72/72 e build de 2.313 módulos passaram. Evidência: `meta/ai-analysis-cache-validation-checks.json`.

## Egress por miniatura adulterada no cache — BUG 168

A imagem principal da análise usava diretamente a URL armazenada, contornando o filtro aplicado na coleta quando o cache fosse alterado. O componente agora reaplica a política HTTPS pública no ponto de uso e não cria `<img>` para destino recusado. Frontend 73/73 e build de 2.313 módulos passaram. Evidência: `meta/ai-analysis-thumbnail-policy-checks.json`.

## Caminho local de foto fora do namespace — BUG 169

O resource convertia qualquer caminho local persistido em URL pública, embora a rotina de exclusão só confiasse em `opportunities/`. Uma política única agora bloqueia traversal, controles e namespaces alheios tanto na saída quanto na operação de storage. A regressão comprova omissão de `documents/` e `..` e preserva um caminho legítimo. Suíte PHP 301/2.266 e Pint passaram. Evidência: `meta/opportunity-photo-path-policy-checks.json`.

## Bypass de política no resource financeiro — BUG 170

A Central e o Wizard recebiam a capa por um segundo resource que aceitava HTTP/HTTPS e caminhos locais arbitrários. Isso reabria egress para destino interno e exposição de namespaces bloqueados no resource principal. O resource financeiro agora compartilha as duas políticas centrais e retorna `null` para origem recusada. Suíte PHP 302/2.280 e Pint passaram. Evidência: `meta/investment-cover-url-policy-checks.json`.

## Exposição de metadados internos da análise financeira — BUG 171

`InvestmentAnalysisResource` combinava `attributesToArray()` com relações Eloquent sem projeção. Qualquer usuário autorizado à própria análise recebia identificador interno de usuário, marca de exclusão lógica e chaves estrangeiras redundantes; colunas futuras também entrariam automaticamente no contrato. O resource agora aplica allowlists independentes no registro principal e em cada relação. A regressão comprova a ausência desses campos e a presença dos dados funcionais. Análises 12/106, suíte PHP 303/2.299 e Pint passaram. Evidência: `meta/investment-analysis-data-minimization-checks.json`.

## Exposição de metadados internos nas etapas — BUG 172

`ProjetoResource` incorporava a coleção Eloquent de etapas sem projeção e os endpoints de criação/edição retornavam o modelo diretamente. As respostas revelavam `tenant_id`, `projeto_id`, `deletado_flg` e timestamps administrativos. `ProjetoEtapaResource` passou a ser a única representação pública nos três caminhos. A regressão valida campos de negócio e ausência dos metadados internos. Projetos relacionados 33/316, suíte PHP 304/2.320 e Pint passaram. Evidência: `meta/project-stage-data-minimization-checks.json`.

## Allowlist CORS desconectada do deploy — BUG 173

`config/cors.php` continha somente quatro origens de desenvolvimento e não consumia `FRONTEND_URL`. Um domínio de produção corretamente configurado seria bloqueado; a correção manual mais provável, wildcard, ampliaria indevidamente a superfície. `CorsOriginPolicy` normaliza somente origens HTTP(S) exatas e elimina wildcard, credenciais, caminho, query e fragmento. Ambiente local mantém portas de desenvolvimento; produção usa `FRONTEND_URL` e `CORS_ALLOWED_ORIGINS`. Preflights positivo e negativo e `config:cache` foram executados. API 6/64, suíte PHP 306/2.330 e Pint passaram. Evidência: `meta/cors-origin-configuration-checks.json`.

## Atualização compatível do lockfile frontend — BUG 174

`npm outdated` identificou dois patches aceitos pelas restrições atuais: `@types/react` 18.3.31 e `postcss` 8.5.31. O lockfile foi atualizado e reconstruído por `npm ci --ignore-scripts`, mantendo a política que evita scripts de instalação; o build comprova que o binário opcional do esbuild continua resolvido. Auditorias completa e de produção retornaram zero vulnerabilidades em 249 dependências. Nove dependências diretas possuem versões principais posteriores e exigem migração separada. Frontend 73/73 e build de 2.313 módulos passaram. Evidência: `meta/frontend-dependency-refresh-checks.json` e `meta/npm-outdated-current.json`.

## Inventário da matriz não acompanhava novas rotas — BUG 175

A comparação final entre o roteador e a matriz encontrou 16 caminhos implementados durante a auditoria que ainda não tinham linha própria. Isso não demonstrou ausência de middleware: as rotas possuíam fronteiras e regressões, mas a documentação operacional estava incompleta. A matriz agora registra as rotas de recuperação/confirmação/sessões, fontes, documentos, calendário e tarefas. A comparação normalizada cobre 59/59 caminhos distintos da API, sem ausência ou entrada obsoleta. A segunda revisão também aprovou 306 testes PHP, 73 testes frontend, build, Pint, lint de 191 arquivos PHP, audits e cache de configuração. Evidência: `meta/final-regression-review-checks.json`.

## Parâmetro de tarefa contornava validação numérica — BUG 176

O middleware de identificadores não reconhecia o nome `{tarefa}` gerado pelo resource router. Entrada textual alcançava o parâmetro `int` do controller e gerava HTTP 500. O caso foi reproduzido antes da correção e passou a retornar 404 após incluir `tarefa` na validação central; `tokenId` também foi incluído como defesa redundante ao constraint da rota. Texto, negativo e valor excessivo são cobertos em 75 asserções sobre recursos com argumentos inteiros e model binding. Suíte completa atual 308/2.388 e Pint passaram. Evidências: `meta/task-route-identifier-checks.json` e `meta/resource-identifier-coverage-checks.json`.

## Importação ITBI dependia da permissividade do SQLite — BUG 177

O normalizador não conferia comprimentos e precisões definidos na migration. SQLite persistia valores que MariaDB/MySQL estrito poderia recusar, criando resultados diferentes por ambiente e usando exceções do driver como validação implícita. Uma rua de 181 caracteres em coluna de 180 foi efetivamente gravada antes da correção. Limites de todos os campos normalizados persistidos passaram a ser aplicados antes da transação da linha, incluindo números finitos/não negativos, UF, CEP, endereço derivado e bytes dos campos `TEXT`. Uma linha inválida agora é isolada como falha parcial em qualquer banco. Suíte completa 308/2.355 passou. Evidência: `meta/itbi-schema-bound-normalization-checks.json`.

## Enumeração por binding e resposta de autorização — BUG 178

`SubstituteBindings` era priorizado antes das barreiras de conta ativa e e-mail confirmado. A diferença 404/403 permitia a uma conta não verificada distinguir IDs ausentes de recursos existentes. Usuários confirmados também recebiam 403 para oportunidades/análises alheias, em contraste com 404 para IDs ausentes. As barreiras de conta agora precedem throttle, binding e `can`; as policies ocultam negações de propriedade com 404, inclusive por meio do Form Request de atualização. A regressão percorre todas as rotas empresariais e a matriz de propriedade verifica leitura, mutação e snapshots. Suíte 311/2.761 e Pint passaram. Evidência: `meta/prebinding-authorization-checks.json`.

## Bearer era enviado a fluxos públicos e 401 apagava sessão válida — BUG 179

O cliente anexava o token atual até ao login e removia a sessão em qualquer 401. Um erro de senha em outra aba encerrava localmente uma sessão ainda válida, e credenciais bearer eram transmitidas desnecessariamente aos endpoints públicos. A allowlist agora impede o header nesses caminhos e condiciona a limpeza a uma chamada protegida que enviou bearer. Frontend 74/74 e build de 2.314 módulos passaram. Evidência: `meta/public-auth-session-isolation-checks.json`.

## Brute force distribuído contornava o orçamento de login por conta — BUG 180

O orçamento nominal por conta combinava e-mail e IP, portanto cada origem recebia cinco tentativas novas contra o mesmo alvo. A reprodução com 31 IPs distintos não atingiu 429. Um teto adicional de 30/hora usa somente o hash do e-mail normalizado, enquanto os limites por IP e conta+IP continuam ativos. A 31ª tentativa distribuída agora é bloqueada. Suíte 312/2.792 e Pint passaram. Evidência: `meta/distributed-login-rate-limit-checks.json`.

## Estado obsoleto em atualização concorrente de tarefa — BUG 181

A tarefa era carregada antes da transação e não recebia lock próprio. Uma edição concorrente podia calcular campos derivados sobre estado antigo e deixar uma tarefa concluída sem data. O serviço agora bloqueia/recarrega a linha, revalida os pais e só então normaliza e grava. Regressão determinística e três pares de processos sobrepostos em MariaDB comprovaram consistência de tarefa e etapa, incluindo orçamento agregado e política de último commit. Evidência: `meta/task-stage-concurrency-mariadb.json`.

## Snapshot antigo após lock permitia órfão lógico — BUG 182

Uma disputa MariaDB comprovou despesa criada com sucesso enquanto o projeto era excluído com sucesso, restando filho ativo sob pai lógico inativo. A revalidação posterior ao lock era uma leitura consistente comum e podia enxergar versão antiga no `REPEATABLE READ`. As revalidações afetadas agora são leituras bloqueantes atuais. Dezesseis corridas, com os dois ordenamentos de oito cenários, comprovaram ausência de órfãos, arquivos residuais e referências ativas inválidas. Evidência: `meta/parent-deletion-concurrency-mariadb.json`.
