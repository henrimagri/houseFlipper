# Edição 3D e comandos

Data: 19/10/2026.

## Motor

`PATCH /scene` aplica ações: `paint_kind`, `add_object`, `remove_object`, `set_object_color`, `transform`, `hide`, `duplicate`, `upsert_object`, `set_active_room`.

`POST /commands` interpreta português via `SceneCommandInterpreter` e executa as ações na cena. Resposta inclui a cena já mutada. Comandos com SQL, URL ou script são recusados.

## Editor

`PropertyPhotoSceneEditor`: seleção, arraste no modo Decorar, cores, materiais, biblioteca, desfazer/refazer local, versões persistidas, restaurar `original`.

Modo Reforma e Remodelação exige `renovation_simulation` (House Flipper). Divisórias são conceito visual.

## O que não muda

Fotografias originais, ficha do anúncio e arquivos de outro cliente.
