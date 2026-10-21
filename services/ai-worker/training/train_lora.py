from __future__ import annotations

import hashlib
import json
import os
import time
from pathlib import Path

import psutil
import torch
from datasets import load_dataset
from peft import LoraConfig, get_peft_model
from transformers import AutoModelForCausalLM, AutoTokenizer, DataCollatorForLanguageModeling, Trainer, TrainingArguments

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "training" / "data"
OUTPUT = ROOT / "models" / "real-estate-lora"
CHECKPOINTS = ROOT / "artifacts" / "training-checkpoints"
BASE_MODEL = os.environ.get("AI_TRAINING_BASE_MODEL", "Qwen/Qwen2.5-0.5B-Instruct")
MAX_LENGTH = 160


def render(example: dict) -> str:
    output = json.dumps(example["output"], ensure_ascii=False, sort_keys=True)
    return f"### Instrução\n{example['instruction']}\n### Entrada\n{example['input']}\n### Resposta\n{output}"


def main() -> None:
    if not (DATA / "train.jsonl").is_file():
        raise RuntimeError("Execute prepare_dataset.py antes do treinamento.")
    started = time.time()
    device = "cuda" if torch.cuda.is_available() else "cpu"
    if device == "cuda":
        torch.cuda.reset_peak_memory_stats()
    tokenizer = AutoTokenizer.from_pretrained(BASE_MODEL, trust_remote_code=False)
    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token
    dataset = load_dataset("json", data_files={
        "train": str(DATA / "train.jsonl"),
        "validation": str(DATA / "validation.jsonl"),
        "test": str(DATA / "test.jsonl"),
    })

    def tokenize(batch: dict) -> dict:
        return tokenizer(render(batch), truncation=True, max_length=MAX_LENGTH)

    tokenized = dataset.map(tokenize, remove_columns=dataset["train"].column_names)
    dtype = torch.float16 if device == "cuda" else torch.float32
    model = AutoModelForCausalLM.from_pretrained(BASE_MODEL, dtype=dtype, trust_remote_code=False)
    model.config.use_cache = False
    lora = LoraConfig(
        r=8,
        lora_alpha=16,
        lora_dropout=0.05,
        target_modules=["q_proj", "v_proj"],
        task_type="CAUSAL_LM",
        bias="none",
    )
    model = get_peft_model(model, lora)
    arguments = TrainingArguments(
        output_dir=str(CHECKPOINTS),
        num_train_epochs=1,
        per_device_train_batch_size=1,
        per_device_eval_batch_size=1,
        gradient_accumulation_steps=2,
        learning_rate=2e-4,
        warmup_steps=0,
        logging_steps=1,
        eval_strategy="epoch",
        save_strategy="epoch",
        save_total_limit=1,
        fp16=device == "cuda",
        report_to=[],
        seed=20261020,
    )
    trainer = Trainer(
        model=model,
        args=arguments,
        train_dataset=tokenized["train"],
        eval_dataset=tokenized["validation"],
        data_collator=DataCollatorForLanguageModeling(tokenizer=tokenizer, mlm=False),
    )
    before = trainer.evaluate()
    train = trainer.train()
    after = trainer.evaluate()
    test = trainer.evaluate(tokenized["test"], metric_key_prefix="test")
    OUTPUT.mkdir(parents=True, exist_ok=True)
    model.save_pretrained(OUTPUT, safe_serialization=True)
    tokenizer.save_pretrained(OUTPUT)
    data_hash = hashlib.sha256((DATA / "dataset_metadata.json").read_bytes()).hexdigest()
    metadata = {
        "base_model": BASE_MODEL,
        "base_model_license": "Apache-2.0",
        "adapter_version": "2026-10-20.1",
        "dataset_metadata_hash": data_hash,
        "device": device,
        "torch": torch.__version__,
        "cuda_runtime": torch.version.cuda,
        "gpu": torch.cuda.get_device_name(0) if torch.cuda.is_available() else None,
        "peak_vram_mb": round(torch.cuda.max_memory_allocated() / 1024**2, 2) if torch.cuda.is_available() else 0,
        "process_rss_mb": round(psutil.Process().memory_info().rss / 1024**2, 2),
        "duration_seconds": round(time.time() - started, 2),
        "parameters": {"rank": 8, "alpha": 16, "dropout": 0.05, "epochs": 1, "learning_rate": 2e-4, "max_length": MAX_LENGTH},
        "metrics": {"before": before, "train": train.metrics, "after": after, "test": test},
        "status": "experimental_not_promoted",
    }
    (OUTPUT / "training_metadata.json").write_text(json.dumps(metadata, ensure_ascii=False, indent=2, default=str) + "\n", encoding="utf-8")
    print(json.dumps(metadata, ensure_ascii=False, default=str))


if __name__ == "__main__":
    main()
