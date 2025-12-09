<?php
// --- CONFIGURAÇÕES DE ACESSO (Mantenha atualizado) ---
$HEADERS = [
    'access-token: A06U3OW6SYcE_q37sQ3yoA',
    'client: vGfsGMcM59UuteKMf5yrEw',
    'uid: batatoads',
    'token-type: Bearer',
    // O Cookie é essencial para o Cloudflare não bloquear
    'Cookie: cf_clearance=DJgGD3geOkcVDZCYafNQvyNeDel_GbsmLWchdX8h120-1765318471-1.2.1.1-BB1ae3BlwOnH8OVf5vx_oOV5QLWSJfxuawdd4PU866UfVdAb5pVhoKjRd8XDQxW.x8a3NRxNi_n1JjkKjH.PkG4OsaMpBVUA34wN3c8e9P_5aklWmyOk0l9B.2zV2alo8nMlSGtLPfjO_8XpOokvbXFrxLYF3Pg52170_DKypNUFv06exTdcEctbh33mzLzYM1Ho1NBxtBRjdYKShQobxFTr6l_EhrbowssGf3DTydE',
    'Origin: https://app.workconsultoria.com',
    'Referer: https://app.workconsultoria.com/',
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'
];

$resultado = null;
$erro = null;

// Verifica se o usuário clicou em "Buscar"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cpf'])) {
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']); // Remove pontos e traços
    
    if (strlen($cpf) > 0) {
        $url = "https://api.workconsultoria.com/api/v1/consults/gate_1/cpf/?cpf=" . $cpf;

        // Inicia o cURL (o navegador do PHP)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $HEADERS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita erro de SSL na hospedagem
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $json = json_decode($response, true);
            if ($json) {
                $resultado = $json;
            } else {
                $erro = "Recebemos os dados, mas não é um JSON válido: " . substr($response, 0, 100);
            }
        } elseif ($httpCode == 401 || $httpCode == 403) {
            $erro = "Erro $httpCode: Tokens expirados ou Bloqueio. Atualize as chaves no código PHP.";
        } else {
            $erro = "Erro inesperado (Código $httpCode).";
        }
    } else {
        $erro = "Por favor, digite um CPF válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta CPF - WorkConsultoria</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; padding-top: 50px; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 800px; }
        h1 { color: #333; text-align: center; }
        .form-group { display: flex; gap: 10px; margin-bottom: 20px; }
        input { flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 16px; }
        button { padding: 12px 25px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #0056b3; }
        .erro { background: #ffe6e6; color: #d63031; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; border: 1px solid #ffcccc; }
        .resultado { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 5px; padding: 20px; overflow-x: auto; }
        pre { white-space: pre-wrap; word-wrap: break-word; color: #2d3436; font-size: 14px; }
        .card { background: white; border-left: 5px solid #007bff; padding: 15px; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .label { font-weight: bold; color: #636e72; font-size: 0.9em; }
        .value { font-size: 1.1em; color: #2d3436; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔎 Consultar CPF</h1>
    
    <form method="POST" class="form-group">
        <input type="text" name="cpf" placeholder="Digite o CPF (apenas números)" required value="<?php echo isset($_POST['cpf']) ? $_POST['cpf'] : ''; ?>">
        <button type="submit">BUSCAR</button>
    </form>

    <?php if ($erro): ?>
        <div class="erro">⚠️ <?php echo $erro; ?></div>
    <?php endif; ?>

    <?php if ($resultado): ?>
        <div class="resultado">
            <h3>Dados Encontrados:</h3>
            
            <?php if (isset($resultado['DadosBasicos'])): ?>
                <div class="card">
                    <div><span class="label">NOME:</span> <br> <span class="value"><?php echo $resultado['DadosBasicos']['nome']; ?></span></div>
                    <br>
                    <div><span class="label">NASCIMENTO:</span> <span class="value"><?php echo $resultado['DadosBasicos']['dataNascimento']; ?></span></div>
                    <div><span class="label">MÃE:</span> <span class="value"><?php echo $resultado['DadosBasicos']['nomeMae']; ?></span></div>
                    <div><span class="label">SITUAÇÃO:</span> <span class="value"><?php echo $resultado['DadosBasicos']['situacaoCadastral']['descricaoSituacaoCadastral']; ?></span></div>
                </div>
            <?php endif; ?>

            <?php if (isset($resultado['DadosEconomicos'])): ?>
                <div class="card">
                    <div><span class="label">RENDA ESTIMADA:</span> <span class="value">R$ <?php echo $resultado['DadosEconomicos']['renda']; ?></span></div>
                    <div><span class="label">PODER AQUISITIVO:</span> <span class="value"><?php echo $resultado['DadosEconomicos']['poderAquisitivo']['poderAquisitivoDescricao']; ?></span></div>
                </div>
            <?php endif; ?>

            <hr>
            <details>
                <summary style="cursor:pointer; color:#007bff;">Ver JSON Completo (Dados Técnicos)</summary>
                <pre><?php echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            </details>
        </div>
    <?php endif; ?>
</div>

</body>
</html>