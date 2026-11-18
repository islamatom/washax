// Washax · bandeau d'information cookies (RGPD / loi belge). Aucun cookie publicitaire ni de mesure d'audience :
// le site n'utilise que du stockage strictement nécessaire, donc une simple information suffit (pas de consentement requis).
(function(){
  try{if(localStorage.getItem('wx_cookies'))return}catch(e){}
  function go(){
    var d=document.createElement('div');d.id='wxck';d.setAttribute('role','region');d.setAttribute('aria-label','Cookies');
    d.innerHTML='<style>#wxck{position:fixed;left:12px;right:12px;bottom:12px;z-index:45;max-width:560px;margin:0 auto;background:#141414;color:#f2f2f2;border:1px solid #2a2a2a;border-left:3px solid #F37021;border-radius:14px;padding:14px 16px;font:13px/1.55 Montserrat,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.5);display:flex;gap:12px;align-items:center}#wxck a{color:#F37021}#wxck button{flex:0 0 auto;background:#F37021;color:#fff;border:0;border-radius:10px;padding:10px 16px;font:600 13px Montserrat,sans-serif;cursor:pointer}@media(max-width:480px){#wxck{flex-direction:column;align-items:stretch}}</style>'
      +'<div>🍪 Ce site n\'utilise <b>aucun cookie publicitaire ni de mesure d\'audience</b>. Seul ce qui est nécessaire à son fonctionnement est enregistré sur votre appareil. <a href="confidentialite.html#cookies">En savoir plus</a></div><button type="button">J\'ai compris</button>';
    d.querySelector('button').onclick=function(){try{localStorage.setItem('wx_cookies','1')}catch(e){}d.remove()};
    document.body.appendChild(d);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',go);else go();
})();
