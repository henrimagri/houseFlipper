# Retenção e minimização

Política em config/observability.php, alterada por configuração de implantação, não SQL arbitrário na interface:

| Categoria | Dias |
|---|---:|
| API detalhada | 30 |
| Segurança | 90 |
| Autenticação | 90 |
| Auditoria | 730 mínimo |
| Analytics | 365 |
| E-mail (metadados de transporte) | 30 |

`php artisan platform:prune` conta elegíveis sem apagar. `php artisan platform:prune --execute` remove IDs em lotes de mil. Auditoria nunca aceita configuração inferior a 730 dias. Revisão legal da política do negócio permanece necessária; não é parecer jurídico. Scheduler diário precisa estar operacional na implantação. O comando não foi usado para apagar logs locais reais nesta entrega.

Retenção de eventos não apaga usuários, fotos, conversas, pesos, arquivos 3D nem tabelas de jobs originais. Não foram criados agregados históricos para fingir cobertura anterior. Coortes só avaliam dias completos cobertos pela API e dentro da retenção; logs de auditoria mais antigos não comprovam atividade de usuários. Retenção/arquivamento de workloads históricos e agregados permanentes continuam pendentes.

Corpo/query/headers, tokens, cookies, senhas, cartões e documentos não são copiados para eventos. Rotas usam templates. IP somente HMAC e máscara; sem geolocalização externa. Exportação usa allowlist, até 5 mil linhas e proteção contra fórmula CSV; não exporta e-mail, notas privadas, payload de job ou segredo. A auditoria registra tipo e quantidade, sem conteúdo do CSV.
