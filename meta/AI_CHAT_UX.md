# UX do chat imobiliário

Data: 19/10/2026. Estado: **implementado**.

## Layout

```
┌ header curto + abas Conversar | Radar
├ thread
│   mensagens
│   destaque
│   cards (origem, Ver detalhes, Ver 3D, carteira, comparar)
└ composer sticky
    textarea auto-grow
    Enter envia, Shift+Enter quebra linha
```

Radar por filtros ficou na segunda aba. Se o radar já tinha busca persistida, a aba Radar abre na volta.

## Regras

- 3 exemplos só com thread vazia; depois “Ver exemplos”.
- Placeholder: `Descreva o imóvel que você procura: cidade, bairro, valor, quartos, vagas ou características.`
- Interpretação continua determinística (`PropertyQueryInterpreter`). Sem LLM.
