# Correções e validação — histórico

16/10/2026 — trabalho local, sem commit/push/deploy.

| Área | Mudança | Evidência |
|---|---|---|
| Isolamento | TenantScope fecha acesso de usuário sem tenant | Falha anterior registrada; regressão aprovada |
| Conta | Login e API verificam usuário/tenant ativo | SecurityRegressionTest |
| Autenticação | Tokens 12h; throttling login/cadastro/API; limites de entrada | SecurityRegressionTest |
| Atribuição | Whitelist endereço/etapas/categorias | Regressão aninhada + CRUD |
| Integridade | Relações de despesa consistentes e referências ativas | DomainIntegrityTest |
| Finanças | Recalcular resultados/custos em troca/remoção de financiamento e atualização de análise | FinancialConsistencyTest + InvestmentAnalysisTest |
| ITBI | Gate com permissão, comando de concessão/revogação, streaming de arquivo, erro neutro, rejeição de lote sem transação | ImportSecurityTest |
| XML | DTD rejeitada, LIBXML_NONET, limite de entrada, limite de índice, texto inline preservado | SpreadsheetSafetyTest |
| Dependências PHP | composer update dentro de restrições | Audit após atualização sem avisos |
| Dependências JS | Correções compatíveis + Router 7/Puppeteer 25/Vite 6.4.4 | Build aprovado; 7 avisos npm restantes |
| Frontend | Logout, labels e autocomplete login, remoção de credenciais preenchidas, menu móvel | Build + navegação local |
| Diagnósticos | Mensagens neutras para exceções HTTP/execução | Teste com marcador privado |
| TLS | Verificação Nestoria habilitada | Inspeção de código; integração externa não exercitada |
| Seeder | Dados demo limitados a local/testing | Inspeção; falta teste específico produção |

Snapshot mais recente: 46 testes, 274 asserções, todos aprovados. Evidências brutas em tests-current.json, *before*.txt/json, audits e routes.json. php-lint.txt registra análise sintática; não é análise semântica completa. Alterações seguem em revisão; não representam certificação de prontidão.

## Continuação — 16/10/2026

- OwnershipMatrixTest: 20 chamadas privadas de outro usuário rejeitadas com 403; snapshots de seis tabelas idênticos antes/depois. Abrange oportunidades, análises, financiamento, custos e resultados.
- FinancialValidationTest: confirmou aceitação indevida de parcelas pagas acima do contrato; lte:total_installments e limites de 600 meses implementados. Fixture de consistência ajustada para declarar prazo contratual junto das parcelas pagas; a verificação financeira original permanece.
- Suíte atual: 46 testes aprovados, 274 asserções. Nenhum teste removido.


## Continuação — cancelamentos e exclusões

- Despesas canceladas excluídas das quatro agregações de custo/ROI (ProjetoService/VendaService).
- VendaService sincroniza imóvel após criação, atualização, transferência ou exclusão lógica. Só reabre imóvel vendido sem outra venda concluída ativa; operações transacionais.
- Três novos testes de domínio exercitam pagamento/cancelamento, cancelamento/exclusão de venda e transferência com outra venda ativa.
- Resultado: 49 testes aprovados, 298 asserções; git diff --check sem erros. Concorrência real ainda pendente, sem alegação de cobertura com SQLite.

## Continuação — provedores simulados

- MarketProviderTest exercita sucesso com linhas válidas/malformadas, HTTP 503 e CSV municipal com quantidade de colunas inválida.
- Filtros de tipo impedem TypeError; normalização rejeita valores não escalares e números não finitos; CSV divergente não chama array_combine com comprimentos diferentes.
- Nenhuma chamada externa: Http::preventStrayRequests + Http::fake.
- Suíte atual: 52 testes aprovados, 318 asserções.

## 16/10/2026 — resolução dos alertas npm

Tailwind 3 migrado para 4.3.3 com ferramenta oficial, incluindo @theme, @utility, PostCSS dedicado e classes renomeadas equivalentes. Classes de borda recebem camada de compatibilidade gerada pelo migrador. npm audit passou de 7 para 0 pacotes sinalizados; build completo aprovado. Revisão de template não encontrou alteração de fluxo/handlers pelo migrador; QA visual do modal de oportunidade realizado. Captura qa-tailwind4-form.jpg nos outputs. Cobertura visual das demais páginas segue pendente.

ApiSecurityHeaders middleware acrescentado à API; teste ApiHeadersTest verifica política de não armazenamento de tokens/dados privados e cabeçalhos de conteúdo/referência/frame. Suíte: 53 testes/328 asserções aprovados.

## Continuação — sessão e estado arquivado

Opportunity normaliza is_archived em evento saving; migration corrige dados históricos usando status como fonte. Migration executada localmente. OpportunityTest cobre arquivamento por criação/edição e reabertura. SecurityRegressionTest agora exercita token expirado e token legado sem prazo explícito com relógio controlado; fluxo sem fotos também aprovado.

Snapshot: 57 testes aprovados, 349 asserções. git diff --check passou. Essas verificações não substituem concorrência e QA visual completo ainda pendentes.

## Continuação — edição de campos opcionais

Envio FormData usa appendOpportunityFields e inclui vazios para permitir limpeza. Dois testes Node nativos (sem dependências adicionais) verificam limpeza explícita e preservação de zero/texto; teste PHP comprova persistência multipart. Mensagem de erro duplicada dentro do formulário modal para manter feedback visível.

Snapshot: backend 58 testes/355 asserções aprovados; frontend 2 testes aprovados; build TypeScript/Vite aprovado. Teste frontend: node --test tests/opportunityForm.test.mjs (Node 24 local).

## Continuação — atomicidade financeira

Custos e resultados passam a usar DB::transaction e bloqueio da análise antes de mutações; recursos filhos são atualizados após refresh dentro da transação. Autorizações permanecem antes da operação. FinancialAtomicityTest injeta erro de persistência e verifica rollback de criação/atualização/exclusão de custo e criação de resultado. Falha anterior reproduzida com custo restante após HTTP 500.

59 testes PHP aprovados / 368 asserções; Pint nos dois arquivos alterados e git diff --check aprovados. Não foi alegada verificação de isolamento concorrente em MySQL.

## Continuação — transações dos financiamentos

Financing, storeFinancingSimulation, destroyFinancingSimulation e update da análise agora envolvem operação inteira em DB::transaction. Lock da análise adotado consistentemente nos caminhos financeiros; oportunidade bloqueada ao criar análise primária. FinancialAtomicityTest comprova rollback de criação/PUT/exclusão de financiamento com falha induzida. Pint e suíte completa aprovados: 60 testes / 378 asserções.

## Continuação — concorrência com MariaDB real

Banco descartável isolado e seis requisições em processos distintos testaram criação de análise principal, seleção de financiamento e soma de custos. Invariantes aprovadas, sem HTTP 500. Servidor encerrado após coleta. Evidência concurrency-mariadb-results.txt. Isso complementa testes de rollback em SQLite; não cobre todas as operações concorrentes.

## Continuação — datas ITBI

Conversão de datas usa formato estrito e comparação de volta ao texto original; corrige rollover silencioso e dependência do dia corrente em referência mensal. Serial Excel limitado ao intervalo máximo. Dados de origem não são sobrescritos. Testes de datas impossíveis, válidas, bissexto e referência mensal aprovados; suíte atual em tests-current.json.

## Continuação — navegação e perfil

Topbar passa a carregar nome de /auth/me, com cancelamento da requisição na desmontagem e estado explícito de falha. Identidade correta verificada em navegador e build aprovado. Navegação de 15 rotas de módulos registrada em ui-smoke.json; estados de demonstração/incompletos não tratados como aprovação funcional. BUG 14 continua pendente para dados estáticos; BUG 24 cobre identidade corrigida.

## Continuação — perfil e limites específicos

Perfil sem tenant retorna tenant=null; gravações empresariais exigem vínculo no authorize dos quatro FormRequests. RateLimiter market-data (6/usuário/min,12/IP/min) aplicado a busca/análise/entorno; itbi-upload (2/usuário/min) após autorização. Regressões sem chamadas externas aprovadas. Suíte: 63 testes/395 asserções.

## Continuação — nulos e defaults

Regras de campos identificados como obrigatórios no banco usam sometimes|required em vez de nullable. Nove payloads testados retornam 422, sem erro SQL; custo opcional de venda no resultado normaliza nulo/vazio a zero. NullValidationTest positivo e negativo incluído na suíte; resultado atual em tests-current.json.

## Continuação — ciclo de vida de oportunidades

InvestmentAnalysisPolicy verifica oportunidade ativa e pertencente à mesma conta, além do proprietário da análise. Caminhos de recursos filhos passam pela mesma policy. Regressão de soft delete confirma 403 sem erro em leitura/cálculo e sem alterar custos existentes. Suíte: 66 testes / 417 asserções; git diff --check aprovado.

## Continuação — identidade ITBI

Chave sem identificador cadastral passa a considerar identidade física; fallback usa linha bruta quando localização suficiente não existe. Migration rekey aplicada localmente e exercitada antes de reimportação em teste; sem exclusão de linhas. Teste de duas propriedades distintas e reenvio aprovado. Recuperação de dados que já tenham sido sobrescritos exige arquivos originais; nenhum dado foi inventado para reconstrução.

67 testes PHP / 420 asserções aprovados.

## Continuação — dashboard real

Adicionado DashboardController/GET /api/dashboard e DashboardTest de cálculo/isolamento. Dashboard.tsx e componentes dashboard aceitam dados do endpoint; Sidebar carrega resumo real. Estados vazios/erro preservam clareza, sem fallback demonstrativo. Captura do navegador mostra conta vazia e perfil real; build aprovado. Suíte: 68 testes/432 asserções. Gráfico distinguido de lucro realizado e nomeado saldo por competência.

## Continuação — precisão dos cálculos

CalculateForStorage valida resultados calculados antes das gravações da análise; resultData usa o mesmo contrato. Validação fora do intervalo ou não finita gera 422 e participa das transações existentes. Teste verifica rollback inclusive do progresso de resultado. Suíte atual: 69 testes PHP / 438 asserções.

## Continuação — etapas

Etapas e projetos precisam estar ativos; estados suportados e progresso inteiro validados. Orçamento não inclui etapas deletadas. Mutações e orçamento ficam na mesma transação com bloqueio do projeto; regressão de rollback comprovada por exceção simulada. Snapshot atual em tests-current.json.

## Continuação — identidade por e-mail

Normalização e comparação case-insensitive parametrizada; cadastro impede variantes equivalentes a registros legados. Login rejeita ambiguidade legada sem selecionar conta arbitrária. Sem mescla automática de dados. Testes e Pint aprovados; suíte atual em tests-current.json.

## Continuação — arquivos e rollback

OpportunityService rastreia somente arquivos recém-armazenados e compensa criação em disco após falha de banco. Sem deleção de fotos anteriores; limpeza falha é registrada. Testes de criação/edição com exceção após upload aprovados; Pint aprovado. Suíte atual em tests-current.json.

## Continuação — galerias

Limite de 20 passa a valer para galeria total dentro da transação, incluindo nova capa externa. Edição bloqueia oportunidade antes de verificar contagem. Índice de capa deve existir nos arquivos enviados. Regressões negativas e caso positivo 19+1 aprovados. Snapshot atual em tests-current.json.

## Continuação — validação de parâmetros de rota

Middleware central protege parâmetros numéricos legados contra TypeError/overflow. Regressão de 36 combinações de rota/ID inválido aprovada. Cabeçalhos de erros de autenticação/validação também testados. Suíte: 80 testes PHP / 521 asserções.

## BUG 35 — navegador do scraper incompatível com Windows

Severidade: média (funcional). Revisão confirmou que PropertyPortalScraper sobrescrevia CHROME_PATH com /usr/bin/google-chrome e o script desabilitava o sandbox incondicionalmente. Correção: configuração em services.portal_scraper, detecção local de Chrome/Edge/Chromium nos sistemas suportados e sandbox habilitado por padrão. Opt-out explícito documentado para ambientes isolados.

Verificação: três testes de configuração aprovados (Windows, override, fallback Linux e navegador ausente). Navegador real iniciou neste Windows com sandbox habilitado e navegou somente para about:blank. Nenhum portal externo acessado; coleta, seletores, redirecionamentos e resultados externos continuam pendentes. Suíte PHP: 80 testes / 521 asserções; frontend: 5 testes aprovados.

## BUG 36 — Importação parcial anuncia sucesso completo

Categoria: Funcional/Interface. Severidade: Média. Status: Corrigido no contrato HTTP e na interface; renderização visual específica do alerta ainda pendente.

Localização: app/Http/Controllers/Api/ItbiImportController.php, upload; frontend/src/pages/ImportarItbi.tsx, upload e resumo do lote. Rota POST /api/itbi-imports/upload.

Causa: o importador já registra completed_with_errors e preserva linhas válidas, mas o controller sempre retornava mensagem de sucesso e a interface sempre usava alerta verde. Impacto: usuário pode interpretar base incompleta como importação integral.

Reprodução anterior em ambiente isolado: executar php artisan test --filter=test_partial_import_reports_warning_and_reupload_recovers_without_duplicates. A fixture cria operador fictício com itbi.import, envia CSV com SQL 123/456, data 15/05/2025, valores 100000/200000 e simula falha de persistência somente para SQL 456 usando evento creating do modelo. Antes, resposta 201 trazia status completed_with_errors, imported_rows=1, failed_rows=1, mas message=Arquivo ITBI importado com sucesso. A asserção de mensagem falhava. Não provoque falha de banco real nem altere dados de produção para reproduzir.

Correção: mensagem explícita de importação parcial quando completed_with_errors; interface escolhe alerta âmbar, com role=status. Cartão do último lote expõe contagens novas/atualizadas/ignoradas/falhas. Importações integralmente concluídas mantêm sucesso e HTTP 201. Preservado processamento parcial previsto pelo importador.

Verificação: o teste acima agora passa; remove a falha simulada, reenvia o mesmo CSV, confirma completed, imported_rows=1, updated_rows=1, failed_rows=0 e exatamente duas transações. Prova recuperação sem duplicação neste cenário sequencial, não concorrência. Suíte completa 81 testes PHP/533 asserções; build TypeScript/Vite aprovado. UI do alerta parcial ainda não exercitada no navegador.

Verificação adicional de planilhas: SpreadsheetSafetyTest agora contém fixtures ODS para repetição de dados, cauda vazia e repetição acima do limite. 83 testes PHP/538 asserções aprovados. Esses testes verificam proteções existentes; não representam novo bug nem prova de limite agregado de recursos.


## BUG 37 — Planilha sobrescreve e descarta colunas silenciosamente

Categoria: Funcional/Integridade. Severidade: Média. Status: Corrigido. Arquivo app/Services/RealEstateData/ItbiSpreadsheetParserService.php, normalizeHeaders/combine. Fluxos CSV/TXT/XLSX/ODS utilizados por POST /api/itbi-imports/upload e comandos de importação.

Causa: array_combine recebe cabeçalhos já normalizados sem verificar duplicatas; array_slice descarta células além dos cabeçalhos. Impacto: valores ambíguos podem sobrescrever uma coluna financeira, e dados extras são perdidos sem erro.

Reprodução anterior local: operador com itbi.import envia CSV fictício `Valor da Transação;Valor da Transacao` seguido por `100000;900000`. Ambos os cabeçalhos viram valor_da_transacao, preservando somente o último. Alternativamente enviar `SQL;Valor` seguido por `123;100000;unexpected`: a terceira célula era descartada. Esperado: rejeição explícita da estrutura ambígua. Os testes test_duplicate_normalized_headers_are_rejected_instead_of_overwriting_values e test_extra_nonblank_columns_are_rejected_instead_of_silently_discarded falharam antes por ausência de exceção.

Correção: nomes não vazios duplicados após normalização são rejeitados; dados não vazios além da largura do cabeçalho também são rejeitados. Controle compartilhado por todos os formatos. Células opcionais ausentes e colunas finais vazias continuam aceitas, comprovadas por test_blank_trailing_columns_and_missing_optional_cells_remain_supported.

Verificação: executar php artisan test --filter=SpreadsheetSafetyTest. Repetir o upload com cabeçalhos distintos e quantidade correta de valores: importação legítima continua funcionando. Um erro estrutural na primeira linha de dados produz lote falho/422 sem gravações; se houver linhas válidas anteriores, o importador conserva-as e marca failed, conforme comportamento parcial existente, cuja recuperação geral permanece em revisão. Nenhum dado real foi alterado para demonstrar o problema.

## Hardening — orçamento cumulativo de expansão ODS

O limite existente de 10000 repetições por bloco não limitava a soma de diversos blocos. Foi adicionado ITBI_MAX_ODS_EXPANDED_CELLS, padrão 2000000, contando linhas de dados multiplicadas por sua largura antes da expansão. Mensagem orienta dividir ou usar CSV. Testes com orçamento reduzido comprovam aceitação no limite e rejeição acumulada de dois blocos individualmente válidos. Não foi realizado ataque de exaustão nem medição de capacidade de produção; este item é hardening, não uma vulnerabilidade explorada comprovada. Não limita memória interna do SimpleXML nem todos os custos de CSV/importação.

## BUG 38 — Recuperação CLI falha e código de saída mascara erros

Categoria: Funcional/Operação. Severidade: Média. Status: Corrigido. Arquivos app/Console/Commands/ItbiImportCommand.php e ItbiSyncCommand.php. Comandos itbi:import e itbi:sync.

Causas: itbi:import devolvia SUCCESS após capturar falhas; itbi:sync ignorava o retorno dos subcomandos. Lotes completed_with_errors não eram selecionados para recuperação. Lote failed com arquivo local era baixado outra vez, inclusive source_url upload://, apesar de já possuir arquivo utilizável. Opções numéricas eram convertidas sem validação; sync descartava limit=0 por teste truthy.

Impacto: automações não detectam importação malsucedida; recuperação de lote manual falha; lotes parcialmente processados ficam fora da fila de retry.

Reprodução anterior local, sem dados reais: php artisan test --filter=ItbiImportCommandTest. O teste prepara arquivos fictícios em disco fake e respostas HTTP fake. Os três cenários iniciais falharam antes da correção: lote manual failed continuou failed após o comando; download HTTP 503 terminou com exit 0; lote completed_with_errors foi ignorado e terminou com exit 0. Não provocar falhas em bases ou provedores reais para demonstrar.

Para verificar manualmente em base isolada: criar lote de teste com storage_path apontando para CSV válido, source_url upload://recover.csv e status failed. Executar php artisan itbi:import --batch=ID_FICTICIO. Esperado agora: completed, exit 0, arquivo local reaproveitado. CSV usa SQL;Data da Transacao;Valor da Transacao com 123;15/05/2025;100000. Para cenários de falha HTTP e de linha usar a fixture automatizada; ela evita rede externa.

Correção: validar --batch e --limit como inteiros positivos; incluir completed_with_errors entre pendentes; verificar existência real do arquivo no disco configurado antes de baixar; acumular falhas e retornar exit 1 quando algum lote não conclui integralmente. Sync interrompe e propaga falha de suas fases, preservando limite zero para rejeição pelo importador.

Verificação: cinco testes em ItbiImportCommandTest; lote manual recupera sem HTTP; HTTP 503 retorna 1; lote parcial com uma linha válida e uma falha retorna 1, continua completed_with_errors e preserva uma transação. Ao remover falha simulada e repetir, completed/exit 0 com exatamente duas transações. Argumentos inválidos rejeitados, sync propaga falha. Suíte completa: 92 testes PHP / 569 asserções aprovados. Não comprova recuperação concorrente, refresh de origem remota ou interrupção abrupta de processo.

## BUG 39 — Falhas de arquivo e conexão deixam pipeline ITBI inconsistente

Categoria: Funcional/Segurança (tratamento de erros). Severidade: Média. Status: Corrigido nos cenários testados. Arquivos app/Services/RealEstateData/ItbiFileDownloadService.php e app/Http/Controllers/Api/ItbiImportController.php. Download CLI e POST /api/itbi-imports/upload.

Causa: retorno false do filesystem era ignorado; download atualizava storage_path/file_hash/status para downloaded sem confirmar gravação. Upload criava lote e tentava importar arquivo não gravado, retornando 422 de formato. Exceções de conexão no downloader escapavam com detalhes originais e sem marcar falha do lote.

Impacto: metadados indicam arquivo disponível que não existe, recuperação confusa e diagnóstico incorreto de arquivo inválido; detalhes de provedor poderiam aparecer na saída do comando. Não foi comprovado vazamento de credencial real.

Reprodução anterior local: php artisan test --filter=ItbiDownloadTest e php artisan test --filter=test_upload_storage_failure_does_not_create_batch_or_start_import. Disco mockado devolve false, sem encher disco real ou alterar permissões. Antes o downloader retornava normalmente e marcava downloaded; a asserção de interrupção falhava. HTTP fake lança PRIVATE_AUDIT_MARKER: antes o texto escapava na exceção. Upload com disco recusando gravação retornava 422 e criava lote em vez de rejeitar indisponibilidade de armazenamento.

Correção: confirmar resultado da gravação antes de atualizar metadados ou criar lote. Upload captura recusa e exceção, registra apenas a classe da exceção e retorna 503 com mensagem neutra. Downloader registra failed/mensagem neutra e lança erro público neutro, preservando causa encadeada para depuração local.

Verificação: ItbiDownloadTest comprova interrupção sem storage_path, failed em falha de conexão e ausência do marcador na mensagem externa/persistida. Download válido salva bytes e SHA-256 exatos; repetir com conteúdo igual mantém um arquivo. ImportSecurityTest cobre recusa false e exceção no disco com 503, zero lotes e zero transações. Suíte completa: 96 testes PHP / 589 asserções aprovados. Reproduzir somente com fixtures isoladas; não provocar indisponibilidade de armazenamento real.

Limites: não comprova rollback entre filesystem e banco, limpeza de arquivos parcialmente escritos pelo driver, limite de tamanho remoto, allowlist de URLs ou segurança de redirecionamentos. Esses pontos continuam pendentes.

## BUG 40 — Download ITBI não restringe a origem dos links

Categoria: Segurança/Validação de destino. Severidade: Média, condicionada ao controle da fonte descoberta ou dos lotes por um operador; não é um endpoint público de URL arbitrária. Status: Corrigido quanto ao esquema, host e redirecionamentos; proteção DNS/egress permanece pendente.

Arquivos: ItbiRemoteFileClient.php (novo), ItbiFileDownloadService.php, SaoPauloItbiDatasetProvider.php e config/real_estate_data.php. Fluxos CLI de descoberta/download.

Causa: links extraídos de HTML ou source_url de lote eram enviados ao cliente HTTP sem política explícita de destinos e com redirecionamento automático. Comportamento confirmado com Http::fake: lote fictício apontando a domínio fora da fonte era baixado e marcado downloaded. Não foi executada exploração contra rede interna ou terceiros, nem comprovado acesso a credenciais.

Reprodução anterior segura: php artisan test --filter=ItbiDownloadTest. test_untrusted_urls_are_blocked_before_any_request falhava porque destino desconhecido terminava downloaded. Fixture só utiliza HTTP fake; não transformar as URLs em chamadas reais. Outros casos cobrem domínio com sufixo enganoso, HTTP, IP literal, userinfo e porta diferente de 443.

Correção: cliente compartilhado exige HTTPS e host exato da ITBI_SP_SOURCE_URL ou da lista ITBI_ALLOWED_DOWNLOAD_HOSTS. Rejeita credenciais embutidas, fragmentos, IPs literais, nomes locais e portas diferentes de 443. Cada Location é resolvido e validado antes da próxima chamada; no máximo três redirecionamentos, sem redirecionamento automático do cliente HTTP. A mesma política se aplica à página de descoberta.

Verificação: destino inválido resulta failed e zero chamadas; redirecionamento para domínio desconhecido faz somente a chamada inicial. Redirecionamento relativo permitido baixa o conteúdo final. Loop encerra após quatro respostas (inicial e três saltos). ItbiDatasetDiscoveryTest confirma fonte configurada com links relativos e bloqueio de redirecionamento indevido. Configurar hosts adicionais exatos somente se necessários para a fonte oficial; não há wildcard ou aceitação por sufixo. Suíte completa: 102 testes PHP / 608 asserções aprovados.

Riscos residuais: hostname autorizado ainda é resolvido pelo cliente HTTP sem pinagem/validação de todos os IPs, e proxies do ambiente podem modificar o caminho de rede. A lista de hosts não prova proteção completa de SSRF/DNS rebinding. Limite de tamanho remoto, redirecionamento com alteração de base dos links relativos e isolamento de egress precisam de verificação adicional. Não houve teste de rede externa nesta etapa.

## BUG 41 — Descoberta resolve links relativos contra a página anterior ao redirecionamento

Categoria: Funcional. Severidade: Média. Status: Corrigido. Localização: SaoPauloItbiDatasetProvider::discover/absoluteUrl e ItbiRemoteFileClient::get. Fluxo itbi:discover/itbi:sync.

Causa: a extração recebia a URL originalmente configurada, mesmo quando a resposta HTML vinha de outra pasta após redirecionamento. O resolvedor manual também mantinha segmentos ../ sem normalização.

Reprodução anterior segura: php artisan test --filter=test_relative_links_use_final_page_location_after_redirect. A fixture HTTP fake responde /catalog/index.html com 302 para /archive/2026/index.html. O HTML final contém href=../data.csv. Esperado: https://source.test/archive/data.csv. Antes retornava https://source.test/catalog/../data.csv. O teste falhava com essa diferença. Nenhum site externo foi chamado.

Correção: o cliente informa a URL final validada; a descoberta a usa como base. Guzzle UriResolver resolve caminhos relativos, absolutos, protocolo relativo e segmentos de diretório conforme URI. O mesmo limite de hosts/redirecionamentos continua aplicado.

Verificação: repetir o teste acima; teste passa. Os testes existentes da fonte sem redirecionamento e links relativos também passam. Suíte completa: 107 testes PHP / 621 asserções aprovados. Elemento HTML base e páginas com links gerados por JavaScript não foram validados.

## Hardening — limites de transferência ITBI

Cliente remoto agora possui limite configurável ITBI_MAX_DOWNLOAD_BYTES=262144000 e limite independente ITBI_MAX_DISCOVERY_BYTES=2097152. Verifica Content-Length no callback de headers, total/bytes recebidos no callback de progresso e tamanho do stream ao concluir a resposta. Tamanho desconhecido no stream final é rejeitado; solicita Accept-Encoding identity e desativa descompressão automática para impedir expansão HTTP implícita. Arquivos compactados ODS/XLSX continuam sujeitos ao parser e aos limites XML existentes.

ItbiRemoteLimitsTest usa limite de quatro bytes e HTTP fake: aceita exatamente quatro, rejeita cinco mesmo com header declarando três, executa callback de headers com anúncio de cinco e callback de progresso com cinco recebidos sem Content-Length. Descoberta usa seu limite separado. Esses testes comprovam a configuração e a lógica dos callbacks; não são um teste real de socket, proxy ou memória sob carga. O controle é hardening por revisão, sem alegação de ataque de exaustão demonstrado. Limite de bytes não garante capacidade de memória ou tempo para toda a importação.

## Verificação real — interrupção de transferência HTTPS

Data: 16/10/2026. Servidor Node HTTPS temporário, escutando exclusivamente em 127.0.0.1:18443; certificado fictício audit.test gerado localmente, confiado somente por esta chamada. Cliente PHP/Laravel/Guzzle/cURL real, sem Http::fake. CURLOPT_CONNECT_TO direcionou audit.test:443 para o listener local; proxy desativado somente no harness. Nenhum DNS externo, chamada a terceiros ou modificação da confiança do sistema.

Limite configurado no harness: 4096 bytes. /small entregou exatamente 4096 e foi aceito. /headers anunciou 65536 e foi interrompido pelo callback on_headers: servidor registrou zero bytes de corpo enviados. /chunked não declarou Content-Length; callback progress interrompeu a resposta após o servidor enviar 4352 dos 65536 bytes planejados. O valor excede o limite por um bloco de 256 bytes; o controle aborta ao observar excesso, não promete limite exato de bytes na rede. Resultado agregado passed=true.

Evidências: remote-transfer-client-results.json e remote-transfer-server-results.json. Scripts locais em work/remote-transfer; chave privada de teste permanece no scratch e não foi copiada para relatórios. Processo temporário identificado pelo comando e encerrado após o teste. Banco da aplicação não foi modificado.

Escopo: comprova callbacks de headers/progresso com o handler cURL instalado e conexão TLS local verificada. Não comprova comportamento de proxy/deploy, DNS/egress, outros handlers, carga, memória total ou processamento completo de arquivos no limite padrão. As pendências correspondentes continuam abertas.

## Evolução solicitada — tela de erro e bairro (16/10/2026)

ErrorPage/404 substitui redirecionamento silencioso de rota desconhecida; ErrorBoundary envolve a aplicação. Navegador local abriu /pagina-inexistente-auditoria, mostrou Página não encontrada e botão de início retornou ao painel. Caminho de exceção inesperada ainda não provocado; não considerar esse cenário testado.

Relato do usuário: lista de bairros não abre. Código anterior usava datalist nativo; ausência de abertura anterior não foi reproduzida nesta etapa, portanto a causa no navegador não é afirmada como comprovada. LocationComboField substitui a interface nativa por lista explícita. Testados por UI: abrir opções de Bairro com São Paulo; selecionar Moema; trocar Cidade para sao paulo, digitar tatuape, ArrowDown/Enter seleciona Tatuapé e fecha lista. Abrir Campinas mostra ausência de sugestões. Cidade sem tabela continua aceitando texto livre; não há cobertura nacional completa nem dados externos de bairros integrados.

Build TS/Vite aprovado; alterações de backend inexistentes neste incremento. Última suíte PHP permanece 107/621. Backlog adicional em PRODUCT_BACKLOG.md preserva os cinco itens solicitados e suas pendências.

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

## BUG 42 — Base de comparáveis inclui o próprio anúncio e imóveis incompatíveis

Categoria: Lógica/Qualidade de avaliação. Severidade: Média. Status: Corrigido nos critérios testados; revisão de avaliação continua aberta. Arquivo app/Services/PropertyValuationService.php, applyComparableMarketEstimates/transactionsBaseQuery/filtros geográficos; consumidor AIPropertySearchController e frontend IAInteligente.

Causa: fallback usava mediana global incluindo o sujeito; igualdade de bairro ou cidade não exigia UF/tipo/área compatível; consulta ITBI por bairro não restringia município/UF, e LOWER sem normalização não tratava acentos consistentemente no SQLite. Contagem da base elevava qualidade/ranking com referências inadequadas.

Reprodução anterior isolada: php artisan test --filter=ValuationQualityTest. Criar em fixtures um apartamento sujeito e cópia da mesma URL: antes contava 1 referência; agora 0. Criar anúncio de Curitiba/PR, casa e imóvel de 1000 m² para sujeito de 100 m² em São Paulo/SP: antes 3 referências, agora 0. Criar transações em bairros Centro de Curitiba/PR e SÃO PAULO/SP: antes 2 transações, agora somente a local com 6000/m². São dados fictícios em RefreshDatabase, sem requisições a portais.

Correção: excluir sujeito/cópias, deduplicar referências, exigir cidade/UF/tipo/área/quartos compatíveis, priorizar grupo do bairro com amostra mínima e retirar fallback global. ITBI é restringido por cidade/UF em todas as camadas; bairro normalizado exato; preços por m² inválidos são retirados da contagem. Falta de referências continua como fallback explícito com qualidade/score limitados, preservando retorno da busca legítima.

Validação: cinco cenários em ValuationQualityTest, incluindo três referências locais válidas e estabilidade de preço com mais referências idênticas. Suíte completa 121 testes/689 asserções aprovada; build TS/Vite aprovado. Antes, três dos quatro cenários iniciais falhavam. A mudança de quantidade isolada também falhou antes (600675 versus 606750) e passa após remover o acréscimo automático; esse ajuste é melhoria de heurística, não validação de preço de mercado.

Apresentação: qualidade heurística substitui probabilidade implícita; faixa identificada como sensibilidade, contagens de vendas/anúncios separadas. Não foi renderizada nesta etapa uma busca com resultados reais para validar todos os textos. AI_EVALUATION.md registra escopo, exemplos e limitações; banco local contém zero transações ITBI, portanto não houve treinamento de modelo estatístico.

## BUG 43 — Transações sem data, futuras e antigas contam como evidência atual

Categoria: Lógica. Severidade: Média. Status: Corrigido nos cenários testados. Arquivo PropertyValuationService::transactionsBaseQuery. Causa: consulta não aplicava a janela temporal já configurada em real_estate_data.avm.max_transaction_age_months. Impacto: base e ranking podiam usar períodos incompatíveis com o cenário corrente.

Reprodução anterior isolada: php artisan test --filter=test_unknown_future_and_stale_transactions_are_not_current_market_evidence. Data congelada em 16/10/2026; cinco transações fictícias: data ausente, amanhã, 25 meses atrás, exatamente 24 meses atrás e um mês atrás. Antes contava 5; esperado e agora observado: 2. Todas as camadas compartilham filtro entre a data inicial inclusiva e hoje. Padrão 24 meses, configuração limitada a 1–120 meses. Nenhuma transação histórica foi apagada.

Verificação: repetir o teste; dados válidos no limite e recentes preservados. Campo transaction_reference_window_months e explicação indicam período. Texto da UI atualizado e build aprovado; resultados reais ainda não renderizados nesta etapa por falta de base local. Não é garantia de atualidade econômica nem atualização por inflação.

## BUG 44 — Rua igual é rotulada como mesmo prédio sem conferir número

Categoria: Lógica/Interface. Severidade: Média. Status: Corrigido nos cenários testados. Arquivo PropertyValuationService::applyBuildingFilter/buildingIdentity/realTransactionReferences. Causa: filtro anterior considerava palavras do logradouro e ignorava números; endereço sem número também obtinha precisão de prédio.

Reprodução anterior em ValuationQualityTest: sujeito Rua Aurora, 100 e três vendas fictícias na Rua Aurora, 200, mesmo bairro/cidade. Antes método itbi_same_building_median_m2; agora itbi_neighborhood_median_m2 com as três referências preservadas. Sujeito Rua Aurora sem número também não recebe rótulo de prédio. Testes test_same_street_different_number_is_not_the_same_building e test_street_without_number_does_not_claim_building_precision.

Correção: identificação exige logradouro/número estruturados ou endereço com separador explícito; nomes e tipos são normalizados e número comparado. Candidatos são conferidos antes de selecionar a camada de prédio. Teste positivo test_matching_street_and_number_preserve_building_reference mantém o método de prédio para três transações em Rua Aurora, 100.

Limites: endereço igual é uma referência cadastral, não comprovação jurídica de unidade/condomínio. Endereços ambíguos ou incompletos recuam para camada menos precisa. Limite de 200 candidatos e 30 referências de prédio; completude em dados volumosos e variações amplas de endereço permanece pendente.

## BUG 45 — Busca geográfica falha no SQLite por função SQL ausente

Categoria: Funcional/Compatibilidade. Severidade: Média. Status: Corrigido no SQLite testado. Arquivo PropertyValuationService::transactionsByRadius. Causa: SQL dependia de acos/radians e HAVING sobre distância; PHP/SQLite instalado não fornece acos. Impacto comprovado: chamada do serviço com coordenadas válidas lançava QueryException em vez de retornar avaliação. Não foi alegada reprodução desse erro por coleta externa na interface.

Reprodução anterior: php artisan test --filter=test_radius_lookup_works_in_sqlite_and_excludes_distant_transactions. Fixture: três vendas próximas de -23.55/-46.63 e uma distante. Antes erro no such function: acos. Agora método itbi_radius_500m_median_m2 com 3 referências.

Correção: filtrar caixa geográfica parametrizada e calcular haversine em PHP, sem exigir extensões matemáticas do banco. Leitura em lotes de 250, fila limitada às 80 referências válidas mais próximas, seleção determinística em empates. Testes adicionais cobrem camada 900m, latitude zero e 160 transações mostrando que 80 próximas mais antigas têm prioridade sobre 80 recentes mais distantes.

Verificação: executar ValuationQualityTest. Não houve carga agressiva ou teste de precisão geodésica em todo o globo. Compatibilidade no banco de produção, polos/antimeridiano e desempenho em volume continuam pendentes. Não é treino de modelo.

## BUG 46 — Outliers descartados da mediana ainda elevam contagem da base

Categoria: Lógica/Qualidade de avaliação. Severidade: Média. Status: Corrigido nos cenários testados. Arquivo PropertyValuationService::filteredReferences e seleção de camadas. Causa: mediana filtrava valores, mas contagem, fontes e qualidade usavam coleção anterior.

Reprodução anterior: php artisan test --filter=test_discarded_price_outliers_do_not_inflate_evidence_counts. Quatro anúncios de 600000 e um de 5000000 com 100 m². Antes contava 5, apesar de excluir o quinto da mediana; agora 4. Mesma fixture em ITBI também mantém quatro referências e mediana 6000/m².

Correção: filtro de preço válido e IQR atua sobre os registros antes da escolha da camada e do cálculo de contagem/qualidade/mediana. Quantidade apresentada acompanha registros realmente utilizados. Não se afirma que IQR identifica todo erro de dados nem que a qualidade heurística é probabilidade calibrada.

## Regressão da avaliação v3

ValuationQualityTest: 14 testes / 30 asserções aprovados. Suíte completa: 130 testes / 704 asserções aprovados; build TS/Vite aprovado. Avaliador identificado como comparables-v3 pela API e pelos itens. Banco local continua com zero transações ITBI; cenários usam RefreshDatabase e dados fictícios, sem acesso a portais externos. Treinamento e benchmark reais permanecem pendentes.

## BUG 47 — Router local rejeita fotos em public/storage no Windows

Categoria: Funcional/Compatibilidade. Severidade: Média. Status: Corrigido no ambiente local testado. Arquivos server.php e app/Support/LocalPublicFile.php. Afeta GET/HEAD /storage/... no servidor PHP local e no proxy Vite.

Causa: realpath resolve a junction public/storage para storage/app/public, fora do prefixo public. O router rejeitava o arquivo existente e passava ao Laravel; neste ambiente retornava 302 para a raiz do frontend. Fotos recebiam HTML/redirecionamento em vez dos bytes da imagem. Arquivo fictício diretamente em public retornava 200 corretamente.

Reprodução anterior: com a aplicação local em execução, criar arquivo texto fictício audit-storage-probe.txt em storage/app/public, com conteúdo AUDIT_STORAGE_FIXTURE. GET sem seguir redirects em http://127.0.0.1:8000/storage/audit-storage-probe.txt ou pela porta 5173. Antes: 302, Location para frontend, conteúdo não corresponde ao arquivo. Controle em public/audit-static-probe.txt: 200 e bytes esperados. Evidência public-files-before.json. Não utilizar fotos reais para demonstrar.

Correção: helper resolve caminhos canônicos, exige fronteira de diretório e permite somente o destino autorizado storage/app/public sob a URL /storage/. Outros alvos de symlink, diretórios privados e prefixos irmãos permanecem fora da permissão. rawurldecode preserva sinais de mais em caminhos. Tipos delegados em storage ficam limitados a imagens/downloads suportados; scripts/HTML/SVG/JS não são delegados como arquivos comuns. Esse limite é hardening preventivo, não prova de exploração por upload.

Verificação real: controles em public e public/storage retornam 200 com bytes esperados nas portas 8000/5173 (public-files-after.json). PNG fictício retorna image/png com SHA-256 idêntico; HEAD retorna 200 sem corpo (public-images-after.json). Caminho inexistente retorna 403 sem redirecionamento, pela rota assinada de armazenamento do Laravel, conforme public-missing-after.json. Não foi alegado teste de galeria completa no navegador.

LocalPublicFileTest cobre fronteira de raiz, destino autorizado só em /storage/, arquivos privados/alvos externos negados, bytes nulos/barras invertidas e tipos permitidos. Suíte completa 134 testes / 721 asserções aprovada. Fixtures removidas; nenhuma foto real foi alterada. Servidores estavam parados no início da rodada e foram restaurados em loopback; portas confirmadas ativas ao final da verificação.

Riscos residuais: arquivos no disco public continuam públicos por URL. A correção funcional não fornece autorização por dono sobre o arquivo. Política privada foi proposta em PHOTO_ACCESS_PROPOSAL.md, sem migração executada. Servidores web de produção precisam de configuração equivalente e verificação própria; os testes atuais são do servidor PHP embutido no Windows e do proxy Vite local.

## Projetos — despesas (BUGs 48 e 49), 16/10/2026
PaginatedCollection e testes novos corrigem limite incompatível e respostas fora de ordem. DespesaService desempata paginação por ID; ExpensePaginationTest cobre contrato real e tenant. Projetos preserva modal/rascunho em falha, distingue falha posterior de atualização e envia observações. Modal usa dialog nativo. expenseSummary exclui canceladas dos totais e preserva o status. Suíte completa: 135 testes PHP/734 asserções; 12 testes Node; build TS/Vite aprovado. diff --check sem erros (aviso de CRLF em Dashboard pré-existente). Teste visual rejeitado pela política da ferramenta de navegador; não executado nem contornado. Detalhes e passos manuais em QA_REPORT.md.

Decisão de fotos resolvida: usuário escolheu públicas por link, como hoje. Não há aprovação privada pendente e nenhuma foto foi migrada.

## Conversão atômica de análise em projeto — BUG 50
Nova AnalysisProjectService coordena cadastro e vínculo em transação, bloqueia análise, devolve projeto existente em retries e rejeita legados ambíguos/exclusão lógica. Endpoint protegido pela policy e middleware; CentralFinanceira passou de dois POSTs independentes para uma operação. Migração aditiva nullable/única aplicada localmente com backup SQLite consistente; sem alterações em registros antigos. Distribuição fecha centavos e conserva metadados financeiros. 10 testes/80 asserções específicos e 145/814 completos aprovados; build aprovado. Concorrência MariaDB real com dois workers sobrepostos: 201/200 no mesmo projeto, sem duplicatas. Evidência conversion-invariants.json; servidor isolado encerrado. UI e reconciliação legada continuam pendentes.

## Cabeçalho, ajuda e projeto manual — atualização do BUG 14
Topbar conecta criação, atividades e ajuda e remove badge fictício. Projetos oferece dialog manual paginado, validação e feedback; Oportunidades aceita ?novo=1 e ambos os dialogs usam semântica/teclado nativos. Estado sem imóvel encaminha ao cadastro real de oportunidade. Ajuda.tsx documenta fluxos reais. ManualProjectTest: 2/17; helpers frontend totais 14; suíte PHP 147/831; build aprovado. Navegador validou viewport estreito, Escape, URL, estados vazios e navegação; capturas em outputs. Módulos demonstrativos restantes continuam pendentes.

## Projetos e despesas — totais confiáveis e ações reais
- A listagem de projetos percorre todas as páginas; resumo ausente não vira R$ 0, 0% nem etapa vazia.
- KPIs incompletos mostram “Indisponível”, aviso com contagem e tentativa de recarga. “Projetos ativos” foi corrigido para “Projetos cadastrados”.
- Agregação monetária do frontend usa centavos e expõe completude; dois testes Node cobrem soma e resumo ausente.
- Filtros de despesas abre/fecha o painel; “Editar” e “Marcar como pago” usam PUT real, recarregam resumo/lista e preservam erros.
- O painel mostra forma de pagamento, data efetiva de pagamento e observação persistidas. Controles fictícios de fornecedor, vencimento e anexo foram retirados.
- ExpenseUpdateWorkflowTest confirma campos persistidos e recálculo da etapa/projeto. Consolidado: 148 testes PHP/841 asserções, 16 Node e build aprovado.

## Busca de imóveis/IA — subprocesso Windows
- Resolvidos caminhos de Node e Chrome/Edge sem depender do PATH herdado pelo servidor antigo.
- O processo filho recebe allowlist mínima de variáveis de runtime; cabeçalhos e dados HTTP não são propagados.
- Diagnóstico do headless não é mais perdido no fallback; endpoint expõe apenas estado seguro e contagens do pipeline.
- Busca real São Paulo/Perdizes retornou 36 anúncios e renderizou 20 na primeira página, sem 502.
- Regressão: ExecutableLocatorTest 4/11, AIPropertySearchTest 2/13; suíte consolidada 154/866, Node 16 e build aprovados.

## Resposta 401 consistente na API
- O redirecionamento de convidados devolve `null` em `/api/*`, evitando exceção por rota `login` inexistente.
- Clientes anônimos com ou sem `Accept: application/json` recebem 401; SecurityRegressionTest cobre os dois casos.
- HTTP local confirmado e suíte consolidada aprovada: 154 testes/866 asserções.

## Página autônoma de despesas ligada à API
- `Despesas.tsx` não contém mais coleção mock, fornecedores/anexos/vencimentos fictícios, KPIs fixos ou paginação cenográfica.
- A tela usa `PaginatedCollection` para despesas e projetos, filtros combináveis, totais por status, detalhe real, PUT de pagamento, estados vazio/erro/carregamento e navegação ao fluxo de lançamento em projeto.
- `expenseExport.ts` gera CSV apenas dos resultados filtrados, preserva centavos/UTF-8, escapa aspas e neutraliza prefixos de fórmula (`=`, `+`, `-`, `@`). Dois testes de regressão foram adicionados.
- Build Vite aprovado; 18 testes Node passaram. Smoke real em tema escuro/viewport estreito não apresentou erros de console e confirmou `/despesas` → `/projetos`.

## CRUD real de fornecedores
- Criados migration, model, request, resource, service, controller e cinco rotas protegidas para fornecedores.
- TenantScope protege leitura/mutação; `tenant_id` e `deletado_flg` não entram em `validated()`. Exclusão é lógica e IDs alheios retornam 404.
- A tela removeu dez fornecedores fictícios e controles cenográficos. Estado vazio, métricas, busca, categoria/status, detalhe, cadastro, edição e exclusão refletem a API.
- FornecedorTest: 4 testes/36 asserções. Suíte completa: 158/908. Build e 18 testes Node aprovados. Migração aditiva aplicada localmente.

## Módulo real e privado de documentos — BUG 57
- Removidos 128 documentos/KPIs/descritivos inventados e controles inertes de `Documentos.tsx`.
- Adicionados migration, model tenant-aware, request, resource, service, controller, configuração e seis rotas autenticadas.
- Arquivos usam disco privado, caminho UUID e download autorizado; API não revela caminho/tenant.
- Allowlist PDF/JPG/JPEG/PNG/WebP/CSV/XLSX/DOCX, teto 10 MB, projeto do tenant e throttle dedicado.
- Exclusão marca o registro e remove o arquivo; falha de criação do banco remove órfão; falha de storage retorna 503 neutro.
- Interface implementa estado vazio, métricas, filtros, upload, edição, download autenticado e exclusão.
- `DocumentoTest`: 6/61. Consolidado PHP: 164/976. Build Vite aprovado; migração 000006 aplicada; dialog/foco/Escape/console verificados sem gravar arquivo demo.

## Relatórios reais — BUG 58
- Removidos KPIs milionários, séries 2024, composição e cinco projetos definidos em constantes.
- Removidos filtros, seletores, abas e links sem operação.
- Tela passa a usar `/api/dashboard`, com valores acumulados, série real de seis meses, composição e projetos em andamento.
- Estados vazio, carregamento, falha e nova tentativa são explícitos.
- Exportação CSV real inclui os indicadores autorizados, centavos/UTF-8 e neutralização de fórmulas.
- Dois testes Node adicionados; frontend 20/20 e build aprovados; navegador em conta vazia e console limpo validados.

## Calendário real — BUG 59
- Removidos 26 eventos/endereço/fornecedores fictícios e grade fixa de maio/2024.
- Criados migration, model, request, resource, service, controller e cinco rotas autenticadas tenant-aware.
- Validação cobre projeto da conta, categoria/status, datas, fim posterior, limites de texto e campos privilegiados.
- UI gera mês atual, navega, recarrega, filtra categorias e executa criação/edição/exclusão reais.
- Eventos multidiários são incluídos em cada dia sobreposto; paginação percorre todas as páginas.
- `EventoCalendarioTest`: 4/52; suíte completa 168/1.034; build e Pint aprovados; migration aplicada.
- Navegador confirmou outubro/2026, vazio real, foco/Escape e console limpo sem inserir evento demo.

## Remoção completa de Mensagens — decisão do usuário / BUG 60
- Excluído `frontend/src/pages/Mensagens.tsx` com contatos, mensagens, anexos e dados pessoais fictícios.
- Removidos import, rota `/mensagens`, item de navegação e contador estático.
- Descartados model/request/resource/service/migration experimentais antes de qualquer rota ou migração aplicada.
- Busca no código ativo sem referências; build aprovado.
- Navegador: zero links no menu, URL antiga apresenta 404 própria e console sem erros.

## Obras conectadas aos projetos reais — BUG 61

- Removidos seis obras, etapas, custos, datas, alertas e métricas definidos em constantes.
- `/obras` percorre a coleção paginada de projetos e carrega resumos persistidos, com cancelamento de requisições antigas e erro explícito.
- Totais financeiros são ocultados quando qualquer resumo falta, evitando apresentar soma parcial como definitiva.
- Status atrasado usa prazo persistido e respeita conclusão/cancelamento; busca e filtros operam sobre projeto e imóvel reais.
- Etapas permitem atualizar status e progresso pelo endpoint existente, que valida valores e tenant no backend.
- O wizard duplicado/incompatível foi removido; criação encaminha ao cadastro real de projeto.
- Build Vite aprovado (2.300 módulos), 23/23 testes Node e navegador validado em conta vazia, fluxo de criação e console limpo.

## Imóveis reais e API limitada — BUGs 62/63

- Removida a análise fixa com imóvel, cenários, ROI, cronograma e comparáveis inventados.
- `/imoveis` tornou-se CRUD real, paginado e tenant-aware, com indicadores, busca, status, detalhes, cadastro, edição e exclusão lógica.
- Formulário envia números/endereço normalizados e permite limpeza explícita de opcionais.
- Listagem valida status/busca/página e limita `per_page` a 100; contagens e observações ganharam tetos de armazenamento.
- Usuário sem tenant recebe 403; IDOR de leitura/mutação/exclusão retorna 404; payload não controla tenant nem exclusão.
- `deletado_flg` foi removido do fillable e o serviço passou a atribuí-lo explicitamente.
- `PropriedadeTest`: 4/37; suíte PHP completa 172/1.071; Node 25/25; build com 2.301 módulos.
- Navegador em conta vazia confirmou zeros, formulário, foco, validação, Escape, viewport estreito e console limpo.

## Painel Financeiro real — BUG 64

- Substituído o PageStub por indicadores acumulados da API `/dashboard`.
- Investimento, vendas, lucro, ROI, composição, série de seis meses, projetos e atividades usam apenas dados tenant-aware.
- Escopo dos indicadores é descrito; despesas canceladas e estados de venda considerados são explícitos.
- Carregamento, erro, tentativa, atualização e conta sem movimento possuem estados próprios.
- Atalhos para Despesas, Projetos, Relatórios e criação de projeto foram validados no navegador.
- Helper protege contra não finitos, preserva resultado negativo e evita divisão por zero.
- Build Vite aprovado (2.303 módulos), Node 27/27 e console limpo em viewport estreito.

## Tarefas funcionais e isoladas — BUGs 65/66

- `/tarefas` deixou de ser `PageStub` e agora executa CRUD real, paginação, busca, filtros, conclusão e exclusão confirmada.
- Controller, requests, resource e service tenant-aware validam projeto, etapa, responsável, limites e estados no backend.
- `ProjetoResource` usa contrato explícito de tarefa; a relação não retorna registros excluídos nem campos internos.
- O último componente `PageStub`, já sem consumidores, foi removido.
- `TarefaTest` passou 4/58; suíte PHP 176/1.135; frontend 30/30; build com 2.304 módulos.
- Browser validou estado vazio, foco, bloqueio sem projeto, Escape, atalho de criação e console limpo.

## Estado real das integrações — BUG 67

- Removidos estados fixos de fontes da página Configurações.
- Novo endpoint protegido informa apenas capacidade/configuração local e dados ITBI, sem sondar terceiros.
- Chaves, tokens, URLs privadas e caminhos internos não são serializados.
- Estados distinguem configuração, disponibilidade local, ausência de dados, pendência e funcionalidade não implementada.
- Interface ganhou loading, erro, nova tentativa, atualização e totais completos.
- `DataSourceStatusTest` 3/25; suíte PHP 179/1.161; frontend 30/30; build 2.304 módulos; console limpo.

## Recuperação segura de senha — BUG 68

- Adicionados solicitação, notificação, redefinição, link no login e duas telas públicas acessíveis.
- Resposta de solicitação não revela se e-mail existe; conta/tenant inativos não recebem link.
- Token fica hashado, expira em 60 minutos, é de uso único e permanece no fragmento da URL frontend.
- Nova senha respeita 12–72 caracteres e limite bcrypt de 72 bytes.
- Redefinição gira `remember_token` e revoga todas as sessões Sanctum.
- Limites por IP e hash de conta reduzem abuso; erros do mailer permanecem neutros.
- `PasswordResetTest` 5/39; suíte PHP 184/1.200; frontend 32/32; build com 2.307 módulos; smoke visual/console aprovados sem envio externo.

## Logout compatível com bearer e sessão — BUG 69

- Removida a chamada incondicional de `delete()` sobre token possivelmente nulo/transitório.
- Bearer token encerra somente o token atual e preserva outras sessões.
- Sessão web executa logout, invalida dados/ID e regenera CSRF.
- Requisição autenticada sem token pessoal deixa de retornar 500.
- Três testes/8 asserções de logout e suíte consolidada 186/1.204 aprovados; Pint específico passou.

## Cabeçalhos defensivos do frontend — BUG 70

- Criada política central em `frontend/security-headers.ts`, compartilhada por Vite dev e preview.
- CSP restringe scripts, objetos, base, formulários, framing, conexões, fontes e imagens às necessidades observadas.
- Adicionados `DENY`, `nosniff`, `no-referrer`, COOP, Permissions Policy e bloqueio cross-domain.
- Build copia `frontend/public/_headers` para implantação estática compatível.
- Dois testes novos elevaram a suíte frontend a 34/34; build com 2.307 módulos passou.
- Requisições reais em 5173/4173 e recarga autenticada no navegador confirmaram headers, compatibilidade e console limpo.

## Erros API sempre JSON e sem diagnóstico interno — BUG 71

- Todo caminho `/api/*` passa a renderizar exceções como JSON mesmo sem cabeçalho `Accept`.
- O callback remove classe, arquivo, linha e trace das exceções; 5xx retornam somente `Server Error`.
- Erros de validação continuam entregando os campos úteis em 422, sem redirect para a SPA.
- Headers de API foram reutilizados também para 404/exceções anteriores ao reconhecimento de rota.
- `ApiHeadersTest` passou 4/54; suíte completa 188/1.240; Pint específico e HTTP real passaram.

## Arquivos públicos sob allowlist — BUG 72

- `LocalPublicFile` bloqueia dotfiles e separa extensões web da raiz dos formatos autorizados em `/storage`.
- Arquivos físicos negados recebem 404 e não são encaminhados à rota catch-all da SPA.
- Apache nega segmentos ocultos; PHP/entrypoints/scripts locais removem `X-Powered-By`.
- HTTP real confirmou bloqueio de quatro variantes, funcionamento de `robots.txt`, storage público, API e health.
- Fixture temporária removida; `LocalPublicFileTest` 5/29, suíte 189/1.252, Pint e Composer validate passaram.

## Remoção do endpoint de limites PHP — BUG 73

- Removido `GET /api/itbi-imports/upload-limits`, sem consumidores no frontend.
- Upload real conserva permissão de operador, throttle e limite validado no controller.
- Regressão autenticada e HTTP real confirmam 404 sem parâmetros PHP.
- Inventário regenerado com 90 rotas; `ImportSecurityTest` 6/34 e suíte 190/1.254 passaram.

## Exclusão segura de fotos de oportunidades — BUG 74

- `OpportunityService::delete` passou a bloquear a oportunidade, colocar fotos locais em quarentena privada, remover originais públicos e excluir metadados dentro de transação.
- Rollback de banco restaura o arquivo público; caminhos fora do namespace `opportunities/` são recusados sem alterar banco ou storage.
- `OpportunityController` devolve 503 neutro para falhas esperadas de storage, sem caminho ou diagnóstico interno.
- Três regressões cobrem sucesso, caminho inválido e falha após quarentena. `OpportunityTest`: 15/75; suíte: 193/1.270; Pint aprovado.

## Exclusão atômica de documentos — BUG 75

- `DocumentoService::excluir` passou a usar lock, transação e quarentena privada antes de ocultar o registro.
- Caminhos físicos são restritos a `documents/{tenant_id}/arquivo`; travessia ou diretório alheio são recusados.
- Falhas de storage recebem 503 neutro; falhas de banco restauram o arquivo original.
- `DocumentoTest` passou 9/76; suíte consolidada 196/1.285 e Pint passaram.

## Bloqueio de exclusão de propriedade em uso — BUG 76

- Exclusão usa transação e lock na propriedade.
- Projetos, vendas e despesas ativos geram conflito 409 e nenhuma alteração.
- Dependências já excluídas logicamente permitem excluir a propriedade.
- `PropriedadeTest`: 6/51; suíte completa: 198/1.299; Pint aprovado.

## Dependentes operacionais na exclusão de projeto — BUG 77

- A exclusão de projeto passou a usar lock e transação.
- Etapas, tarefas e eventos de calendário ativos são encerrados junto com o projeto.
- Despesas e documentos permanecem preservados como histórico.
- `DomainIntegrityTest`: 10/94; suíte: 200/1.312; Pint aprovado.

## Validação de filtros de coleções — BUG 78

- Projetos, vendas, despesas, categorias e oportunidades receberam allowlists de filtros.
- `favorite` e `archived` exigem booleano válido.
- O preço máximo de oportunidade não pode ficar abaixo do mínimo.
- Teste específico 2/27; conjunto relacionado 25/248; suíte 202/1.339; Pint aprovado.

## Remoção das rotas não utilizadas do disco privado — BUG 79

- O disco `local` deixou de registrar entrega e upload temporários diretos em `/storage/{path}`.
- Nenhum consumidor de URL temporária existia; documentos privados continuam pelo controller autenticado.
- O disco público e a política de fotos públicas por link foram preservados.
- Inventário reduzido de 90 para 88 rotas; GET/PUT privados retornam 404 e não criam arquivo; fixture pública continuou 200.
- `SecurityRegressionTest` 24/141, suíte completa 203/1.342 e Pint aprovados.

## Integridade dos dados de entorno da IA — BUG 80

- Removidos pontos, nomes, contagens e distâncias gerados por perfil urbano genérico.
- Removida a lista local de locais e o preenchimento artificial de categorias vazias.
- O serviço usa somente Overpass/Nominatim, última coleta válida em cache ou estado indisponível sem pontos.
- Respostas parciais conservam zero nas categorias não confirmadas.
- `NeighborhoodContextTest` 2/20, suíte completa 205/1.362 e Pint aprovados.

## Allowlist de URLs externas da IA — BUG 81

- `source_url` e `thumbnail` agora passam por validação final comum a todos os provedores.
- Apenas HTTP/HTTPS válidos chegam à resposta; esquemas executáveis, locais e de dados viram `null`.
- Links HTTPS legítimos permanecem disponíveis.
- `AIPropertySearchTest` 3/17, suíte completa 206/1.366 e Pint aprovados.

## Proteção SSRF do navegador dos portais — BUG 82

- Criada política de rede comum para todas as requisições do navegador headless.
- Bloqueados localhost, redes privadas/link-local/metadados, IPv6 local/mapeado, credenciais e esquemas não HTTP.
- DNS é resolvido antes da continuação e qualquer endereço privado bloqueia o hostname.
- Páginas principais, redirecionamentos e enriquecimento opcional usam a mesma política.
- Frontend 38/38, sintaxe Node, build com 2.307 módulos e auditoria npm de produção com zero vulnerabilidades passaram.

## Redirecionamentos seguros no fallback dos portais — BUG 83

- Seguimento automático do cliente HTTP foi desativado.
- Cada salto exige HTTPS, host exato permitido e DNS integralmente público; máximo de três.
- Destinos privados, reservados, mistos, sem DNS, credenciados ou fora da lista são recusados antes da requisição.
- Integração comprova uma única requisição no redirecionamento privado e funcionamento do salto autorizado.
- Testes específicos 11/15, suíte completa 217/1.381 e Pint aprovados.

## Limite de HTML do fallback dos portais — BUG 84

- Resposta HTML limitada a 5 MiB por header, progresso e tamanho final.
- Transferência solicita codificação `identity` e não expande conteúdo automaticamente.
- Excesso retorna falha neutra com corpo vazio, sem entrar no parser.
- Fixture de 5 MiB + 1 byte foi rejeitada; testes específicos 3/9, suíte 218/1.384 e Pint passaram.

## Transparência da análise financeira — BUG 85
- Removidas comparações fixas com Poupança, CDI, Tesouro e FII, que não possuíam fonte, data, período comparável ou ajuste de risco.
- Removidas metas fabricadas de compra, reforma, venda, ROI e IF Score, além do rótulo incondicional “COMPRAR”.
- Cenários passaram a partir do valor de venda informado e expõem `sale_price_change_percentage`, `cost_change_percentage`, `duration_change_months` e sua base.
- “Confiança” foi substituída por completude dos dados; API identifica o método como cálculo financeiro determinístico baseado em regras.
- Removidos atrasos artificiais de um e dois segundos na Central e no wizard.
- Sinais diretos “Comprar/Negociar/Não comprar” foram neutralizados para faixas das regras; o contrato não publica recomendação financeira.
- `InvestmentAnalysisTest` 9/68, suíte PHP 219/1.406, frontend 38/38, Pint e build com 2.307 módulos passaram.

## Proveniência das taxas de financiamento — BUG 86
- Excluídas dez instituições e taxas hardcoded, o período fixo e a atribuição indevida ao Banco Central.
- Endpoint agora valida banco/taxa informados pelo usuário e calcula a taxa anual equivalente, sem alegar consulta externa.
- Central e wizard aceitam taxa mensal manual e avisam sobre CET, seguros, TR e confirmação com a instituição.
- `InvestmentAnalysisTest` 9/67, suíte 219/1.405, frontend 38/38, Pint e build com 2.307 módulos passaram.

## Consistência do ROI periódico — BUG 87
- Prévia deixou de dividir ROI total linearmente pelos meses; mensal e anualizado agora usam equivalência composta igual ao backend.
- Criado helper testável para 100%/12 meses, 21%/6 meses, perda total e entrada inválida.
- Rótulos residuais de decisão financeira foram neutralizados para faixas das regras e avaliação.
- `InvestmentAnalysisTest` 10/73, suíte PHP 220/1.411, frontend 40/40, Pint e build com 2.308 módulos passaram.

## Limites alinhados ao esquema e ao domínio — BUG 88
- `area_m2` de propriedades e oportunidades foi limitado ao máximo suportado por `DECIMAL(10,2)`.
- Análise de mercado passou a recusar área, preço e quantidades fora do domínio e chaves desconhecidas.
- Frontend monta um payload mínimo compatível com a allowlist do endpoint.
- Reprodução anterior confirmou 201/200; após a correção, 2 testes/16 asserções, relacionados 26/159, suíte 222/1.427, Pint e build com 2.308 módulos passaram.

## Contrato explícito de atualização de projetos — BUG 89
- PUT/PATCH de projeto deixou de aceitar `etapas` e `orcamento_categorias` que o serviço descartava silenciosamente.
- Payload não suportado retorna 422 antes de qualquer alteração simples, preservando atomicidade percebida pelo cliente.
- Criação aninhada e endpoints específicos de criação/edição de etapa continuam funcionando.
- `ManualProjectTest` 3/24, testes relacionados 25/209, suíte 223/1.434 e Pint passaram.

## Cronologia de etapas e vendas — BUG 90
- Término previsto de etapa agora precisa ser igual ou posterior ao início da mesma etapa.
- Fechamento de venda não pode preceder proposta; escritura não pode preceder fechamento/proposta.
- Atualizações parciais combinam datas enviadas com os marcos persistidos para impedir bypass.
- Três regressões, relacionados 18/146, suíte 226/1.450 e Pint passaram.

## Venda final única por imóvel — BUG 91
- Propostas e negociações continuam múltiplas; apenas uma venda ativa pode estar `fechada/escriturada`.
- Criação, finalização e movimentação bloqueiam propriedades em ordem determinística dentro da transação.
- Conflito retorna 422 em `status` sem alterar a venda concorrente ou o imóvel.
- Testes relacionados 16/142, suíte 227/1.459 e Pint passaram; concorrência multiprocesso específica permanece pendente.

## Venda realizada coerente no resumo do projeto — BUG 92
- ROI do projeto agora usa somente venda `fechada/escriturada`, igual ao dashboard e ao ROI da propriedade.
- Propostas, negociações e cancelamentos não geram receita, lucro ou `tem_venda=true`.
- Ordenação ganhou desempate por ID; input visual de área foi alinhado ao limite do backend.
- Testes relacionados 13/123, suíte 228/1.468, Pint e build com 2.308 módulos passaram.

## Marcos obrigatórios de venda final — BUG 93

- `VendaRequest` passou a relacionar o estado efetivo aos marcos temporais efetivos, inclusive em atualização parcial.
- `fechada/escriturada` exigem data de fechamento; `escriturada` exige também data de escritura.
- A regressão reproduziu 201 antes da correção e agora valida 422 sem persistência, além dos caminhos finais válidos.
- Testes relacionados 17/151, suíte completa 229/1.480 e Pint global passaram.

## Exclusão lógica de etapas respeitada — BUG 94

- `Projeto::etapas()` passou a devolver somente registros com `deletado_flg=false`.
- O recálculo de custos deixou de percorrer ou atualizar etapas arquivadas.
- Detalhe e resumo foram cobertos por IDs e contagens explícitas.
- Testes relacionados 16/152, suíte completa 229/1.486 e Pint global passaram.

## Ciclo de conclusão do projeto — BUG 95

- O request passou a aceitar `data_termino_real` e validar sua relação com status e início.
- A primeira conclusão usa a data informada ou o dia atual; edições preservam a data e reabertura a limpa.
- A regressão reproduziu o descarte silencioso e cobre conclusão e reabertura.
- Testes relacionados 20/181, suíte completa 230/1.497 e Pint global passaram.

## Preservação da conclusão de tarefas — BUG 96

- A normalização passou a considerar a tarefa persistida durante atualizações.
- Edições de tarefa concluída preservam a data histórica quando o campo não é reenviado.
- Primeira conclusão e reabertura mantêm o comportamento esperado.
- Testes relacionados 16/179, suíte completa 230/1.500 e Pint global passaram.

## Data coerente com pagamento de despesa — BUG 97

- Criação paga sem data registra o dia atual no backend.
- Edição de pagamento preserva data existente ou aceita uma data explícita.
- Estados não pagos limpam `data_pagamento`, mesmo se o cliente reenviar valor antigo.
- Testes relacionados 15/148, suíte completa 231/1.509 e Pint global passaram.

## Data de quitação dos custos detalhados — BUG 98

- O request compartilhado sincroniza `payment_status` e `paid_at` antes da criação ou edição.
- Primeira quitação cria data, edições preservam a histórica e estados não pagos limpam o marco.
- Testes relacionados 16/127, suíte completa 232/1.518 e Pint global passaram.

## Cronologia efetiva em atualização de projeto — BUG 99

- Início e término previstos são comparados com os valores persistidos quando um deles não vem no payload.
- Os dois sentidos de atualização parcial invertida retornam 422 sem gravar.
- Testes relacionados 21/190, suíte completa 233/1.527 e Pint global passaram.

## Status do imóvel derivado da venda final — BUG 100

- Criação não aceita `vendida` sem venda final.
- Atualização bloqueia o imóvel e exige equivalência entre status e venda `fechada/escriturada` ativa.
- O lock compartilhado com `VendaService` serializa alterações concorrentes no mesmo imóvel.
- Testes relacionados 20/196, suíte completa 234/1.543 e Pint global passaram.

## Coerência entre status e progresso das etapas — BUG 101

- A regra foi centralizada em `ProjetoService` e compartilhada por criação de projeto, criação direta e atualização de etapa.
- `concluida` equivale a 100%, `pendente` a 0%; `em_andamento` e `bloqueada` não aceitam 100%.
- Atualizações parciais sincronizam o campo omitido, enquanto pares explicitamente contraditórios retornam 422.
- Testes relacionados 17/176, suíte completa 235/1.561 e Pint passaram.

## Histórico protegido ao excluir projeto — BUG 102

- Projetos com despesas ou documentos ativos não podem mais ser ocultados, evitando vínculos órfãos.
- A verificação ocorre dentro da transação e sob lock do projeto; conflito retorna HTTP 409 sem mutação.
- Após arquivar os registros protegidos, a exclusão e a limpeza dos dependentes operacionais continuam disponíveis.
- Foco 3/21, suíte completa 236/1.569 e Pint passaram.

## Revalidação de pais sob lock — BUG 103

- Criação de projeto bloqueia/revalida o imóvel antes do INSERT.
- Criação e edição de despesas bloqueiam IDs antigos e novos de imóvel/projeto em ordem estável e revalidam os destinos.
- Upload/edição de documento bloqueiam projetos envolvidos; falha após gravação remove o arquivo preparado.
- Relacionados 31/283, suíte completa 238/1.573 e Pint passaram; prova multiprocesso permanece pendente.

## Locks de projeto para tarefas e eventos — BUG 104

- Criação e edição de tarefa/evento agora ocorrem em transação.
- Projeto de origem/destino é bloqueado em ordem estável e o destino é revalidado após o lock.
- Estado alterado entre request e serviço não produz novo dependente operacional.
- Foco 2/10, suíte completa 239/1.575 e Pint passaram; prova multiprocesso permanece pendente.

## Atualização de projeto e imóvel de destino — BUG 105

- A atualização passou a ser transacional.
- Imóveis de origem/destino são bloqueados em ordem estável e o destino é revalidado ativo.
- O próprio projeto é reconsultado sob lock antes da escrita, fechando a janela com sua exclusão.
- Relacionados 26/237, suíte completa 239/1.576 e Pint passaram; prova multiprocesso permanece pendente.

## Limites de textos livres persistidos — BUG 106

- Descrições de projeto/etapa, observações de despesa/venda e descrição/notas de oportunidade foram limitadas a 5.000 caracteres.
- Payloads acima do teto retornam 422 antes da camada de persistência e sem gravações parciais.
- Teste específico 3/34, suíte completa 240/1.594 e Pint passaram.

## Capacidade numérica de ordem e preço de busca — BUG 107

- Ordem de etapa respeita o maior inteiro assinado de 32 bits da coluna.
- `max_price` da busca de imóveis respeita o teto monetário do domínio.
- Excesso retorna 422 antes de persistência ou chamada externa.
- Teste específico 4/42, suíte completa 241/1.602 e Pint passaram.

## Teto comum de página — BUG 108

- `page` agora aceita somente 1–1.000.000 no middleware comum das APIs.
- Valores extremos retornam 422 antes do cálculo de `OFFSET` e da consulta.
- Relacionados 13/142, suíte completa 242/1.605 e Pint passaram.

## Contrato parcial de projetos — BUG 109

- PATCH não exige mais nome e propriedade quando esses campos não serão alterados.
- POST e PUT mantêm o contrato completo.
- Campos omitidos são preservados e as validações cruzadas usam o registro persistido.
- Relacionados 10/78, suíte completa 243/1.611 e Pint passaram.

## Contrato parcial de vendas — BUG 110

- PATCH permite alterar apenas campos enviados, sem reenviar imóvel ou valor.
- Campos financeiros, vínculo, estado e comprador omitidos permanecem intactos.
- POST/PUT mantêm imóvel e valor obrigatórios; invariantes temporais/finais continuam ativas.
- Relacionados 24/224, suíte completa 244/1.622 e Pint passaram.

## Contrato parcial de imóveis — BUG 111

- PATCH não exige mais apelido quando ele não é alterado.
- Campos omitidos, inclusive endereço e valores, permanecem intactos.
- POST/PUT continuam exigindo apelido e a invariante de venda permanece ativa.
- Relacionados 25/236, suíte completa 245/1.631 e Pint passaram.

## Contrato parcial e relações efetivas de despesas — BUG 112

- PATCH não exige categoria, descrição, valor e data quando omitidos; POST/PUT continuam completos.
- Propriedade, projeto, etapa e tarefa são validados usando payload mais registro persistido.
- Alterar somente a etapa não contorna o vínculo com o projeto existente.
- Relacionados 21/211, suíte completa 246/1.647 e Pint passaram.

## Contrato parcial e estado efetivo de tarefas — BUG 113

- PATCH permite campos isolados; POST/PUT mantêm projeto, título, prioridade e status obrigatórios.
- Etapa enviada isoladamente é comparada ao projeto persistido.
- Status/data de conclusão omitidos são preservados com base na tarefa persistida.
- Teste relacionado 5/76, suíte completa 247/1.662 e Pint passaram.

## Contrato parcial e intervalo efetivo de eventos — BUG 114

- PATCH permite alterar campos isolados; POST/PUT mantêm título, categoria e início obrigatórios.
- Início e fim efetivos combinam payload e evento persistido antes da comparação cronológica.
- Alterações isoladas não conseguem gravar fim anterior ao início.
- Teste relacionado 5/68, suíte completa 248/1.678 e Pint passaram.
## Contrato parcial e metadados de documentos — BUG 115

- PATCH permite alterar metadados isolados sem reenviar categoria; POST/PUT mantêm categoria obrigatória.
- Arquivo não pode ser substituído pela atualização e caminho, nome, MIME e tamanho omitidos são preservados.
- Projeto e status persistidos também permanecem intactos.
- Teste relacionado 11/92, suíte completa 249/1.692 e Pint passaram.
## Contrato parcial de fornecedores — BUG 116

- PATCH permite alterar campos isolados sem reenviar nome ou categoria.
- Nome, categoria, contato, e-mail e estado omitidos são preservados.
- POST/PUT continuam exigindo nome e categoria.
- Teste relacionado 5/47, suíte completa 250/1.703 e Pint passaram.
## Contrato parcial de oportunidades — BUG 117

- PATCH permite alterar preço ou outro campo isolado sem reenviar título, tipo e fonte.
- Campos e galeria omitidos permanecem intactos, inclusive a foto de capa.
- PUT continua exigindo título, tipo do imóvel e fonte.
- Teste relacionado 16/89, suíte completa 251/1.717 e Pint passaram.
## Estrutura e teto do contexto de bairro — BUG 118

- O objeto do imóvel aceita somente as seis chaves utilizadas pelo serviço.
- Área respeita o limite 0–99.999.999,99 já usado no domínio imobiliário.
- Payload inválido é bloqueado antes de qualquer provedor externo.
- Teste relacionado 3/25, suíte completa 252/1.722 e Pint passaram.
## Carregamento sob demanda das páginas — BUG 119

- As 24 páginas de funcionalidade usam `React.lazy` e `Suspense` com fallback acessível; a página de erro compartilhada permanece no núcleo.
- Bundle inicial caiu de 1.123,42 kB para 269,46 kB.
- Maior chunk ficou em 421,58 kB e o alerta de 500 kB foi eliminado.
- Frontend 40/40 e build TypeScript/Vite com 2.309 módulos passaram.
## Grade responsiva dos filtros da IA — BUG 120

- Filtros usam 2 colunas em `md`, 4 em `xl` e 7 somente em `2xl`.
- Não há overflow horizontal em 375, 768, 1265 ou 1440 px.
- Lista de bairros, tema escuro e menu móvel foram exercitados no navegador real sem erros de console.
- Frontend 40/40 e build TypeScript/Vite com 2.309 módulos passaram.
## Regressão visual das telas solicitadas — 17/10/2026

- Cadastro validado em 375/768/1440 px, sem overflow e com 13 controles acessíveis.
- CEP incompleto apresenta feedback local previsível.
- Rota removida `/mensagens` renderiza a página 404 própria em viewport móvel.
- Recuperação de senha renderiza sem overflow e informa a resposta anti-enumeração.
- Tema escuro e console sem erros/avisos foram confirmados; nenhuma correção adicional foi necessária.
## Varredura responsiva consolidada — 17/10/2026

- Dezesseis rotas principais foram renderizadas em 375/768/1440 px, totalizando 48 combinações.
- Nenhuma combinação apresentou overflow horizontal, alerta visível ou erro/aviso de console.
- A evidência cobre estados vazios; dados extensos continuam explicitamente pendentes.

## Serialização nula-segura de oportunidades — BUG 121

- O resource tolera registros legados/importados sem `created_at` ou `updated_at`.
- A coleção continua disponível e representa datas ausentes como `null`.
- Regressão específica 1/4; suíte completa 253/1.726 passou.

## Contenção horizontal de tabelas móveis — BUG 122

- O conteúdo principal e a raiz não propagam largura de tabelas para a página.
- A rolagem horizontal permanece no painel da própria tabela.
- Foram inseridos 28 registros fictícios isolados por módulo; paginação e conteúdo extenso foram exercitados em 375/768/1440 px.
- Frontend 40/40 e build TypeScript/Vite passaram.

## Comando padrão da suíte frontend — BUG 123

- `npm test` passa a executar os 40 testes Node existentes.
- O comando terminou com 40 aprovados, zero falhas; build TypeScript/Vite também passou.

## Formulários internos responsivos — 17/10/2026

- Oportunidade, projeto, tarefa, fornecedor, documento e evento foram abertos em 375/768/1440 px.
- As 18 combinações mantiveram todos os controles dentro da viewport e sem deslocamento horizontal da página.

## Foco e nomes acessíveis dos modais — BUG 124

- Seis modais principais preservam e restauram o foco do botão disparador.
- Escape fecha o diálogo e o primeiro controle recebe foco na abertura.
- Fornecedor, documento, tarefa e evento agora ligam explicitamente o diálogo ao seu título.
- Navegador real confirmou 6/6 fluxos; frontend 40/40 e build passaram.

## CRUD visual de fornecedores — 17/10/2026

- Criação, recarga, busca, filtros combinados, edição e segunda recarga passaram com registro fictício isolado.
- Persistência confirmou tenant 3, contato editado, avaliação 4,9 e posterior exclusão lógica ativada.
- Console não registrou erro ou aviso; somente a falha offline segue pendente.

## CRUD visual e transições de tarefas — 17/10/2026

- Criação, recarga, busca, edição, conclusão, reabertura e filtros combinados passaram com fixture isolada.
- Banco confirmou tenant/relações corretos, custos editados, estado reaberto e data de conclusão limpa.
- Campo de data, exclusão e falha offline permanecem explicitamente fora desta prova.

## Edição e exclusão visual de projetos — BUG 125

- O detalhe do projeto agora oferece ações acessíveis de edição e exclusão.
- A edição reutiliza a validação e normalização do cadastro, preservando vínculo, datas e centavos.
- A exclusão usa confirmação com nome/descrição acessíveis, restaura foco e exibe o bloqueio da API quando há histórico protegido.
- Navegador real confirmou criação, recarga, edição, exclusão lógica e a tentativa negada sobre projeto com despesa ativa.
- Frontend 40/40 e build TypeScript/Vite com 2.309 módulos passaram.

## Confirmação acessível de exclusões operacionais — BUG 126

- `window.confirm` foi substituído em fornecedor, documento e evento por um diálogo reutilizável nomeado e descrito.
- O botão de confirmação exibe estado pendente e o cancelamento permanece disponível antes da requisição.
- Fechar ou cancelar devolve o foco ao botão que abriu o diálogo.
- CRUD visual completo do fornecedor e cancelamento sem mutação em fornecedor, documento e evento foram confirmados.
- Oportunidades, simulações e custos financeiros também usam o componente; regressão estrutural impede `confirm()` nas cinco páginas operacionais.
- Frontend 43/43, suíte PHP 256/1.747 e build com 2.311 módulos passaram.

## Gestão de sessões e logout global — BUG 127

- `GET /auth/sessions` retorna somente metadados dos tokens do usuário e marca o atual.
- `DELETE /auth/sessions/{id}` usa a relação do proprietário; identificador alheio responde 404.
- `POST /auth/logout-all` revoga todos os tokens e invalida eventual sessão web.
- Configurações lista dispositivos e permite encerrar um ou todos com confirmação acessível.
- Testes focados 3/17; suíte completa 256/1.747, frontend 43/43 e build passaram.



## Limites das datas recebidas — BUG 128

- Todas as regras HTTP `date` agora limitam a entrada a 40 caracteres antes da interpretação.
- A cobertura inclui Form Requests, filtros globais e filtros do calendário.
- Uma regressão estrutural impede novas datas sem limite.
- Teste focado 1/1, Pint e suíte completa 264/1.787 passaram.


## Estados cancelados, exclusão lógica e recálculos

- Despesas canceladas e excluídas não entram em etapa, projeto, ROI nem dashboard.
- Reativação recompõe os totais e exclusão dispara novo recálculo.
- Vendas canceladas/excluídas removem o estado vendido quando não resta venda final.
- Etapas/projetos excluídos ficam fora das relações e resumos; falhas transacionais preservam o estado anterior.
- Cenário focado passou com 18 asserções; suíte completa 264/1.787.

## Rollback de upload ITBI — BUG 129

- Arquivo novo é removido quando a criação do lote falha.
- Caminho já existente pelo mesmo hash não é regravado nem apagado.
- Cobertura consolidada inclui rollback financeiro, projetos, documentos e fotos.
- ImportSecurityTest 8/43; suíte completa 264/1.787; Pint aprovado.

## Compatibilidade de planilhas — BUG 130

- CSV detecta BOM UTF-8, UTF-16LE/BE e converte antes de detectar delimitador.
- Campos entre aspas mantêm delimitadores internos.
- XLSX com namespace padrão lê atributos, shared strings e rich text.
- ODS mantém limites de repetição e expansão.
- Testes relacionados 16/33; suíte completa 264/1.787; Pint aprovado.

## Aquisição e recuperação de lote ITBI — BUG 131

- Atualização condicional garante um único processador por lote.
- Segunda execução não zera contadores nem altera o início.
- Identidade da transação é consultada sob lock.
- `--recover-stale=N` exige ação explícita e idade mínima.
- Testes focados 10/48; suíte completa 264/1.787; Pint aprovado.

## Filtro geográfico do provedor municipal — BUG 132

- Consultas com cidade e bairro agora exigem que ambos correspondam à mesma linha.
- Casos de cidade correta/bairro errado e bairro correto/cidade errada são descartados.
- Cenários neutros cobrem sucesso, erro HTTP, exceção, configuração ausente e dataset ausente nos provedores externos.
- Testes focados 5/37; suíte completa 266/1.804; Pint aprovado.

## Isolamento do navegador headless — BUG 133

- Navegação do frame principal e enriquecimento exigem HTTPS em portal permitido.
- Correspondência de host respeita fronteira de domínio e recusa sufixos maliciosos, credenciais e downgrade HTTP.
- Cada página nega downloads antes da primeira navegação.
- Sandbox permanece habilitado por padrão; opt-out exige configuração explícita.
- Frontend 46/46, sintaxe Node e build de 2.311 módulos passaram; egress do host segue como requisito de produção.

## Rollback do download remoto ITBI — BUG 134

- Conteúdo já existente pelo mesmo hash não é sobrescrito nem assumido pela tentativa atual.
- Falha ao atualizar o lote remove somente o arquivo recém-gravado.
- O modelo é recarregado antes de persistir `failed`, evitando caminho/hash sujos.
- Testes com falha SQL real: 9/36; suíte completa 268/1.814; Pint aprovado.

## Rótulos de dados de mercado — BUG 135

- A estimativa específica do imóvel não é mais apresentada como média municipal observada.
- Métricas sem fonte aparecem como indisponíveis, sem promessa de dados futuros.
- O painel identifica valores informados e calculados.
- Frontend 48/48 e build de 2.311 módulos passaram.

## Ação de projeto no dashboard — BUG 136

- O botão inerte virou link acessível para o projeto solicitado.
- `/projetos` seleciona o identificador somente após confirmar que ele pertence à coleção retornada pela API.
- Uma regressão AST cobre todos os botões nativos do frontend.
- Frontend 49/49 e build de 2.311 módulos passaram.

## Links externos de anúncios — BUG 137

- Ação sem URL deixou de ser renderizada.
- Somente HTTPS sem credenciais pode virar link externo.
- Lista e detalhe usam a mesma política e isolamento de opener/referrer.
- Frontend 51/51 e build de 2.312 módulos passaram.

## Ordem estável de hooks na análise — BUG 138

- Estados e efeitos são declarados em todas as renderizações.
- Contexto ausente limpa estado e não chama provedores de mercado ou bairro.
- Trocas de parâmetro não alteram a quantidade de hooks.
- Frontend 52/52 e build de 2.312 módulos passaram.

## Prova concorrente da venda final

- Dois processos sobrepostos disputaram a venda final do mesmo imóvel no MariaDB 11.4.10.
- Resultados 201/422 confirmaram serialização e recusa segura do perdedor.
- Estado final: uma venda ativa e imóvel `vendida`.
- Banco descartável encerrado; credenciais e tokens transitórios removidos.

## Prova concorrente do lote ITBI

- Dois workers MariaDB sobrepostos disputaram o mesmo lote de 500 linhas.
- Um concluiu e o segundo foi recusado como já em processamento.
- Estado final contém 500 importações únicas, sem atualização ou falha.
- Servidor, CSV e segredos transitórios foram removidos após a prova.

## Redação dos logs ITBI — BUG 139

- Mensagens integrais de exceção deixaram de ser copiadas para logs de linha e upload.
- Nome original foi substituído por hash SHA-256.
- IDs operacionais, linha e classe da exceção preservam capacidade de diagnóstico.
- Testes focados 9/48; suíte completa 269/1.819; Pint aprovado.

## Redação do stderr do scraper — BUG 140

- Saída textual de Chromium/Puppeteer deixou de ser copiada para logs.
- Código, tamanho e hash preservam diagnóstico e correlação.
- Teste com subprocesso real: 1/5; suíte completa 270/1.824; Pint aprovado.

## Redação da falha global do lote ITBI — BUG 141

- `error_message` armazena uma mensagem pública estável, sem texto originado de exceções internas.
- O logger preserva somente ID do lote e classe da exceção; a causa original continua encadeada internamente.
- O comando CLI orienta a consulta pelo ID do lote sem imprimir a mensagem capturada.
- Regressão com marcador privado: 11 testes/56 asserções focadas; suíte completa 271/1.832 e Pint aprovados.

## Política de capa externa — BUG 142

- Capas remotas exigem HTTPS público, sem credenciais e sem porta imprópria.
- Localhost, domínios `.local` e IPs privados/reservados são recusados.
- Request e serviço compartilham a mesma política; o resource filtra registros legados antes da resposta.
- Testes focados 19/124; suíte completa 273/1.863 e Pint aprovados.


## Verificação visual de Configurações

- Estado móvel escuro de falha em fontes e sessões renderizado em Chromium real.
- Dois alertas e dois retries independentes foram exercitados.
- Recuperação restaurou sessão e fonte sem reload, overflow ou exceção JavaScript.
- Harness reproduzível: `frontend/scripts/configuration-ui-check.mjs`.
- Leitor de tela real continua no checklist.

## Preservação do cadastro de fornecedor em falha de rede

- POST 503 mantém o diálogo e os sete valores informados.
- A ação volta a ficar disponível para retry sem duplicação.
- Retry 201 fecha o modal, mostra sucesso e recarrega um único cartão.
- Chromium real em 375×812, tema escuro, sem overflow ou exceção JavaScript.
- Harness reproduzível: `frontend/scripts/supplier-ui-network-check.mjs`.

## Erro visível na exclusão de fornecedor — BUG 143

- Falha no DELETE agora fecha o diálogo de confirmação.
- O alerta global e a ação de nova tentativa ficam acessíveis.
- O cartão não é removido quando o servidor rejeita a exclusão.
- Chromium móvel real, frontend 52/52 e build com 2.312 módulos aprovados.

## Alertas visíveis em falhas destrutivas — BUG 144

- Calendário, Documentos e Tarefas fecham a confirmação quando o DELETE falha.
- O recurso permanece na coleção e o alerta da página deixa de ficar bloqueado.
- Regressão cobre nove handlers destrutivos em sete páginas.
- Frontend 53/53 e build de 2.312 módulos aprovados.

## Tratamento de falhas nas ações de Oportunidades — BUG 145

- Detalhe, edição, favorito e análise financeira capturam falhas da API.
- Cada fluxo restaura o estado pendente em `finally`.
- O detalhe mostra o alerta dentro do modal e bloqueia ações concorrentes.
- Frontend 55/55 e build com 2.312 módulos aprovados.

## Falhas nas ações de financiamento — BUG 146

- Referência e seleção de simulação agora capturam falhas da API.
- Estado pendente sempre é restaurado em `finally`.
- Controles de comparação e recarga bloqueiam ações concorrentes.
- Frontend 57/57 e build de 2.312 módulos aprovados.

## Falhas remotas no wizard e orçamento de etapas — BUG 147

- Recálculo financeiro captura falhas e sempre encerra o estado de carregamento.
- Atualização de orçamento mostra o erro, bloqueia chamadas concorrentes e restaura o valor anterior se o servidor rejeitar a alteração.
- Frontend 59/59 e build de 2.312 módulos aprovados.

## Proteção contra gravações financeiras duplicadas — BUG 148

- Adicionar simulação, gasto e resultado ativa um estado pendente comum.
- Os controles relacionados ficam desabilitados até sucesso ou falha.
- `finally` restaura a interface em todos os três caminhos; o resultado salvo recebe confirmação.
- Frontend 60/60 e build de 2.312 módulos aprovados.

## Confirmação financeira condicionada à recarga — BUG 149

- A recarga informa sucesso ou falha ao chamador.
- Financiamento, simulação e resultado só exibem confirmação após atualizar os dados da tela.
- A aba não avança quando a sincronização visual falha.
- Frontend 61/61 e build de 2.312 módulos aprovados.

## Busca da IA ordenada e recuperável — BUG 150

- Nova busca aborta a anterior e respostas tardias não alteram mais o ranking atual.
- O botão fica indisponível durante a requisição e o unmount cancela trabalho pendente.
- Chromium móvel comprovou 502 visível, retry 200, concorrência máxima 1 e recuperação sem overflow.
- Frontend 63/63 e build de 2.312 módulos aprovados.

## Miniaturas externas seguras na busca da IA — BUG 151

- Miniaturas precisam usar HTTPS público, sem credenciais e com porta padrão.
- Destinos locais, privados, reservados, `.local`, HTTP e `data:` são omitidos da resposta.
- O placeholder local já existente substitui qualquer miniatura recusada.
- Testes focados 3/20 e Pint aprovados.

## Links externos públicos na IA — BUG 152

- Backend e frontend recusam links para localhost, IPs não públicos, `.local`, credenciais, HTTP e portas alternativas.
- Uma política comum atende links e imagens, reduzindo divergência entre validações.
- URLs públicas HTTPS por domínio e IPv4 continuam aceitas.
- Frontend 63/63, endpoint 3/20, Pint e build aprovados.

## Bloqueio de IP ofuscado em URLs externas — BUG 153

- IPv4 decimal inteiro, hexadecimal, octal e abreviado deixam de ser tratados como domínio público.
- IPv6 com colchetes é normalizado antes da classificação de faixa.
- Controles públicos por domínio e IPv4 canônico permanecem permitidos.
- Política 9/9, integrações 22/144, frontend focado 3/3, Pint e suíte PHP 282/1.876 aprovados.

## URL de origem segura em oportunidades — BUG 154

- Criação e atualização aceitam somente origem HTTPS pública.
- O resource omite URLs legadas inseguras.
- Capas recuperadas do `sessionStorage` são revalidadas antes do uso.
- PHP 283/1.905, frontend 64/64, Pint e build aprovados.

## Redação de diagnósticos externos da busca — BUG 155

- `AIPropertySearchController` transforma diagnósticos não confiáveis em um contrato público limitado: fonte normalizada, status 0–599, quantidade 0–250 e mensagem fixa.
- A resposta 502 e o caminho de sucesso passam pela mesma sanitização e aceitam no máximo 25 entradas.
- A regressão injeta caminho de filesystem, loopback e marcador privado e comprova ausência no JSON.
- Teste focado 3/24, Pint e suíte PHP completa 283/1.909 passaram. Evidência: `meta/ai-diagnostic-redaction-checks.json`.

## Egress e limite dos provedores de mercado — BUG 156

- Os endpoints configurados passam por `ExternalUrlPolicy` antes de qualquer requisição.
- Cartórios, prefeituras e ITBI desativam redirects e descompressão e solicitam transferência `identity`.
- O teto comum de 2 MiB é verificado no header, no progresso e no corpo final.
- Testes focados 9/68, Pint e suíte PHP 286/1.938 passaram. Evidência: `meta/market-provider-egress-limits-checks.json`.


## Egress do contexto de bairro — BUG 157

- `NeighborhoodContextService` passou a aplicar política pública HTTPS a endpoints geográficos.
- Google, Nominatim e Overpass desativam redirects/descompressão e limitam cada resposta a 2 MiB em três camadas.
- Regressões cobrem bloqueio pré-egress de loopback/link-local/HTTP e excesso em header, progresso e corpo final.
- Testes focados 7/37, Pint e suíte PHP 291/1.952 passaram. Evidência: `meta/neighborhood-egress-limits-checks.json`.

## Proteção da consulta de CEP — BUG 158

- ViaCEP agora usa transferência `identity`, sem redirects ou descompressão automática, limitada a 64 KiB em três camadas.
- Campos retornados são aparados e seguem os mesmos limites do cadastro; UF é normalizada e validada.
- Regressões cobrem opções do transporte, excessos em header/progresso/corpo e payloads incompatíveis.
- Testes focados 9/54, Pint e suíte PHP 293/1.962 passaram. Evidência: `meta/postal-code-provider-guards.json`.

## Proteção dos fallbacks da busca por IA — BUG 159

- Nestoria e Mercado Livre passaram a usar transporte comum com TLS verificado, sem redirects/descompressão e `Accept-Encoding: identity`.
- Cada resposta é limitada a 2 MiB em header, progresso e corpo final.
- Regressões cobrem os dois provedores e preservam erro público neutro.
- Testes focados 5/46, Pint e suíte PHP 295/1.984 passaram. Evidência: `meta/ai-provider-transfer-guards.json`.

## Autorização do nome de arquivo ITBI — BUG 160

- A listagem compartilhada omite o nome original do lote para usuários sem `itbi.import`.
- Operadores autorizados continuam vendo o nome na listagem e na resposta do upload.
- A regressão comprova os dois perfis sobre o mesmo lote e ausência do marcador privado para o perfil comum.
- Testes focados 10/55, Pint e suíte PHP 296/1.991 passaram. Evidência: `meta/itbi-filename-authorization-checks.json`.

## Alinhamento do status de fontes — BUG 161

- O painel de fontes passou a validar configurações com `ExternalUrlPolicy`, a mesma regra usada antes do egress.
- Endpoints privados, locais, inseguros ou em porta não permitida deixam de aparecer como configurados.
- Testes focados 4/30, Pint e suíte PHP 297/1.996 passaram. Evidência: `meta/data-source-policy-alignment-checks.json`.


## Invariante automatizada da fronteira de autenticação — 18/10/2026

O inventário executável percorre todas as rotas `/api`: somente cadastro, login, recuperação/redefinição, CEP e confirmação assinada podem ser públicas; seis rotas de sessão/confirmação exigem autenticação e conta ativa antes da verificação; todas as demais exigem também e-mail confirmado. Upload ITBI, upload de documento e três rotas externas mantêm suas permissões/throttles específicos. A revisão estática não encontrou gravação com `$request->all()` ou payload bruto. SecurityRegressionTest 29/415 e suíte completa 299/2.249 passaram. Evidência: `meta/api-auth-boundary-checks.json`.

## Dimensões e nome das fotos de oportunidades — BUG 162

- Uploads agora limitam 12.000 px por lado e 40 milhões de pixels, além de formato e bytes.
- O nome original é reduzido ao basename, limpo de controles e limitado a 240 caracteres.
- A regressão comprova rejeição sem órfão e persistência segura de nome hostil.
- Oportunidades 21/163, Pint e suíte PHP 300/2.259 passaram. Evidência: `meta/opportunity-image-dimension-checks.json`.

## Persistência resiliente da análise da IA — BUG 163

- `sessionStorage` e `localStorage` são tentados de forma independente, evitando que a indisponibilidade de um bloqueie uma gravação válida no outro.
- Quando nenhum armazenamento aceita o contexto, a página mantém o usuário na lista e apresenta uma orientação clara.
- A regressão cobre o isolamento das exceções e o caminho de erro. Frontend 65/65 e build de 2.312 módulos passaram. Evidência: `meta/ai-analysis-storage-resilience-checks.json`.

## Notas locais resilientes no Wizard Financeiro — BUG 164

- JSON local inválido, arrays, tipos inesperados e objetos excessivos passam a usar as descrições retornadas pela API.
- A leitura limita quantidade e tamanho das notas; a escrita local captura indisponibilidade e cota cheia.
- Frontend 66/66 e build de 2.312 módulos passaram. Evidência: `meta/wizard-note-storage-checks.json`.

## Armazenamento resiliente do token de autenticação — BUG 165

- O token passou a ter leitura, gravação e remoção centralizadas com captura de falhas do navegador.
- Um fallback em memória mantém a sessão atual funcional quando a persistência é bloqueada; logout sempre elimina essa cópia.
- O teste comportamental executa o helper contra armazenamento que lança `SecurityError`, e a regressão estática cobre todos os consumidores. Frontend 69/69 e build de 2.313 módulos passaram. Evidência: `meta/auth-token-storage-checks.json`.

## Preferência resiliente do guia de score — BUG 166

- A Central Financeira captura falhas ao ler a preferência e mostra o guia como fallback seguro.
- Dispensar o guia sempre atualiza a interface, mesmo quando a persistência local é recusada.
- Frontend 70/70 e build de 2.313 módulos passaram. Evidência: `meta/score-guide-storage-checks.json`.

## Validação e fallback do cache da análise — BUG 167

- Sessão e armazenamento persistente são lidos isoladamente; falha ou corrupção de um não oculta a cópia válida seguinte.
- ID, item principal, comparáveis, metadados e diagnósticos são validados e limitados antes do render.
- Frontend 72/72 e build de 2.313 módulos passaram. Evidência: `meta/ai-analysis-cache-validation-checks.json`.

## Política da miniatura no detalhe da IA — BUG 168

- A origem armazenada é revalidada pela política HTTPS pública no último ponto antes do `<img>`.
- Destinos locais, privados, link-local, com credenciais ou porta alternativa resultam em placeholder sem egress.
- Frontend 73/73 e build de 2.313 módulos passaram. Evidência: `meta/ai-analysis-thumbnail-policy-checks.json`.

## Namespace das fotos locais de oportunidades — BUG 169

- Uma política compartilhada aceita somente caminhos segmentados sob `opportunities/`.
- O resource omite traversal e referências a outros namespaces; exclusão e serialização agora aplicam a mesma regra.
- Oportunidades 22/170, Pint e suíte PHP 301/2.266 passaram. Evidência: `meta/opportunity-photo-path-policy-checks.json`.

## Política da capa na análise financeira — BUG 170

- `InvestmentAnalysisResource` deixou de manter uma conversão permissiva própria.
- Capas externas e locais usam as mesmas políticas do resource de oportunidades; valores recusados tornam-se `null`.
- Análises 11/87, Pint e suíte PHP 302/2.280 passaram. Evidência: `meta/investment-cover-url-policy-checks.json`.

## Minimização da resposta da análise financeira — BUG 171

- A análise e suas relações deixaram de ser serializadas integralmente e passaram a usar listas explícitas de campos públicos.
- `user_id`, `deleted_at` e `investment_analysis_id` não atravessam mais o contrato; campos necessários para exibição, seleção e exclusão foram mantidos.
- A regressão valida simultaneamente a utilidade da resposta e a ausência das chaves internas.
- Análises 12/106, Pint e suíte PHP 303/2.299 passaram. Evidência: `meta/investment-analysis-data-minimization-checks.json`.

## Minimização das respostas de etapa — BUG 172

- `ProjetoEtapaResource` centraliza os campos públicos de uma etapa.
- Projeto detalhado, criação e atualização deixaram de serializar o model integral.
- Tenant, chave do projeto, exclusão lógica e timestamps administrativos foram removidos; dados operacionais continuam disponíveis.
- Projetos relacionados 33/316, Pint e suíte PHP 304/2.320 passaram. Evidência: `meta/project-stage-data-minimization-checks.json`.

## Configuração segura das origens CORS — BUG 173

- `FRONTEND_URL` passou a compor a allowlist efetiva de CORS.
- `CORS_ALLOWED_ORIGINS` permite domínios adicionais explícitos separados por vírgula.
- A política recusa wildcard, credenciais, caminhos, query, fragmento e esquemas fora de HTTP(S), além de normalizar e remover duplicatas.
- Preflights permitido/recusado, oito entradas da política, cache de configuração, API 6/64, Pint e suíte PHP 306/2.330 passaram. Evidência: `meta/cors-origin-configuration-checks.json`.

## Patches compatíveis das dependências frontend — BUG 174

- `@types/react` foi atualizado de 18.3.28 para 18.3.31 e `postcss` de 8.5.30 para 8.5.31.
- `npm ci --ignore-scripts` demonstrou instalação limpa e reproduzível sem autorizar o postinstall sinalizado pelo npm.
- Audit completo/produção permaneceu em zero vulnerabilidades; frontend 73/73 e build de 2.313 módulos passaram.
- Nove versões principais disponíveis ficaram documentadas para migração deliberada. Evidência: `meta/frontend-dependency-refresh-checks.json`.

## Reconciliação final do inventário de rotas — BUG 175

- A matriz foi comparada programaticamente com o roteador Laravel após normalizar nomes de parâmetros.
- Foram acrescentados os 16 caminhos ausentes de autenticação, fontes, documentos, calendário e tarefas, com método, fronteira de acesso, validação, limite e teste correspondente.
- O inventário resultante cobre 59/59 caminhos distintos da API, sem caminho ausente ou obsoleto.
- A segunda revisão repetiu 306 testes PHP/2.330 asserções, 73 testes frontend, build de 2.313 módulos, Pint, lint de 191 arquivos PHP, Composer/npm audit, cache de configuração e `git diff --check`. Evidência: `meta/final-regression-review-checks.json`.

## Identificadores inválidos de tarefa — BUG 176

- A reprodução automatizada confirmou HTTP 500 quando `{tarefa}` recebia texto e chegava ao argumento inteiro do controller.
- O middleware central agora valida também `tarefa` e `tokenId` como inteiros positivos representáveis.
- A regressão percorre texto, negativo e valor excessivo em propriedades, projetos, despesas, vendas, tarefas, documentos, fornecedores, calendário, oportunidades, análises, financiamentos, custos e resultados.
- A prova inicial 1/42 foi ampliada para 1/75; Pint e suíte completa atual 308/2.388 passaram. Evidências: `meta/task-route-identifier-checks.json` e `meta/resource-identifier-coverage-checks.json`.

## Limites do schema na normalização ITBI — BUG 177

- A reprodução confirmou que o SQLite aceitava `street_name` com 181 caracteres em coluna declarada com 180, tornando o resultado dependente do banco.
- O normalizador passou a aplicar limites de texto/caracteres, `TEXT` em bytes, UF, CEP, precisão decimal, não negatividade, finitude e tamanho do endereço composto antes da persistência.
- Um CSV realista com uma linha válida e outra fora do schema agora produz `completed_with_errors`, uma importada e uma falha sem depender de listener/mock.
- Importação/normalizador 37/154, suíte PHP 308/2.355, frontend 73/73, build 2.313 módulos e Pint passaram. Evidência: `meta/itbi-schema-bound-normalization-checks.json`.

## Fronteira antes do binding e ocultação de recursos — BUG 178

- `active-account` e `verified-account` agora precedem throttle específico, model binding e autorização `can` na prioridade global.
- Toda rota empresarial devolve a barreira uniforme de confirmação antes de resolver IDs ou executar controller.
- Oportunidades, análises e filhos financeiros alheios usam 404; o Form Request preserva `denyAsNotFound()`.
- A regressão dinâmica cobre todos os métodos protegidos, e a matriz de propriedade comprova ausência de mutação em seis tabelas.
- Testes focados 57/801, suíte completa 311/2.761 e Pint aprovados. Evidência: `meta/prebinding-authorization-checks.json`.

## Sessão isolada das requisições públicas — BUG 179

- Login, cadastro, recuperação, CEP e confirmação pública deixaram de receber o bearer existente.
- Respostas 401 só limpam a sessão se a chamada protegida realmente enviou `Authorization: Bearer`.
- Reenvio de confirmação continua autenticado e fora da allowlist pública.
- Frontend 74/74 e build com 2.314 módulos aprovados. Evidência: `meta/public-auth-session-isolation-checks.json`.

## Orçamento distribuído de login — BUG 180

- Mantidos 10 tentativas por IP/minuto e 5 por conta+IP/minuto.
- Adicionado teto de 30 tentativas por conta/hora, identificado apenas por hash do e-mail normalizado.
- Regressão com 31 IPs fictícios comprova 401 nas primeiras 30 e 429 na seguinte.
- Testes focados 2/32, suíte completa 312/2.792 e Pint aprovados. Evidência: `meta/distributed-login-rate-limit-checks.json`.

## Serialização de tarefas e etapas — BUG 181

- `TarefaService` agora bloqueia e recarrega a tarefa dentro da transação antes de derivar `data_conclusao`, preservando a ordem de lock iniciada pelo projeto.
- Regressão determinística cobre a cópia obsoleta que antes podia produzir tarefa concluída sem data.
- Três pares de processos sobrepostos em MariaDB validaram PATCHes disjuntos, conflitos no mesmo campo e atualização de etapa/orçamento.
- Política: mudanças disjuntas são preservadas; em conflito do mesmo campo, vence o último commit; campos derivados sempre usam a linha bloqueada atual.
- Suíte PHP 313/2.793, frontend 74/74, build de 2.314 módulos, Pint e auditorias de dependência passaram. Evidência: `meta/task-stage-concurrency-mariadb.json`.

## Revalidação bloqueante de pais — BUG 182

- Uma corrida real produziu despesa 201 e exclusão de projeto 200, deixando um filho ativo sob pai excluído.
- A causa era uma consulta `ativos()` comum após espera por lock; sob `REPEATABLE READ`, ela podia reutilizar snapshot antigo.
- Revalidações de propriedade/projeto em despesas, documentos, eventos, tarefas e projetos agora também usam leitura bloqueante atual.
- Dezesseis pares de processos MariaDB cobrem oito disputas nos dois ordenamentos, inclusive consistência entre arquivo privado e registro de documento.
- Todos os invariantes passaram; suíte PHP 313/2.793, frontend 74/74, build 2.314 e Pint aprovados. Evidência: `meta/parent-deletion-concurrency-mariadb.json`.

## Cobertura visual de Tarefas e Calendário

- Tarefas foi exercitada com fixture descartável em criação, recarga, busca, filtro, exclusão e entrada nativa de data.
- Calendário foi exercitado em criação, recarga, navegação mensal, edição, exclusão e renderização de evento multidiário.
- Em ambos os módulos, HTTP 503 simulado manteve o diálogo e os valores digitados, com erro visível; tema escuro, viewport 375x812 e ausência de overflow também foram verificados.
- Evidências: `meta/task-ui-crud-checks.json`, `meta/calendar-ui-crud-checks.json` e respectivas capturas móveis.

## Feedback correto após mutação — BUG 183

- Sete loaders de coleção agora retornam sucesso/falha da recarga em vez de ocultar o resultado do chamador.
- Dezesseis ações de criar, editar, concluir, favoritar, arquivar, excluir ou registrar pagamento distinguem falha da mutação de falha posterior da consulta.
- Quando o servidor já confirmou a mudança, a interface informa que ela ocorreu e pede atualização antes de nova tentativa, evitando feedback contraditório e repetição indevida.
- Abrangência: Calendário, Documentos, Fornecedores, Despesas, Imóveis, Tarefas e Oportunidades.
- Regressão específica 2/2, frontend 76/76, build 2.314 módulos e automações visuais de Tarefas/Calendário aprovados. Evidência: `meta/mutation-refresh-feedback-checks.json`.

## Feedback nos fluxos complexos — BUG 184

- Projetos passou a distinguir recarga falha depois de salvar/excluir projeto, criar/editar despesa e criar/alterar orçamento de etapa.
- Central Financeira informa corretamente o resultado de financiamento, simulações, custos e resultado quando o GET posterior falha.
- Importação ITBI preserva o resultado do lote e acrescenta o aviso sobre histórico desatualizado.
- O assistente financeiro só avança de etapa após recarregar a análise; se a escrita foi confirmada e o GET falha, explica o estado e impede repetição silenciosa.
- Verificação focada 11/11, frontend 77/77 e build 2.314 módulos aprovados. Evidência: `meta/mutation-refresh-feedback-batch-2-checks.json`.

## Relatórios avançados — BUG 185

- `GET /api/reports/ledger` entrega a trilha de aquisições, despesas válidas e vendas finais, com filtros de período/projeto e paginação 1–100.
- Totais de entradas, saídas e saldo usam o conjunto filtrado completo; a página visível é só um recorte.
- Tenant obrigatório; projeto alheio ou excluído retorna 422; arrays e intervalo invertido são rejeitados antes da consulta.
- `/api/dashboard` permanece acumulado e sem filtro, para o painel atual continuar compatível.
- A tela exporta o resumo acumulado e um CSV da trilha filtrada, ambos com proteção contra fórmula.
- PHP 321/2.881, frontend 81/81, Pint e build de 2.314 módulos aprovados. Evidência: `meta/financial-ledger-checks.json`.

## IA conversacional e visualização 3D — BUG 186

- O scraper e o `comparables-v3` permanecem a fonte de anúncios; a conversa só traduz linguagem natural em filtros.
- Teto de preço, quartos e vagas obrigatórios não são relaxados sem o usuário pedir.
- `/imoveis/{id}/3d` e `/leads/{id}/3d` existem com fallback honesto quando não há GLB.
- Análise de foto declara origem heurística e recusa extrair medidas.
- Reconstrução neural/fotogrametria e simulador tipo referência comercial não foram fingidos.

## Análise do anúncio, APIs públicas e persistência do radar — BUG 187

- Voltar ao radar não zera a busca: o primeiro write vazio é ignorado e o snapshot vai para sessionStorage e localStorage.
- Abrir a análise envia o contexto no history state, além do cache `market-analysis:{id}`.
- A ficha `/ia-inteligente/analise/{id}` mostra um modelo 3D ilustrativo do próprio anúncio, rotulado como não-fotogrametria.
- DataZAP/FipeZAP não são mais o “próximo nível”. SELIC/CDI/IPCA/IVG-R vêm do BCB SGS; ITBI usa CKAN público.
- Cartório sem contrato deixa de pedir `CARTORIOS_API_URL` como se fosse obrigatório.

## Chat limpo e maquete exterior/interior — BUG 189

- `IAInteligente` passou a ter abas Conversar / Radar; o hero de marketing saiu da primeira tela.
- `AiConsultantPanel` usa composer com textarea, Enter envia, no máximo 3 exemplos e cards com Ver 3D / detalhes / carteira.
- `PropertySceneSpec` + `PropertyMaquetteViewer` substituíram as caixas soltas: fachada e planta interna com selo de fidelidade.
- Anúncio de portal na conversa abre `/ia-inteligente/analise/{id}`; a resposta declara que ONR/DataZAP/leilão não foram consultados.

## Total investido ignora prospecção — BUG 188

- `/api/dashboard` soma `valor_aquisicao` só com `data_aquisicao` preenchida.
- O preço pedido de um lead/QA em prospecção não aparece mais como capital investido.
- Despesas não canceladas seguem no total.
