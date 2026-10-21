# Master ASTRA — marco do editor, 21/10/2026

Estado: implementação funcional e testes deste marco concluídos; **prontidão comercial global parcial**. Base inspecionada: `ee279f7ff7db58f035294087b456900fd0adce7b`, somente `Correção-visua/proteção`.

## Diagnóstico e correções

- A criação dependia de fotos. `POST /visualizations/{tipo}/{id}/rooms` agora cria sala, quarto, cozinha, banheiro, escritório ou área externa sem chamar IA, com dimensões informadas, vazio ou mobiliário conceitual. Máximo de 12 ambientes, 200 objetos; ambientes anteriores e GLB ativo preservados. Há teste HTTP sem requisições externas.
- Superfícies não participavam do raycast. Novos ambientes possuem IDs independentes em quatro paredes, piso e teto; clique e seletor selecionam a mesma superfície. Faces internas permitem corte visual por fora sem eliminar paredes. A janela decorativa implícita foi removida; aberturas construtivas continuam pendentes.
- Histórico local baseado em inversões parciais perdia cores heterogêneas, objetos e dimensões. Snapshots persistidos de até 12 operações restauram exatamente os campos editáveis, inclusive ausência de campos em cenas legadas. Histórico de reconstrução é separado. Adicionar ambiente inicia uma nova sequência de undo e salva a cena anterior como versão.
- Escritas de ações/comandos usam transação e bloqueio de registro. Throttle de edição é separado do upload: 60/min por usuário, respeitando também o limite geral da API. Comparação GET de versão pertence à visualização autorizada e não salva o grafo; restauração continua explícita.
- Pintura singular exige seleção; “atrás do sofá” não presume orientação. Teto selecionado não atende comando de parede. Estimativa de custo exige dimensões informadas e preço por m² explícito; a faixa monetária arbitrária foi removida.
- Editor ganhou acabamento fosco/acetinado/brilhante, vistas frontal/lateral/superior, exportação PNG do canvas, transformação e recuperação de objetos ocultos, catálogo licenciado também no modo interior. Recursos Three.js são liberados no descarte. A câmera permanece durante edição e adapta o enquadramento à largura. Tela cheia tinha `overflow-hidden` prevalecendo sobre `overflow-auto`; corrigido e inspecionado em 390×844.
- Modalidades agora seguem o prompt vigente: **A fotografia, B ambiente editável, C reconstrução multivista**. A organização anterior da Fase 13 foi substituída; geometria monocular calibrada continua explicitamente estimada.

## Worker: problemas reproduzidos

Retry poderia remover staging de job ainda ativo; recuperação podia substituir resultado terminal já persistido por falha; metadados de escopo ausentes/corrompidos não falhavam de forma fechada. Corrigidos com regressões. `result.json` público não devolve `scope_hash`.

O gate aceitava PLY sem UV junto a um arquivo apenas chamado PNG. Agora exige imagem decodificável, UV finito/não degenerado, material associado à mesma imagem e preservação após exportar/reabrir GLB. Testes incluem PLY real ASCII e binário com `TextureFile`/`texcoord`, atlas falso, UV inválido e material divergente. Isso comprova vínculo técnico, **não fidelidade fotométrica**; `photometric_fidelity_validated=false` permanece explícito. Geometria rejeitada não segue para texturização.

`InterruptedError` herda de `OSError` e era capturado como falha comum. Agora cancelamento se propaga antes dos fallbacks. `COLMAP_POISSON_DEPTH` tem padrão 8, faixa 6–10, e é enviado a `--PoissonMeshing.depth`; ajuda real do COLMAP 4.2.1 confirmou a opção (default nativo 13). Isso não é limite garantido de RAM. Subprocessos ainda têm timeout de 1.800 s cada, sem orçamento total ou interrupção imediata no meio do COLMAP.

## Testes efetivamente executados

| Comando | Resultado |
|---|---|
| PHP 8.4 com SQLite: `php artisan test` | 87 testes, 438 assertions, 6.097 ms |
| `services/ai-worker/.venv/Scripts/python.exe -m pytest tests -q` | 83 passaram, 34,53 s; warning esperado da fixture UV NaN rejeitada |
| `npm test` em frontend | 12 passaram; verificações estáticas de código, não testes de interação |
| `npm run build` | TypeScript + Vite aprovados; chunks Three.js/MapCanvas acima de 500 kB continuam como aviso |
| `php vendor/bin/pint --dirty --format=json` | Arquivos PHP formatados |

Regressões novas foram observadas falhando antes da correção em histórico legado, throttle de edição, cancelamento, Poisson, escopo/recuperação e atlas. Testes de API cobrem criação, dimensões inválidas, edição de uma parede, undo/redo após GET, versões, comparação sem escrita e acesso negado entre tenants/visualizações.

## QA visual real

Navegador oficial local, conta de QA já autenticada, imóvel de teste “QA Visão Neural Fase 4”. Sem cookies exportados ou sessão privada copiada. A ferramenta conseguiu abrir uma nova aba HTTP local nesta sessão; a restrição histórica das Fases 9–13 não foi contornada.

Criada sala de 4×5×2,7 m, preservando a cena anterior. Canvas inspecionado: paredes/piso/teto, sofá e cadeira licenciados carregados, fallback procedural de mesa/planta, sombras. Pintura azul alterou somente a parede do fundo; desfazer restaurou bege; refazer e recarga preservaram a alteração. Com teto selecionado, comando de pintar parede foi recusado. Clique na parede na vista lateral selecionou “Parede esquerda”. Rotação e zoom pelo mouse alteraram a câmera. Vistas superior, frontal e lateral foram inspecionadas. Uma versão azul foi salva, a parede atual pintada verde, comparação mostrou azul e retornar à versão atual manteve verde; recarga confirmou a cor e o histórico. Viewport 390×844 confirmou enquadramento e acesso ao painel por rolagem após correção. Override de viewport removido ao terminar.

Capturas locais em `evidence/master-editor/`: `wall-blue-desktop.jpg`, `wall-undo-desktop.jpg`, `rejected-wall-on-ceiling.jpg`, `top-view.jpg`, `side-view.jpg`, `version-comparison.jpg`, `current-version-preserved.jpg`, `mobile-editor.jpg`. Não incluem fotografias privadas, credenciais ou pesos. A cena é **paramétrica**, não reconstrução real; estas capturas não aprovam a malha da Fase 8 nem um novo GLB denso.

## Referência funcional e limites

Inspecionados os controles HTML públicos de [criação Suvinil](https://www.simulador3d.com.br/suvinil2.3/?cena=criarAmbienteNovo&id_cliente=) e [cena Suvinil](https://www.simulador3d.com.br/suvinil2.3/?cena=cena03&id_cliente=): dimensões, categorias de cômodo, cores/acabamentos e transformação de objetos. Não houve QA do runtime Suvinil, cópia de código, marca, textura ou modelo. A comparação funcional inicial não comprova paridade visual.

Não foram executados novo treinamento, benchmark geométrico, reconstrução CUDA, contratação, migração destrutiva ou implantação. Baseline ativo preservado: precisão 50,73%, completude 55,81%, atlas 57,51% (métricas históricas da Fase 8). Sem nova medição de RAM/VRAM ou promessa de qualidade comercial. Máquina permanece Windows/GTX 1660 Ti 6 GB; COLMAP 4.2.1 local sem GPU e Docker ausente.

Pendências: catálogo PBR maior, aberturas reais editáveis, snap/bloqueio/alinhamento, planta conectada entre cômodos, iluminação indireta física, navegação em primeira pessoa, perfis de performance, QA de todos os templates/produtos, compatibilidade de superfícies em cenas legadas. O renderer ainda é reconstruído a cada mudança do grafo. Worker precisa fila durável/múltiplos processos, orçamento total de tempo/RAM/VRAM, cancelamento de subprocesso em execução e primeiro ensaio Linux/CUDA autorizado. Fotografia precisa máscara manual verdadeira; streaming, cobrança e demais marcos estão descritos na matriz, sem alegação de conclusão.
