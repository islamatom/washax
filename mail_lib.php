<?php
// Washax · e-mails clients (bibliothèque commune)
const WX_EXP    = 'contact@washax.com';
const WX_NOM    = 'Washax';
const WX_PATRON = '';                 // e-mail du patron — à remplir quand on l'a
const WX_TEL    = '04XX XX XX XX';    // numéro du patron — à remplir
const WX_WA     = '32000000000';      // WhatsApp du patron (format 32…) — à remplir
const WX_BOITE  = '';                 // id de la boîte Hostinger (si on crée contact@washax.com)
const WX_SITE   = 'https://washax.com';

function wx_h($s, $max = 80) { return htmlspecialchars(mb_substr(trim((string)$s), 0, $max), ENT_QUOTES, 'UTF-8'); }
function wx_date($date) {
  $jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
  $mois = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
  $ts = strtotime($date); if (!$ts) return '';
  return ucfirst($jours[date('w', $ts)]) . ' ' . date('j', $ts) . ' ' . $mois[(int)date('n', $ts)];
}
function wx_formule($t) { return $t === 'Complet int. + ext.' ? 'Complet intérieur + extérieur' : $t; }
function wx_cadre($corps) {
  return '<!DOCTYPE html><html><head><meta name="color-scheme" content="light dark"></head><body style="margin:0;background:#e9e9ec;font-family:Arial,Helvetica,sans-serif;padding:16px 8px">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden">'
  . '<tr><td style="background:#0a0a0a;padding:26px;text-align:center"><img src="' . WX_SITE . '/icone-192.png" alt="Washax" width="72" height="72" style="display:block;margin:0 auto 8px;border-radius:50%">'
  . '<div style="color:#fff;font-size:22px;letter-spacing:6px;font-weight:700">WASH<span style="color:#f37021">AX</span></div><div style="color:#b8b8bf;font-size:12px;letter-spacing:2px">LAVAGE AUTO À DOMICILE</div></td></tr>'
  . $corps
  . '<tr><td style="background:#0a0a0a;padding:20px;text-align:center;color:#9a9aa3;font-size:12px;line-height:1.8">'
  . '<b style="color:#fff">Washax</b> · On vient laver votre voiture chez vous<br>'
  . '<a href="' . WX_SITE . '" style="color:#f37021;text-decoration:none;font-weight:700">washax.com</a></td></tr></table></body></html>';
}
function wx_envoyer($to, $sujet, $html) {
  if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/@example\.com$/i', $to)) return false;
  $corps = wx_cadre($html);
  $cle = trim((string)@file_get_contents(__DIR__ . '/data/mailapi.key'));
  if ($cle && WX_BOITE && function_exists('curl_init')) {
    $ch = curl_init('https://api.mail.hostinger.com/api/v1/mailboxes/' . WX_BOITE . '/send');
    curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 20,
      CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cle, 'Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode(['to' => [$to], 'displayName' => WX_NOM, 'subject' => $sujet, 'html' => $corps,
        'text' => trim(html_entity_decode(strip_tags(str_replace(['<br>', '</tr>', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'))], JSON_UNESCAPED_UNICODE)]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code >= 200 && $code < 300) return true;
  }
  $ent = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
    . 'From: =?UTF-8?B?' . base64_encode(WX_NOM) . '?= <' . WX_EXP . ">\r\n" . (WX_PATRON ? 'Reply-To: ' . WX_PATRON . "\r\n" : '');
  return mail($to, '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corps, $ent, '-f' . WX_EXP);
}
// $d : prenom, email, formule, prix, voiture, adresse, date, heure, tel, raison
function wx_mail($type, $d) {
  $prenom = wx_h($d['prenom'] ?? '', 40); $formule = wx_h(wx_formule($d['formule'] ?? ''));
  $voiture = wx_h($d['voiture'] ?? ''); $heure = wx_h($d['heure'] ?? '', 5); $prix = wx_h($d['prix'] ?? '', 20);
  $adr = wx_h($d['adresse'] ?? '', 160); $tel = wx_h($d['tel'] ?? '', 30); $raison = wx_h($d['raison'] ?? '', 160);
  $dateTxt = wx_date($d['date'] ?? '');
  $wa = '<a href="https://wa.me/' . WX_WA . '" style="display:inline-block;background:#25d366;color:#fff;text-decoration:none;font-weight:700;font-size:16px;padding:15px 26px;border-radius:12px;margin:4px">💬 WhatsApp Washax</a>';
  $details = "🧽 <b>$formule</b>" . ($prix ? " · $prix" : '') . ($voiture ? "<br>🚗 $voiture" : '') . ($adr ? "<br>📍 $adr" : '');
  $titre = fn($ico, $t, $sous) => '<tr><td style="padding:28px 24px 8px;text-align:center"><div style="font-size:42px;line-height:1">' . $ico . '</div>'
    . "<h1 style=\"margin:12px 0 6px;font-size:24px;color:#111\">$t</h1><p style=\"margin:0;color:#555;font-size:15px;line-height:1.6\">$sous</p></td></tr>";
  $carte = fn($lbl, $extra = '') => '<tr><td style="padding:20px 24px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f7;border-radius:14px;border-left:5px solid #f37021">'
    . "<tr><td style=\"padding:18px 18px 6px;font-size:12px;letter-spacing:2px;color:#f37021;font-weight:700\">$lbl</td></tr>"
    . "<tr><td style=\"padding:4px 18px 16px;font-size:15px;color:#111;line-height:1.9\">$details<br>📅 <b>$dateTxt à $heure</b>$extra</td></tr></table></td></tr>";
  $agenda = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode('Lavage Washax')
    . '&dates=' . str_replace('-', '', $d['date'] ?? '') . 'T' . str_replace(':', '', $heure) . '00/' . str_replace('-', '', $d['date'] ?? '') . 'T' . str_replace(':', '', $heure) . '00&ctz=Europe/Brussels';
  switch ($type) {
    case 'demande':
      return wx_envoyer($d['email'] ?? '', 'Votre demande de lavage est bien reçue 🚗',
        $titre('📩', "Demande bien reçue, $prenom !", 'Merci pour votre réservation chez Washax.<br>On vérifie le planning et on vous <b>confirme très vite</b> par SMS ou WhatsApp.')
        . $carte('VOTRE DEMANDE', '<br><span style="display:inline-block;margin-top:8px;background:#fff1e6;color:#a64a00;font-size:13px;font-weight:700;padding:5px 12px;border-radius:20px">⏳ En attente de confirmation</span>'));
    case 'confirme':
      return wx_envoyer($d['email'] ?? '', '✅ Votre lavage Washax est confirmé',
        $titre('✅', "C’est confirmé, $prenom !", 'On vient laver votre voiture chez vous.') . $carte('VOTRE RENDEZ-VOUS')
        . "<tr><td style=\"padding:0 24px 14px;text-align:center\"><a href=\"$agenda\" style=\"display:inline-block;background:#111;color:#fff;text-decoration:none;font-weight:700;font-size:16px;padding:15px 26px;border-radius:12px;margin:4px\">📅 Ajouter à mon agenda</a>$wa</td></tr>"
        . '<tr><td style="padding:0 24px 18px;font-size:14px;color:#333;line-height:1.7">🚗 Laissez la voiture accessible à l’heure prévue.<br>🔔 Vous recevrez un petit rappel la veille.<br>📞 Un empêchement ? Prévenez-nous au <b>' . WX_TEL . '</b>.</td></tr>');
    case 'rappel':
      return wx_envoyer($d['email'] ?? '', '🔔 Rappel : Washax passe demain à ' . ($d['heure'] ?? ''),
        $titre('🔔', "C’est demain, $prenom !", 'Petit rappel de votre lavage Washax.') . $carte('DEMAIN')
        . "<tr><td style=\"padding:0 24px 18px;text-align:center\">$wa</td></tr>");
    case 'annule':
      return wx_envoyer($d['email'] ?? '', 'Votre RDV Washax',
        $titre('😕', "Désolé, $prenom", 'Votre rendez-vous ne peut malheureusement pas avoir lieu' . ($raison ? " ($raison)" : '') . '.')
        . $carte('RDV ANNULÉ')
        . '<tr><td style="padding:0 24px 22px;text-align:center"><a href="' . WX_SITE . '" style="display:inline-block;background:#f37021;color:#fff;text-decoration:none;font-weight:700;font-size:16px;padding:15px 26px;border-radius:12px">📅 Reprendre un RDV</a></td></tr>');
    case 'patron':
      if (!WX_PATRON) return false;
      return wx_envoyer(WX_PATRON, '🔔 Nouvelle demande : ' . strip_tags($prenom) . " · $dateTxt " . ($d['heure'] ?? ''),
        "<tr><td style=\"padding:26px 24px;text-align:center\"><h1 style=\"margin:0 0 8px;font-size:22px;color:#111\">🔔 Nouvelle demande de lavage</h1>"
        . "<p style=\"font-size:16px;color:#111;line-height:1.8\"><b>$prenom</b>" . ($tel ? " · 📞 $tel" : '') . "<br>$details<br>📅 <b>$dateTxt à $heure</b></p>"
        . '<a href="' . WX_SITE . '/panel.html" style="display:inline-block;background:#f37021;color:#fff;text-decoration:none;font-weight:700;padding:14px 24px;border-radius:12px">Ouvrir mon panel</a></td></tr>');
  }
  return false;
}
