# Ponte ChatGPT ↔ Codex CLI no Windows

## Estado e limite do canal

A ponte local executa uma tarefa delimitada por vez com `codex exec`. O arquivo de entrada local é `docs/ai/NEXT_TASK.md`, validado por formato, origem, repositório, branch, tamanho, identificador e SHA-256. Toda execução exige uma aprovação humana explícita e usa o sandbox `workspace-write`.

Não existe neste repositório uma API autorizada para ler conversas privadas do ChatGPT. A ponte não usa cookies, tokens de sessão ou automação de navegador. A entrada automática ChatGPT → máquina local só poderá ser ativada quando houver um canal oficial autenticado e com permissão de escrita. O GitHub conectado aceita `git push` na branch autorizada, mas a tentativa anterior de criar issues retornou HTTP 403; issues não são tratadas como canal disponível.

O retorno Codex → ChatGPT ocorre pelos commits publicados em `Correção-visua/proteção`, que o monitor configurado pelo proprietário consulta periodicamente. O script local não publica sozinho: o fluxo do projeto continua exigindo testes, revisão do diff, auditoria do stage, commit e push.

## Uso sem digitar comandos

Na pasta `tools/ai-bridge`:

- `check.cmd` verifica raiz, branch, Git, CLI, autenticação e tarefa.
- `status.cmd` mostra a última execução e o bloqueio de duplicatas.
- `run-approved.cmd` pede confirmação visível e executa a tarefa uma única vez.

Os atalhos podem ser abertos pelo Explorador de Arquivos. Eles funcionam mesmo quando o processo começa em `C:\WINDOWS\system32`.

## Formato da tarefa

Copie `docs/ai/NEXT_TASK.example.md` para o arquivo local `docs/ai/NEXT_TASK.md`, troque o `Task-ID` e descreva uma única tarefa. Os campos abaixo são obrigatórios e sensíveis a maiúsculas nos valores de segurança:

```text
# HOUSEFLIPPER_TASK_V1
Task-ID: identificador-unico
Origem: local-human
Repositório: henrimagri/houseFlipper
Branch: Correção-visua/proteção
```

`RepoPath` relativo, quando informado por integração, é resolvido a partir da pasta do próprio `bridge.ps1`. `TaskPath` relativo é resolvido a partir da raiz validada do repositório. O diretório atual do shell nunca participa dessa resolução.

## Controles implementados

- descoberta da raiz por `.git`, `artisan`, `composer.json` e `frontend/package.json`;
- Git executado com `git -C` e comparação exata da branch Unicode;
- compatibilidade com Windows PowerShell 5.1 e PowerShell 7;
- script UTF-8 com BOM e arquivos de estado/relatório em UTF-8;
- descoberta e validação da versão do `codex.exe` e verificação não sensível de login;
- limite de 32 KiB e esquema obrigatório para tarefas;
- aprovação humana obrigatória, lock exclusivo e bloqueio por `Task-ID` ou SHA-256;
- timeout configurável, encerramento do processo e propagação do exit code;
- relatórios e estado somente em `storage/logs/ai-bridge`, ignorado pelo Git;
- remoção de padrões comuns de tokens e segredos antes de persistir saídas.

## Canal futuro recomendado

Para integração programática contínua, a documentação oficial diferencia três opções: `codex exec` para jobs delimitados, SDK para fluxos programáticos e `app-server` para sessões persistentes, eventos e aprovações. Qualquer receptor futuro deve autenticar a origem, limitar tamanho e formato, rejeitar duplicatas, serializar execuções, permitir cancelamento e manter segredos fora de logs. Ele não deve ser habilitado até existir uma credencial oficial de serviço e permissão explícita para o canal escolhido.

Referências oficiais consultadas:

- [Build iterative repair loops with Codex](https://developers.openai.com/cookbook/examples/codex/build_iterative_repair_loops_with_codex)
- [Codex as a platform](https://developers.openai.com/blog/codex-as-a-platform)
