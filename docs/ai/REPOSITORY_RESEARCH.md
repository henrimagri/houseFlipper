# Pesquisa e aproveitamento de repositórios externos

**Verificação realizada em:** 20/10/2026.

## Pi

- Fonte: <https://github.com/earendil-works/pi>
- Commit estudado: `9df819de392d610c43d1aaa0830d56219a6321ff`.
- Licença do repositório: MIT.
- Pacotes relevantes encontrados: `@earendil-works/pi-agent-core` 1.3.0 e `@earendil-works/pi-ai` 1.3.0.
- Requisito Node encontrado: `>=22.19`; a máquina possui Node 24.22.0.
- Padrões úteis: ferramentas tipadas, transcript/estado, streaming, hooks antes/depois da ferramenta, abort, execução paralela ou sequencial e contabilização de uso/custo.
- Decisão: não instalar nesta etapa. O Laravel já possui autenticação, persistência, autorização por tenant/produto e execução de tools. Adotar Pi agora criaria um segundo runtime e não eliminaria a necessidade das políticas do SaaS. Os padrões de lifecycle e orçamento serão aplicados ao executor Laravel.

## Awesome LLM Apps

- Fonte: <https://github.com/Shubhamsaboo/awesome-llm-apps>
- Commit estudado: `874bc13a57aa2ba1266a84f2444808ce3093efb6`.
- Licença do repositório: Apache-2.0; pesos, APIs, dados e exemplos externos continuam sujeitos às próprias licenças.
- O exemplo de agentes imobiliários usa Python, Agno, Streamlit, Firecrawl e Gemini ou Ollama. Ele pesquisa portais dos Estados Unidos e não oferece autorização multi-tenant pronta.
- O exemplo de reforma usa Google ADK e Gemini para análise multimodal e imagens conceituais. Não produz malha 3D editável nem reconstrução geométrica fiel.
- Há referências úteis de RAG corretivo/híbrido, saída tipada e avaliação, mas copiar a coleção inteira adicionaria dependências e provedores sem necessidade.
- Decisão: usar os exemplos como padrões de desenho e testes; não incorporar ou instalar o repositório inteiro.

## Matriz de decisão

| Recurso | Código local | Recurso externo | Licença verificada | Custo esperado | Decisão | Evidência/teste |
|---|---|---|---|---|---|---|
| Orquestração/tool calling | Sim, Laravel | Pi | MIT | runtime Node + manutenção | manter Laravel; absorver hooks/abort/budget | allowlist testada em `AiPlatformTest` |
| Busca imobiliária | Sim | agente imobiliário do Awesome | Apache-2.0 no repositório; APIs externas variam | Firecrawl/modelo/provedor | não copiar; adaptar somente ideias de decomposição | avaliação PT-BR 6/6 e pipeline local |
| Visão/reforma | Heurística, sem ML | Home Renovation Agent | Apache-2.0 no repositório; Gemini sujeito a termos/custo | tokens de imagem e armazenamento | protótipo isolado somente após dataset e política | nenhuma precisão visual alegada |
| RAG | Lexical com citações | exemplos selecionados | Apache-2.0 no repositório | embedding/vector DB se adotados | medir recall antes de vetorizar | teste de isolamento entre tenants |
| Memória | sessão + preferências opt-in | padrões dos dois projetos | MIT/Apache-2.0 | banco e retenção | manter dados estruturados no Laravel | teste de isolamento entre usuários |

## Regras para futura incorporação

1. Selecionar uma lacuna mensurável e definir baseline.
2. Verificar licença do código, pesos, dataset e uso comercial.
3. Prototipar atrás de adaptador e feature flag.
4. Medir qualidade, latência e custo contra a implementação atual.
5. Manter autorização, tenant, auditoria e dados privados no Laravel.
6. Integrar somente se o ganho justificar operação e dependências adicionais.
