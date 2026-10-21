# LLM local

- Runtime validado: Ollama 0.40.6, somente em `127.0.0.1:11434`.
- Modelo ativo: `qwen2.5:3b`, digest `357c53fb659c5076de1d65ccb0b397446227b71a42be9d1603d46168015c9e4b`, Apache-2.0.
- Integração: `OllamaClient`, `AiResponseSynthesizer` e o fluxo normal de `AiConversationService`.
- Autoridade: Laravel continua executando filtros, tools, tenants e cálculos; o modelo apenas redige fatos verificados.
- Rollback: `AI_PROVIDER=deterministic`.

Evidência executada: `php artisan ai:local:probe`. A consulta obrigatória foi interpretada corretamente; primeira inferência do Qwen2.5 levou 28.461 ms, com 303 tokens de entrada e 44 de saída. Uma execução real por `AiConversationService` selecionou `house_flipper_analyst`, executou `calculate_investment` e `synthesize_response` e retornou `provider=local`.
