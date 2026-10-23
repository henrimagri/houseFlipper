# Prontidão operacional — gates e bloqueios

## Contrato do indicador

ProductionReadiness::snapshot alimenta /api/platform/readiness. O checklist usa pesos iguais: somente approved recebe 1; partial, blocked e not_configured recebem 0. Score é a fração de itens aprovados, e blocking_checks conta todos os demais. É um indicador operacional do código atual, não certificação comercial, SLA, auditoria geral ou aprovação de QA.

Não fixar a porcentagem na documentação: ela depende das observações e dos administradores reais. Evidência histórica de teste não deve ser inserida no banco local para elevar o score.

| Gate | Fonte e regra atual | Limite relevante |
| --- | --- | --- |
| admin_mfa | Admins por tabela/allowlist comparados a credenciais confirmadas; middleware exige MFA por token | Enrolment real dos operadores, recuperação e HTTPS continuam necessários |
| scheduler | Heartbeat observado online permite approved | Código agendado não comprova daemon; UUID e execução periódica reais são necessários |
| queue | Worker com último loop online resulta em partial | Não comprova cobertura de todas as filas; job longo pode envelhecer heartbeat |
| mysql_validation | blocked até homologação verificável | MySQL local indisponível; conectar ao driver não comprova suíte aprovada |
| billing_ledger | Registros podem demonstrar fundação parcial | Não equivalem a checkout, conciliação ou recorrência comercial ao vivo |
| provider_webhooks | Receipts podem demonstrar observação parcial | Assinatura em fixture local não comprova callbacks reais do provedor |
| backup_restore | not_configured | Sem fonte verificável de backup ou restore drill |
| ai_worker | Health FastAPI online permite somente partial | Heartbeat do processo AI ainda não integrado |
| quotas | not_configured no checklist atual | Catálogo comercial vazio por padrão; não presumir limites |
| 3d | partial | Geometria Fase 8/gate preservados; worker e densificação densa não aprovados por este item |

O gate de quotas ainda é conservador e não calcula automaticamente cobertura de planos configurados. O health HTTP do AI não substitui sinais de processamento nem o heartbeat de 3D. Nenhum desses itens deve ser promovido manualmente por uma captura de tela ou existência de arquivo.

## Pendências antes de implantação

1. Executar e registrar a suíte MySQL 8 no schema de teste isolado, incluindo os contratos necessários da operação. Neste host, serviço indisponível impediu homologação. SQLite não comprova locks concorrentes MySQL.
2. Publicar e acompanhar a CI preparada em .github/workflows/platform-ci.yml. Nenhum resultado remoto aprovado foi obtido nesta atualização documental; workflow sem execução não é evidência.
3. Validar callbacks reais Stripe/Resend com configuração autorizada e associação local de entidades. Contratos de assinatura/replay locais não autorizam cobrança real nem comprovam entrega de e-mail. Accepted não significa delivered; MRR/ARR exigem ledger e recorrência demonstrados.
4. Instalar e observar scheduler/worker reais. Heartbeat de queue é último loop e pode parecer atrasado em job longo; faltam cobertura por conexão/fila, throughput e heartbeat dedicado de AI/3D.
5. Definir quotas comerciais explicitamente. Enforcement disponível apenas para requests IA e reconstruções quando o plano possui limite configurado; contabiliza admissões inclusive se falharem depois. Tokens, imagens, storage, concorrência e custos não medidos permanecem nulos. A contagem/criação preexistente de jobs de reconstrução não é atômica e requer correção antes de prometer cap concorrente estrito.
6. Configurar fonte real de backup, retenção, alertas e executar restore drill em ambiente isolado. Não restaurar sobre produção. Registrar data, artefato lógico, verificações e resultado sem credenciais; nenhum desses passos foi executado por este documento.
7. Definir limpeza das tabelas especializadas e arquivos de exportação expirados. Inventário de storage é limitado e não autoriza exclusão automática. A retenção de requests preserva eventos ainda não agregados.
8. Repetir medições de cache frio, busca e kernels em homologação adequada; investigar outliers sem atribuir causa sem evidência. Benchmark in-process no PC local não constitui teste de carga concorrente ou SLA.

## Analytics e privacidade

Há nova coleta opcional backend/frontend de origem consentida por sessão, com UTM em allowlist e retenção de 90 dias. Não existe relatório administrativo integrado de visitantes/UTM nesta fase, nem identificação de pessoa única, atribuição causal ou preenchimento retroativo. Revogação no navegador impede futuras medições locais; solicitações de eliminação seguem workflow humano, sem exclusão automática indiscriminada.

## Evidências e responsabilidades

VALIDATION.md e MYSQL_VALIDATION.md registram comandos e resultados reais. MFA.md, BILLING_LEDGER.md, RUNTIME.md e WORKFLOWS.md detalham contratos. Atualizar um gate somente com fonte verificável e autorização adequada, preservando ausência de medição como desconhecida. Este documento não proclama produção, conclusão comercial ou QA aprovado.
