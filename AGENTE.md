# AGENTS.md — Regras permanentes do projeto

Este projeto é um SaaS imobiliário com três produtos independentes: Corretor, Imobiliária e House Flipper.

## Arquitetura
- Preservar Laravel, frontend existente, FlipControl, assinaturas e permissões.
- Não misturar dados entre tenants, produtos, filiais ou usuários.
- Não recriar funcionalidades existentes sem necessidade.
- Respeitar as convenções e testes atuais do repositório.

## Desenvolvimento
- Corrigir causas reais, não apenas sintomas.
- Priorizar funcionalidades quebradas antes de melhorias decorativas.
- Preferir componentes reutilizáveis.
- Utilizar bibliotecas UI compatíveis e evitar dependências desnecessárias.
- Validar permissões no backend.
- Nunca inventar dados ou integrações.

## QA e Segurança
- Reproduzir o problema.
- Corrigir.
- Criar ou atualizar testes.
- Executar os testes relevantes.
- Validar visualmente no navegador quando disponível.
- Não afirmar sucesso sem evidências.
- Não executar testes destrutivos nem atacar serviços externos.

## UI/UX
- Manter visual SaaS profissional e responsivo.
- Utilizar componentes acessíveis.
- Evitar overlays que bloqueiem o usuário.
- Utilizar ícones consistentes.
- Garantir que botões, filtros, formulários e menus funcionem.

## Relatórios
- Manter evidências em docs/qa/.
- Relatar funcionalidades corrigidas, testes executados e pendências reais.
- Não modificar produção ou executar migrações destrutivas sem aprovação.