<?php
// Washax · serveur du panel (RDV partagés entre tous les téléphones) — stockage dans data/db.json
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/mail_lib.php';
const CODE_DEPART = '0000';
const CREDITS_DEPART = 0;            // SMS : Atom les ajoute quand le patron les achète
define('ADMIN', trim((string)@file_get_contents(__DIR__ . '/data/admin.key'))); // recharge des SMS par Atom (clé hors du code)
const TRAJET = 30;                    // minutes de route entre deux clients
const DUREES = ['Complet int. + ext.' => 150, 'Intérieur' => 60, 'Extérieur' => 60, 'Remise à neuf' => 180, "Retour d'usine" => 180];
$DB_F = __DIR__ . '/data/db.json';
if (!is_dir(__DIR__ . '/data')) { @mkdir(__DIR__ . '/data', 0750); }
if (!is_file(__DIR__ . '/data/.htaccess')) @file_put_contents(__DIR__ . '/data/.htaccess', "Require all denied\nDeny from all\n");

function vide() { return ['C' => [], 'R' => [], 'S' => null, 'jetons' => [], 'site' => []]; }
function db_ouvrir($f) { $h = fopen($f, 'c+'); flock($h, LOCK_EX); $txt = stream_get_contents($h);
  $db = json_decode($txt ?: '', true) ?: vide(); return [$h, $db]; }
function db_fermer($h, $db) { ftruncate($h, 0); rewind($h); fwrite($h, json_encode($db, JSON_UNESCAPED_UNICODE)); fflush($h); flock($h, LOCK_UN); fclose($h); }
function db_lire($f) { return json_decode(@file_get_contents($f) ?: '', true) ?: vide(); }
function nid() { return (int)(microtime(true) * 1000) * 100 + random_int(0, 99); }
function telN($t) { $t = preg_replace('/\D/', '', (string)$t); return preg_replace('/^(0032|32)/', '0', $t); }
function sortie($x, $code = 200) { http_response_code($code); echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; }
function limite($cle, $max) { $f = sys_get_temp_dir() . '/wxa_' . md5($cle . ($_SERVER['REMOTE_ADDR'] ?? ''));
  $t = array_filter(json_decode(@file_get_contents($f) ?: '[]', true) ?: [], fn($x) => $x > time() - 3600);
  if (count($t) >= $max) sortie(['ok' => false, 'erreur' => 'Trop de tentatives, réessayez plus tard'], 429); $t[] = time(); @file_put_contents($f, json_encode(array_values($t))); }
function minutes($h) { [$a, $b] = array_map('intval', explode(':', $h . ':0')); return $a * 60 + $b; }
function duree($t) { return DUREES[$t] ?? 150; }

// ---------- lectures publiques (site) ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $f = $_GET['f'] ?? '';
  $db = db_lire($DB_F);
  if ($f === 'pris') { $auj = date('Y-m-d'); $o = [];
    foreach ($db['R'] as $r) if (in_array($r['statut'] ?? '', ['attente', 'confirme'], true) && ($r['date'] ?? '') >= $auj) $o[] = ['date' => $r['date'], 'heure' => $r['heure'], 'type' => $r['type'] ?? ''];
    $fer = array_values(array_filter((array)($db['S']['fermes'] ?? []), fn($d) => $d >= $auj));
    sortie(['ok' => true, 'pris' => $o, 'fermes' => $fer, 'horaires' => $db['S']['horaires'] ?? null]); }
  if ($f === 'site') sortie($db['site'] ?: new stdClass());
  sortie(['ok' => false], 400);
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$f = $in['f'] ?? '';

// ---------- demande de RDV depuis le site (public) ----------
if ($f === 'demande') {
  limite('demande', 6);
  $txt = fn($k, $m) => mb_substr(trim(strip_tags((string)($in[$k] ?? ''))), 0, $m);
  $prenom = $txt('prenom', 40); $tel = $txt('tel', 30); $date = $in['date'] ?? ''; $heure = $in['heure'] ?? '';
  if (!$prenom || strlen(telN($tel)) < 9 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $heure) || $date <= date('Y-m-d') || (int)date('w', strtotime($date)) === 0)
    sortie(['ok' => false, 'erreur' => 'Demande incomplète'], 400);
  $type = array_key_exists($in['type'] ?? '', DUREES) ? $in['type'] : 'Complet int. + ext.';
  $email = filter_var(trim($in['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '—';
  [$h, $db] = db_ouvrir($DB_F);
  if (in_array($date, (array)($db['S']['fermes'] ?? []), true)) { db_fermer($h, $db); sortie(['ok' => false, 'erreur' => 'Washax est fermé ce jour-là, choisissez un autre jour']); }
  $deb = minutes($heure); $fin = $deb + duree($type);
  foreach ($db['R'] as $r) if (($r['date'] ?? '') === $date && in_array($r['statut'] ?? '', ['attente', 'confirme'], true)) {
    $d2 = minutes($r['heure'] ?? '0:0'); $f2 = $d2 + duree($r['type'] ?? '');
    if ($deb < $f2 + TRAJET && $d2 < $fin + TRAJET) { db_fermer($h, $db); sortie(['ok' => false, 'erreur' => 'Ce créneau vient d’être pris, choisissez-en un autre']); } }
  $c = null; foreach ($db['C'] as &$x) if (telN($x['tel'] ?? '') === telN($tel)) { $c = &$x; break; } unset($x);
  if ($c === null) { $db['C'][] = ['id' => nid(), 'prenom' => $prenom, 'nom' => $txt('nom', 40), 'tel' => $tel, 'email' => $email, 'mariage' => '', 'statut' => 'normal', 'note' => '', 'notes' => '', 'par' => null, 'amendes' => []]; $cid = end($db['C'])['id']; }
  else { if ($email !== '—') $c['email'] = $email; $cid = $c['id']; }
  $db['R'][] = ['id' => nid(), 'cid' => $cid, 'type' => $type, 'date' => $date, 'heure' => $heure, 'statut' => 'attente', 'src' => 'site', 'message' => $txt('message', 300), 'recu' => (int)(microtime(true) * 1000)];
  db_fermer($h, $db);
  $m = ['prenom' => $prenom, 'tel' => $tel, 'email' => $email !== '—' ? $email : '', 'formule' => $type, 'voiture' => $txt('voiture', 80), 'adresse' => $txt('adresse', 160), 'prix' => $txt('prix', 20), 'date' => $date, 'heure' => $heure];
  if ($m['email']) wx_mail('demande', $m);
  wx_mail('patron', $m);
  sortie(['ok' => true]);
}

// ---------- connexion du panel ----------
if ($f === 'login') {
  limite('login', 12);
  $code = (string)($in['code'] ?? '');
  [$h, $db] = db_ouvrir($DB_F);
  $acces = $db['S']['acces'] ?? [['id' => 1, 'nom' => 'Patron', 'code' => CODE_DEPART, 'role' => 'tout']];
  $ac = null; foreach ($acces as $a) if (hash_equals((string)($a['code'] ?? ''), $code)) { $ac = $a; break; }
  if (!$ac) { db_fermer($h, $db); sortie(['ok' => false, 'erreur' => 'Code incorrect']); }
  $j = bin2hex(random_bytes(24));
  $db['jetons'] = array_filter($db['jetons'] ?? [], fn($v) => ($v['exp'] ?? 0) > time());
  $db['jetons'][$j] = ['acces' => $ac['id'], 'exp' => time() + 90 * 86400];
  db_fermer($h, $db);
  unset($ac['code']);
  sortie(['ok' => true, 'jeton' => $j, 'acces' => $ac, 'codeDepart' => $code === CODE_DEPART]);
}

// ---------- recharge des SMS par Atom ----------
if ($f === 'recharge') {
  if (!ADMIN || !hash_equals(ADMIN, (string)($in['admin'] ?? ''))) sortie(['ok' => false], 403);
  [$h, $db] = db_ouvrir($DB_F); if (!is_array($db['S'])) $db['S'] = [];
  $db['S']['credits'] = isset($in['fixe']) ? max(0, (int)$in['fixe']) : ($db['S']['credits'] ?? CREDITS_DEPART) + max(0, (int)($in['n'] ?? 0));
  db_fermer($h, $db); sortie(['ok' => true, 'credits' => $db['S']['credits']]);
}

// ---------- tout le reste : panel connecté ----------
$db = db_lire($DB_F);
$j = (string)($in['jeton'] ?? '');
if (!$j || !isset($db['jetons'][$j]) || ($db['jetons'][$j]['exp'] ?? 0) < time()) sortie(['ok' => false, 'erreur' => 'Session expirée'], 401);

if ($f === 'charger') { $S = $db['S']; if ($S) $S['credits'] = $S['credits'] ?? CREDITS_DEPART; sortie(['ok' => true, 'C' => $db['C'], 'R' => $db['R'], 'S' => $S]); }

if ($f === 'sync') {
  [$h, $db] = db_ouvrir($DB_F);
  $mails = [];
  $idxC = []; foreach ($db['C'] as $i => $c) $idxC[$c['id']] = $i;
  foreach (($in['clients'] ?? []) as $c) { if (!isset($c['id'])) continue; if (isset($idxC[$c['id']])) $db['C'][$idxC[$c['id']]] = $c; else { $db['C'][] = $c; $idxC[$c['id']] = count($db['C']) - 1; } }
  $idxR = []; foreach ($db['R'] as $i => $r) $idxR[$r['id']] = $i;
  foreach (($in['rdv'] ?? []) as $r) { if (!isset($r['id'])) continue;
    if (isset($idxR[$r['id']])) { $old = $db['R'][$idxR[$r['id']]];
      if (($r['statut'] ?? '') === 'annule' && ($old['statut'] ?? '') !== 'annule') $mails[] = ['annule', $r];
      if (($r['statut'] ?? '') === 'confirme' && ($old['statut'] ?? '') !== 'confirme') $mails[] = ['confirme', $r];
      if (isset($old['rappel'])) $r['rappel'] = array_merge((array)$old['rappel'], (array)($r['rappel'] ?? []));
      $db['R'][$idxR[$r['id']]] = $r; }
    else { $db['R'][] = $r; $idxR[$r['id']] = count($db['R']) - 1; } }
  $sR = array_flip($in['supprRdv'] ?? []); $sC = array_flip($in['supprC'] ?? []);
  $db['R'] = array_values(array_filter($db['R'], fn($r) => !isset($sR[$r['id']])));
  $db['C'] = array_values(array_filter($db['C'], fn($c) => !isset($sC[$c['id']])));
  if (isset($in['S']) && is_array($in['S'])) { $cred = $db['S']['credits'] ?? CREDITS_DEPART; $db['S'] = $in['S']; $db['S']['credits'] = $cred; } // crédits SMS gérés par le serveur
  $lim = date('Y-m-d', strtotime('-13 months'));
  $db['R'] = array_values(array_filter($db['R'], fn($r) => ($r['date'] ?? '9') >= $lim));
  db_fermer($h, $db);
  foreach ($mails as [$quoi, $r]) { $c = null; foreach ($db['C'] as $x) if ($x['id'] == $r['cid']) { $c = $x; break; }
    if ($c) wx_mail($quoi, ['prenom' => $c['prenom'], 'email' => $c['email'] ?? '', 'formule' => $r['type'], 'date' => $r['date'], 'heure' => $r['heure'], 'raison' => $r['raison'] ?? '']); }
  sortie(['ok' => true]);
}

if ($f === 'sms') {
  // SMS via Brevo — la clé se met dans data/brevo.key (jamais dans le code)
  $cle = trim((string)@file_get_contents(__DIR__ . '/data/brevo.key'));
  $envois = array_slice($in['envois'] ?? [], 0, 300);
  [$h, $db] = db_ouvrir($DB_F);
  $cred = $db['S']['credits'] ?? CREDITS_DEPART;
  if (!$cle) { db_fermer($h, $db); sortie(['ok' => true, 'envoyes' => 0, 'rates' => count($envois), 'credits' => $cred, 'raison' => 'SMS pas encore activés, utilisez WhatsApp']); }
  $ok = 0; $ko = 0;
  foreach ($envois as $e) {
    if ($cred < 1) { $ko++; continue; }
    $n = telN($e['tel'] ?? ''); if (strlen($n) < 9) { $ko++; continue; }
    $n = '32' . substr($n, 1);
    $ch = curl_init('https://api.brevo.com/v3/transactionalSMS/sms');
    curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 15,
      CURLOPT_HTTPHEADER => ['api-key: ' . $cle, 'content-type: application/json', 'accept: application/json'],
      CURLOPT_POSTFIELDS => json_encode(['type' => 'transactional', 'sender' => 'Washax', 'recipient' => $n, 'content' => mb_substr((string)($e['texte'] ?? ''), 0, 600)])]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code >= 200 && $code < 300) { $ok++; $cred--; } else $ko++;
  }
  if (!is_array($db['S'])) $db['S'] = [];
  $db['S']['credits'] = $cred;
  db_fermer($h, $db);
  sortie(['ok' => true, 'envoyes' => $ok, 'rates' => $ko, 'credits' => $cred, 'raison' => $ok ? '' : ($cred < 1 ? 'plus de crédits SMS' : 'numéro refusé')]);
}
if ($f === 'reglage') { [$h, $db] = db_ouvrir($DB_F); $db['site'][substr((string)($in['cle'] ?? ''), 0, 30)] = $in['valeur'] ?? null; db_fermer($h, $db); sortie(['ok' => true]); }
if (in_array($f, ['envoi', 'media_ajout', 'media_suppr'], true)) sortie(['ok' => false, 'erreur' => 'Photos du site : à venir']);
sortie(['ok' => false, 'erreur' => 'Action inconnue'], 400);
