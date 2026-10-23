# MySQL, CI e volume — Fase 2

## Isolamento e comandos

`phpunit.mysql.xml` é uma configuração separada. Ela força `APP_ENV=testing`, conexão MySQL, banco **platform_test_ci**, usuário **platform_test**, URL vazia, mailer array e fila sync. As senhas literais dessa configuração são exclusivamente do serviço efêmero de CI; não são credenciais da aplicação.

`PlatformMysqlContractTest` verifica essas condições **antes do bootstrap Laravel**, recusa configuração em cache e depois confirma driver e `SELECT DATABASE()`. Não utiliza `RefreshDatabase`, `migrate:fresh`, `db:wipe`, nem lê `.env` para escolher um banco. Executa migrations aditivas somente no schema explicitamente autorizado; fixtures de cada teste são revertidas por transação. O usuário de CI deve ter permissão apenas nesse schema. Não execute com uma conta de produção.

Comandos:

```text
php vendor/bin/phpunit
php vendor/bin/phpunit -c phpunit.mysql.xml
php vendor/bin/pint --test scripts/platform-volume-benchmark.php tests/Feature/PlatformMysqlContractTest.php
php scripts/platform-volume-benchmark.php
```

Na suíte padrão SQLite, os testes MySQL são ignorados deliberadamente, sem conectar a outro serviço. No opt-in, indisponibilidade do MySQL causa falha explícita; nunca é contabilizada como aprovação.

Os contratos verificam tabelas/migration e índice composto, extração JSON distinta, agrupamento `DATE`, paginação de servidor, limite inválido, unicidade `(kind,key)` e rollback transacional. Não demonstram todas as funcionalidades Laravel em MySQL, plano de execução de produção, concorrência ou ausência de regressões em todas as versões do MySQL.

## CI preparada

`.github/workflows/platform-ci.yml` contém três jobs independentes, com timeout de 15 minutos e permissões `contents: read`:

- PHP 8.4: Composer com lockfile, suíte completa SQLite e Pint limitado aos PHP alterados no intervalo do evento.
- MySQL 8.0 em serviço efêmero, schema/conta exclusivos de testes, health check e suíte contratual separada.
- Node 22: `npm ci --ignore-scripts`, testes frontend e build que inclui TypeScript e Vite.

O workflow não publica aplicação, não executa deploy e não depende de segredos de produção. Checkout não persiste credenciais. Sua existência não comprova execução bem-sucedida no GitHub: o resultado remoto precisa ser consultado após publicar o commit.

## Benchmark reproduzível

`scripts/platform-volume-benchmark.php` define o ambiente **antes** de carregar Laravel e recusa driver diferente de SQLite ou banco diferente de `:memory:`. Não possui argumento para apontar a outro banco. Dados sintéticos: **10.000 usuários, 100.000 eventos, dos quais 50.000 API**. O banco desaparece ao encerrar o processo. Não envia e-mails nem chama provedores de IA.

O operador sintético possui uma credencial MFA e token efêmeros no banco em memória; segredo, token e senha não são impressos. Limites: 384 MB de heap PHP e 180 segundos. Inserts são feitos em lotes de 500, sem carregar todos os eventos simultaneamente.

O JSON contém tempo de seed/migrations, cinco repetições de listagem, busca, dashboard frio/quente, serialização, insert de auditoria e middleware com handler vazio; três repetições do kernel HTTP incluem autenticação real por bearer, verificação MFA, auditoria, middleware, consultas e serialização. Conta queries e tempo SQL sem imprimir SQL ou bindings. Compara clientes com 10 e 100 linhas para detectar crescimento de queries nesse caminho.

São medições **in-process SQLite**, não MySQL, rede, capacidade comercial ou teste de carga concorrente. O pico registrado é alocação PHP, não RSS do processo ou VRAM. As distribuições de fixtures são simplificadas; em particular, não medem N+1 de trials com assinaturas. Percentil p95 usa nearest-rank em amostra pequena e serve como diagnóstico, não SLA.

## Evidência local

Ambiente verificado: Windows/PHP 8.4. Nenhum comando `mysql`, `mysqld` ou `docker` foi encontrado no PATH, e não houve listener local na porta 3306 no momento da verificação. Nada foi instalado e nenhum serviço/configuração do Windows foi alterado.

Primeira execução padrão do novo contrato: **3 ignorados, 0 assertions** (5.114 ms reportados). A execução MySQL real depende do serviço isolado descrito acima e permanece sem aprovação local. Resultados adicionais de execução são registrados abaixo quando disponíveis.

### Execuções de 22/10/2026

- `php vendor/bin/phpunit -c phpunit.mysql.xml`: **3 erros, 0 assertions, 30.369 ms**. Conexão `127.0.0.1:3306` recusada antes de qualquer migration. Classificação: **bloqueado por serviço indisponível**, não aprovado e não atribuído a incompatibilidade SQL.
- Primeiro benchmark: interrompido pelo limite operacional de **180 segundos** durante consulta DB. Sem relatório completo, portanto não usado para métricas. Foi adicionado diagnóstico de fase/tempo e shutdown; não foi alterado o limite.
- Segundo benchmark: **concluído, exit 0**, em 141.132,395 ms. Na revisão, foi identificado que o AuthManager podia reaproveitar guards entre requisições no mesmo processo; essa execução não é usada como medição de autenticação renovada.
- Execução final após `forgetGuards()` antes de cada requisição HTTP: **concluída, exit 0**, em **121.982,887 ms**; checked_at **2026-10-22T23:30:29+00:00**. Fixtures completas (100.000 eventos/50.000 API/10.000 usuários); migrations+seed **65.617,879 ms**; pico PHP **44 MB**. A duração da preparação está separada dos tempos abaixo. O código da Fase 2 estava em integração; não se infere ganho causal comparando essas execuções.

| Caminho real | Repetições | Mediana ms | p95 nearest-rank ms | Queries mín–máx |
|---|---:|---:|---:|---:|
| Clientes, 10 linhas | 5 | 1.237,831 | 1.978,083 | 3–3 |
| Clientes, 100 linhas | 5 | 988,859 | 1.617,951 | 3–3 |
| Eventos, página de 25 | 5 | 1,442 | 469,853 | 1–1 |
| Busca request ID em eventos | 5 | 1.490,014 | 1.894,592 | 1–1 |
| Dashboard, cache frio | 5 | 5.221,358 | 7.408,538 | 16–16 |
| Dashboard, cache aquecido | 5 | 1,002 | 276,844 | 0–0 |
| Serialização de 100 clientes | 5 | 0,111 | 0,201 | 0–0 |
| Insert de auditoria | 5 | 0,775 | 258,739 | 1–3 |
| Middleware com handler vazio | 5 | 1,570 | 516,916 | 3–3 |
| Kernel HTTP `/api/auth/me` | 3 | 482,496 | 1.472,185 | 6–7 |
| Kernel HTTP clientes | 3 | 751,226 | 1.582,997 | 12–13 |
| Kernel HTTP dashboard aquecido | 3 | 468,599 | 558,074 | 9–10 |
| Kernel HTTP eventos | 3 | 373,490 | 793,572 | 10–11 |

Não há crescimento de queries de clientes entre 10 e 100 registros nessa fixture. Isso não significa que todos os módulos estejam livres de N+1. Não houve medição do caminho trials anterior à correção da Fase 2.

**Pendências de desempenho reais:** dashboard frio e busca em eventos ficaram lentos nessa execução. Na busca, o máximo SQL foi 1.892,39 ms; no dashboard frio, 6.490,93 ms. A medição não comprova que a causa seja Windows, contenção ou um índice específico. Middleware/kernel também tiveram outliers; nenhuma causa foi estabelecida. Não foi declarado SLA nem prontidão para grande volume. Próximas medições devem separar plano de execução, distribuição por tenant, cache, aquecimento e concorrência em MySQL 8 real.

O middleware isolado recebe handler vazio, sem autenticação; kernel HTTP inclui o fluxo protegido, sem desabilitar gates. O token MFA é fixture local em memória; não existe bypass no código de produção. O benchmark preserva todos os arquivos e o banco local da aplicação.

Verificação final dos dois PHP novos: `pint --test` aprovado e `php -l` sem erros; suíte padrão filtrada novamente: **3 ignorados, 0 assertions, 3.261 ms**. Execução de CI remota e aprovação contratual MySQL permanecem pendentes.

### Contratos adicionais após revisão cross-DB

Foram acrescentados **dois contratos MySQL**, elevando a suíte opcional para cinco testes. Não foram executados localmente: MySQL continua indisponível, e os números de três testes ignorados/erros acima são evidências das execuções anteriores, não resultados da suíte ampliada.

- Ledger e recibos: dois IDs externos que diferem apenas em caixa devem gerar registros independentes; repetir exatamente o mesmo ID deve preservar a idempotência. O ledger é exercitado pelo serviço `BillingLedger`, com assinatura e tenant sintéticos dentro da transação isolada. Isso cobre o risco de `utf8mb4_unicode_ci` colapsar IDs externos; as novas migrations passaram a definir collation binária no MySQL.
- Quotas: `ResourceQuota` deve contabilizar a mesma operação uma vez, negar operação adicional com HTTP 429 sem deixar admissão parcial, reverter uso/admissão em rollback e preservar escopos independentes de produto e tenant. São verificações sequenciais e transacionais; não comprovam corrida entre conexões/processos simultâneos.

XML, schema/conta autorizados, guard pré-bootstrap e ambiente local da aplicação foram preservados. Apenas o arquivo de contrato foi formatado; a execução efetiva desses cenários permanece pendente no serviço MySQL 8 isolado da CI.
