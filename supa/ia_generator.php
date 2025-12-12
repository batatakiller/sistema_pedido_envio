<?php
// supa/ia_generator.php
// Objetivo: Apenas gerar o texto com IA para preview

header('Content-Type: application/json; charset=utf-8');

// 1. Configurações
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    echo json_encode(['success' => false, 'message' => 'Config não encontrado.']);
    exit;
}

if (!defined('GEMINI_KEY') || empty(GEMINI_KEY)) {
    echo json_encode(['success' => false, 'message' => 'Chave Gemini não configurada.']);
    exit;
}

// 2. Recebe Texto Original
$input = json_decode(file_get_contents('php://input'), true);
$textoOriginal = $input['text'] ?? '';

if (empty($textoOriginal)) {
    echo json_encode(['success' => false, 'message' => 'Texto vazio.']);
    exit;
}

// 3. Chama Gemini
$urlIA = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . GEMINI_KEY;

$prompt = "Atue como um atendente de suporte. Reescreva a mensagem abaixo para WhatsApp de forma cordial, profissional e direta. Mantenha os dados importantes. Retorne APENAS o texto final.\n\nMensagem Original: \"$textoOriginal\"";

$dataIA = ["contents" => [["parts" => [["text" => $prompt]]]]];

$ch = curl_init($urlIA);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataIA));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$resIA = curl_exec($ch);
$errIA = curl_error($ch);
curl_close($ch);

if ($errIA) {
    echo json_encode(['success' => false, 'message' => 'Erro Conexão IA: ' . $errIA]);
} else {
    $jsonIA = json_decode($resIA, true);
    if (isset($jsonIA['candidates'][0]['content']['parts'][0]['text'])) {
        $textoIA = trim($jsonIA['candidates'][0]['content']['parts'][0]['text']);
        $textoIA = trim($textoIA, '"\''); // Remove aspas extras
        echo json_encode(['success' => true, 'ai_text' => $textoIA]);
    } else {
        echo json_encode(['success' => false, 'message' => 'IA não retornou texto válido.']);
    }
}
?>