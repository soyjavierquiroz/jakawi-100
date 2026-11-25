(()=>{
  const deny=()=>{document.documentElement.innerHTML='<meta name="viewport" content="width=device-width"><body><h1>Acceso no disponible</h1><a href="https://jakawi.com/mi-jakawi/productos-digitales">ABRIR MI BIBLIOTECA</a></body>';};
  let busy=false;
  async function check(){
    if(busy)return;busy=true;
    const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),10000);
    try{const r=await fetch('/gluteos/_check',{cache:'no-store',credentials:'same-origin',redirect:'error',signal:controller.signal});if(!r.ok || !(await r.json()).authorized)deny();}
    catch{deny();}finally{clearTimeout(timeout);busy=false;}
  }
  window.addEventListener('offline',deny);
  window.addEventListener('pageshow',check);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)check();});
  setInterval(check,30000);
  if('serviceWorker' in navigator)navigator.serviceWorker.register('/gluteos/sw.js',{scope:'/gluteos/',updateViaCache:'none'}).then(r=>r.update());
})();
