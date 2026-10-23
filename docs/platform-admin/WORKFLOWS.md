# Workflows operacionais da Central SOL

Os endpoints deste documento exigem o grupo administrativo existente: autenticação Sanctum, conta ativa, e-mail verificado, Platform Admin e segundo fator. Administrador de uma imobiliária e cliente assinante não recebem estas permissões.

## Exportações assíncronas

| Método e rota em `/api/platform` | Ação |
| --- | --- |
| `GET exports` | Lista somente exportações do operador autenticado |
| `POST exports` | Cria job; `report=clients/trials/churn/events/jobs` e filtros administrativos usuais |
| `GET exports/{uuid}/download` | Download autenticado, privado, restrito ao criador |
| `POST exports/{uuid}/retry` | Nova tentativa somente de exportação falha/cancelada e ainda válida |
| `POST exports/{uuid}/cancel` | Cancelamento de exportação queued/processing |

Os filtros passam por `PlatformQueries::filters`: datas, janela máxima configurada, tenant, usuário, produto e demais campos permitidos. O job preserva a semântica dos cinco relatórios (trials pela expiração e churn pela atualização), com filtros aplicados no servidor. Paginação HTTP não é usada para percorrer o arquivo. O worker utiliza `chunkById(500)` e um ID máximo inicial; não é um snapshot transacional de dados mutáveis durante toda a execução.

O job implementa `ShouldQueue`, usa a conexão dedicada `platform_ops`, fila `platform-ops`, despacho após commit, até 120 segundos de geração, timeout de worker de 150 segundos, máximo de 100.000 linhas/100 MiB, três tentativas totais e até três exportações queued/processing não expiradas por operador. **Default `sync`, `null`, deferred ou não comprovadamente assíncrono responde 503**; o request não executa um CSV grande escondido. Também exige conexão `platform_ops` com driver `database` e `retry_after > 150` (configuração prevista: 180 segundos). Worker e scheduler precisam estar efetivamente operacionais na implantação. Comando do worker: `php artisan queue:work platform_ops --queue=platform-ops --timeout=150 --tries=1`. Não altera a conexão nem o comportamento das filas de IA/3D.

O arquivo usa disco `local` privado, caminho fixo derivado de UUID validado, nunca caminho recebido do usuário. Um temporário distinto por tentativa impede conflito de workers; promoção final e estado são protegidos pelo lock do registro e pelo número da tentativa. Duplicatas de entrega não criam nova execução; callbacks de falha de geração anterior não alteram a atual. Cancelamento é observado entre lotes e antes da promoção. A remoção de permissões do operador impede publicação e download futuro. Não há URL pública.

Prazo de acesso: 24 horas desde a criação. Expirado retorna 410. Esta etapa bloqueia acesso e geração após expiração; a limpeza física periódica dos arquivos expirados ainda precisa ser instalada e verificada. Não declarar purga automática de arquivos concluída somente porque o acesso expirou.

CSV contém apenas colunas já aprovadas para o relatório, sem e-mails, senhas, tokens, payloads privados ou cartões. Valores perigosos para planilhas (inclusive espaços antes de `=`, `+`, `@` ou `-`) recebem prefixo apóstrofo. Criação, conclusão, download, cancelamento e retry deixam auditoria. Filtros autorizados ficam associados ao job; conteúdo exportado não entra no log.

Erros armazenam classe limitada, sem mensagem, SQL ou caminho. Worker morto/timeout marca `WorkerInterrupted` quando o mecanismo da fila consegue executar o callback; kill abrupto sem callback exige recuperação operacional de jobs stale. Não existe retry genérico de IA/3D: apenas este produtor de CSV é comprovadamente repetível, e suas três tentativas continuam respeitando cancelamento, autorização e prazo.

## Solicitações de privacidade

`GET/POST privacy`, `PATCH privacy/{uuid}`. Tipos: `access`, `correction`, `deletion`, `export`. O tenant é derivado do usuário existente, nunca de um tenant enviado no payload. Notas de suporte têm até 2.000 caracteres e ficam restritas à central; seu conteúdo não é copiado para auditoria.

Fluxo: `open → reviewing/rejected`; `reviewing → awaiting_customer/approved/rejected`; `awaiting_customer → reviewing/rejected`; `approved → completed/reviewing`; `rejected → reviewing`. `completed` é terminal. Repetir o estado permite corrigir atribuição/notas. Responsável precisa ser administrador ativo e verificado. Rejeição/conclusão exigem explicação.

É um workflow humano, não um processo de eliminação. `completed` registra que o operador encerrou o atendimento; não comprova sozinho que dados foram exportados, corrigidos ou apagados. **Nenhum endpoint exclui tenant, usuário ou dados automaticamente.** Cada mudança de estado deixa auditoria sem conteúdo das notas.

## Revisão de sinais

`PUT security-signals/{id}/review`, classificação `confirmed`, `probable`, `false_positive`, `expected`. Somente eventos da categoria `security` são elegíveis. Upsert mantém uma revisão corrente por evento, operador responsável e datas; cada alteração gera auditoria. Não bloqueia IP ou conta, não treina modelo e não muda automaticamente heurísticas. É evidência de revisão humana.

## Registro de administradores

`GET administrators` retorna usuários com acesso via tabela `platform_admins` ou allowlist de configuração, status, MFA confirmado, sessões de token não expiradas e últimos login/ação **observados**. Ausência de evento fica nula; não é inferida atividade histórica. Nenhum secret MFA, recovery code ou valor de token é retornado.

`POST administrators/action`: `action=grant/remove/reset_mfa`, `user_id`, `password`, `confirmed=true`, `reason` permitido. Requer senha válida e MFA recente (10 minutos), conferidos pelo serviço existente. Senha não é persistida pelo controller. Grant exige conta ativa e e-mail confirmado. Remove/reset não operam sobre o próprio operador. Allowlist não pode ser removida por um botão no banco: requer alteração autorizada da configuração da implantação. A operação protege o último administrador ativo e verificado, além de serializar mudanças concorrentes no registro. A autorização do operador é novamente conferida dentro da transação.

Remove elimina o registro administrativo, credencial MFA e tokens do alvo. Reset preserva o papel, mas elimina credencial MFA e tokens: novo login precisa de novo enrolment. Ações são auditadas e revertidas se a auditoria falhar. Não há exclusão da conta, cobrança, impersonação nem recuperação secreta pelo frontend. Aprovação dupla para operações mais graves permanece uma evolução futura, não uma funcionalidade entregue aqui.

## Validação e limites

`PlatformWorkflowTest` exercita rotas HTTP com middleware real administrativo/MFA, autorização negativa, fila sync recusada, filtro e quota, CSV privado/escaping/owner-only/expiração, idempotência, cancelamento/retry e callback antigo, workflow sem delete, classificação de sinais e concessão/remoção/reset de administradores com reautenticação. Usa SQLite em memória e disco fake, sem exportar dados locais reais.

Os resultados finais e a integração das rotas do produto pertencem ao relatório consolidado `VALIDATION.md`. SQLite e disco fake não comprovam locks concorrentes MySQL, worker daemon durável, kill abrupto, armazenamento de produção nem limpeza física. Nenhum desses resultados deve ser declarado por inferência.
