# Validação da central SOL

> As seções iniciais registram a Fase 1. O estado integrado da Fase 2 está no final deste documento; resultados anteriores não são reutilizados como aprovação das novas funcionalidades.

Data: 22/10/2026. Branch: `Correção-visua/proteção`. Ambiente local Windows, PHP 8.4, SQLite, React/TypeScript/Vite. Não é homologação de produção ou de MySQL. Nenhuma dependência pesada, GPU, provedor pago ou serviço público foi criado.

## Evidências executadas

O smoke `scripts/platform-smoke.php` cria seu próprio operador temporário em APP_ENV=local, autentica por HTTP e consulta 25 endpoints. Também abre perfil 360, cria/encerra um incidente informativo e faz logout. Remove somente seu operador e tokens; preserva a auditoria. Não imprime senha, token ou corpo de respostas. A primeira execução aprovou todas as consultas (HTTP 200), criação de incidente (201), atualização e logout (200); consultas entre 289 e 459 ms, health 1.845 ms. Uma execução posterior será registrada separadamente, sem substituir medições anteriores.

QA no navegador utilizou outro operador descartável, sem reutilizar cookies pessoais. Foram inspecionados visão geral, cliente/perfil, trials, conversão, sinal de segurança controlado, requests, auditoria, IA e jobs. Capturas reais em `evidence/platform-admin/`. O sinal `possible_xss` foi provocado por texto inerte no campo de pesquisa, sem execução de código; ele comprova detecção heurística, não ataque confirmado. O detalhe não armazenou o texto pesquisado. Dados exibidos são metadados; nenhum token, fotografia ou conversa foi aberto.

O QA encontrou e motivou correções reais: classificação do trial escondida pela seleção automática de colunas; perfil excessivamente longo convertido em abas; evento de login sem ator porque LoginRequest usava outra instância de Request; auditoria de leitura movida para antes da consulta; respostas antigas descartadas por sequência/cancelamento; consulta com timeout de 15 segundos e opção Atualizar. O timeout foi observado efetivamente no navegador.

Durante a continuação, o host e as consultas locais ficaram muito mais lentos. Logs do servidor indicaram requests com 24–51 segundos; consultas de incidentes excederam o limite de 15 segundos. O volume dos resultados dos 15 jobs era apenas 26.533 bytes (máximo 2.996), portanto não há evidência de que esse payload explique a lentidão. Uma leitura do sistema mostrou cerca de 1,3 GiB livres em 15,9 GiB físicos. Isso não estabelece causalidade. Não há aprovação de performance nem teste de carga. Os serviços e processos alheios não foram encerrados.

## Cobertura e limites

Os testes backend verificam autenticação, e-mail confirmado, conta ativa, separação entre administrador de tenant e plataforma, três produtos, filtros, paginação, ausência de segredos, metadados, CSV e prevenção de fórmulas, auditoria transacional, falha fechada em leituras administrativas, rate limit real, coortes dentro da retenção, alertas deduplicados e feature flag. Não fazem auditoria geral de segurança do SaaS.

Os testes frontend incluem renderização real dos widgets via esbuild/ReactDOMServer: escape de strings hostis, omissão de metadados na tabela, distinção entre nulo/zero/falso, ausência de histórico e configuração não definida. Não são E2E de interação nem aprovação visual de todas as telas.

Permanecem parciais: MFA, ledger/receita conciliada, webhooks nativos comerciais, entrega/bounce de e-mail, atribuição UTM/visitantes consentidos, motivos históricos de churn, causalidade de conversão, uptime, APM distribuído, heartbeat de filas, quotas/custos por tenant, inventário completo de storage, métricas RAM/VRAM por job e validação operacional MySQL. Não receberam valores fictícios.

O scheduler está definido no código, mas seu daemon não foi instalado. Alertas ficam desabilitados até configuração explícita e não bloqueiam IP automaticamente. Não houve envio de e-mail externo, suspensão de cliente existente, cobrança ou exclusão de histórico. A Fase 8, os gates 3D e os três produtos permanecem preservados.

## Reprodução

1. Executar migrations aditivas e iniciar Laravel/Vite em loopback.
2. Executar `php artisan test --compact`, `npm test` e `npm run build` em frontend; Pint nos arquivos PHP alterados.
3. Em APP_ENV=local executar `php scripts/platform-smoke.php`; relatório contém somente status e tempos.
4. Autenticar operador ativo, realmente verificado e com PlatformAdmin; abrir `/platform`.
5. Filtrar o próprio ID em clientes/requests, abrir perfil, alternar abas, conferir trials e valores indisponíveis.
6. Criar incidente informativo de QA, abrir e encerrar como falso positivo; conferir audit log.
7. Abrir confirmação de controle e cancelar sem alterar configuração. Verificar foco/Escape e viewport móvel.
8. Fazer logout e remover apenas o operador descartável pelo procedimento local de QA.

As etapas que não terminarem na sessão devem permanecer identificadas como pendentes, mesmo quando testes automatizados passarem.


## Fechamento da validação

- PHP: 131/131, 1.047 assertions, zero erros/falhas/skips. A execução original de artisan test concluiu em 540.220 ms durante a lentidão. O mesmo PHPUnit/configuração, iniciado com bootstrap absoluto e extensões por comando, aprovou 131/131 em 23,759 s de testes (JUnit local, não publicado como log bruto).
- Pint: 28 arquivos PHP verificados/formatados, oito correções de estilo, saída zero.
- Índices locais: migration 000002 aplicada sem substituição de dados (18,63 ms).
- Smoke HTTP repetido: aprovado, 29 checks; relatório sanitizado em evidence/platform-admin/http-smoke.json. Sem autenticação, endpoint administrativo devolveu 401 em 374 ms.
- Após o host normalizar, UI retomada: incidente informativo 3 criado e encerrado como falso positivo; confirmação de pausa aberta/cancelada, botão desabilitado sem senha/checkbox; perfil 360 com abas, teclado ArrowRight e sessões sem token; trials com classificação visível; health mostrou Laravel/SQLite/Ollama online, Qdrant/FastAPI offline naquele instante; dashboard e navegação móvel 390×844 inspecionados. Nenhuma alteração de controle foi aplicada. Viewport restaurado.
- Capturas finais usam fullPage; capturas iniciais do painel visível podem estar recortadas pela ferramenta. Não são imagens geradas ou montagens.

A lentidão transitória permaneceu sem causa conclusiva. Uma resposta inicial rápida do servidor auxiliar era erro de diretório do router, não validação Laravel; foi descartada. Os servidores de diagnóstico foram usados somente em loopback e serão encerrados. Não houve alteração de php.ini global. A operação padrão voltou a responder e o build final voltou à ordem de segundos; isso não substitui benchmark de produção.

- Fechamento frontend após o último ajuste: 49/49 em 352,756 ms, TypeScript e Vite aprovados; build 10,03 s/3.409 módulos. Warning existente de chunks maiores que 500 KB permanece, sem erro. A última correção impede interpretar um campo booleano ausente como conta inativa.
- Operador QA 26, seu PlatformAdmin, tokens e arquivo privado de credenciais removidos. Dois servidores auxiliares encerrados; Laravel 8000 e Vite 5173 preservados. Auditoria e incidentes controlados de QA permanecem identificados como tal. Navegador voltou à tela Entrar, viewport restaurado.


## Fase 2 — integração de 22/10/2026

O marco P0 foi publicado em 920379f: MFA obrigatório por token, recuperação de uso único, reautenticação recente e gate frontend. Não houve inscrição automática de administradores existentes. QR local permanece pendente; chave manual e URI são reais.

Implementados a seguir: ledger imutável e grupos monetários por moeda; callbacks nativos Stripe/Resend desabilitados por padrão; telemetria de e-mail sem conteúdo; heartbeats observados; agregação APM por minuto; fingerprints de queries opt-in; otimização de consultas de atividade/trials; filas privadas de exportação; workflow LGPD; administração de operadores; quotas de admissão IA/reconstrução; inventário limitado sem exclusão; checklist objetivo de prontidão.

Revisão cruzada corrigiu interseção tenant/usuário em billing, callback Resend precoce com retry sem consumir receipt, avanço de entrega em empate temporal, ausência de plano histórico da invoice (grupo explicitamente não conciliado), IDs externos case-sensitive e verificação exata da assinatura. Nenhum pagamento ou acesso foi ativado por payload arbitrário.

Migrations 000004–000009 foram aplicadas com migrate --force no SQLite local, sem fresh/drop ou substituição de registros. O escopo MySQL permanece isolado e não homologado.

### Bloqueios observados nesta sessão

- Navegador: cua.listTabs retornou “Local browser use is disabled in Work settings”. Nenhuma captura nova e nenhum QA visual da Fase 2 aprovado. Não foi usado outro mecanismo para contornar a configuração. As capturas anteriores são exclusivamente da Fase 1.
- Smoke inicial atualizado: falhou com ConnectionException antes do login. Sondagem da API em 8000 expirou após cerca de 5,3s; a sondagem inicial de 5173 com Accept application/json respondeu 404. Uma requisição HTML correta respondeu 200; portanto o 404 foi artefato da sondagem, não defeito comprovado do frontend. Não interpretado como regressão de autorização nem sucesso operacional. Investigação de processos e nova execução são registradas quando concluídas.
- Memória física livre observada: aproximadamente 296 MiB de 15,9 GiB durante a lentidão. Medição isolada não estabelece causa; processos alheios não foram encerrados. A verificação TypeScript supersedida foi interrompida para reduzir concorrência e será reexecutada.
- MySQL ausente; contrato opt-in falhou por conexão recusada. CI preparada não implica CI remota aprovada.
- Sem cobrança real, envio externo, daemon instalado, GPU contratada, backup ou restore executado. Custos sem metodologia continuam nulos.

### Escopo que permanece parcial

Checkout e abandono reais não estão conectados; renovação/refund nativos exigem adaptadores e conciliação adicionais. Receita recebida de invoice assinada não comprova MRR ou direito de acesso. Quotas de tokens/imagens/storage/concorrência não são anunciadas como aplicadas; IA/reconstrução só impõem teto quando configurado. Não há contabilização nova de RAM/VRAM por job nem heartbeat de processo AI/3D. Heartbeat de queue mede último loop, pode atrasar em job longo e não demonstra cobertura de todas as filas. Exportações têm expiração de acesso, mas purge físico/recovery após kill abrupto permanecem pendentes. Inventário de órfãos é hipótese por referências conhecidas, não autorização para excluir.

Concorrência de criação de reconstruções ainda precisa de reserva atômica; não foi declarada testada em múltiplos processos. Resta validar índices/locks/collation e todos os workflows em MySQL real, planos de execução e p95 sob carga representativa.

### Diagnóstico HTTP segmentado

O router restrito scripts/platform-request-diagnostic.php aceita somente GET local sem Authorization em /api/platform/overview, carrega a aplicação real e preserva o 401 esperado. Mede autoload, definição da aplicação, bootstrap do kernel e handle/terminate separadamente. Não é navegador, não usa sessão privada e não altera o router normal. O servidor auxiliar 8002 foi encerrado após duas requisições. Evidência sanitizada: evidence/platform-admin/phase2-request-timing.json.

| Estágio ms | Primeira amostra | Segunda amostra |
|---|---:|---:|
| Cliente HTTP | 17.977 | 12.254 |
| Autoload Composer | 2.132,94 | 2.052,56 |
| Definição da aplicação | 854,36 | 19,32 |
| Bootstrap kernel | 10.606,54 | 7.631,14 |
| Handle + terminate | 3.438,08 | 1.602,45 |
| Tempo SQL observado após middleware | 1.045,15 | 106,96 |

Ambas observaram sete queries, 723 arquivos incluídos, pico PHP 28MiB e status401. O tempo SQL é subconjunto do handle, não deve ser somado a ele. O listener não mede queries anteriores ao início da observação. Bootstrap dominou essas duas requisições; isto não identifica a causa subjacente nem comprova problema de memória, filesystem ou banco. Nenhum health probe externo foi executado por esse endpoint sem autenticação. O cenário não mede autenticação, que foi avaliada separadamente no benchmark in-process. Performance continua sem aprovação.

### Testes já concluídos na integração

- PHP completo: 180 testes, 1.498 assertions, três skips MySQL, saída0, 15:10.104 e92MiB. Após os últimos ajustes de destinatário de e-mail e dois contratos MySQL adicionais, uma rodada congelada separada foi iniciada; não se reutiliza esse número como resultado da rodada nova.
- Frontend atual: 53/53, saída0, 34.250,475ms; quatro novos casos renderizam componentes reais para exportação privada, workflow humano, incidentes e flags críticas. Não são E2E.
- Pint inicial: 58 arquivos; revisão final17; mais3 arquivos de e-mail revisados com saída0. O runner inicial carregou desnecessariamente tokenizer como extensão dinâmica e avisou que o DLL não existia; tokenizer já é nativo. Comando corrigido nas rodadas seguintes, sem alterar php.ini.
- Migrations aditivas aplicadas localmente: 000004–000009, saída0.
- Publicação: 920379f (MFA) e f5d3687 (ledger), ambos na branch autorizada; semPR.

### Fechamento backend e roteiro visual pendente

Rodada congelada final: **183 testes, 1.506 assertions, 5 skips MySQL, exit0; 12:30.076 reportados pelo PHPUnit, pico PHP92MiB**. O tempo do runner incluindo preparação/descoberta foi817.741,9ms, registrado separadamente em evidence/platform-admin/phase2-backend-validation.json. Frontend final: **55/55, exit0, 52.989,7725ms**. Dois testes novos inicialmente falharam porque o harness não mapeava o componente Overview importado; corrigido somente o mapeamento e preservadas as assertions. Pint:57PHP inspecionados; apenas o router de diagnóstico precisou de estilo, corrigido e rechecado com --test aprovado.

O build limitado experimentalmente a768MiB de heap aprovou TypeScript, mas Vite terminou com heap out of memory. Uma nova execução Vite com1536MiB está separada; nenhuma aprovação é inferida da tentativa interrompida. Não houve mudança de configuração global ou dependências.

QA visual a executar quando o navegador local estiver habilitado:

1. Em operador descartável autorizado, login, cadastro TOTP manual, confirmação, novo token/desafio, recuperação e reset; não capturar segredo/códigos em screenshots. API já possui regressões para replay/rate limit/autorização.
2. Conferir dashboard, score/checklist, valores financeiros indisponíveis, perfil360/financeiro, trials, conversão e estado de um único dia observado.
3. Criar incidente informativo de QA, atribuir responsável, registrar nota/eventos, encerrar como falso positivo; classificar um sinal sem bloquear IP. Abrir confirmação de flag crítica e cancelar.
4. Conferir heartbeat sem inferir daemon, filas, saúde, APM, IA/RAG e metadados3D. Criar exportação filtrada ao próprio usuário com worker dedicado; conferir proprietário, expiração, cancelamento e retry. Workflow LGPD não deve excluir dados automaticamente.
5. Inspecionar desktop e390×844, foco/teclado/Escape, menu e formulários; fazer logout. Registrar capturas reais somente após inspeção. Enquanto isso, QA visual permanece bloqueado, sem novas screenshots aprovadas.

### Publicação do workflow

GitHub recusou .github/workflows/platform-ci.yml: credencial OAuth sem escopo workflow. SSH existente também não autenticou e o conector GitHub retornou403 Resource not accessible by integration. Nenhuma nova chave/credencial/escopo foi criada. O commit CI inicialmente não publicado foi reestruturado preservando SHA256 de15arquivos de trabalho; apenas nossa história local não publicada foi alterada, sem force-push, branches extras ou alteração de main. Backend/testes foram publicados em6d7c91b/25a3860. O workflow permanece pronto localmente até autorização apropriada; CI remota não está aprovada.

Smoke real de fila dedicado: o harness local conferiu platform-ops vazia, invocou queue:work com stop-when-empty/max-time10/max-jobs1 e saiu0. Observado um heartbeat novo, nenhum fixturejob despachado e nenhum daemon permanente instalado. Fonte reproduzível em evidence/platform-admin/phase2-queue-smoke.php e resultado em platform-phase2-queue-smoke.json. Executar somente no ambiente local autorizado, com a fila dedicada vazia; não usar filas de clientes. O estado recente do heartbeat não significa processo ainda em execução.

### Build final e separação de módulos

Vite reexecutado com --max-old-space-size=1536: **exit0, 3.412 módulos, 13m48s**. TypeScript já havia retornado0 antes da etapa Vite no pipeline; nenhum fonte TypeScript foi alterado depois dessa execução. O warning >500KB permanece (Three746,49KB e MapCanvas1.077,85KB). Nenhum limite de warning foi aumentado.

Manifesto auditado em evidence/platform-admin/phase2-bundle-audit.json: admin47.773bytes/15.316gzip; grafo estático do admin780.743bytes incluindo bibliotecas compartilhadas; nenhum Three/GLTF/MapCanvas/globo nesse grafo, entrada comum sem admin imediato. Trata-se de inspeção de artefatos, não QA de rede/navegador. A separação por rota já existia; sem baseline comparável não foi declarada redução de tamanho.

Publicação da interface: 82ee5da; fonte e testes revisados, sem .env, credenciais, datasets, pesos, GLBs ou dist preparados para commit. O arquivo de workflow continua separado por permissão, não por falha de código presumida.

Inventário real executado com --limit=1000:104arquivos,137.881.559bytes,14,803s,truncatedfalse e delete_executedfalse. Somente discos local/public e conjunto limitado de referências; não equivale a inventário completo de serviços/datasets/logs nem confirmação de órfãos. Evidência sanitizada em phase2-storage-inventory.json. Nenhum arquivo existente foi movido ou excluído.
