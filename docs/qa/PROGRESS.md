# Progresso

- Etapa atual: Aceternity 3D Globe parado e centralizado no Brasil, com rotação apenas por arraste, marcadores alinhados, tooltips nítidos e escudo visual validado nos dois temas.
- Cobertura: 112 rotas frontend e 185 rotas API inventariadas.
- Entregas: cadastro completo com CEP, confirmação por Resend quando configurado, perfil/segurança/preferências, tema global, ProductSwitcher no avatar, tutorial, diálogos acessíveis, CRM/clientes, contratos persistentes, mapas, catálogo oficial de leilões e redesign premium responsivo.
- Catálogo público: páginas de funcionalidades e preços redesenhadas com ícones, contraste corrigido, ciclo trimestral com 10% de desconto, anual com 20%, preços e economias recalculados, além do teste de 7 dias no frontend e backend. Contas pendentes de confirmação não acessam rotas `/app`.
- Redesign atual: prévias distintas por produto; mapa real do Brasil com 27 estados/capitais e comunicação animada nas conexões; Aceternity 3D Globe no padrão shadcn com Brasil em foco e 27 capitais; seção nacional refinada; autenticação no shell premium.
- Testes: backend 15/15 (75 asserções), frontend 7/7, `suite:verify` 23/23, build Vite e auditoria npm sem vulnerabilidades.
- Evidências: regressão de navegador 232/232, matriz premium 15/15, adendo 7/7, Brasil/autenticação 21/21 e catálogo público 6/6 em `comprehensive-results.json`, `premium-results.json`, `artifacts/adendo/adendo-results.json`, `brasil-auth-results.json` e `artifacts/public-catalog-redesign/results.json`.
- Pendência técnica não bloqueante: aviso do Vite para chunks WebGL/MapLibre maiores que 500 kB; os recursos seguem lazy-loaded.
