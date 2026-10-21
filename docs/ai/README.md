# Inteligência artificial do SaaS — documentação para Codex

Este diretório contém **especificações de implementação**, não uma declaração de que o repositório já possui cada funcionalidade. O Codex deve comparar estes documentos com o código e preencher o diagnóstico em `IMPLEMENTATION_PROGRESS.md` antes de alterar a arquitetura.

## Leitura seletiva (economia de contexto)

| Necessidade da tarefa | Leia |
|---|---|
| Entender camadas e produtos | `ARCHITECTURE.md` |
| Escolher prioridades e começar | `IMPLEMENTATION_PLAN.md`, `IMPLEMENTATION_PROGRESS.md` |
| Criar agente de busca, CRM ou investimento | `AGENT_CATALOG.md`, `TOOLS.md` |
| Indexar documentos e consultar conhecimento | `RAG.md`, `INTEGRATIONS.md` |
| Preservar filtros e histórico | `MEMORY.md` |
| “Treinar” ou melhorar a IA sem inventar capacidade | `TRAINING_STRATEGY.md`, `EVALUATION.md` |
| Revisar permissões, prompt injection e dados | `SECURITY.md` |
| Avaliar Pi e Awesome LLM Apps | `REPOSITORY_RESEARCH.md` |
| Consultar o inventário técnico comprovado | `ARCHITECTURE_AUDIT.md` |
| Operar LLM local e worker | `LOCAL_LLM.md`, `ML_INFRASTRUCTURE.md` |
| Consultar RAG vetorial | `VECTOR_RAG.md`, `BENCHMARKS.md` |
| Reproduzir dataset/LoRA | `DATASETS.md`, `FINE_TUNING.md`, `MODEL_REGISTRY.md` |
| Ver estado de visão e 3D | `VISION_MODELS.md`, `PHOTOGRAMMETRY.md`, `3D_GENERATION.md` |

## Como iniciar no Codex

1. Abra o **repositório real** do SaaS (não este pacote sozinho).
2. Copie `AGENTS.md` para a **raiz do repositório** e esta pasta `docs/ai/` para o projeto.
3. Peça: “Leia `AGENTS.md`, `docs/ai/IMPLEMENTATION_PLAN.md` e `docs/ai/IMPLEMENTATION_PROGRESS.md`; audite a IA existente, implemente a Fase 1 e os primeiros testes. Registre o resultado e prossiga pela próxima etapa possível.”
4. Nas sessões seguintes, peça para continuar a partir do arquivo de progresso, sem colar novamente todos os documentos.

## Premissas relatadas (verificar no código)

- Backend Laravel e frontend TypeScript/Vite.
- Três produtos: Corretor, Imobiliária, House Flipper.
- Autenticação, entitlements, pesquisa de imóveis, consultor IA, CRM, mapas, FlipControl e serviços 3D em diferentes níveis de implementação.
- Alguns portais e meios de pagamento ainda não têm integração comercial/credenciais; não tratar como operacionais sem evidência.

## Objetivo de negócio

Uma IA brasileira que encontra e compara imóveis reais, ajuda corretores na captação e divulgação autorizadas, auxilia equipes de imobiliárias no CRM e ajuda investidores a estudar oportunidades/reformas. A resposta precisa ser útil **e rastreável**. Uma consulta sem fonte, permissão ou resultados deve falhar com clareza, não produzir fatos inventados.

## Critério de verdade

- **Reportado:** situação descrita anteriormente pelo usuário; requer verificação.
- **Planejado:** capacidade especificada neste pacote, ainda não comprovada.
- **Implementado:** existe código real e integrado.
- **Testado:** evidência de execução do caso relevante em ambiente apropriado.
