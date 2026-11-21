<?php
require 'auth.php';
requireRole(['cozinha','admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

date_default_timezone_set('America/Bahia');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Janela auxiliar - Impressão automática da cozinha</title>
<style>
body{
    margin:0;
    background:#121212;
    color:#fff;
    font-family:Arial,sans-serif;
    display:flex;
    min-height:100vh;
    align-items:center;
    justify-content:center;
    padding:18px;
    box-sizing:border-box;
}
.card{
    width:min(100%,430px);
    background:#fff;
    color:#111;
    border-radius:18px;
    padding:22px;
    box-shadow:0 16px 44px rgba(0,0,0,.35);
}
h1{
    font-size:20px;
    margin:0 0 10px;
}
p{
    font-size:14px;
    line-height:1.5;
    margin:8px 0;
}
.status{
    margin-top:14px;
    border-radius:12px;
    background:#fff3cd;
    color:#664d03;
    padding:12px;
    font-weight:700;
}
small{
    display:block;
    margin-top:14px;
    color:#555;
    line-height:1.45;
}
</style>
</head>
<body>
<div class="card">
    <h1>🖨️ Impressão automática da cozinha pronta</h1>
    <p>Esta janela foi aberta para receber os cupons automaticamente quando chegar item novo no painel da cozinha.</p>
    <p>Ela será reutilizada pelo sistema e, no momento da impressão, exibirá somente o cupom do item novo recebido.</p>
    <div class="status">Mantenha esta janela aberta.</div>
    <small>Ao fechar esta janela, a autoimpressão será desarmada no painel e precisará ser ativada novamente.</small>
</div>
</body>
</html>
