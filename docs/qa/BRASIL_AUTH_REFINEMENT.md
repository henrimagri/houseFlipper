# Refino nacional e autenticação premium

## Resultado

Status geral: **implementado**.

- As três prévias de produto agora representam fluxos distintos de Corretor, Imobiliária e House Flipper, têm links próprios, estados de hover/foco e aviso explícito de composição ilustrativa.
- A visualização institucional usa a geometria real do Brasil com os 26 estados e o Distrito Federal, 27 capitais, conexões animadas, seleção por estado/capital e legenda sem números comerciais.
- O globo mantém a Terra texturizada, inicia com o Brasil em destaque, contém as 27 capitais e conexões nacionais demonstrativas. Os marcadores têm área interativa mínima de 32 px.
- A seção “Operação conectada” comunica conta única, produtos independentes, permissões por assinatura e alcance nacional demonstrativo.
- Login, cadastro, recuperação, redefinição e confirmação de e-mail compartilham o mesmo shell visual responsivo. Login lembra somente o e-mail quando solicitado; login, cadastro e redefinição permitem mostrar a senha; cadastro e redefinição exibem força da senha.
- O fluxo real de cadastro, consulta de CEP, confirmação por e-mail, limites, isolamento de produtos e redirecionamento pós-login foi preservado.

## Responsividade e acessibilidade

Foram validados 375×812, 430×932, 768×1024, 1366×768 e 1920×1080 em Home, Login e Cadastro. Recuperação, redefinição e confirmação também foram verificadas em 375×812 e 1366×768.

Nenhum cenário apresentou overflow horizontal, erro de console ou conteúdo cortado. Os formulários mantêm labels, autocomplete, estados de carregamento, alertas semânticos, foco visível e controles de senha nomeados.

## Evidências

- Antes: `artifacts/brasil-auth/before-landing-1366x768.png`, além das capturas anteriores de globo, mapa e landing em `artifacts/adendo/*-before*.png`.
- Depois: `artifacts/brasil-auth/home-*.png`, `login-*.png`, `register-*.png`, `esqueci-senha-*.png`, `redefinir-senha-*.png` e `confirmar-email-*.png`.
- Resultado estruturado: `brasil-auth-results.json`.
- Implementado: todos os itens do adendo.
- Parcial: nenhum.
- Bloqueado: nenhum.

## Validação final

- QA específico Brasil/autenticação: 21/21.
- QA do adendo visual e tutorial: 7/7.
- Matriz visual premium: 15/15.
- Regressão ampla de navegador: 232/232.
- Frontend: 5/5 testes e build de produção concluído.
- Backend: 14/14 testes, 69 asserções.
- Isolamento, produtos e permissões: 23/23.
- Auditoria npm: 0 vulnerabilidades.

O build mantém o aviso informativo do Vite para os chunks WebGL/MapLibre acima de 500 kB. Globo e mapas continuam carregados sob demanda, sem afetar o bundle inicial.

## Licença do mapa

A geometria do Brasil vem do pacote `@svg-maps/brazil` 2.0.0, distribuído sob CC BY 4.0. A dependência e esta atribuição permanecem registradas no projeto.
