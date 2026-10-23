# Imobiliária — inventário, comparação e sequência

Checkpoint de 23/10/2026, após Central SOL `b73d37a`. Matriz registrada **antes da implementação deste marco**. O escopo é `imobiliaria`; Corretor e House Flipper continuam com seus próprios entitlements. A existência de uma tela ou enum não comprova operação externa.

## Matriz do código real

| Capacidade | Evidência existente | Estado inicial / lacuna | Decisão |
| --- | --- | --- | --- |
| Contatos, clientes, proprietários | `CrmContact`, `CrmContactPolicy`, `SharedCrmContactController`, telas CrmContatos/CrmContatoFicha | Parcial: entidade compartilhada; referências e autorização precisam de testes adicionais | Reutilizar cadastro, sem segundo lead/cliente |
| Carteira e publicação autorizada | `CommercialListing`, `CommercialListingPolicy`, `PublicAgencyController` | Implementação existente; publicação depende de autorização e produto | Preservar fluxo Descoberto → Captação → Autorizado → Carteira → Publicado |
| Funil | `AgencyCrmService`, `CrmDeal`, `CrmDealActivity`, Kanban | Parcial: etapas, histórico e versão otimista; criação/histórico sem transação conjunta | Consolidar serviço existente |
| Atendimento → oportunidade | `CrmTicket`, `CrmTicketMessage`, `AgencyWorkspaceController::convertTicket` | Parcial: repetição cria novos negócios; prioridade omitida acessa chave inexistente | Primeiro marco executável: regressões, conversão atômica/idempotente |
| Organização, equipe e filial | `OrganizationService`, `OrganizationMembership`, `Branch` | Parcial: limites de filial incompletos; bootstrap e defaults de membros exigem revisão | Corrigir limites envolvidos, preservar vínculos existentes |
| Distribuição | `LeadRouletteService`, `LeadAssignment`, `LeadRouletteState` | Parcial: rodízio e histórico; estratégias não equivalem a SLA/capacidade/aceite | Evoluir depois da integridade do CRM; não anunciar todas as estratégias como completas |
| WhatsApp / omnichannel | Canal `whatsapp` e delivery `not_wired` em `AgencyCrmService::addMessage` | Não configurado: gravar mensagem não comprova envio, recebimento ou webhook | Adapter oficial, isolamento, assinatura de webhook e idempotência antes de operação real |
| Site → atendimento | `PublicBrokerController::lead`, `PublicAgencyController::show` | Parcial: formulário do corretor cria contato; site da agência não tem ingestão integrada de tickets | Evoluir fluxo próprio da agência sem herdar permissões de outro produto |
| Marketing e atribuição | `CrmContact.source`, agregações de origem no dashboard | Parcial: não há jornada UTM/click IDs da agência nem ROAS comprovado | Captura consentida e first/last touch antes de importar gastos/conversões |
| Google Ads / Meta Ads | Nenhum adapter operacional comprovado nesta auditoria | Não iniciado / não configurado | OAuth/contas autorizadas, imports e sandbox; nenhuma campanha ou cobrança automática |
| Agenda e tarefas | Histórico de negócio e datas de próxima atividade/retorno | Parcial: não é agenda integrada ou SLA | Reutilizar atividades, acrescentar entidade apenas se o contrato exigir |
| Propostas e comissões | `CrmProposal`, `CommissionService`, regras e registros | Parcial: há persistência; referências, repasses e liquidação exigem validação própria | Não confundir estimativa/comissão registrada com dinheiro recebido |
| Contratos / ERP / locação | `AgencyContractTest`, CRUD de contratos existente | Parcial: CRUD não comprova assinatura jurídica, cobrança, split, repasse ou conciliação | Evoluir por marcos com provedores oficiais e reconciliação |
| IA e automações | Infraestrutura de IA existente do SaaS, comandos e entitlements | Não foi comprovado um SDR omnichannel operacional nesta auditoria | Ferramentas autorizadas e ações estruturadas; nenhum envio autônomo implícito |
| Indicadores | Dashboard da agência e funil existentes | Parcial: captações pendentes fixas em zero e escopos/coortes requerem revisão | Não usar números incompletos como benchmark comercial |

## Referências públicas consultadas

Pesquisa funcional, sem copiar marcas, código, layout ou promessas de desempenho. Disponibilidade anunciada pelos concorrentes não significa que o FlipControl a implementa.

| Referência primária | Aprendizado aplicado ao plano |
| --- | --- |
| [Kenlo CRM](https://www.kenlo.com.br/crm-imobiliario), [Kenlo Leads](https://www.kenlo.com.br/produtos/leads) | Separar carteira, atendimento, distribuição, funil e ERP; manter origem e responsável rastreáveis |
| [Imobzi Central de Atendimento](https://www.imobzi.com/imobiliarias/central-de-atendimento) | Conversa precisa se conectar a tarefa/negócio e ao contexto do cliente |
| [Jetimob Recursos](https://www.jetimob.com/recursos) | Distinguir CRM, gestão de vendas, aluguéis e financeiro; não prometer ERP por existir CRM |
| [Superlógica Split](https://imobiliarias.superlogica.com/hc/pt-br/articles/360035920373-Split-de-Pagamentos-como-funciona-e-regras-de-repasse) | Repasse exige regras e operação financeira comprovadas; previsão não é pagamento |
| [CV CRM — Administrar Lead](https://ajuda.cvcrm.com.br/support/solutions/articles/157000363861-administrar-do-lead-painel-do-corretor) | Histórico, acompanhamento e ações comerciais contextualizadas |
| [RD Station Marketing](https://www.rdstation.com/produtos/marketing/) | Separar aquisição/nutrição da oportunidade comercial; mensuração depende de integração |
| [Google Ads — Conversion management](https://developers.google.com/google-ads/api/docs/conversions/overview?hl=en) | Conversões têm ações e fontes distintas; receita e atribuição exigem eventos reais |
| [WhatsApp Developer Hub](https://whatsappbusiness.com/developers/developer-hub/), [exemplos oficiais Meta](https://github.com/fbsamples/whatsapp-api-examples), [Meta Marketing Insights](https://www.postman.com/meta/facebook-marketing-api/folder/zzd6d5p/insights-api) | Usar canal oficial, webhooks, templates e métricas autorizadas; validar versão/permissões na implementação, sem hardcode de versão antiga |

Nenhuma credencial foi testada nem mensagem externa enviada nesta pesquisa. Documentação técnica detalhada de OAuth, permissões, consentimento e webhooks deve ser verificada novamente antes de implementar cada adapter. Preços de terceiros não foram inferidos.

## Ordem de execução

1. Integridade do CRM existente: defaults, conversão, atomicidade, tenant/organização/filial e regressões.
2. Ingestão da agência e rastreabilidade de origem, deduplicação e consentimento.
3. Omnichannel/WhatsApp oficial: configuração segura, inbox real, webhooks e estados honestos.
4. Distribuição, tarefas, SLA e automações autorizadas.
5. Tráfego pago: contas e métricas somente leitura, atribuição e conversões verificáveis antes de publicação.
6. Portais e publicação autorizada, com diagnósticos e contratos próprios.
7. Propostas/contratos/assinatura e locação.
8. Financeiro/ERP, cobrança, conciliação e repasses comprovados.
9. Operação, manutenção, vistorias/documentos e pós-venda.
10. BI, regressão integrada e QA visual/operacional dos fluxos entregues.

Esta sequência é um plano, não uma declaração de conclusão dos dez marcos. Documentos específicos serão criados junto da implementação correspondente, sem arquivos vazios fingindo entrega. Registro de resultados efetivos em `CRM.md` e `../ai/IMPLEMENTATION_PROGRESS.md`.
