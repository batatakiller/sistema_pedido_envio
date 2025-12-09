<?php
// Arquivo: enviar_msg.php
header('Content-Type: application/json');

// Recebe o JSON do Javascript
$input = json_decode(file_get_contents('php://input'), true);
$phoneRaw = $input['phone'] ?? '';
$messageText = $input['text'] ?? '';

// Validação básica
if (empty($phoneRaw) || empty($messageText)) {
    echo json_encode(['success' => false, 'message' => 'Erro: Telefone ou mensagem vazios.']);
    exit;
}

// Limpa o telefone
$phone = preg_replace('/[^0-9]/', '', $phoneRaw);
// Adiciona 55 se parecer ser um número BR sem DDI
if (strlen($phone) >= 10 && strlen($phone) <= 11) {
    $phone = '55' . $phone;
}

// --- Disparo UAZAPI ---
$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => "https://superbot.uazapi.com/send/text",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    'number' => $phone,
    'text' => $messageText
  ]),
  CURLOPT_HTTPHEADER => [
    "Accept: application/json",
    "Content-Type: application/json",
    "token: 088c5853-8c76-4025-9a31-8376f13ce9fb"
  ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
    echo json_encode(['success' => false, 'message' => "Erro cURL: " . $err]);
} else {
    // Verifica se o status HTTP é 200 ou 201
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true, 'message' => 'Enviado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro API: ' . $response]);
    }
}
?>