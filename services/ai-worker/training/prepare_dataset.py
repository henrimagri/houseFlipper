from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SOURCE = ROOT / "source_examples.json"
OUTPUT = ROOT / "data"


def main() -> None:
    examples = json.loads(SOURCE.read_text(encoding="utf-8"))
    seen: set[str] = set()
    prepared = []
    for example in examples:
        canonical = json.dumps(example, ensure_ascii=False, sort_keys=True)
        digest = hashlib.sha256(canonical.encode()).hexdigest()
        if digest in seen:
            raise ValueError("Exemplo duplicado detectado.")
        seen.add(digest)
        example["example_hash"] = digest
        example["license"] = "Projeto FlipControl — sintético revisado"
        prepared.append(example)
    prepared.sort(key=lambda item: item["example_hash"])
    train_end = max(1, int(len(prepared) * 0.7))
    validation_end = max(train_end + 1, int(len(prepared) * 0.85))
    splits = {
        "train": prepared[:train_end],
        "validation": prepared[train_end:validation_end],
        "test": prepared[validation_end:],
    }
    if any(not values for values in splits.values()):
        raise ValueError("Cada split precisa conter exemplos.")
    OUTPUT.mkdir(parents=True, exist_ok=True)
    for name, values in splits.items():
        with (OUTPUT / f"{name}.jsonl").open("w", encoding="utf-8") as handle:
            for value in values:
                handle.write(json.dumps(value, ensure_ascii=False, sort_keys=True) + "\n")
    metadata = {
        "version": "2026-10-20.1",
        "source": "synthetic-reviewed",
        "sha256": hashlib.sha256(SOURCE.read_bytes()).hexdigest(),
        "counts": {name: len(values) for name, values in splits.items()},
        "contains_personal_data": False,
    }
    (OUTPUT / "dataset_metadata.json").write_text(json.dumps(metadata, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(metadata, ensure_ascii=False))


if __name__ == "__main__":
    main()
