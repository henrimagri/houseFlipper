# Retenção e minimização

## Eventos operacionais

Política em config/observability.php:

| Categoria | Dias |
| --- | ---: |
| API detalhada | 30 |
| Segurança | 90 |
| Autenticação | 90 |
| Auditoria | 730 mínimo |
| Analytics operacional | 365 |
| E-mail em platform_events | 30 |
| Atribuição opcional consentida | 90 |

platform:prune é dry-run por padrão. --execute remove IDs em lotes de mil; auditoria não aceita prazo inferior a 730 dias. API detalhada só é elegível até o cursor confirmado api_minutes_v1: o prune não deve apagar observações ainda necessárias à agregação. Se a agregação parar, eventos podem ultrapassar a retenção nominal; acompanhar backlog e armazenamento em vez de eliminar dados silenciosamente.

O mesmo comando trata platform_consent_attributions pelo consented_at. Desativar futuras medições no navegador não apaga por si registros existentes. O scheduler declara prune diário às 04:30; sua existência no código não comprova execução operacional. Testes de fixtures não autorizam limpeza de dados locais ou reais.

## Agregados e tabelas especializadas

RequestAggregates guarda histogramas/contagens por minuto, não observações individuais completas nem request IDs. Chegadas tardias atualizam o minuto original. Não existe histórico anterior à coleta. Retenção própria de agregados, heartbeats, health samples, admissões/uso de recursos, revisões, incidentes e inventário ainda exige política e limpeza operacional: não alegar que platform:prune cobre todas as tabelas.

Os 30 dias de categoria email não equivalem a limpeza de platform_email_deliveries/platform_provider_receipts. Ledger financeiro possui requisitos de preservação próprios e não é apagado por prune de telemetria. Política definitiva de retenção precisa ser aprovada pelo negócio e sua assessoria; este documento não é parecer jurídico.

Exportações assíncronas são privadas e o acesso expira após 24 horas. Limpeza física automática dos arquivos expirados ainda não está instalada/verificada. Expiração de download não prova eliminação do arquivo. O inventário de storage é limitado e não apaga possíveis órfãos. Retenção não elimina usuários, fotos, documentos, conversas, datasets, pesos, arquivos 3D ou jobs originais.

## Dados minimizados

Eventos usam templates de rota, IDs e metadata allowlisted, sem corpos/query completa, senha, token, cookie, cartão ou documento. IP é HMAC e máscara; hostname de worker é HMAC, sem nome bruto. Queries lentas usam fingerprints, não SQL/bindings. E-mail usa metadados/identificador HMAC, sem mensagem ou destinatário copiado para a central. Atribuição opcional usa HMAC de sessão e UTM restritas.

CSV síncrono é limitado a 5.000 linhas; o fluxo assíncrono possui limites próprios de 100.000 linhas/100 MiB e autorização por criador. Ambos usam colunas permitidas e proteção contra fórmulas. Não exportam credenciais, e-mails, notas privadas nem payload de job. Auditoria registra operação/filtros permitidos/quantidade, sem copiar o conteúdo do CSV. WORKFLOWS.md descreve cancelamento, retry e expiração.

Coortes usam somente dias completos com eventos API observados e dentro da retenção. Auditoria antiga não serve para inventar uso. Resta verificar políticas específicas e rotinas de arquivamento/limpeza das novas tabelas em implantação isolada.
