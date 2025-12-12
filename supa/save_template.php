<?php
// supa/save_template.php
// FUNÇÃO: Atualizar o conteúdo de um template no Supabase

header('Content-Type: application/json; charset=utf-8');

// 1. Segurança e Config
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    echo json_encode(['success' => false, 'message' => 'Config não encontrado.']);
    exit;
}

if (!defined('SB_URL') || !defined('SB_KEY')) {
    echo json_encode(['success' => false, 'message' => 'Chaves do Supabase não configuradas.']);
    exit;
}

// 2. Recebe Dados
$input = json_decode(file_get_contents('php://input'), true);
$slug = $input['slug'] ?? '';
$content = $input['content'] ?? '';

if (empty($slug) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Slug ou conteúdo vazio.']);
    exit;
}

// 3. Atualiza no Supabase
$url = SB_URL . "/rest/v1/message_templates?slug=eq." . urlencode($slug);

$data = ['content' => $content];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . SB_KEY,
    "Authorization: Bearer " . SB_KEY,
    "Content-Type: application/json",
    "Prefer: return=minimal" // Não precisa retornar o objeto, só status 204
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'message' => 'Erro Curl: ' . $err]);
} else {
    // 200 ou 204 significa sucesso no Supabase
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true, 'message' => 'Modelo atualizado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => "Erro Supabase ($httpCode): $response"]);
    }
}
?>