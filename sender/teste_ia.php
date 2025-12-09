<?php
// teste_ia.php

// --- COLOQUE SUA API KEY AQUI DENTRO DAS ASPAS ---
define('MINHA_KEY', 'AIzaSyBSmCi9-Qg3jI0s4jNdnFw_1wKkNu-bs6Y'); 
// --------------------------------------------------

echo "<h2>Diagnóstico de Conexão com Gemini</h2>";

if (MINHA_KEY === 'COLE_SUA_KEY_AQUI') {
    die("<h3 style='color:red'>ERRO: Você esqueceu de colocar a API Key na linha 3 deste arquivo!</h3>");
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-3-12b-it:generateContent?key=' . MINHA_KEY;

$data = [
    "contents" => [[
        "parts" => [["text" => "Responda apenas com a palavra: FUNCIONOU"]]
    ]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desativa verificação SSL temporariamente para teste

$result = curl_exec($ch);
$erroCurl = curl_error($ch);
curl_close($ch);

if ($erroCurl) {
    echo "<p style='color:red'><strong>ERRO DE CONEXÃO (cURL):</strong> $erroCurl</p>";
    echo "<p>Isso geralmente é bloqueio da Hostinger.</p>";
} else {
    $json = json_decode($result, true);
    
    if (isset($json['error'])) {
        echo "<p style='color:red'><strong>ERRO DA API GOOGLE:</strong></p>";
        echo "<pre>" . print_r($json['error'], true) . "</pre>";
        echo "<p>Verifique se sua API Key está ativa e se você habilitou o faturamento (se necessário).</p>";
    } else if (isset($json['candidates'])) {
        echo "<h3 style='color:green'>SUCESSO! A IA RESPONDEU:</h3>";
        echo "<div style='background:#dfd; padding:10px; border:1px solid green'>" . $json['candidates'][0]['content']['parts'][0]['text'] . "</div>";
        echo "<p>Agora copie a API KEY deste arquivo e coloque no <strong>backend.php</strong> com cuidado.</p>";
    } else {
        echo "<p style='color:orange'>Retorno desconhecido:</p>";
        echo "<pre>" . $result . "</pre>";
    }
}
?>