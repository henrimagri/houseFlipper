# Relatório de segurança

Data: 2026-10-19.

## Controles validados

- Autenticação Sanctum, conta ativa, confirmação de e-mail e entitlement por produto.
- Isolamento tenant aplicado às consultas e verificado por testes de contratos, CRM e leilões.
- Rotas de plataforma exigem administrador; recursos de produto rejeitam assinatura incorreta.
- Senhas usam hash do framework; alteração exige senha atual e limite de 72 bytes.
- Uploads têm allowlist, limite de tamanho, nome aleatório e limpeza de temporários.
- Avatares seguem a política escolhida de acesso público por link, com UUID, validação de imagem, dimensões e limite de 2 MB.
- URLs oficiais de leilão aceitam apenas domínios CAIXA, VendasGov/Serpro e gov.br.
- Respostas de status não expõem chaves, tokens ou caminhos internos.
- CSP inclui apenas os provedores de mapa configurados; headers de segurança permanecem ativos.
- Rate limits em login, cadastro, recuperação, confirmação, upload, IA, CEP, webhooks e troca de senha.

## Dependências e testes

- `npm audit --omit=dev`: 0 vulnerabilidades em produção.
- PHPUnit: 12 testes, 57 asserções, todos aprovados.
- `suite:verify`: 23 invariantes de produto, assinatura e catálogo aprovadas.
- Auditoria Composer já registrada em `meta/composer-audit-current.json`; o executável global do Composer não está instalado neste shell.

O trabalho foi limitado ao ambiente local autorizado; não foram executados ataques contra serviços externos.
