# Observações operacionais — Fase 2

## Heartbeats observados

`OperationalHeartbeats::beat(type, workerId, version, startedAt?, seenAt?, hostname?)` é uma API interna para produtores confiáveis. Tipos permitidos: `scheduler`, `queue`, `ai`, `3d`. O identificador é um UUID lógico estável configurado pelo operador. O serviço valida identidade, versão, ordem temporal e rejeita timestamps futuros e regressões de `last_seen`. O mesmo UUID não pode mudar de tipo. Uma inicialização explicitamente informada permite registrar um reinício real; sem ela, o início previamente registrado é preservado.

O hostname é armazenado exclusivamente como HMAC-SHA256 usando a chave da aplicação; consultas retornam somente uma referência de 12 caracteres. Nenhum caminho, hostname bruto, token ou payload é persistido. A rotação da chave muda essa referência. Isso não é autenticação de um worker remoto: nenhuma rota de ingestão pública é criada por este módulo.

`snapshot(onlineSeconds=90, offlineSeconds=300)` retorna estados por worker e por tipo:

- `online`: observação recebida há no máximo 90 segundos.
- `delayed`: idade maior que 90 e até 300 segundos.
- `offline`: idade maior que 300 segundos ou relógio inconsistente.
- `unconfigured`: nenhuma observação persistida daquele tipo.

O estado de um tipo considera a melhor observação de seus workers; todos os workers continuam listados para permitir detectar instâncias individuais paradas. Uma observação isolada comprova somente a invocação naquele instante. Não comprova processamento contínuo, throughput ou saúde de todas as filas. A listagem é limitada a 1.000 workers, explicitamente informada no contrato por `limit` e `truncated`.

Comando disponível sem instalação de daemon:

```powershell
php artisan platform:heartbeat scheduler UUID-ESTAVEL-CONFIGURADO --release=release-id
```

O argumento deve ser um UUID real. Não gerar um UUID diferente em cada execução. Integração periódica deve ocorrer no scheduler efetivamente executado. Heartbeat de queue deve partir do processo real do worker, inclusive nos ciclos ociosos, não do dashboard nem do scheduler se passando pelo worker. AI/3D exigem integração autenticada com observações reais desses processos. Existência deste código não os torna online.

## Agregação de requests

`RequestAggregates::run(limit=5000)` processa no máximo 10.000 eventos `category=api` por invocação. A consulta seleciona somente ID, timestamp, rota parametrizada, método, tenant, produto, status e duração. Nunca consulta corpos, headers, IP, request ID ou metadata.

As dimensões são minuto UTC, rota, método, tenant, produto e status. O serviço cria contagens, contagens HTTP >=500, soma das durações observadas, quantidade de durações válidas e histograma fixo. Eventos com duração ausente contam como requests, mas não como duração zero.

Um cursor por ID e um lock transacional serializam execuções e mantêm a atualização dos buckets e do cursor atômica. Reexecuções não contam o mesmo evento novamente. Uma chegada tardia com ID novo atualiza o minuto original, mesmo que já agregado. A primeira observação de um minuto ainda aberto interrompe o lote sem avançar sobre ela: ela e eventuais observações tardias posteriores ficam para a próxima execução. O horário vem do servidor confiável; uma data futura indevida pode atrasar esse cursor e precisa ser diagnosticada.

```powershell
php artisan platform:aggregate-requests --limit=5000
```

Programar invocação a cada minuto e acompanhar backlog. Um limite não garante absorver volume maior que a capacidade de processamento. A migração é aditiva: três tabelas novas, sem atualização ou varredura da tabela de eventos existente. Rollback remove apenas essas tabelas.

`summary(from, to, tenantId?, product?)` lê intervalo semiaberto `[from, to)` de até 93 dias. A leitura se limita aos 10.000 buckets mais recentes, retorna `truncated` quando excedido e deve permanecer dentro de endpoint autenticado de Platform Admin. Não reutilizar como endpoint de tenant sem autorização própria. Os filtros devem passar pela validação e autorização já existente do Laravel.

Percentis são **estimativas do limite superior do bin**, não percentis exatos nem interpolados. Bins em ms: 10, 25, 50, 100, 250, 500, 1.000, 2.500, 5.000, 10.000, 30.000, 60.000, 120.000 e overflow. p50/p75/p95/p99 no overflow retornam `null`, pois não há limite finito informativo. Taxa de erro considera HTTP >=500 e não mistura 4xx de validação com falhas do servidor. Não chamar isso de SLA.

## Retenção, integração e limites

Este módulo não executa exclusões. A retenção curta dos eventos brutos continua sendo responsabilidade de `platform:prune`. Antes de apagar eventos API não agregados, o processo de retenção deve verificar o cursor; se a agregação estiver atrasada, preservar os eventos ainda necessários e alertar. Agregados não contêm observações individuais; sua retenção deve ser definida operacionalmente, sem armazenar cada request indefinidamente.

Não existe emissão externa de alertas, retry arbitrário de jobs, instalação de scheduler/worker, medição de GPU nem alteração de Python neste módulo. Chamadas de CLI em teste não comprovam operação permanente. Compatibilidade MySQL depende da suíte de homologação do projeto; resultado SQLite não aprova MySQL.

## Verificação específica

`PlatformRuntimeTest` cobre ausência de configuração, estados por idade, hostname protegido, identidade/futuro inválidos, comandos CLI, agregação limitada, reexecução idempotente, chegada tardia, separação por tenant/produto, percentis aproximados e duração desconhecida. Resultados executados devem ser registrados no relatório de validação da fase.

Execução específica em 22/10/2026: PHP 8.4.24, PHPUnit 12.5.40, SQLite em memória, **4 testes / 32 assertions aprovados**, 41,011 s, 60 MB reportados pelo PHPUnit. Pint: **6 arquivos aprovados**. O runner foi executado diretamente por `PHPUnit\TextUI\Application` com `--filter=PlatformRuntimeTest`, usando PHP `-n` e extensões essenciais explícitas (`mbstring`, `openssl`, `pdo_sqlite`, `fileinfo`, `curl`, `sodium`, `gd`, `zip`), em razão de demora operacional observada no launcher padrão. Esse tempo é da suíte local com migrations e não mede p95 da API nem constitui diagnóstico da lentidão do navegador. Não foi executado daemon nem teste MySQL por este módulo.

## Configuração e diagnóstico complementares

- PLATFORM_SCHEDULER_UUID: UUID dedicado à execução observada do scheduler. Definir a variável não inicia daemon nem produz heartbeat.
- PLATFORM_TIMING_DIAGNOSTICS: false por padrão; habilitar apenas de forma controlada. Contagens, duração e fingerprints de queries, sem SQL/bindings.
- RESEND_NATIVE_WEBHOOKS_ENABLED e STRIPE_NATIVE_WEBHOOKS_ENABLED: false por padrão. Exigem RESEND_WEBHOOK_SECRET / STRIPE_WEBHOOK_SECRET do endpoint correspondente. Signing secret não é a API key. Não publicar valores nem habilitar callbacks sem homologação.
- Exportação: conexão platform_ops database com retry_after180; worker queue:work platform_ops --queue=platform-ops --timeout=150 --tries=1. O gate de configuração também exige default queue assíncrona; nenhuma configuração global foi alterada nesta fase.
- Sondagem de bootstrap: iniciar temporariamente php -S 127.0.0.1:8002 -t public scripts/platform-request-diagnostic.php, requisitar /api/platform/overview sem token e encerrar. O arquivo storage/logs/platform-request-diagnostic.json tem somente estágios, contagens e status. Não usar esse router como aplicação pública.

Heartbeats de queue registram último loop; ausência recente não distingue job longo de processo encerrado. O gate permanece parcial mesmo quando há loop online, pois a cobertura de todas as filas não foi comprovada.
