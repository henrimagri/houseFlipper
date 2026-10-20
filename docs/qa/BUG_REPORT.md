# Relatório de bugs

## Corrigidos

- Busca de IA retornava 502 quando o provedor externo falhava: agora aplica fallback interno e mostra a procedência.
- Lista de bairros não abria ou perdia resultados: entrada, limites e tratamento do provedor foram corrigidos.
- Tutorial redirecionava novamente ao dashboard: navegação ocorre somente ao avançar/voltar em uma etapa.
- Conclusão do tutorial era sobrescrita ao reabrir manualmente.
- ProductSwitcher aparecia em mais de um local.
- Rota `/mensagens` foi removida e cai na tela 404 própria.
- Despesas causava overflow em 375 px.
- Configurações apontava para rota legada de ITBI.
- Cadastro não coletava endereço apesar de a API já suportá-lo.
- Clientes ocupava permanentemente a tela com o formulário.
- Contratos era placeholder; agora usa banco e CRUD real.
- Leilões não tinham origem rastreável; agora dependem de importação oficial.
- Sessões, cancelamentos e ações destrutivas receberam feedback e confirmação.

## Estados esperados

- IDs dinâmicos inexistentes retornam 404 com tela de erro.
- Checkout permanece indisponível enquanto Mercado Pago/Stripe não têm credenciais; a UI oferece solicitação comercial e não simula pagamento.
- Catálogo de leilões vazio orienta a importação de arquivo oficial e não cria lotes fictícios.
