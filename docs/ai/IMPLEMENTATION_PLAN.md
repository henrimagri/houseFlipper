# Plano incremental de implementação para Codex

**Objetivo:** implementar resultados verificáveis, não entregar apenas arquitetura escrita. Nenhuma fase está marcada como concluída neste pacote.

## Fase 0 — Diagnóstico e baseline

- Verificar versões em `composer.json`/`package.json`, rotas, controllers e integração de IA existentes.
- Levantar comportamento de busca, ferramentas, prompts, custos e falhas.
- Confirmar comandos de testes reais e montar dataset inicial isolado.
- Preencher `IMPLEMENTATION_PROGRESS.md` com achados sustentados pelo código.

**Aceite:** inventário real de serviços e testes baseline executados (ou limitações declaradas).

## Fase 1 — Busca imobiliária confiável

- Extrair filtros de português natural, separar obrigatórios/flexíveis e aplicar consultas verificáveis.
- Corrigir fontes, contagens, deduplicação e status de conectores.
- Retornar cards com IDs existentes, origem e data.

**Aceite:** casos S01, S02, S03, S05, S06, S07 e S18 da avaliação.

## Fase 2 — Tools e autorização

- Criar registro de ferramentas com schemas; garantir autorização de produto/tenant no servidor.
- Limitar chamadas e implementar erros padronizados, observabilidade e testes negativos.
- Avaliar Pi e manter a arquitetura atual se o ganho não compensar.

**Aceite:** S08, S09, S10 e S13; nenhum acesso cruzado nos testes.

## Fase 3 — Memória de filtros e chat

- Estado de filtros e IDs por conversa; múltiplos turnos e histórico autorizado.
- Persistir preferências apenas conforme políticas; interface limpa, scroll/streaming quando disponível.

**Aceite:** buscas sequenciais preservam restrições e isolamento.

## Fase 4 — RAG e conhecimento

- Ingestão de documentação revisada; índices; recuperação autorizada e citações.
- Testar respostas sem fonte, conflitos e atualização de documentos.

**Aceite:** usuário recebe base citada ou ausência clara; fontes privadas permanecem isoladas.

## Fase 5 — Especialização por produto

- Corretor: rascunhos comerciais e captação somente autorizada.
- Imobiliária: leitura de CRM da própria organização, resumo de atendimento e tarefas sujeitas a aprovação.
- House Flipper: cenário financeiro calculado por código, com premissas e custos explícitos.

**Aceite:** ferramentas e dados de cada produto ficam bloqueados aos demais.

## Fase 6 — Visão, reforma e 3D

- Testar classificação de cômodos com imagens autorizadas.
- Integrar propostas de acabamento/decoração; comando estruturado apenas para editor funcional.
- Separar imagem ilustrativa de malha/cena 3D navegável.

**Aceite:** sem afirmar reconstrução fiel onde não há geometria verificada; S12 e S15.

## Fase 7 — Avaliação e eventual treinamento

- Executar conjunto de regressão, avaliar custo/latência/grounding e registrar versões.
- Fine-tuning apenas com autorização e avaliação de custo/ganho; não obrigatório.

**Aceite:** relatório comparativo sustentado por testes; nenhuma alegação de “IA treinada” sem fato.

## Forma de executar

Trabalhar por incrementos pequenos, cada um com mudanças, testes e atualização de progresso. Não iniciar mais de um grande subsistema ao mesmo tempo. Em bloqueios externos, completar o que for independente e registrar dependência exata.
