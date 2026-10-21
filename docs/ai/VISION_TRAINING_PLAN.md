# Plano de treinamento visual

## Estado atual

Existe preparação reproduzível em `services/ai-worker/training/vision/prepare_dataset.py`. O processo exige manifesto de autorização e licença, impede caminhos fora da raiz autorizada, valida dimensões e classes das máscaras e separa treino, validação e teste por `scene_id`. O resultado inclui um dataset card. Os testes cobrem recusas e divisão determinística.

Não houve fine-tuning dos modelos visuais. O LoRA textual existente não altera DETR, SAM 2, Depth Anything ou reconstrução 3D.

## Dados aceitos

Cada cena precisa registrar origem, titular da autorização, escopo de uso, licença das imagens e anotações e permissão de treino. Dados privados de clientes não entram por padrão. A divisão é por imóvel/cena para impedir vazamento de fotografias quase idênticas.

O esquema previsto cobre tipo de ambiente, caixas, máscaras de parede/piso/teto, portas, janelas, móveis, material quando observável e qualidade da imagem. Salas, quartos, cozinhas, banheiros, escritórios, corredores, garagens, fachadas e áreas externas devem conter variação de iluminação, estilo e perspectiva.

## Etapas antes do treino

1. Aprovar juridicamente um dataset comercial ou coletar imagens próprias com autorização específica.
2. Fixar taxonomia e manual de anotação; revisar amostras por duas pessoas.
3. Criar baseline sem fine-tuning e medir precisão, recall, F1, IoU/mIoU, falhas e calibração.
4. Avaliar separadamente classificador de cômodo, segmentação arquitetônica e detecção. Checkpoints SegFormer/Mask2Former só entram após conferir classes, licença dos pesos, imagens e anotações.
5. Treinar em ambiente isolado, registrar hash, dependências, GPU, custo e sementes.
6. Comparar no conjunto de teste por cena e promover somente com ganho nas métricas e revisão visual.

Nenhum checkpoint de segmentação arquitetônica foi instalado nesta etapa: a licença e adequação comercial do conjunto alvo ainda não foram aprovadas. SAM 2 continua como refinador orientado por pontos, e a inferência de sala atual é uma regra explícita sobre objetos DETR.

## Avaliação de dados especializados nesta fase

- **ADE20K:** útil para classes arquitetônicas, porém a licença pública do conjunto limita o uso a pesquisa/educação não comercial. Não atende diretamente ao SaaS e não foi baixado para treino.
- **Checkpoints SegFormer/Mask2Former de interiores:** os candidatos encontrados não apresentaram, em conjunto, licença clara dos pesos, licença comercial das imagens/anotações e taxonomia adequada. Nenhum peso foi instalado.
- **SUN RGB-D/ScanNet e derivados:** permanecem candidatos de pesquisa, sujeitos a revisão individual de licença, termos de redistribuição e adequação para uso comercial antes de qualquer ingestão.
- **Dados de clientes:** continuam excluídos do treino sem autorização específica, finalidade definida, retenção e opção de revogação.

O ganho desta fase veio de pós-processamento determinístico e orientações SAM 2, medido pelos testes de seleção de componente e exclusão de objetos. Isso não é fine-tuning e não foi descrito como modelo treinado.
