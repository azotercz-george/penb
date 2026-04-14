<?php
/**
 * PENB – Odesílání objednávky e-mailem
 * Umístěte tento soubor vedle penb-web.html na hostingu
 */

// ── Konfigurace ──────────────────────────────────────────
$prijemce  = 'azotercz@gmail.com';
$predmet   = 'Nová objednávka PENB z webu';
$odesilatName = 'Web KalkulacePENB.cz';
// ─────────────────────────────────────────────────────────

// Povolené původy (CORS) – upravte na svou doménu
$allowed_origins = [
    'http://localhost',
    'http://127.0.0.1',
    // 'https://www.vasedomena.cz',  // ← odkomentujte a doplňte svou doménu
];

// CORS hlavičky
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pouze POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metoda není povolena']);
    exit;
}

// ── Načtení dat formuláře ────────────────────────────────
function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$jmeno         = sanitize($_POST['jmeno']          ?? '');
$telefon       = sanitize($_POST['telefon']        ?? '');
$email         = sanitize($_POST['email']          ?? '');
$typ           = sanitize($_POST['typ_nemovitosti'] ?? '');
$adresa        = sanitize($_POST['adresa']         ?? '');
$podklady      = sanitize($_POST['forma_podkladu'] ?? '');
$termin        = sanitize($_POST['termin']         ?? '');
$poznamka      = sanitize($_POST['poznamka']       ?? '');

// ── Validace povinných polí ──────────────────────────────
$errors = [];
if (empty($jmeno))   $errors[] = 'Jméno je povinné';
if (empty($telefon)) $errors[] = 'Telefon je povinný';
if (empty($email))   $errors[] = 'E-mail je povinný';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail není platný';
if (empty($typ))     $errors[] = 'Typ nemovitosti je povinný';
if (empty($adresa))  $errors[] = 'Adresa je povinná';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// ── Sestavení e-mailu ────────────────────────────────────
$datum = date('d.m.Y H:i');

$telo = "========================================\n";
$telo .= "  NOVÁ OBJEDNÁVKA PENB Z WEBU\n";
$telo .= "  Datum: $datum\n";
$telo .= "========================================\n\n";

$telo .= "KONTAKTNÍ ÚDAJE\n";
$telo .= "---------------\n";
$telo .= "Jméno a příjmení : $jmeno\n";
$telo .= "Telefon          : $telefon\n";
$telo .= "E-mail           : $email\n\n";

$telo .= "NEMOVITOST\n";
$telo .= "----------\n";
$telo .= "Typ nemovitosti  : $typ\n";
$telo .= "Adresa           : $adresa\n";
$telo .= "Forma podkladů   : $podklady\n";
$telo .= "Požadovaný termín: $termin\n\n";

if (!empty($poznamka)) {
    $telo .= "POZNÁMKA / DOTAZ\n";
    $telo .= "----------------\n";
    $telo .= "$poznamka\n\n";
}

$telo .= "========================================\n";
$telo .= "Zpráva odeslána automaticky z webu KalkulacePENB.cz\n";

// Hlavičky e-mailu
$headers  = "From: $odesilatName <noreply@kalkulacepenb.cz>\r\n";
$headers .= "Reply-To: $jmeno <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// ── Odeslání ─────────────────────────────────────────────
$predmetEncoded = '=?UTF-8?B?' . base64_encode($predmet) . '?=';

$odeslano = mail($prijemce, $predmetEncoded, $telo, $headers);

// ── Potvrzovací e-mail klientovi ─────────────────────────
if ($odeslano && !empty($email)) {
    $teloKlient  = "Dobrý den $jmeno,\n\n";
    $teloKlient .= "děkujeme za vaši objednávku PENB. Ozveme se vám do 24 hodin.\n\n";
    $teloKlient .= "Shrnutí vaší objednávky:\n";
    $teloKlient .= "- Typ nemovitosti : $typ\n";
    $teloKlient .= "- Adresa          : $adresa\n";
    $teloKlient .= "- Forma podkladů  : $podklady\n";
    $teloKlient .= "- Požadovaný termín: $termin\n\n";
    $teloKlient .= "V případě dotazů nás kontaktujte:\n";
    $teloKlient .= "Tel: +420 731 055 826\n";
    $teloKlient .= "E-mail: azotercz@gmail.com\n\n";
    $teloKlient .= "S pozdravem,\nTým KalkulacePENB.cz\n";

    $headersKlient  = "From: $odesilatName <noreply@kalkulacepenb.cz>\r\n";
    $headersKlient .= "Reply-To: $prijemce\r\n";
    $headersKlient .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headersKlient .= "Content-Transfer-Encoding: 8bit\r\n";

    $predmetKlientEncoded = '=?UTF-8?B?' . base64_encode('Potvrzení objednávky PENB') . '?=';
    mail($email, $predmetKlientEncoded, $teloKlient, $headersKlient);
}

// ── Odpověď ──────────────────────────────────────────────
if ($odeslano) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'Objednávka byla úspěšně odeslána']);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Nepodařilo se odeslat e-mail. Zkuste to prosím znovu.']);
}
