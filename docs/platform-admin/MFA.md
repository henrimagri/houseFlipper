# MFA obrigatório da operação

Implementado sobre Sanctum existente, sem mudar o login dos três produtos. A autorização ativa/verificada/PlatformAdmin continua obrigatória antes do segundo fator. Toda consulta administrativa exige credencial TOTP confirmada e validação do token atual há no máximo 8 horas; um novo login/token não herda MFA de outro. Ações sensíveis mantêm senha atual e passam a exigir segundo fator validado nos últimos 10 minutos.

TOTP SHA1, 6 dígitos, 30 segundos, janela de um intervalo anterior/posterior. Referência primária: [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238.html). Vetores oficiais de oito dígitos são testados separadamente, inclusive após 2038. Segredo aleatório de 160 bits, Base32, cifrado com Laravel Crypt/APP_KEY. Não trocar APP_KEY sem migração segura de dados cifrados.

## Fluxo

1. Login normal; `/platform` chama `/api/platform/mfa/status` antes de montar a central.
2. Sem cadastro, informar senha atual e gerar chave. Adicionar por chave manual em autenticador (SHA1/6/30). Nenhum segredo é enviado a gerador QR externo. URI otpauth também disponível no contrato backend; QR local ainda não implementado.
3. Confirmar em até 10 minutos com código válido. Dez códigos de recuperação são exibidos somente nesta resposta, com Cache-Control no-store. Guardar fora do aplicativo.
4. Em novo login, informar TOTP ou recovery code. Códigos consumidos são invalidados globalmente para aquela credencial. Timestamp TOTP é monotônico: replay do mesmo intervalo é rejeitado, inclusive em outro token.
5. Em `/platform/mfa`, reautenticar, regenerar recovery codes ou remover MFA com senha + segundo fator válido. Remover não libera acesso sem MFA: revoga a validação de todos os tokens e exige novo cadastro.

Recovery codes têm 80 bits de aleatoriedade cada e hash bcrypt; nunca podem ser recuperados depois da resposta inicial. Regenerar invalida todos os anteriores. Consumo usa transação/lock e compare-and-swap de versão para preservar uso único também sem row locks SQLite. Falha na auditoria aborta o grant. Endpoints de escrita têm limite próprio de cinco tentativas/minuto/usuário, além dos limites gerais. Eventos registram resultado, ator e sessão, sem segredo ou código digitado.

Credenciais não são criadas para administradores reais automaticamente. Antes da implantação, operador deve possuir senha válida, email verificado, relógio sincronizado, HTTPS e acesso ao autenticador. Recuperação sem qualquer recovery code requer procedimento de identidade/dual control ainda não implementado; não existe reset público ou bypass em produção. Passkeys/WebAuthn continuam futuros; o status lista métodos realmente suportados. A exceção local expressamente autorizada a seguir é distinta de autenticação TOTP.

Fixtures antigas dos testes administrativos agora provisionam explicitamente credencial confirmada/token validado, sem desabilitar middleware e sem bypass no código de produção. Testes MFA usam endpoints reais: bloqueio, cadastro, expiração, replay, recuperação, regeneração, reset, outro token, usuário comum, token de outro usuário, rate limit e rollback de audit log. QA em navegador deve usar apenas operador descartável, sem capturar segredo/códigos em screenshots.

## Exceção temporária de QA local autorizada em 23/10/2026

O proprietário pediu explicitamente dispensar o MFA somente para o operador temporário local. `platform.local_qa_mfa` aceita ID exato, SHA256 do e-mail exato e expiração obrigatória. A configuração fica desligada por padrão; os valores locais estão somente no `.env` ignorado. Só vale em APP_ENV=local com driverSQLite; produção/testing/staging e outros drivers não recebem a exceção. Mudar o ID sem a impressão correspondente, trocar o e-mail, remover o papel, desativar a conta, deixar o e-mail sem verificação ou expirar a configuração impede acesso pela exceção.

Ainda é obrigatório autenticar com token Sanctum persistido do próprio usuário. Senha atual e confirmação/auditoria de ações sensíveis permanecem obrigatórias. A exigência de TOTP/reautenticação TOTP é dispensada para esse operador enquanto a exceção estiver válida; o banner não afirma que o fator foi validado. Nenhuma chave, credencial ou timestamp de MFA é fabricado/alterado. As outras contas continuam com o fluxo normal.

Chaves de configuração: `PLATFORM_LOCAL_QA_MFA_USER_ID`, `PLATFORM_LOCAL_QA_MFA_EMAIL_SHA256`, `PLATFORM_LOCAL_QA_MFA_EXPIRES_AT`. Configuração local aplicada ao QA ID30 por24h, até24/10/2026 às02:54, America/Sao_Paulo. Ao terminar o QA, remover essas três entradas locais e limpar o cache de configuração antes de remover exclusivamente o operador/credenciais/tokens de QA. Nada disso é publicado no Git.

Testes novos cobrem conta designada/outro admin, senha ainda exigida, fingerprint ausente, e-mail não confirmado, ausência de papel, token alheio, produção, driver nãoSQLite e expiração. Screenshot real da central acessível: `evidence/platform-admin/local-qa-mfa-exemption-access.jpg`. Não é aprovação do redesign completo ou de todos os tamanhos de tela.
