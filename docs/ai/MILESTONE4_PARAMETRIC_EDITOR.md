# Marco 4 — incremento P1 do editor paramétrico

Data: 21/10/2026. Branch: `Correção-visua/proteção`. Incremento posterior ao refinamento fotográfico publicado em `cd2b17b`. Estado: funcionalidades abaixo implementadas e testadas; qualidade comercial global parcial. Nenhuma reconstrução foi gerada ou promovida. Fase 8 preservada.

## Código integrado

- `SceneGraphFactory.php`: aberturas com identificador aleatório, cômodo, parede, tipo, largura/altura, centro horizontal, peitoril e origem `user`. Operações `add_opening`, `update_opening`, `remove_opening` no endpoint existente, com autorização Laravel e persistência transacional. Histórico, undo/redo e versões incluem aberturas; snapshots legados sem esse campo restauram lista vazia.
- Medidas do cômodo devem ter origem informada pelo usuário. Mínimo de 30 cm, margem lateral/superior de 10 cm, distância entre aberturas de 5 cm, porta no piso, janela com peitoril mínimo de 10 cm. Limites de 24 por cômodo e 96 por projeto. Redimensionamento incompatível é rejeitado sem mover ou reduzir os vãos silenciosamente.
- `wallOpenings.mjs` e `PropertyPhotoSceneEditor.tsx`: recortes verdadeiros por células retangulares; triângulos não atravessam a abertura, UV permanece contínuo. Quatro paredes com normais internas. Janela com moldura/vidro transparente; porta aberta a 60 graus com folha e maçaneta. Seleção por raycast e painel contextual. Interface informa que são medidas propostas pelo usuário, não extraídas das fotos.
- `surfaceMaterials.mjs`: seis materiais procedurais originais, mapas de cor/relevo/rugosidade de 128², repetição em metros e metalness zero para superfícies dielétricas. Cor em sRGB; mapas de dados lineares. Mapas liberados junto ao material. **Não são texturas fotográficas, scans nem medição física de materiais.**
- `FurnitureCatalogPanel.tsx`: busca por nome/material, filtro GLB/paramétrico, ícones, dimensões de referência, materiais, autoria/licença e fonte. Reutiliza os dois GLBs licenciados existentes; nenhum modelo novo baixado. Status diferencia carregamento, modelo disponível e fallback simplificado.

## Falhas reproduzidas e corrigidas no QA

1. Inserção usava quantidade global de objetos e podia colocar a cadeira fora do piso. Agora usa contagem do cômodo e grade limitada às dimensões. Três testes falharam antes da correção, incluindo 30 objetos em outro cômodo levando a Z=8,05 m. Objetos existentes não são reposicionados automaticamente. A grade repete após nove posições; **não resolve colisões**, e móveis grandes podem exigir escala manual.
2. Alterar pé-direito 2,7 → 2 m com porta de 2,1 m produzia 422 correto, mas campo ainda mostrava 2. Agora restaura valor persistido após falha, sem sobrescrever edição mais recente. Mensagem antiga de sucesso também é limpa antes de mutações. Repetição no navegador mostrou 2,7 e erro de encaixe, sem alteração da geometria.
3. Callback assíncrono dos GLBs reaplicava seleção capturada antes de o usuário limpá-la. Uma referência atualizada imediatamente em cada seleção agora é usada depois de carregar modelos/texturas. Repetido no viewport normal: selecionar piso, trocar modo, limpar seleção, aguardar modelos; piso voltou a madeira sem brilho azul residual.

## QA real no navegador

Aplicação local, imóvel de QA 30, conta demo autorizada. Sala paramétrica informada de 4 × 5 × 2,7 m. Não é reconstrução de uma fotografia real.

- Catálogo pesquisado por “veludo”, filtro GLB e atribuição inspecionados. Cadeira inserida pelo botão; após correção, inserção permaneceu no piso. Cadeira anterior ao ajuste foi movida manualmente no modo Decorar e persistiu.
- Janela na parede do fundo: inicialmente 1,2 × 1,1 m, centro 0, base 1 m. Tentativa sobreposta rejeitada. Janela selecionada pelo canvas, editada para largura 1,4, desfazer voltou a 1,2 e refazer/reabertura mostraram 1,4.
- Porta na parede esquerda: 0,9 × 2,1 m, centro 0,8, base 0. Vão e folha aberta observados de frente, lateral e acima. Piso alterado para madeira, sombras e materiais visíveis.
- Versão “Marco 4 — vãos e madeira” salva, página recarregada e versão comparada em modo somente leitura, preservando a cena atual. Porta/janela e alterações permaneceram. Retorno à versão atual observado.
- Layout estreito e desktop 1440×1000 inspecionados. Captura desktop tem reamostragem da ferramenta e texto menos nítido; não é medição de resolução do produto. Override removido ao concluir. Algumas tentativas de arrasto acertaram piso e orbitaram a câmera; apenas a movimentação efetivamente salva é contabilizada.

Capturas em `evidence/milestone4/`: `parametric-before.jpg` (sem novos vãos), `furniture-catalog.jpg`, `parametric-reopened.jpg`, `parametric-frontal.jpg`, `parametric-lateral.jpg`, `parametric-top.jpg`, `parametric-version-desktop.jpg`. Licenças dos móveis em `ATTRIBUTION.md` e no catálogo. Arquivos originais de fotos, pesos e GLBs não foram acrescentados ao Git.

## Testes executados

- PHP 8.4 com SQLite: `php vendor/bin/phpunit`: **108/108, 712 assertions, 8.058 ms**. Abrange persistência/restauração/undo, rollback integral, tenant estrangeiro, medidas, sobreposição, limites e colocação de objetos; suíte completa existente preservada.
- `php vendor/bin/pint --test` nos cinco arquivos PHP alterados: aprovado.
- `cd frontend; npm test`: **44/44** (333,6642 ms na execução integrada). Inclui dez testes de mapas/materiais, oito de geometria/UV/raycast real Three.js sem WebGL, quatorze de máscara e doze verificações estáticas existentes. Repetido pelo implementador após correção do destaque: 44/44.
- `npm run build`: TypeScript e Vite aprovados; build final após correção do destaque em 15,35 s, 3.409 módulos. Aviso conhecido de chunks acima de 500 kB permanece.
- Python permanece no incremento anterior desta mesma sessão: **146/146**, 32,19 s, um warning conhecido da fixture com UV inválido. Nenhuma alteração Python neste incremento P1; não foi executado novamente sem mudança que justificasse.
- `git diff --check`: aprovado.

## Recursos e limites

Sem dependências novas, inferência ou reconstrução neste incremento. Geração limitada a três mapas RGBA128² por superfície: 192 KiB de dados CPU por material, antes de mipmaps/cópias GPU. Não se mediu FPS, RAM ou VRAM do navegador; isso não é benchmark de GPU integrada.

Vãos são renderizados em Interior/Decorar/Reformar. Planta e Exterior continuam esquemáticos, com aviso explícito. Sem geometria estrutural de espessura completa, catálogo amplo, encaixe/colisão, navegação em primeira pessoa ou fotorealismo aprovado. Mesa/planta e demais modelos procedurais continuam visivelmente simplificados; duas cadeiras próximas no ensaio mostram que posicionamento requer intervenção humana. Renderizador ainda é recriado após mutações do grafo.

Próximas prioridades: ampliar corpus autorizado da pintura (faltam banheiro, escritório, corredor, fachada e externo), avaliar máscaras com anotação quando disponível; no editor, colisão/encaixe, catálogo licenciado maior, melhorar móveis procedurais e benchmark de renderização. Cancelamento da inferência fotográfica nativa continua parcial conforme relatório da pintura. Não houve auditoria geral, fine-tuning, densificação CUDA nem alteração do gate.
