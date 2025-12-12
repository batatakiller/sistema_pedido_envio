<?php
// ====================================================================
// ARQUIVO: import_processor.php (VERIFICAÇÃO ANTI-DUPLICIDADE)
// ====================================================================

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(300);

$supabaseUrl = 'https://qoobmxjzcjtkpezajbbv.supabase.co'; 
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8'; 

// Função Auxiliar para GET (Consultar existentes)
function checkExistingIds($url, $key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

// Função Auxiliar para POST (Inserir novos)
function insertBatch($url, $data, $key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json", "Prefer: return=minimal"
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['txtFile'])) {
    try {
        $storeName = $_POST['store_name'] ?? 'Loja Padrão';
        $fileTmp = $_FILES['txtFile']['tmp_name'];

        if (!file_exists($fileTmp)) throw new Exception("Arquivo não recebido.");

        // 1. Processamento do Arquivo (Igual ao n8n)
        $content = file_get_contents($fileTmp);
        
        // Remove BOM e converte Encoding
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); 
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $rawLines = explode("\n", $content);

        // Lógica de juntar linhas quebradas (O nome do produto quebrava no seu arquivo)
        $cleanLines = [];
        $currentBuffer = "";
        
        // Pula cabeçalho
        $headersLine = array_shift($rawLines);

        foreach ($rawLines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Regex para ID Amazon (ex: 701-1234567-1234567)
            if (preg_match('/^\d{3}-\d{7}-\d{7}/', $line)) {
                if (!empty($currentBuffer)) $cleanLines[] = $currentBuffer;
                $currentBuffer = $line;
            } else {
                $currentBuffer .= " " . $line;
            }
        }
        if (!empty($currentBuffer)) $cleanLines[] = $currentBuffer;

        // Mapeamento e Limpeza
        $headersMap = [
            "order-id", "order-item-id", "purchase-date", "payments-date", "reporting-date", 
            "promise-date", "days-past-promise", "buyer-email", "buyer-name", 
            "payment-method-details", "cpf", "ship-county", "buyer-phone-number", "sku", 
            "product-name", "quantity-purchased", "quantity-shipped", "quantity-to-ship", 
            "ship-service-level", "recipient-name", "ship-address-1", "ship-address-2", 
            "ship-address-3", "ship-city", "ship-state", "ship-postal-code", "ship-country", 
            "payment-method", "cod-collectible-amount", "already-paid", "payment-method-fee", 
            "verge-of-cancellation", "verge-of-lateShipment"
        ];
        $numericFields = ["cod-collectible-amount", "already-paid", "payment-method-fee", "quantity-purchased"];

        $allData = [];

        foreach ($cleanLines as $line) {
            $cols = explode("\t", $line);
            if (count($cols) < 5) continue;

            $row = [];
            foreach ($headersMap as $index => $key) {
                $val = isset($cols[$index]) ? trim($cols[$index]) : null;
                if ($val) $val = str_replace('"', '', $val);

                if (in_array($key, $numericFields)) {
                    $val = ($val === "" || $val === null) ? 0 : floatval(str_replace(',', '.', $val));
                }
                if ($val === 'false') $val = false;
                if ($val === 'true') $val = true;

                $row[$key] = $val;
            }

            // Campos Fixos
            $row['nome_loja'] = $storeName; // Usa a loja selecionada no form
            $row['data_importacao'] = date('c');
            $row['enviado_whatsapp'] = false;
            $row['enviado_transportadora'] = false;
            $row['instalacao_ok'] = false;
            $row['pedir_nota'] = false;

            $allData[] = $row;
        }

        // 2. Filtragem e Inserção Inteligente
        $totalProcessed = 0;
        $totalInserted = 0;
        
        // Processa em lotes de 50 para não sobrecarregar URL
        $chunks = array_chunk($allData, 50);

        foreach ($chunks as $chunk) {
            // A. Extrai os IDs desse lote
            $idsToCheck = array_column($chunk, 'order-id');
            // Remove vazios
            $idsToCheck = array_filter($idsToCheck); 
            
            if (empty($idsToCheck)) continue;

            // B. Consulta no Banco quais já existem
            $idsString = implode(',', $idsToCheck);
            // URL encode é importante aqui
            $urlCheck = "$supabaseUrl/rest/v1/amazon_orders_raw?select=order-id&order-id=in.(" . urlencode($idsString) . ")";
            
            $existingRows = checkExistingIds($urlCheck, $supabaseAnonKey);
            
            // Cria um array simples só com os IDs existentes [id1, id2]
            $existingIds = [];
            if (is_array($existingRows)) {
                $existingIds = array_column($existingRows, 'order-id');
            }

            // C. Filtra o lote: Mantém apenas quem NÃO está na lista de existentes
            $newRows = [];
            foreach ($chunk as $item) {
                if (!in_array($item['order-id'], $existingIds)) {
                    $newRows[] = $item;
                }
            }

            // D. Insere apenas os novos
            if (!empty($newRows)) {
                $code = insertBatch("$supabaseUrl/rest/v1/amazon_orders_raw", $newRows, $supabaseAnonKey);
                if ($code >= 200 && $code < 300) {
                    $totalInserted += count($newRows);
                }
            }
            $totalProcessed += count($chunk);
        }

        $msg = "Processado! Arquivo lido: $totalProcessed linhas. Novos pedidos inseridos: $totalInserted. (Duplicados foram ignorados)";
        header("Location: index.php?msg=" . urlencode($msg));

    } catch (Exception $e) {
        header("Location: index.php?msg=Erro Fatal: " . urlencode($e->getMessage()));
    }
} else {
    header("Location: index.php");
}
?>