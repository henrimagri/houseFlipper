# Central administrativa â€” execuÃ§Ã£o SOL

Branch `CorreÃ§Ã£o-visua/proteÃ§Ã£o`. Base `c2ea84a`. O painel `/platform` e APIs `/api/platform` ampliam a base existente; nÃ£o sÃ£o administraÃ§Ã£o de imobiliÃ¡ria. Nenhum privilÃ©gio de tenant concede Platform Admin.

## Etapa 1 â€” fundaÃ§Ã£o

Eventos estruturados com request/trace UUID prÃ³prio, rota parametrizada, status, latÃªncia e tamanhos. IP em HMAC e mÃ¡scara; corpo/query/headers/cookies nÃ£o sÃ£o coletados. Eventos API/autenticaÃ§Ã£o/acesso negado/429 e leitura administrativa. Conta ativa e e-mail efetivamente verificado exigidos inclusive para contas legadas. Papel existente `PlatformAdmin`/allowlist de implantaÃ§Ã£o preservado. Rate limit 60/min. Sanctum bearer continua exigindo token explÃ­cito; nÃ£o foi ativada autenticaÃ§Ã£o por cookie sem CSRF. MFA nÃ£o configurado: pendÃªncia explÃ­cita, sem alegar proteÃ§Ã£o existente.

RetenÃ§Ã£o configurÃ¡vel em `config/observability.php`; `platform:prune` faz dry-run, `--execute` remove em lotes. Auditoria tem mÃ­nimo de 730 dias. Falha da telemetria nÃ£o interrompe operaÃ§Ã£o cliente; gravaÃ§Ãµes administrativas explÃ­citas falham junto Ã  transaÃ§Ã£o. Instalar somente migration aditiva nova; sem apagar dados.

Testes iniciais: `php vendor/bin/phpunit --filter PlatformOperationsTest`, 4 testes de autorizaÃ§Ã£o, redaction por nÃ£o coleta, deduplicaÃ§Ã£o/auditoria e retenÃ§Ã£o. Resultado final serÃ¡ registrado apÃ³s execuÃ§Ã£o. Infraestrutura de eventos comeÃ§a agora, nÃ£o recupera atividade histÃ³rica ausente. Etapas seguintes em execuÃ§Ã£o; nenhuma prontidÃ£o global declarada.

## Etapa 2 — clientes e analytics

APIs de clientes/perfil, assinaturas, trials vencidos, churn, funil e coortes implementadas. Filtros e paginação no backend. Receita/UTM/motivos ausentes continuam nulos. `php vendor/bin/phpunit --filter Platform`: 12/12, 66 assertions, 1.922 ms. Interface e QA do conjunto pendentes até etapa 8. Detalhes: ANALYTICS.md. Etapa 1: 4/4, 30 assertions, 877 ms.
