# Cadastro, início da conta e dashboards — 23/10/2026

Status: implementado e testado, com os limites abaixo.

## Alterações

- Cadastro: ícones explícitos nos três produtos; asteriscos e indicação acessível de obrigatoriedade. Nome operacional, telefone, dados específicos do produto e endereço completo são obrigatórios no frontend e no Laravel; CRECI continua opcional. Para complemento inexistente, o formulário orienta informar “Sem complemento”. Nenhum cadastro antigo é modificado.
- Senhas: comparação durante a etapa de credenciais, mensagem junto à confirmação e validade nativa impedindo avanço. Respostas de validação do servidor retornam à primeira etapa relevante; submissão final também verifica as etapas. Parâmetro de produto inválido não pula a escolha inicial.
- Localização: o cabeçalho permite buscar cidade pelo endpoint de CEP existente ou informar cidade/UF manualmente. Apenas a preferência de trabalho do usuário autenticado é atualizada; endereço, telefone e demais preferências são preservados. Não usa GPS. Preferência salva tem prioridade sobre o endereço no rótulo da sessão.
- Início: boas-vindas personalizadas com animação e respeito a movimento reduzido, estado real de acesso, preços do catálogo, recursos específicos de cada produto e links aos fluxos existentes de contratação. Não ativa testes ou pagamentos automaticamente.
- Dashboards: informações adicionais dos endpoints existentes, sem consultas duplicadas nem números demonstrativos em produção. Corretor: contatos, visitas, favoritos, captações e retornos. Imobiliária: publicação, atendimento, membros, filiais, propostas e comissões projetadas. House Flipper: aquisição, reforma, venda alvo, custos e alertas. Valores ausentes aparecem como travessão; listas limitadas e projeções são identificadas.
- Imobiliária: corrigidos rótulos que chamavam negócios de propostas e prometiam um ranking de fechamentos que a consulta não calculava. A consulta existente retorna negócios por responsável.
- Layout: controle de recolher transferido do rodapé da sidebar ao cabeçalho; assinaturas continuam no menu do perfil e na área própria. Ícones do cabeçalho têm cores e hover coerentes. Scrollbars próprias, sem setas, e navegação lateral com rolagem independente. `overflow-x: clip` no shell evita criar ancestral de scroll que anulava o cabeçalho sticky.

## Verificação executada

- `php artisan test`: 208 testes, 203 aprovados, 5 ignorados por exigirem MySQL, 1.673 assertions, 20.143 ms, saída 0. Executado com o PHP 8.4 local.
- `npm test` em frontend: 68/68 aprovados, 632,597 ms, saída 0. Inclui novos testes de comparação de senhas, roteamento de erros e obrigatoriedade por etapa.
- `npm run build` em frontend: TypeScript e Vite aprovados, 8,72 s, saída 0. Permanecem avisos anteriores de chunks grandes de mapa/Three.js.
- Pint nos arquivos PHP tocados; `git diff --check` e leitura UTF-8 dos arquivos alterados aprovados.
- Testes Laravel novos: campos obrigatórios e ausência de criação em erro; detalhes dos três produtos; CRECI omitido aceito; autenticação e UF; tentativa de alterar outro usuário/tenant ignorada; preservação de endereço e preferências.

## QA visual efetivo

Executado pelo navegador integrado contra servidores 8010/5174 e SQLite descartável separado da base do usuário. Fixture comum, sem administrador da plataforma, com um contato, atendimento e negócio sintéticos. Nenhuma cobrança ou envio de e-mail.

Observados: ícones do cadastro; asteriscos, atributos required e bloqueio nativo de etapa vazia; início com e sem produtos liberados; preços 149/499/249 e destino do link para o catálogo; localização buscada pelo CEP público 01310-100; cidade de trabalho alterada para Curitiba/PR e preservada após recarga; expandir/recolher pelo cabeçalho; dashboards dos três produtos e suas informações adicionais; temas claro/escuro; painel de localização dentro da tela a 320 px; início e cadastro a 390 px; desktop a 1280/1440 px. Cabeçalho medido no topo 0 após rolagem ao final do dashboard. Nenhum erro de console retornado na consulta de QA.

Capturas reais: `evidence/account-refinement/`, com hashes no manifesto. Algumas capturas iniciais precedem o ajuste da ordem dos recursos nos cards; `purchase-options-light.png` registra os recursos específicos finais.

Limites: a comparação de senhas foi verificada nos testes automatizados e no vínculo de validade do componente, sem cadastrar nova senha pelo navegador nem submeter termos. Não foram executadas cobrança, ativação de teste, confirmação Resend, mudança de localização física, QA integral de cada rota/largura, Firefox ou canvas 3D. Nenhuma afirmação de pagamento funcional depende desta entrega visual.

Servidores descartáveis, credenciais e banco de QA são encerrados/removidos ao finalizar; aplicação principal 8000/5173 preservada. Workflow `.github/` preexistente não integra o commit.
