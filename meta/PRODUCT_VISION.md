# Visão de produto

Data: 19/10/2026. Estado: diagnóstico. Nada abaixo está marcado como entregue além do que o código já faz.

## O que o código é hoje

**FlipControl** é um SaaS de *house flipping* para um tenant: descobrir anúncios (radar/scraper), guardar leads, analisar investimento, virar projeto, controlar obra, despesa, venda e ROI. A UI padrão é House Flipper. Três slugs existem em `config/products.php` (`house_flipper`, `corretor`, `imobiliaria`), mas a navegação, o CRM e a página pública de corretor **não existem**.

## O que o brief pede

Um ecossistema PropTech brasileiro:

descoberta → captação autorizada → carteira comercial → marketing → página pública → CRM → mapa → assinatura.

Encontrar um anúncio público **não** autoriza republicar fotos, copiar o texto nem intermediar a venda.

## Proposta de valor (alvo)

Transformar descoberta de imóveis em captação, divulgação e venda organizadas, com três produtos no mesmo núcleo:

| Produto | Comprador | Promessa |
|---|---|---|
| House Flipper | Investidor | Comprar, reformar, revender com números auditáveis |
| Corretor | Autônomo | Carteira + página + marketing, só com autorização |
| Imobiliária | Empresa | Equipe, filiais, atendimento, comissões |

## O que não somos

Portal de classificados. Clone de Kenlo/Vista. Fotogrametria. Integração inventada com ZAP, OLX, leiloeiros ou DataZAP.
