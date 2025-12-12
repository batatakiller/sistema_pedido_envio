<?php
// teste_ia.php

// --- SUA API KEY ---
define('MINHA_KEY', 'AIzaSyBSmCi9-Qg3jI0s4jNdnFw_1wKkNu-bs6Y'); 
// -------------------

// Lista completa solicitada
$modelos = [
    'gemini-2.5-flash',
    'gemini-2.5-pro',
    'gemini-2.0-flash-exp',
    'gemini-2.0-flash',
    'gemini-2.0-flash-001',
    'gemini-2.0-flash-exp-image-generation',
    'gemini-2.0-flash-lite-001',
    'gemini-2.0-flash-lite',
    'gemini-2.0-flash-lite-preview-02-05',
    'gemini-2.0-flash-lite-preview',
    'gemini-exp-1206',
    'gemini-2.5-flash-preview-tts',
    'gemini-2.5-pro-preview-tts',
    'gemma-3-1b-it',
    'gemma-3-4b-it',
    'gemma-3-12b-it',
    'gemma-3-27b-it',
    'gemma-3n-e4b-it',
    'gemma-3n-e2b-it',
    'gemini-flash-latest',
    'gemini-flash-lite-latest',
    'gemini-pro-latest',
    'gemini-2.5-flash-lite',
    'gemini-2.5-flash-image-preview',
    'gemini-2.5-flash-image',
    'gemini-2.5-flash-preview-09-2025',
    'gemini-2.5-flash-lite-preview-09-2025',
    'gemini-3-pro-preview',
    'gemini-3-pro-image-preview',
    'nano-banana-pro-preview',
    'gemini-robotics-er-1.5-preview',
    'gemini-2.5-computer-use-preview-10-2025'
];

echo "<h3>Relatório de Teste Gemini</h3>";
echo "<div style='font-family: monospace; font-size: 14px;'>";

// Desativa SSL para evitar erro de certificado local
$sslVerify = false; 

foreach ($modelos as $modelo) {
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$modelo:generateContent?key=" . MINHA_KEY;

    $data = [ "contents" => [[ "parts" => [["text" => "Oi"]] ]] ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout rápido de 3s

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($result, true);

    // Verificação simples: Se HTTP 200 e tem candidatos, funcionou.
    if ($httpCode == 200 && isset($json['candidates'])) {
        echo "<div style='color:green; margin-bottom:4px;'>$modelo (FUNCIONOU)</div>";
    } else {
        // Opcional: Mostra erro se quiser, mas o pedido foi simples
        // $msg = isset($json['error']['message']) ? $json['error']['message'] : "Erro $httpCode";
        echo "<div style='color:red; margin-bottom:4px;'>$modelo (FALHOU)</div>";
    }

    // Tenta forçar o navegador a mostrar linha por linha enquanto carrega
    if(ob_get_level() > 0) { ob_flush(); }
    flush();
}

echo "</div>";
?>