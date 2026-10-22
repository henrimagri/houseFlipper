# Security Center

Eventos de autenticação, 401/403 e 429 coletados sem payload. Detectores heurísticos de XSS, SQL suspeito, traversal, URL privada e prompt injection são sinais observados, não prova de invasão nem substitutos da validação de URLs, queries parametrizadas ou autorização. Não lê senhas/headers/uploads. Campos elegíveis limitados a search/url/source_url/path/message (8 KB por campo). Não há bloqueio automático por score ou geolocalização.

Score transparente: min(100, número de sinais × 5) no período consultado. Mesmo IP pode representar CGNAT; correlação ±10 min não comprova identidade. IP bruto não fica no banco: HMAC e máscara. Bloqueio manual expira em uma hora, exige senha/motivo/confirmação e auditoria transacional. IPs legados sem coleta não são inventados.

Revogar sessão, revogar todas e suspender/restaurar usuário exigem senha atual, confirmação e motivo enumerado. Nenhuma ação sobre administradores de plataforma permitida por esse endpoint. Suspensão revoga tokens Sanctum. Sessões legadas não têm IP/dispositivo correlacionável; essa informação aparece indisponível. MFA não instalado nesta etapa.

Incidentes possuem severidade, eventos vinculados, responsável, status e resolução exigida ao encerrar. Notas de suporte são privadas e auditadas sem copiar conteúdo para logs. Conteúdo de conversas privadas não tem endpoint administrativo neste incremento.
