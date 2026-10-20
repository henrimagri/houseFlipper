# Checklist de teste — em andamento

Legenda: [x] executado, [ ] pendente. Testes locais com dados fictícios; não atacar integrações externas.

## Autenticação e autorização
- [x] Login válido/inválido; cadastro com tentativa de campos privilegiados.
- [x] Conta e tenant desativados; token existente bloqueado.
- [x] Limitação de login e prazo de token emitido.
- [x] Logout revoga somente token atual.
- [x] Todos os métodos de API protegidos rejeitam anônimo.
- [x] CRUD legado autorizado; leitura/listagem/exclusão de outro tenant bloqueadas.
- [x] Oportunidade alheia e análise alheia bloqueadas nos cenários existentes.
- [x] Cada mutação de oportunidade/análise/custo/resultado/financiamento com usuário alheio: 20 chamadas + snapshots intactos.
- [x] Token expirado e token legado com avanço de relógio; reset de senha não implementado e permanece fora dos fluxos disponíveis.
- [x] Sessão por cookie/CSRF avaliada como não aplicável ao fluxo atual: frontend usa Authorization Bearer, CORS não aceita credenciais e não existe login web baseado em sessão; reabrir se cookies forem adotados.

## Dados e regras
- [x] Campos aninhados extras de endereço rejeitados.
- [x] Despesa com etapa de outro projeto rejeitada.
- [x] Paginação inválida e payload login com tipos incorretos.
- [x] Troca/remoção de financiamento sincroniza resultado.
- [x] Limites monetários, inteiros e datas em todos os requests; datas HTTP também têm orçamento máximo de entrada coberto por regressão estrutural.
- [x] Textos livres persistidos: projetos/etapas/despesas/vendas/oportunidades rejeitam valores acima de 5.000 caracteres sem escrita parcial.
- [x] Capacidade numérica: ordem de etapa e preço máximo de busca rejeitam valores acima do contrato antes de persistência/provedor.
- [x] Paginação: `per_page` e `page` têm tetos comuns; valores extremos retornam 422 antes do OFFSET.
- [x] Seleção/criação de análise concorrente em MariaDB; venda final duplicada bloqueada transacionalmente em testes funcionais.
- [x] Venda final: dois processos simultâneos no MariaDB 11.4.10 produziram 201/422, uma venda final ativa e imóvel vendido; requisições temporalmente sobrepostas.
- [x] ROI/resumo: proposta, negociação e cancelamento não contam como venda; somente fechada/escriturada alimenta resultado.
- [x] Estados cancelados/deletados e recálculos financeiros: projeto, etapa, ROI, dashboard, venda final e exclusão lógica cobertos por regressões.
- [x] Falhas transacionais simuladas: rollback financeiro, projeto, documentos, fotos e upload ITBI sem arquivo órfão.
- [ ] Interrupção abrupta de processo/host: validar recuperação no filesystem e banco usados em produção.

## Importação
- [x] Conta comum proibida; operador pode importar CSV fictício.
- [x] Reenvio CSV não duplica transações; arquivo executável rejeitado.
- [x] Lote vazio/inválido não anuncia sucesso.
- [x] ODS básico e normalização dos testes existentes.
- [x] XLSX texto inline; DTD; XML acima do limite; coluna inválida.
- [x] Planilhas: ODS com linhas repetidas/limites, CSV UTF-8 BOM/UTF-16/Windows-1252/aspas e XLSX namespaced/shared strings cobertos.
- [ ] Planilhas: ampliar corpus com arquivos reais variados de Excel/LibreOffice e divergências históricas autorizadas.
- [x] Importação: aquisição exclusiva do lote, repetição idempotente, parcial recuperável e recuperação explícita de processamento antigo.
- [x] Importação: dois workers sobrepostos no MariaDB 11.4.10 disputaram o mesmo lote; um concluiu 500 linhas e o outro foi recusado sem duplicação.
- [ ] Importação: exercitar arquivos próximos de 256 MiB no banco/proxy/storage de produção.

## Interface
- [x] Login/logout; importação sem permissão desabilitada com explicação.
- [x] Menu móvel abre e navega para Leads.
- [ ] CRUD visual de cada formulário; busca/filtros/paginação em todas as telas.
- [x] Fornecedores UI: criar, recarregar, buscar, filtrar, editar e excluir; confirmação própria nomeada, descrita e com retorno de foco.
- [x] Fornecedores UI: POST 503 mantém modal e todos os campos; nova tentativa cria um único registro, mostra sucesso e fecha o modal em 375 px.
- [x] Confirmações destrutivas: fornecedor, documento e evento usam diálogo próprio acessível; abertura e cancelamento preservam registros.
- [x] Falhas destrutivas: todos os nove handlers fecham o modal no erro para revelar o alerta; fornecedor também validado em Chromium real.
- [x] Teclado e foco dos seis modais principais: nome acessível, foco inicial, Escape e retorno ao disparador.
- [ ] Menus, leitores de tela e contraste formal em toda a aplicação.
- [x] Viewports 375/768/1440: 16 rotas principais, 48 combinações, sem overflow/alerta/erro de console em estados vazios.
- [x] Tabelas extensas e cartões populados em 375/768/1440, com 28 registros fictícios isolados e paginação exercitada.
- [x] Formulários internos de oportunidade, projeto, tarefa, fornecedor, documento e evento em 375/768/1440.
- [x] Dados fixos/placeholders: varredura textual corrigiu métricas futuras e rótulo enganoso de média municipal na central financeira.
- [x] Controles nativos: varredura AST de todo o TSX corrigiu o único botão sem ação e adicionou regressão contra botões inertes.
- [x] Links externos da IA: ações ausentes são ocultadas e somente HTTPS sem credenciais é renderizado nas listas e detalhes.
- [x] Análise de mercado: hooks são incondicionais e contextos inválidos não disparam chamadas externas.
- [ ] Interação visual completa: acionar todos os botões/links com fixtures para localizar controles sem efeito e páginas incompletas.
- [ ] Erros de rede, loading e respostas de validação em todos os formulários.
- [x] Falha de recarga após mutação confirmada: 16 ações em sete módulos distinguem gravação concluída de visão desatualizada. Evidência: `mutation-refresh-feedback-checks.json`.
- [x] Falha de recarga após mutações complexas: 14 ações de Projetos, Central Financeira, ITBI e assistente preservam o resultado confirmado. Evidência: `mutation-refresh-feedback-batch-2-checks.json`.

## Infraestrutura e integrações
- [x] Composer/npm audit; manifesto Composer validado em modo estrito; build TS; análise sintática PHP.
- [x] Dependências PHP diretas comparadas com versões atuais; duas migrações de versão principal foram registradas separadamente de vulnerabilidades.
- [x] Dependências frontend comparadas; patches compatíveis aplicados e instalação limpa sem scripts, audit, testes e build repetidos.
- [x] Exceção simulada de provedor não revela marcador privado.
- [x] Resolver 7 alertas npm: audit atual zero, build e modal renderizado após Tailwind 4; revisão visual de todas as páginas permanece pendente.
- [x] Provedores/scraper: sucesso, HTTP inválido, exceção, não configurado, redirecionamento, limite e resposta neutra simulados.
- [x] Navegador headless: sandbox habilitado por padrão, downloads negados, navegação principal/enriquecimento restritos a HTTPS e portais permitidos; egress de infraestrutura permanece separado.
- [ ] Configuração produção, HTTPS, CSP/headers, arquivos públicos, logs e backups.
- [x] Repetir suíte e segunda revisão completa das alterações: 312/312 PHP (2.792 asserções), 74/74 frontend, build 2.314 módulos, Pint, 191 arquivos PHP alterados/novos, Composer/npm audit, cache de configuração, `git diff --check` e cobertura de 59/59 caminhos distintos da API na matriz.




## Evidências incrementais (não substituem itens amplos pendentes)
- [x] Falha isolada de linha ITBI sinalizada como parcial; reenvio recupera a linha sem duplicar a anterior.
- [x] Logs ITBI: falhas de linha/upload registram IDs, hashes e tipos, sem copiar mensagem privada da exceção ou nome original do arquivo.
- [x] Falha global ITBI: mensagem interna ausente do lote, logger e saída CLI; causa preservada somente no encadeamento interno.
- [x] Capa externa: HTTPS público, sem credenciais, localhost, rede privada ou porta imprópria; regra aplicada no request e serviço.
- [x] Logs do scraper: stderr do Chromium reduzido a código, tamanho e hash; caminhos, URLs e termos privados não são copiados.
- [x] Chrome/Edge local inicia no Windows com sandbox; about:blank apenas.
- [ ] Renderizar e interagir com o alerta de importação parcial em navegador.
- [x] Concorrência real limitada a análise principal, financiamento selecionado e soma de custos (MariaDB isolado).

- [x] ODS preserva 3 linhas com dados repetidas, ignora cauda vazia repetida e rejeita 10001 repetições não vazias; formatos amplos e limite agregado permanecem pendentes.

- [x] Cabeçalhos duplicados normalizados e dados excedentes rejeitados; opcionais ausentes e cauda vazia preservados.
- [x] Orçamento ODS acumulado entre blocos; limite exato aceito em fixture reduzida.
- [ ] Validar capacidade/tempo/memória com volumes representativos autorizados; limites atuais não equivalem a medição.


- [x] CLI recupera lote manual failed e lote completed_with_errors pelo arquivo existente, sem HTTP.
- [x] Falhas HTTP/de linha resultam em exit 1; sync propaga falha; limites/IDs inválidos rejeitados.
- [ ] Recuperação após interrupção abrupta e política de atualização de arquivo remoto.


- [x] Download ITBI: conteúdo/hash válidos, reaproveitamento, recusa de gravação e falha de conexão.
- [x] Upload ITBI: recusa false/exceção de armazenamento retorna 503 sem iniciar lote.
- [x] Download ITBI no aplicativo: allowlist HTTPS/redirecionamentos, limite por header/progresso/corpo, escrita verificada e rollback filesystem-banco simulados; handler/proxy real permanece separado.


- [x] Política de origem ITBI: HTTPS, host exato, IP literal/userinfo/porta imprópria, redirecionamento relativo, destino externo e loop.
- [x] Descoberta usa a mesma política e preserva links relativos na fonte configurada.
- [ ] DNS/pinagem, proxy, egress e limites reais de transferência; testes fake não comprovam esses controles.


- [x] Links relativos após mudança de diretório por redirecionamento usam a base final.
- [x] Limite exato e excesso de bytes, header enganoso, callbacks de headers/progresso e limite separado de descoberta (HTTP fake).
- [ ] Verificar interrupção real de transferência com handler/proxy de deploy e capacidade de memória/tempo.


- [x] Interrupção real HTTPS/cURL em loopback: limite exato aceito, header excessivo bloqueado antes do corpo e resposta chunked abortada.
- [ ] Repetir no handler/proxy de deploy; teste local não prova DNS/egress ou capacidade.


- [x] Tema: seleção claro/escuro persiste após navegação/recarga; controles do cabeçalho/configurações sincronizados.
- [x] Renderização escura: painel, busca/bairros, login, menu móvel e 404; configuração clara renderizada.
- [x] Tema: preferência do sistema e mudanças em tempo real acompanhadas; escolha sincronizada entre abas e validada por regressão.
- [ ] Tema: concluir inspeção de todos os modais/tabelas e auditoria formal de contraste.


- [x] Cadastro completo pela UI com conta fictícia; confirmação assinada e acesso ao painel.
- [x] CEP real com TLS verificado; fallback manual e erros simulados.
- [x] Link alterado/expirado, reenvio limitado, conta inativa e falha de entrega.
- [x] Transporte Resend renderiza mensagem e chama endpoint esperado (cliente simulado).
- [ ] Resend real: configurar chave/remetente verificado e confirmar recebimento em caixa de teste.


- [x] IA: sujeito/mesma URL excluídos, cidade/UF/tipo/área compatíveis, ITBI de cidade alheia excluído, acentos normalizados.
- [x] IA: comparáveis válidos produzem estimativa; mais referências idênticas não inflam preço.
- [ ] IA: benchmark real, treino/validação/teste, temporalidade, deduplicação entre fontes, outliers e desempenho.


- [x] IA: datas ausentes/futuras/antigas excluídas; limite temporal inclusivo preservado.
- [x] IA: número distinto e número ausente não recebem precisão de prédio; endereço correto preserva camada.
- [x] IA: raio SQLite 500/900m, latitude zero e seleção das 80 mais próximas entre 160 fixtures.
- [x] IA: registros descartados por IQR não elevam contagem de anúncios/ITBI.
- [ ] IA: formatos amplos de endereços, produção, polos/antimeridiano, volume e precisão em dados reais.
- [x] IA: servidor PHP/Windows inicia Node/Chrome com ambiente mínimo; busca Perdizes retornou 36 anúncios sem 502.
- [x] IA: diagnóstico seguro preserva falha do headless e contagens coletado/tipo/local/preço; endpoint não expõe trace.
- [x] API protegida: cliente anônimo JSON ou genérico recebe 401, sem redirecionamento para rota inexistente nem erro 500.


- [x] Router local: arquivo public e junction public/storage entregam bytes corretos por backend/proxy.
- [x] PNG real: MIME/hash e HEAD sem corpo; caminho inexistente sem redirect.
- [x] Guard de caminhos: raiz irmã, alvo privado/externo e tipos de script negados em testes unitários.
- [x] Fotos: política definida pelo usuário como públicas por link, sem migração privada.
- [x] Fotos: exclusão de oportunidade remove arquivo público e metadados; falhas restauram ambos; caminhos locais não gerenciados são recusados.
- [ ] Fotos: galeria completa e comportamento do storage/CDN no servidor de produção.


- [x] Despesas: 205 registros paginados 100/100/5, ordem estável, limite 200 rejeitado e isolamento entre tenants (integração PHP).
- [x] Despesas: helper descarta respostas antigas, cancela desmontagem e rejeita falha em página intermediária sem publicar total parcial.
- [x] Despesas: canceladas fora do total/a pagar e soma em centavos (helper Node).
- [x] Fotos: decisão explícita do usuário por manter públicas por link; proposta privada não adotada.
- [ ] Despesas UI: POST inválido/offline mantém modal, rascunho e erro; sucesso fecha e exibe registro único; observações persistidas na UI.
- [ ] Despesas UI: dialog/foco/Escape/viewport, troca de projeto e totais em erro/carregamento com projeto descartável. Rota sem projetos recarregou sem erros de console.
- [x] Despesas: editar e marcar como pago persistem campos e recalculam custo da etapa/projeto (integração PHP).
- [x] Despesas: Filtros executa abrir/fechar; campos sem persistência de fornecedor/vencimento/anexo foram retirados; forma de pagamento/observação reais são exibidas.
- [x] `/despesas`: coleção mock, totais, fornecedor/anexo/vencimento e paginação cenográfica removidos; APIs reais, estado vazio, filtros, paginação e navegação ao lançamento verificados.
- [x] Exportação de despesas: CSV usa seleção filtrada, UTF-8/centavos e neutralização de fórmulas, com regressão automatizada.
- [ ] `/despesas` com registro descartável: abrir detalhe, marcar como paga, baixar/conferir CSV e induzir falha offline no navegador.
- [x] Cabeçalho: Novo Projeto, atividades reais/estado vazio e ajuda; teclado/Escape e viewport estreito verificados.
- [x] Projeto manual: payload, datas, orçamento, etapas padrão, tenant e imóvel alheio testados; estado sem imóvel e encaminhamento para Nova oportunidade verificados na UI.
- [x] Projetos: falha de qualquer resumo oculta totais parciais/zeros enganosos, informa quantidade e permite tentar novamente; helper testa completude e centavos.
- [x] Projetos: salvar manualmente com imóvel existente, recarregar, editar e excluir pela UI; bloqueio de exclusão com histórico protegido validado.
- [ ] Projetos: validar falha de rede nos fluxos visuais de criação, edição e exclusão.

- [x] Fornecedores: CRUD backend com validação, paginação, exclusão lógica, mass assignment e isolamento entre tenants.
- [x] Fornecedores UI: coleção fictícia removida; estado vazio, dialog, validação obrigatória, foco e Escape verificados sem gravar dados.
- [x] Fornecedores UI: criar/recarregar/buscar/filtrar/editar registro descartável e confirmar persistência no tenant.
- [x] Fornecedores UI: DELETE 503 fecha confirmação, exibe alerta/retry e preserva o único cartão; exclusão normal e semântica da confirmação também validadas.

- [x] Conversão análise→projeto: transação integral, rollback em etapa/vínculo, retry idempotente, valores derivados no servidor e centavos preservados.
- [x] Conversão: negativo para anonimato, não confirmado, suspenso, sem tenant, outro usuário mesmo/outro tenant, ID inexistente/inválido e método errado.
- [x] Conversão: exclusão lógica, legado sem adoção automática, nomes longos e limite financeiro.
- [x] Conversão concorrente: MariaDB isolado, dois pedidos sobrepostos, mesmo projeto com 201/200, contagens e somas corretas; serviço de teste encerrado.
- [ ] Conversão UI: cliques/reload após resposta perdida, projeto indisponível, erro 409 e revisão/recuperação de legado.

- [x] Documentos: coleção e KPIs fictícios removidos; estado vazio usa API real.
- [x] Documentos API: upload/lista/detalhe/edição/download/exclusão autenticados e isolados por tenant.
- [x] Documentos: allowlist/tamanho, projeto alheio, mass assignment, nome hostil, falhas neutras e limpeza de órfão testados.
- [x] Documentos: exclusão atômica testada em sucesso, falha de storage, caminho não gerenciado e rollback de banco.
- [x] Documentos UI: dialog nativo, foco inicial, Escape, formatos/limite, tema escuro e console sem erros verificados.
- [ ] Documentos UI com arquivo descartável: upload/reload/filtros/edição/download/hash/exclusão e falha offline.
- [ ] Documentos em produção: antivírus/quarentena, criptografia em repouso, backup/retenção, storage distribuído e limites do proxy.

- [x] Relatórios: KPIs, gráficos, composição e projetos fictícios removidos.
- [x] Relatórios: resumo real tenant-aware, loading/erro/vazio/atualização e janela temporal identificada.
- [x] Relatórios: CSV com valores reais, centavos, UTF-8 e proteção contra fórmulas testado.
- [x] Relatórios UI: conta vazia, tema escuro, datas atuais e console limpo verificados.
- [ ] Relatórios UI com fixture: reconciliar venda/despesa/projeto, baixar/abrir CSV e induzir falha de API.
- [x] Relatórios avançados: filtros por período/projeto, trilha contábil paginada, validação de tenant e exportação do conjunto filtrado; painel acumulado permanece em `/api/dashboard`.

- [x] Calendário: grade maio/2024 e 26 eventos fictícios removidos.
- [x] Calendário API: CRUD, intervalo, paginação, projeto e isolamento tenant testados.
- [x] Calendário UI: mês atual, Hoje, navegação, categorias, diálogo, foco e Escape verificados.
- [x] Calendário: evento atravessando período, range inválido, mass assignment e usuário sem tenant testados.
- [x] Calendário UI com fixture: criar/recarregar/navegar/editar/excluir, evento multidiário e falha offline. Evidência: `calendar-ui-crud-checks.json`.
- [ ] Calendário avançado: recorrência, lembretes, timezone configurável, visão semanal e conectores Google/Outlook.

- [x] Mensagens: tela demonstrativa, rota, menu e badge fixo removidos por decisão explícita do usuário.
- [x] Mensagens: implementação backend não aplicada descartada, sem tabela ou endpoint residual.
- [x] Mensagens: URL antiga retorna 404 da SPA e menu não contém o módulo; console limpo.

- [x] Obras: seis projetos, custos, prazos, etapas e alertas fictícios removidos; conta vazia apresenta zero e estado vazio real.
- [x] Obras: paginação completa de projetos, resumos reais, busca/filtros, status de atraso e ocultação de totais parciais cobertos por build/testes.
- [x] Obras: botão de criação abre `/projetos?novo=1`; fluxo vazio e console sem erros validados no navegador.
- [x] Etapas: backend rejeita status/progresso inválidos e ID de outro tenant; UI envia somente status e progresso permitidos.
- [ ] Obras UI com fixture: conferir custos/datas, pesquisar, alterar etapa, recarregar, testar todos os badges e falha de resumo.
- [x] Obras: duas atualizações concorrentes da mesma etapa executadas em processos separados/MariaDB; ambas são serializadas pelo lock do projeto, e o último commit vence campos conflitantes sem desalinhar o orçamento total. Evidência: `task-stage-concurrency-mariadb.json`.

- [x] Imóveis: dados/cenários/comparáveis fictícios removidos; conta vazia mostra zeros e estado vazio.
- [x] Imóveis API: CRUD/endereço, paginação, filtros, limites, mass assignment, tenant ausente e IDOR entre contas testados.
- [x] Imóveis UI: formulário completo, foco inicial, validação obrigatória, Escape, viewport estreito e console limpo.
- [x] Imóveis: helper preserva zero/centavos, normaliza CEP/UF e limpa opcionais explicitamente.
- [ ] Imóveis UI com fixture: criar/recarregar/buscar/filtrar/editar/limpar/excluir e induzir falha offline.
- [x] Imóveis: exclusão com projeto, despesa ou venda ativa retorna 409; documentos pertencem ao projeto, que também fica protegido enquanto houver arquivo ativo.
- [x] Projetos: exclusão com despesa ou documento ativo retorna 409; tarefas, etapas e eventos só são arquivados quando o histórico protegido não bloqueia a operação.
- [x] Concorrência: criação de projeto/despesa/documento/tarefa/evento disputada com exclusão do pai em processos separados/MariaDB, nos dois ordenamentos; nenhum órfão ativo ou arquivo residual. Evidência: `parent-deletion-concurrency-mariadb.json`.
- [x] Concorrência: atualização de projeto disputada com exclusão dos imóveis de origem/destino e do próprio projeto em processos separados/MariaDB, nos dois ordenamentos; referências finais permaneceram válidas. Evidência: `parent-deletion-concurrency-mariadb.json`.

- [x] Financeiro: PageStub removido; indicadores, composição, seis meses, projetos e atividades usam `/api/dashboard`.
- [x] Financeiro UI: conta vazia, datas atuais, pluralização, viewport estreito e console limpo validados.
- [x] Financeiro: atalhos Despesas/Projetos/Relatórios navegados; Criar projeto aponta ao fluxo real.
- [x] Financeiro: helper cobre resultado negativo, valores não finitos, composição negativa e divisão por zero.
- [ ] Financeiro UI com fixture: reconciliar aquisição/despesa cancelada/venda, atualizar e simular falha/recovery.
- [ ] Financeiro: testar navegação/tabela com teclado e leitor de tela em viewport móvel e desktop.

- [x] Tarefas: `PageStub` removido; lista, indicadores, busca, filtros, criação, edição, conclusão e exclusão usam API real.
- [x] Tarefas API: CRUD, paginação 1–100, filtros, limites, mass assignment, tenant ausente e IDOR entre contas testados.
- [x] Tarefas: projeto, etapa e responsável precisam ser do tenant; etapa precisa pertencer ao projeto; conclusão/reabertura normalizam a data.
- [x] Projeto: tarefas usam resource explícito, não expõem tenant/flag e excluídas não reaparecem na relação.
- [x] Tarefas UI: estado vazio, indicadores, dialog, foco, bloqueio sem projeto, Escape, atalho e console limpo verificados.
- [x] Tarefas UI: criar/recarregar/buscar/filtrar/editar/concluir/reabrir registro descartável e confirmar persistência.
- [x] Tarefas UI: excluir registro descartável, induzir falha offline e validar entrada de data com automação compatível com `input[type=date]`. Evidência: `task-ui-crud-checks.json`.
- [x] Tarefas: atualizações concorrentes executadas em processos separados/MariaDB; PATCHes disjuntos preservam ambos os efeitos, campos conflitantes usam o último commit e campos derivados usam a linha bloqueada mais recente. Evidência: `task-stage-concurrency-mariadb.json`.

- [x] Configurações: estados fixos de integrações removidos; snapshot usa configuração/runtime/banco reais.
- [x] Configurações API: anonimato, conta não confirmada/inativa, tenant ausente e resposta autenticada testados.
- [x] Configurações API: chaves, tokens, endpoints e caminhos não são devolvidos; endpoint HTTP é marcado indisponível.
- [x] Configurações UI: loading, três totais, 14 fontes, atualização e console limpo verificados.
- [x] Configurações UI: falhas simuladas de fontes/sessões exibem alertas separados; ambas recuperam por nova tentativa em tema escuro e 375 px, sem overflow ou exceção JavaScript.
- [ ] Configurações UI: validar navegação completa com leitor de tela real.
- [ ] Integrações: implementar e testar DataZAP/FipeZAP, leilões, BCB/IBGE antes de alterar seus estados.
- [ ] Monitoramento: definir health checks assíncronos por provedor sem causar chamadas externas ao abrir a página.

- [x] Recuperação: link no login, solicitação genérica e telas de redefinição/erro implementados.
- [x] Recuperação API: e-mail existente/inexistente/inativo recebe resposta pública idêntica; falha de entrega é neutra.
- [x] Recuperação: token hashado, expirável, uso único e transportado no fragmento da URL frontend.
- [x] Redefinição: senha/bytes/confirmação, empresa suspensa, e-mail/token incorretos e reutilização testados.
- [x] Redefinição: todas as sessões Sanctum anteriores são revogadas; senha antiga falha e nova autentica.
- [x] Recuperação UI: navegação, foco, resposta anti-enumeração, divergência de senha, link inválido e console limpo verificados.
- [ ] Recuperação com Resend real: configurar remetente/chave de teste, receber a mensagem, abrir o link e concluir a troca visual ponta a ponta.
- [ ] Recuperação: revisar deliverability, bounce/complaint, alertas de troca e telemetria de abuso em produção.

- [x] Logout bearer: remove apenas o token atual; segundo dispositivo permanece autenticado.
- [x] Logout sem token pessoal: responde sem exceção/500.
- [x] Logout por sessão: guard web encerrado, sessão invalidada, CSRF regenerado e chamada seguinte recebe 401.
- [x] Sessões: interface lista tokens/dispositivos, identifica a sessão atual, revoga um acesso e oferece “Sair de todos”; isolamento e revogação testados.
- [ ] Sessões em produção: validar cookies Secure/SameSite/domínio e encerramento real atrás do proxy HTTPS.

- [x] Frontend dev: documento recebe CSP, `DENY`, `nosniff`, `no-referrer`, COOP e Permissions Policy.
- [x] Frontend preview: CSP de produção bloqueia frame/objeto e não permite script inline nem `unsafe-eval`.
- [x] Frontend: `/configuracoes` recarregada autenticada sob CSP, com conteúdo esperado e console limpo.
- [x] Build: `_headers` defensivo copiado ao `dist`; 34/34 testes e build aprovados.
- [ ] Deploy público: confirmar que proxy/CDN aplica ou preserva todos os headers e habilitar HSTS somente após HTTPS validado.
- [ ] CSP: revisar novas integrações/origens sempre que scripts, imagens, fontes ou chamadas externas forem adicionados.

- [x] API sem `Accept`: validação retorna 422 JSON em vez de 302/SPA.
- [x] API sem rota: retorna 404 JSON com `no-store`, `nosniff`, `no-referrer` e `DENY`.
- [x] API 5xx com debug ativo: mensagem genérica, sem classe, caminho, linha, trace ou marcador privado.
- [x] API 422: mantém `message` e mapa público de erros após a neutralização.
- [x] Servidor local: `X-Powered-By` ausente em estático, API e health.
- [x] Servidor local: `.user.ini`, `.htaccess`, `index.php` e dotfile codificado retornam 404 sem conteúdo.
- [x] Servidor local: `robots.txt` e formato permitido em `/storage` continuam 200; fixture removida.
- [x] Apache/configuração PHP: dotfiles negados e `expose_php` desativado nos arquivos do projeto.
- [ ] Infraestrutura de produção: confirmar bloqueio de dotfiles/backups e remoção de headers no proxy/Nginx/CDN; confirmar `APP_DEBUG=false` no ambiente implantado.

- [x] ITBI: endpoint sem consumidor que expunha limites PHP removido; conta autenticada recebe 404.
- [x] ITBI: inventário real regenerado e não contém `/api/itbi-imports/upload-limits`.
- [x] ITBI: resposta 404 não inclui `memory_limit` nem `upload_max_filesize`.
- [ ] Deploy: alinhar limite de corpo do proxy com o contrato do upload e monitorar rejeições 413 sem expor memória/configuração interna.
- [x] Propriedades: exclusão com projeto, venda ou despesa ativa retorna 409; dependências excluídas liberam a operação.
- [x] Projetos: exclusão encerra etapas, tarefas e eventos na mesma transação; despesas/documentos históricos são preservados.
- [x] Filtros de coleção: projetos, vendas, despesas, categorias e oportunidades rejeitam enums/booleanos inválidos e faixa invertida.
- [x] Storage privado: rotas automáticas `storage.local` de leitura e upload foram removidas; inventário contém zero rota `storage/*` do framework.
- [x] Storage privado: GET/PUT reais retornam 404 e não criam arquivo; regressão impede reativação acidental.
- [x] Storage público: fixture permitida continuou acessível com 200 após a mudança e foi removida.
- [x] IA/entorno: falha total de mapas retorna indisponível e zero pontos, sem nomes ou distâncias simulados.
- [x] IA/entorno: resposta parcial mantém categorias não confirmadas vazias e não mistura fallback sintético.
- [x] IA/entorno: busca de código confirma remoção das listas locais e do perfil urbano fabricado.
- [x] IA/URLs externas: `source_url` e miniatura aceitam apenas HTTP/HTTPS na resposta final.
- [x] IA/URLs externas: `javascript:` e imagem `data:` controlados são anulados; anúncio HTTPS legítimo permanece.
- [x] Scraper SSRF: localhost, IPv4/IPv6 privados, link-local, metadados e esquemas não HTTP são bloqueados.
- [x] Scraper SSRF: hostname que resolve para endereço privado ou conjunto misto é bloqueado; somente conjunto público passa.
- [x] Scraper SSRF: política aplicada às páginas principais, redirecionamentos e enriquecimento opcional; scripts têm sintaxe válida.
- [ ] Infraestrutura: bloquear egress para redes internas/metadados fora dos destinos necessários e monitorar tentativas negadas.
- [x] Fallback PHP SSRF: redirecionamentos automáticos desativados e cada salto limitado a HTTPS/host de portal/DNS público.
- [x] Fallback PHP SSRF: redirecionamento para IP interno é bloqueado antes da segunda requisição; salto permitido continua 200.
- [x] Fallback PHP: HTML externo limitado a 5 MiB por header, progresso e tamanho final; excesso não chega ao parser.
- [ ] Infraestrutura: aplicar limites de memória/CPU/processos ao PHP e Chromium e alertar por timeouts/transferências negadas.

- [x] Central Financeira: remover benchmarks de Poupança/CDI/Tesouro/FII sem fonte e período comparável.
- [x] Central Financeira: remover metas de preço/reforma/score e projeção de ROI sem recálculo comprovável.
- [x] Cenários: usar valor informado como base e exibir variações exatas de venda, custos e prazo.
- [x] Pontuação: identificar método baseado em regras, exibir completude e negar interpretação como percentil/confiança estatística.
- [x] Wizard: remover esperas artificiais após a API já ter concluído o cálculo.
- [ ] Produto financeiro: validar pesos/limiares do índice com especialistas e dataset histórico antes de alegar acurácia ou recomendação personalizada.

- [x] Financiamento: remover bancos/taxas estáticos atribuídos indevidamente ao BCB.
- [x] Financiamento: taxa e instituição são entradas manuais validadas; anual equivalente é calculada.
- [x] Financiamento: interface informa ausência de ofertas em tempo real e necessidade de confirmar CET/TR/seguros.
- [ ] Financiamento futuro: somente reintroduzir comparação automática com fonte oficial/licenciada, data, produto, CET e atualização testados.

- [x] ROI: prévia e resultado persistido usam equivalência composta para mensal e anualizado.
- [x] ROI: 100% em 12 meses resulta em 5,9463% a.m. e 100% a.a.; 21% em seis meses resulta em 3,2280% a.m. e 46,41% a.a.
- [x] ROI: perda total e entrada inválida não geram `NaN` ou infinito.

- [x] Limites de esquema: `area_m2` em propriedades e oportunidades não excede `DECIMAL(10,2)`.
- [x] Análise de mercado: área, preço, cômodos e vagas têm teto; objeto selecionado rejeita chaves desconhecidas.
- [x] Datas: projeto, etapas, eventos, filtros e vendas validam as relações cronológicas aplicáveis; atualização parcial de venda considera datas persistidas.
- [x] Vendas finais: `fechada/escriturada` exigem fechamento e `escriturada` exige escritura; criação e transição incompletas retornam 422.
- [x] Projetos: atualização rejeita campos aninhados não aplicáveis em vez de responder sucesso parcial.
- [x] Projetos: detalhe, resumo e recálculo ignoram etapas com exclusão lógica.
- [x] Projetos: conclusão persiste término real coerente; reabertura limpa o marco.
- [x] Tarefas: edição após conclusão preserva a data histórica; primeira conclusão cria e reabertura limpa o marco.
- [x] Despesas: pagamento cria/preserva data e estados não pagos removem o marco contábil.
- [x] Custos financeiros detalhados: estado pago cria/preserva `paid_at`; pendente/atrasado limpa.
- [x] Projetos: atualização parcial cruza início/término planejados com valores persistidos nos dois sentidos.
- [x] Projetos: PATCH aceita payload parcial e preserva campos omitidos; PUT permanece completo.
- [x] Vendas: PATCH aceita payload parcial sem reenvio financeiro; PUT permanece completo e invariantes usam estado persistido.
- [x] Imóveis: PATCH aceita payload parcial e preserva campos/endereço omitidos; PUT permanece completo.
- [x] Despesas: PATCH é parcial e relações omitidas são resolvidas pelo registro persistido antes das validações cruzadas.
- [x] Tarefas: PATCH é parcial, preserva conclusão e valida etapa enviada contra o projeto persistido; PUT permanece completo.
- [x] Imóveis: status `vendida` corresponde obrigatoriamente à venda final ativa sob lock.
- [x] Etapas: status e progresso mantêm a invariante pendente=0%, concluída=100% e estados intermediários abaixo de 100% em todos os caminhos de escrita.
- [x] Eventos: PATCH é parcial e compara início/fim enviados isoladamente com a outra extremidade persistida; PUT permanece completo.
- [x] Documentos: PATCH é parcial, preserva metadados/arquivo/projeto omitidos e PUT mantém categoria obrigatória.
- [x] Fornecedores: PATCH é parcial e preserva dados omitidos; POST/PUT mantêm nome e categoria obrigatórios.
- [x] Oportunidades: PATCH é parcial, preserva cadastro/galeria omitidos e PUT mantém título, tipo e fonte obrigatórios.
- [x] Contexto de bairro: objeto selecionado rejeita chaves desconhecidas e área acima de `DECIMAL(10,2)` antes de egress.
- [x] Frontend: páginas usam chunks sob demanda; bundle de entrada e maior chunk permanecem abaixo de 500 kB no build atual.
- [x] IA: filtros sem overflow horizontal em 375/768/1265/1440 px; tema escuro, lista de bairros e menu móvel validados no navegador real.
- [x] Telas solicitadas: cadastro em 375/768/1440, 404 de `/mensagens` e recuperação móvel sem overflow ou erros de console.
















- [x] Ações remotas de oportunidade: detalhe, edição, favorito e análise financeira tratam falhas, restauram pending e exibem alerta dentro do modal ativo.


- [x] Ações financeiras remotas: recarga de referência e seleção de simulação tratam erro, restauram pending e bloqueiam cliques concorrentes.

- [x] Wizard e projetos: recálculo financeiro e orçamento de etapa tratam falhas; a etapa bloqueia concorrência e desfaz visualmente a alteração recusada.

- [x] Central Financeira: criação de simulação, gasto e resultado bloqueia duplo envio, trata falha e sempre restaura o estado pendente.

- [x] Central Financeira: sucesso e avanço de etapa só ocorrem quando a recarga pós-gravação confirma os dados atuais.

- [x] Regressão pós-correções 147–149: suíte PHP completa 273/273 (1.863 asserções), frontend 61/61, build e npm audit sem vulnerabilidades aprovados.

- [x] IA 502/retry: busca bloqueia repetição, aborta solicitação anterior, ignora resposta tardia e recupera em Chromium móvel sem overflow.

- [x] IA egress no navegador: miniaturas externas exigem HTTPS público e omitem credenciais, localhost, IP privado, `.local` e porta não padrão.

- [x] IA navegação externa: links de anúncios são filtrados no backend e frontend para HTTPS público, sem credenciais ou portas alternativas.

- [x] URLs externas: IP decimal/hexadecimal/octal/abreviado e IPv6 privado não contornam a política de destino público.

- [x] Oportunidades: URL de origem é validada na gravação, filtrada em legado e capas do cache local são revalidadas.

- [x] IA: abertura da análise tolera falha isolada de `sessionStorage`/`localStorage` e apresenta erro controlado quando ambos estão indisponíveis.

- [x] Wizard Financeiro: cache de notas inválido ou indisponível usa o fallback da API sem derrubar a tela.

- [x] Autenticação frontend: leitura, gravação e remoção do token não lançam quando `localStorage` está bloqueado; fallback em memória e logout foram validados.

- [x] Central Financeira: preferência do guia de score não derruba o render e continua dispensável quando o armazenamento está bloqueado.

- [x] Análise da IA: cache tenta cada armazenamento isoladamente, exige ID correspondente e rejeita estruturas ausentes ou excessivas antes do render.

- [x] Análise da IA: miniatura recuperada do cache é revalidada como HTTPS pública imediatamente antes do `<img>`.

- [x] Fotos locais de oportunidades: somente caminhos segmentados sob `opportunities/` são serializados ou manipulados como arquivos gerenciados.

- [x] Análises financeiras: capa usa as políticas centrais externa/local e omite loopback, traversal e namespaces alheios.

- [x] Análises financeiras: resposta usa allowlists e omite proprietário, exclusão lógica e chaves estrangeiras internas das relações.

- [x] Projetos: etapas embutidas e respostas de criação/edição omitem tenant, vínculo interno e exclusão lógica.

- [x] CORS: origem do frontend implantado entra na allowlist; origem alheia não recebe autorização e configurações inseguras são descartadas.
