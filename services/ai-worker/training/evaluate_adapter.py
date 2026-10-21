from __future__ import annotations

import hashlib
import json
import time
from pathlib import Path

import torch
from peft import PeftModel
from transformers import AutoModelForCausalLM, AutoTokenizer

ROOT = Path(__file__).resolve().parents[1]
ADAPTER = ROOT / "models" / "real-estate-lora"
BASE_MODEL = "Qwen/Qwen2.5-0.5B-Instruct"


def main() -> None:
    weights = ADAPTER / "adapter_model.safetensors"
    if not weights.is_file() or weights.stat().st_size == 0:
        raise RuntimeError("Adaptador treinado ausente ou vazio.")
    device = "cuda" if torch.cuda.is_available() else "cpu"
    dtype = torch.float16 if device == "cuda" else torch.float32
    tokenizer = AutoTokenizer.from_pretrained(ADAPTER, trust_remote_code=False)
    base = AutoModelForCausalLM.from_pretrained(BASE_MODEL, dtype=dtype, trust_remote_code=False)
    model = PeftModel.from_pretrained(base, ADAPTER).to(device).eval()
    prompt = "### Instrução\nClassifique a intenção imobiliária.\n### Entrada\nCompare os imóveis encontrados.\n### Resposta\n"
    inputs = tokenizer(prompt, return_tensors="pt").to(device)
    started = time.time()
    with torch.inference_mode():
        generated = model.generate(**inputs, max_new_tokens=40, do_sample=False)
    response = tokenizer.decode(generated[0][inputs["input_ids"].shape[1] :], skip_special_tokens=True)
    result = {
        "adapter_loaded": True,
        "adapter_sha256": hashlib.sha256(weights.read_bytes()).hexdigest(),
        "adapter_bytes": weights.stat().st_size,
        "base_model": BASE_MODEL,
        "device": device,
        "latency_seconds": round(time.time() - started, 3),
        "prompt": prompt,
        "response": response,
        "promotion_decision": "not_promoted_small_dataset",
    }
    (ADAPTER / "evaluation_results.json").write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
