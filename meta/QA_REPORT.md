# QA — registro de bugs e reprodução local

Estado: parcial, 16/10/2026. Os cenários abaixo usam dados fictícios em ambiente local isolado. Os comportamentos anteriores foram registrados nos arquivos *before*.txt/json; não reverta a aplicação em execução para demonstrá-los. Para execução automatizada: php artisan test --filter=NOME_DO_TESTE. Não use dados reais.

## BUG 01 — Tenant ausente amplia consulta

Categoria Segurança; severidade Alta; corrigido. Arquivo app/Models/Scopes/TenantScope.php, apply; GET /api/propriedades. Causa: escopo só aplicado quando tenant_id existia. Impacto: leitura entre empresas.

Reprodução anterior: criar tenant A e imóvel fictício; criar usuário sem tenant; autenticar; listar imóveis. Esperado: lista vazia ou bloqueio. Observado: imóvel de A retornava. Verificação: repetir; data deve ter zero itens. Teste test_tenantless_user_cannot_list_another_tenants_properties. Conta legítima com tenant continua com CRUD funcional, ver DomainIntegrityTest.

## BUG 02 — Conta/empresa desativada mantém acesso

Categoria Segurança; Alta; corrigido. AuthService::login e EnsureActiveAccount; /api/auth/login e /api/auth/me. Causa: somente deletado_flg era verificado no login; token não verificava atividade. Impacto: suspensão ineficaz.

Reprodução anterior: criar conta fictícia ativa, emitir token, marcar ativo_flg=false no ambiente de teste; chamar me e tentar login. Ambos aceitavam a conta. Repetir com tenant inativo também permitia me. Agora todas as chamadas devem dar 401; conta ativa continua entrando. Testes test_disabled_account_cannot_login, test_disabled_account_cannot_use_existing_token, test_inactive_tenant_cannot_use_existing_token.

## BUG 03 — Tentativas de login sem limite

Segurança; Média; corrigido. routes/api.php e AppServiceProvider. Enviar 11 logins inválidos para a mesma conta fictícia/IP local: antes todas as respostas eram 401; agora recebe 429 ao atingir limite. Aguardar janela para validar login legítimo. Teste test_login_attempts_are_limited. Evitar repetição fora do ambiente local.

## BUG 04 — Tokens sem prazo

Segurança; Média; corrigido. AuthService e config/sanctum.php. Fazer login fictício e inspecionar expires_at no banco de teste: antes null; agora preenchido para 12 horas. Teste test_issued_tokens_expire. Não imprimir o token em relatórios.

## BUG 05 — Paginação inválida aceita

Funcional/Segurança; Média; corrigido. ValidatePagination. GET /api/opportunities?per_page=-1 e per_page[]=10; antes primeiro caso retornava 200; agora ambos 422. Repetir com per_page=10: 200. Teste test_invalid_pagination_is_rejected.

## BUG 06 — Escrita na base ITBI sem autorização específica

Segurança; Alta; corrigido. routes/api.php e Gate import-itbi. Conta comum autenticada, POST /api/itbi-imports/upload sem arquivo: antes alcançava validação e retornava 422; agora 403 antes de processar arquivo. Para fluxo positivo, usar operador com permissão itbi.import e CSV fictício: 201. Testes test_regular_user_cannot_write_shared_itbi_dataset e ImportSecurityTest. Base é pública/compartilhada por projeto; contas comuns continuam podendo ler.

## BUG 07 — Campos extras em endereço atravessam whitelist

Segurança; Alta; corrigido. PropriedadeRequest::rules. Com conta do tenant A, POST /api/propriedades com apelido e endereco.cidade fictícios, adicionando endereco.tenant_id=B. Antes recebia 201; validação de array-pai preservava campos adicionais. Agora 422 e nenhum endereço criado. Repetir sem campo extra: 201. Teste test_nested_address_cannot_override_tenant. Etapas e categorias também receberam lista explícita.

## BUG 08 — Despesa combina projeto e etapa incompatíveis

Lógica; Média; corrigido. DespesaRequest::after. Criar projetos A/B no mesmo tenant, etapa de B, categoria; enviar despesa de 100 com projeto A e etapa B. Antes 201, afetando custos inconsistentes; agora 422. Repetir com etapa de A e propriedade correta: criação permitida. Teste test_expense_cannot_mix_projects_and_stages; CRUD legítimo em DomainIntegrityTest.

## BUG 09 — Resultado salvo não acompanha troca de financiamento

Lógica; Média; corrigido. InvestmentAnalysisController::applySelectedFinancing/destroyFinancingSimulation. Criar análise, simulações A/B com quitação fictícia 80000/70000, salvar resultado, selecionar B. Antes resultado persistia quitação 80000. Agora 70000 e capital/ROI recalculados. Excluir A e depois B: valores previstos zerados e etapa de financiamento incompleta. Teste FinancialConsistencyTest.

## BUG 10 — Exceções de integração expostas

Segurança; Média; corrigido. PrefeituraTransactionProvider, demais provedores, NeighborhoodContextService e PropertyPortalScraper. No teste, simular falha HTTP com texto privado fictício; antes message incluía o texto. Agora mensagem neutra. Teste test_provider_exceptions_do_not_disclose_credentials. Testar sucesso com resposta simulada válida continua pendente por provedor.

## BUG 11 — Importação vazia anuncia sucesso

Funcional; Média; corrigido. ItbiTransactionImportService. Operador, upload CSV contendo apenas irrelevant;headers. Antes 201/sucesso sem transação; agora 422/lote falho, sem transação. CSV válido e reenvio idempotente continuam aprovados. ImportSecurityTest.

## BUG 12 — Saída indisponível na interface

Interface; Média; corrigido. Sidebar. Antes não havia botão de encerrar sessão. Agora Sair da conta chama API, remove token após revogação e leva ao login; falha de rede mantém erro visível e permite tentar de novo. Login/logout testados no navegador; revogação e preservação de outro token em test_logout_deletes_only_current_bearer_token. Falha de rede visual ainda não testada.

## BUG 13 — Layout cortado em viewport estreito

Interface; Média; correção parcial verificada. AppLayout/Sidebar/Topbar. Abrir Importar ITBI em painel estreito: antes sidebar consumia largura e cortava conteúdo. Agora menu recolhível; abrir Menu e navegar para Leads fecha menu. Abertura e navegação verificadas. Ainda falta revisar todos os formulários e tabelas em múltiplas larguras.

## BUG 14 — Dados demonstrativos exibidos como dados da conta

Funcional/Lógica; Média; correção funcional implementada, com validação visual completa ainda pendente. O achado original abrangia Dashboard, componentes dashboard, Topbar, Sidebar e páginas Documentos/Fornecedores/Mensagens/Calendario: contas novas recebiam números, identidades e documentos fixos. Os módulos ativos foram substituídos por contratos reais e estados vazios nos BUGs 28 e 55–66; Mensagens foi removida por decisão explícita do usuário no BUG 60 e o último `PageStub` foi eliminado no BUG 65. Ainda faltam exercitar visualmente todos os controles com fixtures, falhas de rede e dados extensos antes de encerrar integralmente este achado.

## Planilhas — controles e testes adicionais

SpreadsheetSafetyTest verifica texto inline XLSX, rejeição de DTD, limite XML antes de descompressão e índices inválidos. ODS com repetição de linhas deixou de descartar silenciosamente linhas não vazias; falta fixture de regressão específica dessa repetição. Esses controles foram adicionados por inspeção; não foi executado ataque de exaustão real.

## BUG 15 — Parcelas pagas excedem contrato

Lógica/validação; Média; corrigido. FinancingDetailRequest::rules e InvestmentResultRequest::rules. Pré-requisito: análise privada de conta fictícia local. POST /api/investment-analyses/ID/financing-simulations com total_installments=12 e paid_installments=13. Antes: 201 e capital calculado com quantidade impossível. Agora: 422. Quantidades de parcelas/duração acima de 600 também são rejeitadas; taxas e valores têm limites de entrada. Repetir com total=12 e pagas=2 deve permitir criação. FinancialValidationTest verifica rejeições; FinancialConsistencyTest preserva fluxo legítimo. Evidência anterior: financial-validation-before.txt.

## BUG 16 — Despesa cancelada continua nos custos

Lógica; Média; corrigido. ProjetoService::recalcularCustos/roiProjetado e VendaService::roiPropriedade. Conta fictícia local com imóvel, projeto e categoria; criar despesa paga de 500, conferir resumo, atualizar status_pagamento para cancelado e consultar resumo/ROI novamente. Antes: custo continuava 500. Agora: custo e total_despesas são zero. Registros pagos/pendentes continuam computados conforme modelo de competência existente; exclusão lógica permanece respeitada. Teste test_cancelled_expense_does_not_inflate_project_cost_or_property_roi. Evidência: cancelled-expense-before.txt.

## BUG 17 — Venda cancelada/excluída deixa imóvel vendido

Lógica; Média; corrigido. VendaService::criar/atualizar/excluir e sincronizarStatusPropriedade. Criar imóvel fictício a_venda, cadastrar venda fechada, cancelar ou excluir a venda. Antes: imóvel permanecia vendida. Agora: a_venda se não houver outra venda fechada/escriturada ativa. Atualização que troca propriedade sincroniza origem e destino; outra venda concluída preserva vendida. Operação e sincronização ficam em transação; registro de propriedade recebe lockForUpdate nos bancos que suportam. Não se afirma teste de corrida em SQLite. Testes test_cancelled_or_deleted_sale_does_not_leave_property_sold e test_moving_sale_updates_both_properties_and_preserves_other_closed_sale. Evidência: sale-state-before.txt.

## BUG 18 — Resposta malformada de provedor derruba consulta

Funcional/estabilidade; Média; corrigido. CartorioTransactionProvider::search, PrefeituraTransactionProvider::search, ItbiMunicipalProvider::filterRows/csvRows e NormalizesTransactions::normalizeTransaction. Ambiente local com Http::fake, sem chamadas externas. Simular JSON data=[null,"invalid",{"price":["invalid"]},{"price":"R$ 735.000,00","area_m2":98}]. Antes: TypeError no primeiro elemento nulo; consulta abortava. Agora: três entradas malformadas ignoradas e uma transação normalizada com preço 735000 e preço/m² 7500. ITBI CSV com coluna extra não causa array_combine ValueError; linhas com quantidade divergente são rejeitadas e linhas válidas preservadas. Testes MarketProviderTest; inclui HTTP 503 sem transações e sem sucesso fictício. Falha anterior em provider-malformed-before.txt. Ainda falta padronizar diagnóstico de contagem de linhas rejeitadas e exercitar todos os formatos dos provedores reais; não se afirma integração externa validada.

## BUG 19 — Status arquivado diverge de flag de listagem

Lógica/funcional; Média; corrigido. Opportunity::booted/saving e migration 2026_10_16_000001_sync_opportunity_archive_flag. Pré-requisito: conta fictícia local. Criar oportunidade pelo formulário/API com status=archived. Antes: resposta is_archived=false; item aparecia na lista ativa. Editar status pelo formulário também não sincronizava o indicador. Agora o modelo deriva is_archived do status em cada gravação. Migration não destrutiva normaliza registros existentes; não tenta reconstruir valores inconsistentes em rollback. Teste test_archive_status_is_consistent_when_created_and_edited verifica criação arquivada, ausência na lista ativa, reabertura e edição para arquivada. Evidência anterior: archive-before.txt. Migration aplicada localmente com sucesso.

## Cenários adicionais aprovados

- Oportunidade sem fotos: criação, listagem e detalhe retornam capa nula, sem exceção. Suspeita descartada; coverPhoto usa withDefault.
- Token com expires_at vencido: 401 após avançar relógio 721 minutos, com requisição válida antes.
- Token legado com expires_at nulo: 401 após idade máxima configurada. Nenhum token exposto no relatório.

## BUG 20 — Campos opcionais não podem ser apagados na edição

Funcional; Média; corrigido. frontend/src/pages/Oportunidades.tsx::save e lib/opportunityForm.ts. Criar oportunidade fictícia com cidade, preço e observações; editar, apagar esses valores e salvar. Antes: campos vazios eram omitidos de FormData, e o backend mantinha valores antigos. Agora vazios são enviados explicitamente, ConvertEmptyStringsToNull os converte e atualização persiste null. Helper extraído preservando o comportamento anterior para demonstrar falha; frontend/tests/opportunityForm.test.mjs falhou antes (null em vez de string vazia) e passa após correção. Teste multipart PHP test_multipart_update_can_clear_optional_fields comprova valores nulos na resposta e no banco; preservação de zero/texto acentuado testada. Evidência opportunity-form-before.txt.

Melhoria associada: mensagem de erro de salvamento também aparece dentro do modal, para não ficar coberta pela sobreposição. Verificação visual desse erro ainda pendente.

## BUG 21 — Falha de recálculo deixa custo parcialmente salvo

Lógica/integridade; Alta; corrigido nos endpoints de custos e resultados. InvestmentAnalysisController::storeCost/updateCost/destroyCost/result/updateResult. Ambiente isolado SQLite em memória, observador de InvestmentAnalysis lança exceção simulada ao persistir recálculo. Criar custo de 500: antes API retornava 500, mas linha investment_costs permanecia no banco. Agora criação e recálculo são uma única transação, com lockForUpdate na análise para bancos que suportam. Atualização e exclusão também são revertidas se recálculo falhar; criação de resultado é revertida se atualização do progresso falhar. FinancialAtomicityTest verifica snapshots de análise/custo e ausência de resultado parcial após 500. Remove observador ao final e valida criação normal entre cenários. Evidência anterior atomicity-before.txt. Bloqueios adicionados não equivalem a teste de concorrência real; endpoints de financiamento e demais mutações ainda estão em revisão transacional.

## BUG 22 — Financiamento permanece após falha da análise

Lógica/integridade; Alta; corrigido. InvestmentAnalysisController::financing/storeFinancingSimulation/destroyFinancingSimulation/update. No ambiente isolado, simular falha em eloquent.updating de InvestmentAnalysis. POST de simulação de financiamento com parcela fictícia de 100 devolvia 500 e mantinha financing_details criado. Agora a gravação e recálculo são transacionais; testes também verificam PUT de financiamento e exclusão da última simulação com rollback. Snapshot de análise permanece idêntico e linhas criadas não ficam após erro. Teste test_financing_mutations_roll_back_if_analysis_update_fails; evidência financing-atomicity-before.txt.

Atualizações financeiras obtêm lockForUpdate na análise; criação de análise bloqueia oportunidade antes de decidir is_primary. Seleção de simulação já transacional agora também bloqueia análise e recarrega recurso filho. Esses controles serializam operações no mesmo recurso em bancos com bloqueio de linha; ainda não foram submetidos a concorrência real e não representam garantia de isolamento testado no SQLite local.

## Concorrência real — 16/10/2026

MariaDB 12.3 isolado foi inicializado com senha aleatória, porta 13308 e bind 127.0.0.1; banco fictício houseflipper_audit separado do SQLite da aplicação. Dois processos PHP distintos liberados por barreira executaram cada fase. Seis chamadas HTTP internas ao kernel real:

- Duas criações de análise: HTTP 201/201, duas análises e exatamente uma is_primary.
- Duas seleções de financiamento: HTTP 200/200, exatamente uma is_selected.
- Dois custos (100 e 200): HTTP 201/201, duas linhas, soma e detailed_costs_total iguais a 300.

Verificação final passed=true. Evidência concurrency-mariadb-results.txt. Servidor de teste encerrado após coleta; aplicação original permanece em SQLite, portas 8000/5173. Harness em concurrency-harness é cópia de referência dos scripts, exige adaptar caminhos de localização (originais em work/concurrency), inicialização de banco vazio e arquivo de credencial privado; não executá-lo contra dados reais. Nenhuma senha/token foi copiada para meta ou outputs.

Limite: um par de requisições por cenário e uma execução; não é teste de carga, exaustão, garantia formal contra todas as corridas nem verificação de importação simultânea/vendas/etapas.

## BUG 23 — Data impossível é deslocada para outro mês

Lógica/integridade; Média; corrigido. ItbiTransactionNormalizerService::date. Normalizar linha fictícia com data_transacao=31/02/2026 e valor=100000. Antes: transaction_date=2026-03-03. Agora: null; dado bruto continua preservado para auditoria. A validação faz round-trip dos formatos documentados; ano/mês inicia no primeiro dia, sem depender do dia da execução. Serial Excel tem limite superior para impedir datas fora do intervalo suportado. Teste test_impossible_dates_are_not_silently_shifted_into_another_month cobre data impossível, data válida, ano bissexto, mês sem dia e 29/02 em ano não bissexto. Evidência import-date-before.txt.

## BUG 24 — Cabeçalho mostra identidade fixa de outra pessoa

Interface/funcional; Média; corrigido. frontend/src/components/Topbar.tsx. Entrar com conta fictícia Admin Demo e abrir página privada. Antes cabeçalho mostrava Matheus Silva e saudação Olá, Matheus. Agora consulta /api/auth/me e mostra nome/saudação/iniciais da conta autenticada. Solicitação cancelada no unmount; falha de perfil mostra estado indisponível, e 401 mantém tratamento central de login. Build aprovado e navegador confirmou Olá, Admin / Admin Demo / AD na tela Projetos carregada. Evidência qa-profile-after.jpg nos outputs. Números de demonstração do dashboard/sidebar e demais páginas permanecem no BUG 14; correção de identidade não resolve esses dados fixos.

## Navegação renderizada — cobertura ampliada

Visitas no navegador local: Projetos (carregamento concluído e estado vazio), Imóveis, Obras, Despesas, Financeiro, Tarefas, Fornecedores, Documentos, Relatórios, Calendário, Mensagens, Configurações, Leads, Importar ITBI e IA Inteligente. Snapshots em ui-smoke.json. Na rodada inicial, Financeiro/Tarefas eram PageStub e os demais módulos continham dados fixos; as correções posteriores estão registradas nos BUGs 55–64. Isso comprova renderização/navegação, não CRUD completo nem todos os botões. Formulários e dados dinâmicos exigem testes de fluxo adicionais. Não foi disparada pesquisa contra portais externos naquela rodada.

## BUG 25 — Conta sem empresa causa erro no perfil e gravação empresarial

Funcional/validação; Média; corrigido. AuthController::me e FormRequests de propriedade/projeto/despesa/venda. Conta fictícia tenant_id=null, GET /api/auth/me: antes 500 ao ler tenant->id nulo. Agora 200 com tenant=null. POST /api/propriedades com apelido válido agora 403, sem tentar gravar tenant_id ausente no banco. Teste test_tenantless_profile_has_predictable_response_and_cannot_create_tenant_resource confirma perfil, bloqueio e zero registros. Evidência tenantless-expensive-before.txt. Recursos privados por usuário (oportunidades/análises) mantêm seu modelo de autorização existente.

## Proteção adicional de operações custosas

Busca de imóveis, análise de mercado e entorno compartilham orçamento de 6 requisições/min por usuário e 12/min por IP, além do limite geral. Upload ITBI exige permissão e limita a 2/min por operador. Teste test_expensive_routes_have_separate_budget_without_contacting_providers usa payload inválido para não chamar terceiros; a sétima tentativa recebe 429 e consulta comum de oportunidades continua 200. Antes o orçamento específico não existia. Trata-se de hardening; não foi executado ataque de carga para comprovar exaustão.

## BUG 26 — Validação nullable diverge de colunas obrigatórias

Funcional/validação; Média; corrigido nos campos identificados. PropriedadeRequest, ProjetoRequest, DespesaRequest, VendaRequest, StoreOpportunityRequest e InvestmentResultRequest. Conta local fictícia com tenant; POST propriedade com apelido válido e status=null. Antes: validação aprovava, banco lançava NOT NULL e API retornava 500. Agora 422. NullValidationTest amplia a nove payloads de status/pais/orçamento/comissão em propriedades, projetos, etapas, despesas, vendas e oportunidades. Campos opcionais continuam podendo ser omitidos para usar defaults; nulo explícito em coluna obrigatória é rejeitado. sale_costs vazio/nulo no resultado é normalizado a zero para preservar fluxo de formulário existente, com teste positivo próprio. Campos realmente nullable continuam aceitando limpeza (OpportunityTest multipart). Evidência null-validation-before.txt.

## BUG 27 — Análise de oportunidade excluída gera erro e mantém caminhos de mutação

Lógica/autorização de ciclo de vida; Média; corrigido. InvestmentAnalysisPolicy::view/update. Conta fictícia, oportunidade com análise/custo/financiamento; excluir oportunidade; consultar análise por ID antigo. Antes: 500 em OpportunityScoreService ao ler asking_price do vínculo nulo. A policy verificava apenas user_id da análise. Agora exige oportunidade não excluída e do mesmo usuário; consulta e mutações retornam 403 antes dos cálculos/gravações. Teste test_deleted_opportunity_blocks_its_analysis_and_financial_children cobre consulta, atualização da análise, atualização/exclusão de custo e seleção de financiamento, preservando custo existente. Dados financeiros não foram apagados em cascata; permanecem preservados sob o soft delete da origem. Evidência deleted-opportunity-before.txt.

## BUG 28 — Transações ITBI distintas sobrescritas por colisão de identidade

Lógica/integridade; Alta; corrigido para novas importações e chaves existentes disponíveis. ItbiTransactionNormalizerService::sourceReference e migration 2026_10_16_000002. CSV local fictício sem SQL cadastral: duas linhas com mesma data/valor, Rua A 10 e Rua B 20. Antes: apenas uma transação após importar (segunda sobrescrevia primeira). Agora chave inclui endereço normalizado, complemento e matrícula quando falta identificador cadastral; se faltam também endereço/matrícula, incorpora linha bruta. Teste test_different_properties_without_cadastral_id_are_not_overwritten confirma duas linhas e reimportação idempotente, incluindo execução da migration entre importações.

Migration reidentifica registros sem cadastro sem apagar linhas e sem alterar IDs; aplicada localmente. Não recupera registros já sobrescritos historicamente: reimportar arquivos brutos originais é necessário para reconstruí-los. Histórico bruto/lotes devem ser preservados; colisão inesperada de constraint faz a migration falhar em vez de apagar dados. Rollback não restaura chave defeituosa. Evidência itbi-collision-before.txt.

## BUG 14 — atualização: dashboard e resumo lateral corrigidos

DashboardController fornece /api/dashboard autenticado com escopo por tenant; painel e resumo lateral deixam de exibir quantias/atividades fixas. Indicadores usam imóveis, projetos, despesas não canceladas e última venda concluída por imóvel. Lucro realizado deduz aquisição, despesas e deduções da venda; ROI é consolidado sobre custos dos imóveis vendidos. Gráfico mensal é saldo por competência (datas de aquisição/despesa/fechamento), não lucro realizado por mês nem fluxo bancário. Projetos ativos, despesas recentes e composição vêm do banco; estados vazios e erro/retry explícitos. Não há seletor de período sem implementação; período atual de seis meses é identificado. ROI projetado da tabela usa alvo cadastrado menos aquisição/despesas e fica indisponível se não há dados suficientes.

DashboardTest comprova empresa A: aquisição 100000, despesa válida 10000, cancelada 99000 excluída, venda 150000 menos comissão 5000 => lucro 35000; imóvel de outra empresa 999999 não entra. Empresa B não vê vendas/projetos de A. Navegador confirmou conta demo vazia com zero registros reais e sem números anteriores; qa-dashboard-real-data.jpg. Build aprovado.

BUG 14 continuou parcialmente pendente nesta etapa histórica. Imóveis/Obras/Despesas/Fornecedores/Documentos/Relatórios/Calendário/Mensagens/Financeiro receberam tratamento nos BUGs 55–64 e Tarefas no BUG 65; o estado consolidado está na atualização posterior do próprio BUG 14.

## BUG 29 — Retornos calculados excedem precisão persistível

Lógica/validação; Média; corrigido. InvestmentAnalysisCalculatorService::calculateForStorage/validateForStorage e chamadas de persistência em InvestmentAnalysisController. Cenário fictício: compra=100000, entrada=0.01, venda esperada=999999999999, prazo=1 mês. Valores isolados passavam pelas regras, mas ROI/anualização ultrapassavam DECIMAL(12,4). Antes o SQLite devolvia 201 e armazenava valores incompatíveis com o schema de produção. Agora a validação do resultado calculado limita valores monetários a DECIMAL(15,2), percentuais a DECIMAL(12,4), rejeita não finitos e devolve 422 antes de persistir. Teste test_calculated_returns_outside_database_precision_are_rejected_atomically cobre criação rejeitada e resultado rejeitado sem linha/progresso parcial. Evidência financial-bounds-before.txt.

Cálculos de cenários somente exibidos continuam usando calculate; a validação específica aplica-se ao contrato de armazenamento. Não se alegou reprodução de erro SQL no MariaDB para esse payload; a incompatibilidade foi comprovada por schema e aceitação local anterior. Mensagem orienta revisar capital, valores e prazo, sem truncar silenciosamente resultados.

## BUG 30 — Etapas de projeto excluído e estados inválidos

Lógica/ciclo de vida; Média; corrigido. ProjetoController::storeEtapa/updateEtapa. Conta fictícia com projeto e etapa; excluir projeto, enviar POST /api/projetos/ID/etapas e PATCH /api/projetos-etapas/ID. Antes POST criava etapa (201); agora ambos 404. Criar/editar etapa com status=unrecognized antes era aceito; agora 422. Progresso exige inteiro 0–100 para corresponder à coluna; valor fracionário 99.9 retorna 422, 50 com status em_andamento funciona. Regressões test_deleted_project_rejects_stage_creation_and_mutation e test_stage_status_and_progress_match_supported_states_and_integer_storage. Evidência stage-before.txt.

Recálculo de orçamento ignora etapas logicamente excluídas. Criação/edição e atualização do orçamento do projeto são uma transação com bloqueio do projeto. Teste test_stage_budgets_exclude_deleted_stages_and_roll_back_with_project_failure verifica soma 20/40 sem etapa excluída de 100 e rollback de criação/edição após falha simulada no projeto. Limite da soma é validado antes da persistência. Concorrência real desses endpoints ainda não exercitada.

## BUG 31 — E-mail depende de capitalização no SQLite

Funcional/identidade; Média; corrigido nos fluxos de cadastro/login. LoginRequest/RegisterRequest normalizam e-mail para minúsculas; AuthService compara parametrizadamente com LOWER(email), e cadastro verifica registros legados equivalentes. Conta fictícia Audit.Case@Example.test, login audit.case@example.test: antes 401; agora 200. Novo cadastro AUDIT.CASE@example.test retorna 422 e mantém uma conta. Teste test_email_case_does_not_create_duplicate_accounts_or_block_login; evidência email-case-before.txt.

Se já existem duas contas legadas equivalentes por capitalização, o login falha fechado (401 genérico), em vez de escolher arbitrariamente a primeira. Teste test_ambiguous_legacy_email_cannot_select_an_arbitrary_account. Não foram mescladas contas nem alterados identificadores existentes: reconciliação dessas duplicações exige revisão da propriedade dos dados, se ocorrerem. Novo cadastro normalizado continua protegido pelo índice único para requisições concorrentes da aplicação. Testes executados em SQLite; não se alegou reprodução da duplicação em collation MariaDB case-insensitive.

## BUG 32 — Fotos órfãs após rollback da oportunidade

Integridade/armazenamento; Média; corrigido. OpportunityService::withPhotoTransaction/storePhotos/create/update. Ambiente local com Storage::fake e falha induzida ao gravar histórico. Criar oportunidade com foto, provocar exceção após salvar arquivo. Antes: HTTP 500, banco sem oportunidade/foto, mas arquivo continuava no disco. Agora rastreia caminhos recém-gerados e os remove quando transação falha. Na edição, fotos anteriores são preservadas; apenas novos arquivos são limpos. Falha de limpeza gera log com contagem, sem mascarar exceção original nem divulgar caminhos/credenciais.

Testes test_failed_opportunity_creation_removes_new_photo_files e test_failed_opportunity_update_preserves_existing_photos_and_removes_only_new_files confirmam banco revertido, ausência de arquivos novos e foto original intacta. Evidência photo-rollback-before.txt. Não remove automaticamente órfãos históricos nem altera a política atual de fotos públicas, registrada como risco residual separado.

## BUG 33 — Limite de fotos contornável e capa fora da lista aceita

Validação/consumo; Média; corrigido. OpportunityService::validatePhotoLimit/update e StoreOpportunityRequest::after. Criar oportunidade fictícia com 20 fotos salvas; enviar mais uma em edição. Antes: 200 e 21 fotos; agora 422 sem novo arquivo. Contagem inclui fotos existentes, arquivos enviados e capa externa nova (URL já existente não é contada duas vezes). Validação acontece dentro da transação, com bloqueio da oportunidade na edição. Teste test_photo_limit_applies_to_existing_and_new_photos_combined também remove um registro fictício de fixture e comprova que adicionar a vigésima foto continua permitido.

Enviar uma foto com cover_photo=99 antes criava oportunidade sem capa válida; agora 422 e nenhum registro/arquivo. Teste test_cover_index_must_reference_one_of_the_uploaded_photos. Evidência photo-limit-before.txt. Não elimina automaticamente galerias históricas acima do limite nem comprova quota total de armazenamento por conta; esses são riscos distintos.

## BUG 34 — Identificador não numérico causa TypeError no controller

Funcional/validação; Média; corrigido. ValidateResourceIdentifiers no grupo API e controllers legados com argumentos int. Autenticar conta fictícia, GET /api/propriedades/not-a-number: antes 500 por TypeError. Agora IDs escalares de propriedade/projeto/despesa/venda/etapa precisam ser inteiros positivos representáveis pelo runtime; inválidos retornam 404 antes de chamar controller. RouteIdentifierTest testa texto, negativo e inteiro muito grande em leitura/exclusão dos quatro recursos e rotas ROI/resumo/etapas: 36 asserções aprovadas. Evidência route-identifiers-before.txt; resultados após em route-identifiers-after.txt. Model bindings permanecem responsáveis por IDs dos recursos tipados como modelos.

ApiHeadersTest adicional confirma que erros 401/422 também recebem no-store e nosniff; não apenas respostas de sucesso.

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

## BUG 48 — Despesas do projeto falham na paginação e formulário fecha após erro

Categoria: Funcional/Interface. Severidade: Média. Status: correção implementada; contrato API e helpers aprovados, interação visual completa pendente. Arquivos frontend/src/pages/Projetos.tsx, frontend/src/lib/paginatedCollection.ts e app/Services/DespesaService.php. Rota /projetos e GET/POST /api/despesas.

Causa comprovada no código: a UI enviava per_page=200 enquanto ValidatePagination limita a 100; catch apagava a lista sem feedback. API retorna 422 para 200, confirmado em teste de integração. O modal aguardava um callback que capturava o erro e retornava void, fechando também após falha. Recarregar todos os projetos desmontava o modal durante o salvamento. Requisições de projetos anteriores também podiam sobrescrever a lista atual. Observações não estavam vinculadas ao payload.

Reprodução manual do comportamento anterior: usar uma conta fictícia ativa, com propriedade, projeto, etapas e categoria reforma; abrir /projetos, selecionar o projeto e abrir despesas. No painel de rede, GET despesas com per_page=200 retorna 422; a interface mostra lista vazia. Abrir Nova despesa, preencher descrição, valor, etapa e tipo; simular falha de conexão ou resposta 422 no POST. O modal fecha apesar da falha. Com mais de 100 despesas de teste no mesmo dia, verificar todas as páginas e ausência de duplicações. Não alterar dados reais.

Correção: coleção paginada usa 100 por requisição, consome meta.last_page e só publica resultado completo. AbortController cancela seleção anterior e desmontagem; respostas atrasadas são descartadas, inclusive erros. API ordena por data e ID para desempatar. Erros e carregamento são explícitos; totais indisponíveis exibem travessão. Callback retorna sucesso/falha; só fecha após gravação confirmada, sem desmontar no refresh. Falha de atualização posterior informa que a despesa foi salva. Observações são enviadas; seleção de outro projeto limpa o rascunho vinculado à etapa anterior. Modal usa dialog nativo com título, fechamento por Escape e controles de saída bloqueados durante gravação.

Evidência automatizada: ExpensePaginationTest cria 205 despesas fictícias em RefreshDatabase, confirma 422 para limite anterior, páginas 100/100/5, ordem estável sem perdas e isolamento entre tenants (1 teste/13 asserções). paginatedCollection.test.mjs cobre 205 itens, resposta atrasada, falha na página 2, cancelamento na desmontagem e metadados inválidos (5 testes). Esses testes não executam a interação do modal.

Verificação manual pendente: repetir falha no POST e confirmar modal aberto, valores mantidos e erro visível dentro dele; salvar com conexão restaurada, confirmar apenas um registro, fechamento e lista atualizada; testar foco, Escape e viewport estreita. A ferramenta de navegador rejeitou a aquisição da aba por política de URLs nesta rodada; nenhuma alternativa para contornar esse bloqueio foi usada. Não há alegação de teste visual desses estados.

Atualização posterior: BUGs 51/52 corrigiram o botão Filtros, as falhas de resumo e as ações de edição/pagamento. O campo Fornecedor e anexos foram retirados porque o backend não os modela; uma integração futura exige contrato próprio. CRUD visual completo e cenários offline continuam pendentes.

## BUG 49 — Tela de despesas trata cancelamento como valor a pagar

Categoria: Lógica/Interface. Severidade: Média. Status: corrigido no cálculo e status testados; renderização pendente. Arquivos Projetos.tsx e expenseSummary.ts. Rota /projetos, área Despesas da obra.

Causa: normalização desconhecia cancelado e caía em pendente; soma total incluía todas as despesas. Backend já excluía canceladas no BUG 16, mas a interface mantinha indicador divergente.

Reprodução anterior: em projeto fictício, cadastrar despesas de 10,10 paga, 20,20 pendente, 30,30 atrasada e 999,99 cancelada via API autorizada. Abrir despesas do projeto: a cancelada recebia badge A pagar, elevava pendências e total. Esperado: total de 60,60, pago 10,10, pendente 20,20 e vencido 30,30; registro cancelado continua consultável com status Cancelado.

Correção: status explícito cancelado e exclusão do total/pagamento; soma em centavos reduz erro de ponto flutuante. expenseSummary.test.mjs confirma valores acima e aliases históricos/0,10+0,20 (2 testes). Lista mantém canceladas, sem apagar registros. Repetir cenário na UI para validar badge e filtros; essa etapa visual permanece pendente pela limitação de navegador registrada no BUG 48.

## BUG 50 — Conversão de análise em projeto deixa imóvel órfão e permite duplicação

Categoria: Lógica/Funcional. Severidade: Média. Status: corrigido no protocolo novo e cenários API/concorrência testados; interação visual pendente. Arquivos CentralFinanceira.tsx, AnalysisProjectService.php, InvestmentAnalysisController.php, InvestmentAnalysis.php, routes/api.php e migração 2026_10_16_000004_link_analysis_to_project.php. Rota da tela /analises-financeiras/:id; nova operação POST /api/investment-analyses/{investmentAnalysis}/project.

Causa: o frontend fazia POST propriedades e POST projetos independentes; rollback do segundo não podia desfazer o primeiro. Não havia vínculo persistido nem exclusão mútua entre tentativas. A identificação na UI procurava substring na descrição entre apenas os primeiros 100 projetos; análise #1 também podia combinar com #10.

Reprodução anterior, apenas com fixtures: conta ativa com tenant e análise própria; executar o antigo protocolo POST /api/propriedades e depois POST /api/projetos usando a propriedade criada. Simular falha de persistência de etapa no segundo pedido. Esperado: conversão completa ou nenhuma alteração. Observado: 500 no projeto, imóvel anterior permanece. Repetir o antigo par de pedidos cria imóveis/projetos distintos; não havia chave de idempotência nem vínculo no servidor. O teste test_legacy_two_request_protocol_leaves_property_after_second_request_fails reproduz efetivamente o primeiro caso em banco descartável. A API genérica de propriedades continua permitindo cadastro independente legítimo; a UI de conversão deixou de usar esse protocolo.

Correção: endpoint autoriza update na policy da análise, exige tenant e usa transação com lock da análise e oportunidade. Cria propriedade/endereço/projeto/etapas/orçamentos e salva projeto_id na mesma transação. Repetição retorna o projeto persistido, HTTP 200; criação retorna 201. Dados financeiros e tenant vêm dos registros/sessão, sem aceitar substituição pelo payload. Vínculo não pode ser alterado pela API comum de edição da análise. Orçamento validado antes das gravações; distribuição em centavos preserva o total. Nomes longos são abreviados aos limites dos destinos, mantendo título completo na oportunidade. Campo projeto_id nullable e único, com FK, adicionado sem backfill destrutivo. Interface usa o novo endpoint e o vínculo; fallback legado compara o ID inteiro, evitando #1/#10.

Compatibilidade: projetos legados não foram movidos, apagados ou automaticamente associados a partir de texto editável. Se a descrição histórica identifica uma conversão já existente, endpoint retorna 409 orientando revisão, em vez de duplicar ou adotar vínculo ambíguo. Projeto ou imóvel vinculado excluído logicamente também retorna 409; não recria silenciosamente. Reconciliação de legados e fluxo de restauração continuam pendentes.

Evidência: AnalysisProjectTest, 10 testes/80 asserções. Cobre idempotência, dados persistidos, centavos, payload ignorado para valores/vínculo, rollback em etapa e atualização final, protocolo anterior, usuários alheios no mesmo/outro tenant, dono sem tenant, exclusão lógica, legado sem adoção automática, IDs #1/#10, nomes longos, overflow, anonimato, confirmação, suspensão, IDs inválidos e método HTTP. Na rodada da correção, a suíte tinha 145 testes/814 asserções; o consolidado atual é 148/841. Build TS/Vite aprovado.

Concorrência real: dois workers PHP com barreira, MariaDB isolado em 127.0.0.1:13308. Intervalos de execução sobrepostos; atraso fictício de 250 ms durante criação força transação aberta. Resultados 200/201 com mesmo projeto; um imóvel, um projeto, sete etapas; orçamento 300 e soma de etapas 300. Ver conversion-results.json e conversion-invariants.json. Servidor isolado encerrado após o teste. Isso comprova esse cenário, não todas as cargas/bancos possíveis.

Verificação manual: abrir análise fictícia própria, converter e conferir projeto; repetir pedido e confirmar mesmo ID sem contagens extras. Simular falha no servidor usando o teste isolado de rollback; restaurar e tentar novamente. Testar duas tentativas simultâneas usando harness isolado, sem carga agressiva. UI real e refresh após perda da resposta ainda precisam de execução visual; ferramenta de navegador permaneceu indisponível pela política de acesso já registrada no BUG 48.

Migração local aplicada após VACUUM INTO de backup consistente do SQLite em diretório privado work; nenhum dado foi copiado aos relatórios. Aplicação continua em SQLite/portas 8000 e 5173. Não houve mudança em produção.

## Atualização do BUG 14 — ações globais e projeto manual

Categoria: Funcional/Interface. Severidade: Média. Status: correção parcial ampliada e verificada. Arquivos Topbar.tsx, Ajuda.tsx, App.tsx, Projetos.tsx, projectForm.ts e Oportunidades.tsx. Rotas /projetos?novo=1, /ajuda e /leads?novo=1.

Causa confirmada: “Novo Projeto”, sino e ajuda eram botões sem handler. O sino ainda exibia o número fixo 3 sem origem de dados. A página Projetos afirmava aceitar projetos manuais, mas não apresentava cadastro. O primeiro estado vazio implementado apontava para /imoveis, que continua sendo uma tela demonstrativa; a revisão substituiu esse destino pelo formulário real de oportunidades.

Reprodução anterior: entrar em uma conta fictícia e clicar os três controles do cabeçalho. Nenhuma navegação, painel ou função era executada; o número 3 permanecia mesmo em conta sem despesas. Em Projetos, não havia caminho de criação manual. Esperado: ações identificadas, dados reais e feedback de estado vazio. Resultado anterior comprovado por inspeção do componente sem handlers e renderização da conta vazia.

Correção: Novo Projeto abre dialog nativo de projeto manual e carrega todos os imóveis da conta em páginas de 100. O formulário envia imóvel, nome, orçamento, status, datas e descrição ao endpoint protegido existente; erros ficam no dialog e o botão só fecha após sucesso. Sem imóvel, criação fica desabilitada e o caminho leva a /leads?novo=1, abrindo o formulário real de Nova oportunidade; cancelar remove o parâmetro. O sino deixou de alegar não lidos: abre “Atividades recentes” com os últimos lançamentos reais de /api/dashboard, estados carregando/erro/vazio, fechamento externo/Escape e link para Despesas. Ajuda abre /ajuda, com atalhos para fluxos reais e aviso honesto de que a IA usa regras/comparáveis. Controles receberam nomes acessíveis; dialogs de projeto, despesa e oportunidade usam elemento dialog, título associado e Escape.

Validação visual real em viewport estreito: botão global abriu http://127.0.0.1:5173/projetos?novo=1; conta demo sem imóvel mostrou estado vazio, “Criar projeto” desabilitado e Escape fechou/removendo a query. “Cadastrar oportunidade” abriu /leads?novo=1 com dialog “Nova oportunidade”; cancelar fechou e voltou a /leads. Sino exibiu “Nenhuma atividade registrada” a partir da resposta real, sem badge fictício. Ajuda renderizou tema escuro sem sobreposição e abriu sua rota. Capturas ajuda-mobile.png e novo-projeto-mobile.png. Um erro antigo de HMR apareceu no histórico enquanto Topbar era substituído; reload posterior renderizou a página e não registrou novo erro. Isso não comprova todos os navegadores/viewports.

Testes: ManualProjectTest valida criação completa, quatro etapas padrão, campos/datas/orçamento, relação tenant e rejeição de término anterior/invasão por propriedade alheia (2 testes/17 asserções). projectForm.test.mjs cobre trim, conversão de ID, campos opcionais e centavos. Na rodada da correção, a suíte tinha 147 testes PHP/831 asserções e 14 testes Node; o consolidado atual é 148/841 e 16 Node. Build TS/Vite aprovado. Não foi criado projeto na conta demo durante o teste visual.

Pendências do BUG 14 após esta etapa incremental: os módulos citados na versão original foram tratados nos BUGs 55–64 e Tarefas deixou de ser `PageStub` no BUG 65. Permanecem a revisão visual completa de todas as ações com fixtures e a execução manual de edição/exclusão de projeto e erro de rede do diálogo.

## BUG 51 — Falha de resumo financeiro era apresentada como zero ou total parcial

Categoria: Lógica/Interface. Severidade: Média. Status: corrigido nos cenários automatizados; injeção visual de falha pendente. Arquivos Projetos.tsx, projectSummary.ts e projectSummary.test.mjs.

Causa comprovada no código: cada erro em GET /projetos/{id}/resumo era convertido silenciosamente em null. Cartões formatavam campos ausentes como R$ 0,00 e 0,0%; o detalhe fazia o mesmo e afirmava não haver etapas. Os KPIs somavam somente respostas disponíveis sem avisar que o valor era parcial. A consulta de projetos também usava apenas a primeira página de 100. O rótulo “Projetos ativos” contava todos os estados retornados.

Correção: a coleção de projetos percorre meta.last_page. IDs sem resumo são rastreados; aviso informa a quantidade e oferece nova tentativa. Valores dependentes exibem “Indisponível”, etapas não são apresentadas como vazias e totais financeiros só aparecem quando todos os resumos existem. O rótulo agora é “Projetos cadastrados”. A soma monetária usa centavos. Dois testes Node cobrem conjunto completo e resumo ausente; build aprovado. Falha induzida no navegador não foi executada nesta rodada.

## BUG 52 — Controles de despesa alegavam ações ou dados inexistentes

Categoria: Funcional/Interface. Severidade: Média. Status: corrigido no contrato implementado; validação visual completa pendente. Arquivos Projetos.tsx e ExpenseUpdateWorkflowTest.php.

Causa comprovada no código: “Filtros”, “Editar”, “Marcar como pago” e “Adicionar anexo” não possuíam operação. O formulário aceitava Fornecedor sem estado nem envio. O detalhe inventava fornecedor/forma como “Não informado”, chamava a data da despesa de vencimento e exibia nota_fiscal.pdf inexistente. Esses elementos induziam o usuário a acreditar que dados seriam persistidos.

Correção: Filtros controla a visibilidade do painel com aria-expanded/controls. Editar abre o dialog com os campos reais; salvar usa PUT /despesas/{id}. Marcar como pago envia os campos obrigatórios, status pago e data do pagamento. Após sucesso, resumo e lista são recarregados; erros permanecem visíveis. Forma de pagamento e observação persistidas são exibidas. Campos/controles sem suporte de backend para fornecedor e anexos foram retirados, assim como o vencimento derivado incorretamente. A ação da linha agora é “Ver detalhes”.

Evidência: ExpenseUpdateWorkflowTest persiste descrição, centavos, PIX, observação, status/data de pagamento e confirma recálculo de custo_real na etapa e projeto (1 teste/10 asserções). Suíte completa 148/841, Node 16 e build aprovados. A conta local ativa não possui projeto/despesa; o smoke confirmou /projetos e console sem erros, mas não comprova foco, Escape, falha offline ou os cliques com registro existente.

## BUG 53 — Busca da IA retorna 502 no servidor PHP local do Windows

Categoria: Funcional/Compatibilidade. Severidade: Média. Status: corrigido e validado no ambiente local. Arquivos PropertyPortalScraper.php, ExecutableLocator.php, AIPropertySearchController.php, IAInteligente.tsx, services.php e .env.example. Rota GET /api/ai-property-search e tela /ia-inteligente.

Causa comprovada: pesquisar São Paulo/SP, bairro Perdizes e apartamento devolvia 502 “Nenhuma fonte de anúncios respondeu agora”. A execução direta do scraper pelo terminal retornava anúncios, mas o mesmo Node iniciado no contexto HTTP encerrava com código 134 e asserção ncrypto::CSPRNG. O Symfony Process filtra o ambiente herdado em SAPI web; no servidor embutido, variáveis essenciais do Windows, inclusive SystemRoot, não chegavam ao filho. Sem navegador headless, o fallback HTTP recebia anti-bot/captcha de quase todos os portais e terminava sem fonte. O serviço também descartava o diagnóstico do headless ao entrar no fallback, dificultando a causa raiz.

Reprodução anterior: entrar com conta fictícia ativa e confirmada; abrir /ia-inteligente; selecionar SP, São Paulo, Perdizes e Apartamento; clicar Buscar. A requisição `api/ai-property-search?city=S%C3%A3o+Paulo&neighborhood=Perdizes&state=SP&property_type=apartment&limit=250` retornava 502. No console aparecia Failed to load resource; a tela mostrava zero anúncios. No log local, Node encerrava com exit 134/CSPRNG. Não é necessário atacar portais ou alterar dados para reproduzir.

Correção: ExecutableLocator resolve caminhos explícitos de Node e Chrome/Edge sem depender exclusivamente do PATH antigo do servidor. childEnvironment repassa somente variáveis de runtime em allowlist (SystemRoot, PATH, temporários e diretórios do usuário) e overrides internos; HTTP_AUTHORIZATION, REQUEST_URI e outros dados da requisição ficam excluídos. O scraper preserva diagnóstico do headless/fallback e registra somente tipo/exit/stderr limitado no log local. O endpoint devolve diagnóstico seguro em falha e contagens das etapas coletado → tipo → local → preço; o frontend preserva detalhes no erro.

Verificação: repetir a busca após reiniciar apenas o backend. Observado: OLX coletou 36 itens; 36 passaram por tipo, local e preço; a tela exibiu 36 anúncios ranqueados e 20 botões “Salvar lead” na primeira página, sem 502. Screenshot `outputs/auditoria/ia-perdizes-corrigida.png`. ExecutableLocatorTest cobre preferência configurada, candidatos, ausência, allowlist e exclusão de dados HTTP (4 testes/11 asserções). AIPropertySearchTest cobre retorno normal com diagnóstico do pipeline e 502 neutro com diagnóstico, sem trace interno (2/13). Suíte completa 154/866; Node 16; build aprovado.

Limites: portais externos podem alterar HTML, impor login/captcha ou indisponibilidade. A contagem 36 comprova a consulta executada em 16/10/2026, não disponibilidade futura nem completude do mercado. Sandbox/egress/proxy e capacidade em produção continuam pendentes. A busca real foi autorizada pelo clique do usuário e não salvou leads nem modificou dados.

## BUG 54 — API anônima sem cabeçalho JSON retornava 500

Categoria: Segurança/Contrato HTTP. Severidade: Média. Status: corrigido e validado localmente. Arquivos bootstrap/app.php e SecurityRegressionTest.php.

Causa: o middleware Authenticate só suprimia redirecionamento quando `expectsJson()` era verdadeiro. Um cliente genérico sem sessão acionava o callback padrão do Laravel, que tentava gerar a rota nomeada `login`; a SPA não registra essa rota no backend e a exceção RouteNotFoundException resultava em 500.

Correção e evidência: o callback de convidados retorna `null` para `/api/*` e `/login` para páginas web. O teste existente de rotas públicas/protegidas agora executa GET normal e GET JSON em `/api/auth/me`, ambos com 401. O grupo focal passou com 29 testes/151 asserções; a suíte completa passou com 154 testes/866 asserções. Uma chamada HTTP real pelo servidor ativo confirmou 401 sem corpo sensível.

## BUG 55 — Página `/despesas` apresentava registros e ações fictícias

Categoria: Funcional/Interface. Severidade: Média. Status: corrigido; interações com registro real permanecem parcialmente pendentes. Arquivos `frontend/src/pages/Despesas.tsx`, `frontend/src/lib/expenseExport.ts` e `frontend/tests/expenseExport.test.mjs`.

Causa e impacto: a página mantinha em memória 12 despesas de maio de 2024 e exibia R$ 152.750, fornecedores, vencimentos, observação, PDF e orçamento inexistentes. Exportar, Nova Despesa, filtros, paginação, anexo, edição e pagamento eram botões sem operação. A conta podia parecer conter obrigações financeiras e documentos que nunca existiram, enquanto gastos persistidos pela API não apareciam.

Reprodução anterior: entrar com uma conta ativa; abrir `/despesas`; observar “Casa Jardim Paulista”, 12 registros e totais mesmo em uma base vazia; pesquisar e trocar abas alterava apenas a coleção fixa; clicar Exportar, Nova Despesa, filtros, paginação ou ações não executava operação; abrir o detalhe mostrava fornecedor, vencimento, observação e anexo fixos.

Correção: a tela busca todas as páginas de `/api/despesas` e `/api/projetos` dentro do limite 100 e consulta categorias reais. Cartões excluem canceladas e somam centavos; filtros combinam projeto, categoria, datas, texto e status. A tabela pagina 25 itens, o detalhe mostra somente campos persistidos e “Marcar como paga” envia os campos obrigatórios por PUT antes de recarregar. “Lançar em projeto” abre o fluxo existente. O CSV exporta somente resultados filtrados e neutraliza fórmulas de planilha.

Verificação: com a conta demo local vazia, `/despesas` mostrou zero registros/totais, exportação desabilitada e categorias reais; tema escuro, viewport de 546 px e console sem erros foram confirmados. “Lançar em projeto” navegou para `/projetos`. Build TypeScript/Vite passou; os 18 testes Node incluem dois cenários de CSV/UTF-8/centavos/fórmulas. `ExpenseUpdateWorkflowTest` já comprova PUT e recálculo no backend. Para completar a validação visual, criar projeto/despesa descartáveis e testar detalhe, pagamento, exportação e falha offline sem usar dados reais.

## BUG 56 — Fornecedores fictícios eram apresentados como contatos da conta

Categoria: Funcional/Privacidade percebida. Severidade: Média. Status: corrigido; CRUD visual com registro descartável ainda parcialmente pendente. Arquivos `Fornecedores.tsx`, `FornecedorController.php`, `FornecedorRequest.php`, `FornecedorResource.php`, `Fornecedor.php`, `FornecedorService.php`, migration 000005, rotas e `FornecedorTest.php`.

Causa e impacto: `/fornecedores` carregava dez empresas, nomes de contato, telefones e e-mails diretamente do bundle. Métricas, filtros, paginação, exportação, cadastro, edição, contato e exclusão não consultavam ou alteravam backend. Dados inventados podiam ser confundidos com relações comerciais reais, e qualquer interação se perdia ao recarregar.

Reprodução anterior: entrar em uma conta ativa com base vazia; abrir `/fornecedores`; observar ConstruMais, Hidráulica Silva e outros contatos; pesquisar alterava apenas o array local; clicar Novo Fornecedor, exportação, filtros, telefone/e-mail ou menus não produzia um CRUD persistente.

Correção: nova tabela tenant-aware armazena nome, categoria, contato, telefone, e-mail, avaliação, observações e status. API autenticada implementa listagem paginada, criação, leitura, atualização e exclusão lógica. FormRequest limita campos e formatos; TenantScope impede IDOR/BOLA. A interface usa a API, apresenta estado vazio, formulário nativo, filtros e detalhes sem dados inventados.

Verificação: FornecedorTest executa CRUD próprio, limpeza de opcionais, filtros/paginação 25+1, validações, mass assignment, usuário sem tenant e leitura/mutação/exclusão por outro tenant (4 testes/36 asserções). Suíte completa 158 testes/908 asserções; build e 18 testes Node aprovados. No navegador, a conta demo mostrou zero, o dialog abriu em tema escuro com foco no nome, envio vazio acionou validação nativa e Escape fechou; nenhum registro foi criado. Para validar manualmente o fluxo visual completo, usar dados descartáveis: criar, recarregar, pesquisar, editar, excluir e confirmar que outra conta não enxerga o item.

## BUG 57 — Documentos fictícios e ações sem persistência

Categoria: Funcional/Segurança. Severidade: Média. Status: corrigido no contrato implementado; fluxo visual com arquivo descartável pendente. Arquivos `frontend/src/pages/Documentos.tsx`, `routes/api.php`, `config/documents.php`, `app/Models/Documento.php`, `app/Http/Requests/DocumentoRequest.php`, `app/Http/Resources/DocumentoResource.php`, `app/Http/Controllers/Api/DocumentoController.php`, `app/Services/DocumentoService.php`, migration `2026_10_16_000006_create_documentos_table.php` e `tests/Feature/DocumentoTest.php`. Rota `/documentos` e `/api/documentos*`.

Causa confirmada: a página exibia 128 documentos, 2,45 GB, 18 pastas e dez nomes/descrições/pessoas fictícios. Busca apenas filtrava a constante local. Novo documento, download, compartilhamento e demais ações não executavam operação. Isso poderia levar o usuário a interpretar conteúdo inventado como documentos da conta; ainda não existia upload real, portanto não foi comprovado vazamento de arquivo anterior.

Como reproduzir antes da correção: entrar em uma conta vazia; abrir `/documentos`; observar os KPIs e arquivos fixos; clicar em Novo documento, Download ou Compartilhar. Nada era persistido nem baixado. Esperado: estado vazio fiel e CRUD funcional, com acesso ao arquivo protegido. Observado: dados cenográficos e controles inertes.

Correção: tabela tenant-aware e armazenamento no disco privado `local`, com nome físico UUID; API autenticada para lista, upload, metadados, download e exclusão lógica/física. Tipo/extensão e limite de 10 MB são validados no servidor; projeto precisa pertencer ao tenant; upload tem limite 10/min por usuário e 20/min por IP. Resource não expõe caminho nem tenant. Download exige sessão, retorna `no-store`/`nosniff`; falha de armazenamento usa mensagem neutra; falha de banco remove o arquivo órfão. A UI carrega todos os documentos/projetos, calcula métricas reais, filtra, envia multipart, edita metadados, baixa com bearer token e exclui após confirmação.

Verificação: `DocumentoTest` aprovou 6 testes/61 asserções: CRUD/download legítimo, isolamento completo entre tenants, projeto alheio, tipo executável, tamanho excedente, campos privilegiados, nome hostil, falha neutra, limpeza de órfão e paginação. Suíte completa: 164 testes/976 asserções. Build TS/Vite aprovado. A migração aditiva 000006 foi aplicada. No navegador, a conta demo mostrou zero documentos; o diálogo abriu em tema escuro com foco no arquivo, apresentou formatos/limite e Escape fechou; não houve erro de console e nenhum arquivo foi enviado.

Como validar manualmente: usar uma conta de teste; enviar PDF descartável abaixo de 10 MB; recarregar; buscar/filtrar; abrir detalhes; baixar e comparar o arquivo; editar metadados; excluir; confirmar 404 no download antigo. Em outra conta, a lista deve permanecer vazia e os IDs da primeira devem responder 404. Repetir com `.php`, arquivo maior que 10 MB e projeto alheio para obter 422. O fluxo visual completo com fixture e confirmação gráfica de exclusão continua pendente para evitar alterar a conta demo.

## BUG 58 — Relatórios apresentavam números e projetos fictícios

Categoria: Lógica/Funcional/Interface. Severidade: Alta. Status: corrigido no resumo implementado. Arquivos `frontend/src/pages/Relatorios.tsx`, `frontend/src/lib/reportExport.ts` e `frontend/tests/reportExport.test.mjs`. Rota `/relatorios`; origem real `/api/dashboard`.

Causa confirmada: a página declarava faturamento de R$ 2.317.450, lucro, ROI, 12 projetos concluídos, séries mensais de 2024, composição de custos e cinco projetos, todos definidos em constantes. Controles de período, filtros, exportação, seletores e relatórios completos não tinham operação; sete abas mostravam apenas “em breve”. Em uma conta vazia, esses valores continuavam sendo apresentados como desempenho do usuário. O impacto é decisão financeira baseada em dados inventados; não foi comprovada alteração de dados nem acesso entre tenants.

Reprodução anterior: entrar em uma conta vazia; abrir `/relatorios`; observar valores milionários e projetos Casa Jardim Paulista/Sobrado Vila Madalena; clicar Exportar relatório/Filtros/período. Nenhuma fonte real ou operação existia. Esperado: zero/estado vazio ou dados persistidos com escopo e descrição temporal clara.

Correção: a página consome o endpoint tenant-aware `/api/dashboard`. KPIs acumulados, série dos últimos seis meses, composição de aquisição/despesas e até cinco projetos em andamento usam somente os registros persistidos. A descrição diferencia valores acumulados da janela do gráfico e informa os estados de venda considerados. Controles e abas cenográficos foram removidos. Atualizar recarrega a API. Exportar resumo gera CSV local com data, totais reais e proteção contra formula injection.

Verificação: conta demo vazia renderizou R$ 0,00/0, gráficos de maio a outubro de 2026, estados vazios de composição e projetos; nenhum projeto de 2024 apareceu. Nova aba após o build não registrou erro/aviso de console. Dois testes Node validam campos, centavos e fórmula; suíte frontend 20/20 e build TS/Vite aprovados. `DashboardTest` já cobre cálculo e isolamento entre tenants no endpoint. A exportação visual não foi clicada para evitar download desnecessário; a geração é exercitada pelo teste puro.

Como validar: criar dados descartáveis por APIs/fluxos normais, abrir Relatórios e comparar com Dashboard; clicar Atualizar após nova despesa/venda; exportar e conferir os valores no CSV. Em outra conta, os totais devem permanecer próprios. Falhas de API devem mostrar erro e tentativa, sem preservar números antigos. Filtros avançados, relatórios por período e detalhamento por módulo não foram implementados e não são alegados.


## BUG 59 — Calendário fixo em maio de 2024 com compromissos inventados

Categoria: Funcional/Lógica/Interface. Severidade: Alta. Status: corrigido no contrato implementado; fluxo visual com fixture pendente. Arquivos `frontend/src/pages/Calendario.tsx`, `routes/api.php`, `app/Models/EventoCalendario.php`, `app/Http/Requests/EventoCalendarioRequest.php`, `app/Http/Resources/EventoCalendarioResource.php`, `app/Http/Controllers/Api/EventoCalendarioController.php`, `app/Services/EventoCalendarioService.php`, migration `2026_10_16_000007_create_eventos_calendario_table.php` e `tests/Feature/EventoCalendarioTest.php`.

Causa confirmada: a grade era construída manualmente para maio/2024 e continha 26 compromissos, endereços, fornecedores e imóveis fictícios. Hoje, anterior/próximo mês, sincronizar e novo evento não executavam operações; as visões semana/dia/agenda apenas mudavam estilo sem alterar a grade. O usuário poderia interpretar compromissos inventados como obrigações reais.

Reprodução anterior: entrar em qualquer conta, abrir `/calendario`, observar maio/2024 e eventos como “Pagamento fornecedor”, “Entrega de piso” e “Vistoria final”; clicar Hoje, setas, Sincronizar e Novo evento. A data e os dados permaneciam fixos e nada era persistido.

Correção: nova tabela e CRUD tenant-aware para eventos, com projeto opcional do mesmo tenant, autor derivado da sessão, categorias/status allowlist, intervalo válido, exclusão lógica e consulta paginada por interseção temporal. A UI gera dinamicamente 42 dias do mês corrente, navega meses, retorna a hoje, busca todas as páginas da janela visível, exibe eventos que atravessam dias, filtra categorias e oferece criação, edição e exclusão. Controles de visão sem implementação foram retirados.

Verificação: `EventoCalendarioTest` aprovou 4 testes/52 asserções cobrindo CRUD, filtros, 101+1 registros, evento atravessando mês, range inválido, projeto alheio, mass assignment, usuário sem tenant e leitura/mutação/exclusão por outro tenant. Suíte completa: 168 testes/1.034 asserções; build aprovado; migration 000007 aplicada. No navegador, a conta demo mostrou outubro/2026 e zero eventos, diálogo com data 16/10/2026 09:00, foco no título e fechamento por Escape, sem erro de console e sem criar registro.

Como validar manualmente: em conta de teste, criar evento com início/fim, trocar mês e retornar; recarregar; filtrar categoria; editar para concluído; excluir. Criar um evento atravessando meia-noite e confirmar presença nos dois dias. Em outra conta, IDs devem responder 404 e a lista deve permanecer vazia. Término anterior e projeto alheio devem retornar 422. Sincronização com Google/Outlook, recorrência, lembretes, fuso configurável e visão semanal não são alegados.

## BUG 60 — Tela de Mensagens inteiramente demonstrativa

Categoria: Funcional/Interface. Severidade: Média. Status: removido por decisão explícita do usuário. Arquivos removidos/alterados: `frontend/src/pages/Mensagens.tsx`, `frontend/src/App.tsx` e `frontend/src/components/Sidebar.tsx`. Rota anterior `/mensagens`.

Causa confirmada: a tela continha dez contatos, sete mensagens, dados pessoais, telefone/e-mail, imóvel, notas e três anexos definidos em constantes. Enviar, nova mensagem, chamada, vídeo, configurações, anexos e demais controles não possuíam persistência ou integração. O menu ainda exibia anteriormente um contador fixo 5.

Reprodução anterior: entrar em qualquer conta e abrir `/mensagens`; os mesmos nomes e diálogos apareciam, inclusive em conta vazia. Digitar e clicar enviar não persistia nem transmitia conteúdo. Esperado após a decisão: o produto não deve expor esse módulo nem seus dados simulados.

Correção/verificação: por solicitação expressa do usuário (“Remova essa tela completamente”), o componente foi excluído, o import e a rota React foram removidos e a entrada do menu foi eliminada. O backend de mensagens internas que estava somente em edição local e ainda não tinha rota/migração aplicada foi integralmente descartado, sem tabela residual. Busca no código ativo não encontrou Mensagens/conversas; build TS/Vite passou. No navegador, o menu contém zero links Mensagens e `/mensagens` exibe a tela 404 própria, sem erro de console.

Como verificar: recarregar qualquer página autenticada e confirmar ausência no menu; acessar diretamente `/mensagens` e confirmar “Erro 404 — Página não encontrada”. Nenhum dado real foi removido, pois o módulo anterior não tinha persistência e a migração experimental nunca foi aplicada.

## BUG 61 — Obras, custos, prazos e etapas fictícios

Categoria: Lógica/Funcional/Interface. Severidade: Alta. Status: corrigido na interface; edição visual com projeto descartável permanece pendente. Arquivos `frontend/src/pages/Obras.tsx`, `frontend/src/lib/workView.ts` e `frontend/tests/workView.test.mjs`. Rota `/obras`; APIs `/api/projetos`, `/api/projetos/{id}/resumo` e `/api/projetos-etapas/{id}`.

Causa e impacto: a página continha seis obras, cinco etapas, custos, progresso, datas, dois alertas e totais financeiros fixos. A conta vazia apresentava imóveis de São Paulo e mais de R$ 1 milhão em orçamento como se fossem registros reais. O wizard duplicado enviava imóvel como texto, datas sem tipo, campo `responsavel` inexistente e categorias inconsistentes; várias ações e abas não operavam. Isso permitia decisão operacional e financeira baseada em dados inventados.

Como reproduzir antes: entrar em qualquer conta, inclusive vazia; abrir `/obras`; observar “Casa Jardim Paulista”, “Sobrado Vila Madalena”, valores e etapas idênticos. Buscar e alternar status filtrava apenas a constante local. Abrir uma obra ou o cadastro exibia dados e controles sem relação confiável com o backend.

Correção: a tela agora percorre todas as páginas permitidas de projetos, consulta cada resumo real e deriva status atrasado somente quando o prazo persistido venceu sem conclusão/cancelamento. Busca, filtros, KPIs, custos, saldo e etapas usam registros da conta. Se um resumo falhar, valores financeiros parciais são ocultados e há nova tentativa. O painel de etapas atualiza status/progresso pelo endpoint tenant-aware existente. O cadastro duplicado foi retirado; “Nova obra” abre o fluxo real `/projetos?novo=1`. Estado vazio, carregamento, erro e ausência de resultados são explícitos.

Verificação: build TypeScript/Vite aprovado com 2.300 módulos; suíte Node 23/23. Três regressões novas comprovam precedência de concluída/cancelada sobre atraso, busca em projeto/imóvel e marcação de totais incompletos. A conta demo vazia mostrou zero obras e zero valores, sem nomes fictícios nem erros no console. “Criar primeira obra” abriu o diálogo real em `/projetos?novo=1`, desabilitado enquanto não há imóvel. O backend de etapa já possui validação e isolamento cobertos por `DomainIntegrityTest`; a suíte PHP consolidada anterior permanece 168/1.034.

Como verificar: em conta de teste, criar imóvel/projeto descartável; abrir `/obras`; conferir nomes, datas e valores contra `/api/projetos/{id}/resumo`; pesquisar pelo imóvel; mudar status/progresso de uma etapa e recarregar. Criar projeto vencido, concluído e cancelado para confirmar os badges. Em outra conta, o projeto não deve aparecer e seu ID de etapa deve responder 404. A edição visual não foi executada nesta rodada porque a conta demo não possui projeto e nenhum dado artificial foi gravado.

## BUG 62 — Análise de imóvel inteiramente fictícia

Categoria: Lógica/Funcional/Interface. Severidade: Alta. Status: corrigido no CRUD implementado; fluxo visual com fixture pendente. Arquivos `frontend/src/pages/AnaliseImovel.tsx`, `frontend/src/lib/propertyForm.ts`, `frontend/tests/propertyForm.test.mjs` e `frontend/src/components/Sidebar.tsx`. Rota `/imoveis`.

Causa e impacto: a tela exibia “Casa Jardim Paulista”, endereço, características, aquisição de R$ 420 mil, investimento de R$ 580.250, cenários de venda/ROI/lucro, cronograma e quatro comparáveis definidos em constantes. Salvar, relatório, editar, cenários, abas e anotações eram inertes. Qualquer conta, inclusive vazia, recebia a mesma suposta análise, permitindo decisões baseadas em patrimônio e retorno inexistentes.

Reprodução anterior: entrar em conta vazia, abrir `/imoveis` e observar os valores/nome fixos. Alternar cenários ou abas apenas alterava a apresentação local; recarregar restaurava tudo. Clicar salvar/editar/relatório não persistia nem gerava resultado.

Correção: a rota agora é o cadastro real de imóveis. A interface percorre todas as páginas da API, calcula somente indicadores persistidos, busca por nome/tipo/endereço, filtra status e apresenta detalhes reais. Cadastro, edição e exclusão lógica usam `/api/propriedades`; formulário cobre dados físicos, aquisição, venda alvo, endereço e observações. Estados vazio, carregamento, falha e filtros sem resultado são explícitos. O menu passou a chamar o módulo de “Imóveis”; projeções financeiras continuam no fluxo real de análise financeira, sem duplicação cenográfica nesta página.

Verificação: conta demo vazia mostrou zero imóveis/valores e nenhum dado de Jardim Paulista. Formulário completo abriu em viewport estreito com foco no nome; envio vazio manteve o diálogo e exibiu validação nativa; Escape fechou; console sem erros. Build TS/Vite aprovado com 2.301 módulos; frontend 25/25. Dois testes do payload cobrem números, zero, CEP/UF e limpeza explícita de opcionais. Suíte PHP completa 172 testes/1.071 asserções.

Como verificar: criar imóvel descartável na UI, recarregar, buscar pelo nome/endereço, filtrar status, editar inclusive limpar opcionais e excluir; confirmar que a lista/indicadores refletem cada operação. Em outra conta, a coleção deve ficar própria e IDs alheios devem responder 404. Nenhum registro foi criado na conta demo nesta rodada.

## BUG 63 — API de propriedades aceitava paginação e campos sem limites adequados

Categoria: Segurança/Disponibilidade/Validação. Severidade: Média. Status: corrigido e testado. Arquivos `PropriedadeIndexRequest.php`, `PropriedadeRequest.php`, `PropriedadeController.php`, `Propriedade.php`, `PropriedadeEndereco.php`, `PropriedadeService.php`, `PropriedadeTest.php` e `SecurityRegressionTest.php`.

Causa e impacto: `per_page`, busca e status da coleção não passavam por validação; um usuário da conta podia pedir páginas arbitrariamente grandes. Quartos, banheiros, vagas e observações não tinham teto compatível, permitindo payloads que podiam exceder o tipo do banco ou consumir recursos. Usuário autenticado sem tenant recebia 200 vazio em vez de bloqueio explícito. `deletado_flg` também permanecia fillable no model, ampliando o risco caso dados não validados chegassem a uma criação interna.

Correção: request dedicado limita página a inteiro positivo, `per_page` a 1–100, busca a 120 caracteres e status à allowlist. Contagens ficam entre 0–1.000 e observações em até 5.000 caracteres. Usuário sem tenant recebe 403. `deletado_flg` saiu do fillable; exclusão lógica usa atribuição explícita. O controller usa somente dados validados.

Verificação: `PropriedadeTest` passou 4 testes/37 asserções cobrindo CRUD/endereço, paginação 25+1, filtros inválidos, limites, campos privilegiados, usuário sem tenant e leitura/mutação/exclusão entre tenants. A suíte completa passou 172/1.071. Durante regressão, seis fixtures internas revelaram a necessidade legítima de `tenant_id` explícito fora de sessão; essa compatibilidade foi restaurada, enquanto a API continua ignorando o campo por usar `validated()`.

Como reproduzir antes: autenticado, chamar `GET /api/propriedades?per_page=101` ou `?status=desconhecido`; a listagem aceitava os parâmetros. Enviar `quartos=1001` ou observação acima de 5.000 caracteres não era rejeitado pelas regras. Após a correção, todos retornam 422; usuário sem tenant recebe 403; IDs de outro tenant continuam 404.

## BUG 64 — Financeiro era somente uma tela “Em breve”

Categoria: Funcional/Interface. Severidade: Alta. Status: corrigido no painel implementado. Arquivos `frontend/src/pages/Financeiro.tsx`, `frontend/src/lib/financeView.ts`, `frontend/tests/financeView.test.mjs` e `frontend/src/App.tsx`. Rota `/financeiro`; fonte `/api/dashboard`.

Causa e impacto: a entrada Financeiro abria apenas `PageStub` com “Em breve”. Não havia posição financeira, movimentação, composição, projetos, atividades ou caminhos operacionais. O usuário não conseguia confirmar investimento, vendas, lucro e ROI pela área anunciada no menu.

Reprodução anterior: autenticar e abrir `/financeiro`; observar somente o título e “Em breve”, sem carregamento ou ações. Esperado: dados próprios e acesso aos fluxos financeiros existentes.

Correção: a rota agora consome o resumo tenant-aware do Dashboard e apresenta total investido, faturamento, lucro/ROI realizados, composição, seis meses de competência, projetos em andamento e despesas recentes. Escopo e regra de despesas canceladas/vendas concluídas são explícitos. Estados de carregamento, falha, tentativa, atualização e conta sem movimento foram implementados. Todos os controles são funcionais: Despesas, Projetos, Relatórios e Criar projeto navegam aos fluxos existentes. Nenhuma rota backend nem cálculo financeiro paralelo foi criado.

Verificação: conta demo vazia mostrou R$ 0,00, zero imóveis/projetos, maio–outubro/2026 e estados vazios; console sem erros. Os três atalhos navegaram respectivamente para `/despesas`, `/projetos` e `/relatorios`. O plural “0 imóveis vendidos” foi conferido após correção visual. Build Vite aprovado com 2.303 módulos; frontend 27/27. Dois testes novos preservam lucro/ROI negativos e normalizam valores inválidos/composição sem divisão por zero. O endpoint permanece coberto por `DashboardTest`; suíte PHP consolidada 172/1.071.

Como verificar: abrir `/financeiro` em conta vazia e confirmar zeros; em conta de teste, cadastrar aquisição, despesa paga/cancelada e venda fechada, comparar os cartões com Dashboard/Relatórios e conferir o mês correspondente. Suspender a API deve apresentar erro e nova tentativa sem inventar valores. Validar os quatro atalhos por teclado. Reconciliação visual com fixture financeira descartável e falha offline ainda não foram executadas.

## BUG 65 — Tarefas era uma tela provisória sem funcionalidade

Categoria: Funcional/Lógica/Interface. Severidade: Alta. Status: corrigido no CRUD implementado; fluxo visual com fixture permanece pendente. Arquivos `frontend/src/pages/Tarefas.tsx`, `frontend/src/lib/taskView.ts`, `frontend/tests/taskView.test.mjs`, `app/Http/Controllers/Api/TarefaController.php`, `app/Http/Requests/TarefaIndexRequest.php`, `app/Http/Requests/TarefaRequest.php`, `app/Http/Resources/TarefaResource.php`, `app/Services/TarefaService.php`, `app/Models/Tarefa.php`, `routes/api.php` e `tests/Feature/TarefaTest.php`. Rota `/tarefas`; API `/api/tarefas*`.

Causa e impacto: `/tarefas` renderizava apenas um `PageStub` com “Em breve”, embora tarefas já fizessem parte do domínio de projetos. O usuário não conseguia listar, criar, editar, concluir, filtrar ou excluir atividades; prazos e custos operacionais ficavam inacessíveis pela aplicação.

Como reproduzir antes da correção: entrar em uma conta válida; abrir `/tarefas`; observar somente o título e a mensagem provisória. Não existia formulário, lista, busca, filtro nem ação persistente. Esperado: gerenciamento das tarefas do tenant com validação de projeto, etapa e responsável.

Correção: API CRUD autenticada e tenant-aware com paginação 1–100, busca, filtros e exclusão lógica. Projeto, etapa e responsável são validados dentro do tenant; a etapa precisa pertencer ao projeto; status, prioridade, datas, textos e custos têm contrato e limites. O frontend percorre todas as páginas, calcula indicadores reais, oferece busca/filtros, formulário, conclusão e exclusão confirmada, com estados de carregamento, erro, vazio e ausência de resultados. O último `PageStub` foi removido do projeto.

Verificação: `TarefaTest` passou 4 testes/58 asserções cobrindo CRUD, conclusão/reabertura, paginação, filtros, relações, limites, mass assignment, tenant ausente e leitura/mutação/exclusão entre tenants. Três testes Node cobrem normalização, custo zero, data de conclusão e atraso. Suítes consolidadas: 176 testes PHP/1.135 asserções e 30 testes frontend; build Vite com 2.304 módulos. No navegador, a conta demo mostrou quatro indicadores zerados e estado vazio; o formulário abriu com foco no título, informou a necessidade de projeto, manteve envio desabilitado, fechou por Escape e o atalho abriu `/projetos?novo=1`; console sem erros.

Como verificar: criar projeto e tarefa descartáveis; recarregar; buscar e filtrar; editar prioridade/prazo/custos; concluir e reabrir; excluir e confirmar que não reaparece no projeto. Em outra conta, coleção e IDs devem permanecer inacessíveis. O CRUD visual completo com fixture e falha offline ainda não foi executado.

## BUG 66 — Recurso de projeto serializava tarefas sem contrato explícito e incluía excluídas

Categoria: Segurança/Privacidade/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Resources/ProjetoResource.php`, `app/Http/Resources/TarefaResource.php`, `app/Models/Projeto.php` e `tests/Feature/TarefaTest.php`. Rota afetada: `GET /api/projetos/{id}`.

Causa e impacto: `ProjetoResource` entregava a coleção Eloquent bruta de tarefas. Isso expunha campos internos como `tenant_id` e `deletado_flg`, acoplava a resposta ao model e permitia que tarefas excluídas logicamente reaparecessem ao carregar um projeto. O TenantScope ainda impedia cruzamento entre contas, portanto não foi comprovado IDOR nesse caminho.

Como reproduzir antes da correção: criar projeto e tarefa próprios; consultar o projeto e observar campos internos no objeto da tarefa; marcar a tarefa como excluída e consultar novamente. Esperado: contrato mínimo e apenas tarefas ativas. Observado: serialização bruta e relação sem filtro de exclusão.

Correção: `ProjetoResource` usa `TarefaResource`, que seleciona campos públicos e relações seguras; a relação `Projeto::tarefas()` filtra `deletado_flg=false`. O endpoint dedicado também usa o mesmo resource.

Verificação: a regressão cria tarefa com tenant/flag internos, consulta o projeto e confirma ausência desses campos; os testes de CRUD confirmam que exclusão retorna 404 e não volta à listagem. `TarefaTest` 4/58 e suíte consolidada 176/1.135 passaram.

Como verificar: consultar um projeto com tarefa ativa e confirmar somente o contrato documentado; excluir a tarefa e consultar novamente. `tenant_id` e `deletado_flg` não devem aparecer, e a tarefa excluída não deve constar.

## BUG 67 — Configurações declarava integrações conectadas sem consultar o backend

Categoria: Funcional/Segurança/Interface. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/pages/Configuracoes.tsx`, `app/Http/Controllers/Api/DataSourceStatusController.php`, `routes/api.php` e `tests/Feature/DataSourceStatusTest.php`. Rota `/configuracoes`; endpoint `GET /api/data-sources/status`.

Causa e impacto: os estados das fontes eram constantes do bundle. Portais e ITBI importado apareciam como “conectados” mesmo sem confirmar runtime ou dados; OpenStreetMap, BCB/IBGE e outros eram descritos como disponíveis apesar de não haver verificação ou implementação correspondente. A interface podia induzir o usuário a confiar em fontes ausentes e não diferenciava configuração de saúde externa.

Como reproduzir antes da correção: iniciar a aplicação sem chave Google, endpoints externos e transações ITBI; abrir `/configuracoes`; observar Portais do radar e ITBI importado como conectados e SELIC/CDI e INCC/IPCA como disponíveis. Alterar configuração ou importar dados não modificava a tela, pois nenhum request era realizado.

Correção: endpoint autenticado calcula um snapshot sem chamadas externas. Ele diferencia conectado, configurado, disponível, sem dados, não configurado, não implementado e indisponível; verifica runtime local do scraper, presença de credencial, endpoints HTTPS e contagem/última importação ITBI. Chaves, tokens, endpoints e caminhos não fazem parte da resposta. A UI carrega esse contrato, possui loading, erro, nova tentativa, atualização e totais separados.

Verificação: `DataSourceStatusTest` passou 3 testes/25 asserções para anonimato, confirmação de e-mail, conta ativa, tenant obrigatório, configuração segura, HTTPS, ausência de segredos e dados ITBI reais. Suíte completa 179 testes/1.161 asserções; frontend 30/30; build com 2.304 módulos. No navegador, a conta demo exibiu 2 fontes utilizáveis/configuradas, 5 exigindo atenção e 7 não implementadas; ITBI mostrou 0 transações, Google/endpoints ficaram não configurados e não houve erro/aviso no console.

Como verificar: abrir Configurações sem credenciais e confirmar os estados acima; importar uma planilha ITBI autorizada e clicar Atualizar para observar contagem/última importação. Em ambiente de teste, configurar apenas endpoints HTTPS e conferir “Configurado”; valores HTTP devem aparecer indisponíveis. Inspecionar a resposta e confirmar ausência de chaves, tokens, URLs e caminhos. O snapshot não sonda provedores externos e não deve ser interpretado como monitoramento de disponibilidade.

## BUG 68 — Login não possuía recuperação e redefinição de senha

Categoria: Autenticação/Funcional/Segurança. Severidade: Média. Status: corrigido no fluxo implementado; entrega externa continua dependente da configuração Resend. Arquivos `app/Http/Controllers/Api/PasswordResetController.php`, `app/Http/Requests/Auth/ForgotPasswordRequest.php`, `app/Http/Requests/Auth/ResetPasswordRequest.php`, `app/Notifications/ResetPasswordEmail.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `routes/api.php`, `frontend/src/pages/ForgotPassword.tsx`, `frontend/src/pages/ResetPassword.tsx`, `frontend/src/pages/Login.tsx`, `frontend/src/lib/passwordReset.ts`, `frontend/src/lib/api.ts`, `frontend/src/App.tsx`, `tests/Feature/PasswordResetTest.php` e `frontend/tests/passwordReset.test.mjs`.

Causa e impacto: Laravel já possuía broker e tabela de tokens, mas nenhuma rota, notificação, tela ou link utilizava o recurso. Usuários que esquecessem a senha não tinham recuperação possível. Uma implementação ingênua ainda poderia enumerar contas, expor token em query/referrer, aceitar conta suspensa ou manter sessões roubadas ativas após a troca.

Como reproduzir antes da correção: abrir `/login`; não havia “Esqueci minha senha” nem rota pública correspondente. Requisições a `/api/auth/password/forgot` ou `/reset` retornavam 404. Esperado: solicitação neutra, link expirável e redefinição segura.

Correção: dois endpoints públicos validados e limitados foram adicionados. A solicitação responde 202 com mensagem idêntica para conta existente, inexistente ou inativa. Apenas usuário e empresa ativos recebem notificação. O token aleatório permanece hashado no banco e viaja no fragmento `#` da URL frontend, fora do request inicial e do referrer. Redefinição exige e-mail/token, confirmação, 12–72 caracteres e no máximo 72 bytes; o token expira em 60 minutos, é de uso único, gira `remember_token` e revoga todos os tokens Sanctum. Falhas de entrega são registradas sem e-mail/token/erro privado na resposta. Limites: solicitação 10/h por IP e 3/h por conta normalizada; redefinição 10/min por IP e 5/min por conta.

Verificação: `PasswordResetTest` passou 5 testes/39 asserções: anti-enumeração, URL em fragmento, conta inativa, token hashado/expirado/inválido/uso único, empresa suspensa, limites de senha, rate limit, erro neutro, novo login e revogação de duas sessões. Dois testes Node validam parsing/normalização/rejeição do fragmento. Suíte consolidada 184 testes/1.200 asserções; frontend 32/32; build Vite com 2.307 módulos. No navegador, o link do login abriu o formulário, e-mail fictício inexistente recebeu a resposta neutra, divergência de senhas e link incompleto foram tratados sem request destrutivo e o console ficou limpo.

Como verificar: em conta descartável e mailer de teste, solicitar recuperação e conferir o link recebido; observar que token/e-mail aparecem após `#`, redefinir e confirmar que todos os tokens antigos retornam 401 e apenas a nova senha autentica. Reutilizar ou esperar mais de 60 minutos deve produzir 422 genérico. Comparar a resposta de e-mail existente e inexistente: ambas devem ser 202 iguais. Entrega real pelo Resend não foi alegada porque chave/remetente continuam ausentes neste ambiente.

## BUG 69 — Logout sem token pessoal retornava 500 e não encerrava sessão web

Categoria: Autenticação/Segurança/Funcional. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/AuthService.php`, `app/Http/Controllers/Api/AuthController.php` e `tests/Feature/SecurityRegressionTest.php`. Rota `POST /api/auth/logout`.

Causa e impacto: `AuthService::logout()` executava `currentAccessToken()->delete()` sem verificar o tipo ou a presença do token. Autenticação Sanctum pode ocorrer por bearer token ou por sessão web/cookie. Sem `PersonalAccessToken`, o valor era nulo ou transitório, causando erro 500; a sessão permanecia autenticada e o usuário não conseguia encerrá-la pelo endpoint.

Como reproduzir antes da correção: autenticar um usuário pelo guard de sessão/Sanctum sem criar token pessoal; enviar `POST /api/auth/logout`. Esperado: 200 e sessão invalidada. Observado: 500 “Call to a member function delete() on null”; a chamada seguinte ainda podia usar a sessão.

Correção: o serviço recebe o request e distingue mecanismos. Quando o token corrente é `PersonalAccessToken`, exclui somente ele, preservando outros dispositivos. Sem token pessoal, executa logout do guard web; quando há sessão, invalida seu ID/dados e regenera o token CSRF. Nenhum logout global foi aplicado ao fluxo bearer.

Verificação: três regressões de logout/8 asserções comprovam exclusão somente do bearer atual, resposta segura sem token pessoal e invalidação real da sessão web seguida de 401. Suíte consolidada: 186 testes/1.204 asserções. Pint dos arquivos alterados passou.

Como verificar: criar duas sessões bearer, encerrar uma e confirmar que somente ela recebe 401; autenticar via sessão web, chamar logout e confirmar 401 em `/api/auth/me`; uma chamada anônima ou repetida deve retornar 401 pelo middleware, sem erro 500.

## BUG 70 — Documento da SPA era servido sem cabeçalhos defensivos

Categoria: Segurança/Configuração. Severidade: Média. Status: corrigido e testado no Vite dev/preview e artefato estático. Arquivos `frontend/security-headers.ts`, `frontend/vite.config.ts`, `frontend/public/_headers` e `frontend/tests/securityHeaders.test.mjs`. Rotas afetadas: todas as páginas do frontend.

Causa e impacto: somente as respostas `/api/*` recebiam `no-store`, `nosniff`, política de referência e bloqueio de frame. Uma resposta real de `http://127.0.0.1:5173/configuracoes` não tinha CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` nem `Permissions-Policy`. Isso deixava o documento sem defesa em profundidade contra clickjacking, carregamento de conteúdo ativo não previsto, sniffing e uso acidental de APIs do navegador. Não foi demonstrada execução de XSS; o achado é uma configuração insegura confirmada.

Como reproduzir antes da correção: executar `Invoke-WebRequest http://127.0.0.1:5173/configuracoes` e inspecionar `Headers`; os campos acima estavam ausentes. A página carregava normalmente, mas podia ser enquadrada por outra origem e não impunha política própria de fontes.

Correção: uma política centralizada adiciona CSP, `frame-ancestors 'none'`, `X-Frame-Options: DENY`, `nosniff`, `no-referrer`, COOP, Permissions Policy e bloqueio de políticas cross-domain. Produção aceita scripts somente de `'self'`; desenvolvimento admite apenas inline necessário ao React Refresh e WebSocket do HMR, sem `unsafe-eval`. Google Fonts, IBGE e imagens HTTPS permanecem compatíveis. Vite aplica os headers em dev e preview; `_headers` segue no `dist` para hosts estáticos compatíveis.

Verificação: dois testes Node validam diretivas e impedem `unsafe-inline` no `script-src` de produção. A suíte frontend passou 34/34 e o build processou 2.307 módulos. Requisições HTTP reais em 5173 e preview 4173 confirmaram todos os headers; a política de preview não contém script inline. A página autenticada foi recarregada em `/configuracoes`, manteve perfil e conteúdo e não gerou erros/avisos no console.

Como verificar: consultar os headers no dev e em `npm run preview`; confirmar `DENY`, `nosniff`, `no-referrer` e CSP. Tentar enquadrar a página em um iframe de origem separada deve ser bloqueado. Depois do deploy, repetir no domínio público, pois servidores que ignoram `_headers` precisam receber configuração equivalente no proxy/CDN.

## BUG 71 — Erros da API dependiam de `Accept` e podiam expor diagnóstico interno

Categoria: Segurança/Funcional/API. Severidade: Média. Status: corrigido e testado. Arquivos `bootstrap/app.php`, `app/Http/Middleware/ApiSecurityHeaders.php` e `tests/Feature/ApiHeadersTest.php`. Superfície: qualquer `/api/*` inválido ou que lance exceção.

Causa e impacto: o handler Laravel escolhia HTML ou JSON usando somente `expectsJson()`. Sem `Accept: application/json`, `POST /api/auth/login` inválido retornava 302 para a raiz e acabava exibindo a SPA; uma rota API inexistente retornava uma página HTML. Ao apenas forçar JSON, `APP_DEBUG=true` revelou `exception`, caminho absoluto, linha e stack trace. Clientes genéricos recebiam contrato imprevisível e ambientes de teste/debug vazavam estrutura interna.

Como reproduzir antes da correção: enviar `POST /api/auth/login` com corpo `{}` e `Content-Type: application/json`, sem `Accept`, desabilitando redirects; observar 302 e `Location: http://127.0.0.1:8000`. Acessar `/api/does-not-exist` sem `Accept`; observar HTML 404. Com debug ativo e renderização JSON, o corpo incluía caminho do workspace e trace.

Correção: `shouldRenderJsonWhen` trata qualquer caminho `/api/*` como API, independentemente de negociação do cliente. O callback final remove `exception`, `file`, `line` e `trace`; respostas 5xx ficam em `{"message":"Server Error"}`. Os mesmos headers `no-store`, `nosniff`, `no-referrer` e `DENY` são reaplicados às exceções, inclusive 404 sem rota. Respostas de validação preservam `message` e `errors` úteis.

Verificação: `ApiHeadersTest` passou 4 testes/54 asserções, incluindo validação/404 sem `Accept` e RuntimeException com marcador privado sob debug. A suíte completa passou 188 testes/1.240 asserções; Pint dos três arquivos passou. HTTP real confirmou login inválido 422 JSON e rota ausente 404 JSON, ambos sem redirect/HTML/trace e com os quatro headers.

Como verificar: repetir os dois comandos sem `Accept` e `--max-redirs 0`; esperar 422 e 404 JSON. Em teste automatizado, lançar exceção com texto e caminho fictícios em `/api/*`; a resposta deve conter somente a mensagem genérica e nunca o marcador. Confirmar que validações 422 ainda listam os campos inválidos.

## BUG 72 — Servidor local expunha arquivos ocultos e configuração do diretório público

Categoria: Segurança/Configuração/Exposição de informação. Severidade: Média. Status: corrigido e testado. Arquivos `app/Support/LocalPublicFile.php`, `server.php`, `public/index.php`, `public/.htaccess`, `public/.user.ini`, `composer.json` e `tests/Unit/LocalPublicFileTest.php`. Superfície: arquivos estáticos sob o backend local/servidor web.

Causa e impacto: `LocalPublicFile::isAllowedPath()` aceitava todo arquivo canônico dentro de `public`, sem bloquear dotfiles ou extensões executáveis/configuração. Requisições HTTP reais a `/.user.ini` e `/.htaccess` retornavam 200 com seus conteúdos. Isso expunha limites e configuração do servidor e permitiria divulgar futuros arquivos ocultos, dumps ou configurações colocados acidentalmente no diretório público. As respostas também revelavam `PHP/8.4.24` em `X-Powered-By`.

Como reproduzir antes da correção: iniciar o script de desenvolvimento documentado; acessar `GET /.user.ini` e `GET /.htaccess`; observar status 200 e o conteúdo dos arquivos. Consultar `/api/auth/me`, `/up` ou um estático e observar `X-Powered-By: PHP/8.4.24`.

Correção: o roteador usa allowlist separada para ativos web da raiz pública e downloads permitidos de `/storage`, rejeita qualquer segmento iniciado por ponto e nunca delega PHP, HTML, SQL ou extensões desconhecidas ao servidor estático. Arquivos físicos negados recebem 404 diretamente. `.htaccess` nega dotfiles em Apache e remove o header quando `mod_headers` existe; `.user.ini`, os scripts Composer e os entrypoints removem/desativam `expose_php` em seus contextos suportados.

Verificação: cinco testes unitários/29 asserções cobrem raiz, symlinks, travessia, extensões e dotfiles. HTTP real confirmou 404 para `.user.ini`, `.htaccess`, `index.php` e `%2euser.ini`; `robots.txt` e fixture `.txt` autorizada em `/storage` retornaram 200; API e `/up` continuaram operando e nenhum caso trouxe `X-Powered-By`. A fixture foi removida. `composer validate`, Pint específico e suíte completa 189/1.252 passaram.

Como verificar: repetir os GETs acima e confirmar 404 sem conteúdo de configuração. Solicitar `robots.txt` e um arquivo permitido descartável em `/storage` e confirmar 200; remover a fixture. Inspecionar API, health e estáticos para ausência de `X-Powered-By`. Repetir no servidor/proxy de produção, pois `.htaccess` não configura Nginx/CDNs.

## BUG 73 — Endpoint não utilizado expunha limites internos do PHP

Categoria: Segurança/Exposição de informação. Severidade: Baixa. Status: corrigido e testado. Arquivos `routes/api.php`, `tests/Feature/ImportSecurityTest.php` e `meta/routes.json`. Rota removida: `GET /api/itbi-imports/upload-limits`.

Causa e impacto: a rota devolvia diretamente `ini_get('upload_max_filesize')`, `post_max_size` e `memory_limit` para qualquer usuário autenticado, ativo e verificado. Não exigia a permissão `itbi.import`, não tinha consumidor no frontend e revelava configuração de capacidade útil para fingerprinting. Não expunha credenciais nem permitia alterar limites.

Como reproduzir antes da correção: autenticar uma conta comum verificada e chamar `/api/itbi-imports/upload-limits`; observar 200 com os três valores PHP. Pesquisar o frontend confirmava que nenhum componente utilizava a resposta.

Correção: a rota foi removida. A tela mantém o limite contratual já exibido e o endpoint real de upload continua validando 256.000 KiB, permissão de operador e throttle. O inventário de rotas foi regenerado após a alteração.

Verificação: regressão autenticada confirma 404 e ausência de `memory_limit`/`upload_max_filesize`. HTTP real também retornou 404 JSON sem esses textos; `route:list --json` contém 90 rotas e nenhuma correspondência. `ImportSecurityTest` passou 6 testes/34 asserções; suíte completa 190/1.254 e Pint passaram.

Como verificar: autenticar conta comum ou operadora e repetir o GET; deve retornar 404. Confirmar que `/api/itbi-imports` e o upload autorizado continuam operacionais e que arquivos acima do limite continuam rejeitados pelo backend/proxy.

## BUG 74 — Exclusão de oportunidade mantinha fotos públicas órfãs

Categoria: Privacidade/Integridade/Armazenamento. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/OpportunityService.php`, `app/Exceptions/OpportunityPhotoStorageException.php`, `app/Http/Controllers/Api/OpportunityController.php` e `tests/Feature/OpportunityTest.php`. Rota `DELETE /api/opportunities/{opportunity}`.

Causa e impacto: a exclusão lógica removia somente o registro da oportunidade. Fotos locais permaneciam no disco público e continuavam acessíveis a quem conhecesse o link, sem metadado ativo que permitisse gerenciá-las. A política escolhida pelo usuário — fotos públicas por link — permanece válida enquanto o registro existe, mas não justifica conservar o arquivo após sua exclusão.

Reprodução anterior: criar oportunidade com foto local, guardar o caminho, excluir a oportunidade e verificar o disco público. A API respondia sucesso e a foto continuava presente. O teste de regressão foi executado primeiro contra o comportamento antigo e falhou ao encontrar `opportunities/local.jpg`.

Correção: a exclusão bloqueia o registro, move cada foto local gerenciada para quarentena privada, remove o original público e somente então apaga metadados e aplica soft delete dentro da transação. Falhas restauram arquivos e banco; caminhos fora de `opportunities/` são recusados com 503 neutro. URLs externas têm apenas seus metadados removidos. Após o commit, a cópia privada é eliminada; eventual falha final deixa somente resíduo privado e gera log sem caminho sensível.

Verificação: três cenários comprovam remoção bem-sucedida, recusa atômica de caminho não gerenciado e restauração após falha simulada do banco. `OpportunityTest` passou 15 testes/75 asserções; suíte completa 193/1.270 e Pint passaram. Como verificar manualmente: cadastrar foto descartável, excluir a oportunidade e confirmar 404 no link antigo; simular indisponibilidade do storage em ambiente de teste e confirmar que oportunidade, metadado e arquivo original continuam presentes.

## BUG 75 — Falha ao apagar arquivo ocultava documento e deixava órfão privado

Categoria: Integridade/Armazenamento/API. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/DocumentoService.php`, `app/Http/Controllers/Api/DocumentoController.php` e `tests/Feature/DocumentoTest.php`. Rota `DELETE /api/documentos/{id}`.

Causa e impacto: o serviço marcava `deletado_flg=true` antes de remover o arquivo. Se o storage recusasse ou lançasse exceção, a API podia responder 200 ou 500 depois de ocultar o registro, deixando um arquivo privado órfão sem operação normal de nova tentativa. O caminho persistido também não era revalidado antes da exclusão física.

Reprodução anterior: criar um documento e simular exceção no adaptador ao excluir; a regressão, escrita antes da correção, esperava 503 e registro ativo, mas recebeu 500 depois da atualização lógica. Em um retorno falso de `delete`, o código apenas registrava warning e ainda respondia sucesso.

Correção: o arquivo é copiado para quarentena privada, removido do caminho ativo e o registro é alterado dentro de transação com lock. Falhas de storage retornam 503 neutro e preservam o registro; falha posterior de banco restaura o arquivo original. O caminho precisa pertencer exatamente a `documents/{tenant_id}/` e conter somente um nome seguro. Após commit, a quarentena é eliminada; falha nessa limpeza deixa apenas cópia privada e log sem caminho.

Verificação: `DocumentoTest` passou 9 testes/76 asserções, incluindo exclusão legítima sem resíduos, falha de leitura com resposta neutra e registro ativo, path traversal recusado, rollback forçado com restauração, IDOR, upload/download e validações. Suíte completa: 196 testes/1.285 asserções; Pint aprovado.

## BUG 76 — Propriedade em uso podia ser ocultada e deixar registros ativos inconsistentes

Categoria: Integridade/Lógica de negócio/API. Severidade: Média. Status: corrigido e testado. Arquivos `app/Exceptions/PropertyInUseException.php`, `app/Services/PropriedadeService.php`, `app/Http/Controllers/Api/PropriedadeController.php` e `tests/Feature/PropriedadeTest.php`. Rota `DELETE /api/propriedades/{id}`.

Causa e impacto: a exclusão lógica marcava a propriedade como apagada sem consultar projetos, vendas ou despesas ativos. O imóvel desaparecia do CRUD e a API retornava 200, enquanto registros operacionais e financeiros continuavam referenciando-o. Isso criava navegação inconsistente e permitia esconder a entidade raiz de dados ainda ativos.

Reprodução anterior: criar propriedade e projeto ativo, chamar DELETE e consultar novamente. A regressão escrita antes da correção esperava 409, mas recebeu 200 e encontrou a propriedade com `deletado_flg=true`.

Correção: dentro de transação, o serviço bloqueia a propriedade e verifica projetos, vendas e despesas não excluídos. Qualquer vínculo ativo lança conflito de domínio; o controller responde 409 com mensagem estável. Quando todos os vínculos estão logicamente excluídos, a propriedade pode ser removida. O check e a alteração usam o tenant scope existente.

Verificação: `PropriedadeTest` passou 6 testes/51 asserções, cobrindo projeto, venda e despesa ativos, liberação após exclusão lógica dos vínculos, CRUD legítimo, IDOR, limites e paginação. A prova anterior recebeu 200 indevido. Suíte completa: 198 testes/1.299 asserções; Pint aprovado.

## BUG 77 — Exclusão de projeto mantinha tarefas e eventos ativos

Categoria: Integridade/Lógica de negócio/API. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/ProjetoService.php` e `tests/Feature/DomainIntegrityTest.php`. Rota `DELETE /api/projetos/{id}`.

Causa e impacto: excluir um projeto alterava somente `projetos.deletado_flg`. Etapas continuavam ativas no banco, tarefas permaneciam listadas em `/api/tarefas` e eventos do calendário continuavam visíveis. O projeto pai já retornava 404, criando itens operacionais órfãos na interface e contratos contraditórios entre módulos.

Reprodução anterior: criar projeto com etapa, tarefa e evento, excluir o projeto e listar tarefas/calendário. A regressão escrita antes da correção recebeu uma tarefa na coleção, quando esperava zero; os três registros dependentes continuavam com `deletado_flg=false`.

Correção: `ProjetoService::excluir` agora bloqueia o projeto e, na mesma transação, marca tarefas, etapas e eventos ativos como excluídos antes de ocultar o projeto. Despesas e documentos são preservados como registros históricos vinculados; não são dados operacionais vivos e continuam disponíveis em seus módulos.

Verificação: `DomainIntegrityTest` passou 10 testes/94 asserções, incluindo coleções vazias após exclusão, flags dos três dependentes, rejeição de novas etapas/mutações, rollback de orçamento, IDOR e consistência financeira. Suíte completa: 200 testes/1.312 asserções; Pint aprovado.

## BUG 78 — Filtros de coleções aceitavam estados desconhecidos e faixa de preço invertida

Categoria: Validação/API/QA. Severidade: Baixa. Status: corrigido e testado. Arquivos `app/Http/Middleware/ValidatePagination.php` e `tests/Feature/CollectionFilterValidationTest.php`. Rotas de coleção de projetos, vendas, despesas, categorias de despesa e oportunidades.

Causa e impacto: o middleware global limitava tipos e comprimentos, mas tratava `status`, `tipo`, `source` e `property_type` como strings livres. Filtros booleanos de oportunidades não eram validados e `min_price > max_price` era aceito. A API respondia 200 com coleção vazia ou interpretação booleana inesperada, mascarando erros de cliente e produzindo resultados sem sentido. Não foi demonstrada injeção SQL porque o ORM usa bindings.

Reprodução anterior: autenticar e consultar `/api/projetos?status=desconhecido` ou `/api/opportunities?min_price=200000&max_price=100000`. As duas regressões escritas antes da correção esperavam 422 e receberam 200.

Correção: regras específicas por rota agora aplicam allowlists coerentes com os respectivos FormRequests para projeto, venda, pagamento, categoria, status/tipo/origem de oportunidade e booleanos `favorite/archived`. Uma validação posterior rejeita preço máximo abaixo do mínimo.

Verificação: dois testes novos/27 asserções cobrem oito valores desconhecidos e faixa invertida. O conjunto relacionado passou 25 testes/248 asserções; suíte completa 202 testes/1.339 asserções; Pint aprovado.

## BUG 79 — Disco privado registrava rotas de entrega e upload sem consumidor

Categoria: Segurança/Configuração. Severidade: Baixa. Status: corrigido e testado. Arquivos `config/filesystems.php`, `tests/Feature/SecurityRegressionTest.php`, `meta/routes.json` e `meta/private-storage-route-checks.json`. Rotas removidas: `GET /storage/{path}` (`storage.local`) e `PUT /storage/{path}` (`storage.local.upload`).

Causa e impacto: o disco `local`, cuja raiz é `storage/app/private`, estava com `serve => true`. O Laravel registrava automaticamente uma rota de leitura e outra de upload. Ambas validavam assinatura relativa e os testes HTTP sem assinatura retornaram 403; nenhuma leitura ou gravação indevida foi demonstrada. A aplicação não cria URLs temporárias nem usa essas rotas: documentos privados são baixados pelo controller autenticado. Manter endpoints de escrita/leitura sem consumidor aumentava a superfície e criava risco futuro de emissão acidental de URLs assinadas.

Como reproduzir antes da correção: executar `php artisan route:list --json` e localizar `storage.local` e `storage.local.upload`, sem middleware listado. Enviar GET e PUT sem assinatura para `/storage/audit-private-route.txt`; ambos retornavam 403 e nenhum arquivo era criado. Buscar por `temporaryUrl`, `temporaryUploadUrl` e pelos nomes das rotas fora de dependências; não havia consumidor. Esperado para este produto: nenhum endpoint direto para o disco privado.

Correção: `serve` foi desativado somente no disco `local`. O disco `public`, usado pelas fotos públicas por link conforme decisão do usuário, permanece separado e inalterado. A regressão exige ausência dos dois nomes e de qualquer rota `storage/*` registrada pelo framework.

Verificação: `SecurityRegressionTest` passou 24 testes/141 asserções. O inventário contém 88 rotas e zero rota/nome de storage privado. HTTP real retornou 404 para GET e PUT, e confirmou que nenhum arquivo privado foi criado. Uma fixture descartável no disco público continuou acessível com 200 e conteúdo exato; ela foi removida. Suíte completa: 203 testes/1.342 asserções; Pint aprovado.

Como verificar: consultar `route:list` e confirmar ausência de `storage.local*`; fazer GET e PUT para um nome inexistente em `/storage` e esperar 404 sem arquivo em `storage/app/private`; criar uma imagem ou fixture permitida no disco público e confirmar que `/storage/...` continua 200. URLs públicas ativas permanecem públicas por link; downloads de documentos continuam exigindo a API autenticada.

## BUG 80 — IA fabricava pontos do entorno quando as fontes de mapas falhavam

Categoria: Lógica/Integridade/IA. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Services/NeighborhoodContextService.php`, `tests/Feature/NeighborhoodContextTest.php` e `meta/neighborhood-data-integrity-checks.json`. Rota afetada: `POST /api/neighborhood-context`; tela `/ia-inteligente` → análise de oportunidade.

Causa e impacto: quando Overpass e Nominatim não retornavam pontos, o serviço gerava um “Perfil urbano estimado” com estação, corredor de ônibus, escolas, farmácias, mercados e distâncias fixas. Também preenchia categorias vazias de uma resposta parcial com essa simulação ou com uma lista local não versionada por data/fonte. O frontend somava e apresentava esses registros como “pontos próximos encontrados”, criando evidência imobiliária inexistente. Como localização e liquidez influenciam decisão de compra, o risco foi classificado como alto.

Como reproduzir antes da correção: autenticar conta verificada; simular falha/503 de Nominatim e Overpass; enviar bairro, cidade e UF conhecidos a `/api/neighborhood-context`. A resposta retornava `status=connected`, `source=Perfil urbano estimado`, grupos com contagens positivas e distâncias como 500, 700, 900 ou 1.500 metros, apesar de nenhuma fonte ter confirmado locais. Em resposta Overpass com apenas transporte, outras categorias eram preenchidas artificialmente.

Correção: listas de pontos hardcoded, perfil genérico e preenchimento sintético de grupos foram removidos. A ordem agora é: coleta real do Overpass; busca textual real no Nominatim quando nenhum ponto veio; última coleta válida em cache; ou resposta `unavailable` com grupos vazios. Coordenadas aproximadas podem ajudar a direcionar a consulta, mas nunca geram pontos, nomes ou distâncias. Resposta parcial preserva zero nas categorias que a fonte não informou.

Verificação: `NeighborhoodContextTest` passou 2 testes/20 asserções. Um cenário força falha total e comprova `unavailable`, `groups=[]` e ausência dos nomes/distâncias simulados. Outro retorna somente uma estação real controlada e comprova transporte=1 e as quatro categorias restantes=0. Busca no código não encontra mais os geradores, salvo as asserções negativas. Suíte completa: 205 testes/1.362 asserções; Pint aprovado.

Como verificar: indisponibilizar os endpoints de mapas em ambiente local e abrir uma análise; a seção deve informar indisponibilidade e não mostrar cartões com pontos. Depois fornecer uma resposta controlada contendo apenas uma estação; somente Transporte deve contar um registro. Restaurar os provedores e repetir com dados reais. A precisão e cobertura externas ainda dependem dos provedores e devem ser medidas com dataset real antes de alegar treinamento ou acurácia do modelo.

## BUG 81 — URLs de anúncios externos chegavam ao navegador sem allowlist de esquema

Categoria: Segurança/Validação. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Controllers/Api/AIPropertySearchController.php`, `tests/Feature/AIPropertySearchTest.php` e `meta/external-listing-url-checks.json`. Rota afetada: `GET /api/ai-property-search`; links e miniaturas em `/ia-inteligente` e na análise.

Causa e impacto: itens produzidos pelo scraper eram reutilizados sem normalizar `source_url` e `thumbnail` na fronteira final da API. APIs externas também forneciam links diretamente. A interface colocava `source_url` em `href` e miniatura em `src`. Portais são destinos fixos e o React/CSP oferecem defesas adicionais, mas conteúdo externo malformado ou comprometido podia fornecer esquemas não web, quebrar a navegação ou tentar alcançar um sink executável. Não foi demonstrada execução no navegador local.

Como reproduzir antes da correção: substituir o scraper por resposta controlada com anúncio válido para a região, `source_url=javascript:alert(document.domain)` e `thumbnail=data:image/svg+xml,...`; chamar a busca autenticada. A resposta preservava os dois valores. Esperado: somente URLs HTTP/HTTPS chegam ao frontend.

Correção: todo item, independentemente de scraper, Nestoria ou Mercado Livre, passa por uma allowlist final. `source_url` e `thumbnail` aceitam apenas URL válida cujo esquema normalizado seja `http` ou `https`; `data:`, `blob:`, `javascript:`, `file:`, `ftp:` e valores malformados viram `null`. A filtragem de logo/placeholder permanece. O formulário persistente de oportunidades já possuía `url:http,https`.

Verificação: `AIPropertySearchTest` passou 3 testes/17 asserções. A regressão injeta `javascript:` e imagem `data:` e confirma ambos nulos; o cenário legítimo HTTPS continua retornando um anúncio. Suíte completa: 206 testes/1.366 asserções; Pint aprovado.

Como verificar: repetir a resposta controlada e confirmar `null` nos campos inseguros e ausência dos links/imagens na UI; testar um anúncio HTTPS e confirmar que “Abrir” funciona em nova aba. O conteúdo do portal continua não confiável e deve permanecer sujeito a CSP e validação de saída.

## BUG 82 — Navegador do scraper permitia requisições a redes internas

Categoria: Segurança/SSRF. Severidade: Alta. Status: corrigido e testado. Arquivos `frontend/scripts/network-policy.mjs`, `frontend/scripts/portal-scraper.mjs`, `frontend/tests/network-policy.test.mjs` e `meta/portal-ssrf-protection-checks.json`. Superfície: navegador headless iniciado por `GET /api/ai-property-search`.

Causa e impacto: o scraper abria dez portais confiáveis, mas continuava todas as subrequisições declaradas pelo HTML/JavaScript, exceto fontes, mídia e CSS. Uma página remota comprometida podia solicitar `127.0.0.1`, redes RFC1918, `169.254.169.254` ou IPv6 local a partir do servidor. A função opcional de enriquecimento ainda abria a URL encontrada sem política de rede. Não foi executado ataque contra terceiros nem demonstrado acesso a segredo local; a falha de controle permitia SSRF cego e foi classificada como alta.

Como reproduzir antes da correção: em fixture controlada de portal, incluir uma imagem/fetch apontando para `http://127.0.0.1:8000/`, `http://169.254.169.254/latest/meta-data/` ou hostname público resolvendo para `10.0.0.5`; iniciar a busca. O interceptador anterior chamava `request.continue()` para esses destinos. No enriquecimento, uma `source_url` interna chegava diretamente a `page.goto` quando o modo fosse ativado.

Correção: política central valida somente HTTP/HTTPS sem credenciais, bloqueia localhost, `.local`, endpoints de metadados, IPv4 privado/loopback/link-local/CGNAT/reservado e IPv6 loopback/ULA/link-local/mapeado. Hostnames são resolvidos e a requisição só continua se todos os endereços forem públicos; falha de DNS bloqueia. A política é aplicada a cada requisição e redirecionamento das páginas principais e das páginas opcionais de detalhe. URLs de detalhe também passam por validação antes de `goto`.

Verificação: quatro testes específicos cobrem destinos públicos, redes internas, esquemas/credenciais/formas normalizadas e DNS público que resolve total ou parcialmente para IP privado. A suíte frontend passou 38/38; `node --check` aprovou os dois scripts; build Vite passou com 2.307 módulos. `npm audit --omit=dev` retornou zero vulnerabilidades conhecidas em 249 dependências contabilizadas.

Como verificar: usar resolver injetado que devolve `169.254.169.254`, mistura IP público/privado e devolve apenas IP público; esperar respectivamente bloqueio, bloqueio e permissão. Incluir subrecurso local em uma página controlada e confirmar `abort`. O ambiente de produção ainda deve restringir egress no firewall/rede como segunda camada contra DNS rebinding e falhas futuras do navegador.

## BUG 83 — Fallback HTTP dos portais seguia redirecionamentos sem validar o destino

Categoria: Segurança/SSRF. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Support/RemoteUrlPolicy.php`, `app/Services/PropertyPortalScraper.php`, `tests/Unit/RemoteUrlPolicyTest.php`, `tests/Feature/PortalScraperSecurityTest.php` e `meta/portal-http-redirect-checks.json`. Superfície: fallback PHP da busca de imóveis quando o Chromium não retorna itens.

Causa e impacto: o `Http::get()` do fallback usava o comportamento padrão do Guzzle, que segue redirecionamentos. A URL inicial era construída pelo sistema para um portal conhecido, mas esse servidor podia responder `Location` para loopback, rede privada ou metadados. O backend seguiria o salto antes de inspecionar a resposta, constituindo SSRF. Não foi consultado destino interno real; a reprodução usa o cliente HTTP falso.

Como reproduzir antes da correção: simular `https://www.olx.com.br/...` respondendo 302 com `Location: https://127.0.0.1/private`; executar o fallback. O cliente seguia o redirecionamento automaticamente. Esperado: validar cada salto antes de enviar a próxima requisição.

Correção: redirecionamento automático foi desativado. O serviço processa no máximo três saltos, resolve URLs relativas e valida cada destino antes da requisição. Somente HTTPS sem credenciais, fragmento ou porta diferente de 443 e com hostname exato na lista de portais é aceito. DNS A/AAAA precisa retornar pelo menos um endereço e todos devem ser públicos; IP privado, reservado, resultado misto ou vazio é recusado.

Verificação: nove testes unitários cobrem host, downgrade, credenciais, IPv4/IPv6 privado, metadados, DNS misto e ausência de DNS. Dois testes integrados comprovam que o salto interno termina após uma requisição e que um salto autorizado faz duas requisições e retorna 200. Conjunto específico: 11 testes/15 asserções; suíte completa: 217 testes/1.381 asserções; Pint aprovado.

Como verificar: responder 302 para IP interno em `Http::fake` e confirmar `assertSentCount(1)`; responder 302 para outro host da allowlist e confirmar duas requisições/200. No deploy, manter DNS e egress confiáveis; o firewall continua sendo a camada independente contra rebinding e comprometimento de domínio autorizado.

## BUG 84 — Fallback dos portais aceitava HTML sem limite de transferência

Categoria: Segurança/Performance/Disponibilidade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/PropertyPortalScraper.php`, `tests/Feature/PortalScraperSecurityTest.php` e `meta/portal-response-size-checks.json`. Superfície: fallback PHP de coleta de portais.

Causa e impacto: o cliente tinha timeout de 15 segundos, mas carregava `response->body()` sem limite de bytes. Um portal com resposta muito grande, sem `Content-Length` ou com corpo contínuo podia consumir memória e CPU do processo PHP, afetando disponibilidade da busca e requisições concorrentes. O limite do endpoint reduz frequência, porém não limitava o tamanho individual da resposta.

Como reproduzir antes da correção: simular portal autorizado retornando corpo HTML acima de 5 MB; invocar o fallback. A resposta inteira era armazenada e enviada aos parsers. Esperado: interromper transferência acima de um teto suficiente para listagens.

Correção: teto de 5 MiB aplicado em três pontos: `Content-Length` no recebimento dos headers, callback de progresso durante a transferência e tamanho final antes do parse. O cliente solicita `Accept-Encoding: identity` e desativa descompressão automática para que a medição controle os bytes transferidos sem expansão implícita. Excesso recebe falha neutra e corpo vazio.

Verificação: fixture de 5.242.881 bytes é rejeitada com `ok=false`, mensagem neutra e corpo vazio. Os testes de redirect privado e autorizado continuam passando. `PortalScraperSecurityTest`: 3 testes/9 asserções; suíte completa: 218 testes/1.384 asserções; Pint aprovado.

Como verificar: retornar `Content-Length` acima do limite e confirmar aborto no header; repetir sem comprimento com stream progressivo e confirmar interrupção; usar fixture final acima do limite e confirmar que não chega ao parser. O Chromium possui isolamento/timeout próprios, mas limites de memória do processo/container ainda devem ser configurados no deploy.

## BUG 85 — Central Financeira apresentava projeções e confiança sem base comprovável

Categoria: Lógica/Integridade/Interface financeira. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Services/InvestmentIntelligenceService.php`, `app/Services/InvestmentScoreService.php`, `frontend/src/pages/CentralFinanceira.tsx`, `frontend/src/pages/WizardFinanceiro.tsx`, `tests/Feature/InvestmentAnalysisTest.php` e `meta/financial-analysis-transparency-checks.json`. Funcionalidades: Central Financeira e assistente financeiro.

Causa e impacto: a interface comparava o ROI com Poupança, CDI, Tesouro e FII usando percentuais fixos sem fonte, data, período ou equivalência de risco. Um “Assistente InvestFlip” afirmava que reduzir 6% do preço aumentaria o ROI em seis pontos sem recalcular o fluxo. O plano de melhoria ainda inventava preço de compra 6% menor, reforma 15% menor, venda 6% maior, um IF Score derivado e o rótulo “COMPRAR”. A pontuação determinística era apresentada como percentil de oportunidades e uma contagem de campos era chamada de “confiança”. Esperas de um e dois segundos simulavam cálculo depois de a resposta já estar pronta. Isso podia induzir decisão financeira baseada em números sem proveniência ou modelo calibrado.

Como reproduzir antes da correção: concluir uma análise cujo sinal não fosse verde e abrir a visão completa. A tela sempre mostrava os mesmos benchmarks 3/6/7/8%, gerava metas com multiplicadores fixos e dizia que a hipótese otimista resultaria em “COMPRAR”, mesmo sem recalcular o Investment Score. O wizard aguardava dois segundos após a API concluir. Esperado: somente valores calculados a partir das entradas, premissas claramente expostas e nenhuma alegação de confiança, percentil ou geração por IA sem evidência.

Correção: benchmarks sem fonte, metas “ideais”, ROI incremental e score contrafactual foram removidos. O resumo agora enumera apenas desconto, financiamento, reforma e ROI efetivamente calculados. Os cenários usam como base o preço de venda informado e devolvem as variações exatas de venda, custos e prazo, rotuladas como análise de sensibilidade hipotética e sem recomendação. “Confiança” virou “completude dos dados”; a API declara `rules_based_financial_calculation`. O índice informa explicitamente que não é percentil de mercado. Animações com atraso artificial foram removidas. Os rótulos “Comprar/Negociar/Não comprar” foram substituídos por “Acima da meta/Faixa de atenção/Abaixo da meta”; `recommendation` permanece nulo e `rule_signal` identifica o resultado das regras.

Verificação: `InvestmentAnalysisTest` passou 9 testes/68 asserções e confirma os três preços de cenário (R$ 540.000, R$ 600.000 e R$ 648.000 para base R$ 600.000), suas premissas -10/0/+8%, método determinístico, completude e ausência do campo `confidence`. Busca negativa não encontrou benchmarks, metas inventadas ou atrasos artificiais. Suíte PHP completa: 219 testes/1.406 asserções; frontend: 38/38; Pint e build TypeScript/Vite com 2.307 módulos aprovados.

Como verificar: criar análise com venda esperada de R$ 600.000; confirmar que “Premissas informadas” permanece em R$ 600.000, a sensibilidade desfavorável mostra -10%/R$ 540.000 e a favorável +8%/R$ 648.000, com custos/prazo visíveis. Concluir o resultado e confirmar ausência de benchmarks e metas “ideais”; a interface deve dizer que o cálculo é determinístico e que a sensibilidade não é previsão nem recomendação.

## BUG 86 — Simulador atribuía taxas bancárias estáticas ao Banco Central

Categoria: Integridade/Lógica financeira. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Services/FinancingReferenceService.php`, `app/Http/Controllers/Api/InvestmentAnalysisController.php`, `frontend/src/pages/CentralFinanceira.tsx`, `frontend/src/pages/WizardFinanceiro.tsx`, `tests/Feature/InvestmentAnalysisTest.php` e `meta/financing-rate-provenance-checks.json`. Rota `GET /api/investment-analyses/{id}/financing-reference`.

Causa e impacto: o serviço continha dez bancos e taxas fixas, selecionava automaticamente a menor, declarava “Banco Central do Brasil — financiamento imobiliário” e publicava período de maio de 2026. Não havia consulta ao BCB; a tela de fontes já registrava BCB SGS como não implementado. O wizard apresentava os números como referências de mercado e impedia editar a taxa. Isso podia influenciar parcela, saldo, quitação, capital e ROI com uma oferta inexistente ou desatualizada.

Como reproduzir antes da correção: abrir Financiamento sem proposta cadastrada. A API sempre retornava Banco Bari, 0,68% a.m./8,52% a.a., fonte BCB e período de maio de 2026; a lista exibia dez instituições e “menor taxa”. Nenhuma chamada externa sustentava os valores.

Correção: a lista estática foi eliminada. O endpoint recebe somente `monthly_rate` e `bank_name` opcionais, valida limites, calcula a taxa anual equivalente e identifica a origem como simulação manual. Sem taxa informada, retorna zero, banco nulo e lista vazia. Central e wizard agora permitem digitar a instituição e taxa mensal, exibem a anual equivalente e avisam que CET, seguros, TR e condições precisam ser confirmados. A integração BCB continua honestamente marcada como não implementada.

Verificação: `InvestmentAnalysisTest` passou 9 testes/67 asserções; uma taxa informada de 0,95% a.m. resulta em 12,0149% a.a., preserva principal, amortização e saldo após oito parcelas, não publica data externa e devolve lista bancária vazia. Busca negativa não encontra bancos/taxas/fonte/período antigos. Suíte PHP 219/1.405, frontend 38/38, Pint e build com 2.307 módulos passaram.

Como verificar: abrir o wizard, informar nome de instituição, taxa mensal, sistema e prazo; conferir taxa anual equivalente e parcelas. Alterar a taxa e confirmar recálculo. Abrir sem taxa e confirmar zero, sem banco selecionado e sem alegação de BCB ou mercado. Comparar a simulação com proposta real antes de decisão.

## BUG 87 — Prévia calculava ROI mensal e anualizado com fórmula divergente

Categoria: Lógica/Integridade financeira. Severidade: Alta. Status: corrigido e testado. Arquivos `frontend/src/lib/financialReturns.ts`, `frontend/src/pages/CentralFinanceira.tsx`, `frontend/src/pages/WizardFinanceiro.tsx`, `frontend/tests/financialReturns.test.mjs`, `app/Services/InvestmentAnalysisCalculatorService.php`, `tests/Feature/InvestmentAnalysisTest.php` e `meta/financial-return-consistency-checks.json`. Funcionalidade: prévia e resultado da Central Financeira.

Causa e impacto: quando ainda não existia resultado persistido, a interface calculava retorno mensal por `ROI total / meses` e elevava esse valor para anualizar. O backend usava equivalência composta diretamente sobre o fator total. A mesma análise podia, portanto, mostrar dois números incompatíveis e superestimar o retorno anual. Exemplo confirmado: 100% de ROI total em 12 meses aparecia na prévia como aproximadamente 161% anual, embora a equivalência correta e o backend resultassem em 100%.

Como reproduzir antes da correção: informar valores que resultem em capital próprio de R$ 100.000, lucro de R$ 100.000 e duração de 12 meses. Antes de persistir o resultado, observar a prévia anualizada. Esperado: 100% anualizado e aproximadamente 5,9463% ao mês. Observado anteriormente: aproximadamente 161% anualizado por capitalização do quociente simples de 8,3333% ao mês.

Correção: criada função única de frontend que deriva os retornos periódico e anualizado do fator de crescimento total, com duração mínima de um mês e tratamento seguro de perda total/entrada inválida. Central e wizard usam essa convenção, igual à do serviço PHP. Textos restantes de “decisão” foram substituídos por resultado/faixas das regras, sem aconselhamento de compra.

Verificação: dois testes frontend validam 100% em 12 meses, 21% em seis meses, perda de 100% e entrada inválida. Teste PHP independente confirma ROI total 100%, mensal 5,9463%, anualizado 100% e ausência de aconselhamento no motivo. `InvestmentAnalysisTest`: 10 testes/73 asserções; suíte PHP completa: 220/1.411; frontend: 40/40; Pint e build TypeScript/Vite com 2.308 módulos aprovados.

Como verificar: montar o cenário de R$ 100.000 investidos, R$ 200.000 recebidos e 12 meses; confirmar prévia e resultado com 100% total, 5,9463% mensal e 100% anualizado. Repetir com 21% em seis meses e confirmar aproximadamente 3,2280% mensal e 46,41% anualizado.

## BUG 88 — Área e análise de mercado aceitavam números acima do domínio suportado

Categoria: Funcional/Validação/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/PropriedadeRequest.php`, `app/Http/Requests/StoreOpportunityRequest.php`, `app/Http/Controllers/Api/PropertyMarketAnalysisController.php`, `frontend/src/pages/AnaliseMercadoOportunidade.tsx`, `tests/Feature/SchemaBoundValidationTest.php` e `meta/schema-bound-validation-checks.json`. Rotas `POST /api/propriedades`, `POST /api/opportunities` e `POST /api/property-market-analysis`.

Causa e impacto: `propriedades.area_m2` e `opportunities.area_m2` são `DECIMAL(10,2)`, cujo maior valor é 99.999.999,99, mas os requests permitiam até 999.999.999.999. SQLite aceitou 100.000.000 e respondeu 201 durante a reprodução; MySQL/MariaDB estrito pode rejeitar ou truncar o mesmo dado, tornando o comportamento dependente do banco. A análise de mercado não tinha máximos para área, preço ou quantidades e aceitava chaves desconhecidas no objeto `selected`, permitindo cálculos desnecessários com valores fora do domínio e um contrato maior que o necessário.

Como reproduzir antes da correção: autenticar uma conta ativa/verificada e enviar `POST /api/propriedades` com `apelido` e `area_m2: 100000000`; o teste recebeu 201, embora o valor exceda a coluna. Enviar `POST /api/property-market-analysis` com área 100.000.000, 101 quartos/banheiros/vagas, preço 1.000.000.000.000 e uma chave desconhecida; a resposta anterior foi 200. Esperado: 422 antes de persistir ou iniciar provedores/cálculos.

Correção: os dois requests persistentes limitam área ao máximo de `DECIMAL(10,2)`. A análise aceita apenas os onze campos necessários, limita área ao mesmo teto, cômodos/vagas a 100 e preço a 999.999.999.999; UF exige dois caracteres. A tela de análise monta explicitamente esse payload reduzido para manter o fluxo legítimo compatível com o contrato estrito.

Verificação: a regressão confirma 422 para propriedade e oportunidade, ausência de registros no banco e 422 para todos os campos fora do domínio e a chave desconhecida. O comportamento anterior falhou com 201/200; após a correção passaram 2 testes/16 asserções, os 26 testes relacionados/159 asserções, a suíte completa 222/1.427, Pint e o build TypeScript/Vite com 2.308 módulos.

Como verificar: repetir os dois payloads acima e confirmar 422 com erros em `area_m2` e `selected.*`; consultar as tabelas e confirmar que nenhuma linha foi criada. Repetir a análise normal pela IA e confirmar que a chamada envia somente título, endereço, localização, área, cômodos, preço e tipo e continua carregando as fontes.

## BUG 89 — Atualização de projeto respondia sucesso enquanto descartava etapas

Categoria: Funcional/Lógica/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/ProjetoRequest.php`, `app/Services/ProjetoService.php`, `tests/Feature/ManualProjectTest.php` e `meta/project-update-contract-checks.json`. Rota `PUT|PATCH /api/projetos/{id}`.

Causa e impacto: `ProjetoRequest` validava `etapas` e `orcamento_categorias` em qualquer método. Na atualização, o serviço executava `unset` nesses campos e atualizava somente os valores simples. Um cliente recebia 200 e podia acreditar que nomes, datas ou orçamentos de etapas tinham sido alterados, enquanto o banco mantinha a coleção anterior. Na reprodução, nome/orçamento do projeto foram alterados parcialmente e a etapa enviada foi ignorada.

Como reproduzir antes da correção: criar projeto com etapas padrão e enviar PUT contendo os campos obrigatórios, novo nome/orçamento, uma lista `etapas` com valores diferentes e `orcamento_categorias`. A resposta anterior foi 200. Recarregar o projeto: campos simples mudaram, mas a etapa permaneceu intacta. Esperado: operação atômica e explícita, sem sucesso parcial silencioso.

Correção: em PUT/PATCH, `etapas` e `orcamento_categorias` são agora proibidos pelo request e geram 422 antes do serviço. Criação continua aceitando as estruturas aninhadas. Atualizações de etapa continuam nos endpoints `PATCH /api/projetos-etapas/{id}` e `POST /api/projetos/{id}/etapas`, cujos campos possuem semântica e autorização próprias. Não foi implementada substituição destrutiva de coleção sem IDs.

Verificação: regressão envia nome/orçamento simples e ambos os campos aninhados, exige erros em `etapas` e `orcamento_categorias` e confirma no banco que projeto e etapa não mudaram. `ManualProjectTest`: 3 testes/24 asserções; conjunto relacionado: 25/209; suíte completa: 223/1.434; Pint aprovado.

Como verificar: repetir o PUT e confirmar 422; consultar projeto/etapa e confirmar valores originais. Depois atualizar orçamento/status de uma etapa pelo endpoint específico e confirmar sucesso, demonstrando que a funcionalidade suportada permanece disponível.

## BUG 90 — Etapas e vendas aceitavam cronologia invertida

Categoria: Lógica/Validação/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/ProjetoRequest.php`, `app/Http/Requests/VendaRequest.php`, `tests/Feature/TemporalValidationTest.php` e `meta/temporal-validation-checks.json`. Rotas `POST /api/projetos` e `POST|PUT|PATCH /api/vendas/{venda?}`.

Causa e impacto: datas individuais eram validadas como datas, mas a etapa não comparava término com início e a venda não relacionava proposta, fechamento e escritura. Isso permitia cronogramas impossíveis e afetava ordenação, status e relatórios. Uma regra dependente apenas do payload também seria insuficiente em atualização parcial, pois o cliente pode enviar uma nova escritura sem repetir proposta/fechamento já armazenados.

Como reproduzir antes da correção: criar projeto com etapa iniciando em 10/11/2026 e terminando em 09/11/2026; a API respondeu 201. Criar venda com proposta em 10/11, fechamento em 09/11 e escritura em 08/11; também respondeu 201. Criar venda válida e atualizar somente a escritura para 09/11; a API respondeu 200 apesar de proposta/fechamento persistidos em 10/11 e 11/11.

Correção: término de cada etapa exige data igual ou posterior ao início da mesma posição do array. `VendaRequest::after` constrói a linha do tempo efetiva a partir do request e, quando um campo não veio, da venda existente isolada pelo tenant; fechamento não pode preceder proposta e escritura não pode preceder fechamento ou, na ausência dele, proposta. Erros de formato interrompem a comparação e continuam tratados pelas regras básicas.

Verificação: três testes cobrem criação de etapa invertida, criação de venda invertida, bypass por atualização parcial e atualização parcial válida. Projetos/etapas/vendas inválidos não são persistidos ou alterados. Testes específicos e relacionados: 18/146; suíte completa: 226/1.450; Pint aprovado.

Como verificar: repetir as três requisições inválidas e confirmar 422 nos campos de término/fechamento/escritura; confirmar o banco inalterado. Atualizar escritura de 12/11 para 13/11 sem reenviar proposta/fechamento e confirmar 200.

## BUG 91 — Um imóvel podia manter várias vendas finais ativas

Categoria: Lógica/Integridade financeira/Concorrência. Severidade: Alta. Status: corrigido e testado localmente. Arquivos `app/Services/VendaService.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/final-sale-invariant-checks.json`. Rotas `POST /api/vendas` e `PUT|PATCH /api/vendas/{venda}`.

Causa e impacto: não havia invariante que limitasse vendas `fechada/escriturada` por imóvel. A reprodução criou duas vendas fechadas com 201. O dashboard deduplicava por `propriedade_id`, o ROI escolhia apenas a mais recente e a coleção devolvia ambas; receita, status e resultado dependiam do consumidor. Atualizar duas propostas para estados finais produzia a mesma ambiguidade. O teste anterior inclusive preservava duas vendas finais como comportamento esperado.

Como reproduzir antes da correção: criar imóvel e enviar duas vezes `POST /api/vendas` com o mesmo `propriedade_id`, valor e `status=fechada`; ambas retornavam 201. Alternativamente, criar duas propostas e promover ambas por PUT; as duas atualizações retornavam 200. Comparar lista, dashboard e ROI para observar critérios diferentes de seleção/deduplicação.

Correção: múltiplas propostas/negociações permanecem permitidas. Ao entrar em `fechada/escriturada`, o serviço abre transação, bloqueia todas as propriedades envolvidas por ID em ordem estável e busca outra venda final ativa, excluindo a própria na atualização. Se existir, retorna 422 em `status` antes da escrita. Movimentação de venda bloqueia imóvel antigo e novo e sincroniza ambos depois da alteração.

Verificação: teste confirma que a segunda venda final é recusada, duas propostas são aceitas, somente a primeira pode ser finalizada e a segunda permanece `proposta`; movimentar a única venda final atualiza corretamente os dois imóveis. Conjunto relacionado: 16 testes/142 asserções; suíte completa: 227/1.459; Pint aprovado. O lock foi revisado e exercitado transacionalmente, mas ainda não houve dois processos simultâneos MariaDB para este novo cenário; essa limitação permanece explícita.

Como verificar: criar duas propostas no mesmo imóvel; finalizar a primeira e confirmar status `vendida`; tentar finalizar a segunda e confirmar 422/registro ainda `proposta`. Cancelar/excluir a primeira e repetir para confirmar que uma nova venda pode ser finalizada legitimamente.

## BUG 92 — Resumo do projeto tratava proposta ou cancelamento como venda realizada

Categoria: Lógica/Integridade financeira. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Services/ProjetoService.php`, `frontend/src/pages/AnaliseImovel.tsx`, `tests/Feature/DomainIntegrityTest.php` e `meta/project-sale-summary-checks.json`. Rota `GET /api/projetos/{id}/resumo`.

Causa e impacto: `roiProjetado` buscava a primeira venda ativa do imóvel sem filtrar seu estado. `deletado_flg=false` inclui proposta, negociação e cancelamento. Dashboard e `VendaService::roiPropriedade` usavam somente `fechada/escriturada`, portanto a mesma conta via números diferentes conforme a tela. Uma proposta não aceita ou uma venda cancelada de valor alto podia inflar `valor_liquido_venda`, lucro e ROI no módulo Obras.

Como reproduzir antes da correção: criar imóvel de R$ 100 mil e projeto; registrar proposta de R$ 160 mil e venda cancelada de R$ 999.999. Consultar `/api/projetos/{id}/resumo`. Antes, `roi.tem_venda` era `true` e uma venda não realizada alimentava o resultado. Esperado: sem venda final, `tem_venda=false` e receita zero.

Correção: a consulta do resumo agora exige status `fechada` ou `escriturada` e desempata por data de fechamento e ID. O limite visual de área no formulário de imóvel também foi alinhado ao teto corrigido no BUG 88 para evitar erro previsível no envio.

Verificação: regressão comprova que proposta e cancelamento resultam em `tem_venda=false`/receita zero. Depois promove a proposta a `fechada` e confirma `tem_venda=true`/R$ 160.000. Testes relacionados: 13/123; suíte completa: 228/1.468; Pint e build TypeScript/Vite com 2.308 módulos aprovados.

Como verificar: repetir o cenário, consultar o resumo antes e depois de finalizar a proposta e comparar também dashboard/ROI da propriedade; os três devem ignorar estados não finais e usar a única venda final ativa.

## BUG 93 — Venda final podia existir sem data de fechamento ou escritura

Categoria: Lógica/Validação/Integridade financeira. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Http/Requests/VendaRequest.php`, `tests/Feature/TemporalValidationTest.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/final-sale-milestone-checks.json`. Rotas `POST /api/vendas` e `PUT|PATCH /api/vendas/{venda}`.

Causa e impacto: o request comparava a ordem das datas quando elas existiam, mas não relacionava sua presença ao estado final. Assim, `status=fechada` sem fechamento e `status=escriturada` sem escritura eram aceitos. O dashboard somava a venda nos KPIs realizados, enquanto a série mensal a descartava por não haver data de fechamento, produzindo resultados incompatíveis.

Como reproduzir antes da correção: autenticar conta ativa/verificada, criar imóvel e enviar `POST /api/vendas` com `propriedade_id`, `valor_venda` e `status=fechada`, omitindo `data_fechamento`. A resposta observada foi 201. Repetir com `status=escriturada`, fechamento presente e escritura ausente; também não havia regra impeditiva. Esperado: 422 no marco ausente e nenhuma linha criada.

Correção: a validação posterior determina status e datas efetivos, usando os campos recebidos ou a venda persistida em atualização. Estados `fechada` e `escriturada` exigem `data_fechamento`; `escriturada` exige também `data_escritura`. As comparações cronológicas do BUG 90 permanecem ativas.

Verificação: regressão exige 422 nas duas criações e na promoção de uma venda fechada para escriturada sem escritura, confirma banco vazio após criações inválidas e aceita venda fechada e promoção escriturada quando os marcos existem. Testes relacionados: 17/151; suíte completa: 229/1.480; Pint global aprovado.

Como verificar: enviar os dois payloads incompletos e confirmar 422 em `data_fechamento` ou `data_escritura`. Criar venda fechada com data, promover para escriturada primeiro sem e depois com a escritura; confirmar 422 seguido de 200 e registro coerente.

## BUG 94 — Etapa excluída reaparecia no detalhe e resumo do projeto

Categoria: Lógica/Integridade de dados. Severidade: Média. Status: corrigido e testado. Arquivos `app/Models/Projeto.php`, `app/Services/ProjetoService.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/deleted-project-stage-checks.json`. Rotas `GET /api/projetos/{id}` e `GET /api/projetos/{id}/resumo`.

Causa e impacto: a relação `Projeto::etapas()` ordenava as etapas, mas não aplicava `deletado_flg=false`. Uma etapa arquivada podia reaparecer nas respostas e o recálculo percorria também registros excluídos, divergindo do endpoint individual e da soma de orçamento, que já filtravam ativos. Interfaces poderiam exibir trabalho removido e valores obsoletos.

Como reproduzir antes da correção: criar projeto, inserir no banco uma etapa com `deletado_flg=true` e criar outra ativa pela API. Consultar detalhe e resumo. O detalhe observado continha duas etapas, embora apenas uma estivesse ativa. Esperado: somente a etapa ativa em ambas as respostas.

Correção: a relação do projeto filtra `projetos_etapas.deletado_flg=false` antes de ordenar. `recalcularCustos` também usa `ProjetoEtapa::ativos()`, impedindo mutação de registros arquivados. Operações que dependem da relação herdam o mesmo critério.

Verificação: regressão confirma contagem um e o ID da etapa ativa tanto no detalhe quanto no resumo; orçamento e rollback existentes continuam cobertos. Testes relacionados 16/152, suíte completa 229/1.486 e Pint global passaram.

Como verificar: repetir a fixture com uma etapa excluída e uma ativa; consultar as duas rotas e confirmar que só a ativa aparece. Alterar seu orçamento e confirmar que total e rollback continuam corretos.

## BUG 95 — Projeto concluído descartava a data de término real

Categoria: Funcional/Lógica/Integridade temporal. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/ProjetoRequest.php`, `app/Services/ProjetoService.php`, `tests/Feature/TemporalValidationTest.php` e `meta/project-completion-date-checks.json`. Rotas `POST /api/projetos` e `PUT|PATCH /api/projetos/{projeto}`.

Causa e impacto: o resource devolvia `data_termino_real` e o model aceitava o campo, mas `ProjetoRequest` não o validava nem o repassava. Alterar o status para `concluido` não definia nenhum marco. Relatórios e histórico não conseguiam determinar quando o projeto terminou, e uma data enviada pelo cliente era silenciosamente ignorada.

Como reproduzir antes da correção: criar projeto iniciado em 01/10/2026 e enviar PUT com os campos obrigatórios, `status=concluido` e `data_termino_real=17/10/2026`. A resposta observada foi 200 com `data.data_termino_real=null`. Esperado: persistir a data validada e manter status/data coerentes.

Correção: o request aceita data real, impede data informada fora do estado concluído e impede término anterior ao início efetivo. O serviço normaliza criação e atualização: usa a data informada, registra a data corrente na primeira conclusão sem data, preserva o marco em edições posteriores e o remove se o projeto for reaberto.

Verificação: regressão conclui com 17/10/2026 e confirma a resposta/persistência; depois muda para `em_andamento` e confirma a limpeza. Estados não concluídos com data e término anterior ao início retornam 422. Testes relacionados 20/181, suíte completa 230/1.497 e Pint global passaram.

Como verificar: repetir o PUT e confirmar a data. Reabrir o projeto e confirmar `data_termino_real=null`. Tentar informar término anterior ao início ou data real em estado não concluído e confirmar 422.

## BUG 96 — Editar tarefa concluída regravava sua data de conclusão

Categoria: Lógica/Integridade temporal. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/TarefaService.php`, `tests/Feature/TarefaTest.php` e `meta/task-completion-date-checks.json`. Rota `PUT|PATCH /api/tarefas/{tarefa}`.

Causa e impacto: `normalizeCompletion` recebia somente o payload. Para qualquer atualização com `status=concluida` e data omitida ou nula, aplicava `now()`, sem consultar o registro. Clientes que editassem título, descrição, responsável ou custos de uma tarefa concluída poderiam falsificar involuntariamente o histórico de conclusão.

Como reproduzir antes da correção: concluir tarefa com `data_conclusao=2026-10-10`. Enviar nova atualização com todos os campos obrigatórios e `status=concluida`, alterando o título, mas omitindo `data_conclusao`. Em 17/10/2026, a resposta observada mudou a data para 17/10. Esperado: preservar 10/10.

Correção: a atualização passa a tarefa persistida para a normalização. Se ela já estava concluída e possui data, o valor é preservado; primeira conclusão sem data usa o dia corrente; data explícita continua aceita; estados não concluídos limpam o campo.

Verificação: regressão conclui em 10/10, edita o título sem o campo e confirma título novo com data ainda em 10/10; depois reabre e confirma data nula. Testes relacionados 16/179, suíte completa 230/1.500 e Pint global passaram.

Como verificar: repetir as três atualizações e conferir o JSON/banco depois de cada uma. A edição intermediária não pode alterar o marco histórico.

## BUG 97 — Despesa paga podia ficar sem data de pagamento

Categoria: Lógica/Integridade financeira. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/DespesaService.php`, `tests/Feature/ExpenseUpdateWorkflowTest.php` e `meta/expense-payment-date-checks.json`. Rotas `POST /api/despesas` e `PUT|PATCH /api/despesas/{despesa}`.

Causa e impacto: `data_pagamento` era apenas opcional e o serviço não relacionava o campo ao status. A própria tela de projeto cria despesas com `status_pagamento=pago` sem enviar uma data. O banco recebia pagamento sem marco contábil; edições e cancelamentos também podiam manter combinações inconsistentes.

Como reproduzir antes da correção: criar categoria e enviar POST com descrição, valor, data da despesa e `status_pagamento=pago`, omitindo `data_pagamento`. A resposta observada foi 201 com `data.data_pagamento=null`. Esperado: um pagamento confirmado precisa de data consistente.

Correção: a normalização do serviço usa estado e registro persistido. Primeiro pagamento sem data recebe o dia atual; data explícita é respeitada; edição posterior de item pago preserva o marco; `pendente`, `atrasado` ou `cancelado` limpam a data.

Verificação: regressão cria o pagamento sem data e confirma o dia atual, troca explicitamente para 16/10/2026, edita a descrição omitindo a data e confirma preservação, depois cancela enviando inclusive a data antiga e confirma `null`. Testes relacionados 15/148, suíte completa 231/1.509 e Pint global passaram.

Como verificar: repetir as quatro operações e conferir resposta/banco. O status e a data devem permanecer sincronizados em cada etapa.

## BUG 98 — Custo detalhado pago podia permanecer sem `paid_at`

Categoria: Lógica/Integridade financeira. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/InvestmentCostRequest.php`, `tests/Feature/FinancialValidationTest.php` e `meta/investment-cost-payment-date-checks.json`. Rotas `POST /api/investment-analyses/{id}/costs` e `PUT /api/investment-costs/{id}`.

Causa e impacto: o request validava formato e enum isoladamente, sem relacionar `payment_status` e `paid_at`. Central e wizard podem marcar custos reais como pagos sem enviar o marco. Isso produzia dados financeiros sem data, e uma edição posterior podia manter ou apagar datas sem uma política definida.

Como reproduzir antes da correção: criar análise e enviar custo com categoria, descrição, valor e `payment_status=paid`, omitindo `paid_at`. A resposta era 201 e o registro observado tinha `paid_at=null`. Esperado: uma quitação deve possuir marco temporal.

Correção: `prepareForValidation` obtém o custo vinculado na atualização, determina o estado efetivo e normaliza o campo. Primeira quitação usa o dia atual, data explícita é aceita, edição paga preserva o valor persistido e estados não pagos forçam `null`.

Verificação: regressão cria pago sem data e confirma o dia atual; define 16/10/2026; edita sem o campo e confirma preservação; volta para pendente reenviando a data e confirma limpeza. Testes relacionados 16/127, suíte completa 232/1.518 e Pint global passaram.

Como verificar: executar a sequência pelas duas rotas e consultar o custo pelo detalhe da análise ou banco após cada passo.

## BUG 99 — Atualização parcial podia inverter as datas planejadas do projeto

Categoria: Lógica/Validação/Integridade temporal. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/ProjetoRequest.php`, `tests/Feature/TemporalValidationTest.php` e `meta/project-partial-date-checks.json`. Rota `PUT|PATCH /api/projetos/{projeto}`.

Causa e impacto: a regra declarativa `after_or_equal:data_inicio` enxerga apenas o request atual. Se o cliente enviasse somente uma data, não havia comparação com a outra já armazenada. Cronogramas previamente válidos podiam se tornar invertidos por uma atualização parcial, afetando calendário, prazo e relatórios.

Como reproduzir antes da correção: criar projeto com início 10/11/2026 e término previsto 20/11/2026. Enviar PUT com os campos obrigatórios e apenas `data_termino_prevista=09/11/2026`. A resposta observada foi 200. O caminho inverso consistia em enviar apenas `data_inicio=21/11/2026`. Esperado: 422 e datas originais preservadas.

Correção: a validação posterior usa `exists` para escolher valor recebido ou persistido em cada campo. Se o término efetivo preceder o início efetivo, adiciona erro em `data_termino_prevista`; a regra original permanece para payloads completos.

Verificação: regressão cobre ambos os payloads parciais, exige 422 e confirma início 10/11 e término 20/11 no banco. Testes relacionados 21/190, suíte completa 233/1.527 e Pint global passaram.

Como verificar: repetir as duas atualizações separadamente e recarregar o projeto; nenhuma deve alterar o cronograma.

## BUG 100 — Status vendido podia divergir da venda final ativa

Categoria: Lógica/Integridade financeira/Concorrência. Severidade: Alta. Status: corrigido e testado localmente. Arquivos `app/Services/PropriedadeService.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/property-sale-status-checks.json`. Rotas `POST /api/propriedades` e `PUT|PATCH /api/propriedades/{propriedade}`.

Causa e impacto: `vendida` era editável como qualquer outro status, embora o serviço de vendas também o altere automaticamente. Era possível declarar um imóvel vendido sem receita, ou manter uma venda fechada enquanto a listagem mostrava o imóvel à venda. Dashboard seleciona vendas finais e outras telas usam o status da propriedade, gerando resultados contraditórios.

Como reproduzir antes da correção: enviar POST de imóvel com `status=vendida`; a resposta observada foi 201 sem qualquer venda. Em outro imóvel, criar venda fechada e depois enviar PUT do imóvel com `status=a_venda`; a API aceitava a divergência. Esperado: 422 em ambos.

Correção: criação rejeita `vendida`, pois nenhuma venda pode existir antes do imóvel. Na atualização, o serviço abre transação, bloqueia a linha do imóvel e verifica venda final ativa; o status solicitado deve corresponder exatamente ao resultado. O lock é compatível com a ordem usada por `VendaService`, fechando a janela entre validação e escrita.

Verificação: regressão cobre criação vendida sem venda, rebaixamento com venda, marcação vendida sem venda, preservação do banco e edição legítima de nome mantendo `vendida`. Testes relacionados 20/196, suíte completa 234/1.543 e Pint global passaram. A disputa multiprocesso MariaDB do fluxo de venda continua como limitação operacional já registrada.

Como verificar: repetir os três payloads inválidos e confirmar 422 em `status`; criar venda final pelo endpoint e editar outro campo mantendo `vendida`, confirmando 200.

## BUG 101 — Status e progresso de etapa podiam divergir

Categoria: Lógica/Integridade de dados. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Controllers/Api/ProjetoController.php`, `app/Services/ProjetoService.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/stage-progress-invariant-checks.json`. Rotas `POST /api/projetos`, `POST /api/projetos/{projeto}/etapas` e `PATCH /api/projetos-etapas/{etapa}`.

Causa e impacto: status e percentual eram validados isoladamente. Uma etapa podia ser criada como `concluida` com 0%, ou atualizada para `em_andamento` com 100%. Resumos e barras de progresso exibiam estados incompatíveis; a criação aninhada de projeto repetia o problema por um caminho diferente.

Como reproduzir antes da correção: criar uma etapa com `status=concluida` e sem percentual. A resposta observada foi 201, `status=concluida` e `progresso_pct=0`. Também era possível enviar `status=bloqueada` e `progresso_pct=100`. Esperado: estado final equivale a 100%, pendente a 0%, e estados intermediários não podem ter 100%.

Correção: a invariante foi centralizada em `ProjetoService` e aplicada aos três caminhos. Atualizações explícitas contraditórias retornam 422; atualização só de status ajusta o percentual; atualização só do percentual ajusta o status; etapas concluídas criadas diretamente ou dentro do projeto recebem 100%.

Verificação: a regressão reproduziu primeiro o 0% observado, depois cobriu criação direta, criação aninhada, conclusão, reabertura, atualização somente do percentual e dois pares inválidos. Testes relacionados 17/176, suíte completa 235/1.561 e Pint passaram.

Como verificar: criar etapa concluída sem percentual e confirmar 100%; alterá-la apenas para `em_andamento` e confirmar 0%; enviar apenas 100% e confirmar `concluida`; enviar `concluida/50` ou `bloqueada/100` e confirmar 422 sem alteração.

## BUG 102 — Excluir projeto deixava despesas e documentos ativos órfãos

Categoria: Lógica/Integridade financeira e documental. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Exceptions/ProjectInUseException.php`, `app/Http/Controllers/Api/ProjetoController.php`, `app/Services/ProjetoService.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/project-protected-history-checks.json`. Rota `DELETE /api/projetos/{projeto}`.

Causa e impacto: a exclusão arquivava tarefas, etapas e eventos, mas ignorava despesas e documentos ativos. O endpoint respondia 200 e escondia o projeto enquanto registros financeiros e arquivos continuavam ativos e vinculados a ele, deixando histórico inconsistente nas demais telas.

Como reproduzir antes da correção: criar projeto e despesa vinculada, ou projeto e documento vinculado; enviar DELETE do projeto. O resultado observado foi 200 e o projeto ficou com `deletado_flg=true`, enquanto a dependência permaneceu ativa. Esperado: impedir a exclusão até que o histórico protegido seja tratado explicitamente.

Correção: sob o lock transacional já existente, o serviço verifica despesas e documentos não excluídos. Se algum existir, lança conflito de domínio; o controller responde 409 com mensagem estável e nenhuma alteração. Depois de arquivar os vínculos, o fluxo existente pode excluir o projeto e seus dependentes operacionais.

Verificação: regressão cobre separadamente despesa e documento, exige 409 e projeto intacto, depois arquiva cada vínculo e confirma os dois DELETEs legítimos. Os testes de cascata operacional e rollback continuam passando. Foco 3/21, suíte completa 236/1.569 e Pint passaram. A corrida entre criação concorrente de dependência e exclusão ainda exige prova multiprocesso em banco transacional.

Como verificar: criar um projeto com despesa ativa e tentar excluí-lo; repetir com documento ativo. Ambos devem retornar 409. Arquivar o vínculo e repetir; deve retornar 200.

## BUG 103 — Validação anterior ao lock permitia vínculo com pai excluído em corrida

Categoria: Concorrência/Integridade referencial. Severidade: Alta. Status: corrigido no código e testado sequencialmente; prova multiprocesso pendente. Arquivos `app/Services/ProjetoService.php`, `app/Services/DespesaService.php`, `app/Services/DocumentoService.php`, `tests/Feature/DomainIntegrityTest.php`, `tests/Feature/DocumentoTest.php` e `meta/parent-lock-revalidation-checks.json`. Fluxos de criação/edição de projeto, despesa e documento.

Causa e impacto: os FormRequests verificavam que o imóvel/projeto estava ativo antes de entrar no serviço, mas a escrita não repetia a verificação sob o mesmo lock usado pela exclusão. Entre validação e INSERT/UPDATE, outra transação poderia excluir o pai e deixar um novo registro ativo ligado a ele. No upload, o arquivo já poderia ter sido gravado.

Como reproduzir conceitualmente antes da correção: requisição A valida projeto ativo; requisição B exclui e confirma o projeto; A prossegue e cria despesa ou documento usando o ID já oculto. A reprodução determinística de serviço confirmou que, sem a revalidação, a escrita aceitava o pai excluído. A disputa real em dois processos exige MariaDB/PostgreSQL disponível.

Correção: criação de projeto bloqueia e revalida o imóvel; despesas bloqueiam imóvel/projeto antigos e novos em ordem estável e revalidam os destinos; documentos fazem o mesmo para projetos em transação. O arquivo é preparado antes do lock e removido se a revalidação/banco falhar, reduzindo o tempo do lock sem deixar órfão no storage.

Verificação: dois testes novos simulam o estado alterado após a validação: projeto/despesa recebem `ModelNotFoundException` e nada é criado; upload ligado a projeto excluído remove o arquivo e não cria documento. Relacionados 31/283, suíte completa 238/1.573 e Pint passaram. Ainda não se afirma comprovação multiprocesso do lock.

Como verificar: em teste controlado, validar o payload, marcar o pai como excluído antes de chamar o serviço e confirmar ausência da entidade. Para prova operacional, executar criação e exclusão simultâneas contra o mesmo pai em banco transacional e confirmar que apenas uma ordem válida prevalece.

## BUG 104 — Tarefa ou evento podia nascer depois da exclusão concorrente do projeto

Categoria: Concorrência/Integridade operacional. Severidade: Alta. Status: corrigido no código e testado sequencialmente; prova multiprocesso pendente. Arquivos `app/Services/TarefaService.php`, `app/Services/EventoCalendarioService.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/operational-parent-lock-checks.json`.

Causa e impacto: o projeto arquiva tarefas e eventos existentes ao ser excluído, mas os serviços de criação/edição não participavam da transação nem bloqueavam o projeto. Uma requisição validada antes da exclusão podia gravar depois da limpeza, criando dependente ativo de projeto oculto.

Como reproduzir conceitualmente antes da correção: A valida projeto ativo; B exclui o projeto e arquiva dependentes; A executa `Tarefa::create` ou `EventoCalendario::create`. Como não havia revalidação no serviço, o novo registro permanecia ativo. A simulação determinística usa um projeto excluído entre validação e serviço.

Correção: ambos os serviços agora executam criação e atualização em transação, bloqueiam projetos antigos/novos em ordem de ID e revalidam o destino ativo depois do lock. A ordem coincide com a exclusão do projeto, serializando as operações no banco compatível.

Verificação: regressão chama os dois serviços com o estado alterado após a validação; ambos lançam `ModelNotFoundException` e as tabelas continuam sem o título de teste. O teste de exclusão/cascata existente permanece aprovado. Foco 2/10, suíte completa 239/1.575 e Pint passaram. A disputa real ainda depende de banco transacional multiprocesso.

Como verificar: validar uma tarefa/evento, excluir o projeto antes da chamada ao serviço e confirmar ausência do registro. Depois executar duas requisições simultâneas em MariaDB/PostgreSQL e confirmar que a criação vence antes da exclusão (que a arquiva) ou falha depois dela.

## BUG 105 — Projeto podia ser movido para imóvel excluído entre validação e atualização

Categoria: Concorrência/Integridade referencial. Severidade: Alta. Status: corrigido no código e testado sequencialmente; prova multiprocesso pendente. Arquivos `app/Services/ProjetoService.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/project-property-lock-checks.json`. Rota `PUT|PATCH /api/projetos/{projeto}`.

Causa e impacto: a criação de projeto já revalidava o imóvel sob lock, mas a atualização continuava fora de transação. Ao trocar `propriedade_id`, o destino podia ser excluído após o FormRequest e antes do UPDATE, deixando projeto ativo vinculado a imóvel oculto. A própria exclusão concorrente do projeto também podia intercalar-se com a edição.

Como reproduzir conceitualmente antes da correção: validar atualização de A para B enquanto B está ativo; excluir B; deixar a atualização prosseguir. O serviço antigo executava apenas `$projeto->update($dados)` e aceitava o ID. A reprodução determinística marca B como excluído antes da chamada do serviço.

Correção: a atualização ocorre em transação; origem e destino são bloqueados por ID em ordem estável, o destino é revalidado como ativo, e o projeto é reconsultado/bloqueado antes da normalização e escrita. Qualquer falha desfaz toda a operação.

Verificação: a regressão tenta mover para destino excluído, recebe `ModelNotFoundException` e confirma que o projeto ainda aponta para a origem. Testes relacionados 26/237, suíte completa 239/1.576 e Pint passaram. A disputa simultânea real continua dependente de banco transacional externo.

Como verificar: após validar o payload, excluir o destino e chamar o serviço; confirmar erro e vínculo original. Para prova operacional, competir PUT e DELETE do imóvel destino em processos separados.

## BUG 106 — Textos livres sem limite podiam exceder o contrato do banco

Categoria: Validação/Disponibilidade/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/ProjetoRequest.php`, `app/Http/Requests/DespesaRequest.php`, `app/Http/Requests/VendaRequest.php`, `app/Http/Requests/StoreOpportunityRequest.php`, `tests/Feature/SchemaBoundValidationTest.php` e `meta/free-text-bound-checks.json`.

Causa e impacto: descrição de projeto/etapa, observações de despesa/venda e descrição/notas de oportunidade aceitavam strings sem teto. SQLite armazena textos grandes e escondia o problema; em MySQL, colunas `TEXT` possuem limite e podem falhar ou truncar conforme configuração. O payload também podia consumir memória desnecessária antes da persistência.

Como reproduzir antes da correção: enviar 5.001 caracteres em qualquer um dos seis campos com os demais dados válidos. A validação não apontava o campo; o request alcançava a camada de persistência. Esperado: rejeição previsível antes de qualquer INSERT/UPDATE.

Correção: todos os seis textos livres usam `string|max:5000`, alinhados ao teto já aplicado a descrições de tarefas, eventos, documentos, fornecedores e propriedades.

Verificação: regressão envia o mesmo valor de 5.001 caracteres aos quatro endpoints, exige erros nos seis caminhos e confirma zero projetos, despesas, vendas e oportunidades. Teste específico 3/34, suíte completa 240/1.594 e Pint passaram.

Como verificar: repetir com 5.001 caracteres e confirmar 422 no campo correspondente; repetir com até 5.000 caracteres e os demais dados válidos para confirmar o fluxo legítimo.

## BUG 107 — Ordem de etapa e preço de busca não tinham teto numérico

Categoria: Validação/Compatibilidade/Disponibilidade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/ProjetoRequest.php`, `app/Http/Controllers/Api/AIPropertySearchController.php`, `tests/Feature/SchemaBoundValidationTest.php` e `meta/numeric-capacity-checks.json`.

Causa e impacto: `etapas.*.ordem` aceitava qualquer inteiro embora a coluna seja `INTEGER`; SQLite mascarava valores acima de 32 bits que podem falhar em MySQL. `max_price` da busca externa tinha apenas mínimo e aceitava números sem limite, propagando entrada sem sentido aos provedores/cálculos.

Como reproduzir antes da correção: criar projeto com etapa de ordem 2.147.483.648 ou consultar `/api/ai-property-search?max_price=1000000000000`. Ambos passavam pela validação. Esperado: 422 antes de banco ou integração externa.

Correção: ordem foi limitada ao intervalo 0–2.147.483.647 da coluna; preço máximo adotou o teto monetário de 999.999.999.999 usado no restante do domínio.

Verificação: regressão exige erro nos dois campos, confirma zero projetos/etapas e evita disparar a busca. Teste específico 4/42, suíte completa 241/1.602 e Pint passaram.

Como verificar: repetir os dois valores acima do teto e confirmar 422; usar os limites exatos para confirmar que a validação de capacidade os aceita quando o restante do payload é válido.

## BUG 108 — Página extrema era aceita e gerava OFFSET dependente do driver

Categoria: Validação/Performance/Disponibilidade. Severidade: Baixa. Status: corrigido e testado. Arquivos `app/Http/Middleware/ValidatePagination.php`, `tests/Feature/CollectionFilterValidationTest.php` e `meta/pagination-page-bound-checks.json`. Coleções sob `/api/*`.

Causa e impacto: `page` exigia apenas inteiro positivo. O valor máximo de 64 bits com `per_page=100` era aceito; a multiplicação do offset e sua conversão para SQL variam entre PHP/PDO/drivers, além de permitir consultas absurdamente distantes sem utilidade operacional.

Como reproduzir antes da correção: autenticar e chamar `GET /api/projetos?per_page=100&page=9223372036854775807`. O resultado observado no SQLite foi 200. Esperado: rejeitar antes de construir a consulta.

Correção: o middleware comum limita `page` ao intervalo 1–1.000.000, aplicado uniformemente antes dos controllers de coleção. `per_page` continua limitado a 100.

Verificação: a regressão exige 422 e erro `page`; projetos, propriedades e tarefas mantêm filtros/paginação legítimos. Relacionados 13/142, suíte completa 242/1.605 e Pint passaram.

Como verificar: repetir a URL extrema e confirmar 422; consultar página 1 com tamanho permitido e confirmar resposta normal.

## BUG 109 — PATCH de projeto exigia campos não alterados

Categoria: API/Funcional/Contrato HTTP. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/ProjetoRequest.php`, `tests/Feature/ManualProjectTest.php` e `meta/project-patch-contract-checks.json`. Rota `PATCH /api/projetos/{projeto}`.

Causa e impacto: a rota aceita PATCH, mas o mesmo request exigia sempre `propriedade_id` e `nome`. Um cliente que pretendesse alterar somente nome, descrição, status ou data recebia 422 e precisava reenviar todo o recurso, aumentando risco de sobrescrever dados concorrentes.

Como reproduzir antes da correção: criar projeto completo e enviar `PATCH` apenas com `{"nome":"Nome parcial"}`. A resposta observada foi 422: “The propriedade id field is required.” Esperado: 200, alterando somente o campo enviado.

Correção: campos estruturais permanecem obrigatórios em POST/PUT, mas usam `sometimes` em PATCH. As validações temporais continuam combinando payload e estado persistido, e estruturas aninhadas seguem proibidas na atualização.

Verificação: regressão altera somente nome e confirma preservação de descrição, propriedade e orçamento. Testes relacionados 10/78, suíte completa 243/1.611 e Pint passaram.

Como verificar: repetir o PATCH mínimo e recarregar o projeto; somente o nome deve mudar. Omitir `propriedade_id` em PUT continua retornando 422.

## BUG 110 — PATCH de venda exigia imóvel e valor não alterados

Categoria: API/Integridade financeira/Contrato HTTP. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/VendaRequest.php`, `tests/Feature/DomainIntegrityTest.php` e `meta/sale-patch-contract-checks.json`. Rota `PATCH /api/vendas/{venda}`.

Causa e impacto: embora a rota aceite PATCH e o serviço já preservasse campos omitidos, o request exigia sempre `propriedade_id` e `valor_venda`. Uma edição de observação ou contato precisava reenviar valores financeiros, criando risco desnecessário de sobrescrever uma alteração concorrente.

Como reproduzir antes da correção: criar venda em proposta e enviar `PATCH` somente com `observacoes`. A resposta observada foi 422 exigindo propriedade e valor. Esperado: alterar somente a observação.

Correção: os dois campos estruturais usam `sometimes` somente em PATCH; permanecem obrigatórios em POST e PUT. Datas/estado continuam avaliados com os valores persistidos, e o serviço conserva locks e invariantes de venda final.

Verificação: regressão confirma observação nova e preserva valor, imóvel, estado e comprador. Um PUT com o mesmo payload mínimo retorna 422 nos dois campos. Relacionados 24/224, suíte completa 244/1.622 e Pint passaram.

Como verificar: executar PATCH apenas com observação e consultar a venda; depois repetir por PUT e confirmar 422 sem alteração.

## BUG 111 — PATCH de imóvel exigia apelido não alterado

Categoria: API/Funcional/Contrato HTTP. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/PropriedadeRequest.php`, `tests/Feature/PropriedadeTest.php` e `meta/property-patch-contract-checks.json`. Rota `PATCH /api/propriedades/{propriedade}`.

Causa e impacto: o endpoint publicava PATCH, mas `apelido` era sempre obrigatório. Editar observações, valor, estado ou endereço exigia reenviar um campo não alterado, aumentando acoplamento e risco de sobrescrita por clientes com estado antigo.

Como reproduzir antes da correção: criar imóvel completo e enviar PATCH apenas com `observacoes`. A resposta observada foi 422 exigindo apelido. Esperado: alterar apenas a observação.

Correção: `apelido` usa `sometimes` em PATCH e permanece obrigatório em POST/PUT. O serviço já atualiza somente chaves validadas e conserva endereço/vínculo/status quando omitidos; a invariante de imóvel vendido continua aplicada somente se o status for enviado.

Verificação: regressão confirma observação nova e preserva apelido, valor de aquisição e cidade. PUT mínimo retorna 422 em apelido. Relacionados 25/236, suíte completa 245/1.631 e Pint passaram.

Como verificar: enviar PATCH somente com observação e recarregar o imóvel; depois enviar o mesmo corpo por PUT e confirmar rejeição sem mutação.

## BUG 112 — PATCH de despesa não era parcial e podia ignorar relação persistida

Categoria: API/Integridade financeira/Contrato HTTP. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Http/Requests/DespesaRequest.php`, `tests/Feature/ExpenseUpdateWorkflowTest.php` e `meta/expense-patch-contract-checks.json`. Rota `PATCH /api/despesas/{despesa}`.

Causa e impacto: PATCH exigia categoria, descrição, valor e data completos. Além disso, a checagem etapa/projeto só rodava quando os dois IDs vinham no mesmo payload. Ao aceitar uma futura parcialidade sem corrigir isso, trocar apenas `etapa_id` poderia associar despesa a etapa de outro projeto.

Como reproduzir antes da correção: enviar PATCH apenas com observações e receber 422 nos quatro campos obrigatórios. O desvio relacional é demonstrado enviando apenas uma etapa de outro projeto: a validação anterior não combinava o novo ID com `projeto_id` persistido.

Correção: os quatro campos são `sometimes` em PATCH e obrigatórios em POST/PUT. A validação carrega a despesa autorizada, calcula propriedade/projeto/etapa/tarefa efetivos com payload mais estado persistido e aplica todas as relações sobre esse conjunto.

Verificação: regressão altera apenas observação e preserva descrição, valor, projeto e etapa; etapa externa retorna 422 e o banco mantém a original; PUT mínimo retorna erros nos quatro campos. Relacionados 21/211, suíte completa 246/1.647 e Pint passaram.

Como verificar: executar as três operações da regressão e consultar o registro após cada uma; nenhuma rejeição pode alterar custos ou vínculos.

## BUG 113 — PATCH de tarefa não era parcial e podia ignorar projeto persistido

Categoria: API/Integridade operacional/Contrato HTTP. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Http/Requests/TarefaRequest.php`, `app/Services/TarefaService.php`, `tests/Feature/TarefaTest.php` e `meta/task-patch-contract-checks.json`. Rota `PATCH /api/tarefas/{tarefa}`.

Causa e impacto: PATCH exigia projeto, título, prioridade e status. A relação etapa/projeto só era comparada quando ambos vinham no payload, e o serviço acessava `status` diretamente. Uma implementação parcial ingênua permitiria etapa externa ou apagaria/alteraria o marco de conclusão.

Como reproduzir antes da correção: editar somente o título de tarefa concluída; a resposta observada foi 422 exigindo projeto, prioridade e status. Enviar apenas `etapa_id` não comparava o valor ao projeto persistido.

Correção: campos centrais usam `sometimes` em PATCH e permanecem obrigatórios em POST/PUT. O request combina projeto, etapa e status enviados com a tarefa persistida. O serviço determina o status efetivo, preservando `data_conclusao` de tarefa já concluída quando o campo não é reenviado.

Verificação: regressão altera só o título e preserva estado, data 10/10, projeto e etapa; etapa de outro projeto retorna 422 e não muda o banco; PUT mínimo exige projeto, prioridade e status. Teste relacionado 5/76, suíte completa 247/1.662 e Pint passaram.

Como verificar: concluir tarefa com data conhecida, aplicar PATCH de título, tentar PATCH de etapa externa e finalmente PUT incompleto; conferir resposta e banco em cada passo.

## BUG 114 — PATCH de evento não era parcial e permitia intervalo invertido por omissão

Categoria: API/Integridade temporal/Contrato HTTP. Severidade: Alta. Status: corrigido e testado. Arquivos `app/Http/Requests/EventoCalendarioRequest.php`, `tests/Feature/EventoCalendarioTest.php` e `meta/event-patch-contract-checks.json`. Rota `PATCH /api/eventos-calendario/{id}`.

Causa e impacto: PATCH exigia título, categoria e início. A regra `after_or_equal:inicio_em` comparava somente os campos presentes no payload; alterar apenas o fim ou apenas o início ignorava a outra data persistida e podia gravar um evento com fim anterior ao início.

Como reproduzir antes da correção: crie evento das 09:00 às 10:00; envie PATCH somente com título e receba 422. Em seguida, envie somente `fim_em=08:59:59` ou somente `inicio_em=10:00:01`; a validação anterior não comparava o intervalo efetivo.

Correção: os três campos centrais usam `sometimes` somente em PATCH e continuam obrigatórios em POST/PUT. A validação posterior combina payload e evento persistido e compara início/fim como valores de calendário no mesmo formato usado pela persistência.

Verificação: PATCH de título retorna 200 preservando categoria e intervalo; alterações isoladas que inverteriam o período retornam 422 sem mutação; PUT incompleto exige categoria e início. Teste relacionado 5/68, suíte completa 248/1.678 e Pint passaram.

Como verificar: repita as quatro requisições acima e consulte o evento após as rejeições; o intervalo deve continuar 09:00–10:00.
## BUG 115 — PATCH de documento exigia categoria e forçava reenvio de metadados

Categoria: API/Integridade de arquivos/Contrato HTTP. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/DocumentoRequest.php`, `tests/Feature/DocumentoTest.php` e `meta/document-patch-contract-checks.json`. Rota `PATCH /api/documentos/{id}`.

Causa e impacto: a rota aceitava PATCH, mas `categoria` permanecia obrigatória. Uma alteração isolada de descrição era rejeitada e clientes precisavam reenviar metadados já persistidos, ampliando o risco de sobrescrita acidental.

Como reproduzir antes da correção: envie um documento ligado a projeto e depois faça PATCH apenas com `descricao`. A resposta observada era 422 exigindo categoria.

Correção: `categoria` usa `sometimes` em PATCH e continua obrigatória em POST/PUT. Upload permanece proibido na atualização e o serviço conserva todas as chaves omitidas.

Verificação: PATCH de descrição retorna 200 e preserva categoria, projeto, status, caminho, nome, MIME, tamanho e arquivo físico; PUT sem categoria retorna 422. Teste relacionado 11/92, suíte completa 249/1.692 e Pint passaram.

Como verificar: envie um PDF fictício, registre os metadados, aplique PATCH apenas de descrição e compare resposta, banco e armazenamento; depois repita o corpo por PUT.
## BUG 116 — PATCH de fornecedor exigia nome e categoria completos

Categoria: API/Integridade cadastral/Contrato HTTP. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/FornecedorRequest.php`, `tests/Feature/FornecedorTest.php` e `meta/supplier-patch-contract-checks.json`. Rota `PATCH /api/fornecedores/{id}`.

Causa e impacto: a rota aceitava PATCH, mas nome e categoria continuavam obrigatórios. Alterar apenas avaliação ou contato retornava 422 e obrigava clientes a reenviar estado não alterado.

Como reproduzir antes da correção: crie fornecedor completo e envie PATCH somente com `avaliacao=3.5`; a resposta observada era 422 em nome e categoria.

Correção: nome e categoria usam `sometimes` em PATCH e permanecem obrigatórios em POST/PUT. O serviço atualiza somente as chaves validadas.

Verificação: PATCH isolado retorna 200 e preserva nome, categoria, contato, e-mail e estado ativo; PUT com o mesmo corpo retorna 422 em nome e categoria. Teste relacionado 5/47, suíte completa 250/1.703 e Pint passaram.

Como verificar: registre os campos do fornecedor, aplique PATCH apenas de avaliação, compare o registro e envie o mesmo corpo por PUT.
## BUG 117 — PATCH de oportunidade exigia os campos completos da criação

Categoria: API/Integridade cadastral e de mídia/Contrato HTTP. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Requests/UpdateOpportunityRequest.php`, `tests/Feature/OpportunityTest.php` e `meta/opportunity-patch-contract-checks.json`. Rota `PATCH /api/opportunities/{opportunity}`.

Causa e impacto: o request de atualização herdava sem adaptação as regras de criação, tornando título, tipo do imóvel e fonte obrigatórios também em PATCH. Alterar somente preço ou nota retornava 422 e forçava reenvio de estado e mídia não alterados.

Como reproduzir antes da correção: crie oportunidade com título, tipo, fonte e foto; envie PATCH somente com `asking_price`. A resposta observada era 422 nos três campos centrais.

Correção: o request de atualização troca esses campos para `sometimes` apenas quando o método é PATCH. PUT preserva o contrato completo e todas as demais regras de upload e autorização permanecem ativas.

Verificação: PATCH de preço retorna 200 e preserva título, tipo, fonte, cidade, notas e a única foto de capa; PUT somente com preço retorna 422 nos três campos obrigatórios. Teste relacionado 16/89, suíte completa 251/1.717 e Pint passaram.

Como verificar: registre oportunidade com foto, aplique PATCH apenas de preço, compare banco/galeria e depois envie o mesmo corpo por PUT.
## BUG 118 — Contexto de bairro aceitava estrutura desconhecida e área fora do domínio

Categoria: API/Validação/Integração externa. Severidade: Média. Status: corrigido e testado. Arquivos `routes/api.php`, `tests/Feature/NeighborhoodContextTest.php` e `meta/neighborhood-input-bound-checks.json`. Rota `POST /api/neighborhood-context`.

Causa e impacto: o objeto `selected` aceitava qualquer chave e `area_m2` possuía apenas limite mínimo. O endpoint equivalente de análise já aplicava lista fechada e capacidade `DECIMAL(10,2)`. Entradas fora do contrato eram aceitas e ainda podiam acionar provedores externos desnecessariamente.

Como reproduzir antes da correção: envie `selected.city=São Paulo`, `selected.area_m2=100000000` e `selected.unexpected=ignored input`. A regressão observou HTTP 200 antes da correção; esperado: 422 sem egress.

Correção: `selected` passou a aceitar somente título, endereço, bairro, cidade, UF e área; a área ficou limitada a 0–99.999.999,99, alinhada ao domínio imobiliário existente.

Verificação: o mesmo payload retorna 422 nos campos `selected` e `selected.area_m2`, e `Http::assertNothingSent` comprova ausência de chamada externa. Teste relacionado 3/25, suíte completa 252/1.722 e Pint passaram.

Como verificar: intercepte o cliente HTTP, envie o payload acima e confirme 422, os dois erros de validação e zero requisições aos provedores.
## BUG 119 — Bundle inicial carregava todas as páginas e excedia 1,1 MB

Categoria: Performance/Frontend. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/App.tsx`, `meta/frontend-build-current.txt` e `meta/frontend-code-splitting-checks.json`. Superfície: inicialização de todas as rotas web.

Causa e impacto: todas as 25 páginas eram importadas estaticamente pelo roteador. O build gerava um único JavaScript principal de 1.123,42 kB (300,24 kB gzip), disparava o alerta de chunks acima de 500 kB e obrigava login e páginas simples a baixar código de gráficos e módulos não visitados.

Como reproduzir antes da correção: execute `npm run build` no frontend e observe `index-*.js` com 1.123,42 kB e o alerta de chunk acima de 500 kB.

Correção: as páginas passaram a usar `React.lazy` e `Suspense`; layout e página de erro compartilhada permanecem no núcleo. O fallback possui `role=status` e respeita tema claro/escuro.

Verificação: build TypeScript/Vite com 2.309 módulos terminou sem alertas; entrada 269,46 kB (89,57 kB gzip), maior chunk 421,58 kB (113,63 kB gzip), e cada página possui chunk próprio. Os 40 testes Node passaram.

Como verificar: limpe/reconstrua `frontend/dist`, confirme ausência do alerta e inspecione que nenhuma saída JavaScript excede 500 kB; navegue entre rotas e confirme o fallback durante carregamento em rede limitada.
## BUG 120 — Filtros da IA causavam rolagem horizontal e cortavam campos

Categoria: Interface/Responsividade. Severidade: Média. Status: corrigido e testado visualmente. Arquivos `frontend/src/pages/IAInteligente.tsx`, `meta/ai-responsive-visual-checks.json` e `meta/frontend-build-current.txt`. Rota `/ia-inteligente`.

Causa e impacto: a grade de filtros ativava sete colunas rígidas já em `lg`. Dentro do conteúdo reduzido pela barra lateral, a soma mínima excedia a viewport desktop; desconto e botão ficavam cortados e a página ganhava rolagem horizontal.

Como reproduzir antes da correção: abra a IA em aproximadamente 1265 px com a barra lateral visível e tema escuro. A inspeção visual mostrou scrollbar horizontal e o campo de desconto truncado.

Correção: a grade usa duas colunas em `md`, quatro em `xl` e somente adota sete colunas dimensionadas em `2xl`. Todos os controles permanecem acessíveis sem largura rígida excessiva.

Verificação: navegador real confirmou `scrollWidth === clientWidth` em 375, 768, 1265 e 1440 px. Em 375 px, menu móvel abriu/fechou e permaneceu utilizável; em tema escuro, a lista de bairros abriu com dez opções. Console registrou zero erros/avisos. Frontend 40/40 e build TypeScript/Vite com 2.309 módulos passaram sem alerta de chunk.

Como verificar: selecione tema escuro, abra a lista de bairros e teste 375/768/1440 px; confirme ausência de scrollbar horizontal, acesso a todos os filtros e menu móvel funcional.
## Revalidação visual das telas solicitadas — 17/10/2026

Navegador real validou `/cadastro` em 375/768/1440 px, `/mensagens` como 404 em 375 px e `/esqueci-senha` em 375 px. Não houve overflow horizontal nem erro/aviso de console. O cadastro expôs 13 controles nomeados, manteve o tema escuro e exibiu “Informe os oito números do CEP.” sem chamada de cadastro ao receber CEP incompleto. A recuperação exibiu explicitamente a política anti-enumeração. Evidência: `requested-screens-visual-checks.json`. Nenhum novo bug foi confirmado nesta rodada.
## Varredura responsiva das rotas principais — 17/10/2026

Com conta fictícia vazia e tema escuro, navegador real percorreu 16 rotas em 375, 768 e 1440 px: 48 combinações. `scrollWidth` nunca excedeu `clientWidth`; não houve alerta visível nem erro/aviso de console. O escopo cobre shells e estados vazios das rotas; tabelas extensas e formulários com dados continuam pendentes e não são inferidos desta prova. Evidência: `responsive-route-sweep.json`.

## BUG 121 — Oportunidade legada sem timestamps derrubava a listagem

Categoria: API/Robustez/Compatibilidade de dados. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Resources/OpportunityResource.php` e `tests/Feature/OpportunityTest.php`.

Causa e impacto: o resource chamava `toIso8601String()` diretamente em `created_at` e `updated_at`, embora as colunas permitam nulo. Um registro legado ou importado sem datas fazia `GET /api/opportunities` responder 500 e indisponibilizava toda a lista.

Correção e verificação: a serialização agora é nula-segura. A regressão insere diretamente uma oportunidade sem timestamps e comprova resposta 200, registro presente e ambos os campos nulos. Suíte completa: 253 testes/1.726 asserções.

## BUG 122 — Tabela populada de despesas deslocava a página móvel

Categoria: Interface/Responsividade. Severidade: Média. Status: corrigido e verificado visualmente. Arquivos `frontend/src/components/AppLayout.tsx` e `frontend/src/index.css`.

Causa e impacto: o contêiner principal aceitava overflow horizontal e uma tabela de 770 px ampliava a área rolável da página em viewport de 375 px. A navegação lateral da página podia deslocar-se junto com a tabela.

Correção e verificação: o layout contém overflow no eixo horizontal e a raiz impede deslocamento global; tabelas preservam seu próprio `overflow-x-auto`. A conta isolada recebeu 28 registros por módulo. O navegador real confirmou oportunidades paginadas (1–10 de 28), documentos com 28 linhas e tabelas/cartões populados em 375/768/1440 sem overflow efetivo ou alerta visível. Frontend 40/40 e build passaram.

## BUG 123 — Suíte frontend não era exposta pelo comando padrão do projeto

Categoria: QA/Automação. Severidade: Baixa. Status: corrigido e verificado. Arquivo `frontend/package.json`.

Causa e impacto: os 40 testes Node existiam, mas `npm test` terminava com “Missing script: test”. Desenvolvedores e pipelines que usam a convenção npm não executavam a suíte sem conhecer um comando alternativo.

Correção e verificação: o script `test` agora executa `node --test tests/*.test.mjs`. `npm test` passou com 40/40, e `npm run build` continuou aprovado.

## Varredura responsiva dos formulários internos — 17/10/2026

Navegador real abriu os formulários de oportunidade, projeto, tarefa, fornecedor, documento e evento em 375, 768 e 1440 px. Foram verificadas 18 combinações, contendo de 9 a 20 controles por formulário. Nenhum controle ficou fora da viewport e nenhuma página apresentou deslocamento horizontal efetivo. Os formulários foram inspecionados sem submissão; os fluxos de persistência correspondentes permanecem cobertos pelos testes de API. Evidência: `meta/populated-form-responsive-checks.json`.

## BUG 124 — Modais não devolviam o foco ao elemento disparador

Categoria: Acessibilidade/Teclado. Severidade: Média. Status: corrigido e verificado em navegador. Arquivos `frontend/src/pages/Oportunidades.tsx`, `Projetos.tsx`, `Tarefas.tsx`, `Fornecedores.tsx`, `Documentos.tsx` e `Calendario.tsx`.

Causa e impacto: ao desmontar o `<dialog>` depois de Escape, React removia o elemento antes que o navegador restaurasse o foco. O foco passava ao documento, fazendo usuários de teclado e leitores de tela perderem o ponto de navegação. Quatro diálogos também não associavam explicitamente o título ao nome acessível.

Correção: cada modal preserva o elemento ativo antes de `showModal()` e devolve o foco no cleanup. Fornecedor, documento, tarefa e evento receberam `aria-labelledby` ligado a um título único; oportunidade e projeto já possuíam nome acessível.

Verificação: navegador real abriu e fechou por Escape os seis modais principais. Todos apresentaram título acessível, fecharam, mantiveram foco inicial dentro do formulário e devolveram foco ao botão disparador. Frontend 40/40 e build TypeScript/Vite passaram. Evidência: `meta/modal-keyboard-accessibility-checks.json`.

## CRUD visual de fornecedores — 17/10/2026

No tenant fictício de auditoria, a interface criou `QA CRUD Fornecedor 20261017`, fechou o modal após sucesso e exibiu o registro. Após recarga completa, a busca localizou o fornecedor; a edição alterou contato para `Contato CRUD Editado` e avaliação para 4,9. Nova recarga preservou os valores. Os filtros combinados de categoria `Auditoria` e status `Ativos` retornaram exatamente um cartão. A exclusão removeu o cartão e a consulta direta confirmou `tenant_id=3` e `deletado_flg=true`. Console: zero erros/avisos. Falha offline continua pendente. Evidência: `meta/supplier-ui-crud-checks.json`.

## CRUD visual e transições de tarefas — 17/10/2026

A interface criou `QA CRUD Tarefa 20261017` no projeto/etapa fictícios e, após recarga e busca, editou status para `em_andamento`, custo real para R$ 987,65 e demais dados. O botão de conclusão moveu a tarefa para `concluida`; a edição posterior reabriu para `em_andamento`. A consulta direta confirmou `tenant_id=3`, relações 1/1, custos, prioridade alta, `data_conclusao=null` após reabertura e registro ativo. Filtros combinados de status, prioridade e projeto retornaram exatamente um cartão. Console: zero erros/avisos. A automação do navegador não conseguiu disparar de forma confiável o evento React do `input[type=date]`; por isso a data não é declarada validada por esta evidência. Exclusão e falha offline continuam pendentes. Evidência: `meta/task-ui-crud-checks.json`.

## BUG 125 — Projetos não podiam ser editados ou excluídos pela interface

Categoria: Funcionalidade/Frontend/CRUD. Severidade: Média. Status: corrigido e verificado visualmente. Arquivos `frontend/src/pages/Projetos.tsx` e `meta/project-ui-crud-checks.json`. Rota `/projetos`.

Causa e impacto: a API já oferecia atualização e exclusão tenant-scoped, mas o detalhe do projeto não expunha nenhuma ação correspondente. O usuário conseguia criar e acompanhar a obra, porém precisava recorrer diretamente à API para corrigir seus dados ou removê-la.

Correção: o detalhe passou a oferecer edição com o mesmo formulário validado da criação e exclusão com diálogo nativo, nome e descrição acessíveis, bloqueio durante a operação e mensagem clara sobre histórico protegido. O fluxo restaura o foco ao disparador, apresenta erros da API e recarrega a coleção após sucesso.

Verificação: navegador real criou o projeto descartável ligado ao imóvel existente, recarregou a rota, editou nome, orçamento para R$ 80.000,00, status e descrição, e confirmou os valores no detalhe e no banco. A exclusão lógica removeu o registro da coleção e o banco confirmou `deletado_flg=true`. Em projeto com despesas ativas, a tentativa retornou a mensagem de bloqueio e preservou `deletado_flg=false`. Frontend 40/40 e build TypeScript/Vite com 2.309 módulos passaram. Evidência: `meta/project-ui-crud-checks.json`.

## BUG 126 — Exclusões operacionais dependiam do diálogo nativo do navegador

Categoria: Acessibilidade/Frontend/CRUD. Severidade: Baixa. Status: corrigido e testado. Arquivos `frontend/src/components/ConfirmActionDialog.tsx`, `frontend/src/pages/Fornecedores.tsx`, `frontend/src/pages/Documentos.tsx`, `frontend/src/pages/Calendario.tsx`, `frontend/src/pages/Oportunidades.tsx`, `frontend/src/pages/CentralFinanceira.tsx`, `frontend/tests/confirm-action-dialog.test.mjs`, `meta/supplier-ui-crud-checks.json` e `meta/confirm-action-dialog-checks.json`.

Causa e impacto: `window.confirm` delegava confirmações ao navegador, impedia a aplicação de fornecer descrição acessível vinculada, estado de processamento e retorno de foco consistente, além de bloquear automação e inspeção do próprio fluxo.

Correção: fornecedor, documento, evento, oportunidade, simulação e custo financeiro usam um `<dialog>` reutilizável com `aria-labelledby`, `aria-describedby`, cancelamento por Escape, bloqueio durante a requisição e restauração do foco ao elemento disparador. Não resta chamada ao diálogo nativo nas páginas; `VerifyEmail.confirm` é somente o nome de uma função de confirmação de token.

Verificação: a exclusão do fornecedor descartável removeu o cartão e persistiu `deletado_flg=true`. Em seguida, o novo diálogo foi aberto e cancelado sobre fornecedor, documento e evento preservados; apresentou nome e descrição acessíveis e devolveu o foco ao botão do fornecedor. Uma regressão lê as seis páginas operacionais e falha se `confirm()` retornar ou se a semântica acessível for removida. Frontend 43/43, suíte PHP 256/1.747 e build TypeScript/Vite com 2.311 módulos passaram. A validação visual adicional de oportunidade e central financeira ficou indisponível porque o controle local do navegador foi desativado no ambiente; a prova dessas duas superfícies é estrutural e de build.

## BUG 127 — Conta não oferecia gestão dos tokens e dispositivos ativos

Categoria: Segurança/Autenticação/Frontend. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/AuthService.php`, `app/Http/Controllers/Api/AuthController.php`, `routes/api.php`, `frontend/src/pages/Configuracoes.tsx`, `tests/Feature/SecurityRegressionTest.php` e `meta/session-management-checks.json`.

Causa e impacto: o usuário podia encerrar apenas o token corrente pelo menu. Não havia inventário de sessões, revogação de outro dispositivo nem ação de emergência para encerrar todos os acessos após perda ou suspeita de comprometimento.

Correção: três endpoints autenticados listam os tokens pertencentes ao usuário, revogam um identificador somente dentro da relação do proprietário e removem todos os tokens. A listagem nunca devolve o hash do token ou abilities; expõe somente identificador, nome, estado atual e marcos temporais. A tela de configurações mostra esses dados, confirma ações destrutivas e remove o token local ao encerrar a sessão corrente ou todas.

Verificação: regressões comprovam lista com dois tokens próprios e ausência do token alheio, marcação do token corrente, 404 ao tentar revogar token de outra conta, preservação desse token, revogação de outro dispositivo sem encerrar o atual e rejeição do token após logout global. Testes focados 3/17; suíte completa 256/1.747, frontend 43/43 e build TypeScript/Vite com 2.311 módulos passaram. A renderização visual ficou pendente porque o controle local do navegador foi desativado no ambiente.



## BUG 128 — Regras de data aceitavam entradas sem limite de tamanho

Categoria: Segurança/Validação/Disponibilidade. Severidade: Baixa. Status: corrigido e testado. Arquivos nos Form Requests, `ValidatePagination.php`, `EventoCalendarioController.php`, `tests/Unit/DateInputBoundTest.php` e `meta/request-bound-validation-checks.json`.

Causa e impacto: as regras `date` validavam interpretação temporal, porém não impunham um orçamento explícito de caracteres antes do parser. Payloads excessivos podiam consumir trabalho desnecessário e deixavam o contrato inconsistente entre projetos, imóveis, despesas, vendas, tarefas, documentos, eventos e análises.

Correção: toda entrada HTTP de data agora aceita no máximo 40 caracteres, suficiente para os formatos ISO usados pela aplicação. A regressão percorre todos os Form Requests, o middleware compartilhado de filtros e o controlador de filtros do calendário, impedindo a criação de uma nova regra `date` sem o limite.

Verificação: teste focado 1/1, Pint aprovado e suíte completa 264 testes/1.787 asserções. Os testes existentes de cronologia, atualização parcial e marcos financeiros continuaram aprovados.


## BUG 129 — Falha ao criar lote ITBI deixava arquivo sem referência

Categoria: Integridade/Filesystem/Disponibilidade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Http/Controllers/Api/ItbiImportController.php`, `tests/Feature/ImportSecurityTest.php` e `meta/transaction-file-rollback-checks.json`.

Causa e impacto: o upload era persistido antes de `ItbiImportBatch::create`. Se o banco falhasse nessa janela, a exceção encerrava a requisição sem remover o arquivo novo. Uma repetição podia acumular órfãos. Remover incondicionalmente seria incorreto porque o caminho é baseado em hash e poderia pertencer a um lote anterior.

Correção: o controlador consulta a existência do caminho antes da gravação, evita sobrescrever conteúdo já presente e registra se criou um arquivo novo. Se a criação do lote falhar, remove somente esse arquivo novo; falha de limpeza é registrada sem expor caminho ou detalhe ao cliente.

Verificação: uma falha simulada de criação do lote deixa zero lotes, zero transações e zero arquivos novos. Outro cenário pré-carrega o mesmo caminho content-addressed e comprova que ele e seus bytes são preservados. ImportSecurityTest passou 8/43; suíte completa 264/1.787 e Pint passaram. Interrupção abrupta do host continua separada como ensaio de produção.

## BUG 130 — CSV codificado e XLSX namespaced eram interpretados incorretamente

Categoria: Integridade/Importação/Compatibilidade. Severidade: Média. Status: corrigido e testado. Arquivos `ItbiSpreadsheetParserService.php`, `SpreadsheetSafetyTest.php` e `meta/spreadsheet-format-compatibility-checks.json`.

Causa e impacto: CSV UTF-8 com BOM incorporava os bytes ao cabeçalho; UTF-16 chegava ao `fgetcsv` antes da conversão, impedindo a detecção correta do delimitador. Em XLSX com namespace padrão, os atributos `t` e `r` das células não eram lidos de modo confiável, fazendo índices de shared strings aparecerem como dados crus.

Correção: a leitura detecta BOM, posiciona o stream após o marcador e instala filtro UTF-16→UTF-8 antes de ler linhas. O cabeçalho também remove BOM residual. O XLSX navega pelo namespace padrão, lê atributos explicitamente e extrai shared strings/rich text por XPath independente do prefixo.

Verificação: UTF-8 BOM com delimitador dentro de aspas, UTF-16LE com acentos, ODS repetido e limitado, XLSX inline e XLSX namespaced com shared/rich strings passaram. Testes relacionados 16/33; suíte completa 264/1.787 e Pint passaram. Um corpus amplo de arquivos reais continua registrado separadamente.

## BUG 131 — Mesmo lote ITBI podia ser processado simultaneamente

Categoria: Concorrência/Integridade/Importação. Severidade: Alta. Status: corrigido e testado localmente. Arquivos `ItbiTransactionImportService.php`, `ItbiImportCommand.php`, testes de pipeline/comando e `meta/itbi-import-recovery-checks.json`.

Causa e impacto: cada worker atualizava o lote para `processing` sem condição sobre o estado anterior. Duas execuções podiam zerar e sobrescrever os mesmos contadores, competir pela identidade única das transações e publicar um estado final enganoso.

Correção: uma única atualização SQL muda o estado somente quando ele ainda não é `processing`; retorno diferente de uma linha encerra a segunda execução antes de ler o arquivo. A consulta da chave única usa `lockForUpdate`. Recuperação não ocorre automaticamente: o operador precisa informar `--recover-stale=N`, e o comando libera atomicamente apenas um lote que continua processando e começou antes do corte.

Verificação: lote já adquirido preserva início e contadores e não cria transações. Lote de 30 minutos não é recuperado com janela de 60, mas conclui com janela explícita de 15; opção zero é recusada. Testes focados 10/48, suíte completa 264/1.787 e Pint passaram. Concorrência em dois processos no MariaDB e upload próximo de 256 MiB seguem como ensaios de produção.

## BUG 132 — Filtro municipal aceitava cidade ou bairro isoladamente

Categoria: Integridade/Provedor de dados. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/MarketData/Providers/ItbiMunicipalProvider.php`, `tests/Unit/MarketProviderTest.php` e `meta/market-provider-response-checks.json`.

Causa e impacto: ao consultar cidade e bairro juntos, o predicado usava OR. Uma transação da cidade correta em outro bairro, ou do bairro homônimo em outra cidade, entrava no conjunto de comparáveis e podia distorcer avaliação, confiança e amostra exibida.

Reprodução anterior: configurar um dataset fictício com três linhas: uma correspondente a ambos os critérios, uma apenas à cidade e outra apenas ao bairro; consultar a combinação. Antes, as três eram aceitas. Agora apenas a linha que corresponde simultaneamente à cidade e ao bairro é retornada.

Correção: o provedor exige correspondência conjuntiva para os critérios geográficos fornecidos. A regressão também simula sucesso, erro HTTP, exceção, provedor não configurado e dataset ausente em Cartórios, Prefeitura e ITBI municipal, confirmando respostas neutras e ausência de detalhes privados.

Verificação: `MarketProviderTest` passou 5 testes/37 asserções; suíte completa 266/1.804 e Pint passaram.

## BUG 133 — Navegador headless podia sair do conjunto de portais autorizado

Categoria: Segurança/SSRF/Integração. Severidade: Alta. Status: corrigido no aplicativo e testado; defesa de egress do host permanece pendente. Arquivos `frontend/scripts/network-policy.mjs`, `frontend/scripts/portal-scraper.mjs`, `frontend/tests/network-policy.test.mjs`, `frontend/tests/portal-scraper-security.test.mjs` e `meta/headless-browser-egress-checks.json`.

Causa e impacto: a política resolvia DNS e recusava destinos privados, mas não exigia que a navegação principal permanecesse nos portais conhecidos. Um redirecionamento de um portal comprometido para outro domínio público seria seguido. URLs de detalhes extraídas da página tinham a mesma liberdade. Downloads não eram negados explicitamente.

Reprodução anterior: fazer um alvo autorizado responder com redirecionamento para `https://attacker.test/`. A interceptação considerava o endereço público seguro e permitia a navegação. Uma URL de detalhe pública fora dos portais também chegava ao `page.goto`.

Correção: a política compartilhada valida HTTPS, host exato ou subdomínio em fronteira real. Toda requisição de navegação do frame principal aplica essa allowlist; enriquecimento inválido é descartado. Cada página configura `Page.setDownloadBehavior` como `deny` antes de navegar. O sandbox continua ativo salvo opt-out explícito.

Verificação: regressões cobrem host exato, subdomínio, downgrade HTTP, sufixo malicioso, credenciais, esquema local, integração da guarda e negação de download em todas as páginas criadas. Frontend 46/46, `node --check` e build de produção com 2.311 módulos passaram. Restrição de egress no firewall/container ainda precisa ser comprovada no deploy porque a resolução do Chromium é externa ao processo Node.

## BUG 134 — Falha de banco após download deixava arquivo ITBI órfão

Categoria: Integridade/Filesystem/Importação. Severidade: Média. Status: corrigido e testado localmente. Arquivos `app/Services/RealEstateData/ItbiFileDownloadService.php`, `tests/Feature/ItbiDownloadTest.php` e `meta/remote-download-rollback-checks.json`.

Causa e impacto: o conteúdo remoto era gravado antes da atualização do lote, sem compensação se o banco recusasse essa atualização. O caminho baseado em hash podia ficar órfão. Como o modelo mantém atributos alterados após uma gravação SQL rejeitada, a marcação posterior como `failed` também podia persistir referência para um arquivo já removido.

Reprodução anterior: criar lote `discovered`, simular resposta HTTP válida, permitir a escrita no disco e provocar falha SQL somente na transição para `downloaded`. Antes, o arquivo permanecia. O mesmo cenário com um arquivo já existente não distinguia propriedade da tentativa.

Correção: o serviço consulta a existência do destino antes de gravar, evita reescrever conteúdo igual e, se o banco falhar, remove somente o arquivo novo. Antes de marcar a falha, recarrega o lote do banco para descartar atributos sujos. Falhas de limpeza são registradas apenas com hash do caminho e tipo da exceção.

Verificação: um trigger SQLite rejeita a atualização real. O primeiro cenário comprova lote `failed`, hash/caminho nulos e diretório vazio; o segundo comprova que bytes preexistentes continuam intactos e sem referência indevida no lote. Testes focados 9/36, suíte completa 268/1.814 e Pint passaram.

## BUG 135 — Estimativa do imóvel era rotulada como média da cidade

Categoria: Funcional/Interface/Integridade de dados. Severidade: Média. Status: corrigido e testado estruturalmente. Arquivos `frontend/src/pages/CentralFinanceira.tsx`, `frontend/tests/financial-market-labels.test.mjs` e `meta/financial-market-label-checks.json`.

Causa e impacto: o painel “Histórico da região” reutilizava a estimativa específica da oportunidade sob o rótulo “Preço médio da cidade”. Também exibia liquidez, valorização anual e tempo médio de venda sem dados, usando “Em breve” e “Preparado para dados reais”. O usuário podia interpretar valores calculados ou digitados como indicadores regionais observados.

Reprodução anterior: abrir a central financeira de uma oportunidade e consultar os cartões de contexto. A estimativa do próprio imóvel aparecia como média municipal, enquanto três métricas sem fonte pareciam funcionalidades prestes a fornecer histórico real.

Correção: o painel agora se chama “Referências da análise”, identifica a estimativa como tal, usa “Valor médio vendido” somente para o campo fornecido e marca métricas ausentes como “Não disponível”. O selo informa que os valores são informados e calculados.

Verificação: regressões falham se os placeholders ou o rótulo municipal enganoso retornarem. Frontend 48/48 e build de produção com 2.311 módulos passaram. A interação visual de todos os controles permanece em item separado.

## BUG 136 — Botão de projeto do dashboard não tinha ação

Categoria: Funcional/Interface. Severidade: Baixa. Status: corrigido e testado estruturalmente. Arquivos `frontend/src/components/dashboard/ProjetosTable.tsx`, `frontend/src/pages/Projetos.tsx`, `frontend/tests/interactive-controls.test.mjs` e `meta/interactive-control-checks.json`.

Causa e impacto: a última coluna de cada projeto exibia um `<button>` com ícone de três pontos, mas sem `onClick`, menu ou comportamento de formulário. Clique e teclado não produziam resultado, embora o controle sugerisse uma ação disponível.

Reprodução anterior: abrir o dashboard com ao menos um projeto em andamento e acionar os três pontos da linha. A interface permanecia inalterada.

Correção: o controle agora é um link nomeado “Abrir projeto …” para `/projetos?projeto={id}`. Depois de carregar apenas os projetos autorizados da conta, a página seleciona o identificador solicitado se ele existir na coleção. Identificadores ausentes ou alheios não são selecionados.

Verificação: a suíte percorre a AST de todo `frontend/src` e falha se um botão nativo não tiver ação nem submit válido. Frontend 49/49 e build de produção com 2.311 módulos passaram. O clique renderizado permanece no checklist visual.

## BUG 137 — IA renderizava link vazio ou com esquema não confiável

Categoria: Segurança/Funcional/Interface. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/lib/externalUrl.ts`, `frontend/src/pages/IAInteligente.tsx`, `frontend/src/pages/AnaliseMercadoOportunidade.tsx`, `frontend/tests/external-url.test.mjs` e `meta/external-listing-link-checks.json`.

Causa e impacto: o cartão sempre criava a ação “Abrir”, substituindo URL ausente por `#`; o detalhe apenas verificava se a string existia. Um resultado sem anúncio oferecia um controle sem destino, e um provedor comprometido podia fornecer esquema impróprio para navegação iniciada pelo usuário.

Reprodução anterior: retornar `source_url=null` na busca e clicar “Abrir”; o navegador usava o fragmento da página. Retornar uma string com esquema não HTTP e observar que ela era atribuída diretamente ao `href`.

Correção: `safeExternalHttpsUrl` analisa a URL, exige HTTPS e recusa usuário/senha. As duas páginas só renderizam o link quando a função retorna destino válido e usam isolamento de opener/referrer.

Verificação: testes exercitam HTTPS, ausência, fragmento, HTTP, credenciais, `javascript:`, `data:` e texto inválido, além de confirmar o uso da função em lista e detalhe. Frontend 51/51 e build de 2.312 módulos passaram.

## BUG 138 — Retorno condicional alterava a quantidade de hooks da análise

Categoria: Funcional/Frontend/Estabilidade. Severidade: Média. Status: corrigido e testado estruturalmente. Arquivos `frontend/src/pages/AnaliseMercadoOportunidade.tsx`, `frontend/tests/external-url.test.mjs` e `meta/market-analysis-hook-checks.json`.

Causa e impacto: o componente retornava a tela de contexto ausente antes dos `useState` e `useEffect`. Uma navegação que reutilizasse a instância entre um ID válido e outro sem contexto podia fazer o React observar menos ou mais hooks e interromper a renderização.

Reprodução anterior: abrir uma análise temporária válida e alterar a rota para um ID sem contexto na mesma árvore do router; no caminho inverso, os hooks passavam a existir somente na segunda renderização. O comportamento violava a ordem invariável exigida pelo React.

Correção: estados e efeitos são declarados antes do retorno. Cada efeito verifica `selectedItem`; sem item, limpa seu estado, encerra loading e não envia requisição. Após essa etapa, a tela de ausência pode retornar com ordem estável.

Verificação: a regressão confirma que `useState`/`useEffect` aparecem antes do retorno e que ambas as chamadas possuem guarda para item ausente. Frontend 52/52 e build de 2.312 módulos passaram.

## Verificação adicional — concorrência real da venda final

Em banco MariaDB 11.4.10 descartável, dois workers PHP independentes aguardaram a mesma barreira e enviaram `POST /api/vendas` para o mesmo imóvel fictício, ambos com estado `fechada`. Os timestamps confirmaram sobreposição. Um worker recebeu 201 e o concorrente recebeu 422. A consulta posterior encontrou uma única venda final ativa, somente o valor vencedor e o imóvel em `vendida`.

O servidor escutou apenas em `127.0.0.1:13308`, foi encerrado após a prova e a porta deixou de ouvir. Senha aleatória, bearer token, jobs e resultados transitórios foram removidos; o JSON preservado contém apenas evidência não sensível. O ZIP oficial MariaDB 11.4.10 foi validado pelo SHA-256 publicado. Ver `final-sale-concurrency-mariadb.json` e `concurrency-harness/run-sale.ps1`.

## Verificação adicional — concorrência real do mesmo lote ITBI

Um lote fictício com 500 linhas foi criado em MariaDB 11.4.10 isolado. Dois workers PHP independentes aguardaram a mesma barreira e chamaram `ItbiTransactionImportService::import` para o mesmo ID. Os timestamps comprovaram sobreposição. Um terminou `completed`; o outro foi recusado com a mensagem específica de processamento em curso.

A consulta final confirmou lote `completed`, `total_rows=500`, `imported_rows=500`, `updated_rows=0`, `failed_rows=0` e exatamente 500 transações. Banco, CSV, senha e artefatos com token foram encerrados/removidos. Isso comprova aquisição exclusiva sob concorrência real; interrupção abrupta e arquivo próximo de 256 MiB continuam como exercícios separados.

## BUG 139 — Logs ITBI copiavam mensagem privada e nome original do arquivo

Categoria: Segurança/Privacidade/Logging. Severidade: Média. Status: corrigido e testado. Arquivos `ItbiTransactionImportService.php`, `ItbiImportController.php`, `ImportSecurityTest.php` e `meta/itbi-log-redaction-checks.json`.

Causa e impacto: a falha isolada de linha incluía a mensagem integral da exceção no log; a falha geral incluía essa mensagem e o nome fornecido pelo usuário. Erros SQL podem incorporar consulta/valores e nomes de arquivo podem conter endereço, CPF ou outro identificador, ampliando a exposição para agregadores de logs.

Reprodução anterior: provocar uma exceção contendo um marcador privado durante a importação ou enviar arquivo cujo nome contenha o marcador. O contexto estruturado do log preservava ambos literalmente.

Correção: o evento de linha registra somente ID do lote, número da linha e classe da exceção. O evento geral registra ID, hash do nome e classe. A resposta continua neutra.

Verificação: o teste injeta `PRIVATE_IMPORT_MARKER` e `PRIVATE_FILENAME.csv`, intercepta o evento de log e exige hash/tipo sem qualquer marcador. Testes focados 9/48, suíte completa 269/1.819 e Pint passaram.

## BUG 140 — Falha do navegador headless copiava stderr privado para logs

Categoria: Segurança/Privacidade/Logging. Severidade: Média. Status: corrigido e testado. Arquivos `PropertyPortalScraper.php`, `PortalScraperLogRedactionTest.php` e `meta/scraper-log-redaction-checks.json`.

Causa e impacto: a falha do processo registrava os primeiros 500 caracteres de `getErrorOutput()`. Puppeteer/Chromium pode incluir caminho do executável, stack, URL corrente e parâmetros formados com cidade ou bairro pesquisados, expondo esses dados a operadores e agregadores de logs.

Reprodução anterior: executar um processo que escreva URL e marcador privado em stderr e termine com código diferente de zero. O contexto de log continha o texto literal truncado.

Correção: o contexto é produzido por função dedicada e contém apenas `exit_code`, `stderr_bytes` e `stderr_hash`. O texto nunca é retornado ao logger.

Verificação: o teste inicia um subprocesso PHP real com código 9 e conteúdo privado, confirma tamanho/hash e exige ausência tanto do marcador quanto do termo sensível. Teste focado 1/5, suíte completa 270/1.824 e Pint passaram.

## BUG 141 — Falha global da importação ITBI persistia e imprimia detalhes internos

Categoria: Segurança/Privacidade/Tratamento de erros. Severidade: Média. Status: corrigido e testado. Arquivos `ItbiTransactionImportService.php`, `ItbiImportCommand.php`, `ItbiImportPipelineTest.php` e `meta/itbi-batch-error-redaction-checks.json`.

Causa e impacto: embora a API já devolvesse uma mensagem neutra, o serviço salvava `exception->getMessage()` em `itbi_import_batches.error_message` e o comando de importação imprimia a mesma mensagem. Erros de parser, filesystem ou banco podem incorporar caminhos, hosts, consultas ou dados das linhas, expondo-os no banco operacional e em logs de automação.

Reprodução anterior: fazer o parser lançar uma exceção contendo `PRIVATE_DATABASE_HOST=db-internal.example.test` após o lote ser adquirido. Antes, o marcador era salvo integralmente no lote e podia aparecer na saída do comando.

Correção: falhas globais persistem e lançam apenas `Falha ao processar o lote ITBI.`. A exceção original permanece encadeada como causa para tratamento interno, enquanto o log contém somente ID do lote e classe da exceção. O comando mostra orientação pelo identificador, sem interpolar a mensagem capturada.

Verificação: a regressão injeta o marcador privado, confirma estado `failed`, mensagem pública fixa e ausência do marcador no banco, na exceção pública e no contexto do logger; também confirma que a causa interna foi preservada. Testes focados 11/56, suíte completa 271/1.832 e Pint passaram.

## BUG 142 — Capa externa aceitava HTTP e destinos locais

Categoria: Segurança/Privacidade/Interface. Severidade: Média. Status: corrigido e testado. Arquivos `ExternalImageUrlPolicy.php`, `StoreOpportunityRequest.php`, `OpportunityService.php`, `OpportunityTest.php` e `meta/external-cover-url-checks.json`.

Causa e impacto: `cover_image_url` aceitava HTTP ou HTTPS e o serviço considerava válida qualquer URL reconhecida pelo PHP. A imagem era persistida e renderizada no navegador de usuários da conta. Isso permitia conteúdo misto em páginas HTTPS, credenciais embutidas e requisições de imagem para localhost ou IPs privados, além de imagens quebradas ou rastreamento inseguro.

Reprodução anterior: criar uma oportunidade com `cover_image_url=http://images.example.test/cover.jpg`, `https://127.0.0.1/cover.jpg` ou uma URL com usuário e senha. O endpoint aceitava e devolvia o endereço como capa.

Correção: a política compartilhada exige HTTPS, host válido, ausência de credenciais, porta padrão e recusa localhost, `.local` e IP literal privado/reservado. O request aplica a regra antes da escrita e o serviço repete a defesa para chamadas internas que contornem o controller. O resource também omite URLs externas inseguras de registros legados antes de responder ao navegador.

Verificação: a regressão recusa sete classes de destino inseguro e aceita HTTPS público na porta 443 com query string, persistindo exatamente uma oportunidade e uma foto. Testes focados 19/124, suíte completa 273/1.863 e Pint passaram. A política não resolve DNS de imagens no cadastro; CSP e controles de rede do navegador continuam necessários contra mudança posterior de resolução.


## Verificação visual — falha e recuperação em Configurações

Um Chromium local isolado renderizou `/configuracoes` em 375×812, tema escuro e APIs simuladas. As primeiras respostas de fontes e sessões foram 503; a tela apresentou dois alertas com mensagens próprias e dois controles “Tentar novamente”. Após acioná-los, a sessão e a fonte ITBI apareceram, os alertas sumiram e cada endpoint recebeu nova tentativa. `scrollWidth=375` antes e depois, sem exceção JavaScript. As duas mensagens 503 do console são o diagnóstico esperado das respostas injetadas. Evidência em `configuration-ui-checks.json` e nas capturas `configuration-ui-mobile-error.png` / `configuration-ui-mobile-recovered.png`. Leitor de tela real permanece pendente.

## Verificação visual — fornecedor preservado após falha de rede

Em Chromium real, tema escuro e 375×812, a primeira tentativa de cadastrar um fornecedor recebeu 503. O diálogo permaneceu aberto, exibiu alerta próprio, preservou nome, categoria, contato, telefone, e-mail, avaliação, observações e status ativo, e reabilitou o botão de envio. A segunda tentativa recebeu 201, fechou o diálogo, exibiu “Fornecedor cadastrado.” e apresentou exatamente um cartão após recarregar a coleção. Foco inicial, ausência de overflow e ausência de exceções JavaScript também foram confirmados. Evidência em `supplier-ui-network-checks.json` e nas capturas `supplier-ui-network-error.png` / `supplier-ui-network-recovered.png`. Falha durante exclusão permanece separada.

## BUG 143 — Erro ao excluir fornecedor ficava oculto atrás do modal

Categoria: Funcional/Interface/Tratamento de erro. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/pages/Fornecedores.tsx`, `frontend/scripts/supplier-ui-network-check.mjs`, `meta/supplier-ui-network-checks.json` e `meta/supplier-ui-delete-error.png`.

Causa e impacto: quando o DELETE falhava, o estado de erro era atualizado, mas `deleteTarget` permanecia definido. O diálogo modal continuava sobre a página enquanto o alerta era renderizado atrás dele. O usuário não via a explicação e podia repetir a ação sem contexto.

Reprodução anterior: abrir “Excluir fornecedor?”, confirmar e fazer `DELETE /api/fornecedores/{id}` responder 503. O botão deixava o estado de carregamento, mas o modal permanecia; a mensagem existia somente no conteúdo bloqueado ao fundo.

Correção: o ramo de erro limpa o alvo da exclusão depois de registrar a mensagem. O diálogo desmonta, devolve o foco e revela o alerta global com “Tentar novamente”; o registro local permanece inalterado.

Verificação: Chromium real em tema escuro e 375×812 recebeu 503. O diálogo fechou, o alerta ficou visível, exatamente um cartão permaneceu e não houve overflow ou exceção JavaScript. O mesmo harness também comprovou preservação integral do cadastro após POST 503 e retry 201 sem duplicação. Frontend 52/52 e build de produção com 2.312 módulos passaram.

## BUG 144 — Erros de exclusão também ficavam ocultos em Calendário, Documentos e Tarefas

Categoria: Funcional/Interface/Tratamento de erro. Severidade: Média. Status: corrigido e testado. Arquivos `Calendario.tsx`, `Documentos.tsx`, `Tarefas.tsx`, `destructive-error-visibility.test.mjs` e `meta/destructive-error-visibility-checks.json`.

Causa e impacto: os três handlers repetiam o padrão confirmado no BUG 143: em erro de DELETE, atualizavam o alerta da página sem limpar o estado que mantinha o `ConfirmActionDialog` aberto. Como o diálogo é modal, a mensagem permanecia inacessível atrás dele.

Reprodução anterior: abrir a confirmação de exclusão de evento, documento ou tarefa e fazer o endpoint responder 5xx. O registro permanecia corretamente, mas o modal continuava cobrindo a mensagem de falha.

Correção: cada ramo `catch` agora limpa seu alvo de exclusão depois de definir o erro. Uma regressão percorre os nove handlers destrutivos que usam confirmação em sete páginas e exige que cada caminho de erro desmonte o respectivo modal.

Verificação: a regressão passou junto com 53/53 testes frontend; build de produção transformou 2.312 módulos sem alerta. O comportamento modal/alerta foi renderizado de ponta a ponta no fornecedor, que compartilha o mesmo componente; os três handlers adicionais foram verificados estruturalmente.

## BUG 145 — Ações remotas de Oportunidades falhavam sem feedback

Categoria: Funcional/Interface/Tratamento de erro. Severidade: Média. Status: corrigido e testado. Arquivos `Oportunidades.tsx`, `opportunity-action-errors.test.mjs` e `meta/opportunity-action-error-checks.json`.

Causa e impacto: visualizar detalhes, carregar edição, favoritar e abrir/criar análise financeira aguardavam diretamente a API sem `try/catch`. Erros de rede ou respostas 5xx produziam rejeições não tratadas e nenhuma mensagem confiável. No detalhe, alertas globais também ficavam atrás do modal.

Reprodução anterior: fazer qualquer um desses endpoints falhar e acionar o respectivo botão. A promessa era rejeitada; o usuário permanecia sem indicação clara e podia repetir a operação.

Correção: as quatro ações limpam erro anterior, ativam estado pendente, capturam a mensagem pública ou usam fallback específico e sempre restauram `saving`. O detalhe renderiza o alerta dentro do próprio modal e bloqueia ações concorrentes enquanto uma chamada está ativa.

Verificação: duas regressões verificam os quatro caminhos de erro, restauração de pending, alerta modal e bloqueio dos controles. Frontend 55/55 e build de produção com 2.312 módulos passaram.

## BUG 146 — Referência e seleção de financiamento falhavam sem feedback

Categoria: Funcional/Interface/Financeiro. Severidade: Média. Status: corrigido e testado. Arquivos `CentralFinanceira.tsx`, `financial-action-errors.test.mjs` e `meta/financial-action-error-checks.json`.

Causa e impacto: “Recarregar referência” e “Escolher” simulação aguardavam diretamente GET/PATCH sem `try/catch`, estado pendente ou bloqueio dos controles. Uma falha de rede/servidor gerava rejeição JavaScript e permitia cliques concorrentes, sem explicar se os valores haviam sido alterados.

Reprodução anterior: abrir a aba Financiamento, fazer o endpoint de referência ou seleção responder 5xx e acionar o botão. Nenhum alerta era definido e o controle permanecia acionável durante a chamada.

Correção: ambas as ações limpam erros anteriores, ativam `saving`, capturam mensagens de validação/servidor e restauram o estado em `finally`. Recarregar, adicionar, escolher e excluir simulações ficam desabilitados enquanto uma ação financeira está pendente.

Verificação: duas regressões verificam caminhos de erro e propagação de pending aos controles. Frontend 57/57 e build de produção com 2.312 módulos passaram.

## BUG 147 — Recalcular financiamento e editar orçamento de etapa falhavam silenciosamente

Categoria: Funcional/Interface/Tratamento de erro. Severidade: Média. Status: corrigido e testado. Arquivos `WizardFinanceiro.tsx`, `Projetos.tsx`, `wizard-project-action-errors.test.mjs` e `meta/wizard-project-action-error-checks.json`.

Causa e impacto: a atualização automática da referência financeira possuía apenas `finally`; uma rejeição do endpoint escapava como promessa não tratada. A edição de meta/orçamento de uma etapa aguardava PATCH e recarga sem `try/catch`, permitia novos eventos durante a chamada e deixava no campo o valor que o servidor recusara.

Reprodução anterior: abrir a etapa de financiamento do wizard ou desfocar o orçamento de uma etapa enquanto o respectivo endpoint responde 5xx. O navegador não exibia explicação confiável; na etapa, o rascunho recusado permanecia visível como se estivesse salvo.

Correção: o wizard limpa o erro anterior, captura mensagem pública/fallback e sempre encerra o indicador de recálculo. O orçamento da etapa ativa estado pendente, bloqueia nova submissão, publica erros, retorna sucesso/falha e restaura o valor persistido quando o PATCH falha.

Verificação: duas regressões cobrem captura, mensagem, restauração de pending, bloqueio e rollback visual. Frontend 59/59 e build de produção com 2.312 módulos passaram.

## BUG 148 — Gravações financeiras permitiam submissões duplicadas

Categoria: Funcional/Integridade/Interface. Severidade: Média. Status: corrigido e testado. Arquivos `CentralFinanceira.tsx`, `financial-action-errors.test.mjs` e `meta/financial-create-pending-checks.json`.

Causa e impacto: adicionar uma simulação, cadastrar um gasto e salvar o resultado capturavam a resposta de erro, mas não ativavam `saving`. Os botões permaneciam disponíveis durante a requisição e cliques repetidos podiam criar registros duplicados ou disputar a atualização final da análise.

Reprodução anterior: atrasar qualquer um dos três POSTs e clicar novamente antes da primeira resposta. O frontend emitia outra requisição porque não havia estado pendente nem botão desabilitado.

Correção: os três handlers agora limpam mensagens anteriores, ativam `saving` antes da chamada e o restauram em `finally`. Comparador, formulário de gasto, exclusões de gastos e formulário de resultado recebem o mesmo estado e bloqueiam ações concorrentes; o resultado também apresenta confirmação explícita após persistência.

Verificação: a regressão exige pending, captura e `finally` nos três handlers e propagação aos controles. Frontend 60/60 e build de produção com 2.312 módulos passaram.

## BUG 149 — Falha de recarga ainda produzia confirmação financeira enganosa

Categoria: Funcional/Integridade/Interface. Severidade: Média. Status: corrigido e testado. Arquivos `CentralFinanceira.tsx`, `financial-action-errors.test.mjs` e `meta/financial-refresh-outcome-checks.json`.

Causa e impacto: `load()` capturava internamente a falha do GET e resolvia a promessa sem informar o resultado. Depois de salvar financiamento, simulação ou resultado, os handlers continuavam exibindo sucesso — e o primeiro avançava de aba — mesmo quando a tela permanecia com dados antigos.

Reprodução anterior: permitir que o PUT/POST conclua e fazer o GET subsequente de `/investment-analyses/{id}` falhar. A página mostrava simultaneamente o erro de recarga e uma confirmação de sincronização/recalculo que não podia comprovar.

Correção: `load()` agora retorna `true` somente após reconstruir todo o estado e `false` após publicar o erro. Confirmação e avanço de aba dependem explicitamente desse retorno nos três fluxos afetados.

Verificação: a regressão exige contrato booleano no loader e condicionamento do sucesso em financiamento, simulação e resultado. Frontend 61/61 e build de produção com 2.312 módulos passaram.

## BUG 150 — Busca da IA permitia concorrência e resposta antiga podia substituir a atual

Categoria: Funcional/Concorrência/Interface. Severidade: Média. Status: corrigido e testado. Arquivos `IAInteligente.tsx`, `ai-search-actions.test.mjs`, `scripts/ai-search-ui-check.mjs` e `meta/ai-search-network-checks.json`.

Causa e impacto: “Buscar” permanecia ativo enquanto `/api/ai-property-search` estava pendente e não havia cancelamento nem identificador de sequência. Cliques repetidos em condições/filtros diferentes podiam manter várias raspagens simultâneas; uma resposta mais antiga e lenta podia sobrescrever o ranking mais recente.

Reprodução anterior: iniciar uma busca lenta, alterar a consulta e acionar novamente antes da resposta. Ambas permaneciam ativas e cada conclusão atualizava resultados, diagnóstico, erro e loading sem conferir qual solicitação era atual.

Correção: cada busca incrementa uma sequência, aborta o controller anterior e envia o novo `AbortSignal`. Sucesso, erro e `finally` só alteram estado se a solicitação ainda for a atual. O botão fica desabilitado durante a chamada e a desmontagem da tela aborta qualquer busca ativa.

Verificação: duas regressões verificam cancelamento, sinal, descarte de resposta tardia, limpeza no unmount e botão pendente. Chromium real em tema escuro e 375×812 simulou 502 seguido de 200: duas tentativas, concorrência máxima 1, erro recuperado, resultado renderizado, sem overflow ou exceção de página. Frontend 63/63 e build com 2.312 módulos passaram.

## BUG 151 — Miniaturas da busca da IA podiam apontar para destinos internos

Categoria: Segurança/SSRF no navegador/Validação de saída. Severidade: Alta. Status: corrigido e testado. Arquivos `AIPropertySearchController.php`, `ExternalImageUrlPolicy.php`, `AIPropertySearchTest.php` e `meta/ai-thumbnail-url-policy-checks.json`.

Causa e impacto: `validImageUrl()` aceitava qualquer URL HTTP/HTTPS fornecida por scraper ou API. A resposta era usada diretamente em `<img src>`, permitindo que um provedor comprometido induzisse o navegador autenticado a requisitar localhost, IP privado/link-local, domínio `.local`, URL com credenciais ou serviço HTTP.

Reprodução anterior: fazer o scraper devolver `thumbnail=https://127.0.0.1/item.jpg` ou `https://10.0.0.5/item.jpg`. O endpoint preservava o valor e a tela tentava carregá-lo.

Correção: miniaturas agora passam pela política comum `ExternalImageUrlPolicy`, que exige HTTPS, host público válido, ausência de credenciais e porta padrão. URLs relativas com `//` são normalizadas para HTTPS antes da política; destinos recusados viram `null` e o frontend usa seu placeholder local.

Verificação: a regressão cobre `data:`, HTTP, credenciais, localhost, loopback, rede privada, `.local`, porta 8443 e uma URL HTTPS pública na porta 443. Testes focados 3/20 e Pint passaram.

## BUG 152 — Links “Abrir” da IA aceitavam hosts privados e portas alternativas

Categoria: Segurança/Navegação externa/Validação de saída. Severidade: Média. Status: corrigido e testado. Arquivos `ExternalUrlPolicy.php`, `ExternalImageUrlPolicy.php`, `AIPropertySearchController.php`, `externalUrl.ts`, `external-url.test.mjs`, `AIPropertySearchTest.php` e `meta/ai-external-link-policy-checks.json`.

Causa e impacto: o backend aceitava qualquer link HTTP/HTTPS e o frontend restringia apenas para HTTPS sem credenciais. Um provedor comprometido ainda podia apresentar como “Abrir” uma URL para localhost, rede privada/link-local, domínio `.local` ou porta alternativa, induzindo navegação do usuário para serviço interno ou destino não previsto.

Reprodução anterior: devolver `source_url=https://127.0.0.1/admin`, `https://169.254.169.254/` ou `https://portal.example:8443/`. O backend preservava todos; o helper frontend também aceitava as versões HTTPS sem credenciais.

Correção: `ExternalUrlPolicy` centraliza HTTPS, host público, porta 443 e ausência de credenciais. A política de imagens delega a ela. O endpoint filtra `source_url` no servidor, e o helper frontend repete a proteção para defesa em profundidade, recusando também literais IPv6.

Verificação: frontend focado 3/3; endpoint 3/20; Pint, suíte frontend 63/63 e build de 2.312 módulos passaram. Controles públicos HTTPS por domínio e IPv4 continuam aceitos.

## BUG 153 — Representações alternativas de IP contornavam a política de URL pública

Categoria: Segurança/Validação de URL/Bypass. Severidade: Alta. Status: corrigido e testado. Arquivos `ExternalUrlPolicy.php`, `ExternalUrlPolicyTest.php`, `external-url.test.mjs` e `meta/external-url-obfuscated-ip-checks.json`.

Causa e impacto: a política reconhecia apenas literais aceitos por `FILTER_VALIDATE_IP`. Formas que navegadores resolvem como loopback — IPv4 decimal inteiro, hexadecimal, octal e abreviado — eram tratadas como nomes de domínio e liberadas. IPv6 entre colchetes também não era normalizado antes da classificação.

Reprodução anterior: avaliar URLs como `https://2130706433/admin`, `https://0x7f000001/admin`, `https://0177.0.0.1/admin` ou `https://127.1/admin`. Seis dos oito controles negativos retornavam permitido.

Correção: o host IPv6 é desempacotado antes de `FILTER_VALIDATE_IP`; qualquer literal privado/reservado é recusado. Hosts compostos apenas de segmentos numéricos/hexadecimais que não sejam um IPv4 canônico também são rejeitados, impedindo as notações alternativas. O frontend testa as mesmas variantes, que `URL` normaliza antes da política defensiva.

Verificação: o teste antes da correção falhou em 6/9 cenários. Após a correção, política 9/9, frontend focado 3/3, integrações de IA/oportunidade 22/144, Pint e suíte PHP completa 282/1.876 passaram.

## BUG 154 — Oportunidades manuais e legadas contornavam a política de URL externa

Categoria: Segurança/Validação de entrada e saída. Severidade: Alta. Status: corrigido e testado. Arquivos `StoreOpportunityRequest.php`, `OpportunityResource.php`, `Oportunidades.tsx`, `OpportunityTest.php`, `external-url.test.mjs` e `meta/opportunity-source-url-policy-checks.json`.

Causa e impacto: o endpoint de oportunidades aceitava `source_url` HTTP/HTTPS sem política de host e o resource devolvia valores legados literalmente. Além disso, a tela reaproveitava miniaturas guardadas em `sessionStorage` sem revalidá-las. Assim, a proteção aplicada à busca podia ser contornada criando um registro manual, lendo um registro antigo ou adulterando o cache local.

Reprodução anterior: cadastrar oportunidade com `source_url=https://127.0.0.1/item` ou inserir esse valor em registro legado. A gravação era aceita e a API devolvia o endereço. Um snapshot local também podia associar miniatura privada ao título ou URL.

Correção: criação e atualização agora aplicam `ExternalUrlPolicy`; o resource omite valores legados inseguros. Ao reconstruir capas do radar, o frontend revalida tanto miniatura quanto URL de origem e só cria chaves de cache com valores públicos aprovados.

Verificação: oito destinos inseguros foram rejeitados, HTTPS público foi persistido e um legado privado foi omitido sem aparecer no corpo. Oportunidades 20/153, frontend focado 4/4, Pint, suíte PHP 283/1.905, frontend 64/64 e build de 2.312 módulos passaram.

## BUG 155 — Diagnóstico externo da busca podia expor detalhes internos

Categoria: Segurança/Exposição de informações. Severidade: Média. Status: corrigido e testado. Arquivos `AIPropertySearchController.php`, `AIPropertySearchTest.php` e `meta/ai-diagnostic-redaction-checks.json`.

Causa e impacto: mensagens produzidas pelo processo headless ou por uma fonte HTTP eram incluídas sem filtragem em respostas 502 e em respostas de sucesso. Uma mensagem externa podia conter caminho local, URL privada, termo pesquisado ou outro dado operacional e chegar ao navegador autenticado.

Reprodução anterior: fazer o scraper retornar uma mensagem contendo `C:\private\scraper.js`, `http://127.0.0.1:9000` e um marcador secreto. O endpoint preservava o texto em `diagnostics`.

Correção: a API agora limita o diagnóstico público a 25 registros, normaliza o nome da fonte, restringe `status` e `found` aos intervalos esperados e substitui qualquer mensagem externa por `Fonte externa indisponível.`. O mesmo filtro é aplicado em respostas com e sem anúncios.

Verificação: a regressão injeta caminho, URL privada e marcador secreto e comprova que nenhum deles aparece na resposta. Teste focado 3/24, Pint e suíte PHP completa 283/1.909 passaram.

## BUG 156 — Provedores de mercado permitiam redirecionamento e resposta sem limite

Categoria: Segurança/SSRF/Disponibilidade. Severidade: Alta. Status: corrigido e testado. Arquivos `NormalizesTransactions.php`, `CartorioTransactionProvider.php`, `PrefeituraTransactionProvider.php`, `ItbiMunicipalProvider.php`, `MarketProviderTest.php` e `meta/market-provider-egress-limits-checks.json`.

Causa e impacto: os conectores configuráveis de cartórios, prefeituras e ITBI seguiam redirecionamentos do cliente HTTP e materializavam o corpo integral antes do parse. Um endpoint comprometido podia redirecionar o backend para endereço interno ou devolver resposta arbitrariamente grande, consumindo memória. Configurações HTTP, loopback e link-local também eram usadas sem validação prévia.

Reprodução anterior: configurar um endpoint privado ou devolver corpo acima de 2 MiB. A requisição era iniciada e o corpo seguia para `json()`/`body()` sem teto específico.

Correção: os três provedores agora exigem endpoint HTTPS público pela política comum, recusam redirecionamentos, solicitam `Accept-Encoding: identity`, desativam descompressão automática e aplicam teto de 2 MiB em `Content-Length`, progresso recebido e corpo final.

Verificação: a regressão comprova as opções do transporte, interrupção por header, rejeição final nos três provedores e ausência de qualquer egress para HTTP, loopback e metadata link-local. Testes focados 9/68, Pint e suíte PHP completa 287/1.940 passaram.


## BUG 157 — Contexto de bairro permitia egress privado, redirects e corpos ilimitados

Categoria: Segurança/SSRF/Disponibilidade. Severidade: Alta. Status: corrigido e testado. Arquivos `NeighborhoodContextService.php`, `NeighborhoodContextTest.php` e `meta/neighborhood-egress-limits-checks.json`.

Causa e impacto: Nominatim e Overpass aceitavam diretamente endpoints configurados, e todas as chamadas geográficas seguiam redirects e carregavam respostas sem teto de transferência. Uma configuração insegura ou serviço externo comprometido podia induzir acesso a loopback/link-local ou consumir memória do processo. A rota é acionável por usuários autenticados dentro do orçamento de requisições de mercado.

Reprodução anterior: configurar Nominatim em `https://127.0.0.1`, Overpass em `https://169.254.169.254`, ou devolver corpo maior que 2 MiB. O serviço tentava a chamada ou materializava o corpo antes do parse.

Correção: todas as chamadas Google, Nominatim e Overpass usam transporte comum que exige HTTPS público para endpoints, bloqueia redirects, solicita `identity`, desativa descompressão e limita 2 MiB pelo header, progresso e corpo final. Endpoints privados são descartados antes da requisição; fallbacks públicos válidos continuam disponíveis.

Verificação: a regressão registra as URLs solicitadas e comprova ausência dos três destinos inseguros, valida as opções do transporte e cobre excesso anunciado, progressivo e final. Testes focados 7/37, Pint e suíte PHP completa 291/1.952 passaram.

## BUG 158 — Consulta de CEP seguia redirects, aceitava corpo ilimitado e dados incompatíveis

Categoria: Segurança/Disponibilidade/Contrato. Severidade: Média. Status: corrigido e testado. Arquivos `PostalCodeController.php`, `RegistrationVerificationTest.php` e `meta/postal-code-provider-guards.json`.

Causa e impacto: a consulta pública ao ViaCEP seguia redirecionamentos e materializava qualquer tamanho de resposta. O validador também aceitava até 500 bytes para todos os campos, embora cadastro/endereço permitam rua 180, bairro/cidade 120 e UF com duas letras. Um provedor anômalo podia consumir memória ou preencher o formulário com valor que falharia somente no envio posterior.

Reprodução anterior: devolver redirect, corpo acima de 64 KiB, logradouro com 181 caracteres ou UF `S1`. O transporte não tinha teto e os dois últimos valores passavam pela consulta.

Correção: a chamada fixa desativa redirects e descompressão, solicita `identity` e limita 64 KiB no header, progresso e corpo final. Rua, bairro e cidade foram alinhados aos limites do cadastro; UF é normalizada para maiúsculas e exige exatamente duas letras.

Verificação: testes cobrem resposta válida, ausente, malformada, normalização de UF, campos excessivos, corpo final, tamanho anunciado, progresso e opções de transporte. Testes focados 9/54, Pint e suíte PHP completa 293/1.962 passaram.

## BUG 159 — Fallbacks da busca por IA seguiam redirects e aceitavam respostas ilimitadas

Categoria: Segurança/SSRF/Disponibilidade. Severidade: Alta. Status: corrigido e testado. Arquivos `AIPropertySearchController.php`, `AIPropertySearchTest.php` e `meta/ai-provider-transfer-guards.json`.

Causa e impacto: o scraper tinha limites próprios, mas os fallbacks Nestoria e Mercado Livre usavam o cliente HTTP padrão. Um provedor comprometido podia redirecionar a requisição do backend ou devolver corpo excessivo, elevando uso de memória em uma busca autenticada.

Reprodução anterior: simular corpo maior que 2 MiB ou redirect em uma das APIs após o scraper retornar vazio. A resposta era materializada e analisada sem teto específico.

Correção: ambos os fallbacks usam TLS verificado, transferência `identity`, redirects e descompressão desativados e teto de 2 MiB aplicado no `Content-Length`, progresso e corpo final. A falha continua neutra e permite o fallback seguinte quando aplicável.

Verificação: regressões percorrem todas as tentativas dos dois provedores, inspecionam as opções do transporte e interrompem excesso final, anunciado e progressivo. Testes focados 5/46, Pint e suíte PHP completa 295/1.984 passaram.

## BUG 160 — Nome original do upload ITBI era exposto a usuários sem permissão de importação

Categoria: Segurança/Autorização/Exposição de informações. Severidade: Média. Status: corrigido e testado. Arquivos `ItbiImportController.php`, `ImportSecurityTest.php` e `meta/itbi-filename-authorization-checks.json`.

Causa e impacto: `GET /api/itbi-imports` é intencionalmente legível por contas verificadas para consulta da base compartilhada, mas `summary.latest_batch.source_filename` devolvia o nome original enviado pelo operador. Esse texto controlado no upload pode conter cliente, endereço, CPF ou referência interna e não é necessário para usuários sem `itbi.import`.

Reprodução anterior: criar lote com `source_filename=CLIENTE-JOAO-CPF-PRIVATE.csv`, autenticar usuário comum e consultar `/api/itbi-imports`. O nome era retornado junto ao resumo.

Correção: o controller calcula a autorização uma vez e inclui o nome original somente para usuários que podem `import-itbi`. A resposta do próprio upload preserva o campo porque a rota já exige essa permissão; transações públicas e métricas permanecem disponíveis.

Verificação: a regressão usa o mesmo lote com usuário comum e operador. O primeiro recebe `source_filename=null` e não contém o marcador; o segundo recebe o nome integral. Testes focados 10/55, Pint e suíte PHP completa 296/1.991 passaram.

## BUG 161 — Tela de fontes marcava endpoints bloqueados como configurados

Categoria: Funcional/Configuração segura. Severidade: Baixa. Status: corrigido e testado. Arquivos `DataSourceStatusController.php`, `DataSourceStatusTest.php` e `meta/data-source-policy-alignment-checks.json`.

Causa e impacto: o status aceitava qualquer URL com esquema HTTPS, enquanto os clientes externos já recusavam localhost, rede privada/link-local, domínio `.local`, credenciais e porta alternativa. A tela podia afirmar “configurado” para uma integração que seria bloqueada em toda consulta, dificultando diagnóstico e incentivando configuração insegura.

Reprodução anterior: configurar Cartórios em `https://127.0.0.1`, ITBI em `https://169.254.169.254`, Prefeitura em domínio `.local` ou OpenStreetMap em host privado. O status ainda classificava esses valores HTTPS como disponíveis/configurados.

Correção: a leitura de status usa a mesma `ExternalUrlPolicy` do egress e apresenta endpoint inválido quando não é HTTPS público.

Verificação: a regressão cobre as quatro fontes com loopback, link-local, `.local`, localhost e rede privada. Todas retornam `unavailable`. Testes focados 4/30, Pint e suíte PHP completa 297/1.996 passaram.


## BUG 162 — Fotos aceitavam dimensões extremas e nome original não normalizado

Categoria: Segurança/Upload/Disponibilidade. Severidade: Média. Status: corrigido e testado. Arquivos `StoreOpportunityRequest.php`, `OpportunityService.php`, `OpportunityTest.php` e `meta/opportunity-image-dimension-checks.json`.

Causa e impacto: as fotos tinham limite de 5 MiB e formato real de imagem, mas não limitavam largura, altura ou quantidade de pixels. Uma imagem altamente comprimida podia passar pelo upload e exigir memória excessiva ao decodificar/renderizar. O nome original também era salvo e devolvido sem remover segmentos de caminho ou caracteres de controle.

Reprodução anterior: enviar JPEG válido de 12.001×1 px ou nome `../../PRIVATE\\nphoto.jpg`. A dimensão não era recusada e o texto original era persistido no metadado da foto.

Correção: cada foto permite no máximo 12.000 px por lado e 40 milhões de pixels totais, além dos limites existentes de formato e 5 MiB. O nome agora usa somente basename, troca controles por `_` e limita 240 caracteres antes de persistir.

Verificação: a regressão rejeita a imagem excessiva sem criar oportunidade/arquivo e aceita imagem pequena com nome hostil, comprovando `PRIVATE_photo.jpg` no banco e na resposta. Oportunidades 21/163, Pint e suíte PHP completa 300/2.259 passaram.

## BUG 163 — Armazenamento indisponível interrompia a abertura da análise da IA

Categoria: Funcional/Resiliência. Severidade: Baixa. Status: corrigido e testado. Arquivos `frontend/src/pages/IAInteligente.tsx`, `frontend/tests/ai-search-actions.test.mjs` e `meta/ai-analysis-storage-resilience-checks.json`.

Causa e impacto: o clique em “Ver análise” gravava o mesmo payload diretamente em `sessionStorage` e `localStorage`. Quando um deles lançava `QuotaExceededError` ou `SecurityError`, condição possível com cota cheia, política corporativa ou armazenamento desabilitado, a exceção interrompia o handler antes da navegação. Mesmo que o outro armazenamento estivesse disponível, a análise não abria e nenhum feedback era mostrado.

Como reproduzir antes da correção: abra `/ia-inteligente`, obtenha resultados, faça `localStorage.setItem` lançar uma exceção nas ferramentas do navegador e clique em “Ver análise”. A gravação em sessão podia ter ocorrido, mas o clique encerrava com erro JavaScript e permanecia na lista.

Correção: a persistência temporária tenta os dois armazenamentos de forma independente e considera a operação utilizável quando ao menos um aceita o conteúdo. Quando ambos falham, a navegação é interrompida de forma controlada e a tela orienta a liberar o armazenamento do site.

Como verificar: simule falha apenas em `localStorage` ou apenas em `sessionStorage` e confirme que a navegação continua; simule falha nos dois e confirme a mensagem pública sem erro não tratado. A regressão estrutural cobre tentativa independente, tratamento da exceção, resultado booleano e caminho visual de erro. Frontend 65/65 e build de 2.312 módulos passaram.

## BUG 164 — Notas locais inválidas impediam o carregamento do Wizard Financeiro

Categoria: Funcional/Resiliência. Severidade: Baixa. Status: corrigido e testado. Arquivos `frontend/src/pages/WizardFinanceiro.tsx`, `frontend/tests/wizard-project-action-errors.test.mjs` e `meta/wizard-note-storage-checks.json`.

Causa e impacto: o wizard fazia `JSON.parse` direto sobre um valor editável do `localStorage` e também gravava sem tratar exceções. JSON truncado fazia o carregamento remoto bem-sucedido terminar no alerta genérico; bloqueio ou cota cheia durante a gravação podia chegar ao Error Boundary. Objetos com forma inesperada também eram aceitos como estado de notas.

Como reproduzir antes da correção: grave `{invalido` em `investflip-wizard-notes-<id>` e abra `/analises-financeiras/<id>`. A API respondia, mas a tela informava que não conseguiu carregar a análise. Alternativamente, faça `localStorage.setItem` lançar `QuotaExceededError` e edite uma nota para causar exceção no efeito React.

Correção: leitura e gravação foram encapsuladas em caminhos tolerantes a falha. A leitura só aceita objeto simples com até 100 pares, chaves de até 120 caracteres e valores textuais de até 5.000; qualquer conteúdo inválido volta às descrições persistidas na API. A gravação local é opcional e nunca derruba o wizard.

Como verificar: repita com JSON inválido, array, valor não textual, objeto excessivo e armazenamento indisponível. A análise deve continuar carregando e usando o fallback remoto. A regressão cobre parse protegido, validação de forma/limites e escrita protegida. Frontend 66/66 e build de 2.312 módulos passaram.

## BUG 165 — Bloqueio do localStorage quebrava os fluxos de autenticação

Categoria: Funcional/Autenticação/Resiliência. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/lib/authToken.ts`, `App.tsx`, `lib/api.ts`, `Sidebar.tsx`, `Configuracoes.tsx`, `Login.tsx`, `Register.tsx`, `ResetPassword.tsx`, `VerifyEmail.tsx` e `frontend/tests/auth-token-storage.test.mjs`.

Causa e impacto: oito consumidores liam, gravavam ou removiam o bearer token diretamente no `localStorage`. Em navegadores com armazenamento bloqueado, `SecurityError` podia impedir a inicialização da rota privada, transformar login/cadastro bem-sucedido em erro visual, interromper logout e quebrar a confirmação ou redefinição de senha.

Como reproduzir antes da correção: bloqueie o armazenamento do site ou substitua os três métodos de `localStorage` por funções que lançam `SecurityError`; abra uma rota privada, faça login ou encerre uma sessão. A exceção escapava do fluxo correspondente.

Correção: todos os consumidores usam um helper único que captura as operações de armazenamento e mantém fallback em memória para a sessão SPA atual. Login e cadastro continuam utilizáveis; logout limpa imediatamente a cópia em memória; uma recarga completa exige novo login quando a persistência está bloqueada.

Como verificar: simule `getItem`, `setItem` e `removeItem` lançando e confirme leitura inicial nula, uso do token recém-autenticado em memória e limpeza sem exceção. O teste comportamental transpila e executa o helper contra esse armazenamento simulado, e outro teste impede novos acessos diretos nos oito consumidores. Frontend 69/69 e build de 2.313 módulos passaram.

## BUG 166 — Guia de score podia derrubar a Central Financeira

Categoria: Funcional/Interface/Resiliência. Severidade: Baixa. Status: corrigido e testado. Arquivos `frontend/src/pages/CentralFinanceira.tsx`, `frontend/tests/financial-action-errors.test.mjs` e `meta/score-guide-storage-checks.json`.

Causa e impacto: a inicialização da Central Financeira consultava `localStorage` diretamente no lazy initializer do estado. Um `SecurityError` durante o render derrubava a página no Error Boundary. A ação de dispensar o guia também gravava antes de fechar; com armazenamento indisponível, o usuário não conseguia ocultá-lo.

Como reproduzir antes da correção: faça `localStorage.getItem` lançar e abra uma análise financeira; a Central não renderiza. Se apenas `setItem` lançar, clique para dispensar o guia; a exceção interrompe o handler antes de ocultá-lo.

Correção: leitura e gravação da preferência têm helpers protegidos. Falha de leitura apresenta o guia por padrão; falha de gravação não impede o fechamento na sessão atual.

Como verificar: simule separadamente falhas de leitura e escrita. A Central deve carregar, e o botão deve fechar o guia em ambos os casos. A regressão cobre os dois `try/catch`, o fallback e a ordem fechamento após tentativa de persistência. Frontend 70/70 e build de 2.313 módulos passaram.

## BUG 167 — Cache inválido podia ocultar ou derrubar a análise da IA

Categoria: Funcional/Validação/Resiliência. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/pages/AnaliseMercadoOportunidade.tsx`, `frontend/tests/ai-analysis-cache.test.mjs` e `meta/ai-analysis-cache-validation-checks.json`.

Causa e impacto: a leitura usava `sessionStorage.getItem(...) || localStorage.getItem(...)` dentro de um único `try`. Se o armazenamento de sessão lançasse, a cópia persistente válida nunca era consultada. JSON sintaticamente válido era aceito sem esquema; `{}` ou comparáveis nulos alcançavam o render e podiam provocar erro em propriedades obrigatórias.

Como reproduzir antes da correção: mantenha uma análise válida em `localStorage`, faça `sessionStorage.getItem` lançar e abra sua URL; a tela informa que não encontrou a análise. Alternativamente, grave `{}` ou `{"selected":null}` na chave e abra a rota para provocar falha durante o render.

Correção: cada armazenamento é consultado e analisado em isolamento. Conteúdo inválido em um não impede testar o seguinte. O contexto exige ID correspondente à URL, item principal válido, até 250 comparáveis válidos, metadados limitados e até 25 diagnósticos com fonte limitada.

Como verificar: simule sessão indisponível com cópia local válida; depois teste JSON corrompido, ID divergente, item ausente, 251 comparáveis ou diagnóstico excessivo. O primeiro cenário deve abrir a análise e os demais devem mostrar o estado seguro “Análise não encontrada”. Frontend 72/72 e build de 2.313 módulos passaram.

## BUG 168 — Miniatura adulterada no cache permitia egress do navegador

Categoria: Segurança/SSRF no cliente/Privacidade. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/pages/AnaliseMercadoOportunidade.tsx`, `frontend/tests/external-url.test.mjs` e `meta/ai-analysis-thumbnail-policy-checks.json`.

Causa e impacto: a API e a lista da IA filtravam miniaturas, mas a página de análise confiava novamente no valor salvo no armazenamento do navegador e o entregava diretamente ao `<img>`. Um script/extensão ou manipulação manual do cache podia usar `http://127.0.0.1`, IP privado ou metadata link-local e induzir uma requisição da estação do usuário.

Como reproduzir antes da correção: altere `selected.thumbnail` do contexto local para `http://127.0.0.1:8080/private`, mantenha o restante do objeto válido e abra a análise. O navegador tentava carregar o destino como imagem.

Correção: `SafeImage` reaplica `safeExternalHttpsUrl` imediatamente antes de renderizar. Somente HTTPS público sem credenciais e em porta padrão produz `<img>`; valores recusados mostram o placeholder. O estado de falha também é reiniciado quando a origem segura muda.

Como verificar: teste HTTP, localhost, redes privadas, link-local, credenciais, porta alternativa e uma URL HTTPS pública. Os casos inseguros não devem criar elemento de imagem com essa origem; o público deve renderizar com `referrerPolicy=no-referrer`. Frontend 73/73 e build de 2.313 módulos passaram.

## BUG 169 — Resource expunha caminhos locais de foto fora do namespace gerenciado

Categoria: Segurança/Exposição de arquivos/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `OpportunityPhotoPathPolicy.php`, `OpportunityResource.php`, `OpportunityService.php`, `OpportunityTest.php` e `meta/opportunity-photo-path-policy-checks.json`.

Causa e impacto: o serviço de exclusão reconhecia como gerenciado apenas o namespace `opportunities/`, mas o resource convertia qualquer caminho não HTTP do banco em `/storage/<caminho>`. Registros legados, importados ou corrompidos como `documents/tenant/private.jpg` ou `opportunities/../documents/private.pdf` eram publicados ao cliente e podiam referenciar arquivos de outra área do disco público.

Como reproduzir antes da correção: insira uma foto ligada à oportunidade com um dos caminhos acima e consulte `GET /api/opportunities/{id}`. `cover_url` e `photos[].url` continham o caminho convertido em `/storage/...`.

Correção: uma política compartilhada permite somente segmentos seguros sob `opportunities/`, sem `..`, barras invertidas, controles ou outro namespace. O resource omite entradas locais recusadas, e a exclusão usa exatamente a mesma política, evitando divergência futura. URLs externas continuam passando por `ExternalImageUrlPolicy`.

Como verificar: crie registros com traversal, namespace `documents/` e `opportunities/tenant/safe-photo.jpg`. Os dois primeiros devem ser omitidos inclusive como capa; o terceiro deve retornar `/storage/opportunities/tenant/safe-photo.jpg`. Oportunidades 22/170, Pint e suíte PHP completa 301/2.266 passaram.

## BUG 170 — Análise financeira contornava as políticas de URL da capa

Categoria: Segurança/Exposição de arquivos/SSRF no cliente. Severidade: Alta. Status: corrigido e testado. Arquivos `InvestmentAnalysisResource.php`, `InvestmentAnalysisTest.php` e `meta/investment-cover-url-policy-checks.json`.

Causa e impacto: embora `OpportunityResource` filtrasse capas, `InvestmentAnalysisResource` possuía uma segunda implementação que retornava qualquer URL HTTP/HTTPS sem validação e qualquer caminho local como `/storage/...`. A Central Financeira e o Wizard consomem esse resource e entregavam `cover_url` diretamente ao `<img>`. Um registro legado/corrompido podia induzir egress do navegador para loopback/rede privada ou expor outro namespace do storage público.

Como reproduzir antes da correção: associe à oportunidade da análise uma capa `https://127.0.0.1/private.jpg`, `opportunities/../documents/private.jpg` ou `documents/tenant/private.jpg`; consulte `GET /api/investment-analyses/{id}`. O valor era retornado em `data.opportunity.cover_url`.

Correção: o resource financeiro agora usa `ExternalImageUrlPolicy` para imagens externas e `OpportunityPhotoPathPolicy` para locais, exatamente como o resource principal. Valores recusados retornam `null`; HTTPS público e caminho legítimo sob `opportunities/` continuam funcionais.

Como verificar: a regressão alterna a mesma capa entre loopback, traversal, namespace alheio, caminho local legítimo e HTTPS público. Os três primeiros retornam `null`, e os dois últimos retornam as URLs esperadas. Análises 11/87, Pint e suíte PHP completa 302/2.280 passaram.

## BUG 171 — Análise financeira expunha campos internos do banco

Categoria: Segurança/Privacidade/API. Severidade: Baixa. Status: corrigido e testado. Arquivos `app/Http/Resources/InvestmentAnalysisResource.php`, `tests/Feature/InvestmentAnalysisTest.php` e `meta/investment-analysis-data-minimization-checks.json`.

Causa e impacto: o resource começava por todos os atributos do modelo e devolvia relações Eloquent inteiras. A resposta incluía `user_id`, `deleted_at`, `investment_analysis_id` e `user_id` do custo, mesmo sem uso na interface. Além da exposição desnecessária da estrutura interna, qualquer coluna futura seria publicada automaticamente.

Como reproduzir antes da correção: autentique-se com o proprietário, crie uma análise com financiamento, custo e resultado e consulte `GET /api/investment-analyses/{id}`. Observe `data.user_id`, `data.deleted_at`, `data.financing_detail.investment_analysis_id`, `data.costs[0].user_id` e chaves equivalentes nas relações.

Correção: o registro principal e cada relação usam allowlists próprias. IDs de itens necessários às ações da interface e todos os valores financeiros públicos foram preservados; identificadores de propriedade, exclusão lógica e vínculos internos foram removidos.

Como verificar: repita a consulta e confirme que banco, cenário, descrição do custo e lucro continuam presentes, enquanto todos os caminhos internos acima estão ausentes. `test_analysis_resource_omits_internal_ownership_and_relation_keys` cobre o contrato. Análises 12/106, Pint e suíte PHP completa 303/2.299 passaram.

## BUG 172 — Etapas de projeto expunham campos internos do banco

Categoria: Segurança/Privacidade/API. Severidade: Baixa. Status: corrigido e testado. Arquivos `ProjetoEtapaResource.php`, `ProjetoResource.php`, `ProjetoController.php`, `ManualProjectTest.php` e `meta/project-stage-data-minimization-checks.json`.

Causa e impacto: a etapa era devolvida como model Eloquent integral na relação do projeto e nas respostas de criação/edição. Isso publicava `tenant_id`, `projeto_id`, `deletado_flg`, `data_inclusao` e `data_modificacao`, além de tornar qualquer futura coluna pública por padrão.

Como reproduzir antes da correção: crie um projeto e examine `data.etapas[0]`; depois chame `POST /api/projetos/{id}/etapas` e `PATCH /api/projetos-etapas/{id}`. Os cinco campos internos apareciam nas respostas.

Correção: um resource dedicado lista somente ID e campos operacionais da etapa — nome, descrição, ordem, orçamento, custo, datas, status e progresso — e é usado nos três caminhos de resposta.

Como verificar: repita as três chamadas, confirme os dados operacionais e verifique a ausência dos cinco campos internos. `test_project_stage_responses_omit_tenant_and_internal_relation_fields` cobre todos os caminhos. Projetos relacionados 33/316, Pint e suíte PHP completa 304/2.320 passaram.

## BUG 173 — CORS ignorava o endereço configurado do frontend

Categoria: Segurança/Configuração/Funcional. Severidade: Média. Status: corrigido e testado. Arquivos `CorsOriginPolicy.php`, `config/cors.php`, `.env.example`, `ApiHeadersTest.php` e `meta/cors-origin-configuration-checks.json`.

Causa e impacto: a allowlist continha apenas `localhost` e `127.0.0.1` nas portas 5173/5175. Alterar `FRONTEND_URL` para o domínio implantado não alterava CORS, então preflights legítimos eram bloqueados. Uma liberação manual com `*` poderia autorizar origens não previstas.

Como reproduzir antes da correção: defina `FRONTEND_URL=https://app.example.test`, gere o cache de configuração e envie `OPTIONS /api/auth/me` com `Origin: https://app.example.test` e `Access-Control-Request-Method: GET`. A resposta não inclui `Access-Control-Allow-Origin` para esse domínio.

Correção: a allowlist usa o frontend configurado, aceita origens adicionais explícitas e conserva as origens Vite apenas em `local`/`testing`. Uma política central exige origem HTTP(S) sem credenciais, caminho, query ou fragmento e remove wildcard e duplicatas.

Como verificar: repita o preflight configurado e confirme o header com a mesma origem; use `https://evil.example.test` e confirme ausência do header. Teste também wildcard, JavaScript, credenciais, caminho e query. `config:cache`/`config:clear`, API 6/64, Pint e suíte PHP completa 306/2.330 passaram.

## BUG 174 — Lockfile frontend mantinha patches compatíveis atrasados

Categoria: Qualidade/Dependências. Severidade: Baixa. Status: corrigido e testado. Arquivos `frontend/package-lock.json`, `meta/frontend-dependency-refresh-checks.json` e `meta/npm-outdated-current.json`.

Causa e impacto: duas dependências diretas estavam abaixo da versão aceita pelas próprias faixas do manifesto. Não havia advisory associado, mas o lockfile deixava correções de manutenção disponíveis sem aplicação.

Como reproduzir antes da correção: execute `npm outdated --json --long` em `frontend`. A saída mostra `@types/react` atual 18.3.28/desejada 18.3.31 e `postcss` atual 8.5.30/desejada 8.5.31.

Correção: os dois patches foram atualizados no lockfile. Uma reinstalação integral por `npm ci --ignore-scripts` confirmou que o projeto não depende da autorização do postinstall sinalizado para compilar. Versões principais posteriores não foram misturadas com essa manutenção.

Como verificar: execute instalação limpa com scripts desativados, `npm audit`, `npm test`, `npm run build` e `npm outdated`. Audit deve retornar zero; testes 73/73 e build 2.313 módulos devem passar; os dois pacotes devem estar nas versões desejadas. Nove candidatos principais continuam visíveis para migração futura.

## BUG 175 — Matriz de segurança omitia rotas implementadas durante a auditoria

Categoria: Qualidade da auditoria/Inventário. Severidade: Baixa. Status: corrigido e verificado. Arquivos `meta/SECURITY_MATRIX.md` e `meta/final-regression-review-checks.json`.

Causa e impacto: a matriz inicial não havia sido reconciliada após a implementação de cadastro confirmado, sessões, recuperação de senha, diagnóstico de fontes, documentos, calendário e tarefas. Dezesseis caminhos distintos da API existiam no roteador, mas não apareciam como linhas da tabela principal. As proteções estavam implementadas e tinham testes, porém o artefato exigido para revisão e operação era incompleto.

Como reproduzir antes da correção: normalize os parâmetros de `artisan route:list --path=api --json` e compare os caminhos com a segunda coluna das tabelas de `SECURITY_MATRIX.md`. A diferença continha 16 caminhos, incluindo `/api/auth/password/reset`, `/api/documentos`, `/api/eventos-calendario` e `/api/tarefas`.

Correção: a matriz recebeu autenticação, autorização, validação, limites e evidências específicas para todos os caminhos ausentes. A comparação repetida encontrou 59/59 caminhos distintos da API, nenhuma ausência e nenhuma entrada obsoleta.

Como verificar: gere novamente o inventário JSON, normalize nomes dentro de `{}` e compare com a matriz. O resultado esperado é `ACTUAL_PATHS=59`, `DOCUMENTED_API_PATHS=59`, `MISSING=0` e `STALE=0`. Na mesma revisão, 306/306 testes PHP, 73/73 testes frontend, build de 2.313 módulos, Pint, 191 arquivos PHP e as auditorias de dependência passaram.

## BUG 176 — Identificador textual de tarefa causava erro interno

Categoria: API/Validação/Tratamento de erro. Severidade: Baixa. Status: corrigido e testado. Arquivos `ValidateResourceIdentifiers.php`, `RouteIdentifierTest.php` e `meta/task-route-identifier-checks.json`.

Causa e impacto: `Route::apiResource('tarefas', ...)` nomeia o parâmetro como `{tarefa}`, mas o middleware compartilhado validava apenas `id`, `propriedade`, `projeto`, `despesa` e `venda`. O controller exige `int $id`; assim, um texto, número negativo ou inteiro excessivo chegava ao argumento tipado e produzia `TypeError`/HTTP 500. A resposta pública era neutra, mas o erro poluía logs, desperdiçava recursos e classificava entrada inválida como falha do servidor.

Reprodução comprovada antes da correção: autentique uma conta válida e execute `GET /api/tarefas/not-a-number`. A regressão recebeu 500 e capturou `TarefaController::show(): Argument #1 ($id) must be of type int, string given`.

Correção: `{tarefa}` e, por defesa adicional, `{tokenId}` entraram na allowlist numérica do middleware. Valores precisam ser inteiros positivos representáveis antes de alcançar controller ou consulta. O constraint específico da rota de sessão continua ativo.

Como verificar: repita GET e DELETE de tarefa com `not-a-number`, `-1` e um inteiro maior que a faixa aceita; todos devem retornar 404 sem exceção. A regressão inicial passou com 42 asserções; a revisão posterior ampliou-a para propriedades, projetos, despesas, vendas, tarefas, documentos, fornecedores, calendário, oportunidades, análises, financiamentos, custos e resultados, totalizando 75 asserções. A suíte completa atual passou 308/2.388 e o Pint aprovou os arquivos. Evidência adicional: `meta/resource-identifier-coverage-checks.json`.

## BUG 177 — Normalização ITBI aceitava valores incompatíveis com o schema

Categoria: Integridade/Compatibilidade entre bancos/Importação. Severidade: Média. Status: corrigido e testado. Arquivos `ItbiTransactionNormalizerService.php`, `ImportSecurityTest.php`, `ItbiTransactionNormalizerTest.php` e `meta/itbi-schema-bound-normalization-checks.json`.

Causa e impacto: SQLite não aplica os comprimentos de `VARCHAR` nem a precisão dos `DECIMAL` como MariaDB/MySQL estrito. O normalizador encaminhava textos e números sem conferir os limites da migration. Assim, o mesmo CSV podia terminar como `completed` localmente e falhar parcialmente ou abortar linhas no banco de produção. A identidade, o endereço normalizado e o preço por m² também podiam exceder suas colunas.

Reprodução comprovada antes da correção: envie um CSV com uma linha válida e outra contendo 181 caracteres em `Logradouro`. No SQLite, a resposta retornava `batch.status=completed` e gravava as duas linhas, embora `street_name` seja `VARCHAR(180)`. A nova regressão esperava `completed_with_errors` e falhou antes da correção.

Correção: a normalização recusa, antes do banco, campos textuais acima de cada coluna, UF fora de duas letras, CEP acima de 12 dígitos, valores/áreas negativos ou além da precisão decimal, `price_m2` não finito/acima do limite, endereço normalizado acima de 255 e descrições `TEXT` acima de 65.535 bytes. A linha bruta continua no arquivo de auditoria; o importador conta somente a linha inválida como falha e preserva as válidas.

Como verificar: use o CSV descrito e confirme uma linha importada, uma falha, status parcial e ausência de `AUDIT-OVERSIZED`. Os testes unitários também cobrem moeda, área, UF, CEP, tipo, endereço composto, preço por m², `TEXT` e nome da fonte. Importação/normalizador 37/154, suíte completa 308/2.355, frontend 73/73, build de 2.313 módulos e Pint passaram.

## Cobertura adicional — tipos dos filtros de coleção

A revisão posterior enviou arrays em filtros escalares de projetos, vendas, despesas, categorias, fornecedores, documentos e oportunidades. Todos os sete endpoints responderam 422 com erro no campo correspondente; nenhum valor chegou ao query builder nem causou HTTP 500. A proteção já existia em `ValidatePagination`, e a prova foi incorporada a `CollectionFilterValidationTest` com 21 asserções. Evidência: `meta/collection-filter-type-checks.json`.

## BUG 178 — Binding e policies revelavam a existência de recursos antes da confirmação e da autorização

Categoria: Segurança/Autorização/Enumeração. Severidade: Média. Status: corrigido e testado. Arquivos `bootstrap/app.php`, `OpportunityPolicy.php`, `InvestmentAnalysisPolicy.php`, `UpdateOpportunityRequest.php`, `RegistrationVerificationTest.php`, `OwnershipMatrixTest.php` e regressões relacionadas.

Causa e impacto: a prioridade efetiva executava rate limiting e `SubstituteBindings` antes de `active-account` e `verified-account`. Uma conta recém-cadastrada e ainda não confirmada recebia 404 para um identificador inexistente, enquanto um registro existente avançava até a barreira de confirmação e recebia 403, permitindo inferir existência. Depois da confirmação, as policies de oportunidade e análise devolviam 403 para IDs alheios e 404 para IDs ausentes, preservando o mesmo oráculo de enumeração. `UpdateOpportunityRequest` convertia a resposta detalhada da policy em booleano e perderia um status de ocultação.

Reprodução anterior: autenticar uma conta com `requires_email_verification=true`, sem `email_verified_at`, e chamar `PATCH /api/opportunities/1/favorite` sem o registro 1; a resposta observada foi 404 em vez de `email_verification_required`. Em uma conta confirmada, consultar ou alterar uma oportunidade/análise existente de outro usuário retornava 403, enquanto um ID inexistente retornava 404.

Correção: a prioridade global agora executa conta ativa e confirmação imediatamente depois da autenticação, antes de throttles específicos, binding e `can`. As policies de oportunidade e análise usam `denyAsNotFound()` quando o proprietário ou a oportunidade ativa não correspondem. O Form Request de atualização preserva essa resposta ao autorizar via Gate, em vez de reduzi-la a booleano.

Verificação: uma regressão percorre dinamicamente todos os métodos de todas as rotas empresariais e exige 403 com `email_verification_required` antes de binding/controller. A matriz de propriedade exige 404 em leitura e mutação alheias e compara snapshots de seis tabelas para comprovar ausência de escrita. Testes focados: 57/801; suíte completa: 311/2.761; Pint aprovado. Evidência: `meta/prebinding-authorization-checks.json`.

## BUG 179 — Falha de login público apagava uma sessão válida existente

Categoria: Funcional/Autenticação/Privacidade. Severidade: Média. Status: corrigido e testado. Arquivos `frontend/src/lib/api.ts`, `frontend/src/lib/authRequest.ts` e `frontend/tests/auth-token-storage.test.mjs`.

Causa e impacto: o interceptor anexava o bearer atual a toda chamada, inclusive login, cadastro, consulta de CEP, recuperação e confirmação. Qualquer resposta 401 limpava o token. Assim, um usuário com sessão válida em outra aba que abrisse `/login` e errasse a senha perdia a sessão local válida; o token também era transmitido sem necessidade para endpoints públicos.

Correção: uma política explícita reconhece somente as rotas públicas de autenticação. Essas requisições não recebem `Authorization`. Um 401 só limpa a sessão quando a requisição não é pública e efetivamente enviou um bearer. Reenvio de confirmação permanece autenticado.

Verificação: a regressão executa todos os caminhos públicos, confirma que nenhum autoriza limpeza mesmo diante de 401, confirma que `/auth/email/resend` não é público, que `/auth/me` com bearer expirado limpa e que uma chamada sem bearer não apaga sessão. Frontend 74/74 e build de produção com 2.314 módulos passaram. Evidência: `meta/public-auth-session-isolation-checks.json`.

## BUG 180 — Limite de login por conta podia ser contornado distribuindo os IPs

Categoria: Segurança/Autenticação/Rate limiting. Severidade: Média. Status: corrigido e testado. Arquivos `AppServiceProvider.php`, `SecurityRegressionTest.php` e `meta/distributed-login-rate-limit-checks.json`.

Causa e impacto: o limite chamado `login-account` usava uma chave formada por e-mail e IP. Um atacante que alternasse IP criava um orçamento novo para cada origem; os limites de 5 por combinação e 10 por IP continuavam válidos, mas não havia teto compartilhado pelo e-mail alvo.

Reprodução anterior: enviar 31 senhas incorretas para `distributed-target@example.test`, cada uma com um `REMOTE_ADDR` fictício distinto. As 31 respostas eram 401; nenhuma atingia 429.

Correção: foi acrescentado um terceiro orçamento de 30 tentativas por hora, usando somente o hash SHA-256 do e-mail normalizado. Os limites anteriores permanecem para conter abuso concentrado. A chave de cache não armazena o endereço em claro.

Verificação: as primeiras 30 tentativas distribuídas recebem 401 e a 31ª, vinda de outro IP, recebe 429. O teste existente continua comprovando o limite mais rígido por IP. Testes focados 2/32, suíte completa 312/2.792 e Pint passaram. Evidência: `meta/distributed-login-rate-limit-checks.json`.

## BUG 181 — Atualizações concorrentes podiam invalidar a conclusão de uma tarefa

Categoria: Lógica/Concorrência/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `app/Services/TarefaService.php`, `tests/Feature/TarefaTest.php`, `meta/concurrency-harness/*task-stage*` e `meta/task-stage-concurrency-mariadb.json`.

Causa e impacto: o controller carregava a tarefa antes da transação. O serviço bloqueava o projeto, mas atualizava e calculava `data_conclusao` usando esse objeto possivelmente antigo, sem bloquear ou recarregar a própria tarefa. Se uma requisição marcasse a tarefa como concluída enquanto outra alterava apenas o título, a segunda podia usar o estado anterior `pendente`, manter o status já persistido como `concluida` e limpar a data de conclusão. O registro terminava internamente contraditório.

Como reproduzir antes da correção: carregue a mesma tarefa pendente em duas requisições; envie simultaneamente `PATCH {"status":"concluida"}` e `PATCH {"titulo":"Título concorrente"}`. Quando a conclusão commitava primeiro e a edição continuava com o model antigo, a tarefa podia terminar com `status=concluida` e `data_conclusao=null`.

Correção: a atualização conserva a ordem de lock projeto→tarefa, obtém a linha atual com `lockForUpdate`, revalida os pais e calcula os campos derivados sobre essa cópia bloqueada. PATCHes em campos diferentes preservam ambos os efeitos; no mesmo campo, a política documentada é último commit vence.

Como verificar: o teste determinístico simula um model obsoleto e exige que a data já gravada seja preservada. O harness isolado executou três pares de processos realmente sobrepostos em MariaDB: conclusão+edição de tarefa, dois títulos conflitantes e duas atualizações conflitantes de etapa. Todos responderam 200; tarefa manteve data, o último commit venceu os conflitos e o orçamento da etapa permaneceu igual ao total do projeto. Suíte completa 313/2.793, frontend 74/74, build de 2.314 módulos e Pint passaram.

## BUG 182 — Snapshot antigo permitia criar filhos após exclusão do pai

Categoria: Lógica/Concorrência/Integridade. Severidade: Média. Status: corrigido e testado. Arquivos `DespesaService.php`, `DocumentoService.php`, `EventoCalendarioService.php`, `ProjetoService.php`, `TarefaService.php`, `meta/concurrency-harness/worker.php`, harness `parent-races` e `meta/parent-deletion-concurrency-mariadb.json`.

Causa e impacto: os serviços primeiro adquiriam lock na linha do pai sem filtrar exclusão e depois revalidavam `ativos()` por uma consulta comum. No isolamento `REPEATABLE READ` do MariaDB, essa segunda leitura podia usar um snapshot anterior à espera pelo lock. Foi comprovado um interleaving em que a criação de despesa retornou 201, a exclusão do projeto retornou 200 e restou uma despesa ativa ligada a projeto logicamente excluído.

Como reproduzir antes da correção: em dois processos e MariaDB/InnoDB, envie simultaneamente `POST /api/despesas` para um projeto e `DELETE /api/projetos/{id}`, favorecendo a exclusão enquanto a criação já iniciou a transação. O resultado observado foi `[201, 200]`, `project_deleted=true` e `active_expenses=1`.

Correção: toda revalidação de propriedade/projeto após aquisição coordenada dos locks agora também usa `lockForUpdate()`, forçando leitura atual em vez do snapshot consistente antigo. O padrão foi corrigido em despesas, documentos, eventos, tarefas e movimentação de projeto.

Como verificar: o harness reinicializa o schema e executa 16 pares realmente sobrepostos: criação de projeto, despesa, documento, tarefa e evento contra exclusão do pai; movimentação contra exclusão da origem/destino; e atualização contra exclusão do projeto. Cada disputa roda com exclusão favorecida e mutação favorecida. Todos os invariantes passaram: ou a mutação é recusada e o pai é excluído, ou a mutação vence e a exclusão é bloqueada/absorve os filhos; não há órfão ativo, arquivo residual ou referência ativa a pai excluído. Suíte PHP 313/2.793, frontend 74/74, build 2.314 módulos e Pint passaram.

## Cobertura adicional — Tarefas e Calendário

As duas telas foram validadas por automação de navegador contra fixtures em memória, sem alterar dados reais. Tarefas cobriu criação, recarga, busca, filtro, exclusão e `input[type=date]`; Calendário cobriu criação, recarga, navegação, edição, exclusão e evento multidiário. Em cada fluxo, a primeira gravação recebeu HTTP 503 e o diálogo permaneceu aberto com os valores preservados e a mensagem visível; a tentativa seguinte persistiu. Ambos passaram em tema escuro, viewport 375x812, sem overflow ou erro de página. Evidências: `meta/task-ui-crud-checks.json`, `meta/calendar-ui-crud-checks.json`, `meta/task-ui-crud-mobile.png` e `meta/calendar-ui-crud-mobile.png`.

## BUG 183 — Falha de recarga confundia visão desatualizada com falha da operação

Categoria: Funcional/Lógica/Interface. Severidade: Média. Status: corrigido e testado. Arquivos `Calendario.tsx`, `Documentos.tsx`, `Fornecedores.tsx`, `Despesas.tsx`, `AnaliseImovel.tsx`, `Tarefas.tsx`, `Oportunidades.tsx`, `frontend/tests/mutation-refresh-feedback.test.mjs` e `meta/mutation-refresh-feedback-checks.json`.

Causa e impacto: 16 handlers executavam uma mutação e imediatamente recarregavam a coleção dentro do mesmo fluxo de sucesso. Os loaders capturavam internamente a falha da consulta e não devolviam seu estado. A interface podia manter simultaneamente uma confirmação genérica e um erro de carregamento, sem explicar que o servidor já havia aceitado a alteração. Repetir um cadastro, upload ou ação acreditando que a primeira tentativa falhou poderia gerar duplicidade ou uma segunda operação desnecessária.

Como reproduzir antes da correção: intercepte uma das ações cobertas, devolva sucesso para POST/PUT/PATCH/DELETE e HTTP 503 somente para o GET imediatamente seguinte. Exemplos: criar um evento, enviar um documento, cadastrar fornecedor, marcar despesa como paga, salvar imóvel, concluir tarefa ou arquivar oportunidade. A mutação era confirmada no servidor, mas a interface não classificava explicitamente a falha como uma recarga posterior nem orientava evitar a repetição.

Correção: os sete loaders retornam `true` quando publicam a coleção atual e `false` quando a consulta falha, é cancelada ou fica obsoleta. Cada um dos 16 handlers inspeciona esse resultado. Se a mutação foi confirmada e o GET falhou, a mensagem afirma que a alteração ocorreu, informa que a lista não pôde ser atualizada e orienta usar Atualizar antes de repetir.

Como verificar: configure a sequência sucesso da mutação → HTTP 503 do GET e confirme a mensagem de operação concluída com visão desatualizada; depois recarregue e confirme o estado persistido. A regressão estrutural percorre os 16 handlers e os sete loaders. Ela passou 2/2; a suíte frontend passou 76/76, o build compilou 2.314 módulos e as automações visuais de Tarefas e Calendário continuaram aprovadas.

## BUG 185 — Relatórios avançados sem trilha filtrada nem exportação do mesmo conjunto

Categoria: Funcional/Interface/Segurança. Severidade: Média. Status: corrigido e testado. Arquivos `FinancialLedgerController.php`, `FinancialLedgerRequest.php`, `Relatorios.tsx`, `reportExport.ts`, `FinancialLedgerTest.php` e `frontend/tests/reportExport.test.mjs`. Rota `GET /api/reports/ledger`; painel permanece em `/api/dashboard`.

Causa e impacto: a tela de relatórios só exibia o resumo acumulado. Controles de período/projeto, paginação e trilha contábil não existiam; o CSV não refletia um recorte filtrado. Sem validação de tenant no filtro de projeto, um ID alheio poderia ser tratado como conjunto vazio em vez de recusado.

Correção: o ledger autentica conta ativa/verificada, exige tenant, valida período/projeto/`page`/`per_page` e monta aquisições, despesas não canceladas e vendas finais da própria conta. Totais pertencem ao conjunto filtrado; a página só fatia a lista. Projeto de outro tenant retorna 422. A UI mantém o painel acumulado e acrescenta filtros, paginação e exportação que percorre as páginas do mesmo filtro, com proteção contra fórmula.

Verificação: `FinancialLedgerTest` cobre isolamento, período, projeto, paginação com totais estáveis, intervalo invertido, `per_page` 101, página excessiva, anônimo e usuário sem tenant. A suíte PHP passou 321/2.881; frontend 81/81; build 2.314 módulos. O painel `/api/dashboard` permanece sem filtro. Reconciliação visual com fixture descartável continua pendente. Evidência: `meta/financial-ledger-checks.json`.

## BUG 186 — IA Inteligente sem conversa, recomendação explicável nem visualização 3D por imóvel

Categoria: Funcional/Arquitetura. Severidade: Alta. Status: evoluído sobre o motor existente; reconstrução automática 3D permanece fora de escopo. Arquivos `PropertyQueryInterpreter.php`, `AiConversationService.php`, `PropertyMatchRanker.php`, `PropertyVisualizationService.php`, `PhotoSceneAnalyzer.php`, `IAInteligente.tsx`, `PropertyViewer.tsx`.

Causa e impacto: a “IA” era radar de filtros + scraper + comparáveis. Não havia linguagem natural, continuidade de critérios, justificativa de recomendação nem rota `/imoveis/{id}/3d`. Qualquer avanço generativo sem esses trilhos inventaria anúncios ou misturaria modelos entre imóveis.

Correção: a conversa interpreta português em filtros obrigatórios/preferidos, consulta carteira e reutiliza `GET /api/ai-property-search`, recusa preço acima do teto e explica recomendações. Cada imóvel/lead tem subpágina 3D com GLB autenticado ou fallback explícito. Fotos são classificadas pelo nome do arquivo, com origem e limitações.

Verificação: testes unitários do interpretador, `AiConversationTest`, `PropertyVisualizationTest` e `frontend/tests/propertyVisualization.test.mjs`. Fotogrametria, decoração e visita falada não foram marcadas como concluídas.

## BUG 187 — Análise do anúncio sem 3D, aviso de API paga e radar perdido no Voltar

Categoria: Funcional/Interface. Severidade: Alta. Status: corrigido. Arquivos `AnaliseMercadoOportunidade.tsx`, `IAInteligente.tsx`, `ListingLayoutViewer.tsx`, `browserStore.ts`, `OpenGovernmentTransactionProvider.php`, `EconomicIndicatorService.php`, `PropertyMarketDataService.php`, `DataSourceStatusController.php`.

Causa e impacto: o efeito de persistência do radar gravava o estado vazio no mount (Strict Mode apagava sessionStorage). A análise do hash do anúncio não tinha modelo 3D. Cartório/ITBI/DataZAP apareciam como API paga/oficial mesmo com catálogos públicos disponíveis.

Correção: o radar e a análise usam sessionStorage+localStorage, pulam a primeira gravação vazia e levam o payload no `navigate` state. A ficha do anúncio gera layout 3D ilustrativo. ITBI em CKAN (dados abertos) e BCB SGS/BrasilAPI entram sem chave; DataZAP/FipeZAP permanecem comerciais e fora da análise.

Verificação: `MarketPublicApisTest`, `MarketProviderTest`, `DataSourceStatusTest`, `frontend/tests/radar-state.test.mjs`. Fotogrametria continua fora de escopo.

## BUG 189 — 3D em caixas e chat poluído

Categoria: Produto/Interface. Severidade: Alta. Status: corrigido. Arquivos `propertySceneSpec.ts`, `PropertyMaquetteViewer.tsx`, `ListingLayoutViewer.tsx`, `PropertyViewer.tsx`, `AiConsultantPanel.tsx`, `IAInteligente.tsx`, `AiConversationService.php`, `InventoryListingSearch.php`, `PropertyVisualizationService.php`.

Causa: o layout 3D era um diagrama de volumes; o chat empilhava exemplos longos, radar e input numa linha. Anúncio de portal na conversa não tinha caminho 3D.

Correção: maquete com exterior/interior/planta e selo (real, planta 2D, paramétrico, ilustrativo). Chat com composer, radar em aba, cards com origem e Ver 3D. Sem fotogrametria, sem ONR/DataZAP/leilão fingidos.

Verificação: `frontend/tests/propertySceneSpec.test.mjs`, `frontend/tests/ai-chat-ux.test.mjs`, `test_property_with_rooms_exposes_parametric_maquette`, `AiConversationTest`.

## BUG 188 — Total investido soma preço de prospecção sem data de compra

Categoria: Funcional. Severidade: Média. Status: corrigido. `DashboardController`, `Propriedade::scopeComAquisicao`, `Financeiro.tsx`, `DashboardTest`.

Causa: `sum(valor_aquisicao)` em todos os imóveis ativos. O Apto Aldeota QA (R$ 480.000, status `prospeccao`, sem `data_aquisicao`) entrou no KPI de `/financeiro`. O ledger já exigia data de aquisição.

Correção: só entra imóvel com `data_aquisicao`. Despesas não canceladas continuam. Prospecção deixa de inflar capital investido.

Verificação: `test_prospect_asking_price_is_not_counted_as_invested_capital`.

## BUG 190 — Visualização 3D só com caixas genéricas; fotos do anúncio ignoradas

Categoria: Funcional/Interface. Severidade: Alta. Status: corrigido no protótipo; sem fotogrametria. Arquivos `PhotoSceneAnalyzer.php`, `PhotoPixelSampler.php`, `SceneBriefBuilder.php`, `SceneGraphFactory.php`, `SceneCommandInterpreter.php`, `PropertyVisualizationService.php`, `PropertyVisualizationController.php`, `PropertyPhotoSceneEditor.tsx`, `PropertyViewer.tsx`, `PropertyVisualizationTest.php`.

Causa e impacto: o visualizador 3D montava uma maquete paramétrica de volumes, mesmo quando o lead tinha fotografias locais autorizadas. A galeria era apresentada como se fosse experiência 3D editável. Não havia grafo de cena, paleta amostrada, comandos que mutassem a geometria nem isolamento explícito de fotos entre imóveis.

Correção: pipeline fotos → análise (nome + histograma GD) → brief estruturado → grafo editável → editor Three.js. Uma foto de cômodo gera só aquele ambiente, rotulado `photo_referenced`. Sem fachada, o exterior é conceitual. Comandos em português pintam/adicionam/restauram a cena; SQL/URL/script são recusados. Fotos originais e a ficha do anúncio não mudam. GLB enviado permanece o único modo fiel. Gaussian Splatting/SfM não foram ligados.

Verificação: `PropertyVisualizationTest` (uma foto, quarto ≠ planta, isolamento de lead/tenant, paredes azuis e restore, GLB fiel), `SceneCommandInterpreterTest`, `frontend/tests/propertyVisualization.test.mjs`. Build frontend: `tsc -b && vite build`, 2329 módulos. Protótipo local em `/leads/30/3d` com `sala-estar-janela-sofa.jpg`. Pendente: fila assíncrona, malha por profundidade, API GPU 3D.

## Diagnóstico 19/10/2026 — plataforma imobiliária (brief marketplace)

Categoria: Produto/Arquitetura. Severidade: n/a (gap, não regressão). Status: documentado, **não implementado**.

O código atual é FlipControl House Flipper (Laravel 13 + React). SPA só autenticada (`App.tsx`): não há `/corretor/:slug` nem `/c/:slug`. Sidebar fixa “House Flipper”. `OpportunityStatus` é funil de investimento, não captação comercial. `public_listing_3d` e `lead_matching` existem só em `config/products.php`. `DataSourceStatusController` declara DataZAP unavailable e leilões not_implemented. Tenant não tem filial. Billing Mercado Pago/Stripe ausente.

Aceite deste item: os arquivos `/meta/PRODUCT_VISION.md`, `REQUIREMENTS.md`, `ARCHITECTURE.md`, `DATABASE.md`, `INTEGRATIONS.md`, `API_SPEC.md`, `USER_FLOWS.md`, `ROADMAP.md`, `SECURITY.md`, `PROGRESS.md` descrevem o gap sem marcar marketplace como concluído.

## Diagnóstico 19/10/2026 — verificação do MVP de captação

Categoria: Segurança/Funcional. Status: verificado no código local (não é o briefing inteiro).

Executado via serviços Laravel em tenants fictícios A/B:

- B não inicia captação no lead de A (404).
- Carteira recusa autorização `pending`.
- Publicar sem plano `public_listing` retorna 403.
- Publicar sem foto `licensed_for_publication` retorna 422.
- Trial corretor + foto licenciada publica.
- JSON público não contém nome, telefone nem documento do proprietário.
- Tenant B não lê listing de A no `TenantScope`.
- Cancelar o plano esconde `/c/{slug}` e mantém o registro da carteira.

Frontend: `tsc -b && vite build` (2335 módulos). Migração `2026_10_19_000300_create_marketplace_tables` aplicada.

Ainda fora: 8 modos de mapa, WhatsApp Cloud, checkout real, roleta, comissões, filiais, editor visual de sites, conectores de portal/leilão.

