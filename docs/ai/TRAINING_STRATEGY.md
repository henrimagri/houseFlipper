# Estratégia de melhoria e eventual treinamento da IA

**Princípio:** não chamar integração com API, prompt longo, RAG ou memória de “treinamento do modelo”. Eles melhoram o sistema sem alterar os pesos do modelo.

## Etapa 0 — Baseline

Registrar provedor, versão, prompts, tools, dados disponíveis, custos, latência e uma suíte de perguntas representativas. Medir antes de alterar.

## Etapa 1 — Instruções e ferramentas

Prompts curtos por produto; schemas estruturados; backend calcula números e valida autorização. Evitar cadeias de agentes quando uma ferramenta determinística resolve.

## Etapa 2 — RAG e dados confiáveis

Indexar documentação e fontes autorizadas com proveniência. Usar dados em tempo adequado; não armazenar integralmente anúncios de terceiros sem licença.

## Etapa 3 — Memória e preferências

Preservar critérios de busca entre turnos sem vazar dados entre usuários/organizações; consentimento para preferências persistentes.

## Etapa 4 — Feedback supervisionado

Registrar correção factual, relevância dos imóveis, respeito a filtros e indicação de fontes. Não transformar uma avaliação individual em alteração automática global.

## Etapa 5 — Dataset e avaliação

Criar casos com perguntas e expectativas revisadas: busca geográfica, comparações, dados ausentes, segurança, leilões, reformas e cenários. Dividir em desenvolvimento e teste **sem vazamento** entre conjuntos. Manter fixtures de dados sintéticos rotuladas para testes.

## Etapa 6 — Fine-tuning somente se necessário

Adotar apenas com: problema mensurável não resolvido por tool/RAG/prompt; dados autorizados e suficientes; provedor com suporte; orçamento; versão de avaliação; análise de segurança; experimento A/B ou comparação controlada. Registrar dataset, consentimento, licença, versões, parâmetros e métricas antes/depois. Não iniciar GPU/custo alto automaticamente.

## Dados que não usar automaticamente

- Conversas privadas de clientes.
- Leads/CPFs/telefones/documentos.
- Fotos e textos de anúncios de terceiros sem licença.
- Estatísticas sintéticas rotuladas como se fossem vendas reais.
- Dados empresariais de outras organizações.

## Critério de promoção de uma versão

Deve reduzir erros de negócio e violações de permissão, não piorar significativamente latência/custo, e passar suítes de regressão de filtros, fontes e segurança. Métricas-alvo iniciais devem ser negociadas a partir do baseline real, não inventadas.

## Entregas esperadas

1. Script reproduzível de avaliação.
2. Dataset versionado e revisado.
3. Registro de prompts/modelos.
4. Relatório de métricas.
5. Decisão documentada: *não treinar*, *melhorar recuperação* ou *fine-tuning aprovado*.
