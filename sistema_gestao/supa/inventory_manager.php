<?php
// inventory_manager.php
header('Content-Type: application/json');
$supabaseUrl = 'https://qoobmxjzcjtkpezajbbv.supabase.co'; 
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8'; 

$action = $_GET['action'] ?? '';

function sbRequest($url, $method, $data = null, $key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json", "Prefer: return=minimal"]);
    if($method == 'POST') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST'); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $resp = curl_exec($ch); curl_close($ch); return $resp;
}

// 1. OBTER CONTAGEM (GET)
if ($action === 'count') {
    // Busca todas as chaves 'available'
    $url = "$supabaseUrl/rest/v1/license_keys?select=product_name&status=eq.available";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $supabaseAnonKey", "Authorization: Bearer $supabaseAnonKey"]);
    $response = curl_exec($ch); curl_close($ch);
    
    $data = json_decode($response, true);
    $counts = [];
    if(is_array($data)) {
        foreach($data as $row) {
            $prod = $row['product_name'];
            if(!isset($counts[$prod])) $counts[$prod] = 0;
            $counts[$prod]++;
        }
    }
    echo json_encode(['success'=>true, 'data'=>$counts]);
    exit;
}

// 2. ADICIONAR CHAVES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $product = $input['product'] ?? '';
    $rawKeys = $input['keys'] ?? '';

    if(!$product || !$rawKeys) { echo json_encode(['success'=>false, 'message'=>'Dados inválidos']); exit; }

    // Separa por quebra de linha
    $keysArray = explode("\n", $rawKeys);
    $insertData = [];
    
    foreach($keysArray as $k) {
        $k = trim($k);
        if(!empty($k)) {
            $insertData[] = [
                'product_name' => $product,
                'license_key' => $k,
                'status' => 'available'
            ];
        }
    }

    if(count($insertData) > 0) {
        $url = "$supabaseUrl/rest/v1/license_keys";
        sbRequest($url, 'POST', $insertData, $supabaseAnonKey);
        echo json_encode(['success'=>true, 'count'=>count($insertData)]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Nenhuma chave válida encontrada']);
    }
    exit;
}
?>