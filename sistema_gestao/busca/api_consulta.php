<?php
// busca/api_consulta.php
header('Content-Type: application/json');
require '../config.php';

$cpf = preg_replace('/[^0-9]/', '', $_GET['cpf'] ?? '');

if (!$cpf) {
    echo json_encode(['success' => false, 'msg' => 'CPF vazio']);
    exit;
}

$url = "https://api.workconsultoria.com/api/v1/consults/gate_1/cpf/?cpf=" . $cpf;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $WORK_HEADERS);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $json = json_decode($response, true);
    
    $telefonesEncontrados = [];
    $emailsEncontrados = [];

    // --- FILTRO DE CELULAR (POR MÁSCARA) ---
    if (isset($json['telefones']) && is_array($json['telefones'])) {
        foreach ($json['telefones'] as $t) {
            // Pega o número independente da estrutura
            $numero = is_array($t) ? ($t['telefone'] ?? '') : $t;
            
            // Limpa tudo que não é número
            $limpo = preg_replace('/[^0-9]/', '', $numero);

            // Remove 55 do início se houver (para padronizar)
            if (strlen($limpo) > 11 && substr($limpo, 0, 2) === '55') {
                $limpo = substr($limpo, 2);
            }

            // REGRA CELULAR BRASIL:
            // 11 dígitos (2 DDD + 9 números) e o primeiro digito do numero é '9'
            // Ex: 11 9 8888 7777
            if (strlen($limpo) === 11 && $limpo[2] === '9') {
                $telefonesEncontrados[] = $limpo;
            }
        }
    }

    // --- FILTRO DE EMAIL ---
    if (isset($json['emails']) && is_array($json['emails'])) {
        foreach ($json['emails'] as $e) {
            $mail = is_array($e) ? ($e['email'] ?? '') : $e;
            if (filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $emailsEncontrados[] = $mail;
            }
        }
    }

    echo json_encode([
        'success' => true,
        // array_values reseta as chaves para evitar JSON estranho no JS
        'telefones' => array_values(array_unique($telefonesEncontrados)),
        'emails' => array_values(array_unique($emailsEncontrados))
    ]);

} else {
    echo json_encode(['success' => false, 'msg' => "Erro API: $httpCode"]);
}
?>