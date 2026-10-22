# Analytics operacional

Consultas paginadas no servidor (25 padrão, 100 máximo), janela máxima 93 dias. Clientes são filtrados pelo cadastro no período; demais campos de data são explicitados nas respostas. Produto e plano são assinaturas do tenant, não papéis do usuário. Perfil 360 mostra metadados e sessões sem token, conversas ou fotografias privadas.

Funil é coorte de usuários cadastrados no período. Confirmação usa email_verified_at; trial compartilhado por tenant é identificado. Não multiplicar usuários por joins em múltiplos produtos. Visitantes, UTM, checkout pago e renovação ainda sem coleta/ledger aparecem não configurados, não zero. Assinatura ativa não comprova receita. Trials vencidos sem medição histórica não são classificados como “nunca usou”. Churn atual não possui motivo informado histórico; não inventar psicologia.

Coortes: retorno no dia calendário Dn, somente dias completos cobertos pela telemetria; sem inferir atividade anterior. Consulta limitada a dez mil usuários por janela. Análises causais sobre influência de funcionalidades não implementadas.
