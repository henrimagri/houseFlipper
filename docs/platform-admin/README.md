# Central administrativa — SOL

Entrada /platform; backend /api/platform. A Fase 2 evolui a central existente a partir de 2e375c6, exclusivamente em Correção-visua/proteção. Não recria os produtos: Corretor, Imobiliária e House Flipper preservam rotas, entitlements e autorização independentes. Administrador de imobiliária não é Platform Admin.

## Estado integrado

| Área | Código integrado | Limites operacionais |
| --- | --- | --- |
| Segurança | TOTP obrigatório por token administrativo, recovery codes de uso único, reautenticação recente, registro de admins e revisão de sinais | QR local, passkeys e recuperação de identidade sem códigos pendentes |
| Billing | Ledger imutável pela aplicação, métricas por moeda e período, timeline e adaptadores de callbacks assinados | Testes locais; sem conciliação/checkout ao vivo nem receita presumida |
| Operação | Scheduler/queue heartbeats, alertas agrupados, incidentes, health e amostras por minuto | Daemons não presumidos; queue mede último loop e cobertura parcial; heartbeat AI/3D não integrado |
| Performance | Agregados por minuto, histogramas p50/p75/p95/p99 estimados, slow requests e diagnóstico opcional | Outliers e cache frio ainda exigem investigação; não é APM completo ou SLA |
| Workflows | Exportações assíncronas privadas, cancelamento/retry restrito, privacidade humana e flags auditadas | Worker real necessário; expiração não comprova limpeza física |
| Recursos | Admissões mensais de IA/reconstruções e inventário limitado de storage | Enforcement só para esses recursos quando há limite configurado; demais medições nulas |
| Analytics | Coortes/uso já existentes e nova coleta opcional de UTM por sessão consentida | Sem relatório UTM integrado, visitantes únicos ou causalidade |
| Qualidade | CI e contrato MySQL isolado preparados; testes/benchmark locais documentados | MySQL indisponível e CI remota não executada; sem aprovação de produção |

Os resultados realmente executados, inclusive falhas e QA disponível, pertencem a VALIDATION.md. Este índice não declara que toda a Fase 2 está aprovada ou que houve novo QA visual.

## Acesso e implantação

Conta ativa, e-mail confirmado e Platform Admin são exigidos no backend. Leituras são auditadas antes da consulta; escritas administrativas relevantes ficam em transação com audit log. Sanctum bearer não habilita cookies stateful sem proteção. Administradores existentes precisam realizar enrolment TOTP; não há cadastro automático nem bypass de MFA para operação real.

Migrations são aditivas para eventos/índices, MFA, ledger, runtime, callbacks, workflows, quotas/inventário e consentimento. Não executar rollback destrutivo sobre dados financeiros ou operacionais reais. O ambiente local usa SQLite; homologação MySQL permanece bloqueada pela infraestrutura indisponível. A suíte de contrato e CI não equivalem a execução remota aprovada.

Scheduler declara avaliação de alertas, agregação e probes por minuto, além de prune diário. PLATFORM_SCHEDULER_UUID deve ser um UUID estável. Queue::looping registra observação do processo real, não um daemon instalado pelo código. Exportações grandes exigem conexão assíncrona platform_ops e worker próprio conforme WORKFLOWS.md. Não há envio externo automático de alertas nesta integração.

## Bloqueios explícitos

Backup e restore drill não têm fonte real configurada. Callbacks Stripe/Resend têm contratos assinados testados localmente, sem validação ao vivo. Cobertura de filas/throughput e heartbeats dos processos AI/3D são incompletos. Limites comerciais não são inventados: config/platform_resources.php não define planos por padrão. Tokens, imagens, storage, concorrência e custo financeiro local não medidos permanecem nulos/não configurados.

A checagem preexistente de concorrência de reconstrução não é atômica entre contagem e criação do job; tratá-la como pendência, não como quota concorrente garantida. Fase 8 e gate geométrico são preservados, sem declarar densificação CUDA ou nova qualidade comercial. Não há endpoint administrativo para conteúdo privado de conversas ou fotografias.

## Documentação

- OBSERVABILITY.md e RUNTIME.md: fontes, agregação, heartbeats e limites.
- SECURITY_CENTER.md e MFA.md: autorização, segundo fator e controles.
- BILLING_LEDGER.md: evidência financeira e normalização.
- ANALYTICS.md: definições, consentimento e lacunas de atribuição.
- RETENTION.md: política por categoria e tabelas ainda sem limpeza própria.
- WORKFLOWS.md: exportação, atendimento de privacidade e administradores.
- MYSQL_VALIDATION.md: suíte isolada, CI preparada e benchmark local.
- PRODUCTION_READINESS.md: gates, bloqueios e requisitos antes de produção.
- VALIDATION.md: evidências consolidadas de execução e QA.

Procedimento de backup/restore isolado: [BACKUP_RESTORE.md](BACKUP_RESTORE.md). Não executado nesta fase; status continua não configurado.
