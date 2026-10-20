# Recomendações

`PropertyMatchRanker` soma o Opportunity Score já existente com bônus de compatibilidade:

- critérios obrigatórios atendidos
- bairros preferidos
- características textuais (varanda, piscina, etc.)

A recomendação sempre traz `explanation` e `caveats`. Não afirma “melhor imóvel do mercado”. Estimativa `fallback_heuristic` é declarada. Anúncios de portal continuam dependentes da fonte original.

Sinais futuros (favoritos, descartes) podem entrar no mesmo ranker; nesta entrega o favorito do lead só eleva o score da carteira.
