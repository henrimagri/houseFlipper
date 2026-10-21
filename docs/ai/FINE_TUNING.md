# Fine-tuning e LoRA

Pipeline real em `services/ai-worker/training/`: preparação determinística, splits sem duplicação, LoRA PEFT, checkpoints, avaliação e carregamento do adaptador.

- Base: `Qwen/Qwen2.5-0.5B-Instruct`, Apache-2.0.
- PyTorch: 2.8.0+cu126; PEFT 0.21.2; Transformers 5.20.2.
- GPU: GTX 1660 Ti 6 GB.
- LoRA: rank 8, alpha 16, dropout 0,05; `q_proj` e `v_proj`.
- Execução: 1 época, 12 exemplos de treino, 3 validação, 3 teste, 15,76 s totais; pico de 1.291,21 MB de VRAM e 2.341,43 MB de RSS.
- Artefato: `adapter_model.safetensors`, 2.175.168 bytes, SHA-256 `f4ec9941fe2e19fd3b4d6ea75b2f2b0ddc61c7c66789455a09d66f8c5771beb7`.
- Validação: loss 2,924 antes e 2,819 depois; test loss 3,689.
- Decisão: `experimental_not_promoted`. O adaptador carrega e infere, porém o teste qualitativo foi insatisfatório.

Reprodução: `prepare_dataset.py`, `train_lora.py`, `evaluate_adapter.py`. Não usar o adaptador em produção até ampliar dados e melhorar a avaliação qualitativa.
