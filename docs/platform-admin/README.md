# Central administrativa â€” execuÃ§Ã£o SOL



Branch `CorreÃ§Ã£o-visua/proteÃ§Ã£o`. Base `c2ea84a`. O painel `/platform` e APIs `/api/platform` ampliam a base existente; nÃ£o sÃ£o administraÃ§Ã£o de imobiliÃ¡ria. Nenhum privilÃ©gio de tenant concede Platform Admin.



## Etapa 1 â€” fundaÃ§Ã£o



Eventos estruturados com request/trace UUID prÃ³prio, rota parametrizada, status, latÃªncia e tamanhos. IP em HMAC e mÃ¡scara; corpo/query/headers/cookies nÃ£o sÃ£o coletados. Eventos API/autenticaÃ§Ã£o/acesso negado/429 e leitura administrativa. Conta ativa e e-mail efetivamente verificado exigidos inclusive para contas legadas. Papel existente `PlatformAdmin`/allowlist de implantaÃ§Ã£o preservado. Rate limit 60/min. Sanctum bearer continua exigindo token explÃ­cito; nÃ£o foi ativada autenticaÃ§Ã£o por cookie sem CSRF. MFA nÃ£o configurado: pendÃªncia explÃ­cita, sem alegar proteÃ§Ã£o existente.



RetenÃ§Ã£o configurÃ¡vel em `config/observability.php`; `platform:prune` faz dry-run, `--execute` remove em lotes. Auditoria tem mÃ­nimo de 730 dias. Falha da telemetria nÃ£o interrompe operaÃ§Ã£o cliente; gravaÃ§Ãµes administrativas explÃ­citas falham junto Ã  transaÃ§Ã£o. Instalar somente migration aditiva nova; sem apagar dados.



Testes iniciais: `php vendor/bin/phpunit --filter PlatformOperationsTest`, 4 testes de autorizaÃ§Ã£o, redaction por nÃ£o coleta, deduplicaÃ§Ã£o/auditoria e retenÃ§Ã£o. Resultado final serÃ¡ registrado apÃ³s execuÃ§Ã£o. Infraestrutura de eventos comeÃ§a agora, nÃ£o recupera atividade histÃ³rica ausente. Etapas seguintes em execuÃ§Ã£o; nenhuma prontidÃ£o global declarada.



## Etapa 2 — clientes e analytics



APIs de clientes/perfil, assinaturas, trials vencidos, churn, funil e coortes implementadas. Filtros e paginação no backend. Receita/UTM/motivos ausentes continuam nulos. `php vendor/bin/phpunit --filter Platform`: 12/12, 66 assertions, 1.922 ms. Interface e QA do conjunto pendentes até etapa 8. Detalhes: ANALYTICS.md. Etapa 1: 4/4, 30 assertions, 877 ms.


## Etapa 3 — segurança e ações administrativas

Security Center por eventos/rota/IP mascarado, timeline correlacionada, incidentes, sessões Sanctum, suporte e suspensão/revogação controladas. Score não bloqueia automaticamente. Teste das rotas revelou binding antes da autorização: prioridade corrigida. Detector traversal tinha regex inválido: substituído por comparação limitada. `--filter Platform`: 16/16, 107 assertions, 2.977 ms. QA visual e regras de alerta seguem nas próximas etapas.

## Etapa 4 — API, erros, performance e health

Snapshot de serviços com timeouts/allowlist, banco/storage/fila e última checagem. Performance com percentis amostrais explicitados; erros por rota/status, pesquisa por identificadores sem conteúdo privado. `--filter Platform`: 18/18, 124 assertions, 2.315 ms. Sem histórico de uptime nem heartbeat real de fila. Texto/arquivos normalizados para UTF-8; sem nova dependência.

## Etapa 5 — IA, RAG e ferramentas

Agregados reais de AiToolRun por provider/modelo/tool/status/produto, tokens/custo registrado nulos quando ausentes; mensagens apenas contadas. Consulta de ferramentas exclui inputs, hashes de conteúdo e respostas. Observers criam metadados de uso de IA, transições de assinatura e jobs sem payload privado. RAG usa tool retrieve_knowledge; métricas de indexação não verificadas continuam indisponíveis. Modelo configurado não é apresentado como carregado. `--filter Platform`: 19/19, 137 assertions, 1.948 ms.
