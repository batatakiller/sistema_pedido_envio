<?php
// ====================================================================
// ARQUIVO: supa/update_process.php
// VERSÃO: Lógica de Contador Personalizada (Incrementa só na Confirmação)
// ====================================================================

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// 1. Config
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    echo json_encode(['success' => false, 'message' => 'Erro: config.php ausente.']);
    exit;
}

if (!defined('SB_URL') || !defined('SB_KEY')) {
    echo json_encode(['success' => false, 'message' => 'Chaves do Banco ausentes.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['order_id'] ?? '';
$column = $input['column'] ?? ''; 
$value = $input['value']; 

// Flags de controle
$incrementCounter = $input['increment_counter'] ?? false;
$noIncrement = $input['no_increment'] ?? false;

if (empty($orderId) || empty($column)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

// 2. Lógica de Incremento
// Regra: Incrementa se o front pedir (increment_counter) OU se for um envio padrão, 
// DESDE QUE não tenha a flag de bloqueio (no_increment).
$isEnvioColumn = ($column === 'enviado_whatsapp' || $column === 'enviado_transportadora');
$shouldIncrement = ($incrementCounter || ($isEnvioColumn && $value === true)) && !$noIncrement;

$currentCount = 0;
$data = [];

// 3. Busca contador atual (se for incrementar)
if ($shouldIncrement) {
    $chGet = curl_init(SB_URL . "/rest/v1/amazon_orders_raw?order-id=eq." . urlencode($orderId) . "&select=whatsapp_sent_count");
    curl_setopt($chGet, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chGet, CURLOPT_HTTPHEADER, ["apikey: " . SB_KEY, "Authorization: Bearer " . SB_KEY]);
    curl_setopt($chGet, CURLOPT_SSL_VERIFYPEER, false);
    
    $resGet = curl_exec($chGet);
    curl_close($chGet);
    
    $jsonGet = json_decode($resGet, true);
    if (!empty($jsonGet) && isset($jsonGet[0])) {
        $currentCount = (int)($jsonGet[0]['whatsapp_sent_count'] ?? 0);
    }
    
    $data['whatsapp_sent_count'] = $currentCount + 1;
}

// 4. Dados a atualizar
if ($column !== null && $value !== null) {
    $data[$column] = $value;
}

if (!empty($input['new_phone'])) {
    $data['buyer-phone-number'] = preg_replace('/[^0-9]/', '', $input['new_phone']);
}

if (empty($data)) {
    echo json_encode(['success' => true, 'message' => 'Nada a atualizar.']);
    exit;
}

// 5. Envia Patch
$url = SB_URL . "/rest/v1/amazon_orders_raw?order-id=eq." . urlencode($orderId);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . SB_KEY,
    "Authorization: Bearer " . SB_KEY,
    "Content-Type: application/json",
    "Prefer: return=minimal"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http >= 200 && $http < 300) {
    // Marca chaves como usadas (apenas se for entrega de chaves real)
    if ($column === 'enviado_whatsapp' && $value === true && !$noIncrement && !empty($input['used_keys'])) {
        foreach ($input['used_keys'] as $k) {
            $ch2 = curl_init(SB_URL . "/rest/v1/rpc/mark_key_used");
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['p_license_key' => $k, 'p_order_id' => $orderId]));
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ["apikey: ".SB_KEY, "Authorization: Bearer ".SB_KEY, "Content-Type: application/json"]);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch2);
            curl_close($ch2);
        }
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => "Erro Supabase ($http)"]);
}
?>