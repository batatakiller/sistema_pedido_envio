<?php
// fetch_assigned_keys.php
header('Content-Type: application/json');

$supabaseUrl = 'https://qoobmxjzcjtkpezajbbv.supabase.co'; 
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8'; 

$input = json_decode(file_get_contents('php://input'), true);
$orderIds = $input['order_ids'] ?? [];

if (empty($orderIds)) {
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

// Formata para o filtro "in" do Supabase: (id1,id2,id3)
$idsString = implode(',', $orderIds);

// Busca chaves onde used_by_order_id está na lista
$url = "{$supabaseUrl}/rest/v1/license_keys?select=license_key,used_by_order_id&used_by_order_id=in.({$idsString})";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: {$supabaseAnonKey}",
    "Authorization: Bearer {$supabaseAnonKey}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$keysData = json_decode($response, true);
$mappedKeys = [];

// Agrupa chaves por pedido (caso tenha comprado mais de uma unidade)
if (is_array($keysData)) {
    foreach ($keysData as $row) {
        $oid = $row['used_by_order_id'];
        if (!isset($mappedKeys[$oid])) {
            $mappedKeys[$oid] = [];
        }
        $mappedKeys[$oid][] = $row['license_key'];
    }
}

echo json_encode(['success' => true, 'data' => $mappedKeys]);
?>