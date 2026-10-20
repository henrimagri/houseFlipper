# Auditoria de formulários

## Padrões verificados

- Labels, tipos de input, limites de tamanho, mensagens de erro e estados de envio.
- Máscaras brasileiras reutilizáveis para telefone, CEP e CPF; normalização antes de enviar à API.
- Valores monetários normalizados e persistidos em centavos nos módulos novos.
- Datas com relação início/término validada no frontend e backend.

## Fluxos corrigidos

- Cadastro: produto, dados pessoais, dados do produto, telefone, endereço completo, busca de CEP, consentimento e confirmação de e-mail via Resend configurável.
- Perfil: foto pública por link, nome, e-mail somente leitura, telefone e preferências de notificação.
- Segurança: alteração de senha exige senha atual, confirmação e mínimo de 12 caracteres.
- Clientes/proprietários: cadastro abre somente após “Novo cliente/proprietário”, com busca, filtros e estados vazios.
- Imóveis: CEP consulta o mesmo endpoint protegido do cadastro e preenche endereço editável.
- Contratos: título, parte, tipo, status, vigência, valor e observações com CRUD persistente.

As APIs repetem as regras importantes; nenhuma validação crítica depende apenas do navegador.
