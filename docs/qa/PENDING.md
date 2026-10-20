# Pendências externas e limites conhecidos

- Resend exige `RESEND_API_KEY` e remetente validado para entrega real; sem credenciais, o fluxo informa indisponibilidade sem simular envio.
- Mercado Pago e Stripe continuam sem checkout porque não há credenciais/contrato configurados.
- Mapbox só entra em uso quando sua chave for fornecida; OpenFreeMap e OpenStreetMap atendem o mapa e a geolocalização atuais.
- Importação de leilões é manual porque os portais oficiais podem exigir navegação, termos ou CAPTCHA. O sistema não contorna essas barreiras.
- E-mail do perfil permanece somente leitura até existir um fluxo dedicado de troca com reconfirmação segura.
- Otimização adicional pode dividir os chunks grandes de MapLibre/Three.js; o carregamento atual já usa rotas lazy.
