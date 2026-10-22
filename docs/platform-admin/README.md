# Central administrativa — SOL

Implementação em `Correção-visua/proteção`, base `c2ea84a`. Entrada: `/platform`. Backend: `/api/platform`. A central reutiliza Laravel/Sanctum, assinaturas, leads comerciais, AiToolRun e PropertyProcessingJob existentes. Não é a administração de uma imobiliária e não concede direitos sobre produtos aos clientes.

## Estado e execução

| Etapa | Entrega | Evidência |
|---|---|---|
| 1 | Eventos, auditoria, autorização e retenção | `dfc2ad6`; testes iniciais 4/4, 30 assertions |
| 2 | Clientes/perfil, trials, conversão, churn e coortes | `e22b849`; suíte Platform 12/12, 66 assertions |
| 3 | Sinais, IPs, sessões, incidentes e ações com confirmação | `63e9c25`; Platform 16/16, 107 assertions |
| 4 | API, erros, performance amostral e health | `0738fbf`; Platform 18/18, 124 assertions |
| 5 | IA, RAG e ferramentas, sem conteúdo privado | `a2c0735`; Platform 19/19, 137 assertions |
| 6 | Jobs 3D, assets registrados e filas | `a23757c`; Platform 20/20, 149 assertions |
| 7 | Billing honesto, webhooks, e-mails e CSV | `9a512b5`; Platform 21/21, 164 assertions |
| 8 | Interface integrada, alertas, feature flag, regressões e QA | Interface e fluxos validados; resultados e limitações em VALIDATION.md |

As contagens acima são checkpoints, não somas de testes. A revisão posterior corrige problemas encontrados nas etapas anteriores. Nenhuma prontidão comercial integral foi declarada.

## Operação

Conta ativa, e-mail realmente confirmado e PlatformAdmin existente (ou allowlist de implantação) são exigidos no backend antes de bindings. Nenhum papel de tenant concede acesso. Leituras administrativas são auditadas antes da consulta e falham se a auditoria estiver indisponível. Escritas administrativas ficam em transação com seu audit log. Ações de suspensão/revogação/bloqueio e controles exigem motivo, confirmação e senha atual; não operam sobre administradores da plataforma.

Sanctum bearer exige token explícito; autenticação cookie stateful sem CSRF não foi habilitada. MFA ainda não configurado: evolução recomendada é TOTP/WebAuthn com recuperação, enrolment de administradores existentes e testes antes de exigir o segundo fator; não alegar proteção atual.

Migration aditiva `2026_10_22_000001_create_platform_operations` cria eventos, incidentes e controles. Migration `000002` acrescenta índices para consultas reais. Nenhuma tabela comercial foi substituída. Ambiente local utiliza SQLite; compatibilidade operacional MySQL deve ser validada na implantação, sem atestado de teste MySQL neste host.

`platform:prune` é dry-run; `--execute` aplica retenção em lotes. `platform:evaluate-alerts` avalia regras habilitadas, sem bloqueio automático. Scheduler possui avaliação a cada minuto e prune diário; seu daemon não foi instalado ou presumido ativo. Não há envio externo de alertas.

## Documentação

- OBSERVABILITY.md: schema, fontes, saúde e limites.
- SECURITY_CENTER.md: sinais e ações controladas.
- ANALYTICS.md: definições de funil/coortes/uso.
- RETENTION.md: política e limpeza.
- VALIDATION.md: comandos, QA e pendências.

O script `scripts/platform-smoke.php` executa checks HTTP exclusivamente em APP_ENV=local com operador temporário, sem aproveitar sessão privada do navegador, imprimir credenciais ou registrar corpos das respostas. O operador e tokens são removidos ao terminar; auditoria é preservada.

## Limites explícitos

Sem ledger conciliado, MRR/ARR/receita permanecem indisponíveis. Checkout e webhooks nativos ainda não validados; processamento HMAC legado não é comprovação de integração comercial. Entrega/bounce, UTM/visitantes consentidos, uptime histórico, heartbeat de filas, APM completo, quotas e inventário total de storage, cancelamento administrativo cross-user e métricas GPU por job não são apresentados como concluídos. Conteúdo de conversas/fotografias não está disponível na central. Reconstrução Fase 8 e gate preservados.
