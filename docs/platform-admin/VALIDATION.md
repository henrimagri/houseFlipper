# Validação da central SOL

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
