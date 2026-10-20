# Auditoria de modais

## Componente comum

`frontend/src/ui/DialogShell.tsx` fornece:

- `role=dialog`, nome acessível e descrição.
- foco inicial, prisão de foco, retorno ao acionador e fechamento com Escape.
- bloqueio de rolagem do body e clique no overlay.
- corpo rolável com ações visíveis; tela cheia no celular e largura limitada no desktop.

## Migrações

- Confirmações destrutivas.
- Detalhes do CRM Kanban.
- Cadastro/edição de imóvel.
- Cadastro/edição de cliente.
- Cadastro/edição de contrato.

Diálogos nativos restantes foram revisados para foco, Escape, limite de viewport e rolagem. Dropdowns usam camadas abaixo do diálogo comum.
