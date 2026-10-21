# RAG — recuperação de conhecimento imobiliário brasileiro

## Objetivo

Ajudar a IA a responder usando **fontes identificáveis**, legislação/documentação e conteúdo autorizado da própria plataforma. RAG não substitui busca SQL para dados tabulares e imóveis ativos.

## Tipos de fonte

1. Manuais internos do SaaS (planos, onboarding, documentação do CRM, fluxos comerciais).
2. Base imobiliária própria, quando o contrato autorizar indexação e exibição.
3. Dados públicos e documentos oficiais, preservando origem/data/escopo.
4. Materiais fornecidos pelo usuário e permitidos para aquela conta/organização.
5. Documentação contratada de parceiros, **somente conforme licença**.

## Ingestão

- Registrar `source_id`, URL/identificador, proprietário, tenant, licença, categoria, idioma, data da publicação, data da coleta, status de validade e política de retenção.
- Extrair conteúdo de forma segura, normalizar e dividir em trechos com vínculo ao documento/página; preservar passagem citável.
- Aplicar *redaction* de PII quando adequado e evitar indexar segredos ou arquivos privados sem necessidade.
- Suportar reindexação, revogação, exclusão da fonte e atualização incremental.
- Evitar duplicação por hash/versão.

## Recuperação

1. Validar sessão, produto e organização.
2. Definir índice/escopo permitido; em multi-tenant, filtro de autorização **antes e depois** da recuperação, inclusive na leitura do documento original.
3. Buscar por palavras-chave + semântica quando útil; aplicar filtros de localização, tempo e tipo.
4. Reclassificar e selecionar trechos relevantes, respeitando limite de tokens.
5. Retornar referências auditáveis: ID, título, origem, data, trecho e regras de acesso.
6. Responder com indicação de fonte, incerteza e atualização quando necessário.

## Política de respostas

- Fato inexistente nos dados: “não consegui confirmar”.
- Documento desatualizado: informar data e procurar versão vigente quando possível.
- Preço anunciado ≠ preço de transação ≠ avaliação estimada.
- Sem base confiável para determinado prédio: não preencher histórico fictício.
- Texto recuperado nunca recebe privilégios de instruções de sistema.

## Implementação

Escolher entre índice textual do banco atual, pgvector, Qdrant ou outras opções **após medir** volume e relevância. Se o stack atual usa MySQL, verificar capacidades de busca existentes antes de introduzir PostgreSQL ou serviço novo. Incluir testes de separação por tenant, remoção de fonte e citações.

## Métricas

Relevância de trechos, taxa de respostas sem suporte, cobertura de citações, latência, atualização e custo por consulta. Testar perguntas sem resposta conhecida e fontes conflitantes.
