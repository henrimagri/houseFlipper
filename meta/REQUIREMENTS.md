# Requisitos

Data: 19/10/2026. RF = funcional. RNF = não funcional. Status: `tem` | `parcial` | `não`.

## Funcionais — núcleo existente

| ID | Requisito | Status |
|---|---|---|
| RF-HF-01 | Cadastro de imóvel do tenant | tem |
| RF-HF-02 | Projeto, etapas, despesas, venda, ROI | tem |
| RF-HF-03 | Lead/oportunidade por usuário | tem |
| RF-HF-04 | Análise financeira editável | tem |
| RF-HF-05 | Radar de anúncios com origem | tem |
| RF-HF-06 | 3D por registro (GLB / paramétrico / foto) | parcial |
| RF-HF-07 | Isolamento tenant em propriedades | tem |
| RF-HF-08 | Lead isolado por `user_id` | tem |

## Funcionais — marketplace (lacuna)

| ID | Requisito | Status |
|---|---|---|
| RF-CAP-01 | Estados Descoberto → Favorito → Captação → Autorizado → Carteira → Publicado → Negociação → Vendido | não |
| RF-CAP-02 | Autorização de comercialização obrigatória para publicar | não |
| RF-CAP-03 | Preservar dados da fonte ao personalizar apresentação | parcial (lead guarda origem; sem snapshot imutável) |
| RF-CAP-04 | Carteira comercial ≠ lista de leads de investimento | não |
| RF-PUB-01 | Página `/corretor/{slug}` | não |
| RF-PUB-02 | Publicação ligada à assinatura + graça + suspensão sem apagar | não |
| RF-MKT-01 | Estúdio de peças com imagens licenciadas | não |
| RF-CRM-01 | Clientes, proprietários, kanban comercial, propostas | não |
| RF-CRM-02 | Central de atendimento / WhatsApp Cloud | não |
| RF-CRM-03 | Roleta de leads e comissões | não |
| RF-MAP-01 | Oito modos de mapa com dados verificáveis | não |
| RF-PAY-01 | Checkout Mercado Pago/Stripe | não |

## Não funcionais

| ID | Requisito | Status |
|---|---|---|
| RNF-01 | Multi-tenant | parcial (tenant em imóvel; lead por user) |
| RNF-02 | Autorização no backend | tem nas rotas atuais |
| RNF-03 | Sem inventar dado de imóvel na IA | parcial (comandos 3D e chat recusam invenção; textos de marketing ainda não existem) |
| RNF-04 | Sem scraping contra ToS para republicar | política: descoberta ≠ publicação |
| RNF-05 | LGPD / base legal para lead e proprietário | não modelado |
| RNF-06 | Assinatura idempotente | não |
