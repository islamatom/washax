// Washax · service worker minimal (notifications du panel)
self.addEventListener('install',e=>self.skipWaiting());
self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));
self.addEventListener('notificationclick',e=>{e.notification.close();e.waitUntil(self.clients.matchAll({type:'window'}).then(l=>{for(const c of l)if(c.url.includes('panel'))return c.focus();return self.clients.openWindow('/panel.html')}))});
