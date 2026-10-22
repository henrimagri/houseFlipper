# Observabilidade

`platform_events` é store de metadados indexado por categoria/data, tenant/data, usuário/data, produto/data, severidade/data e request/trace. UUID gerado no servidor é retornado em X-Request-ID. Headers e corpo não são armazenados. Página de eventos usa simple pagination sem COUNT global. Overview possui cache 30 s; janela máxima 93 dias.

Performance calcula percentis sobre últimas até 10 mil requisições filtradas e identifica amostragem. Não é APM completo. Erros agrupados por rota parametrizada/status; classe de exceção, sem mensagem/stack com valores sensíveis. Geolocalização, métricas frontend, uptime e conexões/slow queries do banco não configurados.

Health lê SELECT 1/migration e consulta somente endpoints configurados com allowlist, sem redirects, conexão 1 s e timeout 2 s, cache 30 s. Worker usa token interno; resposta não devolve token/endereço nem caminhos. Storage usa disk_free_space/total: disco do processo, não soma de arquivos por tenant. Fila síncrona é degradada; contagem de jobs não comprova heartbeat de worker.

Não executar SQL arbitrário pela interface. Sem serviço adicional instalado. Prune é dry-run por padrão. Scheduler deve ser executado pelo operador para retenção/alertas; não presumir daemon disponível.
