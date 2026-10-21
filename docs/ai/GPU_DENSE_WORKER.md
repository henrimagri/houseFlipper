# Worker de reconstrução densa

## Contrato operacional

O fluxo permanece `React -> Laravel -> fila persistida -> FastAPI/GPU -> armazenamento privado -> gate -> Three.js`. Laravel cria o job, verifica produto, usuário, tenant e propriedade dos assets. O worker recebe uma chave UUID idempotente e um escopo opaco HMAC por visualização. Consulta, cancelamento e download exigem o mesmo escopo; ele não é devolvido na API nem gravado em logs.

O worker aceita uma reconstrução simultânea por processo. O estado é escrito de forma atômica. Após reinício, jobs que estavam em `queued`, `processing` ou `cancelling` viram `failed/worker_restarted`, permitindo retry controlado com nova chave. Jobs terminais expiram após `AI_WORKER_RETENTION_HOURS` (72 horas por padrão). O armazenamento permanente e a cota por imóvel continuam sob autoridade do Laravel.

## Pipeline COLMAP 4.2.1

O binário é inspecionado no início do job. O capability gate exige Linux, dispositivo NVIDIA visível, build GPU confirmado e `image_undistorter`, `patch_match_stereo`, `stereo_fusion`, `poisson_mesher` ou `delaunay_mesher`, e `mesh_texturer`.

Os nomes e opções foram conferidos no COLMAP 4.2.1, commit `bd1fcf6`. A sequência usa poses esparsas existentes, undistortion, PatchMatch geométrico, fusão, meshing, correção fotométrica do atlas e conversão da saída texturizada para GLB. Uma malha sem arquivo de textura verificável é rejeitada. O GLB continua com `final_model_eligible=false` até avaliação geométrica independente e revisão visual.

## Implantação Linux/CUDA

`services/ai-worker/compose.gpu.yml` exige uma imagem interna fixada por digest, token forte, volume privado e runtime NVIDIA. O serviço publica somente em loopback. Ollama, Qdrant, FastAPI e GPU não devem receber exposição pública direta.

Requisitos estimados para `playroom_small`: Linux x86_64, driver NVIDIA compatível, COLMAP 4.2.1 com CUDA, GTX 1660 Ti 6 GB ou superior, 16 GB de RAM e 30 GB temporários. São estimativas de planejamento. O primeiro job Linux autorizado deverá registrar consumo real.

## Estado do host em 21/10/2026

- Windows; NVIDIA GTX 1660 Ti, 6.144 MiB.
- COLMAP 4.2.1 `bd1fcf6` identificado como `without GPU support`.
- Os comandos densos exigidos existem, incluindo `mesh_texturer`.
- Docker não está instalado.
- WSL2 e drivers não foram alterados.

Consequência: contratos e testes estão prontos, mas PatchMatch CUDA, Stereo Fusion real, atlas real e GLB denso ainda não foram executados neste host.

## Auditoria corretiva do master (21/10/2026)

Correção das afirmações anteriores: detectar arquivo PNG não comprovava um atlas ligado à malha. O gate agora decodifica a imagem, verifica UVs finitos/não degenerados, vínculo do material ao atlas e round-trip do GLB. `photometric_fidelity_validated=false` distingue isso de fidelidade fotográfica. Testes PLY cobrem ASCII e binário com texcoords por face; não houve novo atlas real do dataset.

Na recuperação, resultado terminal já gravado é preservado; retry não remove staging ativo. Escopo ausente/corrompido em reconstrução falha fechado, e `result.json` não expõe scope_hash. Caminhos legados de visão/simulação ainda dependem do token interno, sem novo contrato de escopo nesta etapa. Cancelamentos propagam `InterruptedError` antes de capturas de `OSError`.

`COLMAP_POISSON_DEPTH` é padrão 8, clamp 6–10, confirmado contra `poisson_mesher -h` local e enviado explicitamente. Timeout permanece 1.800 s por comando, sem limite total de trabalho/RAM/VRAM ou interrupção imediata do subprocesso. Há um worker por processo e fila em memória, não uma fila distribuída durável. Idempotência por UUID não compara hash do conteúdo. Não declarar implantação de produção concluída com esses limites.
