<?php
// supa/exportar_para_sender.php
// CORREÇÃO: UTF-8 na saída

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(0); 
header('Content-Type: application/json; charset=utf-8'); // Header UTF-8

try {
    if (file_exists('../config.php')) require_once '../config.php';
    else { /* Fallback... */ 
        if (!defined('SB_URL')) define('SB_URL', 'https://qoobmxjzcjtkpezajbbv.supabase.co');
        if (!defined('SB_KEY')) define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];

    if (empty($ids)) throw new Exception('Nenhum pedido selecionado.');

    $idsString = implode(',', $ids);
    $urlSupa = SB_URL . "/rest/v1/amazon_orders_raw?order-id=in.(" . urlencode($idsString) . ")";

    $ch = curl_init($urlSupa);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: ".SB_KEY, "Authorization: Bearer ".SB_KEY]);
    // Força encoding na leitura se necessário (geralmente Supabase já manda UTF-8)
    $res = curl_exec($ch);
    curl_close($ch);
    $pedidos = json_decode($res, true);

    $listaFinal = [];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    $apiUrl = $baseUrl . "/busca/api_consulta.php";

    foreach ($pedidos as $p) {
        $cpf = preg_replace('/[^0-9]/', '', $p['cpf'] ?? '');
        $nome = $p['buyer-name'];
        $pedidoId = $p['order-id'];
        $produto = $p['product-name'];
        
        $emails = [];
        $zaps = [];
        $statusBusca = "Aguardando";

        if (!empty($cpf)) {
            $tentativa = 0; $sucesso = false;
            while ($tentativa < 3 && !$sucesso) {
                $chBusca = curl_init();
                curl_setopt($chBusca, CURLOPT_URL, $apiUrl . "?cpf=" . $cpf);
                curl_setopt($chBusca, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chBusca, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chBusca, CURLOPT_TIMEOUT, 20);
                $jsonRaw = curl_exec($chBusca);
                $httpCode = curl_getinfo($chBusca, CURLINFO_HTTP_CODE);
                curl_close($chBusca);

                if ($httpCode == 200 && $jsonRaw) {
                    $dados = json_decode($jsonRaw, true);
                    if ($dados && ($dados['success'] ?? false)) {
                        $sucesso = true;
                        if (!empty($dados['emails'])) $emails = $dados['emails'];
                        if (!empty($dados['telefones'])) $zaps = $dados['telefones'];
                        $statusBusca = "✅ Encontrado";
                    }
                }
                $tentativa++;
                if(!$sucesso) sleep(1);
            }
            if (!$sucesso) $statusBusca = "❌ Erro Busca";
            elseif (empty($emails) && empty($zaps)) $statusBusca = "⚠️ Sem contatos";
        } else {
            $statusBusca = "❌ Sem CPF";
        }

        $emails = array_unique($emails);
        $zaps = array_unique($zaps);

        foreach ($emails as $e) $listaFinal[] = criarItem($p, $e, 'email', $statusBusca);
        foreach ($zaps as $z) $listaFinal[] = criarItem($p, $z, 'celular', $statusBusca);
        
        if (empty($emails) && empty($zaps)) {
             $listaFinal[] = criarItem($p, "Não encontrado", 'erro', $statusBusca);
        }
    }

    // Saída com UNESCAPED_UNICODE para o JS ler os acentos corretamente
    echo json_encode(['success' => true, 'data' => $listaFinal], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function criarItem($p, $contato, $tipo, $origem) {
    return [
        'pedido' => $p['order-id'],
        'nome' => $p['buyer-name'], // Nome deve vir UTF-8 do banco
        'produto' => $p['product-name'],
        'cpf' => $p['cpf'],
        'tipo' => $tipo,
        'contato' => $contato,
        'origem' => $origem,
        'mensagemBase' => '', 
        'assuntoBase' => ''
    ];
}
?>