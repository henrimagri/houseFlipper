# Auditoria técnica — relatório parcial

Data: 16/10/2026. Estado: EM ANDAMENTO; aplicação não aprovada para produção.

## Evidências verificadas

- 52 testes aprovados, 318 asserções; suíte inicial: 20 testes/89 asserções.
- Novos testes: SecurityRegressionTest, DomainIntegrityTest, FinancialConsistencyTest, ImportSecurityTest, SpreadsheetSafetyTest.
- Build React/TypeScript aprovado após atualizações (Vite 6.4.4).
- Composer audit: nenhum aviso após atualização dentro das restrições existentes.
- npm audit: 27 pacotes sinalizados inicialmente; 7 restantes, relacionados à cadeia Tailwind 3 (5 altos e 2 moderados). Avisos de dependência não equivalem a exploração comprovada.
- Login/logout e restrição de importação renderizados e exercitados no navegador local.
- Menu móvel aberto e navegação para oportunidades verificados.

## Cobertura e limites

Backend Laravel 13/PHP 8.4, React 18/TypeScript, SQLite local, Sanctum, Spatie Permission e importação CSV/ODS/XLSX. Controllers, requests, policies, isolamento por tenant, serviços financeiros e importação foram examinados. Inventário de rotas em SECURITY_MATRIX.md e routes.json.

Ainda falta completar a revisão de todos os serviços e páginas, executar os casos negativos de cada operação, concorrência, validação visual de todos os formulários e testar falhas de integrações com respostas simuladas. Produção, TLS do deploy, permissões do servidor e banco MySQL não foram testados. Nenhum ataque contra provedores externos foi executado. Não há alegação de conformidade ASVS nem de ausência total de vulnerabilidades.

## Pendências antes de produção

- Resolver dependências de Tailwind sem perder compatibilidade visual; migração de versão principal exige revisão de CSS.
- Eliminar dados estáticos de demonstração no dashboard e módulos incompletos; não apresentar números fictícios como resultados da conta.
- Completar testes de isolamento por operação e de concorrência financeira/importação.
- Revisar exposição das fotos em storage público, política de privacidade e ciclo de exclusão dos arquivos.
- Revisar sandbox e rede do navegador de coleta; executável atual tem caminho Linux fixo e flags sem sandbox.
- Rever importações grandes: XML limitado para proteção de memória; CSV recomendado para bases maiores.
- Preparar configuração real de produção: debug desligado, HTTPS, cookies seguros, headers/CSP, backup, fila e monitoramento.

Os relatórios serão atualizados conforme os módulos restantes forem verificados. Nenhuma credencial ou token de acesso está incluído.




## Atualização — dependências e headers (16/10/2026)

Os 7 alertas npm descritos nas seções anteriores foram resolvidos nesta continuação. Migração oficial @tailwindcss/upgrade 4.3.3 executada, com atualização de CSS, PostCSS e classes equivalentes nos templates. npm-audit-after-tailwind4.json registra ZERO avisos. Build TypeScript/Vite aprovado (frontend-build-tailwind4.txt). Servidor Vite reiniciado para descartar plugin PostCSS antigo em memória; tela de oportunidades e formulário renderizados no navegador local após recarga. Cobertura visual ainda parcial, não equivale a validação de todas as páginas.

API agora envia Cache-Control no-store/private, nosniff, Referrer-Policy no-referrer e X-Frame-Options DENY. ApiHeadersTest confirma headers no login e ausência de cache também em resposta de recurso privado. HTTPS/HSTS e CSP do frontend continuam dependentes da configuração de deploy e não foram declarados implementados.

Snapshot atual: 53 testes aprovados, 328 asserções. Nenhum aviso em Composer audit anterior nem no npm audit atual. Permanecem as demais limitações listadas, especialmente concorrência, cobertura completa de UI e dados estáticos.

## Snapshot mais recente

57 testes aprovados / 349 asserções. Migration de sincronização de arquivamento aplicada localmente. Expiração real e limite de idade de token legado testados com avanço de relógio; fluxo de oportunidade sem fotos aprovado. BUG 19 detalha divergência de status/flag corrigida.

## Snapshot mais recente — edição

58 testes PHP aprovados / 355 asserções e 2 testes frontend aprovados; build aprovado. BUG 20 corrige campos opcionais que não podiam ser limpos pela interface. Demais pendências da auditoria permanecem.

## Snapshot mais recente — atomicidade

59 testes PHP aprovados / 368 asserções. BUG 21 elimina persistência parcial nos endpoints de custos e resultados quando recálculo/progresso falha. Concorrência real e revisão transacional dos demais endpoints seguem pendentes.

## Snapshot mais recente — financiamento

60 testes PHP aprovados / 378 asserções. BUG 22 corrige persistência parcial de financiamento. Principais mutações de análise/custos/financiamento/resultados são transacionais e bloqueiam a análise nos bancos compatíveis. Testes de rollback comprovados; concorrência real permanece pendente.

## Snapshot de concorrência

Três cenários concorrentes reais no MariaDB isolado passaram: análise principal única, financiamento selecionado único e total correto de custos. Seis requisições concluídas (200/201), resultado final passed=true. Aplicação original continua em SQLite; banco de teste encerrado. Ainda pendentes concorrência da importação/vendas/etapas e outros cenários do checklist.

## Snapshot mais recente — datas importadas

61 testes PHP aprovados / 383 asserções. BUG 23 corrige deslocamento silencioso de datas impossíveis na normalização ITBI, com testes de datas válidas, bissextas e mensais. Nenhuma nova alegação de cobertura dos itens ainda pendentes.

## Snapshot mais recente — UI

Build aprovado após correção de identidade; conta autenticada aparece no cabeçalho em navegador. Quinze rotas de módulos visitadas/renderizadas; Projetos teve carregamento final verificado. Evidência em ui-smoke.json. Não há alegação de CRUD completo nas telas. Permanecem funcionalidades de demonstração, PageStub e demais pendências descritas.

## Snapshot mais recente — perfil e consumo

63 testes PHP aprovados / 395 asserções. BUG 25 corrige erro 500 em perfil sem empresa e rejeita gravação empresarial sem vínculo. Rotas custosas possuem limites próprios testados sem contato com terceiros. Limitações de cobertura e demais pendências permanecem.

## Snapshot mais recente — validação de nulos

65 testes PHP aprovados / 407 asserções. BUG 26 corrige campos nullable que geravam violação NOT NULL e erro 500. Regras de omissão/default preservadas; sale_costs vazio normalizado. Persistem pendências explicitadas na auditoria.

## Snapshot mais recente — recursos de origem excluída

66 testes PHP aprovados / 417 asserções. BUG 27 corrige acesso/cálculo de análise cuja oportunidade foi excluída; mutações financeiras ligadas passam a ser negadas pela policy. Permanecem as demais pendências de cobertura e funcionalidades demonstrativas.

## Snapshot mais recente — identidade ITBI

67 testes PHP aprovados / 420 asserções. BUG 28 corrige colisão que sobrescrevia imóveis sem cadastro. Migration de reidentificação aplicada localmente; reimportação mantém duas linhas. Risco residual: perdas históricas por sobrescrita só podem ser reconstruídas com arquivos originais, sem recuperação automática presumida.

## Snapshot mais recente — painel

68 testes PHP aprovados / 432 asserções. Dashboard e resumo lateral agora mostram dados reais por empresa; cálculo e isolamento testados, build e estado vazio renderizado aprovados. BUG 14 permanece parcial para demais telas demonstrativas. Endpoint /api/dashboard inclui autenticação, conta ativa, headers de API e limite geral.

## Snapshot mais recente — limites calculados

69 testes PHP aprovados / 438 asserções. BUG 29 impede persistência de resultados incompatíveis com a precisão decimal do banco. Payloads extremos são rejeitados sem gravações parciais. Demais itens pendentes permanecem registrados.

## Snapshot mais recente — etapas de obra

72 testes PHP aprovados / 455 asserções. BUG 30 corrige criação/alteração em projeto excluído e status sem validação. Soma de orçamento e rollback de etapas testados. Os limites de cobertura restantes permanecem no checklist.

## Snapshot mais recente — identidade de login

74 testes PHP aprovados / 459 asserções. BUG 31 corrige divergência de capitalização entre login/cadastro e evita seleção arbitrária em duplicações legadas. Reconciliação de eventuais contas antigas equivalentes depende de revisão, sem mescla automática.

## Snapshot mais recente — arquivos de oportunidade

76 testes PHP aprovados / 468 asserções. BUG 32 corrige arquivos novos órfãos após rollback e preserva fotos prévias. Riscos residuais: órfãos históricos e política de acesso público das fotos exigem tratamento próprio.

## Snapshot mais recente — galeria

78 testes PHP aprovados / 477 asserções. BUG 33 corrige limite acumulado de fotos e índice de capa inválido. Fluxo legítimo da vigésima foto aprovado. Quota global por conta e galerias históricas continuam sem alteração automática.

## Snapshot mais recente — parâmetros e erros

80 testes PHP aprovados / 521 asserções. BUG 34 corrige erro 500 para IDs inválidos nos controllers legados. Respostas de erro 401/422 com no-store/nosniff verificadas. Auditoria ainda não concluída nos itens pendentes.


## Snapshot — execução local do scraper
BUG 35 corrigido: navegador configurável com detecção local e sandbox por padrão. Smoke real about:blank aprovado no Windows. 80 testes PHP / 521 asserções e 5 testes frontend aprovados. Coleta externa e demais pendências da auditoria permanecem abertas.
