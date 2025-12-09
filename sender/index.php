<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disparador por Fases</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .console { background: #000; color: #0f0; font-family: monospace; height: 350px; overflow-y: auto; padding: 15px; border-radius: 8px; font-size: 13px; border: 1px solid #444; }
        .console .error { color: #ff5555; }
        .console .warn { color: #ffff55; }
        .console .email { color: #64b5f6; } /* Azul claro para email */
        .console .zap { color: #69f0ae; } /* Verde claro para zap */
    </style>
</head>
<body class="bg-dark text-light">

<div class="container mt-4">
    <div class="card bg-secondary text-white shadow-lg">
        <div class="card-header bg-black border-bottom border-dark">
            <h5 class="m-0">📦 Disparador Organizado (1º Emails -> 2º WhatsApp)</h5>
        </div>
        <div class="card-body">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Assunto (Email):</label>
                    <input type="text" id="assuntoBase" class="form-control" value="Info sobre Pedido {pedido}">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Arquivo CSV:</label>
                    <input type="file" id="csvFile" class="form-control" accept=".csv">
                </div>
            </div>

            <div class="mb-3">
                <label>Mensagem Base (WhatsApp será reescrito pela IA / Email será exato):</label>
    <textarea id="msgBase" class="form-control" rows="8">Olá {nome}, tudo bem?

Recebemos a solicitação do pedido {pedido} referente ao item: {produto}.

Como se trata de um produto digital/envio eletrônico, nosso sistema pede uma breve confirmação de segurança para liberar o acesso imediato.

Poderia responder com um "Sim" para confirmarmos a titularidade?

Assim que confirmar, prosseguimos com a liberação.

Ficamos no aguardo!
Nosso Whats de Suporte/Validação: 1193585-6950</textarea>
</div>

            <div class="row align-items-end mb-4">
                <div class="col-md-3">
                    <label>Delay WhatsApp (Min/Max):</label>
                    <div class="input-group">
                        <input type="number" id="minDelay" class="form-control" value="15">
                        <input type="number" id="maxDelay" class="form-control" value="30">
                    </div>
                </div>
                <div class="col-md-9 d-flex gap-2">
                    <button id="btnStart" class="btn btn-success flex-grow-1 fw-bold" onclick="iniciarTudo()">▶ INICIAR CAMPANHA</button>
                    <button id="btnStop" class="btn btn-danger fw-bold d-none" onclick="parar()">⏹ PARAR</button>
                    <button id="btnRelatorio" class="btn btn-warning fw-bold d-none" onclick="baixarRelatorio()">📥 BAIXAR ERROS</button>
                </div>
            </div>

            <div class="progress mb-2" style="height: 25px;">
                <div id="progressBar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
            </div>

            <div id="consoleLog" class="console">
                <div>> Aguardando CSV...</div>
            </div>
        </div>
    </div>
</div>

<script>
    let abortar = false;
    let listaErros = []; // Armazena os erros para o relatório final

    function parar() {
        abortar = true;
        log("⛔ SOLICITAÇÃO DE PARADA!", "error");
        document.getElementById('btnStop').disabled = true;
    }

    async function iniciarTudo() {
        const fileInput = document.getElementById('csvFile');
        const msgBase = document.getElementById('msgBase').value;
        const assuntoBase = document.getElementById('assuntoBase').value;

        if (!fileInput.files.length) { alert("CSV necessário!"); return; }

        // Reset
        abortar = false;
        listaErros = [];
        document.getElementById('btnStart').classList.add('d-none');
        document.getElementById('btnStop').classList.remove('d-none');
        document.getElementById('btnStop').disabled = false;
        document.getElementById('btnRelatorio').classList.add('d-none');
        
        // Ler CSV
        const file = fileInput.files[0];
        const text = await file.text();
        const lines = text.split('\n').filter(line => line.trim() !== '');
        if (lines[0].toLowerCase().includes('cpf')) lines.shift();

        // SEPARAR LISTAS (FASE 1 e FASE 2)
        let queueEmail = [];
        let queueZap = [];

        lines.forEach(line => {
            const cols = line.split(',');
            if (cols.length < 4) return;
            
            const item = {
                cpf: cols[0], nome: cols[1], tipo: cols[2].trim(), contato: cols[3].trim(),
                pedido: cols[4] || '', produto: cols[5] || '',
                mensagemBase: msgBase, assuntoBase: assuntoBase
            };

            if (item.tipo.toLowerCase() === 'email') {
                queueEmail.push(item);
            } else if (item.tipo.toLowerCase() === 'celular') {
                queueZap.push(item);
            }
        });

        const totalGeral = queueEmail.length + queueZap.length;
        let processados = 0;

        log(`--- INICIANDO: ${queueEmail.length} Emails e ${queueZap.length} WhatsApps ---`);

        // === FASE 1: EMAILS ===
        if (queueEmail.length > 0) {
            log(`📧 FASE 1: Disparando ${queueEmail.length} Emails...`, "email");
            
            for (let i = 0; i < queueEmail.length; i++) {
                if (abortar) break;
                
                const item = queueEmail[i];
                log(`[${i+1}/${queueEmail.length}] Email p/ ${item.nome}...`, "email");
                
                await enviarItem(item); // Envia
                
                processados++;
                updateProgress(processados, totalGeral);
                
                // Delay curto para Email (3s)
                await new Promise(r => setTimeout(r, 3000));
            }
        }

        // === FASE 2: WHATSAPP ===
        if (queueZap.length > 0 && !abortar) {
            log(`📱 FASE 2: Disparando ${queueZap.length} WhatsApps...`, "zap");
            
            for (let i = 0; i < queueZap.length; i++) {
                if (abortar) break;

                const item = queueZap[i];
                log(`[${i+1}/${queueZap.length}] Zap p/ ${item.nome}...`, "zap");
                
                await enviarItem(item); // Envia

                processados++;
                updateProgress(processados, totalGeral);

                // Delay Longo para WhatsApp (Anti-Block)
                if (i < queueZap.length - 1) {
                    const min = parseInt(document.getElementById('minDelay').value) * 1000;
                    const max = parseInt(document.getElementById('maxDelay').value) * 1000;
                    const delay = Math.floor(Math.random() * (max - min + 1) + min);
                    log(`⏳ Aguardando ${delay/1000}s (IA/Segurança)...`, "warn");
                    await new Promise(r => setTimeout(r, delay));
                }
            }
        }

        // FIM
        document.getElementById('btnStart').classList.remove('d-none');
        document.getElementById('btnStop').classList.add('d-none');
        
        log(`🏁 PROCESSO FINALIZADO. Erros: ${listaErros.length}`);
        
        if (listaErros.length > 0) {
            document.getElementById('btnRelatorio').classList.remove('d-none');
            log(`⚠ ATENÇÃO: ${listaErros.length} envios falharam. Baixe o relatório.`, "error");
        }
    }

    async function enviarItem(payload) {
        try {
            const response = await fetch('backend.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            
            const raw = await response.text();
            try {
                const res = JSON.parse(raw);
                if (res.success) {
                    let icon = payload.tipo.toLowerCase() === 'celular' ? '📱' : '📧';
                    let extra = res.ai_used ? ' (🧠 IA)' : '';
                    log(`✅ ${icon} Sucesso: ${res.message}${extra}`);
                } else {
                    handleError(payload, res.message);
                }
            } catch (e) {
                handleError(payload, `Erro Fatal PHP: ${raw.substring(0, 50)}...`);
            }
        } catch (e) {
            handleError(payload, `Erro Rede: ${e.message}`);
        }
    }

    function handleError(item, msg) {
        log(`❌ Falha (${item.nome}): ${msg}`, "error");
        // Adiciona ao relatório de erros
        listaErros.push([item.nome, item.tipo, item.contato, msg, new Date().toLocaleTimeString()]);
    }

    function baixarRelatorio() {
        let csvContent = "data:text/csv;charset=utf-8,Nome,Tipo,Contato,Erro,Hora\n";
        
        listaErros.forEach(row => {
            let linha = row.map(e => `"${e}"`).join(","); // Aspas para evitar quebra com vírgulas
            csvContent += linha + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "relatorio_erros.csv");
        document.body.appendChild(link);
        link.click();
    }

    function log(msg, type = '') {
        const consoleDiv = document.getElementById('consoleLog');
        consoleDiv.innerHTML += `<div class="${type}" style="border-bottom:1px solid #333; padding:2px;">${msg}</div>`;
        consoleDiv.scrollTop = consoleDiv.scrollHeight;
    }

    function updateProgress(atual, total) {
        const p = Math.round((atual / total) * 100);
        const bar = document.getElementById('progressBar');
        bar.style.width = p + '%';
        bar.innerText = `${p}% (${atual}/${total})`;
    }
</script>

</body>
</html>