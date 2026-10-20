# Geração 3D

Data: 19/10/2026.

## O que a segunda IA faz hoje

`SceneGraphFactory` transforma o brief em:

- caixas de parede/piso/teto pintadas com a paleta amostrada
- plano com a fotografia do cômodo como textura
- móveis discretos quando o nome da foto traz evidência (sofá, cama, mesa, cadeira, pia, janela, porta)
- envelope externo conceitual se não houver fachada

Não há malha reconstruída por profundidade. Não há serviço image-to-3D pago. O resultado é `photo_referenced` ou `illustrative`, nunca anunciado como fotogrametria.

## Quando há uma foto

Um cômodo. Geometria fora do quadro não é inventada como planta fiel.

## Quando há várias fotos

Ambientes separados se os nomes indicam cômodos diferentes. Sem grafo de circulação entre eles.

## Pendente

API GPU de malha, Gaussian Splatting, fila assíncrona, cache de artefatos pesados.
