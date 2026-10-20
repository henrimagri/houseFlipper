# Funcionalidades e preços

## Correções

- `/funcionalidades` recebeu hierarquia visual, ícones por categoria, nomes legíveis para todos os códigos do catálogo, estados de carregamento/erro e comparação responsiva dos três produtos.
- `/precos` recebeu navegação acessível por produto, cartões com contraste explícito nos temas claro e escuro, preços oficiais em destaque e linguagem comercial sem expor estados técnicos do checkout.
- O plano pago agora permite escolher cobrança mensal, trimestral ou anual. O ciclo trimestral aplica 10% de desconto e o anual aplica 20%, recalculando o valor mensal equivalente, a economia e o total do período.
- O CTA de proposta foi substituído pelo fluxo “Continuar para pagamento”; a API aceita o ciclo escolhido e continua recusando o checkout enquanto nenhum provedor estiver configurado.
- Os recursos incluídos mostram até sete itens com ícone, título e explicação.
- Contas que ainda precisam confirmar o e-mail são redirecionadas para `/confirmar-email` antes de qualquer rota `/app`, inclusive pelo antigo acesso “Ver catálogo”.
- O período de teste passou de 14 para 7 dias no catálogo, serviço de cobrança, seeder e interfaces relacionadas.
- Os valores pagos continuam vindos de `config/products.php`: Corretor R$ 149/mês, Imobiliária R$ 499/mês e House Flipper R$ 249/mês.
- Os ciclos e descontos também vêm de `config/products.php` e são retornados pelo catálogo público, evitando divergência entre backend e interface.

## Validação

- Frontend: 7/7 testes e build TypeScript/Vite aprovado.
- Backend: 15/15 testes, 75 asserções; `suite:verify` 23/23.
- Navegador: 6/6 cenários em 375 px e 1366 px, temas claro/escuro, com os nove valores mensal/trimestral/anual validados nos três produtos, sem overflow, erros de console ou falhas de rede.
- Evidências: `artifacts/public-catalog-redesign/`.
