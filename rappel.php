<?php
// Washax · rappel automatique la veille (tâche planifiée Hostinger chaque jour à 18h)
if (PHP_SAPI !== 'cli' && ($_GET['cle'] ?? '') !== 'wx-rappel-5Kd8') { http_response_code(403); exit('non'); }
require __DIR__ . '/mail_lib.php';
$f = __DIR__ . '/data/db.json';
if (!is_file($f)) exit("rien\n");
$h = fopen($f, 'c+'); flock($h, LOCK_EX);
$db = json_decode(stream_get_contents($h) ?: '', true) ?: ['C' => [], 'R' => []];
$demain = date('Y-m-d', strtotime('+1 day')); $n = 0;
foreach ($db['R'] as &$r) {
  if (($r['date'] ?? '') !== $demain || ($r['statut'] ?? '') !== 'confirme' || !empty($r['rappel']['mail'])) continue;
  $c = null; foreach ($db['C'] as $x) if ($x['id'] == $r['cid']) { $c = $x; break; }
  $ok = $c && wx_mail('rappel', ['prenom' => $c['prenom'], 'email' => $c['email'] ?? '', 'formule' => $r['type'], 'date' => $r['date'], 'heure' => $r['heure']]);
  $r['rappel'] = ['mail' => $ok ? date('c') : 'sans e-mail']; $n++;
}
unset($r);
ftruncate($h, 0); rewind($h); fwrite($h, json_encode($db, JSON_UNESCAPED_UNICODE)); fflush($h); flock($h, LOCK_UN); fclose($h);
echo "rappels : $n\n";
