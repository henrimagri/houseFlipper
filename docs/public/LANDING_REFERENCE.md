# Página inicial — referência stipple / lagoon

23/10/2026. Implementação React/TypeScript da home pública `/`, baseada no HTML fornecido pelo proprietário. Textos adaptados para FlipControl em português conforme resposta expressa. A continuação da Imobiliária foi interrompida por este novo pedido visual; seu marco anterior permanece publicado.

## Implementado

Layout, cores, tipografia, espaçamento e estilos de botões/cards adaptados do CSS fornecido. Prefixo `landing-` isola o estilo da Central SOL e dos demais produtos. Home possui cabeçalho/rodapé próprios; demais páginas continuam no PublicLayout existente. Links reais para cadastro, planos, soluções, comparação, ajuda, contato e documentos legais. Rotas privadas, confirmação de e-mail e entitlements preservados.

| Efeito da referência | Implementação entregue | Evidência / limite |
| --- | --- | --- |
| Esfera pontilhada giratória | Canvas2D com rotação, relevo em movimento, iluminação pelo cursor, respiração sutil, inércia e ondas locais | Canvas inspecionado no navegador; clique e Enter executados, setas implementadas. Arraste com inércia implementado, sem gesto de arraste automatizado nesta ferramenta |
| Entradas e revelação | Hero em sequência, esfera em escala, seções por IntersectionObserver | Entradas observadas em navegação e capturas reais |
| Fluxo conectado | Traços correndo, nó pulsante, segmentos/etiquetas e contexto alternados | Controle Carteira clicado e estado pressionado conferido; diagrama inspecionado |
| Painéis | Barras crescendo em sequência, blocos com variação de luz, números com transição de entrada | Cálculos e teclado conferidos; gráficos ilustrativos, sem receita/retorno presumidos |
| Etapas animadas | Digitação/caret, cursores, clique, cartões deslizando, carimbo e toast | Três animações `landing-cur1/2/3` observadas com `animationPlayState:running`; replay executado |
| Cards e carrossel | Rolagem horizontal, navegação anterior/próximo, zoom e traço SVG no hover/foco | Rolagem real583px registrada; três imagens carregadas. Hover definido em CSS, sem sequência automatizada dedicada |
| CTA e FAQ | Spotlight acompanha cursor; expansão com transição de altura | FAQ expandido e aria-expanded conferido; spotlight implementado, sem benchmark/QA separado |
| Rodapé | Horizonte de partículas animado e navegação real | Canvas e consentimento vistos no navegador |
| Preferência por menos movimento | CSS sem animação/transição e Canvas sem loop automático; interações continuam disponíveis | Guardas implementadas. Não foi alterada a preferência do sistema para simular QA |

O arquivo enviado contém DOM/CSS e referências a bundles relativos, mas não contém o JavaScript original nem pixels serializados do canvas. As animações JavaScript foram recriadas; **não se declara equivalência pixel a pixel ou auditoria de um site remoto cujo URL não foi fornecido**. O adendo do hero esclareceu clique/Enter para ondas, incluídos nesta revisão.

Canvas limitado a aproximadamente30fps em repouso, DPR máximo2, metade dos pontos em telas estreitas, no máximo4 ondas e expiração em2,2s. Loop pausa fora da área visível e em aba oculta; efeitos são desligados na preferência reduced-motion. Gráficos param quando fora de visão; fluxo também não troca o contexto fora da tela. Não foi medido FPS real, CPU ou GPU; esses limites de código não são um benchmark de desempenho.

## Imagens e consentimento

Retratos e avatares do exemplo removidos. Três cenas originais geradas com a ferramenta integrada image_gen, revisadas visualmente, copiadas ao projeto e codificadas em WebP na resolução original1536×1024. Não são fotografias de imóveis existentes, anúncios, reconstruções3D ou clientes; textos alternativos e legenda dizem que são ilustrações por IA.

- `frontend/public/assets/landing/corretor-interior.webp` —252.712bytes.
- `frontend/public/assets/landing/imobiliaria-building.webp` —355.262bytes.
- `frontend/public/assets/landing/flipper-renovation.webp` —203.768bytes.

Total811.742bytes. PNGs originais mantidos no destino de geração fora do Git; nenhuma imagem de portal foi baixada. Prompts finais e modo de geração em `evidence/public-home/image-prompts.json`. Não houve edição semântica na conversão de formato.

AnalyticsConsent recebeu somente variante visual opcional para esta home, com título compacto, detalhes expansíveis e botões próprios do tema. Variante padrão e lógica de consentimento continuam existentes; nada foi aceito automaticamente, nenhuma medição de QA foi enviada por clique em Permitir. A recusa mantém as funções. Captura mobile comprova que não há mais a faixa azul desconectada do visual solicitada na revisão.

## Verificação final

- `npm --prefix frontend run build`: TypeScript/Vite aprovados,2.418módulos,8,23s,exit0. Entrada Home31,69kB JS/10,07kB gzip; CSS42,82kB/8,80kB gzip. Não representa o tamanho total de toda a aplicação.
- `npm --prefix frontend test`:65/65,zero skips,524,3409ms,exit0. Atualizado somente o teste obsoleto que exigia globo geográfico na home; suas verificações de geometria/capitais/componentes preservadas.
- `git diff --check` aprovado. PHP/backend não alterado e não reexecutado neste marco.
- Navegador real em1440,768,390 e320pixels; largura do documento igual à clientWidth em cada medição. Cabeçalho passa a menu compacto até960px, evitando quebra em tablet. Em320px, seletores têm214/231px sem overflow interno.
- Tema claro/escuro, menu com fundo inert/foco/Escape, FAQ, carrossel, replay, clique/Enter no canvas, cálculo por botões e teclado observados. Venda1milhão a3%→30mil; ArrowRight→3,5%→35mil. Sem valores de mercado ou retorno comercial inferidos.

Capturas reais em `evidence/public-home/`: `hero-dark-desktop.png`, `hero-light-mobile.png`, `products-desktop.png`, `animated-steps-desktop.png`, `footer-consent-mobile.png`. Não são montagens. Não houve publicação de sessão, e-mail, senha, token ou conteúdo privado. Os warnings prévios de chunks grandes de Three/MapCanvas e do harness SSR dos testes continuam existentes; não são erros de build.

Pendente específico: reprodução exata dos bundles ausentes dependeria do código-fonte original autorizado. QA de arraste/touch completo e preferência de movimento no sistema não foram declarados aprovados. O novo design substitui a composição anterior da home, mas os componentes antigos de globo/mapa permanecem preservados no repositório. Não foram adicionadas dependências nem alterada a base local.
