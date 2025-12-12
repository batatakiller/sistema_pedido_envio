<?php
// ====================================================================
// ARQUIVO: validate_keys.php (CORRIGIDO E COM DEBUG)
// ====================================================================

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300); 
header('Content-Type: application/json');

$supabaseUrl = 'https://qoobmxjzcjtkpezajbbv.supabase.co'; 
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8'; 

// CHAVE CORRIGIDA (Com o "U" no final)
$pidKeyApiKey = 'iTLb1jblUOgVZwws8Ks5ie5nU'; 

// --- Função Auxiliar Supabase ---
function sbRequest($url, $method, $data = null, $key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [ "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json" ];
    if ($method === 'DELETE') $headers[] = "Prefer: return=minimal";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

try {
    // 1. Buscar chaves 'available' no banco
    $urlGet = "$supabaseUrl/rest/v1/license_keys?select=id,license_key&status=eq.available&limit=20"; // Testando com 20 para ser rápido
    $keysDB = sbRequest($urlGet, 'GET', null, $supabaseAnonKey);

    if (empty($keysDB)) {
        echo json_encode(['success' => true, 'message' => 'Nenhuma chave disponível para validar.', 'removed_count' => 0]);
        exit;
    }

    $mapIdByKey = [];
    $keysList = [];
    
    foreach ($keysDB as $k) {
        $cleanKey = trim($k['license_key']);
        if(empty($cleanKey)) continue;
        $keysList[] = $cleanKey;
        $mapIdByKey[$cleanKey] = $k['id'];
    }

    // A API pede quebra de linha \r\n entre as chaves
    $keysString = implode("\r\n", $keysList);

    // 2. Chamar API PidKey
    $apiUrl = "https://pidkey.com/ajax/pidms_api";
    $params = [
        'keys' => $keysString,
        'justgetdescription' => 0,
        'apikey' => $pidKeyApiKey
    ];
    
    // Monta URL
    $fullUrl = $apiUrl . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignora validação SSL para evitar erros locais
    
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Erro de conexão cURL: $curlError");
    }

    // Tenta decodificar o JSON
    $results = json_decode($apiResponse, true);
    
    // DEBUG: Se não for JSON válido, mostra o que veio (HTML, Texto, etc)
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Limita o tamanho da mensagem de erro para não poluir
        $rawContent = substr(strip_tags($apiResponse), 0, 300); 
        throw new Exception("A API não retornou JSON válido. Retorno: " . $rawContent);
    }

    // 3. Processar Retorno
    $idsToRemove = [];
    $detailsLog = [];

    // Normaliza para array (caso a API retorne um único objeto)
    $itemsToCheck = is_array($results) ? $results : [$results];

    foreach ($itemsToCheck as $item) {
        $keyVal = $item['Key'] ?? $item['Product_Key'] ?? null;
        $errCode = $item['ErrorCode'] ?? $item['Act_Config_ID'] ?? '';

        if (!$keyVal) continue;

        // Códigos para REMOVER
        // 0xC004C060 = Bloqueada
        // 0xC004C003 = Bloqueada
        if (stripos($errCode, '0xC004C060') !== false || stripos($errCode, '0xC004C003') !== false) {
            if (isset($mapIdByKey[$keyVal])) {
                $idsToRemove[] = $mapIdByKey[$keyVal];
                $detailsLog[] = "$keyVal ($errCode)";
            }
        }
    }

    // 4. Remover do Banco
    $removedCount = 0;
    if (!empty($idsToRemove)) {
        $idsString = implode(',', $idsToRemove);
        $urlDelete = "$supabaseUrl/rest/v1/license_keys?id=in.($idsString)";
        sbRequest($urlDelete, 'DELETE', null, $supabaseAnonKey);
        $removedCount = count($idsToRemove);
    }

    echo json_encode([
        'success' => true, 
        'message' => "Verificado com sucesso! $removedCount chaves inválidas removidas.",
        'details' => $detailsLog
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>