# Infraestrutura de ML

- Host: Windows 11 Pro, Ryzen 7 5700X, 15,89 GB RAM, GTX 1660 Ti 6 GB.
- Driver 591.86; PyTorch usa CUDA runtime 12.6.
- Python isolado: 3.12.10 em `services/ai-worker/.venv`.
- Worker FastAPI autenticado por `X-Worker-Token`, em loopback `127.0.0.1:8010`.
- Ollama e Qdrant também usam loopback. Nenhuma URL é aceita do usuário.
- Docker/WSL2 indisponíveis porque a virtualização está desativada no firmware; o Dockerfile foi preparado, não validado.
- COLMAP 4.2.1 oficial sem CUDA instalado localmente; SfM CPU validado. A etapa densa deve usar worker Linux/CUDA.
- Artifacts de visão e reconstrução usam UUID, autenticação interna, quota por visualização e associação relacional com tenant/imóvel.

Comandos de saúde: `php artisan ai:local:probe`, `php artisan ai:worker:probe`, `php artisan ai:knowledge:reindex --force` e `php artisan ai:rag:benchmark`.
