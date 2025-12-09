<?php
// buscar.php

if (!isset($_POST['search']) || empty($_POST['search'])) {
    die("Termo de busca vazio.");
}

// 1. Configurações Manuais (COLE AQUI O QUE COPIOU DO NAVEGADOR)
// DICA: Mantenha as aspas simples.
$cookie_string = 'cole_aqui_o_conteudo_inteiro_do_cookie_que_copiou_do_f12';
$user_agent    = 'cole_aqui_o_user_agent_exato';

// URL alvo com o termo
$termo = urlencode($_POST['search']);
$url = "https://app.workconsultoria.com/search?module=cpf_completa&q=" . $termo;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Necessário na Hostinger as vezes
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Cabeçalhos para "enganar" a Cloudflare
$headers = [
    'Authority: app.workconsultoria.com',
    'Upgrade-Insecure-Requests: 1',
    'User-Agent: ' . $user_agent,
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Sec-Fetch-Site: same-origin',
    'Sec-Fetch-Mode: navigate',
    'Sec-Fetch-User: ?1',
    'Sec-Fetch-Dest: document',
    'Referer: https://app.workconsultoria.com/',
    'Cookie: ' . $cookie_string
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Tenta fazer a requisição mascarada
$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Exibição
?>
<!DOCTYPE html>
<html lang="pt-br">
<body style="font-family: monospace; padding: 20px;">
    <h2>Status HTTP: <?php echo $httpCode; ?></h2>
    
    <?php if ($httpCode == 403 || $httpCode == 503): ?>
        <div style="background: #ffcccc; padding: 15px;">
            <strong>Bloqueado pela Cloudflare/Login.</strong><br>
            Provavelmente o Cookie `cf_clearance` ou a sessão expirou.<br>
            Você precisa logar novamente no seu PC e atualizar a variável $cookie_string.
        </div>
    <?php else: ?>
        <div style="border: 1px solid #ccc; padding: 10px;">
            <?php 
                // Tenta limpar o HTML e mostrar só o texto ou JSON
                echo htmlentities(substr($resposta, 0, 2000)) . "..."; 
            ?>
        </div>
    <?php endif; ?>
    <br><a href="index.php">Voltar</a>
</body>
</html>