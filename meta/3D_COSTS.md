# Custos

Data: 19/10/2026.

Pipeline atual: CPU PHP (GD) + WebGL no cliente. Sem token de API 3D. Sem GPU server.

Limite: `throttle:visualization-upload` 6 req/min por usuário.

Pendências que gerariam custo: image-to-3D hospedado, Gaussian Splatting, fila Redis, armazenamento de texturas grandes. Não ligar sem teto por produto (House Flipper / Corretor / Imobiliária).
