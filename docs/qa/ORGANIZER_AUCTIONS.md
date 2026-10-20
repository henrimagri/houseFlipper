# QA — Organizador pessoal e leilões automáticos

Data: 2026-10-20

## Entregas

- O menu Comercial agora abre um organizador pessoal, separado de clientes e leads.
- Tarefas possuem nome, descrição, coluna, prioridade e prazo, com edição, exclusão e movimentação.
- O foco dos campos em modais permanece estável durante toda a digitação.
- A importação reconhece o preâmbulo presente nos CSV oficiais da CAIXA.
- Vários arquivos CSV, TXT, XLSX e ODS podem ser selecionados e processados em sequência.
- A busca automática baixa a lista pública oficial da CAIXA por UF e aplica cidade, bairro, tipo, valor máximo e palavra-chave.

## Evidências

- `npm test`: 11/11 testes.
- `npm run build`: concluído.
- `php artisan test --compact`: 20 testes e 99 assertions.
- `node scripts/organizer-auctions-qa.mjs`: 3/3 cenários.
- Consulta real de QA à lista pública da CAIXA para AC: 19 imóveis processados, sem linhas rejeitadas.
- Capturas em `docs/qa/artifacts/organizer-auctions/`.
