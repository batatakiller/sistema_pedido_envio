<?php
// get_keys.php
header('Content-Type: application/json');

$supabaseUrl = 'https://qoobmxjzcjtkpezajbbv.supabase.co'; 
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8'; 

$input = json_decode(file_get_contents('php://input'), true);
$amazonProductName = $input['product_name'] ?? '';
$quantity = intval($input['quantity'] ?? 1);

// --- Lógica de Mapeamento (Amazon -> Tabela license_keys) ---
// Você deve ajustar esses "IFs" conforme os nomes que você cadastrou na tabela license_keys
$dbProductName = '';

if (stripos($amazonProductName, '2024') !== false) {
    $dbProductName = 'Office 2024'; // Nome exato na tabela license_keys
} elseif (stripos($amazonProductName, '2021') !== false) {
    $dbProductName = 'Office 2021';
} elseif (stripos($amazonProductName, '2019') !== false) {
    $dbProductName = 'Office 2019';
} else {
    // Se não identificar, tenta buscar algo genérico ou retorna vazio
    echo json_encode(['success' => false, 'message' => 'Produto não mapeado automaticamente.']);
    exit;
}

// Busca chaves disponíveis (status = 'available')
// Limitamos à quantidade do pedido
$url = "{$supabaseUrl}/rest/v1/license_keys?select=license_key&status=eq.available&product_name=eq." . urlencode($dbProductName) . "&limit={$quantity}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: {$supabaseAnonKey}",
    "Authorization: Bearer {$supabaseAnonKey}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$keys = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && is_array($keys)) {
    // Retorna apenas as strings das chaves
    $cleanKeys = array_column($keys, 'license_key');
    echo json_encode(['success' => true, 'keys' => $cleanKeys, 'mapped_product' => $dbProductName]);
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhuma chave disponível encontrada.']);
}
?>