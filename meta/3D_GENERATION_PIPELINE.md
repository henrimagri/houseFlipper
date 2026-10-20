# Pipeline de geração 3D

Estados persistidos: `insufficient`, `available`, `failed`.

Fluxo A (entregue): validar extensão e cabeçalho → antivírus opcional (`DocumentMalwareScanner`) → gravar em `visualizations/{tenant}/{tipo}/{id}/{uuid}.glb` → marcar `level=a_model`.

Fluxos B e C: fila Laravel existe no projeto mas **não há Job de reconstrução**. Não processar o mesmo conjunto em loop até existir worker dedicado, orçamento e insumos com overlap suficiente.

Custo: upload limitado a 25 MB e 6 envios/min por usuário.
