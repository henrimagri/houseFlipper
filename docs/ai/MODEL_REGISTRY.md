# Model registry

O registro executável está em `services/ai-worker/models/registry.json`. Ele contém runtime, modelo base, digest/hash, licença, estado de implantação e rollback.

Estados atuais:

- Qwen2.5 3B: `local_active`.
- Qwen3 Embedding 0.6B: `local_active`.
- LoRA imobiliário 0.5B: `experimental_not_promoted`.
- Depth Anything V2 Small: `local_active`.
- SAM 2.1 Hiera Tiny: `local_active`.

Pesos fundamentais e caches não entram no Git. O adaptador LoRA pequeno permanece como evidência do experimento e está sujeito à licença do modelo base.
