# Sistema visual FlipControl — 23/10/2026

Status: **implementado e testado**, com amostragem visual descrita abaixo. Escopo autorizado: páginas públicas, autenticação, conta, três produtos e Central.

## Implementação

- Tokens neutros, superfícies claras/escuras, verde da marca, bordas, tipografia, botões e foco compartilhados em `index.css` e `styles/flipcontrol-theme.css`. Cores de alerta, erro e sucesso continuam semânticas.
- `SiteSurface` reúne header, navegação responsiva em dialog nativo, rodapé animado e consentimento. Home e páginas institucionais usam o mesmo componente. Resets específicos da landing excluem conteúdo legado por `:where(:not(.fc-public))`, preservando a especificidade original e o texto branco do CTA.
- `AuthShell` aplica a identidade a login, cadastro, recuperação, redefinição e confirmação. Tema resolvido vem do provider existente; a preferência continua persistente. Nenhum contrato de verificação, senha ou entrega de e-mail foi trocado.
- `ProductLayout`, sidebar, `PageHeader`, KPIs e formulários compartilham a base visual. Ícones Lucide nos cabeçalhos conforme o assunto, nos produtos, cards e ações. Sidebar adaptável ao tema e com nome acessível no controle recolhido. Busca recebe `min-width:0`, evitando cortar perfil/ações com sidebar expandida.
- Três dashboards independentes com três cards principais, indicadores secundários agrupados, números maiores e widgets em grade. Todos os indicadores anteriores continuam apresentados, usando as respostas reais existentes. Não foram inventados sparklines, tendências, percentuais de crescimento ou clientes.
- Imobiliária: origem dos leads usa barras proporcionais aos totais reais. Gráfico de funil tem área própria para não ultrapassar o card. House Flipper: movimentação mensal e composição financeira antecedem o mapa. Resumo financeiro adapta a distribuição ao tamanho efetivo do card por container query.
- Central mantém seus módulos, dados, filtros, permissões e MFA. Tema compartilhado e seletor no header/acesso MFA; busca passa a uma segunda linha em telas pequenas.
- Corrigidos durante QA: contador ilegível do kanban no escuro, resumo financeiro cortado, perfil fora do header, CTA da home afetado pela especificidade do reset e ícones de cadastro confundidos com o fundo. Estados sem dados permanecem explícitos.

## Referência Cruip

Referência solicitada: [Mosaic Lite](https://github.com/cruip/tailwind-dashboard-template), demo [mosaic.cruip.com](https://mosaic.cruip.com/), consultadas em 23/10/2026. Inspiração de composição: navegação recolhível, três cards amplos, indicadores secundários, gráficos em módulos, header com filtros e espaçamento. Implementação própria sobre React/Tailwind/Lucide/Recharts já instalados. Não foram importados código, imagens, dados de exemplo, Chart.js ou dependências do template. O README consultado informa GPL e condições de uso; nenhuma cópia de template foi redistribuída.

## Evidência e validação

Capturas reais publicáveis e seus hashes/dimensões: `evidence/site-theme/validation.json`. Painéis dos produtos usam somente dados sintéticos em SQLite separado, identificado como **Workspace QA visual**. Não são estatísticas comerciais da plataforma. O usuário desse ambiente era verificado somente como fixture de teste e possuía três entitlements locais; nenhum pagamento ou e-mail externo foi executado.

Abertura e estrutura de header/heading/footer conferidas nas 12 rotas institucionais: soluções dos três produtos, funcionalidades, comparação, preços, sobre, contato, demonstração, ajuda, termos e privacidade. Inspeção visual representativa de home, funcionalidades, preços, contato e conteúdo legal. Preços anuais observados: Corretor R$119,20, Imobiliária R$399,20 e House Flipper R$199,20 equivalentes mensais; teste continua de 7 dias. Contratação/checkout não foram submetidos.

No ambiente isolado foram abertos os três dashboards, conta, assinaturas, configurações, atendimentos, funil e tarefas; seleção de atendimento, navegação de sidebar e recolhimento foram exercitados. Login pela UI, temas claro/escuro e logout aguardado até retorno ao formulário. Cadastro observado nas etapas 1 e 2, bloqueio nativo de campos obrigatórios vazios; recuperação e link inválido de redefinição abertos sem envio. Cadastro definitivo, nova senha, CEP externo e entrega Resend não foram exercitados nesta alteração visual.

Larguras efetivamente observadas: 320, 390, 546, 768, 1280 e 1440 pixels em telas representativas, não cada combinação de rota/tema/largura. Sem overflow documental nessas verificações; o kanban mantém rolagem horizontal interna intencional. Header expandido em 1440: perfil termina em x1401 dentro de x1425; tablet768: perfil x729 dentro de x753.

Menu público abriu como dialog nativo, bloqueou rolagem e fechou por Escape retornando foco ao botão. Menu administrativo390 fechou por Escape e retornou foco. Esfera da home renderizada e Enter executado; arraste/touch, FPS e mudança real da preferência do sistema não foram novamente certificados. Movimento reduzido continua implementado.

Central inspecionada em claro/escuro e390px com o operador temporário previamente autorizado. Capturas com agregados reais ficaram somente em `storage/app/private/site-theme-qa/`, ignoradas pelo Git. Formulário MFA inspecionado sem digitar senha, gerar segredo, cadastrar ou desativar fator. Ao fim do QA, acesso administrativo do operador temporário30 removido, tokens revogados, conta desativada e exceção local encerrada; histórico preservado. Nenhuma outra conta foi alterada.

Banco/credenciais do QA isolado removidos após encerrar seus servidores8010/5174. Servidores principais8000/5173 preservados. Nenhuma dependência instalada, migration, alteração de API ou esquema no marco.

## Verificação final

- `npm --prefix frontend test`: **65/65**, 649,813ms, exit0. Inclui testes existentes do editor Three.js, materiais/aberturas e componentes administrativos.
- `npm --prefix frontend run build`: TypeScript + Vite, **exit0, 9,47s**. Warnings existentes de chunks grandes de Three.js/MapCanvas mantidos visíveis.
- `php artisan test --compact`: **205 testes, 200 aprovados, 5 skips MySQL, 1.600 assertions, 15.303ms**, exit0. Banco de testes em memória; nenhuma mudança PHP de produto nesta etapa.
- `git diff --check`: sem erro de whitespace. Avisos locais de conversão CRLF/LF não são falha de build.
- Consulta ao console no fim do QA retornou lista vazia de erros. Não equivale a instrumentação de todas as rotas durante toda a sessão.

Limites: estilos compartilhados alcançam os módulos que os consomem; isto não certifica individualmente todos os fluxos do SaaS. Vitrines personalizáveis mantêm cores/configuração do proprietário. Não houve nova reconstrução, alteração de materiais do canvas, QA visual 3D, cobrança, envio externo ou avaliação de desempenho nesta tarefa. ERP/CRM e integrações pendentes continuam nos marcos anteriores. Não houve cópia integral do dashboard nem promessa de fidelidade pixel a pixel.
