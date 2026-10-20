# Política de acesso a fotos — públicas por link

## Comportamento atual verificado

Fotos locais são gravadas no disco public e retornadas por URLs /storage. O router de desenvolvimento foi corrigido para entregar a junction/symlink autorizada. Isso mantém a política pública existente: conhecer a URL permite baixar o arquivo sem autenticação. Não se afirma que essa política é adequada para todos os dados enviados.

## Alternativa não adotada: fotos privadas da conta

- Novos arquivos ficam em disco privado, separados das importações.
- Rota proposta GET /api/opportunities/{opportunity}/photos/{photo}/content exige Sanctum, conta ativa, confirmação quando aplicável e propriedade da oportunidade; também verifica vínculo da foto ao recurso.
- Frontend carrega a imagem com Authorization e usa URL de objeto local, revogada ao desmontar. Token não vai na URL, em query ou nos logs. Capas, listas, modal e galeria precisam ser atualizados e testados.
- Outro usuário e usuário anônimo recebem bloqueio sem conteúdo. Não se cria compartilhamento externo automático.
- URLs externas de anúncios continuam pertencendo aos portais; tornar a foto local privada não torna a imagem externa privada. A interface deve distinguir as origens.

## Compatibilidade e migração

Arquivos existentes não serão movidos ou apagados sem uma etapa explícita de migração e validação. A migração deve ter inventário, cópia/validação por hash, atualização transacional dos metadados, recuperação e verificação de que a URL pública antiga deixou de entregar o conteúdo. Cópias antigas em caches/backups não desaparecem automaticamente. Excluir a cópia pública é uma etapa separada que exige autorização.

Até concluir essa migração, fotos antigas continuam com sua política anterior. Não afirmar que todo acervo ficou privado só porque arquivos novos mudaram de disco.

## Alternativa: fotos públicas por link

Preservar /storage para arquivos locais, informar visibilidade ao enviar e revisar nomes, tipos, exclusão e cache. Não usar essa alternativa para documentos privados nem alegar autorização por usuário sobre o arquivo. Compartilhamento público permanece uma decisão explícita do produto.

## Evidência e decisão

A correção funcional do router foi validada com arquivos fictícios; nenhuma foto real foi migrada. O usuário escolheu explicitamente em 16/10/2026: “Públicas por link, como hoje”. A política pública existente será preservada; não há migração privada pendente de aprovação. As demais etapas da auditoria podem continuar independentemente.
