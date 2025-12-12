<?php
// ====================================================================
// ARQUIVO: supa/index.php
// VERSÃO: V6 (Manuais A4 Clean com Template + Contador V5 Mantido)
// ====================================================================

if (file_exists('../config.php')) {
    require_once '../config.php';
    $supabaseUrl = defined('SB_URL') ? SB_URL : 'https://qoobmxjzcjtkpezajbbv.supabase.co';
    $supabaseAnonKey = defined('SB_KEY') ? SB_KEY : 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8';
} else {
    die("Erro: config.php não encontrado.");
}
$tableName = 'amazon_orders_raw';

// --- Função Auxiliar API ---
function fetchSupabaseRaw($url, $key, $headersExtra = [], $method = 'GET') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); 
    $headers = array_merge([
        "apikey: {$key}", "Authorization: Bearer {$key}", "Content-Type: application/json"
    ], $headersExtra);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if($method === 'HEAD') curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header_text = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $contentRange = '';
    foreach (explode("\r\n", $header_text) as $i => $line) {
        if (stripos($line, 'Content-Range:') === 0) {
            $contentRange = trim(substr($line, 14));
        }
    }
    curl_close($ch);
    return ['body' => json_decode($body, true), 'range' => $contentRange];
}

// Filtros
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 100;
$offset = ($page - 1) * $limit;
$rangeStart = $offset;
$rangeEnd = $offset + $limit - 1;

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$filterZap = $_GET['filter_zap'] ?? '';      
$filterTransp = $_GET['filter_transp'] ?? ''; 
$filterInst = $_GET['filter_inst'] ?? '';    
$filterNota = $_GET['filter_nota'] ?? '';
$filterStore = $_GET['filter_store'] ?? '';     
$filterOrderId = $_GET['filter_order_id'] ?? ''; 
$filterClient = $_GET['filter_client'] ?? '';
$filterCancel = $_GET['filter_cancel'] ?? 'false'; 

$queryParams = "";
if (!empty($startDate) && !empty($endDate)) $queryParams .= "&data_importacao=gte.{$startDate}T00:00:00&data_importacao=lte.{$endDate}T23:59:59";
if ($filterZap === 'true') $queryParams .= "&enviado_whatsapp=eq.true";
if ($filterZap === 'false') $queryParams .= "&enviado_whatsapp=eq.false";
if ($filterTransp === 'true') $queryParams .= "&enviado_transportadora=eq.true";
if ($filterTransp === 'false') $queryParams .= "&enviado_transportadora=eq.false";
if ($filterInst === 'true') $queryParams .= "&instalacao_ok=eq.true";
if ($filterInst === 'false') $queryParams .= "&instalacao_ok=eq.false";
if ($filterNota === 'true') $queryParams .= "&pedir_nota=eq.true";
if ($filterNota === 'false') $queryParams .= "&pedir_nota=eq.false";
if (!empty($filterStore)) $queryParams .= "&nome_loja=eq." . urlencode($filterStore);
if (!empty($filterOrderId)) $queryParams .= "&order-id=eq." . urlencode($filterOrderId);
if (!empty($filterClient)) $queryParams .= "&buyer-name=ilike.*" . urlencode($filterClient) . "*";
if ($filterCancel === 'true') $queryParams .= "&status_cancelado=eq.true";
if ($filterCancel === 'false') $queryParams .= "&status_cancelado=eq.false";

$currentSort = $_GET['sort_order'] ?? 'desc';
$nextSort = ($currentSort === 'desc') ? 'asc' : 'desc';
$sortIcon = ($currentSort === 'desc') ? '⬇️' : '⬆️';

$urlOrders = "{$supabaseUrl}/rest/v1/{$tableName}?select=*{$queryParams}&order=data_importacao.{$currentSort}";
$result = fetchSupabaseRaw($urlOrders, $supabaseAnonKey, ["Range: $rangeStart-$rangeEnd", "Prefer: count=exact"]);
$orders = $result['body'];

$totalRecords = 0;
if ($result['range']) {
    $parts = explode('/', $result['range']);
    if (isset($parts[1]) && is_numeric($parts[1])) $totalRecords = (int)$parts[1];
}
$totalPages = ($totalRecords > 0) ? ceil($totalRecords / $limit) : 1;

$resTemplates = fetchSupabaseRaw("{$supabaseUrl}/rest/v1/message_templates?select=slug,name,content&order=name.asc", $supabaseAnonKey);
$templates = $resTemplates['body'];

$resStores = fetchSupabaseRaw("{$supabaseUrl}/rest/v1/{$tableName}?select=nome_loja&limit=1000&order=created_at.desc", $supabaseAnonKey);
$storeList = [];
if(is_array($resStores['body'])) {
    $allStores = array_column($resStores['body'], 'nome_loja');
    $storeList = array_unique($allStores);
}

function format_phone($phone) { return preg_replace('/[^0-9]/', '', $phone); }
function format_date($date) { 
    if (empty($date)) return '-';
    try { $d = new DateTime($date); $d->setTimezone(new DateTimeZone('America/Sao_Paulo')); return $d->format('d/m/Y H:i'); } catch (Exception $e) { return $date; }
}
function format_money($val) { return is_numeric($val) ? 'R$ ' . number_format((float)$val, 2, ',', '.') : '-'; }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão Completa de Pedidos</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f0f2f5; color: #333; font-size: 13px; }
        .container { max-width: 100%; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; color: #2c3e50; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: #fafafa; padding: 15px; border: 1px solid #eee; border-radius: 8px; }
        .card h3 { margin-top: 0; font-size: 14px; color: #555; border-bottom: 1px solid #ddd; padding-bottom: 5px; display:flex; justify-content:space-between; align-items:center; }
        #inventoryDisplay { display: flex; flex-wrap: wrap; gap: 10px; font-weight: bold; font-size: 14px; color: #2980b9; }
        .inv-item { background: #e8f6f3; padding: 5px 10px; border-radius: 4px; border: 1px solid #a2d9ce; }
        input, button, select, textarea { padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        button { cursor: pointer; font-weight: bold; }
        .btn-action { background: #00b894; color: white; border: none; }
        .btn-clear { background: #95a5a6; color: white; border: none; }
        .btn-danger-outline { background: transparent; border: 1px solid #e74c3c; color: #e74c3c; }
        .btn-danger-outline:hover { background: #e74c3c; color: white; }
        .top-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; align-items: flex-end; background: #fafafa; padding: 15px; border-radius: 5px; border: 1px solid #eee; }
        .filter-group { display: flex; flex-direction: column; gap: 3px; }
        .filter-group label { font-size: 11px; font-weight: bold; color: #7f8c8d; }
        .table-responsive { overflow-x: auto; margin-top:10px; border: 1px solid #eee; max-height: 70vh; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th { background: #2c3e50; color: white; position: sticky; top: 0; z-index: 100; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }
        tr.selected-row { background-color: #fff3cd !important; border-left: 4px solid #f1c40f; }
        tr.row-canceled { background-color: #ffebee !important; color: #999; }
        tr.row-canceled td { text-decoration: line-through; }
        tr.row-canceled td .badge, tr.row-canceled td input { text-decoration: none !important; opacity: 0.8; }
        th a { color: white !important; text-decoration: none; }
        th a:hover { text-decoration: underline; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.85em; color: white; font-weight:bold; display: inline-block; min-width: 30px; text-align: center; cursor: pointer; transition: 0.3s; }
        .badge-green { background: #27ae60; }
        .badge-disabled { background: #95a5a6; cursor: not-allowed; }
        .btn-toggle-red { background: #e74c3c; color: white; }
        .btn-toggle-green { background: #27ae60; color: white; }
        .btn-toggle-gray { background: #95a5a6; color: white; }
        .pagination { margin-top: 15px; display: flex; gap: 5px; justify-content: center; align-items: center; }
        .pagination a { padding: 8px 12px; background: #eee; text-decoration: none; color: #333; border-radius: 4px; }
        .pagination span { font-weight: bold; color: #555; }
        .modal-overlay { display: none; position: fixed !important; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 650px; max-width: 95%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); display: flex; flex-direction: column; gap: 10px; max-height: 90vh; overflow-y: auto;}
        .modal-header { display: flex; justify-content: space-between; font-weight: bold; font-size: 18px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .form-group { display: flex; flex-direction: column; gap: 3px; }
        textarea { resize: vertical; min-height: 100px; width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; font-family: sans-serif; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; border-top: 1px solid #eee; padding-top: 15px; }
        .btn-cancel { background: #95a5a6; color: white; border: none; padding: 10px 20px; border-radius: 5px; }
        .btn-just-confirm { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; }
        #manualPhoneInput { border: 2px solid #e74c3c; background: #fff5f5; color: #c0392b; font-weight: bold; width: 100%; }
        .key-input { margin-bottom: 5px; border: 1px solid #e74c3c; font-weight: bold; width: 100%; background: #fff5f5; padding: 8px; }
        .key-input.loaded { background: #f0fff4; border-color: #27ae60; } 
        .select-row { transform: scale(1.3); cursor: pointer; }
        .column-toggle { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 5px; padding: 10px; background: #fafafa; border: 1px solid #eee; margin-top: 10px; font-size: 11px;}
        #bulkActions { position: fixed; bottom: 20px; right: 20px; background: #2c3e50; padding: 15px 25px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); display: none; align-items: center; gap: 15px; z-index: 2000; }
        .btn-download { background: #f1c40f; color: #2c3e50; border: none; padding: 10px 15px; border-radius: 30px; font-weight: bold; cursor: pointer; }
        .btn-print { background: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 30px; font-weight: bold; cursor: pointer; }
        .btn-manual { background: #e67e22; color: white; border: none; padding: 10px 15px; border-radius: 30px; font-weight: bold; cursor: pointer; }
        .btn-sender { background: #8e44ad; color: white; border: none; padding: 10px 20px; border-radius: 30px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn-ia { background: linear-gradient(135deg, #6c5ce7, #a29bfe); color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; margin-bottom: 5px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-ia:hover { filter: brightness(1.1); }
        .btn-save-model { background: #2d3436; color: #dfe6e9; border: 1px solid #636e72; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-save-model:hover { background: #636e72; color:white; }
        .btn-tag { background: #dfe6e9; color: #2d3436; border: 1px solid #b2bec3; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; margin-right: 5px; font-weight: bold; }
        .btn-tag:hover { background: #b2bec3; }
        .toggle-switch { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; cursor: pointer; }
        .toggle-switch input { width: 18px; height: 18px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Gestão de Pedidos & Estoque</h1>

        <div class="dashboard-grid">
            <div class="card">
                <h3>📦 Estoque <button class="btn-danger-outline" onclick="validateStock()">🧹 Validar</button></h3>
                <div id="inventoryDisplay">Carregando...</div>
                <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
                <form id="addKeysForm" style="display:flex; gap:10px; align-items:flex-start;">
                    <select id="prodKeySelect" required><option value="">Selecione Produto...</option><option value="Office 2024">Office 2024</option><option value="Office 2021">Office 2021</option><option value="Office 2019">Office 2019</option><option value="Office 365">Office 365</option><option value="Project 2021">Project 2021</option><option value="Visio 2021">Visio 2021</option></select>
                    <textarea id="bulkKeys" placeholder="Cole as chaves aqui" style="min-height:35px; height:35px; flex-grow:1;"></textarea>
                    <button type="submit" class="btn-action">➕ Add</button>
                </form>
            </div>
            <div class="card">
                <h3>📥 Importar</h3>
                <form action="import_processor.php" method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center;">
                    <input list="stores" name="store_name" placeholder="Nome da Loja" required style="width:150px;">
                    <datalist id="stores"><?php foreach($storeList as $s): ?><option value="<?php echo htmlspecialchars($s); ?>"><?php endforeach; ?></datalist>
                    <input type="file" name="txtFile" accept=".txt" required>
                    <button type="submit" class="btn-action">Upload</button>
                </form>
                <?php if(isset($_GET['msg'])): ?><div style="color:green; font-weight:bold; margin-top:5px; font-size:12px;"><?php echo htmlspecialchars($_GET['msg']); ?></div><?php endif; ?>
            </div>
        </div>

        <form method="GET" class="top-bar">
            <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($currentSort); ?>">
            <div class="filter-group"><label>De:</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>"></div>
            <div class="filter-group"><label>Até:</label><input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>"></div>
            <div class="filter-group"><label>Loja:</label><select name="filter_store"><option value="">Todas</option><?php foreach($storeList as $s): ?><option value="<?php echo htmlspecialchars($s); ?>" <?php if($filterStore===$s) echo 'selected'; ?>><?php echo htmlspecialchars($s); ?></option><?php endforeach; ?></select></div>
            <div class="filter-group"><label>Cliente:</label><input type="text" name="filter_client" value="<?php echo htmlspecialchars($filterClient); ?>" style="width:120px;"></div>
            <div class="filter-group"><label>Order ID:</label><input type="text" name="filter_order_id" value="<?php echo htmlspecialchars($filterOrderId); ?>" style="width:120px;"></div>
            
            <div class="filter-group"><label>Status:</label><select name="filter_cancel"><option value="false" <?php if($filterCancel==='false') echo 'selected'; ?>>✅ Ativos</option><option value="true" <?php if($filterCancel==='true') echo 'selected'; ?>>🚫 Cancelados</option><option value="">Todos</option></select></div>
            <div class="filter-group"><label>WhatsApp:</label><select name="filter_zap"><option value="">Todos</option><option value="true" <?php if($filterZap==='true')echo'selected';?>>✅ Enviado</option><option value="false" <?php if($filterZap==='false')echo'selected';?>>❌ Pendente</option></select></div>
            <div class="filter-group"><label>Conf. Receb:</label><select name="filter_transp"><option value="">Todos</option><option value="true" <?php if($filterTransp==='true')echo'selected';?>>✅ Sim</option><option value="false" <?php if($filterTransp==='false')echo'selected';?>>❌ Não</option></select></div>
            <div class="filter-group"><label>Instalação:</label><select name="filter_inst"><option value="">Todos</option><option value="true" <?php if($filterInst==='true')echo'selected';?>>✅ OK</option><option value="false" <?php if($filterInst==='false')echo'selected';?>>❌ Pendente</option></select></div>
            <div class="filter-group"><label>Avaliação:</label><select name="filter_nota"><option value="">Todos</option><option value="true" <?php if($filterNota==='true')echo'selected';?>>✅ Pedir</option><option value="false" <?php if($filterNota==='false')echo'selected';?>>❌ Não precisa</option></select></div>
            
            <div class="filter-group"><button type="submit" class="btn-action">🔍 Filtrar</button></div>
            <?php if($startDate||$filterZap||$filterTransp||$filterInst||$filterNota||$filterStore||$filterOrderId||$filterClient||$filterCancel!=='false'): ?><div class="filter-group"><a href="index.php"><button type="button" class="btn-clear">Limpar</button></a></div><?php endif; ?>
        </form>

        <details>
            <summary style="cursor:pointer; font-weight:bold; margin-bottom:10px; color:#00b894;">👁️ Colunas</summary>
            <div class="column-toggle" id="colSwitches"></div>
        </details>

        <?php if (empty($orders)): ?>
            <p style="text-align:center; padding: 20px;">Nenhum pedido encontrado.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table id="mainTable">
                    <thead>
                        <tr>
                            <th style="text-align:center;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" style="transform: scale(1.3);"></th>
                            <th>Loja</th>
                            <th><a href="?sort_order=<?php echo $nextSort; ?>">Data <?php echo $sortIcon; ?></a></th>
                            <th>Order ID</th>
                            <th>Nome Cliente</th>
                            <th>CPF</th>
                            <th>Telefone</th>
                            <th>Produto</th>
                            <th>Qtd</th>
                            <th>WhatsApp</th>
                            <th>Conf. Recebimento</th>
                            <th>Instalação OK</th>
                            <th>Avaliação</th>
                            <th>Cancelado?</th>
                            <th>Cidade/UF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            $rawPhone = $order['buyer-phone-number'] ?? '';
                            $cleanPhone = format_phone($rawPhone);
                            $buyerName = htmlspecialchars($order['buyer-name'] ?? 'Cliente');
                            $orderId = htmlspecialchars($order['order-id'] ?? '');
                            $productName = htmlspecialchars($order['product-name'] ?? '');
                            $quantity = intval($order['quantity-purchased'] ?? 1);
                            
                            $sentZap = $order['enviado_whatsapp'] ?? false;
                            $sentTransp = $order['enviado_transportadora'] ?? false;
                            $zapCount = isset($order['whatsapp_sent_count']) ? (int)$order['whatsapp_sent_count'] : 0;
                            
                            $instOk = $order['instalacao_ok'] ?? false;
                            $pedirNota = $order['pedir_nota'] ?? false;
                            $isCanceled = $order['status_cancelado'] ?? false; 
                            $hasPhone = strlen($cleanPhone) > 8;

                            $jsData = json_encode([ "phone" => $cleanPhone, "name" => $buyerName, "orderId" => $orderId, "product" => $productName, "quantity" => $quantity, "cpf" => $order['cpf']??'', "sendCount" => $zapCount ]);
                            $jsDataSafe = htmlspecialchars($jsData, ENT_QUOTES, 'UTF-8');
                            
                            $labelData = json_encode([ 'name' => $order['recipient-name']??$buyerName, 'addr1' => $order['ship-address-1']??'', 'addr2' => $order['ship-address-2']??'', 'city' => $order['ship-city']??'', 'state' => $order['ship-state']??'', 'zip' => $order['ship-postal-code']??'' ]);
                            $labelDataSafe = htmlspecialchars($labelData, ENT_QUOTES, 'UTF-8');
                            
                            $rowClass = $isCanceled ? 'row-canceled' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>" onclick="highlightRow(this)">
                            <td style="text-align:center;"><input type="checkbox" class="select-row" value="<?php echo $orderId; ?>" data-address='<?php echo $labelDataSafe; ?>' data-product="<?php echo htmlspecialchars($productName); ?>" data-name="<?php echo htmlspecialchars($buyerName); ?>" onchange="updateBulkAction()"></td>
                            <td><?php echo htmlspecialchars($order['nome_loja'] ?? ''); ?></td>
                            <td><?php echo format_date($order['data_importacao'] ?? ''); ?></td>
                            <td><?php echo $orderId; ?></td>
                            <td><?php echo mb_strimwidth($buyerName, 0, 20, "..."); ?></td>
                            <td><?php echo htmlspecialchars($order['cpf'] ?? ''); ?></td>
                            <td><?php echo $cleanPhone; ?></td>
                            <td title="<?php echo $productName; ?>"><?php echo mb_strimwidth($productName, 0, 25, "..."); ?></td>
                            <td><?php echo $quantity; ?></td>
                            
                            <td style="text-align:center;">
                                <?php if($sentZap): ?>
                                    <span class="badge btn-toggle-green" onclick='resetWhatsapp(this, "<?php echo $orderId; ?>", <?php echo $jsDataSafe; ?>)'>Sim</span>
                                <?php elseif($hasPhone): ?>
                                    <span class="badge btn-toggle-red" onclick='openModal(this, <?php echo $jsDataSafe; ?>, "whatsapp")'>
                                        Não <?php echo $zapCount > 0 ? "($zapCount)" : ""; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-disabled">Não</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align:center;">
                                <?php if($sentTransp): ?>
                                    <span class="badge btn-toggle-green" onclick='toggleTransport(this, "<?php echo $orderId; ?>")'>Sim</span>
                                <?php elseif($hasPhone): ?>
                                    <span class="badge btn-toggle-red" onclick='openModal(this, <?php echo $jsDataSafe; ?>, "transport")'>
                                        Não <?php echo $zapCount > 0 ? "($zapCount)" : ""; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-disabled">Não</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align:center;"><span class="badge <?php echo $instOk?'btn-toggle-green':'btn-toggle-red'; ?>" onclick="toggleGenericStatus(this, '<?php echo $orderId; ?>', 'instalacao_ok', <?php echo $instOk?'true':'false'; ?>)"><?php echo $instOk?'Sim':'Não'; ?></span></td>
                            <td style="text-align:center;"><span class="badge <?php echo $pedirNota?'btn-toggle-green':'btn-toggle-red'; ?>" onclick="toggleGenericStatus(this, '<?php echo $orderId; ?>', 'pedir_nota', <?php echo $pedirNota?'true':'false'; ?>)"><?php echo $pedirNota?'Sim':'Não'; ?></span></td>
                            
                            <td style="text-align:center;">
                                <span class="badge <?php echo $isCanceled ? 'btn-toggle-red' : 'btn-toggle-gray'; ?>" style="background-color: <?php echo $isCanceled ? '#e74c3c' : '#95a5a6'; ?>; border-color: transparent;" onclick="toggleCancel(this, '<?php echo $orderId; ?>', <?php echo $isCanceled?'true':'false'; ?>)"><?php echo $isCanceled ? 'Sim' : 'Não'; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($order['ship-city'] ?? '') . ' / ' . htmlspecialchars($order['ship-state'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <?php if($page > 1): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">« Anterior</a><?php endif; ?>
                <span style="padding:0 15px;">Página <strong><?php echo $page; ?></strong> de <strong><?php echo $totalPages; ?></strong> (Total: <?php echo $totalRecords; ?>)</span>
                <?php if($page < $totalPages): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">Próxima »</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="bulkActions" style="display:none; position:fixed; bottom:20px; right:20px; background:#2c3e50; padding:15px 25px; border-radius:50px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); align-items:center; gap:10px; z-index:2000;">
        <span style="color:white; font-weight:bold; margin-right:10px;"><span id="bulkCount">0</span> sel.</span>
        <button onclick="prepararConferencia()" style="background:#8e44ad; color:white; border:none; padding:10px 20px; border-radius:30px; font-weight:bold; cursor:pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">🚀 Enviar p/ Sender</button>
        <button onclick="downloadAmazonFile()" style="background:#f1c40f; color:#2c3e50; border:none; padding:10px 15px; border-radius:30px; font-weight:bold; cursor:pointer;">📄 Arq. Envio</button>
        <button onclick="printLabels()" style="background:#3498db; color:white; border:none; padding:10px 15px; border-radius:30px; font-weight:bold; cursor:pointer;">🏷️ Etiquetas</button>
        <button onclick="openManualModal()" style="background:#e67e22; color:white; border:none; padding:10px 15px; border-radius:30px; font-weight:bold; cursor:pointer;">🖨️ Manuais</button>
    </div>

    <div id="msgModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div style="display:flex; align-items:center;">
                    <span id="modalTitle">Enviar Mensagem</span>
                    <span id="modalSendCountBadge" style="font-size:11px;background:#f1c40f;color:#2c3e50;padding:2px 8px;border-radius:10px;margin-left:10px;display:none;">0 envios</span>
                </div>
                <span style="cursor:pointer;" onclick="closeModal()">✕</span>
            </div>
            
            <input type="hidden" id="modalPhone"><input type="hidden" id="rawName"><input type="hidden" id="rawOrder"><input type="hidden" id="rawProduct"><input type="hidden" id="rawActionType"><input type="hidden" id="rawCPF"> 
            
            <div class="form-group"><label>Para:</label> <div id="divPhoneDisplay"><span style="font-weight:bold" id="modalRecipientLabel"></span> <button onclick="enablePhoneEdit()" style="background:none;border:none;color:#00b894;cursor:pointer;text-decoration:underline;">(Editar)</button></div><div id="divPhoneEdit" style="display:none;"><input type="text" id="manualPhoneInput" onkeyup="syncPhone()"></div><div style="font-size:11px; color:#999;" id="modalProductLabel"></div></div>
            <div class="form-group"><label>Modelo:</label><select id="templateSelect" onchange="updatePreview()" style="width:100%"><option value="" data-slug="">-- Selecione --</option><?php if($templates): foreach($templates as $tpl): ?><option value="<?php echo htmlspecialchars(json_encode($tpl['content'])); ?>" data-slug="<?php echo htmlspecialchars($tpl['slug']); ?>"><?php echo htmlspecialchars($tpl['name']); ?></option><?php endforeach; endif; ?></select></div>
            <div id="keysWrapper" class="form-group"><label style="color:#e74c3c;">🔑 Chave(s):</label><div id="keysLoading" style="display:none; color:#e67e22; font-size:12px;">🔄 Buscando estoque...</div><div id="keysContainer"></div></div>
            
            <div class="form-group"><label style="font-size:12px; font-weight:bold; color:#555;">Variáveis:</label><div style="margin-bottom:5px;"><button type="button" class="btn-tag" onclick="insertAtCursor('{nome}')">{nome}</button><button type="button" class="btn-tag" onclick="insertAtCursor('{pedido}')">{pedido}</button><button type="button" class="btn-tag" onclick="insertAtCursor('{produto}')">{produto}</button><button type="button" class="btn-tag" onclick="insertAtCursor('{chave}')">{chave}</button><button type="button" class="btn-tag" onclick="insertAtCursor('{cpf}')">{cpf}</button></div></div>

            <div class="form-group">
                <label style="display:flex; justify-content:space-between; align-items:center;">
                    Texto Base (Original/Editado):
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn-save-model" onclick="saveTemplateToDB()">💾 Salvar Modelo</button>
                        <label class="toggle-switch"><input type="checkbox" id="chkSkipAI"> <span style="font-size:12px; font-weight:normal; color:#d63031;">Enviar texto exato (Sem IA)</span></label>
                        <button type="button" class="btn-ia" onclick="generateIAPreview()">✨ Sugestão IA</button>
                    </div>
                </label>
                <textarea id="msgPreview" placeholder="Mensagem padrão..."></textarea>
            </div>
            
            <div class="form-group" id="divPreviewIA" style="display:none; animation: fadeIn 0.5s;">
                <label style="color:#6c5ce7; font-weight:bold;">Sugestão da IA (Será enviada se não marcar 'Sem IA'):</label>
                <textarea id="msgPreviewIA" style="border: 2px solid #a29bfe; background:#f8f9fe;"></textarea>
            </div>

            <div class="modal-actions">
                <button class="btn-just-confirm" id="btnJustConfirm" onclick="confirmOnly()" style="display:none;">💾 Somente Confirmar</button>
                <button class="btn-cancel" onclick="closeModal()">Cancelar</button>
                <button onclick="sendFromModal()" id="btnConfirmSend" style="background:#27ae60; color:white; border:none; padding:10px 20px; border-radius:5px; font-weight:bold;">✈️ Enviar</button>
            </div>
        </div>
    </div>

    <div id="modalConferencia" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:3000; justify-content:center; align-items:center;">
        <div style="background:white; width:90%; max-width:1000px; height:90%; padding:0; border-radius:10px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="padding:20px; background:#f8f9fa; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
                <div><h2 style="margin:0; color:#2c3e50; font-size:20px;">🧐 Conferência de Dados Enriquecidos</h2><small style="color:#666;">Verifique os contatos retornados pela busca antes de enviar.</small></div>
                <button onclick="fecharConferencia()" style="background:none; border:none; font-size:20px; cursor:pointer;">✕</button>
            </div>
            <div style="flex:1; overflow-y:auto; padding:20px;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead style="background:#2c3e50; color:white; position:sticky; top:0;"><tr><th style="padding:10px;">Pedido / Cliente</th><th style="padding:10px;">CPF</th><th style="padding:10px;">Tipo</th><th style="padding:10px;">Contato</th><th style="padding:10px;">Origem</th><th style="padding:10px; text-align:center;">Remover</th></tr></thead>
                    <tbody id="tbodyConferencia"></tbody>
                </table>
            </div>
            <div style="padding:20px; background:#f8f9fa; border-top:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
                <div id="resumoConferencia" style="font-weight:bold; color:#2c3e50;"></div>
                <button onclick="confirmarEnvioFinal()" style="background:#27ae60; color:white; border:none; padding:12px 30px; border-radius:5px; font-weight:bold; font-size:16px; cursor:pointer;">✅ Confirmar e Ir para Sender</button>
            </div>
        </div>
    </div>

    <div id="manualModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:4000; justify-content:center; align-items:center;">
        <div style="background:white; padding:25px; border-radius:8px; width:400px; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
            <h3 style="margin-top:0; color:#2c3e50;">🖨️ Imprimir Manual</h3>
            <p style="color:#666; font-size:13px;">Selecione o modelo que contém as instruções:</p>
            
            <select id="manualTemplateSelect" style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #ccc; border-radius:4px;">
                <?php if($templates): foreach($templates as $tpl): ?>
                    <option value="<?php echo htmlspecialchars(json_encode($tpl['content'])); ?>"><?php echo htmlspecialchars($tpl['name']); ?></option>
                <?php endforeach; endif; ?>
            </select>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button onclick="document.getElementById('manualModal').style.display='none'" style="background:#95a5a6; color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer;">Cancelar</button>
                <button onclick="printManualsExecute()" style="background:#e67e22; color:white; border:none; padding:10px 20px; border-radius:5px; font-weight:bold; cursor:pointer;">Imprimir</button>
            </div>
        </div>
    </div>

    <script>
        var dadosGlobaisParaEnvio = [];
        let currentBtnElement = null;

        function highlightRow(row) {
            document.querySelectorAll('tr').forEach(r => r.classList.remove('selected-row'));
            row.classList.add('selected-row');
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadInventory();
            const table = document.getElementById('mainTable'); if(!table) return;
            const headers = table.querySelectorAll('thead th'); const container = document.getElementById('colSwitches');
            const defaultCols = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14]; 
            headers.forEach((th, index) => {
                const label = document.createElement('label'); const checkbox = document.createElement('input');
                checkbox.type = 'checkbox'; checkbox.checked = defaultCols.includes(index); checkbox.onchange = () => toggleColumn(index, checkbox.checked);
                label.appendChild(checkbox); label.appendChild(document.createTextNode(' ' + th.innerText)); container.appendChild(label); toggleColumn(index, checkbox.checked);
            });
        });

        // --- LÓGICA DE NEGÓCIO ---
        async function loadInventory() { try { const res = await fetch('inventory_manager.php?action=count'); const data = await res.json(); const display = document.getElementById('inventoryDisplay'); if(data.success && Object.keys(data.data).length > 0) { display.innerHTML = ''; for (const [prod, count] of Object.entries(data.data)) { display.innerHTML += `<span class="inv-item">${prod}: ${count}</span>`; } } else { display.innerHTML = 'Sem chaves disponíveis.'; } } catch(e) {} }
        async function validateStock() { const btn = document.querySelector('.btn-danger-outline'); const originalText = btn.innerText; if(!confirm("Verificar TODAS chaves na API?")) return; btn.innerText = "⏳ Verificando..."; btn.disabled = true; try { const res = await fetch('validate_keys.php'); const data = await res.json(); if (data.success) { alert(data.message); loadInventory(); } else { alert("Erro: " + data.message); } } catch (e) { alert("Erro de conexão."); } finally { btn.innerText = originalText; btn.disabled = false; } }
        document.getElementById('addKeysForm').addEventListener('submit', async function(e) { e.preventDefault(); const prod = document.getElementById('prodKeySelect').value; const keys = document.getElementById('bulkKeys').value; try { const res = await fetch('inventory_manager.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({product: prod, keys: keys}) }); const data = await res.json(); if(data.success) { alert(`Adicionadas ${data.count} chaves!`); document.getElementById('bulkKeys').value = ''; loadInventory(); } else { alert('Erro: ' + data.message); } } catch(e) { alert('Erro ao salvar'); } });

        // --- UI ---
        function toggleColumn(index, isVisible) { const table = document.getElementById('mainTable'); const rows = table.rows; for (let i = 0; i < rows.length; i++) { const cell = rows[i].cells[index]; if (cell) cell.style.display = isVisible ? '' : 'none'; } }
        function toggleSelectAll(source) { document.querySelectorAll('.select-row').forEach(cb => cb.checked = source.checked); updateBulkAction(); }
        function updateBulkAction() { const count = document.querySelectorAll('.select-row:checked').length; document.getElementById('bulkCount').innerText = count; document.getElementById('bulkActions').style.display = count > 0 ? 'flex' : 'none'; }

        // --- MANUAIS (NOVO) ---
        function openManualModal() {
            const selected = document.querySelectorAll('.select-row:checked');
            if (selected.length === 0) { alert('Selecione os pedidos!'); return; }
            document.getElementById('manualModal').style.display = 'flex';
        }

        async function printManualsExecute() {
            const selected = document.querySelectorAll('.select-row:checked');
            const orderIds = Array.from(selected).map(cb => cb.value);
            
            // Pega o template selecionado
            let templateContent = document.getElementById('manualTemplateSelect').value;
            try { templateContent = JSON.parse(templateContent); } catch(e) {} // Remove aspas se for JSON

            if (!templateContent) return alert("Erro no template.");

            let assignedKeys = {};
            try { 
                const res = await fetch('fetch_assigned_keys.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({order_ids: orderIds}) }); 
                const data = await res.json(); 
                if(data.success) assignedKeys = data.data; 
            } catch(e) {}

            // CSS A4 CLEAN
            const style = `
                @page { size: A4; margin: 0; }
                body { margin: 0; padding: 0; background: #f0f0f0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-print-color-adjust: exact; }
                .page { width: 210mm; height: 297mm; background: white; margin: auto; padding: 15mm; box-sizing: border-box; page-break-after: always; display: flex; flex-direction: column; position: relative; }
                .header { text-align: center; border-bottom: 3px solid #2c3e50; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { margin: 0; color: #2c3e50; font-size: 28px; text-transform: uppercase; letter-spacing: 2px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
                .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 5px solid #3498db; }
                .info-label { font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: bold; margin-bottom: 5px; }
                .info-value { font-size: 16px; color: #2c3e50; font-weight: 500; }
                .key-section { margin: 20px 0; text-align: center; }
                .key-title { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
                .key-box { border: 2px dashed #27ae60; background: #eafaf1; padding: 25px; border-radius: 12px; display: inline-block; width: 80%; }
                .license-key { font-family: 'Consolas', 'Monaco', monospace; font-size: 24px; color: #27ae60; font-weight: bold; word-break: break-all; line-height: 1.5; }
                .instructions-title { font-size: 18px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; margin-top: 30px; }
                .instructions-content { font-size: 14px; line-height: 1.6; color: #444; white-space: pre-wrap; }
                .footer { text-align: center; font-size: 11px; color: #95a5a6; margin-top: auto; padding-top: 20px; border-top: 1px solid #eee; }
                @media print { body { background: white; } .page { box-shadow: none; margin: 0; width: 100%; height: 100%; } }
            `;

            let html = `<html><head><title>Manuais de Instalação</title><style>${style}</style></head><body>`;

            selected.forEach(cb => {
                const oid = cb.value;
                const name = cb.dataset.name;
                const prod = cb.dataset.product;
                const keys = assignedKeys[oid] || ["CHAVE NÃO ENCONTRADA (Verifique se já enviou)"];
                
                // Prepara o texto do template (Substitui variáveis)
                let instructions = templateContent;
                instructions = instructions.replace(/{{nome}}/gi, name).replace(/{nome}/gi, name);
                instructions = instructions.replace(/{{pedido}}/gi, oid).replace(/{pedido}/gi, oid);
                instructions = instructions.replace(/{{produto}}/gi, prod).replace(/{produto}/gi, prod);
                instructions = instructions.replace(/{{chave}}/gi, "").replace(/{chave}/gi, ""); // Remove a chave do texto pois já tem box dedicado
                
                html += `
                <div class="page">
                    <div class="header">
                        <h1>Certificado de Licença</h1>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-box">
                            <div class="info-label">Cliente</div>
                            <div class="info-value">${name}</div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Produto Adquirido</div>
                            <div class="info-value">${prod}</div>
                        </div>
                    </div>

                    <div class="key-section">
                        <div class="key-title">Sua Chave de Ativação</div>
                        <div class="key-box">
                            <div class="license-key">${keys.join('<br>')}</div>
                        </div>
                    </div>

                    <div class="instructions-section">
                        <div class="instructions-title">Instruções de Instalação</div>
                        <div class="instructions-content">${instructions}</div>
                    </div>

                    <div class="footer">
                        Pedido: ${oid} • Obrigado pela preferência!<br>
                        Suporte Técnico disponível via WhatsApp.
                    </div>
                </div>`;
            });

            html += '</body></html>';
            
            const w = window.open('', '_blank');
            w.document.write(html);
            w.document.close();
            
            // Fecha modal
            document.getElementById('manualModal').style.display = 'none';
            
            // Aguarda renderizar e imprime
            setTimeout(() => { w.print(); }, 800);
        }

        // --- ENRIQUECIMENTO ---
        async function prepararConferencia() {
            const selected = document.querySelectorAll('.select-row:checked'); if (selected.length === 0) return alert("Selecione pelo menos um pedido.");
            const btn = document.querySelector('#bulkActions button'); const originalText = btn.innerText; btn.innerText = "⏳ Buscando na API..."; btn.disabled = true; const ids = Array.from(selected).map(cb => cb.value);
            try { const response = await fetch('exportar_para_sender.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ ids: ids }) }); const json = await response.json(); if (json.success) { dadosGlobaisParaEnvio = json.data; if (dadosGlobaisParaEnvio.length === 0) { alert("Nenhum dado válido retornado."); } else { abrirModalConferencia(); } } else { alert("Erro no Backend: " + json.message); } } catch (e) { console.error(e); alert("Erro de Comunicação: " + e.message); } finally { btn.innerText = originalText; btn.disabled = false; }
        }
        function abrirModalConferencia() {
            const tbody = document.getElementById('tbodyConferencia'); tbody.innerHTML = ''; let totalEmail = 0, totalZap = 0;
            dadosGlobaisParaEnvio.forEach((item, index) => { if (item.tipo === 'email') totalEmail++; if (item.tipo === 'celular') totalZap++; const tr = document.createElement('tr'); let bgColor = item.tipo === 'celular' ? '#e8f8f5' : '#f4f6f7'; let icon = item.tipo === 'celular' ? '📱 Zap' : '📧 Email'; let origemColor = item.origem.includes('Busca') ? '#d35400' : '#7f8c8d'; tr.style.backgroundColor = bgColor; tr.innerHTML = `<td style="padding:10px;"><b>${item.pedido}</b><br>${item.nome}</td><td style="padding:10px;">${item.cpf}</td><td style="padding:10px;">${icon}</td><td style="padding:10px; font-family:monospace;">${item.contato}</td><td style="padding:10px; color:${origemColor}; font-weight:bold;">${item.origem}</td><td style="padding:10px; text-align:center;"><button onclick="removerItemLista(${index})" style="color:red; background:none; border:none; cursor:pointer;">X</button></td>`; tbody.appendChild(tr); });
            document.getElementById('resumoConferencia').innerText = `Resumo: ${dadosGlobaisParaEnvio.length} envios (${totalEmail} Emails, ${totalZap} Zaps)`; document.getElementById('modalConferencia').style.display = 'flex';
        }
        function removerItemLista(index) { dadosGlobaisParaEnvio.splice(index, 1); abrirModalConferencia(); }
        function fecharConferencia() { document.getElementById('modalConferencia').style.display = 'none'; }
        function confirmarEnvioFinal() { if (dadosGlobaisParaEnvio.length === 0) return alert("Lista vazia!"); localStorage.setItem('dadosSender', JSON.stringify(dadosGlobaisParaEnvio)); window.open('../sender/index.php?auto=true', '_blank'); fecharConferencia(); }

        // --- MANIPULAÇÃO DE PEDIDOS ---
        async function toggleGenericStatus(btn, orderId, column, currentState) { const newState = !currentState; const originalText = btn.innerText; btn.innerText = '...'; try { const res = await fetch('update_process.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, column: column, value: newState }) }); const data = await res.json(); if(data.success) { if (column === 'status_cancelado') { location.reload(); return; } btn.innerText = newState ? 'Sim' : 'Não'; btn.className = newState ? 'badge btn-toggle-green' : 'badge btn-toggle-red'; btn.onclick = function() { toggleGenericStatus(this, orderId, column, newState); }; } else { alert('Erro: ' + data.message); btn.innerText = originalText; } } catch(e) { alert('Erro: ' + e.message); btn.innerText = originalText; } }
        async function toggleCancel(btn, orderId, isCanceled) { if(!confirm(isCanceled ? "Reativar?" : "Cancelar?")) return; const res = await fetch('update_process.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, column: 'status_cancelado', value: !isCanceled }) }); if((await res.json()).success) location.reload(); }
        async function toggleTransport(btn, orderId) { if(!confirm("Marcar como NÃO recebido?")) return; const res = await fetch('update_process.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, column: 'enviado_transportadora', value: false }) }); if((await res.json()).success) location.reload(); }
        async function resetWhatsapp(btn, orderId, jsData) { if(!confirm("Reativar envio?")) return; const res = await fetch('update_process.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, column: 'enviado_whatsapp', value: false }) }); if((await res.json()).success) { btn.innerText = "Não"; btn.className = "badge btn-toggle-red"; btn.onclick = function() { openModal(this, jsData, "whatsapp"); }; } }

        // --- ENVIO INDIVIDUAL ---
        function insertAtCursor(myValue) { const myField = document.getElementById('msgPreview'); if (document.selection) { myField.focus(); sel = document.selection.createRange(); sel.text = myValue; } else if (myField.selectionStart || myField.selectionStart == '0') { var startPos = myField.selectionStart; var endPos = myField.selectionEnd; myField.value = myField.value.substring(0, startPos) + myValue + myField.value.substring(endPos, myField.value.length); myField.selectionStart = startPos + myValue.length; myField.selectionEnd = startPos + myValue.length; } else { myField.value += myValue; } myField.focus(); }
        function processTextVariables(text) { if (!text) return ""; const nome = document.getElementById('rawName').value || ""; const pedido = document.getElementById('rawOrder').value || ""; const produto = document.getElementById('rawProduct').value || ""; const cpf = document.getElementById('rawCPF').value || ""; const kInp = document.querySelectorAll('.key-input'); let kArr = []; kInp.forEach((inp, i) => { if(inp.value) kArr.push(`Licença ${i+1}: ${inp.value}`); }); const chave = kArr.join('\n') || ""; let processed = text; processed = processed.replace(/{{nome}}/gi, nome).replace(/{nome}/gi, nome); processed = processed.replace(/{{pedido}}/gi, pedido).replace(/{pedido}/gi, pedido); processed = processed.replace(/{{produto}}/gi, produto).replace(/{produto}/gi, produto); processed = processed.replace(/{{chave}}/gi, chave).replace(/{chave}/gi, chave); processed = processed.replace(/{{cpf}}/gi, cpf).replace(/{cpf}/gi, cpf); return processed; }
        
        async function saveTemplateToDB() {
            const sel = document.getElementById('templateSelect');
            const slug = sel.options[sel.selectedIndex].getAttribute('data-slug');
            const content = document.getElementById('msgPreview').value; 
            if(!slug || !content) return alert("Selecione um modelo e escreva algo para salvar.");
            if(!confirm("⚠️ ATENÇÃO: Isso atualizará o modelo no banco de dados para TODOS os futuros envios.\n\nDeseja continuar?")) return;
            try { const res = await fetch('save_template.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ slug: slug, content: content }) }); const data = await res.json(); if(data.success) { alert(data.message); } else { alert("Erro: " + data.message); } } catch(e) { alert("Erro ao salvar template."); }
        }

        function updatePreview() { 
            const sel = document.getElementById('templateSelect'); 
            let txt = ""; 
            try { txt = JSON.parse(sel.value); } catch(e) { txt = sel.value; } 
            
            if(!txt) return; 
            
            const kInp = document.querySelectorAll('.key-input'); 
            let kArr = []; 
            kInp.forEach((inp, i) => { if(inp.value) kArr.push(`Licença ${i+1}: ${inp.value}`); }); 
            
            const filledText = txt.replace(/{{nome}}/gi, document.getElementById('rawName').value).replace(/{{pedido}}/gi, document.getElementById('rawOrder').value).replace(/{{chave}}/gi, kArr.join('\n') || "[CHAVE]");
            document.getElementById('msgPreview').value = filledText;
            
            if(filledText.trim() !== "" && !document.getElementById('chkSkipAI').checked) {
                generateIAPreview();
            }
        }
        
        async function generateIAPreview() {
            const rawText = document.getElementById('msgPreview').value;
            if(!rawText) return; 
            const finalText = processTextVariables(rawText); 
            const btn = document.querySelector('.btn-ia'); const originalBtnText = btn.innerText;
            btn.innerText = "🤖 ..."; btn.disabled = true;
            try {
                const res = await fetch('ia_generator.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ text: finalText }) });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('divPreviewIA').style.display = 'flex';
                    document.getElementById('msgPreviewIA').value = data.ai_text;
                }
            } catch (e) { console.error("Erro IA:", e); } finally { btn.innerText = originalBtnText; btn.disabled = false; }
        }

        async function openModal(element, data, actionType) {
            currentBtnElement = element; document.getElementById('msgModal').style.display = 'flex';
            document.getElementById('divPhoneDisplay').style.display = 'flex'; document.getElementById('divPhoneEdit').style.display = 'none';
            document.getElementById('modalPhone').value = data.phone; document.getElementById('manualPhoneInput').value = data.phone;
            document.getElementById('rawName').value = data.name; document.getElementById('rawOrder').value = data.orderId;
            document.getElementById('rawProduct').value = data.product; document.getElementById('rawActionType').value = actionType;
            document.getElementById('rawCPF').value = data.cpf || ""; 
            document.getElementById('modalRecipientLabel').innerText = data.name + " (" + data.phone + ")"; document.getElementById('modalProductLabel').innerText = data.product;
            const keysContainer = document.getElementById('keysContainer'); keysContainer.innerHTML = ''; document.getElementById('msgPreview').value = '';
            
            if (data.sendCount > 0) { document.getElementById('modalSendCountBadge').style.display = 'inline-block'; document.getElementById('modalSendCountBadge').innerText = `${data.sendCount} envios anteriores`; } else { document.getElementById('modalSendCountBadge').style.display = 'none'; }

            let existingKeysFound = false;
            if (actionType !== 'transport') {
                try {
                    const resAssigned = await fetch('fetch_assigned_keys.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ order_ids: [data.orderId] }) });
                    const dataAssigned = await resAssigned.json();
                    if (dataAssigned.success && dataAssigned.data[data.orderId] && dataAssigned.data[data.orderId].length > 0) {
                        existingKeysFound = true; document.getElementById('keysWrapper').style.display = 'block';
                        dataAssigned.data[data.orderId].forEach(k => { const inp = document.createElement('input'); inp.className = 'key-input loaded'; inp.value = k; keysContainer.appendChild(inp); });
                    }
                } catch(e) { console.error(e); }
            }
            
            if (actionType === 'transport') {
                document.getElementById('keysWrapper').style.display = 'none'; document.getElementById('modalTitle').innerText = "Confirmar Recebimento"; document.getElementById('btnJustConfirm').style.display = 'inline-block';
            } else {
                document.getElementById('keysWrapper').style.display = 'block'; document.getElementById('modalTitle').innerText = "Enviar Entrega"; document.getElementById('btnJustConfirm').style.display = 'none';
                if (!existingKeysFound) {
                    document.getElementById('keysLoading').style.display = 'block'; const qty = parseInt(data.quantity)||1; for(let i=0; i<qty; i++) { const inp=document.createElement('input'); inp.className='key-input'; inp.placeholder='...'; keysContainer.appendChild(inp); }
                    try { const r = await fetch('get_keys.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({product_name:data.product, quantity:qty})}); const d = await r.json(); if(d.success) d.keys.forEach((k,i)=>{ if(keysContainer.children[i]) { keysContainer.children[i].value=k; keysContainer.children[i].classList.add('loaded'); } }); } catch(e){} finally { document.getElementById('keysLoading').style.display='none'; }
                }
            }
            
            const select = document.getElementById('templateSelect'); select.selectedIndex = 0; const pName = data.product ? data.product.toLowerCase() : "";
            for(let i=0; i<select.options.length; i++){
                const option = select.options[i]; const slug = (option.getAttribute('data-slug') || "").toLowerCase(); const name = (option.text || "").toLowerCase();
                if(actionType === 'transport' && (slug.includes('confirm_receb') || slug.includes('transport') || name.includes('recebimento'))) { select.selectedIndex=i; break; }
                if(actionType === 'whatsapp') {
                    if (pName.includes('2024') && (slug.includes('2024') || name.includes('2024'))) { select.selectedIndex=i; break; }
                    else if (pName.includes('2021') && (slug.includes('2021') || name.includes('2021'))) { select.selectedIndex=i; break; }
                    else if (pName.includes('2019') && (slug.includes('2019') || name.includes('2019'))) { select.selectedIndex=i; break; }
                    else if (pName.includes('365') && (slug.includes('365') || name.includes('365'))) { select.selectedIndex=i; break; }
                    else if (pName.includes('project') && (slug.includes('project') || name.includes('project'))) { select.selectedIndex=i; break; }
                    else if (pName.includes('visio') && (slug.includes('visio') || name.includes('visio'))) { select.selectedIndex=i; break; }
                }
            }
            updatePreview();
        }

        async function sendFromModal() {
            const btn = document.getElementById('btnConfirmSend');
            let phone = document.getElementById('divPhoneEdit').style.display !== 'none' ? document.getElementById('manualPhoneInput').value : document.getElementById('modalPhone').value;
            let textToSend = ""; let skipAI = false;
            const iaText = document.getElementById('msgPreviewIA').value;
            const iaVisible = document.getElementById('divPreviewIA').style.display !== 'none';
            const manualCheck = document.getElementById('chkSkipAI').checked; 
            
            if (manualCheck) { textToSend = processTextVariables(document.getElementById('msgPreview').value); skipAI = true; } else if (iaVisible && iaText.trim() !== "") { textToSend = iaText; skipAI = true; } else { textToSend = processTextVariables(document.getElementById('msgPreview').value); skipAI = false; }
            if(!textToSend) return alert('Mensagem vazia!');
            
            const oid = document.getElementById('rawOrder').value; const action = document.getElementById('rawActionType').value;
            btn.disabled = true; btn.innerText = 'Enviando...';
            try {
                let keys = []; if(action == 'whatsapp') document.querySelectorAll('.key-input').forEach(i => { if(i.value.length>5) keys.push(i.value.trim()); });
                const r = await fetch('enviar_msg.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ phone: phone, text: textToSend, skip_ai: skipAI }) });
                const d = await r.json(); if(!d.success) throw new Error(d.message);
                if (action === 'transport') { alert('✅ ' + d.message); }
                const colName = (action === 'transport') ? 'enviado_transportadora' : 'enviado_whatsapp';
                
                // CORREÇÃO CRÍTICA DO CONTADOR:
                // Se for WhatsApp (Entrega): Muda pra Sim, mas NÃO CONTA (no_increment: true)
                // Se for Transportadora (Confirmação): Muda pra Sim e CONTA (increment_counter: true)
                
                let params = { 
                    order_id: oid, 
                    column: colName, 
                    value: true, 
                    used_keys: keys, 
                    new_phone: phone 
                };

                if (action === 'whatsapp') {
                    params.no_increment = true; // Bloqueia contador
                } else if (action === 'transport') {
                    params.increment_counter = true; // Força contador
                }

                const rUp = await fetch('update_process.php', { 
                    method:'POST', 
                    headers:{'Content-Type':'application/json'}, 
                    body: JSON.stringify(params) 
                });
                
                if((await rUp.json()).success) location.reload();
            } catch(e) { alert(e.message); } finally { btn.disabled=false; btn.innerText='Enviar'; }
        }
        
        async function confirmOnly() {
            const btn = document.getElementById('btnJustConfirm'); const orderId = document.getElementById('rawOrder').value; const originalText = btn.innerText;
            if(!orderId) { alert("ID não encontrado."); return; }
            if(!confirm("Marcar como 'Confirmado' SEM enviar mensagem?")) return;
            btn.disabled = true; btn.innerText = 'Salvando...';
            try { const res = await fetch('update_process.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ 
                    order_id: orderId, 
                    column: 'enviado_transportadora', 
                    value: true,
                    no_increment: true // Somente Confirmar: Nunca conta
                }) 
            }); const data = await res.json(); if(data.success) { location.reload(); } else { alert('Erro: ' + data.message); btn.disabled = false; btn.innerText = originalText; } } catch(e) { alert('Erro de conexão: ' + e.message); btn.disabled = false; btn.innerText = originalText; }
        }

        function enablePhoneEdit() { document.getElementById('divPhoneDisplay').style.display = 'none'; document.getElementById('divPhoneEdit').style.display = 'block'; document.getElementById('manualPhoneInput').focus(); }
        function syncPhone() { document.getElementById('modalPhone').value = document.getElementById('manualPhoneInput').value; }
        
        function closeModal() { 
            document.getElementById('msgModal').style.display='none'; 
            document.getElementById('divPreviewIA').style.display='none'; 
            document.getElementById('msgPreviewIA').value=''; 
            document.getElementById('chkSkipAI').checked = false;
        }
        
        function downloadAmazonFile() { const selected = document.querySelectorAll('.select-row:checked'); if(selected.length === 0) return; const today = new Date(); const shipDate = today.toISOString().split('T')[0]; const dateStr = String(today.getDate()).padStart(2,'0') + String(today.getMonth()+1).padStart(2,'0') + String(today.getFullYear()).slice(-2); let content = "order-id\torder-item-id\tquantity\tship-date\tcarrier-code\tcarrier-name\ttracking-number\tship-method\n"; let counter = 10; selected.forEach(cb => { counter++; const tracking = "WZP" + dateStr + counter; content += `${cb.value}\t\t\t${shipDate}\tOther\tWZPEXPRESS\t${tracking}\t\n`; }); const a = document.createElement('a'); a.href = window.URL.createObjectURL(new Blob([content], {type: 'text/plain'})); a.download = `ENVIO_AMAZON_${shipDate}.txt`; a.click(); }
        function printLabels() { const selected = document.querySelectorAll('.select-row:checked'); if (selected.length === 0) { alert("Selecione pedidos!"); return; } const style = `@page { size: A4; margin: 10mm; } body { font-family: Arial, sans-serif; margin: 0; padding: 0; } .a4-container { width: 210mm; height: 297mm; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(5, 1fr); gap: 5mm; padding: 5mm; box-sizing: border-box; page-break-after: always; } .label { border: 1px dashed #ccc; padding: 15px; display: flex; flex-direction: column; justify-content: center; font-size: 14px; line-height: 1.4; } .label strong { font-size: 16px; display: block; margin-bottom: 5px; } @media print { .label { border: 1px solid #000; } }`; let htmlContent = `<html><head><title>Etiquetas</title><style>${style}</style></head><body>`; const items = Array.from(selected); for (let i = 0; i < items.length; i += 10) { const chunk = items.slice(i, i + 10); htmlContent += '<div class="a4-container">'; chunk.forEach(checkbox => { try { const data = JSON.parse(checkbox.dataset.address); htmlContent += `<div class="label"><strong>DESTINATÁRIO:</strong>${data.name.toUpperCase()}<br>${data.addr1.toUpperCase()} ${data.addr2 ? '- ' + data.addr2.toUpperCase() : ''}<br>${data.city.toUpperCase()} - ${data.state.toUpperCase()}<br><strong>CEP: ${data.zip}</strong></div>`; } catch(e) {} }); htmlContent += '</div>'; } htmlContent += '</body></html>'; const w = window.open('', '_blank'); w.document.write(htmlContent); w.document.close(); setTimeout(() => w.print(), 800); }

        window.onclick = function(e) { if(e.target == document.getElementById('msgModal')) closeModal(); }
    </script>
</body>
</html>