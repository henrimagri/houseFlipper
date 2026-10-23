# Observabilidade operacional — Fase 2

A central reutiliza Laravel, Sanctum e os eventos existentes. Leituras administrativas exigem conta ativa, e-mail verificado, Platform Admin e MFA do token; a auditoria antecede a consulta. Dados operacionais não concedem acesso aos produtos dos tenants.

## Requests, APM e diagnóstico

platform_events mantém metadados indexados, UUID de request/trace gerado pelo servidor e rotas parametrizadas. X-Request-ID permite correlação sem armazenar corpos, query strings completas, senhas, cookies, tokens ou headers. Listagens têm paginação de servidor, janela máxima de 93 dias e filtros validados. Overview mantém cache curto de 30 segundos.

O relatório amostral anterior continua disponível e identificado como tal. A nova API administrativa /api/platform/apm usa RequestAggregates: buckets UTC de um minuto por rota, método, tenant, produto e status. O cursor transacional processa lotes limitados, não conta duas vezes a mesma observação e incorpora chegadas tardias no minuto original. Minutos abertos são adiados. Consultas limitam a leitura a 10.000 buckets e informam truncamento.

p50/p75/p95/p99 são estimativas do limite superior de bins fixos, não percentis exatos. Overflow acima de 120 segundos e ausência de duração retornam percentis nulos quando não há limite informativo; duração ausente não vira zero. Taxa de erro considera HTTP >=500. /api/platform/slow lista requests observados com duração >=1.000 ms, request ID, usuário e tenant, sem payload. Contratos completos em RUNTIME.md.

PLATFORM_TIMING_DIAGNOSTICS habilita contagem/tempo de queries, fingerprints SHA-256 de até dez queries lentas e estágios do pipeline administrativo. SQL e bindings não são retornados. Os tempos de banco, auditoria, gate e probes podem se sobrepor: não somar como fases exclusivas. Isso não mede separadamente toda serialização, filesystem, autenticação e chamada externa. O diagnóstico não estabelece sozinho a causa dos outliers de 24–51 segundos vistos no QA anterior; medições e limites estão em MYSQL_VALIDATION.md e VALIDATION.md. Não declarar SLA ou prontidão com o benchmark local.

## Health e histórico observado

Health consulta SELECT 1/migration e somente serviços configurados com allowlist, sem redirects, conexão de até 1 segundo e timeout de até 2 segundos por probe, cache de 30 segundos. Diferencia online, offline, timeout, desabilitado/não configurado e resposta degradada. FastAPI usa autenticação interna; tokens e endpoints privados não são devolvidos. Probes sequenciais ainda podem somar latência no cache frio.

platform:probe-health pode registrar status em platform_health_samples por serviço/minuto. /api/platform/healthHistory resume somente os minutos efetivamente observados; intervalos sem coleta são desconhecidos. Não é histórico retrospectivo, uptime contínuo nem SLA. A definição no scheduler não comprova execução permanente.

Storage físico mostra espaço do disco do processo. O inventário de metadados é executado fora de requests, com limite de arquivos e tempo; sua associação a referências/tenant ainda é parcial e pode ficar desatualizada. Arquivo sem referência reconhecida é candidato a revisão, não órfão comprovado. Nenhuma exclusão automática é autorizada por esse relatório.

## Scheduler, filas, IA e 3D

PLATFORM_SCHEDULER_UUID identifica o scheduler quando sua execução real emite heartbeat. Queue::looping registra o último loop real do worker. Estados derivados por idade: online até 90 segundos, delayed até 300, offline depois; ausência de observação é unconfigured. Hostname fica em HMAC, sem nome bruto. Heartbeat de fila pode envelhecer durante job longo e não informa quais filas/conexões estão cobertas. Por isso o gate de filas permanece parcial mesmo com worker observado online.

Contagens de jobs não comprovam consumidor vivo. Throughput/latência de fila e cobertura completa não foram demonstrados. Heartbeats dos processos AI e 3D ainda não estão integrados; health HTTP não os substitui. Métricas ausentes de tokens, custos, VRAM e tempo GPU continuam nulas. Nenhum valor financeiro por token Ollama é presumido.

## Execução preparada, não daemon instalado

O scheduler declara avaliação de alertas, agregação de requests e probes a cada minuto, além de prune diário. O operador precisa instalar e acompanhar o processo de execução. Não há envio externo de alertas. CI está preparada, mas sua execução remota ainda não foi comprovada. MySQL real permanece indisponível neste host. Ver PRODUCTION_READINESS.md para bloqueios.
