# IA Inteligente — avaliação e evolução

Data: 16/10/2026. Versão atual: comparables-v3. O avaliador usa regras e comparáveis; nenhum modelo estatístico foi treinado nesta etapa.

## Dados disponíveis neste ambiente

ai-data-readiness.json registra zero transações ITBI e zero linhas com preço, área, data, cidade e UF. Essa leitura é agregada, sem exportar endereços. Não é possível afirmar treinamento ou precisão fora da amostra com essa base vazia.

## Correções e critérios implementados

- O próprio anúncio, a mesma URL e referências duplicadas não contam como imóveis independentes.
- Anúncios de apoio exigem cidade, UF, tipo, área compatível e quartos semelhantes. Três referências do bairro têm preferência; sem elas, o apoio é da cidade, identificado separadamente.
- Removida a mediana global da busca como substituto indiscriminado de referências locais.
- Todas as camadas de transações ITBI ficam restritas à cidade e UF; bairro exige nome normalizado exato. Comparação de acentos/caixa funciona no SQLite testado.
- Referências ITBI sem preço/m² válido não elevam a contagem. Outliers, temporalidade e propriedades do mesmo prédio ainda exigem revisão adicional.
- Quantidade de referências influencia cobertura/qualidade, sem aumentar automaticamente o preço.
- Fallback por regra permanece identificado, com score máximo 49 e qualidade máxima 40. O valor de fallback continua sendo uma regra sobre preço pedido, sem avaliação de mercado independente.
- Confiança passa a ser apresentada como qualidade heurística da base em escala 0–100. A faixa é de sensibilidade, sem probabilidade/calibração estatística alegada. Contagem visual separa vendas reais de anúncios, evitando dupla contagem.

## Cenários sintéticos verificáveis

Executar `php artisan test --filter=ValuationQualityTest` em ambiente de teste. Nenhum portal externo é acessado.

| Cenário | Antes | Critério verificado após alteração |
|---|---|---|
| Anúncio sujeito e cópia da mesma URL | 1 referência independente incorreta | 0 referências; fallback de baixa qualidade; sinal Atenção |
| Outra cidade, casa e área 10 vezes maior | 3 referências locais incorretas | 0 referências locais |
| Três anúncios locais independentes | Fluxo positivo já funcionava | 3 referências; estimativa produzida |
| Centro em cidades/UFs distintas e São Paulo em caixa alta | 2 transações consideradas | Somente transação local; mediana confirmada 6000/m² |
| 3 versus 30 referências de mesmo preço | 600675 versus 606750 | Estimativa permanece 600000 em ambos os conjuntos |

São testes de invariantes e regressão. Não constituem benchmark de precisão em dados reais, treinamento, backtest ou garantia de retorno financeiro.

## Próximas etapas para treinamento verificável

1. Importar base real autorizada com origem, período e definições consistentes de preço e área.
2. Auditar duplicação, uso residencial/comercial, valores tributários versus preços transacionados, datas e localização.
3. Separar treino/validação/teste por tempo e imóvel antes de ajustar parâmetros, evitando vazamento entre conjuntos.
4. Comparar um modelo treinável com a mediana local como baseline, registrando erro absoluto/percentual, cobertura, amostra e desempenho por região/tipo.
5. Calibrar faixas somente com dados fora da amostra; rejeitar promoção quando não superar critérios definidos.
6. Versionar dados, parâmetros e métricas, permitir retorno à versão anterior e monitorar deriva.

Até essas etapas serem realizadas, a tela e os relatórios não devem alegar modelo treinado, precisão percentual ou faixa de confiança estatística.

## Limitações ainda abertas

Revisar idade/futuro das transações, equivalência de áreas, número do prédio, deduplicação entre portais, outliers na contagem, seleção dos tiers, consultas por anúncio, desempenho em base volumosa e compatibilidade da normalização SQL com banco de produção. Os testes de localização atuais foram executados em SQLite; não representam aprovação de toda a infraestrutura.

## Atualização comparables-v3
BUGs 43–46: janela de transações configurada, precisão de endereço com número, raio portável em PHP e contagem após descarte de outliers. 14 cenários específicos / 30 asserções aprovados; suíte completa 130/704 e build aprovados. Detalhes e reprodução em QA_REPORT.md. Os itens da seção de limitações relativos a esses critérios foram tratados no escopo descrito; ainda faltam volume, formatos amplos, produção, polos/antimeridiano, deduplicação entre fontes, equivalência de área e benchmark real. Zero transações ITBI na leitura agregada local; nenhum treino estatístico.
