# Central administrativa — execução SOL

Branch `Correção-visua/proteção`. Base `c2ea84a`. O painel `/platform` e APIs `/api/platform` ampliam a base existente; não são administração de imobiliária. Nenhum privilégio de tenant concede Platform Admin.

## Etapa 1 — fundação

Eventos estruturados com request/trace UUID próprio, rota parametrizada, status, latência e tamanhos. IP em HMAC e máscara; corpo/query/headers/cookies não são coletados. Eventos API/autenticação/acesso negado/429 e leitura administrativa. Conta ativa e e-mail efetivamente verificado exigidos inclusive para contas legadas. Papel existente `PlatformAdmin`/allowlist de implantação preservado. Rate limit 60/min. Sanctum bearer continua exigindo token explícito; não foi ativada autenticação por cookie sem CSRF. MFA não configurado: pendência explícita, sem alegar proteção existente.

Retenção configurável em `config/observability.php`; `platform:prune` faz dry-run, `--execute` remove em lotes. Auditoria tem mínimo de 730 dias. Falha da telemetria não interrompe operação cliente; gravações administrativas explícitas falham junto à transação. Instalar somente migration aditiva nova; sem apagar dados.

Testes iniciais: `php vendor/bin/phpunit --filter PlatformOperationsTest`, 4 testes de autorização, redaction por não coleta, deduplicação/auditoria e retenção. Resultado final será registrado após execução. Infraestrutura de eventos começa agora, não recupera atividade histórica ausente. Etapas seguintes em execução; nenhuma prontidão global declarada.
