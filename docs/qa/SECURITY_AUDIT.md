# Auditoria defensiva — 2026-10-19

## Vulnerabilidades confirmadas e corrigidas

1. **MapLibre vulnerável a XSS (crítica)**
   - `npm audit` identificou `GHSA-jrc7-96c5-q579` em `maplibre-gl` 5.x.
   - Atualizado para 6.15.0; o audit atual retorna zero vulnerabilidades.

2. **Referências cruzadas no CRM (alta)**
   - A criação/edição de negócios, tickets e propostas aceitava IDs de responsável, contato ou imóvel sem confirmar tenant e organização.
   - O serviço agora valida membro ativo, tenant e organização antes de persistir. Tentativas cruzadas retornam 422.
   - Regressão automatizada cobre responsável e contato de outro tenant.

3. **Gerenciamento de sessões**
   - Revogação individual continua limitada à relação de tokens do usuário.
   - Novo encerramento das outras sessões mantém o token atual.
   - Testes cobrem preservação da sessão atual e tentativa de revogar token de outro usuário.

4. **Mapa e integrações**
   - CSP permite somente os hosts necessários de OpenFreeMap, Nominatim e IBGE.
   - A busca Nominatim usa limite, User-Agent, throttle de rota, timeout, retry limitado e cache.

## Verificações adicionais

- Busca por padrões de chaves privadas e tokens conhecidos fora de `.env`: nenhum resultado.
- Isolamento de produtos/assinaturas: 23 verificações passaram.
- Publicação do site continua protegida por entitlement e sanitização de tema.

## Riscos remanescentes

- `composer audit` depende da instalação do executável Composer no host e não pôde ser executado.
- Os chunks de Three.js e MapLibre são grandes; isso afeta desempenho inicial nas rotas que os carregam, sem evidência de falha funcional.
- Serviços externos continuam sujeitos à disponibilidade de rede e às credenciais configuradas no ambiente.
