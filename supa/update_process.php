<?php
// ====================================================================
// ARQUIVO: update_process.php (ATUALIZADO)
// ====================================================================

// Desativa erros HTML para não quebrar o JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Configurações
    $supabaseUrl = 'https://qoobmxjzcjtkpezajbbv.supabase.co'; 
    $supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8'; 

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Payload inválido.');

    $orderId = $input['order_id'] ?? '';
    $column = $input['column'] ?? ''; 
    $value = $input['value']; 
    $usedKeys = $input['used_keys'] ?? [];
    
    // NOVO: Recebe o telefone corrigido (opcional)
    $newPhone = $input['new_phone'] ?? null;

    if (empty($orderId) || empty($column)) {
        throw new Exception('Dados incompletos.');
    }

    function supabaseRequest($url, $method, $data, $key) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: {$key}", "Authorization: Bearer {$key}", "Content-Type: application/json", "Prefer: return=minimal"
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'response' => $res];
    }

    // 1. Preparar dados para atualização do pedido
    $updateData = [$column => $value];
    
    // SE um novo telefone foi enviado, adiciona à lista de atualização
    if (!empty($newPhone)) {
        // Remove caracteres não numéricos para salvar limpo no banco
        $cleanPhoneDB = preg_replace('/[^0-9]/', '', $newPhone);
        $updateData['buyer-phone-number'] = $cleanPhoneDB;
    }

    // Atualizar Pedido
    $urlOrder = "{$supabaseUrl}/rest/v1/amazon_orders_raw?order-id=eq." . urlencode($orderId);
    $resOrder = supabaseRequest($urlOrder, 'PATCH', $updateData, $supabaseAnonKey);

    if ($resOrder['code'] >= 300) {
        throw new Exception("Erro ao atualizar pedido: " . $resOrder['response']);
    }

    // 2. Processar Chaves (se necessário)
    if (!empty($usedKeys) && $value === true && ($column === 'enviado_whatsapp' || $column === 'enviado_transportadora')) {
        foreach ($usedKeys as $keyRaw) {
            $cleanKey = trim($keyRaw);
            if(empty($cleanKey)) continue;

            $urlRpc = "{$supabaseUrl}/rest/v1/rpc/mark_key_used";
            $dataRpc = [
                'p_license_key' => $cleanKey,
                'p_order_id' => (string)$orderId
            ];
            supabaseRequest($urlRpc, 'POST', $dataRpc, $supabaseAnonKey);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>