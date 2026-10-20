# Integração de leilões e vendas públicas

## Fontes

- CAIXA — portal oficial de imóveis e arquivo de listagem.
- VendasGov/Serpro — catálogo e editais públicos.

## Fluxo implementado

1. O usuário baixa o arquivo no portal oficial.
2. A tela aceita CSV, TXT, XLSX ou ODS, até 10 MB.
3. O backend calcula SHA-256, processa no máximo 50.000 linhas e registra o lote de importação.
4. Registros válidos são normalizados e deduplicados por provedor + identificador oficial.
5. URLs externas são mantidas apenas quando pertencem aos domínios oficiais permitidos.
6. O arquivo temporário é apagado mesmo quando o processamento falha.

O catálogo preserva fonte, identificador, modalidade, valores, URL oficial, edital, payload original e data de coleta. Não há bypass de autenticação, CAPTCHA, scraping oculto ou dados fictícios.
