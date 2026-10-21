# Arquitetura de IA — desenho-alvo

**Status:** proposta sujeita a auditoria do código existente. Evitar criar serviços duplicados.

## Separação entre produtos

| Produto | Propósito e dados permitidos | Exemplos de ferramentas |
|---|---|---|
| Corretor | captação, carteira própria, clientes e página comercial | busca, imóveis autorizados, atividades e rascunhos de divulgação |
| Imobiliária | imóveis da organização, leads, atendimentos, equipe/filiais | resumo de CRM, atividades, match lead×imóvel, relatórios por permissão |
| House Flipper | oportunidades, análise financeira, reforma e modelos 3D | comparáveis, cálculo financeiro determinístico, imagens e projetos próprios |

Produto/tenant devem ser **obtidos da sessão verificada** e não confiados ao parâmetro que a IA emitir. Compartilhamento só mediante autorização explícita.

## Fluxo recomendado

```text
Frontend (chat e ações estruturadas)
  → Backend atual (autenticação, entitlement, tenant, limitação de uso)
  → AI Gateway / Orchestrator (contexto, política de modelo, custo e tracing)
  → Tools registradas (schemas restritos + autorização no servidor)
     ├── DB de imóveis e carteira
     ├── CRM/atendimentos
     ├── motor de mapas
     ├── calculadoras financeiras
     ├── conhecimento RAG e conectores autorizados
     └── análise visual e projeto 3D, quando disponível
  → Validação determinística da resposta / referências
  → Resposta estruturada e streaming, se suportado
```

O AI Gateway pode ser serviço dentro do Laravel ou componente TypeScript interno. A escolha segue o repositório; **não criar microserviços por padrão**. Bibliotecas Pi podem ser integradas quando simplificarem orquestração existente. Uma camada Python/GPU só se justifica para tarefas especializadas de visão/3D.

## Contrato conceitual da requisição

```json
{
  "product": "corretor",
  "conversation_id": "id-opcional-da-conversa",
  "message": "Apartamentos na Aldeota até 650 mil e duas vagas",
  "filters": {"city": "Fortaleza", "max_price_brl": 650000, "min_parking": 2}
}
```

`product` e filtros enviados pelo cliente são **pedidos**, não prova de autorização. O servidor revalida produto, tenant, usuário e acesso. Resposta proposta:

```json
{
  "answer": "Foram encontrados resultados verificados...",
  "results": [{"property_id": "id-real", "source": "base-autorizada", "updated_at": "ISO8601"}],
  "applied_filters": {"max_price_brl": 650000, "min_parking": 2},
  "warnings": [],
  "tool_status": [{"name": "search_properties", "status": "ok"}]
}
```

Não retorne imóveis ou contagens se não foram de fato consultados. Resultados de demonstração somente em testes isolados e rotulados.

## Camadas

1. **Entendimento**: extrair intenção, filtros obrigatórios/flexíveis, produto ativo, referência a mensagens anteriores.
2. **Consulta**: SQL/índices geográficos/serviços licenciados; RAG para documentos e explicações, não substituição desnecessária de filtros SQL.
3. **Orquestração**: escolha de ferramentas limitada ao escopo; timeout, budget, máximo de chamadas e tratamento de falha parcial.
4. **Validação**: autorização, qualidade da fonte, cálculos por código, contagens verificadas e schema final.
5. **Interface**: chat, cards e ações validadas; nada de HTML arbitrário do LLM.
6. **Observabilidade**: trace, duração, tokens, provedor, custo aproximado, erros; minimizar PII.

## Dados e armazenamento

Entidades candidatas (verificar equivalentes existentes): `ai_conversations`, `ai_messages`, `ai_preferences`, `ai_tool_audit`, `ai_document_sources`, `ai_document_chunks`, `ai_evaluations`, `ai_feedback`. Todo dado privado deve ter associação a usuário/tenant/organização, política de acesso e retenção. Indexe quando necessário; não criar tabelas só por este documento.

## Mapas e 3D

- Mapa geográfico (MapLibre/OpenFreeMap, se confirmado): filtros por geometria e posições reais. Não inventar localização.
- Visualizador 3D: modelo GLB/cena com geometria é diferente de imagem conceitual; reconstrução fiel só quando existirem insumos suficientes.
- A IA só envia **ações estruturadas** a um editor que realmente as suporte, sem alegar alterações gráficas que não ocorreram.

## Resiliência

Para APIs indisponíveis, registrar `sem acesso`, `não configurada`, `erro`, `sem resultados`, nunca retornar zero como sinônimo de falha. Retry controlado, cache que respeita licença/frescura, limite por plano e mecanismos contra duplicação de jobs.
