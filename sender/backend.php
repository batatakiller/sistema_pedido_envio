<?php
// backend.php
header('Content-Type: application/json');

// --- CARREGAMENTO DO PHPMAILER ---
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- CONFIGURAÇÕES ---
// --- CONFIGURAÇÕES ---
define('UAZAPI_URL', 'https://superbot.uazapi.com/send/text');
define('UAZAPI_TOKEN', '088c5853-8c76-4025-9a31-8376f13ce9fb'); // Coloque seu token real aqui
define('GMAIL_USER', 'sacsupersoftware@gmail.com');
define('GMAIL_PASS', 'jalg vtwm rluq kozn'); // Senha de App do Google
define('GEMINI_API_KEY', 'AIzaSyBSmCi9-Qg3jI0s4jNdnFw_1wKkNu-bs6Y'); // <--- PEGUE NO AI STUDIO
define('GEMINI_MODEL', 'gemma-3-12b-it');
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit(json_encode(['success' => false, 'message' => 'Sem dados']));

// Dados
$cpf     = $input['cpf'] ?? '';
$nome    = $input['nome'];
$tipo    = strtolower(trim($input['tipo']));
$contato = trim($input['contato']);
$pedido  = $input['pedido'] ?? '';
$produto = $input['produto'] ?? '';
$msgBase = $input['mensagemBase']; 
$assuntoBase = $input['assuntoBase']; 

// 1. Substituição de Variáveis (Base para ambos)
$vars = ['{nome}', '{pedido}', '{produto}', '{cpf}'];
$vals = [$nome, $pedido, $produto, $cpf];

$msgPronta = str_replace($vars, $vals, $msgBase);
$assuntoFinal = str_replace($vars, $vals, $assuntoBase);

$response = ['success' => false, 'message' => '', 'ai_used' => false];

try {
    // --- LÓGICA WHATSAPP (COM IA) ---
    if ($tipo === 'celular') {
        $numero = preg_replace('/[^0-9]/', '', $contato);
        if (substr($numero, 0, 2) !== '55') $numero = '55' . $numero;

        // Tenta reescrever. Se falhar, usa a $msgPronta original
        if (!empty(GEMINI_API_KEY)) {
            $msgIA = reescreverComIA($msgPronta, $nome, $pedido);
            
            // Verificação: Se a IA devolveu o mesmo texto ou erro, avisamos no log
            if ($msgIA !== $msgPronta) {
                $msgFinal = $msgIA;
                $response['ai_used'] = true;
            } else {
                $msgFinal = $msgPronta; // Fallback
            }
        } else {
            $msgFinal = $msgPronta;
        }

        if (enviarUazapi($numero, $msgFinal)) {
            $response['success'] = true;
            $response['message'] = "WhatsApp enviado para $nome";
            // Retorna um trecho para você conferir visualmente a mudança
            $response['preview'] = substr($msgFinal, 0, 40) . "..."; 
        } else {
            throw new Exception("Erro na API Uazapi");
        }

    // --- LÓGICA EMAIL (SEM IA - ORIGINAL DO MODAL) ---
    } elseif ($tipo === 'email') {
        // Usa estritamente a $msgPronta (apenas variáveis trocadas)
        if (enviarGmail($contato, $nome, $assuntoFinal, $msgPronta)) {
            $response['success'] = true;
            $response['message'] = "Email enviado para $nome";
            $response['preview'] = "Original mantido.";
        } else {
            throw new Exception("Erro no SMTP Gmail");
        }
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


// --- FUNÇÕES ---

function reescreverComIA($textoOriginal, $cliente, $ped) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.GEMINI_MODEL.':generateContent?key=' . GEMINI_API_KEY;
    
    $prompt = "Aja como um gerador de texto silencioso. \n" .
              "TAREFA: Reescreva a mensagem abaixo para variar o padrão (anti-spam), mantendo o tom profissional.\n" .
              "DADOS PROIBIDOS DE MUDAR: Nome '$cliente', Pedido '$ped' (se houver) e Produto.\n" .
              "REGRAS:\n" .
              "1. Retorne APENAS o texto final.\n" .
              "2. NÃO escreva 'Aqui está' ou introduções.\n" .
              "Mensagem: \"$textoOriginal\"";

    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // --- PROTEÇÃO CONTRA TRAVAMENTO (TIMEOUTS) ---
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Max 3s para conectar
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);        // Max 6s esperando a resposta
    // ---------------------------------------------
    
    $result = curl_exec($ch);
    $erro = curl_error($ch); // Pega erro se houver
    curl_close($ch);
    
    // Se deu erro de timeout ou conexão, retorna original IMEDIATAMENTE
    if ($erro) return $textoOriginal;
    
    $json = json_decode($result, true);
    
    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        $respostaIA = trim($json['candidates'][0]['content']['parts'][0]['text']);
        $respostaIA = str_replace(['Aqui está:', 'Opção 1:', '"'], '', $respostaIA);
        return trim($respostaIA);
    }
    
    return $textoOriginal;
}

function enviarUazapi($numero, $texto) {
    $curl = curl_init();
    $data = ["number" => $numero, "text" => $texto];
    
    curl_setopt_array($curl, [
        CURLOPT_URL => UAZAPI_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "token: " . UAZAPI_TOKEN],
        
        // --- PROTEÇÃO CONTRA TRAVAMENTO ---
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10, 
        // ----------------------------------
    ]);
    
    $res = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Consideramos sucesso 200 ou 201
    return ($httpCode >= 200 && $httpCode < 300);
}
function enviarGmail($email, $nome, $assunto, $corpo) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = GMAIL_USER;
        $mail->Password = GMAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(GMAIL_USER, 'Atendimento');
        $mail->addAddress($email, $nome);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body = nl2br($corpo);
        $mail->send();
        return true;
    } catch (Exception $e) { return false; }
}
?>