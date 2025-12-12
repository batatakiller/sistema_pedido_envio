<?php
// sender/backend.php
// CORREÇÃO: Codificação UTF-8 Forçada para corrigir acentos (Cl√≥vis -> Clóvis)

header('Content-Type: application/json; charset=utf-8'); // Header explícito

// Carrega config
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    echo json_encode(['success' => false, 'message' => 'Erro Crítico: config.php ausente']);
    exit;
}

// Bibliotecas de Email
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit(json_encode(['success' => false, 'message' => 'Sem dados']));

$nome    = $input['nome'] ?? 'Cliente';
$tipo    = $input['tipo'] ?? '';
$contato = $input['contato'] ?? '';
$pedido  = $input['pedido'] ?? '';
$produto = $input['produto'] ?? '';
$msgBase = $input['mensagemBase'] ?? '';
$assunto = $input['assuntoBase'] ?? 'Informação Pedido';

// Substituição de variáveis
$msgFinal = str_replace(
    ['{nome}', '{pedido}', '{produto}'], 
    [$nome, $pedido, $produto], 
    $msgBase
);
$assuntoFinal = str_replace(['{nome}', '{pedido}', '{produto}'], [$nome, $pedido, $produto], $assunto);

$response = ['success' => false, 'message' => '', 'ai_used' => false];

try {
    // === WHATSAPP ===
    if ($tipo === 'celular') {
        
        // IA (Opcional) - Ajustada para gemini-2.5-flash-lite conforme seu teste
        if (defined('GEMINI_KEY') && !empty(GEMINI_KEY)) {
             $urlIA = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . GEMINI_KEY;
             
             // Prompt otimizado
             $prompt = "Atue como suporte. Reescreva esta mensagem para WhatsApp de forma cordial e direta, mantendo os dados ($pedido, $produto): \"$msgFinal\". Retorne APENAS o texto.";
             
             $dataIA = ["contents" => [["parts" => [["text" => $prompt]]]]];
             
             $ch = curl_init($urlIA);
             curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
             curl_setopt($ch, CURLOPT_POST, true);
             // JSON_UNESCAPED_UNICODE evita problemas com acentos na IA
             curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataIA, JSON_UNESCAPED_UNICODE));
             curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
             curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
             curl_setopt($ch, CURLOPT_TIMEOUT, 10);
             
             $resIA = curl_exec($ch);
             curl_close($ch);
             
             $jsonIA = json_decode($resIA, true);
             if (isset($jsonIA['candidates'][0]['content']['parts'][0]['text'])) {
                 $textoIA = trim($jsonIA['candidates'][0]['content']['parts'][0]['text']);
                 // Remove aspas que a IA as vezes coloca
                 $msgFinal = trim($textoIA, '"\'');
                 $response['ai_used'] = true;
             }
        }

        // ENVIO UAZAPI (COM CORREÇÃO DE ENCODING)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => UAZAPI_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            // JSON_UNESCAPED_UNICODE é vital aqui para o nome sair certo
            CURLOPT_POSTFIELDS => json_encode(['number' => $contato, 'text' => $msgFinal], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json; charset=utf-8", // Header explícito para a API
                "token: " . UAZAPI_TOKEN
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15
        ]);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http >= 200 && $http < 300) {
            $response['success'] = true;
            $response['message'] = "WhatsApp enviado";
        } else {
            throw new Exception("Erro Uazapi ($http): $res");
        }

    // === EMAIL ===
    } elseif ($tipo === 'email') {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USER;
        $mail->Password = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8'; // Garante UTF-8 no email
        
        $mail->setFrom(MAIL_USER, 'Atendimento');
        $mail->addAddress($contato, $nome);
        $mail->isHTML(true);
        $mail->Subject = $assuntoFinal;
        $mail->Body = nl2br($msgFinal);
        $mail->AltBody = strip_tags($msgFinal);
        $mail->send();
        
        $response['success'] = true;
        $response['message'] = "Email enviado";
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Retorno JSON com encoding correto
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>