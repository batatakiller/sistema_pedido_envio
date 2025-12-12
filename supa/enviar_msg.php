<?php
// supa/enviar_msg.php
// ATUALIZADO: Suporte a envio direto (sem reescrever novamente)

header('Content-Type: application/json; charset=utf-8');

if (file_exists('../config.php')) require_once '../config.php';

// Validações Básicas
if (!defined('UAZAPI_TOKEN')) exit(json_encode(['success'=>false, 'message'=>'Erro Config']));

$input = json_decode(file_get_contents('php://input'), true);
$phoneRaw = $input['phone'] ?? '';
$messageText = $input['text'] ?? '';
$skipAI = $input['skip_ai'] ?? false; // NOVO PARAMETRO

if (empty($phoneRaw) || empty($messageText)) exit(json_encode(['success'=>false, 'message'=>'Dados incompletos']));

// Formata Telefone
$phone = preg_replace('/[^0-9]/', '', $phoneRaw);
if (strlen($phone) >= 10 && strlen($phone) <= 11) $phone = '55' . $phone;

$aiUsed = false;

// SÓ CHAMA A IA SE O USUÁRIO NÃO TIVER GERADO PREVIEW (skip_ai = false)
if (!$skipAI && defined('GEMINI_KEY') && !empty(GEMINI_KEY)) {
    $urlIA = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . GEMINI_KEY;
    $prompt = "Atue como suporte. Reescreva para WhatsApp (profissional e direto). Mantenha os dados. Retorne APENAS o texto.\n\nOriginal: \"$messageText\"";
    
    $ch = curl_init($urlIA);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["contents" => [["parts" => [["text" => $prompt]]]]]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 8
    ]);
    $resIA = curl_exec($ch);
    curl_close($ch);
    
    $jsonIA = json_decode($resIA, true);
    if (isset($jsonIA['candidates'][0]['content']['parts'][0]['text'])) {
        $messageText = trim($jsonIA['candidates'][0]['content']['parts'][0]['text']);
        $aiUsed = true;
    }
}

// Envio UAZAPI
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => UAZAPI_URL,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode(['number' => $phone, 'text' => $messageText], JSON_UNESCAPED_UNICODE),
  CURLOPT_HTTPHEADER => ["Content-Type: application/json; charset=utf-8", "token: " . UAZAPI_TOKEN],
  CURLOPT_SSL_VERIFYPEER => false
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http >= 200 && $http < 300) {
    $msg = 'Enviado!';
    if ($skipAI) $msg .= ' (Texto Aprovado ✅)';
    elseif ($aiUsed) $msg .= ' (IA Automática 🤖)';
    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro API Zap: ' . $res]);
}
?>