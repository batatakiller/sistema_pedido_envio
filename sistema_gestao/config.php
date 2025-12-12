<?php
// config.php
// CENTRAL DE CONFIGURAÇÕES

date_default_timezone_set('America/Sao_Paulo');

// --- 1. WHATSAPP (UAZAPI) ---
define('UAZAPI_URL', 'https://superbot.uazapi.com/send/text');
define('UAZAPI_TOKEN', '088c5853-8c76-4025-9a31-8376f13ce9fb'); // b88a874b-180e-44bc-bfee-56ea32096c9c <--- supersoftware  088c5853-8c76-4025-9a31-8376f13ce9fb <--- sergipe

// --- 2. SUPABASE (BANCO DE DADOS) ---
define('SB_URL', 'https://qoobmxjzcjtkpezajbbv.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvb2JteGp6Y2p0a3BlemFqYmJ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMwNDI3OTgsImV4cCI6MjA3ODYxODc5OH0.oGauqAKx1ZaMUgvYrQgvepE6XVXoKEIgbVhfWIKpgY8');

// --- 3. GOOGLE GEMINI (IA) ---
define('GEMINI_KEY', 'AIzaSyBNw8ul4PFs6OcVpBM2COYQslcNUvLGoOU'); 

// --- 4. EMAIL (GMAIL SMTP) ---
define('MAIL_USER', 'sacsupersoftware@gmail.com');
define('MAIL_PASS', 'jalg vtwm rluq kozn');

// --- 5. API BUSCA CPF (WORK CONSULTORIA) ---
$WORK_HEADERS = [
    'access-token: pgNf_XSeDhWJ0-B3n8Iu6w',
    'client: AOfODkSWr-96wx0xn0e2HA',
    'uid: batatoads',
    'token-type: Bearer',
    'Cookie: cf_clearance=SEU_COOKIE_ATUALIZADO_AQUI;', // <--- ATUALIZE SE NECESSÁRIO
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
];
?>


