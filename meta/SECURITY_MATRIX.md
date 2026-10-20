# Matriz de segurança — em andamento

Inventário revisto em 18/10/2026. Uma regressão percorre todas as rotas e exige fronteira explícita de autenticação, conta ativa e verificação; policies, TenantScope e testes negativos continuam provando autorização por recurso.

| Método | Rota | Autenticação | Autorização | Validação | Limite | Evidência |
|---|---|---|---|---|---|---|
| GET|HEAD | /api/ai-property-search | Sanctum + conta ativa + e-mail verificado | Usuário autenticado; sem persistência | Estado/cidade/tipo/limite validados no controller; limite 1–250 | market-data: 6/min por usuário e 12/min por IP, além do global | AIPropertySearchTest (sucesso e falha segura); teste de throttle; busca real Perdizes registrada em ai-search-runtime-check.json |
| POST | /api/auth/login | Pública | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/auth/logout | Sanctum + conta ativa | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/auth/me | Sanctum + conta ativa | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/auth/register | Pública | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/categorias-despesa | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/despesas | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/despesas | Sanctum + conta ativa | TenantScope; propriedade/projeto/etapa/tarefa/categoria do tenant | FormRequest; vínculos consistentes; pagamento cria data; valores limitados | 120/min global | DomainIntegrityTest e ExpenseUpdateWorkflowTest |
| GET|HEAD | /api/despesas/{despesa} | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PUT|PATCH | /api/despesas/{despesa} | Sanctum + conta ativa | TenantScope; vínculos do tenant consistentes | FormRequest; pagamento preserva data e estados não pagos limpam; recálculo transacional | 120/min global | ExpenseUpdateWorkflowTest e DomainIntegrityTest |
| DELETE | /api/despesas/{despesa} | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET\|HEAD | /api/fornecedores | Sanctum + conta ativa + e-mail verificado | TenantScope; somente tenant atual | Busca/categoria/status e paginação 1–100 validados | 120/min global | FornecedorTest: filtros, paginação e isolamento |
| POST | /api/fornecedores | Sanctum + conta ativa + e-mail verificado | Tenant obrigatório; tenant/flags indevidos ignorados | FornecedorRequest: textos, telefone, e-mail, avaliação 0–5 e booleano | 120/min global | FornecedorTest: CRUD, validação, mass assignment e usuário sem tenant |
| GET\|HEAD | /api/fornecedores/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; ID alheio retorna 404 | ID inteiro positivo pelo middleware | 120/min global | FornecedorTest: leitura alheia bloqueada |
| PUT\|PATCH | /api/fornecedores/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; ID alheio retorna 404 | FornecedorRequest completo; campos opcionais podem ser limpos | 120/min global | FornecedorTest: edição própria e mutação alheia bloqueada |
| DELETE | /api/fornecedores/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; exclusão lógica própria | ID inteiro positivo pelo middleware | 120/min global | FornecedorTest: exclusão própria/alheia e registro preservado com flag |
| DELETE | /api/financing-details/{financingDetail} | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PATCH | /api/financing-details/{financingDetail}/select | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/investment-analyses/{investmentAnalysis} | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PUT | /api/investment-analyses/{investmentAnalysis} | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/investment-analyses/{investmentAnalysis}/costs | Sanctum + conta ativa | Proprietário (policy) | Categoria/descrição/valor limitados; estado pago cria `paid_at` | 120/min global | FinancialValidationTest e OwnershipMatrixTest |
| PUT | /api/investment-analyses/{investmentAnalysis}/financing | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/investment-analyses/{investmentAnalysis}/financing-reference | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/investment-analyses/{investmentAnalysis}/financing-simulations | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/investment-analyses/{investmentAnalysis}/result | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PUT | /api/investment-costs/{investmentCost} | Sanctum + conta ativa | Proprietário (policy) | Contrato completo; pago preserva `paid_at`, pendente/atrasado limpa | 120/min global | FinancialValidationTest, FinancialAtomicityTest e OwnershipMatrixTest |
| DELETE | /api/investment-costs/{investmentCost} | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PUT | /api/investment-results/{investmentResult} | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/itbi-imports | Sanctum + conta ativa + e-mail verificado | Base pública compartilhada; nome original do lote somente com `itbi.import` | Página 1–1.000.000 e `per_page` 1–100; resposta aplica allowlist e omite payload bruto/metadados internos | 120/min global | ImportSecurityTest: acesso, minimização do lote/transações e paginação validada |
| POST | /api/itbi-imports/upload | Sanctum + conta ativa + e-mail verificado | Permissão `itbi.import`; uploader registrado somente em metadado interno | Arquivo obrigatório até 250 MiB e extensão allowlist; parser limita XML/expansão; normalizador aplica comprimentos, bytes, UF, CEP e precisão decimal do schema por linha | `itbi-upload` 2/min por operador + 120/min global | ImportSecurityTest, SpreadsheetSafetyTest e ItbiImportPipelineTest: formatos, XXE/expansão, limites, rollback, parcial e divergência SQLite/MariaDB |
| POST | /api/neighborhood-context | Sanctum + conta ativa | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/opportunities | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/opportunities | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/opportunities/{opportunity} | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PUT|PATCH | /api/opportunities/{opportunity} | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| DELETE | /api/opportunities/{opportunity} | Sanctum + conta ativa | Proprietário (policy) | Policy; caminhos locais restritos a `opportunities/`; quarentena privada e rollback de arquivo/banco | 120/min | BUG 74: sucesso, caminho não gerenciado e falha transacional testados |
| PATCH | /api/opportunities/{opportunity}/archive | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PATCH | /api/opportunities/{opportunity}/discard | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PATCH | /api/opportunities/{opportunity}/favorite | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/opportunities/{opportunity}/investment-analysis | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PATCH | /api/opportunities/{opportunity}/status | Sanctum + conta ativa | Proprietário (policy) | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/projetos | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/projetos | Sanctum + conta ativa | TenantScope; propriedade do tenant | FormRequest; limites, cronologia e término real coerente com conclusão | 120/min global | ManualProjectTest e TemporalValidationTest |
| PATCH | /api/projetos-etapas/{id} | Sanctum + conta ativa + e-mail confirmado | `tenant_id` da sessão e projeto ativo; ID alheio retorna 404 | orçamento 0–999999999999; status em allowlist; progresso inteiro 0–100 | 120/min global | `DomainIntegrityTest`: IDOR, status/progresso inválidos, transação/rollback e atualização legítima |
| POST | /api/projetos/{id}/etapas | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/projetos/{id}/resumo | Sanctum + conta ativa | TenantScope; somente etapas ativas; venda final do tenant | Sem payload; recálculo ignora cancelados/excluídos | 120/min global | DomainIntegrityTest: etapa excluída não reaparece; resumo financeiro coberto |
| GET|HEAD | /api/projetos/{projeto} | Sanctum + conta ativa | TenantScope; somente etapas/tarefas ativas | ID numérico e recurso ativo | 120/min global | DomainIntegrityTest: isolamento e exclusão lógica de etapas |
| PUT|PATCH | /api/projetos/{projeto} | Sanctum + conta ativa | TenantScope; propriedade do tenant | FormRequest; datas efetivas cruzam payload/banco; rejeita coleções aninhadas; conclusão/reabertura sincronizam término real | 120/min global | ManualProjectTest e TemporalValidationTest |
| DELETE | /api/projetos/{projeto} | Sanctum + conta ativa + e-mail confirmado | TenantScope; ID alheio/excluído retorna 404 | transação + lock; encerra etapas, tarefas e eventos; preserva histórico financeiro/documental | 120/min global | BUG 77: coleção de tarefas/eventos e flags dependentes testadas |
| POST | /api/property-market-analysis | Sanctum + conta ativa | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/propriedades | Sanctum + conta ativa + e-mail confirmado | TenantScope; sem tenant recebe 403 | status allowlist; busca ≤120; página ≥1; per_page 1–100 | 120/min global | `PropriedadeTest`: paginação 25+1, filtros 422, tenant ausente e coleção isolada |
| POST | /api/propriedades | Sanctum + conta ativa + e-mail confirmado | Tenant atribuído pela sessão; payload não controla exclusão | request allowlist; números/contagens/endereço/observações limitados; `vendida` exige fluxo de venda | 120/min global | `PropriedadeTest` e `DomainIntegrityTest`: criação/endereço, limites, status financeiro, campos privilegiados e sem tenant |
| GET|HEAD | /api/propriedades/{id}/roi | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /api/propriedades/{propriedade} | Sanctum + conta ativa + e-mail confirmado | TenantScope; ID alheio/excluído retorna 404 | ID inteiro de rota; resposta omite tenant/exclusão | 120/min global | `PropriedadeTest`: leitura própria e IDOR |
| PUT|PATCH | /api/propriedades/{propriedade} | Sanctum + conta ativa + e-mail confirmado | TenantScope; ID alheio/excluído retorna 404; lock da propriedade | contrato limitado; status `vendida` deve equivaler à venda final ativa | 120/min global | `PropriedadeTest` e `DomainIntegrityTest`: atualização, IDOR e invariante de venda |
| DELETE | /api/propriedades/{propriedade} | Sanctum + conta ativa + e-mail confirmado | TenantScope; ID alheio/excluído retorna 404 | transação + lock; projeto, venda ou despesa ativa bloqueia com 409 | 120/min global | `PropriedadeTest`: exclusão própria, IDOR e três classes de vínculo ativo |
| GET|HEAD | /api/vendas | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| POST | /api/vendas | Sanctum + conta ativa | TenantScope; propriedade do tenant | FormRequest; cronologia; datas obrigatórias por estado final; valores limitados; uma venda final ativa por imóvel sob lock | 120/min global | DomainIntegrityTest e TemporalValidationTest; concorrência multiprocesso MariaDB 11.4.10 aprovada (201/422, uma venda final) |
| GET|HEAD | /api/vendas/{venda} | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| PUT|PATCH | /api/vendas/{venda} | Sanctum + conta ativa | TenantScope; venda/propriedade do tenant | FormRequest; cronologia e marcos obrigatórios pelo estado efetivo; venda final única; locks ordenados ao mover | 120/min global | DomainIntegrityTest e TemporalValidationTest; conflito retorna 422 |
| DELETE | /api/vendas/{venda} | Sanctum + conta ativa | TenantScope; relacionamentos validados | FormRequest/controller; paginação 1–100; revisar casos restantes | 120/min; login 5/conta+IP/min, 10/IP/min e 30/conta/h | Revisão de rota; ver testes por módulo |
| GET|HEAD | /sanctum/csrf-cookie | Pública | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | Não aplicado | Revisão de rota; ver testes por módulo |
| GET|HEAD | /storage/{path} | Pública | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | Não aplicado | Revisão de rota; ver testes por módulo |
| PUT | /storage/{path} | Pública | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | Não aplicado | Revisão de rota; ver testes por módulo |
| GET|HEAD | /up | Pública | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | Não aplicado | Revisão de rota; ver testes por módulo |
| GET|HEAD | /{path?} | Pública | Conforme controller | FormRequest/controller; paginação 1–100; revisar casos restantes | Não aplicado | Revisão de rota; ver testes por módulo |

## Rotas complementares verificadas na revisão final

Estas rotas também fazem parte do inventário ativo. A comparação normalizada com `artisan route:list --path=api --json` cobre agora todos os 59 caminhos distintos da API.

| Método | Rota | Autenticação | Autorização | Validação | Limite | Evidência |
|---|---|---|---|---|---|---|
| POST | /api/auth/password/forgot | Pública; resposta anti-enumeração | Apenas conta e tenant ativos recebem tentativa de envio | E-mail normalizado, obrigatório, válido e até 150 caracteres | `password-reset-request` por e-mail/IP | PasswordResetTest: conta existente/inexistente/inativa, limite e falha de transporte |
| POST | /api/auth/password/reset | Pública; token obrigatório | Token hashado, expirável e de uso único; conta e tenant precisam estar ativos | E-mail até 150, token até 255 e senha confirmada de 12–72 caracteres/bytes | `password-reset` por e-mail/IP | PasswordResetTest: sucesso, expiração, reuso, conta inelegível, senha e revogação das sessões |
| GET\|HEAD | /api/auth/postal-code/{postalCode} | Pública | Consulta somente ao provedor HTTPS fixo ViaCEP | Rota aceita exatamente oito dígitos; resposta sem redirect, até 64 KiB e campos/tamanhos allowlist | 30/min por IP, além do limite global | RegistrationVerificationTest e limites de resposta HTTP reais/fakes |
| GET\|HEAD | /api/auth/email/verify/{id}/{hash} | Link público assinado | Assinatura relativa, hash constante, usuário/tenant ativos | ID resolvido no backend; hash comparado com `hash_equals` | 6/min por IP | RegistrationVerificationTest: assinatura, hash, expiração, conta e confirmação idempotente |
| POST | /api/auth/email/resend | Sanctum + conta ativa | Somente a própria sessão; conta já confirmada não reenvia | Sem payload; serviço usa URL assinada/expirável | `verification` | RegistrationVerificationTest e ResendTransportTest |
| POST | /api/auth/logout-all | Sanctum + conta ativa | Remove todos os tokens do próprio usuário e invalida a sessão web atual | Sem payload | 120/min global | SecurityRegressionTest: tokens próprios revogados e sessão encerrada |
| GET\|HEAD | /api/auth/sessions | Sanctum + conta ativa | Lista somente tokens do próprio usuário, sem hash/token secreto | Sem payload | 120/min global | SecurityRegressionTest: isolamento e minimização dos dados de sessão |
| DELETE | /api/auth/sessions/{tokenId} | Sanctum + conta ativa | Busca restrita aos tokens do próprio usuário; ID alheio retorna 404 | ID inteiro positivo na rota | 120/min global | SecurityRegressionTest: revogação própria e tentativa alheia bloqueada |
| GET\|HEAD | /api/data-sources/status | Sanctum + conta ativa + e-mail verificado | Diagnóstico somente de configuração/capacidade; não retorna segredos nem efetua chamadas externas | Sem payload | 120/min global | DataSourceStatusTest: autenticação, estados, ausência de segredos e zero egress |
| GET\|HEAD | /api/documentos | Sanctum + conta ativa + e-mail verificado | TenantScope; somente documentos ativos da conta | Busca/filtros até 255, IDs positivos, status allowlist e paginação 1–100 | 120/min global | DocumentoTest: paginação, filtros, validação e isolamento |
| POST | /api/documentos | Sanctum + conta ativa + e-mail verificado | Tenant obrigatório; projeto ativo precisa pertencer ao tenant | Extensão/MIME e tamanho configurados, categoria até 80, descrição até 5000 e metadados privilegiados não aceitos | `document-upload` + global | DocumentoTest: upload, IDOR, arquivo inválido, rollback e mass assignment |
| GET\|HEAD | /api/documentos/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; ID alheio/excluído retorna 404 | ID inteiro positivo | 120/min global | DocumentoTest: leitura própria e IDOR |
| PUT\|PATCH | /api/documentos/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; projeto de destino ativo e do mesmo tenant | Arquivo proibido; campos editáveis em allowlist e PATCH parcial | 120/min global | DocumentoTest: edição parcial, projeto alheio e preservação do arquivo |
| DELETE | /api/documentos/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; caminho restrito ao namespace do tenant | Lock/transação, quarentena privada e restauração em falha | 120/min global | DocumentoTest: sucesso, caminho não gerenciado, falhas de storage/banco e rollback |
| GET\|HEAD | /api/documentos/{id}/download | Sanctum + conta ativa + e-mail verificado | TenantScope e caminho privado gerenciado; ID alheio retorna 404 | ID inteiro positivo; nome de resposta sanitizado | 120/min global | DocumentoTest: download autenticado, conteúdo, IDOR e ausência de caminho interno |
| GET\|HEAD | /api/eventos-calendario | Sanctum + conta ativa + e-mail verificado | TenantScope; somente eventos ativos da conta | Intervalo obrigatório/coerente, filtros allowlist, projeto positivo e paginação 1–100 | 120/min global | EventoCalendarioTest: janela, multidiário, filtros, paginação e isolamento |
| POST | /api/eventos-calendario | Sanctum + conta ativa + e-mail verificado | Tenant obrigatório; projeto ativo precisa pertencer ao tenant | Título/descrição/local/endereço limitados; categoria/status allowlist; fim não precede início | 120/min global | EventoCalendarioTest: criação, validação, projeto alheio e mass assignment |
| GET\|HEAD | /api/eventos-calendario/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; ID alheio/excluído retorna 404 | ID inteiro positivo | 120/min global | EventoCalendarioTest: leitura própria e IDOR |
| PUT\|PATCH | /api/eventos-calendario/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; projeto de destino ativo e do mesmo tenant | Mesmo contrato temporal; PATCH combina payload com estado persistido | 120/min global | EventoCalendarioTest e TemporalValidationTest |
| DELETE | /api/eventos-calendario/{id} | Sanctum + conta ativa + e-mail verificado | TenantScope; exclusão lógica apenas do próprio registro | ID inteiro positivo | 120/min global | EventoCalendarioTest: exclusão própria/alheia e ocultação do registro |
| GET\|HEAD | /api/tarefas | Sanctum + conta ativa + e-mail verificado | TenantScope; projetos e resultados somente da conta | Projeto/status/prioridade/busca/datas e paginação 1–100 validados | 120/min global | TarefaTest: filtros, paginação, datas, usuário sem tenant e isolamento |
| POST | /api/tarefas | Sanctum + conta ativa + e-mail verificado | Tenant obrigatório; projeto, etapa e responsável precisam pertencer à conta | Textos, datas, status/prioridade e valores limitados; etapa precisa pertencer ao projeto | 120/min global | TarefaTest e DomainIntegrityTest: CRUD, vínculos, mass assignment e conclusão |
| GET\|HEAD | /api/tarefas/{tarefa} | Sanctum + conta ativa + e-mail verificado | TenantScope; ID alheio/excluído retorna 404 | ID inteiro positivo | 120/min global | TarefaTest: leitura própria e IDOR |
| PUT\|PATCH | /api/tarefas/{tarefa} | Sanctum + conta ativa + e-mail verificado | TenantScope; vínculos de destino dentro da conta | PATCH parcial; coerência etapa/projeto e data de conclusão/status | 120/min global | TarefaTest, TemporalValidationTest e DomainIntegrityTest |
| DELETE | /api/tarefas/{tarefa} | Sanctum + conta ativa + e-mail verificado | TenantScope; exclusão lógica somente do próprio registro | ID inteiro positivo | 120/min global | TarefaTest: exclusão própria/alheia e registro excluído oculto |


## Atualização de rate limiting

Rotas /api/ai-property-search, /api/property-market-analysis e /api/neighborhood-context: throttle:market-data (6/min por usuário;12/min por IP), além do global. /api/itbi-imports/upload: can:import-itbi e throttle:itbi-upload (2/min por operador). As tabelas anteriores representam inventário da etapa inicial; esta seção prevalece para esses limites. Teste específico aprovado com sétima chamada 429 e rota comum não bloqueada.

## Nova rota — painel real

| Método | Rota | Autenticação | Autorização | Validação | Limite | Evidência |
|---|---|---|---|---|---|---|
| GET | /api/dashboard | Sanctum + conta ativa | TenantScope em imóveis/projetos/despesas/vendas | Sem payload; paginação/filtros globais | 120/min global | DashboardTest: cálculos e isolamento entre duas empresas; anônimo incluído na matriz automatizada de rotas |
| GET | /api/reports/ledger | Sanctum + conta ativa + e-mail verificado; tenant obrigatório | TenantScope; `projeto_id` precisa existir na própria conta e estar ativo | período, projeto, `page` 1–1.000.000, `per_page` 1–100; arrays e intervalo invertido rejeitados | 120/min global | FinancialLedgerTest: isolamento, filtros, paginação com totais do conjunto filtrado, projeto alheio 422, sem tenant 403; CollectionFilterValidationTest |

## Serviços CLI de ITBI — controles adicionais
| Serviço | Acesso | Validação | Limites | Evidência |
|---|---|---|---|---|
| Descoberta CLI | Operador do terminal | HTTPS, host exato, redirecionamentos validados, URL final como base | 3 redirecionamentos; HTML 2 MiB configurável | ItbiDatasetDiscoveryTest, ItbiRemoteLimitsTest |
| Download CLI | Operador do terminal | Mesma política de destinos; gravação confirmada antes dos metadados | 3 redirecionamentos; arquivo 250 MiB configurável | ItbiDownloadTest, ItbiRemoteLimitsTest |

DNS/egress, proxy e transferência real ainda não comprovados; testes de limites usam HTTP fake.


## Novos endpoints de cadastro
| Método/rota | Autenticação/autorização | Validação e limite | Evidência |
|---|---|---|---|
| POST /api/auth/register | Público; flags privilegiadas definidas pelo serviço | RegisterRequest; 10/h por IP | RegistrationVerificationTest e SecurityRegressionTest |
| GET /api/auth/postal-code/{postalCode} | Público | 8 dígitos, destino HTTPS fixo; 30/min por IP | Testes fake e consulta real pela UI |
| GET /api/auth/email/verify/{id}/{hash} | Assinatura relativa temporária, hash e conta ativa | 60 min; 6/min por IP | Link válido/alterado/expirado e conta inativa |
| POST /api/auth/email/resend | Sanctum + conta ativa; somente próprio usuário | 1/min e 5/h usuário; 10/h IP | Reenvio e 429 testados |
| Demais módulos protegidos | Acrescentado verified-account para novas contas | 403 code=email_verification_required | Bloqueio antes e acesso após confirmação |


### Evidência adicional: despesas paginadas
GET /api/despesas continua exigindo auth:sanctum, conta ativa, confirmação quando aplicável e filtro tenant no backend. ExpensePaginationTest: per_page=200 rejeitado 422; per_page=100 com projeto próprio entrega 100/100/5 registros de 205, sem perda na ordem por data/ID; outro tenant obtém coleção vazia para o ID alheio. Não substitui a matriz anterior nem comprova todos os filtros. O frontend agora consome todas as páginas e descarta requisições antigas; essa parte não é controle de autorização.

| Método | Rota nova | Autenticação | Autorização | Validação | Limite | Evidência |
|---|---|---|---|---|---|---|
| POST | /api/investment-analyses/{investmentAnalysis}/project | Sanctum, conta ativa, confirmação quando exigida | InvestmentAnalysisPolicy::update: dono da análise/oportunidade; tenant obrigatório | ID válido; valores derivados de registros; teto de orçamento; payload não altera vínculo/tenant | throttle:api, 120/min | AnalysisProjectTest: anonimato, suspensão, não confirmado, outro usuário mesmo/outro tenant, tenant ausente, ID inválido; rollback/idempotência; MariaDB concorrente |

A migração não transforma descrições em autorização. Legados com marcador correspondente recebem 409 sem associação automática; projetos excluídos não são recriados por retry. Retorno 200 significa reutilização do mesmo projeto, 201 significa criação completa. Testes do novo endpoint não comprovam segurança de toda a implantação.

## Endpoints de documentos privados
| Método/rota | Autenticação/autorização | Validação e limite | Evidência |
|---|---|---|---|
| GET `/api/documentos` | Sanctum + ativa + verificada; TenantScope | busca/projeto/categoria/status; paginação 1–100 | coleção alheia vazia; filtros/paginação 25+1 |
| POST `/api/documentos` | Mesmo grupo; tenant obrigatório | arquivo obrigatório, allowlist, 10 MB, projeto do tenant; 10/min usuário e 20/min IP | upload legítimo, projeto alheio/tipo/tamanho/mass assignment/falhas |
| GET `/api/documentos/{id}` | Mesmo grupo; ID tenant-scoped | ID inteiro positivo pelo middleware | próprio 200; alheio 404 |
| GET `/api/documentos/{id}/download` | Mesmo grupo; ID tenant-scoped | arquivo precisa existir; resposta privada/no-store/nosniff | próprio 200; alheio 404; caminho não exposto |
| PUT/PATCH `/api/documentos/{id}` | Mesmo grupo; ID tenant-scoped | somente metadados; arquivo proibido | próprio atualiza; alheio 404 |
| DELETE `/api/documentos/{id}` | Mesmo grupo; ID tenant-scoped | lock, caminho restrito ao tenant, quarentena privada e rollback | próprio exclui sem resíduo; alheio 404; falha de storage 503 sem alteração |

## Endpoints do calendário
| Método/rota | Autenticação/autorização | Validação | Limite/evidência |
|---|---|---|---|
| GET `/api/eventos-calendario` | Sanctum + ativa + verificada; TenantScope | início/fim obrigatórios, faixa, categoria/status/projeto, paginação | 120/min global; 101+1, overlap e coleção alheia vazia |
| POST `/api/eventos-calendario` | Mesmo grupo; tenant/autor da sessão | projeto do tenant, título, categoria, datas, status e limites | 120/min global; legítimo, alheio, mass assignment e sem tenant |
| GET `/api/eventos-calendario/{id}` | Mesmo grupo; ID tenant-scoped | ID inteiro positivo pelo middleware | próprio 200; alheio 404 |
| PUT/PATCH `/api/eventos-calendario/{id}` | Mesmo grupo; ID tenant-scoped | contrato completo e fim após início | próprio atualiza; alheio 404 |
| DELETE `/api/eventos-calendario/{id}` | Mesmo grupo; ID tenant-scoped | exclusão lógica | próprio exclui; alheio 404 |

## Endpoints de tarefas

| Método/rota | Autenticação/autorização | Validação | Limite/evidência |
|---|---|---|---|
| GET `/api/tarefas` | Sanctum + ativa + verificada; TenantScope e tenant obrigatório | busca, projeto/status/prioridade/datas; página e `per_page` 1–100 | 120/min global; paginação/filtros, coleção alheia vazia |
| POST `/api/tarefas` | Mesmo grupo; tenant derivado da sessão | projeto/etapa/responsável do tenant; etapa do projeto; textos, datas, estado, prioridade e custos limitados | 120/min global; CRUD, relações alheias, limites e mass assignment |
| GET `/api/tarefas/{id}` | Mesmo grupo; ID tenant-scoped | ID inteiro positivo pelo middleware | próprio 200; alheio 404 |
| PUT/PATCH `/api/tarefas/{id}` | Mesmo grupo; ID tenant-scoped | contrato completo; primeira conclusão cria data, edição preserva e reabertura limpa | próprio atualiza; alheio 404; TarefaTest cobre ciclo temporal |
| DELETE `/api/tarefas/{id}` | Mesmo grupo; ID tenant-scoped | exclusão lógica explícita | próprio exclui e some de projeto/lista; alheio 404 |

## Diagnóstico de fontes de dados

| Método/rota | Autenticação/autorização | Validação/exposição | Limite/evidência |
|---|---|---|---|
| GET `/api/data-sources/status` | Sanctum + ativa + verificada; tenant obrigatório | Snapshot local; somente estados, descrições e métricas ITBI; sem segredo/URL/caminho | 120/min global; `DataSourceStatusTest` cobre anonimato, conta, tenant, HTTPS e não exposição |

## Recuperação de senha

| Método/rota | Autenticação/autorização | Validação/exposição | Limite/evidência |
|---|---|---|---|
| POST `/api/auth/password/forgot` | Pública; envia somente para usuário/tenant ativos | E-mail normalizado; resposta 202 idêntica; falha neutra; nenhum token na resposta | 10/h IP + 3/h hash da conta + throttle do broker; `PasswordResetTest` |
| POST `/api/auth/password/reset` | Pública; posse de token válido para e-mail elegível | Token até 255; senha confirmada 12–72 caracteres e 72 bytes; revoga sessões | 10/min IP + 5/min hash da conta; expiração 60 min/uso único testados |

## Logout

| Método/rota | Autenticação/autorização | Invalidação | Limite/evidência |
|---|---|---|---|
| POST `/api/auth/logout` | Sanctum + conta ativa | Bearer: exclui somente `PersonalAccessToken` atual; sessão: logout, invalidate e regenerateToken | 120/min global; testes bearer, ausência de token e sessão web/401 |

## Documento frontend

| Superfície | Autenticação | Proteções | Evidência |
|---|---|---|---|
| Vite dev `:5173/*` | Conforme rota React/API | CSP de desenvolvimento, frame bloqueado, nosniff, no-referrer, COOP e Permissions Policy | HTTP real e recarga autenticada sem erros de console |
| Vite preview `:4173/*` | Conforme build implantado | CSP de produção sem script inline/eval; mesmas proteções de documento | HTTP real 200 e teste automatizado das diretivas |
| Build estático `dist/_headers` | Depende do host | Manifesto com CSP e seis headers complementares | Artefato confirmado no build; aplicação pelo host público pendente |

## Erros e rotas inválidas da API

| Cenário | Contrato | Exposição | Evidência |
|---|---|---|---|
| Validação `/api/*` sem `Accept` | 422 JSON; sem redirect | `message` e `errors` públicos | Teste integrado e HTTP real |
| Rota `/api/*` inexistente | 404 JSON com headers defensivos | Sem classe/arquivo/linha/trace | Teste integrado e HTTP real |
| Exceção 5xx em `/api/*` | JSON `Server Error`, `no-store` | Sem texto privado nem caminho base, mesmo com debug | RuntimeException controlada em `ApiHeadersTest` |

## Entrega de arquivos pelo servidor local

| Caminho/classe | Acesso | Proteção | Evidência |
|---|---|---|---|
| `/.user.ini`, `/.htaccess`, dotfiles | Público negado | Segmento oculto bloqueado e 404 direto; regra Apache adicional | HTTP real e `LocalPublicFileTest` |
| `/index.php`, extensões não web | Público negado como estático | Allowlist da raiz pública; execução somente pelo front controller interno | HTTP real 404 e teste de PHP/HTML/SQL |
| `/robots.txt`, ativos CSS/JS/imagens/fontes | Público | Allowlist web explícita e caminho canônico sob `public` | `robots.txt` 200 e testes de extensões |
| `/storage/*` | Público por decisão do usuário | Raiz canônica própria e allowlist de formatos; executáveis/SVG/HTML negados | Fixture `.txt` 200/removida e testes de symlink/extensão |

`GET /api/itbi-imports/upload-limits` foi removido no BUG 73. Limites internos de PHP não fazem parte do contrato público; a proteção do upload permanece no `POST /api/itbi-imports/upload`.

Nota BUG 78: os GETs de coleção de projetos, vendas, despesas, categorias de despesa e oportunidades aplicam allowlists específicas; oportunidades também validam booleanos e `min_price <= max_price`. Casos inválidos retornam 422.

Nota BUG 79: o disco privado não registra mais `storage.local` nem `storage.local.upload`. Documentos privados permanecem atrás do controller autenticado; `/storage/*` está reservado aos formatos autorizados do disco público e não aceita PUT.

Nota BUG 80: `POST /api/neighborhood-context` nunca produz estabelecimentos ou distâncias locais sem fonte. Overpass/Nominatim e cache de coleta válida são as únicas origens de pontos; falha retorna indisponível, e categorias ausentes permanecem zeradas.

Nota BUG 81: a saída de `GET /api/ai-property-search` limita links e imagens de qualquer provedor a URLs HTTP/HTTPS válidas antes de alimentar `href`/`src`; valores incompatíveis são nulos.

Nota BUG 82: toda requisição do Chromium usado pelo scraper passa por política de egress com validação HTTP/HTTPS, IP e DNS. Destinos locais, privados, link-local, metadados, credenciados ou com qualquer resolução privada são abortados.

Nota BUG 83: o fallback PHP de portais desativa redirects automáticos e valida cada salto contra HTTPS, lista exata de hosts, porta padrão e conjunto DNS exclusivamente público antes de enviar a requisição.

Nota BUG 84: o HTML externo do fallback tem teto de 5 MiB verificado em header, progresso e corpo final, com codificação `identity`; excesso falha antes do parse.

Nota BUG 85: respostas de análise financeira identificam o método determinístico e a completude dos dados. Cenários são sensibilidades sobre a venda/custos/prazo informados e publicam suas premissas; não há benchmarks sem fonte, percentil de mercado ou confiança estatística na interface.

Nota BUG 86: `GET /api/investment-analyses/{id}/financing-reference` não publica mais tabela bancária ou taxa atribuída a fonte externa. Recebe taxa mensal e instituição opcionais validadas, calcula uma simulação e declara origem manual; a autorização por análise permanece.

Nota BUG 101: os três caminhos de escrita de etapas aplicam a mesma invariante entre status e progresso. Pares explícitos contraditórios retornam 422; atualizações parciais sincronizam o campo derivado.

Nota BUG 102: DELETE de projeto retorna 409 quando há despesa ou documento ativo; a checagem ocorre dentro da transação antes de arquivar tarefas, etapas e eventos.

Nota BUG 103: projeto, despesa e documento revalidam pais ativos sob locks compartilhados com exclusões; movimentos bloqueiam IDs antigos/novos em ordem estável. Prova multiprocesso permanece pendente.

Nota BUG 104: tarefas e eventos usam transações e locks de projeto em criação/edição, impedindo escrita após exclusão detectada; disputa multiprocesso ainda não foi executada.

Nota BUG 105: PUT/PATCH de projeto bloqueia imóveis de origem/destino em ordem estável, revalida o destino e bloqueia o projeto antes da escrita; prova multiprocesso permanece pendente.

Nota BUG 106: descrições/observações/notas livres nos quatro contratos afetados têm teto de 5.000 caracteres; excesso retorna 422 antes da persistência.

Nota BUG 107: ordem de etapa é limitada à capacidade INTEGER e max_price ao teto monetário do domínio; valores excedentes retornam 422 antes de banco/provedor.

Nota BUG 108: middleware comum limita page a 1–1.000.000 e per_page a 1–100; valores extremos retornam 422 antes do cálculo de OFFSET.

Nota BUG 109: PATCH de projeto aceita campos parciais e preserva os omitidos; POST/PUT continuam exigindo nome e propriedade, e estruturas aninhadas seguem proibidas em atualização.

Nota BUG 110: PATCH de venda permite payload parcial e preserva campos omitidos; POST/PUT continuam exigindo propriedade e valor, com regras temporais/finais sobre estado efetivo.

Nota BUG 111: PATCH de propriedade aceita corpo parcial e preserva chaves/endereço omitidos; POST/PUT mantêm apelido obrigatório e status vendido continua derivado da venda final.

Nota BUG 112: PATCH de despesa é parcial; propriedade/projeto/etapa/tarefa efetivos combinam payload e registro persistido, impedindo vínculos incompatíveis por omissão.

Nota BUG 113: PATCH de tarefa resolve projeto/etapa/status pelo payload mais registro persistido, preserva data de conclusão omitida e bloqueia etapa incompatível; PUT continua completo.

Nota BUG 114: PATCH de evento combina datas enviadas e persistidas, bloqueia intervalos invertidos por qualquer extremidade e preserva campos omitidos; PUT continua completo.
Nota BUG 115: PATCH de documento aceita metadados isolados, preserva identidade e vínculo do arquivo e continua proibindo upload na edição; POST/PUT exigem categoria.
Nota BUG 116: PATCH de fornecedor aceita campos isolados e preserva o cadastro omitido; POST/PUT continuam exigindo nome e categoria.
Nota BUG 117: PATCH de oportunidade aceita campos isolados e preserva cadastro e galeria omitidos; PUT continua completo e regras de mídia/autorização permanecem ativas.
Nota BUG 118: POST de contexto de bairro usa estrutura fechada e teto de área do domínio; entrada inválida retorna 422 antes de qualquer chamada externa.
Nota BUG 119: as rotas web carregam 24 páginas de funcionalidade sob demanda; entrada e maior chunk permanecem abaixo de 500 kB no build atual.

Nota BUG 120: interface da IA mantém filtros dentro da viewport nos breakpoints testados e lista de bairros acessível em tema escuro, sem erros de console.

Nota BUG 171: `GET /api/investment-analyses/{id}` e respostas equivalentes mantêm a autorização por proprietário e agora projetam explicitamente os campos públicos da análise e das relações, sem `user_id`, `deleted_at` ou chaves estrangeiras redundantes.

Nota BUG 172: respostas de projeto e de `POST/PATCH` de etapas usam `ProjetoEtapaResource`; a seleção pública preserva os dados operacionais e omite `tenant_id`, `projeto_id`, `deletado_flg` e timestamps administrativos.

Nota BUG 173: middleware CORS aceita somente origens HTTP(S) exatas derivadas de `FRONTEND_URL` e `CORS_ALLOWED_ORIGINS`; wildcard, credenciais e componentes além da origem são eliminados antes da configuração.

Nota BUG 174: lockfile frontend recebeu somente patches dentro das faixas existentes; instalação limpa usa `--ignore-scripts`, audit permanece limpo e migrações principais foram separadas da classificação de vulnerabilidade.
