# Auditoria técnica — relatório parcial consolidado

Data: 16/10/2026. Estado: EM ANDAMENTO. Aplicação ainda não aprovada para produção.

## Evidência atual

- Suíte PHP: 121 testes aprovados, 689 asserções. Baseline: 20 testes/89 asserções.
- Frontend: 5 testes de helpers aprovados; build TypeScript/Vite aprovado após a correção de importação parcial. O build alerta para bundle JavaScript acima de 500 kB.
- Últimas verificações de dependências: Composer sem avisos e npm sem avisos após migração Tailwind 4. Evidências versionadas em meta; não equivalem a certificação de segurança contínua.
- Três cenários reais de concorrência em MariaDB isolado passaram: análise principal, seleção de financiamento e soma de custos. Seis requisições; banco temporário encerrado. A aplicação principal continua usando SQLite.
- Navegador local do scraper iniciou com sandbox habilitado e abriu somente about:blank. Nenhum portal externo foi atacado ou usado nesse teste.

## Problemas e correções

42 registros de problemas confirmados em QA_REPORT.md. BUG 13 (responsividade) e BUG 14 (dados demonstrativos/funcionalidades incompletas) continuam parcialmente corrigidos; demais registros descrevem correções e seus limites de verificação. Esse total inclui problemas funcionais, de lógica e interface, não apenas vulnerabilidades.

Correções de segurança incluem isolamento por tenant, bloqueio de contas/empresas inativas, expiração e revogação de tokens, limites de acesso, autorização específica para escrever ITBI, listas permitidas de campos aninhados, erros neutros de integrações, validação de uploads/planilhas e headers de API. Correções de integridade incluem transações financeiras, recálculo de financiamento, exclusões/cancelamentos, identidade de registros ITBI, limites calculados, estados de oportunidades/etapas e limpeza de arquivos novos em rollback.

Dashboard e identidade do cabeçalho usam dados reais. Outros módulos ainda exibem dados de demonstração e ações incompletas. A importação parcial agora é sinalizada explicitamente; teste confirma recuperação por reenvio sem duplicar a linha já gravada. O alerta visual específico ainda precisa ser exercitado no navegador. Recuperação CLI de lotes locais/parciais e propagação de erros para automações foram corrigidas e testadas (BUG 38).

O detalhamento por categoria, severidade, causa e reprodução está em QA_REPORT.md; as contagens finais de vulnerabilidades por severidade serão reconciliadas na revisão final. Não apresentar os 42 registros como 42 vulnerabilidades.

## Cobertura efetivamente obtida

Backend Laravel/PHP, frontend React/TypeScript, SQLite, Sanctum, Spatie Permission, requests/policies/controllers, serviços financeiros, importação CSV/ODS/XLSX e parte dos provedores. Inventário em SECURITY_MATRIX.md. Testes negativos cobrem anonimato das rotas protegidas, isolamento entre empresas e mutações financeiras de outro usuário, contas suspensas, expiração, validação de tipos, rollback e limites específicos.

Login/logout, navegação de módulos, menu móvel, oportunidade e estados de dashboard foram renderizados ou exercitados conforme evidências registradas. Visitar uma rota não comprova CRUD completo, acessibilidade ou todos os estados de rede.

## Trabalho ainda obrigatório

- Completar revisão de serviços e casos negativos por endpoint; atualizar matriz com evidência individual.
- Testar CRUD visual, botões, busca, filtros, paginação, teclado, foco e viewports por módulo; corrigir demonstrações e ações incompletas preservando funcionalidades.
- Completar importação: concorrência, interrupção, recuperação geral, arquivos próximos aos limites, formatos amplos de CSV/ODS/XLSX. O teste de recuperação sequencial atual não prova esses cenários.
- Revisar fotos em storage público, privacidade e ciclo de exclusão; tratar possíveis órfãos históricos sem presumir perda/reconstrução automática.
- Revisar egress, redirecionamentos, downloads e extração do scraper; sandbox local habilitado não comprova segurança de toda a integração.
- Revisar concorrência de vendas, etapas e demais mutações não cobertas pelos três cenários MariaDB.
- Configuração efetiva de produção: HTTPS, debug, cookies, CORS/CSRF conforme arquitetura adotada, CSP, logs, permissões, backups e monitoramento.
- Executar segunda revisão das alterações e verificações finais após concluir os módulos pendentes.

## Avaliação

Os testes executados acima passaram; a falha reproduzida nesta etapa (mensagem de sucesso em importação parcial) foi corrigida e o teste de regressão passou. Testes não executados estão registrados no checklist e nas limitações acima, e não são considerados aprovados. Não há afirmação de conformidade ASVS nem de ausência de vulnerabilidades.

Os históricos anteriores foram preservados em history/FINAL_REPORT-before-consolidation.md. QA_REPORT.md e FIXES.md mantêm os registros rastreáveis. Nenhuma senha, chave ou token deve constar nos relatórios.







## Evidência adicional de transferência real
Harness HTTPS local com TLS verificado e cURL real aprovado: 4096 bytes aceitos, anúncio de 65536 bloqueado antes do corpo e chunked abortado após 4352 bytes. Resultados em remote-transfer-client-results.json e remote-transfer-server-results.json. Servidor temporário encerrado; banco inalterado. Persistem limitações de proxy/deploy/DNS e capacidade. Não houve alteração de código da aplicação nesta etapa; última suíte permanece 107 testes/621 asserções.



## Cadastro solicitado e Resend
Cadastro/endereço/CEP e confirmação assinada implementados e validados localmente; 116 testes PHP/674 asserções e build aprovados. Resend preparado e testado com transporte simulado; envio real pendente de chave/remetente. Guia RESEND_SETUP.md e andamento dos cinco itens em PRODUCT_BACKLOG.md. Não há declaração de entrega externa nem conclusão da auditoria.



## Qualidade da IA
BUG 42 corrigido: base local de comparáveis deixa de incluir sujeito/cópias e imóveis incompatíveis; ITBI restringido por cidade/UF. 121 testes/689 asserções e build aprovados. Não há modelo treinado; zero transações ITBI no banco local. Evidência e plano de avaliação real em AI_EVALUATION.md/ai-data-readiness.json. Auditoria e evolução permanecem em andamento.
