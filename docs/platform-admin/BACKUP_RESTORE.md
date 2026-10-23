# Backup e restore drill

Estado atual: **não configurado**. Último backup e última simulação: não medidos. Não há integração com catálogo verificável de backups, portanto o checklist não aprova este item. Nenhum restore foi executado nesta fase.

## Procedimento de homologação isolada

1. O responsável pela infraestrutura provisiona um schema descartável com nome exclusivo de teste e conta sem privilégios em produção. Confirmar host, SELECT DATABASE(), permissões e ambiente antes de importar. Não reutilizar .env ou conexão de produção.
2. Gerar backup de uma base de fixtures autorizadas sem dados de clientes. Registrar data UTC, versão do servidor, versão das migrations, quantidade de tabelas/registros, tamanho e SHA-256. Cifrar o arquivo e guardar fora do repositório. Credenciais devem ficar em secret manager ou arquivo privado, nunca em argumento de processo/log.
3. Restaurar somente no schema isolado; restaurar arquivos em storage privado de teste com raiz separada. Nenhum comando automático é autorizado a apontar para produção.
4. Executar sanity checks de migrations, integridade referencial, contagens, acesso entre tenants, três produtos, login/MFA e abertura de um artefato de fixture. Conferir hash dos arquivos restaurados e acesso negado sem autorização.
5. Registrar tempo real de backup/restore, tamanho, erros, cobertura e responsável. O resultado mede aquele exercício, não disponibilidade histórica ou SLA. Manter evidência sanitizada, sem SQL de clientes, chaves, tokens ou caminhos privados.
6. Revisar descarte pelo procedimento autorizado da infraestrutura. Não apagar automaticamente backups ou arquivos originais.

Um ensaio SQLite não substitui restore de MySQL com a mesma versão, collation, SQL modes e estratégia de storage da produção. Aprovação exige fonte real do backup e exercício documentado; a mera existência deste roteiro não muda o gate.
