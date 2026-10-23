# Security Center — estado integrado

## Autorização e MFA

A central exige autenticação Sanctum por bearer, conta ativa, e-mail realmente confirmado, permissão Platform Admin e MFA confirmado no token atual. Papel de tenant/imobiliária não concede acesso. Login dos três produtos continua independente; MFA não é imposto aos clientes comuns nesta fase.

TOTP está integrado: enrolment com senha, confirmação inicial, segredo cifrado, proteção contra replay e códigos de recuperação com hash e uso único. Validação administrativa vale até oito horas; ações sensíveis exigem MFA recente de até dez minutos, além de senha atual/motivo/confirmação quando exigidos pelo endpoint. Códigos, segredo e senha não entram na auditoria. Recuperação é exibida apenas na resposta de emissão. Nenhuma credencial é cadastrada para operadores reais automaticamente. QR local e WebAuthn/passkeys permanecem pendentes. Detalhes e limites em MFA.md.

Cadastro de administradores permite concessão, remoção e reset controlado, com auditoria e proteção do último administrador elegível. Remoção por configuração/allowlist não é simulada por alteração no banco. Não existe impersonação ou reset público; dual control e recuperação de identidade sem códigos são evoluções futuras.

## Sinais e revisão humana

Autenticações, 401/403 e 429 são observados sem payload. Heurísticas de XSS, SQL suspeito, traversal, URL privada e prompt injection são sinais, não confirmação de invasão. Campos analisados são limitados; logs não copiam seus valores. Não há geolocalização externa ou bloqueio automático por score.

O score continua transparente: min(100, sinais × 5) no período. IP é HMAC e máscara; um mesmo IP pode representar CGNAT, não uma identidade. Correlação temporal e hash de conta não comprovam autoria. Operador pode classificar sinais como confirmado, provável, falso positivo ou esperado. Revisão é auditada, não altera automaticamente heurísticas e não treina modelo.

Bloqueio manual de IP expira em uma hora. Suspensão/revogação e controles exigem confirmação, motivo permitido, senha e MFA recente conforme o fluxo. Escritas ficam em transação com auditoria; suspensão revoga tokens. Endpoints comuns de suspensão/revogação não operam sobre administradores da plataforma: o registro específico tem suas proteções próprias.

## Alertas e incidentes

Regras permitem somente login_failures, server_errors e rate_limits, contagem, janela de 60–3.600 segundos, agrupamento global ou ip_hash e severidade permitida. Não há SQL/expressão arbitrária. A avaliação produz evento e incidente deduplicado por regra/grupo/janela, sem bloqueio automático. Painel é o destino disponível; envio de e-mail/webhook de alertas não foi integrado ao fluxo operacional.

Scheduler está declarado; heartbeat só comprova execução observada. Sem processo real, não afirmar alertas contínuos. Incidentes têm responsável, severidade, status, eventos relacionados e resolução exigida ao fechar. Notas privadas não são copiadas para audit log. Workflows de privacidade registram atendimento humano e não executam exclusão de usuários ou tenants automaticamente.

## Callbacks e limites de validação

Adaptadores Stripe/Resend verificam assinatura, janela temporal, identificador e replay/idempotência; persistem dados permitidos, sem payload inteiro ou cartões. Signing secrets ficam na configuração protegida. Estão cobertos por contratos locais; callbacks reais de provedores e conciliação comercial ao vivo não foram validados. Eventos unsupported ou associações não conciliadas não viram pagamentos/acessos presumidos. Accepted de e-mail não significa delivered.

Migrations e testes principais locais usam SQLite. MySQL indisponível, CI remota não executada e backup/restore não configurado impedem atestado de produção. Este documento não é auditoria geral de segurança nem aprovação de QA visual. Resultados consolidados ficam em VALIDATION.md.

Regra correlacionada adicional: correlated_auth, opt-in, sempre por ip_hash. Exige limiar de falhas de login, cinco hashes de contas distintos, acesso à plataforma negado e dois rate limits na mesma janela. Avalia até100 grupos e vincula até30 eventos representativos. Sugere incidente deduplicado; não comprova ataque/identidade e não bloqueia automaticamente. Três testes focais aprovados,18assertions. Incidentes também recebem notas limitadas, prioridade, responsável e eventos vinculados; auditoria não copia o conteúdo das notas.

No correlator, “contas” significa hashes dos identificadores tentados no login. Isso não confirma que essas contas existam; a aplicação não expõe essa distinção para autenticações falhas.
