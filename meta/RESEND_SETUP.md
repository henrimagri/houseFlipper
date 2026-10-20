# Ativar confirmação de cadastro pelo Resend

O cadastro, a confirmação assinada e o transporte estão implementados. O ambiente local continua com MAIL_MAILER=log e nenhuma chave Resend configurada. Não houve envio externo nesta validação.

## Configuração necessária no backend

No arquivo .env local, definir:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=SUBSTITUIR_PELA_CHAVE_DO_RESEND
MAIL_FROM_ADDRESS=contato@SEU_DOMINIO_VERIFICADO
MAIL_FROM_NAME="FlipControl"
FRONTEND_URL=http://127.0.0.1:5173
```

Usar um remetente de domínio já verificado na conta Resend. A chave deve permanecer somente no ambiente; não colocar em frontend, Git ou relatórios. O SDK compatível foi instalado, e o transporte utiliza endpoint fixo api.resend.com, TLS verificado, conexão de até 3 segundos e requisição de até 10 segundos.

Após alterar ambiente, limpar cache de configuração se houver (`php artisan config:clear`) e reiniciar o processo da aplicação caso necessário. Em produção, usar a URL HTTPS real do frontend.

## Verificação de entrega real ainda pendente

1. Configurar chave e remetente autorizado no ambiente.
2. Usar endereço de e-mail de teste sob seu controle em /cadastro.
3. Confirmar aceitação no Resend e recebimento na caixa de entrada/spam.
4. Abrir o link e escolher Confirmar meu e-mail; acessar o painel.
5. Testar reenvio após a janela de um minuto. O backend também impõe 5 envios por hora/usuário e 10 por hora/IP.

Os links expiram após 60 minutos. Link alterado/expirado é rejeitado. Novas contas precisam confirmar o e-mail para acessar módulos; perfil, saída e reenvio continuam disponíveis. Contas existentes mantêm sua política anterior nesta migração; exigir confirmação retroativa exige planejamento próprio.

## Evidência local obtida

- 116 testes PHP / 674 asserções aprovados; build TypeScript/Vite aprovado.
- Testes de transporte Resend usam cliente HTTP simulado: renderização da mensagem, destinatário, autenticação fictícia, endpoint e rejeição do provedor. Não equivalem a entrega externa.
- Cadastro fictício foi criado pela interface; link da mesma notificação foi gerado no ambiente de teste e confirmado pela interface, liberando o painel. Não se alegou recebimento em caixa postal.
- CEP consultado realmente na interface após configurar bundle local de raízes públicas confiadas pelo Windows, sem desativar TLS. HTTP_CA_BUNDLE é opcional e específico do ambiente. Bundle e .env não foram incluídos nos entregáveis.

Pendências: entrega Resend real, revisão adicional de abuso/concorrrência, UX completa em múltiplas larguras e acessibilidade. Cadastro ainda é um incremento em validação, não aprovação de produção.
