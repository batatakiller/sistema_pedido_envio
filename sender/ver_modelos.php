<?php
// ver_modelos.php

// --- COLOQUE SUA API KEY AQUI ---
define('MINHA_KEY', 'AIzaSyBSmCi9-Qg3jI0s4jNdnFw_1wKkNu-bs6Y'); 
// --------------------------------

if (MINHA_KEY === 'COLE_SUA_KEY_AQUI') {
    die("<h3>Erro: Edite o arquivo e coloque sua API KEY na linha 5.</h3>");
}

// Endpoint para listar modelos
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . MINHA_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$json = json_decode($result, true);

echo "<h2>Modelos Disponíveis para sua Chave:</h2>";

if (isset($json['models'])) {
    echo "<ul>";
    $encontrou = false;
    foreach ($json['models'] as $model) {
        // Filtra apenas modelos que geram texto (generateContent)
        if (in_array('generateContent', $model['supportedGenerationMethods'])) {
            // Remove o prefixo 'models/' para ficar fácil de copiar
            $nomeLimpo = str_replace('models/', '', $model['name']);
            echo "<li><strong>" . $nomeLimpo . "</strong> <span style='color:green'>(Compatível)</span></li>";
            $encontrou = true;
        }
    }
    echo "</ul>";
    
    if (!$encontrou) {
        echo "<p style='color:red'>Nenhum modelo de geração de texto encontrado. Sua chave pode estar restrita.</p>";
    } else {
        echo "<p style='background:#eee; padding:10px;'>Copie um dos nomes acima (ex: <code>gemini-1.5-flash-001</code>) e coloque no seu <strong>backend.php</strong>.</p>";
    }

} else {
    echo "<pre>" . print_r($json, true) . "</pre>";
}
?>