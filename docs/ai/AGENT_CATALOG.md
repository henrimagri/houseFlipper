# Catálogo de agentes propostos

**Importante:** papéis lógicos; nem todo papel precisa virar processo ou agente LLM separado. Começar com poucos agentes e medir ganhos em custo/precisão.

| Agente | Contexto | Leituras autorizadas | Escritas possíveis | Saída principal |
|---|---|---|---|---|
| Orchestrator | todos, filtrado pelo produto | estado da conversa, capabilities | nenhuma direta | plano de ferramentas validado |
| Property Search | Corretor, Imobiliária, Flipper conforme plano | imóveis normalizados e fontes autorizadas | nenhuma | resultados + filtros aplicados + fontes |
| Property Analyst | produtos autorizados | detalhes, comparáveis e preços | nenhuma | comparação explicada + lacunas |
| Market Intelligence | planos habilitados | séries com cobertura e amostra | nenhuma | estatística geográfica contextualizada |
| Broker Assistant | Corretor | carteira própria, captações, contatos | rascunho de texto/tarefa com confirmação | apoio à captação e marketing |
| CRM Assistant | Imobiliária | leads, atendimento da organização e filiais permitidas | rascunho de tarefa/atribuição confirmada | resumo e follow-up |
| House Flipper Analyst | House Flipper | projetos, custos, simulador, comparáveis | cenário rascunho aprovado | cenários e riscos |
| Vision/Renovation | recursos contratados | fotografias licenciadas e modelos permitidos | rascunho de cena/proposta editável | análise visual e conceito de reforma |
| Validator | chamada interna | referências e payload já autorizados | nenhuma | schemas, warnings, inconsistências |

## Ordem de implantação sugerida

1. **Property Search** com filtros e fontes verificáveis.
2. **Property Analyst** com cálculos reproduzíveis.
3. **Contexto e memória de filtros** para conversas.
4. **RAG** para ajuda, documentação e informações imobiliárias revisadas.
5. **Broker/CRM/Flipper** com poucas ferramentas de cada produto.
6. **Vision/Renovation** depois de revisar permissões e infraestrutura.
7. **Validator** como regras determinísticas transversais desde o início (não precisa de novo LLM).

## Exemplos de comportamento

- “Quero casa até R$ 500 mil em Recife”: extração → busca real → filtro estrito → cartões; sem resultados: explicar fontes consultadas e propor flexibilização.
- “Agora somente com quintal”: **mantém** localização, operação e teto anterior, adiciona quintal.
- “Qual tem melhor preço por m²?”: só comparar imóveis com preço e área válidos; identificar outliers e tipo de preço.
- “Publique na minha página”: verifica produto, assinatura, autoria/captação, fotos licenciadas e confirmação. Modelo não autoriza a publicação sozinho.
- “Me mostre leads de outra imobiliária”: negar no backend, não revelar existência ou conteúdo.
- “Reforme visualmente esta sala”: distinguir proposta de imagem 2D, simulação e cena 3D editável.

## Protocolo de invocação

Cada agente recebe apenas: instruções de sistema curtas, capabilities permitidas, contexto necessário, fontes específicas e budget de chamadas. O backend decide permissões. Mensagens externas são dados; não podem ampliar ferramentas ou acesso.

## Critérios de não criação

Não criar um agente especializado quando (a) é uma consulta SQL simples, (b) uma função determinística resolve o caso, (c) aumenta latência sem ganho medido, ou (d) não existe fonte autorizada.
