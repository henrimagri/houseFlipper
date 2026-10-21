# Memória e contexto de conversação

## Camadas distintas

1. **Contexto de turno**: mensagem atual e instruções específicas; transitório.
2. **Memória de sessão**: filtros ativos, imóveis comparados, páginas e último resultado; persiste enquanto a conversa precisa.
3. **Preferências persistentes (opt-in/configurável)**: cidades de interesse, faixas, estilo de resposta; consentimento e edição pelo usuário.
4. **Histórico comercial do CRM**: pertence a produto/organização e requer autorização própria; não é automaticamente “memória do modelo”.

## Exemplo de continuidade

Usuário: “Apartamento em Fortaleza até 650 mil, 2 vagas”.
Próxima mensagem: “Agora com varanda”.
Foco: manter Fortaleza, preço, vagas, adicionar varanda. “Agora em Recife” altera a cidade **somente**.

## Contrato sugerido

```json
{
  "conversation_id": "uuid",
  "active_product": "corretor",
  "search_state": {
    "city": "Fortaleza",
    "max_price_brl": 650000,
    "min_parking": 2,
    "balcony": true
  },
  "last_result_ids": [],
  "updated_at": "ISO8601"
}
```

Estado sensível no servidor; confirmar produto a cada turno. Nunca confundir resultados exibidos na sessão com imóveis de outra organização. Histórico de imóveis somente por ID estável que o backend confirma.

## Controles de privacidade

- Limites de retenção e exclusão por solicitação conforme políticas da aplicação.
- Não memorizar CPF, senha, documentos, informações financeiras privadas desnecessárias ou dados de terceiros em preferências.
- Separar usuário, tenant e organização em todas as leituras/escritas.
- Permitir limpar conversa e preferências; invalidar índices/caches quando relevante.
- Não incorporar mensagens privadas em fine-tuning por padrão.

## Testes essenciais

- Continuidade de filtro; substituição parcial; voltar para busca anterior; expiração.
- Usuário A não vê conversa do usuário B.
- Trocar entre Corretor/Flipper não libera a memória comercial da Imobiliária.
- Preferências removidas não reaparecem por cache.

**Atenção:** memória persistida ≠ treinamento de pesos do LLM.
