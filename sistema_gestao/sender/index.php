<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Disparador Sender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .console { background: #000; color: #0f0; font-family: monospace; height: 450px; overflow-y: auto; padding: 15px; border-radius: 8px; font-size: 13px; border: 1px solid #444; }
        .log-email { color: #64b5f6; } 
        .log-zap { color: #69f0ae; }
        .log-error { color: #ff5252; } 
        .log-info { color: #ffff8d; }
    </style>
</head>
<body class="bg-dark text-light">

<div class="container mt-4">
    <div class="card bg-secondary text-white shadow-lg">
        <div class="card-header bg-black d-flex justify-content-between align-items-center">
            <h5 class="m-0">🚀 Disparador Sender</h5>
            <span id="badgeStatus" class="badge bg-warning text-dark">Aguardando</span>
        </div>
        <div class="card-body">
            
            <div class="mb-3">
                <label class="fw-bold">Assunto (Emails):</label>
                <input type="text" id="assuntoBase" class="form-control" value="Informações sobre seu Pedido">
            </div>

            <div class="mb-3">
                <label class="fw-bold">Mensagem (WhatsApp será reescrito pela IA):</label>
                <textarea id="msgBase" class="form-control" rows="5">Olá {nome}, tudo bem?
Recebemos seu pedido {pedido} referente ao item: {produto}.
Precisamos confirmar o recebimento. Responda SIM.</textarea>
            </div>

            <div class="row align-items-end mb-4">
                <div class="col-md-4">
                    <label>Delay Zap (Segundos):</label>
                    <div class="input-group">
                        <input type="number" id="minDelay" class="form-control" value="10">
                        <input type="number" id="maxDelay" class="form-control" value="20">
                    </div>
                </div>
                <div class="col-md-8 d-flex gap-2">
                    <button id="btnStart" class="btn btn-success flex-grow-1 fw-bold p-3" onclick="iniciarTudo()">▶ INICIAR</button>
                    <button id="btnStop" class="btn btn-danger fw-bold d-none" onclick="parar()">⏹ PARAR</button>
                </div>
            </div>

            <div class="progress mb-2" style="height: 25px;">
                <div id="progressBar" class="progress-bar bg-info" style="width: 0%">0%</div>
            </div>

            <div id="consoleLog" class="console"><div>> Aguardando...</div></div>
        </div>
    </div>
</div>

<script>
    let abortar = false;
    let dadosCarregados = [];

    // LÊ DADOS DO SUPA
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto') === 'true') {
            const rawData = localStorage.getItem('dadosSender');
            if (rawData) {
                dadosCarregados = JSON.parse(rawData);
                
                // Filtra itens inválidos (erro) para não contar no log inicial
                const validos = dadosCarregados.filter(i => i.tipo !== 'erro');
                
                if (validos.length > 0) {
                    log(`📥 <b>${validos.length}</b> contatos carregados.`, "log-info");
                    log(`👉 Clique em INICIAR para disparar.`, "log-info");
                } else {
                    log(`⚠️ Lista carregada, mas sem contatos válidos.`, "log-error");
                    document.getElementById('btnStart').disabled = true;
                }
            } else {
                log("❌ Nenhum dado no LocalStorage.", "log-error");
            }
        }
    });

    function parar() { abortar = true; document.getElementById('btnStop').disabled = true; }

    async function iniciarTudo() {
        if (dadosCarregados.length === 0) return;

        const assunto = document.getElementById('assuntoBase').value;
        const msg = document.getElementById('msgBase').value;

        // UI
        abortar = false;
        document.getElementById('btnStart').classList.add('d-none');
        document.getElementById('btnStop').classList.remove('d-none');
        document.getElementById('btnStop').disabled = false;
        document.getElementById('badgeStatus').innerText = "Processando...";

        let queueEmail = [];
        let queueZap = [];

        dadosCarregados.forEach(item => {
            item.mensagemBase = msg;
            item.assuntoBase = assunto;
            if (item.tipo === 'email') queueEmail.push(item);
            else if (item.tipo === 'celular') queueZap.push(item);
        });

        const total = queueEmail.length + queueZap.length;
        let feitos = 0;

        // 1. EMAILS
        if (queueEmail.length > 0) {
            log(`📧 --- EMAILS (${queueEmail.length}) ---`, "log-info");
            for (let item of queueEmail) {
                if (abortar) break;
                log(`✉️ Envio p/ <b>${item.nome}</b> (${item.contato})...`, "log-email");
                
                let res = await enviarItem(item);
                if (res.success) log(`   ✅ Email Enviado`, "log-email");
                else log(`   ❌ Erro: ${res.message}`, "log-error");

                feitos++;
                updateProgress(feitos, total);
                await new Promise(r => setTimeout(r, 2000));
            }
        }

        // 2. WHATSAPP
        if (queueZap.length > 0 && !abortar) {
            log(`📱 --- WHATSAPP (${queueZap.length}) ---`, "log-info");
            for (let i = 0; i < queueZap.length; i++) {
                if (abortar) break;
                
                let item = queueZap[i];
                log(`📞 Envio p/ <b>${item.nome}</b> (${item.contato})...`, "log-zap");
                
                let res = await enviarItem(item);
                if (res.success) log(`   ✅ Zap Enviado ${res.ai_used?'(IA)':''}`, "log-zap");
                else log(`   ❌ Erro: ${res.message}`, "log-error");

                feitos++;
                updateProgress(feitos, total);

                if (i < queueZap.length - 1) {
                    const delay = Math.floor(Math.random() * (parseInt(document.getElementById('maxDelay').value)*1000 - parseInt(document.getElementById('minDelay').value)*1000 + 1) + parseInt(document.getElementById('minDelay').value)*1000);
                    log(`   ⏳ Delay ${delay/1000}s...`, "log-info");
                    await new Promise(r => setTimeout(r, delay));
                }
            }
        }

        document.getElementById('btnStart').classList.remove('d-none');
        document.getElementById('btnStop').classList.add('d-none');
        document.getElementById('badgeStatus').innerText = "Finalizado";
        log("🏁 CONCLUÍDO", "log-info");
    }

    async function enviarItem(payload) {
        try {
            const response = await fetch('backend.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            return await response.json();
        } catch (e) { return { success: false, message: e.message }; }
    }

    function log(msg, cls) {
        const div = document.getElementById('consoleLog');
        div.innerHTML += `<div class="${cls}">[${new Date().toLocaleTimeString()}] ${msg}</div>`;
        div.scrollTop = div.scrollHeight;
    }

    function updateProgress(atual, total) {
        const p = Math.round((atual / total) * 100);
        document.getElementById('progressBar').style.width = p + '%';
        document.getElementById('progressBar').innerText = `${p}%`;
    }
</script>
</body>
</html>