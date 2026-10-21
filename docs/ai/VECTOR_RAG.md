# RAG vetorial

- Embedding: `qwen3-embedding:0.6b`, 1024 dimensões, executado pelo Ollama.
- Banco: Qdrant 1.19.2 nativo para Windows, restrito a `127.0.0.1:6333`.
- Recuperação: lexical + vetorial + Reciprocal Rank Fusion + pós-filtro Eloquent de tenant/produto.
- Conteúdo completo permanece no banco relacional; Qdrant recebe IDs e metadados mínimos.
- Operações: sincronizar, reindexar, atualizar, remover, consultar e detectar status/hash desatualizado.
- Rollback: `AI_VECTOR_ENABLED=false`, preservando o RAG lexical.

Evidência: 6 chunks indexados sem falha. Inserção e remoção foram executadas com um documento temporário; após a remoção o Qdrant voltou a 6 pontos e o registro relacional ficou `deleted`. Em três paráfrases sem coincidência lexical, lexical teve Recall@4/MRR `0/0`; híbrido teve `1,0/1,0`, em 168 ms. Resultado em `rag-benchmark.json`.
