# Analytics operacional e consentimento

## Definições existentes

Consultas administrativas usam paginação de servidor (25 padrão, 100 máximo), filtros validados e janela máxima de 93 dias. Datas têm semântica do relatório: clientes pelo cadastro, trials pela expiração e churn pela atualização. Produto e plano vêm das assinaturas do tenant, não do papel do usuário. Perfil 360 não expõe conteúdo de conversas, fotografias privadas ou tokens.

O funil existente é uma coorte de usuários cadastrados. Confirmação usa email_verified_at; trials compartilhados por tenant são tratados explicitamente sem multiplicar usuários por joins de produtos. Assinatura ativa não comprova pagamento. Trial vencido sem medição histórica não significa nunca usou. Motivo histórico de churn ausente permanece desconhecido.

Uso de funcionalidades é observação autorizada de usuário/produto/dia, não número exato de cliques. Coortes medem retorno no dia calendário Dn, somente dias completos cobertos pela telemetria API e dentro da retenção. Consulta tem limite de dez mil usuários; não se infere atividade anterior a partir de auditoria antiga.

## Coleta opcional de origem — nova

AnalyticsConsent nas páginas públicas permite aceitar ou recusar analytics sem bloquear funcionalidades. A escolha fica no navegador; ao aceitar, cria UUID da sessão em sessionStorage e envia POST /api/analytics/visit com política analytics-v1. Não há fingerprinting nem identificação agressiva de visitante. Recusar/desativar remove o identificador local para futuras medições; isso não executa eliminação imediata de registros já recebidos pelo backend.

Backend exige consentimento afirmativo, política permitida e UUID, aplica rate limit e guarda apenas HMAC do identificador, política, datas e UTM permitidas. Allowlist: utm_source, utm_medium, utm_campaign, utm_term, utm_content; limites e regex restringem valores. Não enviar nomes, e-mails, telefones ou identificadores pessoais nas campanhas. URL completa, cookies, IP bruto e parâmetros arbitrários não entram nessa tabela.

No cadastro, analytics_session pode vincular a sessão previamente consentida ao novo usuário/tenant, quando ainda sem vínculo e consentida nos últimos 90 dias. Não atribui automaticamente sessões de outros contextos, nem comprova identidade de uma pessoa única. Uma pessoa pode possuir várias sessões e uma sessão pode ser compartilhada.

Retenção backend: 90 dias por consented_at, integrada a platform:prune. A execução periódica depende do scheduler real. Esta coleta é nova e não preenche o histórico passado. **Ainda não há endpoint de relatório administrativo de UTM/visitantes**: a tabela não deve ser apresentada como funil completo já integrado, taxa de visitantes únicos ou causalidade de campanha.

## Receita e conversão

Ledger financeiro agora possui fundação própria e métricas por moeda/produto/plano, somente com evidência permitida. MRR exige recorrência e período demonstrados; assinaturas ativas, callbacks de teste e parcelas anuais brutas não são receita mensal. Globais nulos não devem ser convertidos em zero. BILLING_LEDGER.md define normalização, refunds e limites.

O adaptador Stripe atual registra invoice.paid conciliada à assinatura local, sem presumir período recorrente ou alterar acesso. Checkout real, abandono após janela, renovação e funil completo trial → checkout → pago ainda dependem de fontes/instrumentação verificadas. Callback assinado em teste local não comprova operação comercial ao vivo. Não atribuir melhora de conversão a uma funcionalidade ou campanha sem desenho analítico apropriado.

Nenhuma aprovação de produção, desempenho ou QA é inferida desses contratos. Consulte VALIDATION.md para evidências efetivamente executadas.

Incremento posterior: GET /api/platform/attribution e tela Origem consentida agregam somente sessões autorizadas por source/campaign e vínculos ao cadastro. Filtros de período/tenant/usuário são explícitos; filtro de produto é rejeitado porque a sessão não possui essa dimensão. Não se divide por todos os visitantes, não se afirma pessoa única e não se atribui causalidade.
