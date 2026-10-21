# Ferramentas da IA: contratos e permissões

Os nomes abaixo são **propostas**, não endpoints existentes. Localize implementações reais e adapte.

## Política geral

- O backend passa uma identidade autenticada; `userId`, `tenantId`, `organizationId`, `product` e plano vêm do **servidor**, não são parâmetros livres emitidos pelo LLM.
- Validar os argumentos com schemas e limitar paginação/escopo; logs devem omitir PII e tokens.
- Saídas retornam `{status, data, source, retrieved_at, warnings}`; distinguir erro, indisponibilidade e lista vazia.
- Ferramentas de escrita precisam confirmar a intenção e checar política de autorização no momento da execução; operações críticas exigem confirmação humana.

## Matriz inicial

| Ferramenta conceitual | Escopo | Permissão | Efeitos colaterais |
|---|---|---|---|
| `search_properties(filters)` | fontes habilitadas/tenants elegíveis | ler imóveis | nenhum |
| `get_property_details(id)` | imóvel acessível | ler detalhes | nenhum |
| `compare_properties(ids)` | todos IDs permitidos | ler detalhes | nenhum |
| `search_properties_in_polygon(geojson)` | fontes habilitadas | ler geografia | nenhum |
| `get_market_indicators(region, period)` | dados licenciados | ler indicadores | nenhum |
| `get_authorized_lead(id)` | org/filial permitidas | ler CRM | nenhum |
| `create_follow_up_draft(lead, text)` | Corretor/Imobiliária | escrever rascunho | rascunho |
| `assign_lead(id, assignee)` | Imobiliária | gestor autorizado + confirmação | modifica responsável |
| `calculate_investment(inputs)` | House Flipper | simulador | nenhum; matemática por código |
| `save_investment_scenario(id, inputs)` | House Flipper | proprietário + confirmação | salva versão |
| `analyze_property_images(id)` | imagens licenciadas | ler imagens/AI quota | inicia job quando autorizado |
| `propose_3d_changes(project, changes)` | projeto próprio e editor ativo | editor + confirmação | salva proposta, não publica automaticamente |

## Exemplo de schema de busca

```json
{
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "city": {"type": "string", "maxLength": 120},
    "state": {"type": "string", "pattern": "^[A-Z]{2}$"},
    "neighborhoods": {"type": "array", "maxItems": 15, "items": {"type": "string"}},
    "max_price_brl": {"type": "number", "minimum": 0},
    "min_bedrooms": {"type": "integer", "minimum": 0},
    "min_parking": {"type": "integer", "minimum": 0},
    "limit": {"type": "integer", "minimum": 1, "maximum": 50}
  }
}
```

Cada campo não informado fica ausente; nunca inferir filtro obrigatório a partir de preferência vaga. Os atributos disponíveis devem refletir o schema real dos imóveis.

## Segurança de execução

- Sem `run_shell`, `eval`, SQL livre, fetch arbitrário ou acesso irrestrito ao sistema de arquivos a partir do chat público.
- Não usar credenciais de API no browser. Rotas de tools verificam permissões novamente.
- Para escrita: mostrar prévia e confirmar; usar transação, idempotência quando cabível, registrar ator e resultado.
- Se o provedor não tiver API de consulta autorizada, não criar uma tool que retorna resultados inventados.

## Operação e UX

Limitar profundidade/chamadas por turno, lidar com timeout, cancelar operações quando suportado e mostrar ao usuário quando o resultado é parcial. Em streaming, nunca mostrar “ação concluída” até o backend confirmar.
