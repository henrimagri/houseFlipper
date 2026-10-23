# Ledger financeiro — Fase 2 SOL

## Escopo e fronteira de confiança

O `BillingLedger` acrescenta uma fundação contábil normalizada à central existente. A tabela anterior `tenant_product_subscriptions` representa acesso, não comprovação de pagamento. `subscription_events` representa eventos do produto. O webhook legado em `Marketplace/BillingService` apenas registrava eventos recebidos/processados; ele não conciliava pagamentos nem implementava o protocolo nativo Stripe/Mercado Pago. Nenhuma dessas fontes é convertida automaticamente em receita pelo ledger.

`BillingLedger::recordVerified(array $record): BillingTransaction` é uma API interna. Somente um adaptador de provedor com assinatura, timestamp e replay verificados, ou uma importação conciliada e autorizada, pode chamá-la. O nome e o campo `evidence_source` não são provas criptográficas por si só. Não exponha esta chamada diretamente a usuários, agentes ou webhooks genéricos. A autorização administrativa e a verificação nativa pertencem aos controladores/adaptadores. Este serviço não faz chamadas externas, cobranças, checkout, alteração de entitlement ou mudança na assinatura.

## Contrato

Obrigatórios:

| Campo | Regra |
| --- | --- |
| `provider` | `stripe`, `mercado_pago`, `reconciled_import` |
| `external_id` | ID do evento imutável; único por provedor; até 160 caracteres de identificador |
| `tenant_id`, `subscription_id` | Inteiros; a assinatura precisa pertencer ao tenant e produto |
| `product` | `corretor`, `imobiliaria`, `house_flipper` |
| `plan` | Slug histórico confirmado pelo adaptador; não inferir do catálogo atual |
| `amount_minor` | Inteiro PHP positivo, até 10¹²; nunca float ou string; unidades mínimas da moeda |
| `currency` | Código de três letras maiúsculas, fornecido pelo provedor; sem conversão cambial |
| `type` | `payment`, `refund` |
| `status` | `pending`, `paid`, `failed`, `refunded`, `cancelled` |
| `effective_at` | Instante econômico UTC do evento confirmado |
| `evidence_source` | `verified_provider` ou `reconciled_import`, conforme origem realmente validada |

Opcionais: `supersedes_id`, `refund_of_id`, `service_period_start`, `service_period_end`, `recurring_months`. O recebimento (`received_at`) é registrado pelo servidor. Campos extras, inclusive payload, dados de cartão e credenciais, são rejeitados.

O `plan` é um snapshot histórico, pois a assinatura pode mudar de plano. Não é automaticamente substituído por `plan_slug`. Adapters devem obter e conferir esse snapshot na associação comercial autorizada.

## Imutabilidade, idempotência e estornos

Eventos são somente acrescentados; update/delete via instância Eloquent falham. Nova situação financeira exige outro ID de evento e `supersedes_id` da folha atual. O hash canônico detecta replay com dados conflitantes. Mesma identificação e conteúdo retornam o registro anterior.

Transições permitidas: `pending → paid/failed/cancelled`, `failed → paid/cancelled`, `paid → refunded`. Identidade, valor, moeda, vínculo e período não mudam em uma transição. Um registro tem no máximo um sucessor, garantido por índice único. Pagamentos são serializados pelo lock da assinatura em bancos que implementam `FOR UPDATE`.

Um estorno referencia a **raiz** do pagamento por `refund_of_id`, na mesma assinatura, tenant, produto, plano, moeda e provedor. Exige pagamento confirmado anterior. A soma de estornos pagos não supera o pagamento. Estornos pendentes/falhos não são dinheiro devolvido. O estado `refunded` de um pagamento exige estornos integrais já registrados. Um refund não pode ter estado `refunded`. Estornos parciais preservam a trilha e reduzem o saldo e a parcela recorrente reconhecida.

Índices únicos e FKs existem no banco. Imutabilidade via model é uma proteção da aplicação, **não** uma barreira para DBA ou SQL direto; produção deve restringir privilégios de escrita e backups. SQLite não demonstra comportamento concorrente de locks MySQL. A homologação MySQL e um teste concorrente real são necessários antes de aprovação financeira em produção.

## Métricas e metodologia

`metrics($filters, ?CarbonImmutable $asOf)` retorna grupos separados por **moeda + produto + plano**, sem somar moedas. Filtros: `tenant_id`, `subscription_id`, `product`, `plan`, `currency`, `from`, `to`. O chamador autoriza acesso; o serviço não recebe o usuário autenticado.

Receitas e refunds são fluxo de caixa evidenciado nos eventos `paid`, dentro de `from/to` e até `asOf`. Recebimento original não migra de mês quando um status posterior registra estorno. `pending_minor` e `failed_minor` refletem folhas atuais nesse período; falha é evidência de cobrança falha, não prova suficiente de uma dívida vencida. Não se atribui inadimplência sem vencimento e obrigação contratual.

MRR é um snapshot no instante `asOf`, independente da janela de fluxo de caixa `from/to`. Exige período de serviço corrente, pagamento confirmado e recorrência explícita de 1, 3 ou 12 meses calendários completos. A parcela líquida de refunds confirmados é dividida por meses, arredondada em inteiro para a unidade mínima mais próxima (meio para cima). ARR = 12 × MRR normalizado. Pagamento anual não vira integralmente MRR. Pagamento sem período confiável contribui para caixa, mas não para MRR. Períodos sobrepostos na mesma assinatura são ambíguos e ficam excluídos do MRR em vez de duplicados. Eventos futuros não apagam o estado histórico ao consultar `asOf` anterior.

Essa métrica é receita recorrente **observada e paga no período**, não um cálculo completo de receita contratada, reconhecimento contábil por competência ou previsão de renovação. Um código de moeda não define sozinho o expoente monetário: adaptadores devem respeitar a unidade mínima oficial do provedor (inclusive moedas sem centavos). Não formatar todos os inteiros dividindo cegamente por 100.

Sem registros, `has_evidence=false`, grupos vazios e MRR/ARR globais `null`. Dentro de um grupo com evidência, caixa zero é um zero observado. MRR continua `null` quando a recorrência não foi demonstrada. Globais `mrr/arr` permanecem `null` intencionalmente para evitar agregação financeira entre moedas; consumir os grupos.

## Timeline e persistência

`timeline($filters, $limit=50)` retorna até 100 lançamentos, por data econômica decrescente. Inclui IDs internos, valor, moeda, tipo, status, produto/plano e datas. Não retorna payload original, hash ou identificador externo. O serviço não inventa trial, checkout, renovação ou abandono: esses eventos precisam de fontes reais adicionais.

Migration aditiva `2026_10_22_000004_create_platform_billing_ledger.php`: cria tabela nova e índices; não copia dados nem toca em assinaturas anteriores. FKs usam `restrictOnDelete` para evitar apagar trilha financeira por cascata de tenant. Rollback remove a tabela e só é aceitável em ambiente isolado antes de haver dados financeiros; não executar para descartar lançamentos reais.

## Verificação

A suíte `PlatformLedgerTest` utiliza o serviço público e banco SQLite em memória: ausência de evidência, valores inteiros, ownership, rejeição de payload, replay/conflicto, normalização anual/trimestral, expiração, transições imutáveis, limites de estorno, período econômico preservado, consultas históricas, períodos ambíguos e isolamento de timeline. Resultados efetivamente executados são registrados no relatório de validação consolidado pelo coordenador. Não houve cobrança real nem preenchimento do ledger local com pagamentos fictícios.

## Integração nativa nesta fase

Stripe invoice.paid assinada e conciliada por external_subscription_id exato produz somente recebimento; não ativa acesso nem presume recorrência. O plano da invoice não é inferido do plano atual da assinatura: lançamentos sem snapshot histórico ficam em provider_invoice_unreconciled. Estornos, renovação/checkout/abandono e períodos recorrentes exigem adaptadores adicionais antes de uma aprovação comercial.

Resend usa o verificador oficial instalado. Callback anterior à associação local retorna 503 e reverte receipt para permitir retry; eventos com timestamp igual podem avançar o estado, sem regressão para accepted. Accepted continua distinto de delivered. Adapters permanecem desabilitados por padrão, sem teste ao vivo nesta sessão.

IDs externos possuem collation binária no MySQL e comparação exata da associação de assinatura. O contrato MySQL adicional foi preparado, mas não executado localmente.
