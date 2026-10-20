# Sidebar, ITBI e análise financeira

- Sidebar fixado ao viewport no desktop, drawer limitado ao viewport no celular e rolagem interna independente.
- Cabeçalho exibe cidade e UF do endereço do cadastro, com fallback explícito quando ausentes.
- Google Maps/Places removido do diagnóstico, configuração e contexto de bairro; OpenStreetMap permanece como fonte geográfica.
- Seletor ITBI aceita a seleção local do arquivo mesmo sem permissão de operador e explica por que o envio está bloqueado.
- Central financeira recebeu cabeçalho executivo, atualização manual, estado de erro recuperável, alternância resumo/edição, navegação fixa e rota de retorno corrigida.
- Conta local `cliente.validado@flipcontrol.local` criada com e-mail verificado, sem produto, assinatura ou acesso administrativo.

## Validação

- Navegador autenticado: 5/5 cenários aprovados, incluindo rolagem do sidebar, localização, seleção ITBI e análise financeira em 1366 px e 390 px.
- Evidências: `artifacts/shell-itbi-finance/`.
