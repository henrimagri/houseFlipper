<?php

return [
    'provider' => env('AI_PROVIDER', 'deterministic'),
    'prompt_version' => '2026-10-20.1',
    'max_tool_calls' => (int) env('AI_MAX_TOOL_CALLS', 6),
    'session_retention_days' => (int) env('AI_SESSION_RETENTION_DAYS', 90),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('AI_OPENAI_MODEL', 'gpt-5-mini'),
    ],
    'local' => [
        'endpoint' => env('AI_LOCAL_ENDPOINT', 'http://127.0.0.1:11434'),
        'model' => env('AI_LOCAL_MODEL', 'qwen2.5:3b'),
        'timeout' => (int) env('AI_LOCAL_TIMEOUT', 120),
        'allowed_hosts' => ['127.0.0.1', 'localhost'],
    ],
    'embeddings' => [
        'provider' => env('AI_EMBEDDING_PROVIDER', 'ollama'),
        'model' => env('AI_EMBEDDING_MODEL', 'qwen3-embedding:0.6b'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1024),
    ],
    'vector' => [
        'enabled' => filter_var(env('AI_VECTOR_ENABLED', false), FILTER_VALIDATE_BOOL),
        'endpoint' => env('AI_QDRANT_ENDPOINT', 'http://127.0.0.1:6333'),
        'api_key' => env('AI_QDRANT_API_KEY'),
        'collection' => env('AI_QDRANT_COLLECTION', 'ai_knowledge_v1'),
        'timeout' => (int) env('AI_QDRANT_TIMEOUT', 15),
        'allowed_hosts' => ['127.0.0.1', 'localhost'],
    ],
    'worker' => [
        'endpoint' => env('AI_WORKER_ENDPOINT', 'http://127.0.0.1:8010'),
        'token' => env('AI_WORKER_TOKEN'),
        'timeout' => (int) env('AI_WORKER_TIMEOUT', 30),
        'processing_timeout' => (int) env('AI_WORKER_PROCESSING_TIMEOUT', 1800),
        'allowed_hosts' => ['127.0.0.1', 'localhost'],
    ],
    'agents' => [
        'general_assistant' => ['label' => 'Assistente geral', 'tools' => ['retrieve_knowledge', 'synthesize_response']],
        'business_assistant' => ['label' => 'Assistente empresarial', 'tools' => ['retrieve_knowledge', 'synthesize_response']],
        'marketing_assistant' => ['label' => 'Assistente de marketing', 'tools' => ['retrieve_knowledge', 'synthesize_response']],
        'text_creation_assistant' => ['label' => 'Assistente de conteúdo', 'tools' => ['retrieve_knowledge', 'synthesize_response']],
        'real_estate_search' => ['label' => 'Busca imobiliária', 'tools' => ['search_inventory', 'search_market', 'compare_properties', 'retrieve_knowledge', 'synthesize_response']],
        'broker_assistant' => ['label' => 'Assistente do corretor', 'tools' => ['search_inventory', 'search_market', 'compare_properties', 'retrieve_knowledge', 'synthesize_response']],
        'agency_intelligence' => ['label' => 'Inteligência da imobiliária', 'tools' => ['search_inventory', 'search_market', 'compare_properties', 'crm_summary', 'retrieve_knowledge', 'synthesize_response']],
        'house_flipper_analyst' => ['label' => 'Analista House Flipper', 'tools' => ['search_inventory', 'search_market', 'compare_properties', 'calculate_investment', 'visualization_context', 'retrieve_knowledge', 'synthesize_response']],
        'vision_renovation' => ['label' => 'Análise visual e reforma', 'tools' => ['search_inventory', 'search_market', 'visualization_context', 'retrieve_knowledge', 'synthesize_response']],
        'quality_validation' => ['label' => 'Validação de qualidade', 'tools' => []],
    ],
    'product_agents' => [
        'corretor' => ['general_assistant', 'business_assistant', 'marketing_assistant', 'text_creation_assistant', 'broker_assistant', 'real_estate_search', 'quality_validation'],
        'imobiliaria' => ['general_assistant', 'business_assistant', 'marketing_assistant', 'text_creation_assistant', 'agency_intelligence', 'real_estate_search', 'quality_validation'],
        'house_flipper' => ['general_assistant', 'business_assistant', 'marketing_assistant', 'text_creation_assistant', 'house_flipper_analyst', 'real_estate_search', 'vision_renovation', 'quality_validation'],
    ],
];
