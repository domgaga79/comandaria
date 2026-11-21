# Comandaria

Sistema PHP de atendimento, pedidos por QR Code, cozinha e caixa.

## Instalação

1. Instale PHP com MySQLi e Composer.
2. Execute `composer install` na raiz.
3. Configure DB_HOST, DB_USER, DB_PASSWORD e DB_NAME no ambiente do PHP. Configure um ambiente separado para a instalação legada em burguershake/ se ela usar outro banco.
4. Use um banco da Comandaria já migrado. Para o login Google, configure GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET. A variável GOOGLE_REDIRECT_URI permite informar o callback explicitamente.
5. Configure o servidor web para usar as regras de roteamento e proteção dos arquivos .htaccess.

Os backups e as migrações específicas da instalação original contêm dados operacionais e ficam armazenados separadamente.
