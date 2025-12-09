<?php
// ====================================================================
// ARQUIVO: index.php (VERSÃO V14 - FINAL COM BUSCA POR NOME)
// ====================================================================

$supabaseUrl = 'https://qoobmxjzcjtkpezajbbv.supabase.co'; 
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8'; 
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

// 1. Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 100;
$offset = ($page - 1) * $limit;
$rangeStart = $offset;
$rangeEnd = $offset + $limit - 1;

// 2. Filtros
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$filterZap = $_GET['filter_zap'] ?? '';      
$filterTransp = $_GET['filter_transp'] ?? ''; 
$filterInst = $_GET['filter_inst'] ?? '';    
$filterNota = $_GET['filter_nota'] ?? '';
$filterStore = $_GET['filter_store'] ?? '';     
$filterOrderId = $_GET['filter_order_id'] ?? ''; 
$filterClient = $_GET['filter_client'] ?? ''; // FILTRO DE CLIENTE
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

// Lógica Filtro Cliente (Parcial)
if (!empty($filterClient)) {
    $queryParams .= "&buyer-name=ilike.*" . urlencode($filterClient) . "*";
}

// Filtro Cancelado
if ($filterCancel === 'true') $queryParams .= "&status_cancelado=eq.true";
if ($filterCancel === 'false') $queryParams .= "&status_cancelado=eq.false";

$currentSort = $_GET['sort_order'] ?? 'desc';
$nextSort = ($currentSort === 'desc') ? 'asc' : 'desc';
$sortIcon = ($currentSort === 'desc') ? '⬇️' : '⬆️';

// 3. Busca Pedidos
$urlOrders = "{$supabaseUrl}/rest/v1/{$tableName}?select=*{$queryParams}&order=data_importacao.{$currentSort}";
$result = fetchSupabaseRaw($urlOrders, $supabaseAnonKey, ["Range: $rangeStart-$rangeEnd", "Prefer: count=exact"]);
$orders = $result['body'];

$totalRecords = 0;
if ($result['range']) {
    $parts = explode('/', $result['range']);
    if (isset($parts[1]) && is_numeric($parts[1])) $totalRecords = (int)$parts[1];
}
$totalPages = ($totalRecords > 0) ? ceil($totalRecords / $limit) : 1;

// 4. Templates e Lojas
$resTemplates = fetchSupabaseRaw("{$supabaseUrl}/rest/v1/message_templates?select=slug,name,content&order=name.asc", $supabaseAnonKey);
$templates = $resTemplates['body'];

$resStores = fetchSupabaseRaw("{$supabaseUrl}/rest/v1/{$tableName}?select=nome_loja&limit=1000&order=created_at.desc", $supabaseAnonKey);
$storeList = [];
if(is_array($resStores['body'])) {
    $allStores = array_column($resStores['body'], 'nome_loja');
    $storeList = array_unique($allStores);
}

// --- Formatadores ---
function format_phone($phone) { return preg_replace('/[^0-9]/', '', $phone); }
function format_date($date) { 
    if (empty($date)) return '-';
    try {
        $d = new DateTime($date);
        $d->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $d->format('d/m/Y H:i');
    } catch (Exception $e) { return $date; }
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
        
        /* Grid Dashboard */
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

        /* Filtros */
        .top-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; align-items: flex-end; background: #fafafa; padding: 15px; border-radius: 5px; border: 1px solid #eee; }
        .filter-group { display: flex; flex-direction: column; gap: 3px; }
        .filter-group label { font-size: 11px; font-weight: bold; color: #7f8c8d; }

        /* Tabela */
        .table-responsive { overflow-x: auto; margin-top:10px; border: 1px solid #eee; max-height: 70vh; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th { background: #2c3e50; color: white; position: sticky; top: 0; z-index: 100; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }
        
        /* ESTILO CANCELADO */
        tr.row-canceled { background-color: #ffebee !important; color: #999; }
        tr.row-canceled td { text-decoration: line-through; }
        tr.row-canceled td .badge, tr.row-canceled td input { text-decoration: none !important; opacity: 0.8; }
        
        th a { color: white !important; text-decoration: none; }
        th a:hover { text-decoration: underline; }

        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.85em; color: white; font-weight:bold; display: inline-block; min-width: 30px; text-align: center; }
        .badge-green { background: #27ae60; cursor: default; }
        .badge-disabled { background: #95a5a6; cursor: not-allowed; }
        .btn-toggle-red { background: #e74c3c; cursor: pointer; transition: 0.3s; color: white; }
        .btn-toggle-green { background: #27ae60; cursor: pointer; transition: 0.3s; color: white; }
        /* Botão Cinza para 'Não' no cancelamento */
        .btn-toggle-gray { background: #95a5a6; cursor: pointer; transition: 0.3s; color: white; }

        .pagination { margin-top: 15px; display: flex; gap: 5px; justify-content: center; align-items: center; }
        .pagination a { padding: 8px 12px; background: #eee; text-decoration: none; color: #333; border-radius: 4px; }
        .pagination span { font-weight: bold; color: #555; }

        /* MODAL */
        .modal-overlay { display: none; position: fixed !important; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 600px; max-width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); display: flex; flex-direction: column; gap: 15px; max-height: 90vh; overflow-y: auto;}
        .modal-header { display: flex; justify-content: space-between; font-weight: bold; font-size: 18px; color: #2c3e50; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        textarea { resize: vertical; min-height: 150px; width: 100%; box-sizing: border-box; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
        
        .btn-cancel { background: #95a5a6; color: white; border: none; }
        .btn-just-confirm { background: #3498db; color: white; border: none; }
        .btn-just-confirm:hover { background: #2980b9; }

        #manualPhoneInput { border: 2px solid #e74c3c; background: #fff5f5; color: #c0392b; font-weight: bold; width: 100%; }
        .key-input { margin-bottom: 5px; border: 1px solid #e74c3c; font-weight: bold; width: 100%; background: #fff5f5; padding: 8px; }
        .key-input.loaded { background: #f0fff4; border-color: #27ae60; }

        .select-row { transform: scale(1.3); cursor: pointer; }
        #bulkActions { position: fixed; bottom: 20px; right: 20px; background: #2c3e50; color: white; padding: 15px 25px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); display: none; align-items: center; gap: 15px; z-index: 2000; }
        .btn-download { background: #f1c40f; color: #2c3e50; border: none; padding: 8px 15px; border-radius: 20px; font-weight: bold; margin-left: 5px; cursor: pointer; }
        .btn-print { background: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 20px; font-weight: bold; margin-left: 5px; cursor: pointer; }
        .btn-manual { background: #9b59b6; color: white; border: none; padding: 8px 15px; border-radius: 20px; font-weight: bold; margin-left: 5px; cursor: pointer; }
        
        .column-toggle { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 5px; padding: 10px; background: #fafafa; border: 1px solid #eee; margin-top: 10px; font-size: 11px;}
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Gestão de Pedidos & Estoque</h1>

        <div class="dashboard-grid">
            <div class="card">
                <h3>
                    📦 Estoque Disponível
                    <button class="btn-danger-outline" onclick="validateStock()" title="Verificar API e remover chaves inválidas" style="font-size: 11px; padding: 2px 8px;">🧹 Validar Chaves</button>
                </h3>
                <div id="inventoryDisplay">Carregando...</div>
                <hr style="border:0; border-top:1px solid #eee; margin:10px 0;">
                <form id="addKeysForm" style="display:flex; gap:10px; align-items:flex-start;">
                    <select id="prodKeySelect" required>
                        <option value="">Selecione Produto...</option>
                        <option value="Office 2024">Office 2024</option>
                        <option value="Office 2021">Office 2021</option>
                        <option value="Office 2019">Office 2019</option>
                        <option value="Office 365">Office 365</option>
                        <option value="Project 2021">Project 2021</option>
                        <option value="Visio 2021">Visio 2021</option>
                    </select>
                    <textarea id="bulkKeys" placeholder="Cole as chaves aqui" style="min-height:35px; height:35px; flex-grow:1;"></textarea>
                    <button type="submit" class="btn-action">➕ Add</button>
                </form>
            </div>

            <div class="card">
                <h3>📥 Importar Pedidos</h3>
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
            
            <div class="filter-group"><label>Loja:</label>
                <select name="filter_store">
                    <option value="">Todas</option>
                    <?php foreach($storeList as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>" <?php if($filterStore===$s) echo 'selected'; ?>><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group"><label>Cliente:</label><input type="text" name="filter_client" placeholder="Nome..." value="<?php echo htmlspecialchars($filterClient); ?>" style="width:120px;"></div>
            <div class="filter-group"><label>Order ID:</label><input type="text" name="filter_order_id" placeholder="Ex: 702-..." value="<?php echo htmlspecialchars($filterOrderId); ?>" style="width:120px;"></div>

            <div class="filter-group"><label>Status Pedido:</label>
                <select name="filter_cancel">
                    <option value="false" <?php if($filterCancel==='false') echo 'selected'; ?>>✅ Ativos</option>
                    <option value="true" <?php if($filterCancel==='true') echo 'selected'; ?>>🚫 Cancelados</option>
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="filter-group"><label>WhatsApp:</label><select name="filter_zap"><option value="">Todos</option><option value="true" <?php if($filterZap==='true')echo'selected';?>>✅ Enviado</option><option value="false" <?php if($filterZap==='false')echo'selected';?>>❌ Pendente</option></select></div>
            <div class="filter-group"><label>Conf. Receb:</label><select name="filter_transp"><option value="">Todos</option><option value="true" <?php if($filterTransp==='true')echo'selected';?>>✅ Sim</option><option value="false" <?php if($filterTransp==='false')echo'selected';?>>❌ Não</option></select></div>
            <div class="filter-group"><label>Instalação:</label><select name="filter_inst"><option value="">Todos</option><option value="true" <?php if($filterInst==='true')echo'selected';?>>✅ OK</option><option value="false" <?php if($filterInst==='false')echo'selected';?>>❌ Pendente</option></select></div>
            <div class="filter-group"><label>Avaliação:</label><select name="filter_nota"><option value="">Todos</option><option value="true" <?php if($filterNota==='true')echo'selected';?>>✅ Pedir</option><option value="false" <?php if($filterNota==='false')echo'selected';?>>❌ Não precisa</option></select></div>
            
            <div class="filter-group" style="justify-content: flex-end;"><button type="submit" class="btn-action">🔍 Filtrar</button></div>
            <?php if($startDate||$filterZap||$filterTransp||$filterInst||$filterNota||$filterStore||$filterOrderId||$filterClient||$filterCancel!=='false'): ?><div class="filter-group" style="justify-content: flex-end;"><a href="index.php"><button type="button" class="btn-clear">Limpar</button></a></div><?php endif; ?>
        </form>

        <details>
            <summary style="cursor:pointer; font-weight:bold; margin-bottom:10px; color:#00b894;">👁️ Mostrar/Esconder Colunas</summary>
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
                            
                            <th>Valor</th>
                            <th>Cidade</th>
                            <th>Estado</th>
                            <th>CEP</th>
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
                            $valCOD = $order['cod-collectible-amount'] ?? 0;
                            
                            $sentZap = $order['enviado_whatsapp'] ?? false;
                            $sentTransp = $order['enviado_transportadora'] ?? false;
                            $instOk = $order['instalacao_ok'] ?? false;
                            $pedirNota = $order['pedir_nota'] ?? false;
                            $isCanceled = $order['status_cancelado'] ?? false; 
                            $hasPhone = strlen($cleanPhone) > 8;

                            $jsData = json_encode([ "phone" => $cleanPhone, "name" => $buyerName, "orderId" => $orderId, "product" => $productName, "quantity" => $quantity ]);
                            $labelData = json_encode([ 'name' => $order['recipient-name']??$buyerName, 'addr1' => $order['ship-address-1']??'', 'addr2' => $order['ship-address-2']??'', 'city' => $order['ship-city']??'', 'state' => $order['ship-state']??'', 'zip' => $order['ship-postal-code']??'' ]);
                            
                            $rowClass = $isCanceled ? 'row-canceled' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td style="text-align:center;"><input type="checkbox" class="select-row" value="<?php echo $orderId; ?>" data-address='<?php echo htmlspecialchars($labelData, ENT_QUOTES, 'UTF-8'); ?>' data-product="<?php echo htmlspecialchars($productName); ?>" data-name="<?php echo htmlspecialchars($buyerName); ?>" onchange="updateBulkAction()"></td>
                            <td><?php echo htmlspecialchars($order['nome_loja'] ?? ''); ?></td>
                            <td><?php echo format_date($order['data_importacao'] ?? ''); ?></td>
                            <td><?php echo $orderId; ?></td>
                            <td><?php echo mb_strimwidth($buyerName, 0, 20, "..."); ?></td>
                            <td><?php echo htmlspecialchars($order['cpf'] ?? ''); ?></td>
                            <td><?php echo $cleanPhone; ?></td>
                            <td title="<?php echo $productName; ?>"><?php echo mb_strimwidth($productName, 0, 25, "..."); ?></td>
                            <td><?php echo $quantity; ?></td>
                            
                            <td style="text-align:center;"><?php if($sentZap): ?><span class="badge badge-green">Sim</span><?php elseif($hasPhone): ?><span class="badge btn-toggle-red" onclick='openModal(this, <?php echo $jsData; ?>, "whatsapp")'>Não</span><?php else: ?><span class="badge badge-disabled">Não</span><?php endif; ?></td>
                            
                            <td style="text-align:center;">
                                <?php if($sentTransp): ?>
                                    <span class="badge btn-toggle-green" onclick='toggleTransport(this, "<?php echo $orderId; ?>")'>Sim</span>
                                <?php elseif($hasPhone): ?>
                                    <span class="badge btn-toggle-red" onclick='openModal(this, <?php echo $jsData; ?>, "transport")'>Não</span>
                                <?php else: ?>
                                    <span class="badge badge-disabled">Não</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align:center;"><span class="badge <?php echo $instOk?'btn-toggle-green':'btn-toggle-red'; ?>" onclick="toggleGenericStatus(this, '<?php echo $orderId; ?>', 'instalacao_ok', <?php echo $instOk?'true':'false'; ?>)"><?php echo $instOk?'Sim':'Não'; ?></span></td>
                            <td style="text-align:center;"><span class="badge <?php echo $pedirNota?'btn-toggle-green':'btn-toggle-red'; ?>" onclick="toggleGenericStatus(this, '<?php echo $orderId; ?>', 'pedir_nota', <?php echo $pedirNota?'true':'false'; ?>)"><?php echo $pedirNota?'Sim':'Não'; ?></span></td>
                            
                            <td style="text-align:center;">
                                <span class="badge <?php echo $isCanceled ? 'btn-toggle-red' : 'btn-toggle-gray'; ?>" 
                                      style="background-color: <?php echo $isCanceled ? '#e74c3c' : '#95a5a6'; ?>; border-color: transparent;"
                                      onclick="toggleCancel(this, '<?php echo $orderId; ?>', <?php echo $isCanceled?'true':'false'; ?>)">
                                    <?php echo $isCanceled ? 'Sim' : 'Não'; ?>
                                </span>
                            </td>
                            
                            <td><?php echo format_money($valCOD); ?></td>
                            <td><?php echo htmlspecialchars($order['ship-city'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($order['ship-state'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($order['ship-postal-code'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <?php if($page > 1): ?>
                    <?php $q = $_GET; $q['page'] = $page-1; echo '<a href="?'.http_build_query($q).'">« Anterior</a>'; ?>
                <?php endif; ?>
                <span style="padding:0 15px;">Página <strong><?php echo $page; ?></strong> de <strong><?php echo $totalPages; ?></strong> (Total: <?php echo $totalRecords; ?>)</span>
                <?php if($page < $totalPages): ?>
                    <?php $q = $_GET; $q['page'] = $page+1; echo '<a href="?'.http_build_query($q).'">Próxima »</a>'; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="bulkActions">
        <span><span id="bulkCount">0</span> selecionados</span>
        <button class="btn-download" onclick="downloadAmazonFile()">📄 Arq. Envio</button>
        <button class="btn-print" onclick="printLabels()">🏷️ Etiquetas</button>
        <button class="btn-manual" onclick="printManuals()">🖨️ Manuais</button>
    </div>

    <div id="msgModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><span id="modalTitle">Enviar Mensagem</span><span style="cursor:pointer;" onclick="closeModal()">✕</span></div>
            <input type="hidden" id="modalPhone"><input type="hidden" id="rawName"><input type="hidden" id="rawOrder"><input type="hidden" id="rawProduct"><input type="hidden" id="rawActionType"> 
            <div class="form-group"><label>Para:</label> <div id="divPhoneDisplay"><span style="font-weight:bold" id="modalRecipientLabel"></span> <button onclick="enablePhoneEdit()" style="background:none;border:none;color:#00b894;cursor:pointer;text-decoration:underline;">(Editar)</button></div><div id="divPhoneEdit" style="display:none;"><input type="text" id="manualPhoneInput" onkeyup="syncPhone()"></div><div style="font-size:11px; color:#999;" id="modalProductLabel"></div></div>
            <div class="form-group"><label>Modelo:</label><select id="templateSelect" onchange="updatePreview()" style="width:100%"><option value="" data-slug="">-- Selecione --</option><?php if($templates): foreach($templates as $tpl): ?><option value="<?php echo htmlspecialchars(json_encode($tpl['content'])); ?>" data-slug="<?php echo htmlspecialchars($tpl['slug']); ?>"><?php echo htmlspecialchars($tpl['name']); ?></option><?php endforeach; endif; ?></select></div>
            <div id="keysWrapper" class="form-group"><label style="color:#e74c3c;">🔑 Chave(s):</label><div id="keysLoading" style="display:none; color:#e67e22; font-size:12px;">🔄 Buscando estoque...</div><div id="keysContainer"></div></div>
            <div class="form-group"><label>Preview:</label><textarea id="msgPreview"></textarea></div>
            <div class="modal-actions"><button class="btn-just-confirm" id="btnJustConfirm" onclick="confirmOnly()" style="display:none;">💾 Somente Confirmar</button><button class="btn-cancel" onclick="closeModal()">Cancelar</button><button onclick="sendFromModal()" id="btnConfirmSend">✈️ Enviar e Atualizar</button></div>
        </div>
    </div>

    <script>
        let currentBtnElement = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadInventory();
            const table = document.getElementById('mainTable'); if(!table) return;
            const headers = table.querySelectorAll('thead th'); const container = document.getElementById('colSwitches');
            const defaultCols = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]; 
            headers.forEach((th, index) => {
                const label = document.createElement('label'); const checkbox = document.createElement('input');
                checkbox.type = 'checkbox'; checkbox.checked = defaultCols.includes(index); checkbox.onchange = () => toggleColumn(index, checkbox.checked);
                label.appendChild(checkbox); label.appendChild(document.createTextNode(' ' + th.innerText)); container.appendChild(label); toggleColumn(index, checkbox.checked);
            });
        });

        async function loadInventory() {
            try { const res = await fetch('inventory_manager.php?action=count'); const data = await res.json(); const display = document.getElementById('inventoryDisplay'); if(data.success && Object.keys(data.data).length > 0) { display.innerHTML = ''; for (const [prod, count] of Object.entries(data.data)) { display.innerHTML += `<span class="inv-item">${prod}: ${count}</span>`; } } else { display.innerHTML = 'Sem chaves disponíveis.'; } } catch(e) {}
        }

        async function validateStock() {
            const btn = document.querySelector('.btn-danger-outline');
            const originalText = btn.innerText;
            if(!confirm("Isso irá verificar TODAS as chaves 'disponíveis' na API do PIDKey e remover as inválidas do banco.\n\nIsso pode demorar alguns minutos. Deseja continuar?")) return;
            btn.innerText = "⏳ Verificando..."; btn.disabled = true;
            try { const res = await fetch('validate_keys.php'); const data = await res.json(); if (data.success) { alert(data.message + "\n\nDetalhes no log do servidor."); loadInventory(); } else { alert("Erro: " + data.message); } } catch (e) { alert("Erro de conexão ao validar chaves."); } finally { btn.innerText = originalText; btn.disabled = false; }
        }

        document.getElementById('addKeysForm').addEventListener('submit', async function(e) {
            e.preventDefault(); const prod = document.getElementById('prodKeySelect').value; const keys = document.getElementById('bulkKeys').value; if(!prod || !keys) return;
            try { const res = await fetch('inventory_manager.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({product: prod, keys: keys}) }); const data = await res.json(); if(data.success) { alert(`Adicionadas ${data.count} chaves!`); document.getElementById('bulkKeys').value = ''; loadInventory(); } else { alert('Erro: ' + data.message); } } catch(e) { alert('Erro ao salvar'); }
        });

        function toggleColumn(index, isVisible) { const table = document.getElementById('mainTable'); const rows = table.rows; for (let i = 0; i < rows.length; i++) { const cell = rows[i].cells[index]; if (cell) cell.style.display = isVisible ? '' : 'none'; } }
        
        async function toggleGenericStatus(btn, orderId, column, currentState) {
            const newState = !currentState; const originalText = btn.innerText; btn.innerText = '...';
            try {
                const res = await fetch('update_process.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, column: column, value: newState }) });
                const data = await res.json();
                if(data.success) {
                    if (column === 'status_cancelado') { location.reload(); return; }
                    btn.innerText = newState ? 'Sim' : 'Não'; btn.className = newState ? 'badge btn-toggle-green' : 'badge btn-toggle-red';
                    btn.onclick = function() { toggleGenericStatus(this, orderId, column, newState); };
                } else { alert('Erro: ' + data.message); btn.innerText = originalText; }
            } catch(e) { alert('Erro: ' + e.message); btn.innerText = originalText; }
        }

        async function toggleCancel(btn, orderId, isCanceled) {
            const newState = !isCanceled;
            const msg = newState ? "Tem certeza que deseja CANCELAR este pedido?" : "Deseja REATIVAR este pedido?";
            if(!confirm(msg)) return;
            
            btn.innerText = '...';
            try {
                const res = await fetch('update_process.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, column: 'status_cancelado', value: newState }) });
                const data = await res.json();
                if(data.success) { location.reload(); } // Recarrega para aplicar o estilo riscado
                else { alert('Erro: ' + data.message); btn.innerText = isCanceled ? 'Sim' : 'Não'; }
            } catch(e) { alert('Erro: ' + e.message); }
        }

        async function toggleTransport(btn, orderId) {
            if(!confirm("Marcar como NÃO recebido?")) return;
            try {
                const res = await fetch('update_process.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, column: 'enviado_transportadora', value: false }) });
                const data = await res.json();
                if(data.success) { location.reload(); } else { alert('Erro: ' + data.message); }
            } catch(e) { alert('Erro: ' + e.message); }
        }
        
        function toggleSelectAll(source) { document.querySelectorAll('.select-row').forEach(cb => cb.checked = source.checked); updateBulkAction(); }
        function updateBulkAction() { const count = document.querySelectorAll('.select-row:checked').length; document.getElementById('bulkCount').innerText = count; document.getElementById('bulkActions').style.display = count > 0 ? 'flex' : 'none'; }
        function downloadAmazonFile() { const selected = document.querySelectorAll('.select-row:checked'); if(selected.length === 0) return; const today = new Date(); const shipDate = today.toISOString().split('T')[0]; const dateStr = String(today.getDate()).padStart(2,'0') + String(today.getMonth()+1).padStart(2,'0') + String(today.getFullYear()).slice(-2); let content = "order-id\torder-item-id\tquantity\tship-date\tcarrier-code\tcarrier-name\ttracking-number\tship-method\n"; let counter = 10; selected.forEach(cb => { counter++; const tracking = "WZP" + dateStr + counter; content += `${cb.value}\t\t\t${shipDate}\tOther\tWZPEXPRESS\t${tracking}\t\n`; }); const a = document.createElement('a'); a.href = window.URL.createObjectURL(new Blob([content], {type: 'text/plain'})); a.download = `ENVIO_AMAZON_${shipDate}.txt`; a.click(); }
        function printLabels() { const selected = document.querySelectorAll('.select-row:checked'); if (selected.length === 0) { alert("Selecione pedidos!"); return; } const style = `@page { size: A4; margin: 10mm; } body { font-family: Arial, sans-serif; margin: 0; padding: 0; } .a4-container { width: 210mm; height: 297mm; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(5, 1fr); gap: 5mm; padding: 5mm; box-sizing: border-box; page-break-after: always; } .label { border: 1px dashed #ccc; padding: 15px; display: flex; flex-direction: column; justify-content: center; font-size: 14px; line-height: 1.4; } .label strong { font-size: 16px; display: block; margin-bottom: 5px; } @media print { .label { border: 1px solid #000; } }`; let htmlContent = `<html><head><title>Etiquetas</title><style>${style}</style></head><body>`; const items = Array.from(selected); for (let i = 0; i < items.length; i += 10) { const chunk = items.slice(i, i + 10); htmlContent += '<div class="a4-container">'; chunk.forEach(checkbox => { try { const data = JSON.parse(checkbox.dataset.address); htmlContent += `<div class="label"><strong>DESTINATÁRIO:</strong>${data.name.toUpperCase()}<br>${data.addr1.toUpperCase()} ${data.addr2 ? '- ' + data.addr2.toUpperCase() : ''}<br>${data.city.toUpperCase()} - ${data.state.toUpperCase()}<br><strong>CEP: ${data.zip}</strong></div>`; } catch(e) {} }); htmlContent += '</div>'; } htmlContent += '</body></html>'; const w = window.open('', '_blank'); w.document.write(htmlContent); w.document.close(); setTimeout(() => { w.print(); }, 500); }
        async function printManuals() { const selected = document.querySelectorAll('.select-row:checked'); if(selected.length === 0) { alert('Selecione os pedidos!'); return; } const orderIds = Array.from(selected).map(cb => cb.value); let assignedKeys = {}; try { const res = await fetch('fetch_assigned_keys.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({order_ids: orderIds}) }); const data = await res.json(); if(data.success) assignedKeys = data.data; } catch(e) {} const style = `@page { size: A4; margin: 0; } body { font-family: 'Segoe UI', Arial; margin: 0; padding: 0; } .a4-page { width: 100%; height: 100vh; padding: 10mm; box-sizing: border-box; page-break-after: always; } .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px;} .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; text-transform: uppercase; } .product-title { text-align: center; font-size: 20px; font-weight: bold; color: #007bff; margin-bottom: 15px; } .instructions-section { background-color: #f9fcfd; padding: 15px; border: 1px solid #e1e8ed; border-radius: 8px; } .license-key-box { background: #fff; border: 2px dashed #007bff; padding: 10px; font-family: monospace; font-size: 20px; font-weight: bold; display: inline-block; } .footer { margin-top: 25px; text-align: center; font-size: 12px; border-top: 1px solid #eee; padding-top: 10px; }`; let html = `<html><head><title>Manuais</title><style>${style}</style></head><body>`; selected.forEach(cb => { const oid = cb.value; const name = cb.dataset.name; const prod = cb.dataset.product.toLowerCase(); const keys = assignedKeys[oid] || ["CHAVE NÃO ENCONTRADA"]; let link = "https://supersoftware.info/office/2024.zip"; let title = "OFFICE 2024 PRO PLUS"; if(prod.includes('2021')) { link = "https://supersoftware.info/office/2021.zip"; title = "OFFICE 2021 PRO PLUS"; } else if(prod.includes('2019')) { link = "https://supersoftware.info/office/2019.img"; title = "OFFICE 2019 PRO PLUS"; } html += `<div class="a4-page"><div class="header"><h1 class="company-name">Super Software</h1></div><div class="product-title">${title}</div><p>Olá, <strong>${name}</strong>. Pedido: <strong>${oid}</strong></p><div class="instructions-section"><h3>Instalação:</h3><ol><li>Baixe: <a href="${link}">${link}</a></li><li>Extraia e instale.</li><li>Insira a chave no Word.</li></ol></div><div style="text-align:center; margin-top:20px;"><div class="license-key-box">${keys.join('<br>')}</div></div><div class="footer">Suporte WhatsApp: (11) 93585-6950</div></div>`; }); html += '</body></html>'; const w = window.open('','_blank'); w.document.write(html); w.document.close(); setTimeout(() => w.print(), 800); }

        function enablePhoneEdit() { document.getElementById('divPhoneDisplay').style.display = 'none'; document.getElementById('divPhoneEdit').style.display = 'block'; document.getElementById('manualPhoneInput').focus(); }
        function syncPhone() { document.getElementById('modalPhone').value = document.getElementById('manualPhoneInput').value; }
        
        async function confirmOnly() {
            const btn = document.getElementById('btnJustConfirm'); const orderId = document.getElementById('rawOrder').value; const originalText = btn.innerText;
            if(!confirm("Deseja marcar como 'Confirmado' SEM enviar mensagem?")) return;
            btn.disabled = true; btn.innerText = 'Salvando...';
            try {
                const res = await fetch('update_process.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ order_id: orderId, column: 'enviado_transportadora', value: true }) });
                const data = await res.json();
                if(data.success) { location.reload(); } else { alert('Erro: ' + data.message); btn.disabled = false; btn.innerText = originalText; }
            } catch(e) { alert('Erro de conexão'); btn.disabled = false; btn.innerText = originalText; }
        }

        async function openModal(element, data, actionType) {
            currentBtnElement = element; document.getElementById('msgModal').style.display = 'flex';
            document.getElementById('divPhoneDisplay').style.display = 'flex'; document.getElementById('divPhoneEdit').style.display = 'none';
            document.getElementById('modalPhone').value = data.phone; document.getElementById('manualPhoneInput').value = data.phone;
            document.getElementById('rawName').value = data.name; document.getElementById('rawOrder').value = data.orderId;
            document.getElementById('rawProduct').value = data.product; document.getElementById('rawActionType').value = actionType;
            document.getElementById('modalRecipientLabel').innerText = data.name + " (" + data.phone + ")"; document.getElementById('modalProductLabel').innerText = data.product;
            const keysWrapper = document.getElementById('keysWrapper'); const keysContainer = document.getElementById('keysContainer');
            keysContainer.innerHTML = ''; document.getElementById('msgPreview').value = '';
            
            const btnJustConfirm = document.getElementById('btnJustConfirm');
            if (actionType === 'transport') {
                keysWrapper.style.display = 'none'; 
                document.getElementById('modalTitle').innerText = "Confirmar Recebimento"; 
                btnJustConfirm.style.display = 'inline-block';
                document.getElementById('btnConfirmSend').innerText = "Enviar";
            } else {
                keysWrapper.style.display = 'block'; 
                document.getElementById('modalTitle').innerText = "Enviar Entrega";
                btnJustConfirm.style.display = 'none';
                document.getElementById('btnConfirmSend').innerText = "Enviar e Atualizar";
                document.getElementById('keysLoading').style.display = 'block';
                const qty = parseInt(data.quantity) || 1;
                for(let i=0; i<qty; i++) { const inp = document.createElement('input'); inp.className = 'key-input'; inp.placeholder = 'Buscando...'; inp.oninput = updatePreview; keysContainer.appendChild(inp); }
                try {
                    const resKeys = await fetch('get_keys.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({product_name: data.product, quantity: qty}) });
                    const dKeys = await resKeys.json();
                    if(dKeys.success && dKeys.keys.length > 0) { dKeys.keys.forEach((k, idx) => { if(keysContainer.children[idx]) { keysContainer.children[idx].value = k; keysContainer.children[idx].classList.add('loaded'); } }); }
                } catch(e) {} finally { document.getElementById('keysLoading').style.display = 'none'; }
            }
            
            const select = document.getElementById('templateSelect'); select.selectedIndex = 0; const pName = data.product.toLowerCase();
            for(let i=0; i<select.options.length; i++){
                const slug = select.options[i].getAttribute('data-slug'); if(!slug) continue;
                const slugLower = slug.toLowerCase();
                if(actionType === 'transport' && slugLower === 'envio_msg_confirm_receb') { select.selectedIndex=i; break; }
                if(actionType === 'whatsapp') {
                    if (pName.includes('2024') && slugLower.includes('2024')) { select.selectedIndex=i; break; }
                    else if (pName.includes('2021') && slugLower.includes('2021')) { select.selectedIndex=i; break; }
                    else if (pName.includes('2019') && slugLower.includes('2019')) { select.selectedIndex=i; break; }
                    else if (pName.includes('365') && slugLower.includes('365')) { select.selectedIndex=i; break; }
                    else if (pName.includes('project') && slugLower.includes('project')) { select.selectedIndex=i; break; }
                    else if (pName.includes('visio') && slugLower.includes('visio')) { select.selectedIndex=i; break; }
                }
            }
            updatePreview();
        }

        function closeModal() { document.getElementById('msgModal').style.display='none'; }
        function updatePreview() {
            const sel = document.getElementById('templateSelect'); let txt = ""; try { txt = JSON.parse(sel.value); } catch(e) { txt = sel.value; } if(!txt) return;
            const keysInputs = document.querySelectorAll('.key-input'); let keysStr = "";
            let kArr = []; keysInputs.forEach((inp, i) => { if(inp.value) kArr.push(`Licença ${i+1}: ${inp.value}`); }); keysStr = kArr.join('\n');
            document.getElementById('msgPreview').value = txt.replace(/{{nome}}/gi, document.getElementById('rawName').value).replace(/{{pedido}}/gi, document.getElementById('rawOrder').value).replace(/{{chave}}/gi, keysStr || "[CHAVE]");
        }

        async function sendFromModal() {
            const btn = document.getElementById('btnConfirmSend');
            let phoneToSend = document.getElementById('modalPhone').value;
            if(document.getElementById('divPhoneEdit').style.display !== 'none') { phoneToSend = document.getElementById('manualPhoneInput').value; }
            const text = document.getElementById('msgPreview').value; const orderId = document.getElementById('rawOrder').value; const actionType = document.getElementById('rawActionType').value;
            if(!text) { alert('Selecione template'); return; }
            if(actionType === 'whatsapp' && text.includes('[CHAVE]') && !confirm("CHAVE VAZIA? Enviar assim mesmo?")) return;
            btn.disabled = true; btn.innerText = 'Enviando...';
            try {
                let keys = []; if(actionType == 'whatsapp') { document.querySelectorAll('.key-input').forEach(i => { if(i.value.length>5) keys.push(i.value.trim()); }); }
                const rMsg = await fetch('enviar_msg.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({phone: phoneToSend, text}) });
                const dMsg = await rMsg.json(); 
                if(!dMsg.success) {
                    if(dMsg.message.toLowerCase().includes('not on whatsapp') || dMsg.message.toLowerCase().includes('invalid')) {
                        alert('❌ Erro: Número inválido! Corrija o campo em destaque e tente novamente.'); enablePhoneEdit(); throw new Error("Número incorreto. Por favor corrija.");
                    } else { throw new Error(dMsg.message); }
                }
                
                if (actionType === 'transport') { alert('✅ Mensagem enviada com sucesso!'); closeModal(); return; }

                const col = 'enviado_whatsapp';
                const rUp = await fetch('update_process.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ order_id: orderId, column: col, value: true, used_keys: keys, new_phone: phoneToSend }) });
                const dUp = await rUp.json();
                if(dUp.success) location.reload(); else alert('Erro BD: ' + dUp.message);
            } catch(e) { alert(e.message); } finally { btn.disabled=false; btn.innerText=(actionType==='transport'?'Enviar':'Enviar e Atualizar'); }
        }
        window.onclick = function(e) { if(e.target == document.getElementById('msgModal')) closeModal(); }
    </script>
</body>
</html>