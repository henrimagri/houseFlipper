# Banco de dados

O arquivo `houseflipper-schema.sql` contém somente a estrutura do banco MySQL,
sem os dados locais da aplicação.

Para importar a estrutura:

```bash
mysql -u SEU_USUARIO -p NOME_DO_BANCO < database/dumps/houseflipper-schema.sql
```

Como alternativa, configure o `.env` e execute as migrations e os seeders:

```bash
php artisan migrate --seed
```

Arquivos terminados em `.private` são dumps locais com dados e não devem ser
versionados.
