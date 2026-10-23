# CRM da Imobiliária — marco de integridade

23/10/2026. **Implementado e testado no backend**. Escopo: serviços existentes `AgencyCrmService` e `OrganizationService`, sem novos cadastros, tabelas, migrations, bibliotecas ou provedores.

## Comportamento entregue

- Criar atendimento somente com assunto funciona; prioridade ausente/nula usa `normal`.
- Converter atendimento recarrega a linha sob `lockForUpdate` em transação, retorna o negócio já vinculado nas repetições e preserva prioridade, responsável, contato, imóvel e filial. Criação do negócio e de seu histórico é transacional. Falha ao vincular o atendimento desfaz ambos.
- Vínculo para negócio arquivado, ausente ou inacessível retorna conflito409; não restaura, não expõe registro estrangeiro e não cria substituto silenciosamente. Histórico pré-existente não é apagado.
- Filial informada deve pertencer ao tenant/organização. Agente com filial não pode retirar a filial do negócio nem abrir atendimentos sem filial ou de outra filial; criação omitida herda a filial do vínculo.
- Referências passam pelas políticas existentes, tenant/organização, restrição de filial e estado do responsável. Imóveis com soft delete continuam excluídos da validação. Registros legados sem organização só são vinculáveis quando a política já permite acesso ao ator.
- Consultas e assertions de negócio/atendimento recebem tenant explícito, também fora de um request autenticado.
- Abrir organização não transforma um usuário sem convite em proprietário. Bootstrap é serializado pelo tenant e limitado ao primeiro usuário do tenant, compatível com `AuthService::register`. Membro inativo continua inativo e sem acesso; papéis existentes são preservados. Não houve migração ou reclassificação de membros locais.
- Convite sem papel/status cria `agent`/`active`. Atualização parcial preserva papel, status e filial já existentes.
- Nota interna em atendimento WhatsApp é gravada como `internal`/`recorded`. Mensagem externa continua `not_wired`; **não foi enviada**.

## Evidência reproduzível

PHP8.4, Laravel13, SQLite`:memory:`; `bootstrap/cache/config.php` inexistente antes dos testes. Variáveis de banco aplicadas somente ao processo de teste. Nenhuma migração na base local.

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
php vendor/bin/phpunit --filter 'AgencyCrmSecurityTest|AgencyContractTest' --no-progress
php vendor/bin/phpunit tests/Feature/AgencyInboxTest.php --no-progress
php vendor/bin/pint --test app/Services/Workspace/AgencyCrmService.php app/Services/Workspace/OrganizationService.php tests/Feature/AgencyInboxTest.php
php vendor/bin/phpunit --no-progress
git diff --check
```

Baseline inicial:3 testes/21assertions aprovados. A primeira bateria correta de10 regressões demonstrou9 falhas funcionais e1 aprovação: erro500 por chave `priority`, duplicação, persistência órfã, reconversão de arquivado, filial estrangeira aceita, filial removida, atendimento sem filial acessível e nota com canal externo. Os testes de bootstrap/convite e referência arquivada/filial também falharam antes das correções. Erros de fixture durante a elaboração (cópia não persistida, e-mail duplicado e autorização obrigatória do imóvel) foram corrigidos antes de atribuir falhas ao produto.

Verificação integrada após Pint: **205 testes,200 aprovados,5 skips de MySQL,1.600assertions,20.876ms,exit0**. `AgencyInboxTest` acrescenta19 cenários. Resultado saneado em `evidence/imobiliaria/crm-foundation.json`. Suite frontend/build não reexecutados neste marco exclusivamente PHP; última verificação da Central pertence ao commit anterior.

## Limites e próxima fatia

Não há teste de corrida real de duas conexões MySQL: SQLite verifica repetição, dados antigos e rollback, mas não comprova a semântica concorrente de `SELECT FOR UPDATE` em produção. A transação usa até3 tentativas de deadlock. Conversão existente continua manual; não transforma toda conversa em venda automaticamente. Não existe chave pública de idempotência para criação genérica de tickets.

Nenhum QA visual novo da inbox foi declarado; frontend permanece existente. Erros assíncronos da tela, integração do site da agência, origem/UTM/consentimento rastreável, deduplicação de ingestão, roleta/SLA, agenda e adapters WhatsApp/Ads são próximos marcos. Revisões específicas de referências de propostas/comissões e métricas do dashboard permanecem pendentes; isto não é auditoria geral de segurança do SaaS.

WhatsApp, Google Ads, Meta Ads, portais, assinatura e pagamentos não foram conectados nem comprovados por estes testes. Preparar contratos e testes antes de depender de credenciais; configuração ausente deve continuar explícita. ERP comercial completo **parcial**, conforme matriz em `PRODUCT_SCOPE.md`.
