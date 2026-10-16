<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chat de atendimento</title>
<style>
:root{--brand:#c45100;--ink:#18181b;--muted:#71717a;--line:#e4e4e7;--surface:#fff;--soft:#f4f4f5;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light}
*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;overflow:hidden;background:transparent}button,input,textarea{font:inherit}
.launcher{position:absolute;right:15px;bottom:15px;width:58px;height:58px;border:0;border-radius:999px;color:#fff;background:var(--brand);box-shadow:0 6px 18px -6px rgba(24,24,27,.35);display:grid;place-items:center;cursor:pointer;transition:transform .18s ease,box-shadow .18s ease}.launcher:hover{transform:translateY(-2px);box-shadow:0 9px 22px -8px rgba(24,24,27,.38)}.launcher:focus-visible,.icon-button:focus-visible,.primary:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid color-mix(in srgb,var(--brand) 32%,transparent);outline-offset:2px}
.panel{position:absolute;inset:0;background:var(--surface);border:1px solid rgba(24,24,27,.1);border-radius:18px;box-shadow:0 20px 60px rgba(24,24,27,.22);overflow:hidden;display:none;grid-template-rows:auto 1fr auto}.open .panel{display:grid}.open .launcher{display:none}
.header{min-height:76px;padding:14px 14px 13px 16px;color:#fff;background:var(--brand);display:flex;align-items:center;gap:12px}.avatar{width:42px;height:42px;border-radius:13px;background:rgba(255,255,255,.18);display:grid;place-items:center;flex:none}.heading{min-width:0;flex:1}.heading strong{display:block;font-size:15px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.heading span{display:flex;align-items:center;gap:6px;margin-top:3px;font-size:12px;opacity:.92}.dot{width:7px;height:7px;border-radius:50%;background:#86efac}.icon-button{width:44px;height:44px;border:0;border-radius:12px;background:transparent;color:inherit;display:grid;place-items:center;cursor:pointer}.icon-button:hover{background:rgba(255,255,255,.14)}
.content{min-height:0;overflow:auto;padding:16px;background:#fafafa}.intro{font-size:12px;color:var(--muted);text-align:center;margin:0 0 14px}.messages{display:flex;flex-direction:column;gap:10px}.message{max-width:84%;padding:10px 12px;border-radius:14px;font-size:14px;line-height:1.45;white-space:pre-wrap;overflow-wrap:anywhere}.message.out{align-self:flex-start;background:#fff;color:var(--ink);border:1px solid var(--line);border-bottom-left-radius:5px}.message.in{align-self:flex-end;background:var(--brand);color:#fff;border-bottom-right-radius:5px}.time{display:block;margin-top:4px;font-size:10px;opacity:.65;text-align:right}
.start{height:100%;display:flex;flex-direction:column;justify-content:center;gap:12px}.start h2{font-size:18px;margin:0;color:var(--ink)}.start p{font-size:13px;line-height:1.5;color:var(--muted);margin:0}.field{display:grid;gap:6px}.field label{font-size:12px;font-weight:650;color:#3f3f46}.field input{min-height:44px;width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;color:var(--ink);background:#fff}.primary{min-height:44px;border:0;border-radius:10px;background:var(--brand);color:#fff;font-weight:700;cursor:pointer;padding:10px 16px}.primary:disabled{opacity:.55;cursor:not-allowed}.error{display:none;margin:0;padding:9px 10px;border-radius:9px;background:#fef2f2;color:#b91c1c;font-size:12px}.error.show{display:block}
.composer{padding:10px;background:#fff;border-top:1px solid var(--line);display:none;align-items:flex-end;gap:8px}.ready .composer{display:flex}.composer textarea{width:100%;min-height:44px;max-height:100px;resize:none;border:1px solid var(--line);border-radius:12px;padding:11px 12px;color:var(--ink)}.send{width:44px;height:44px;flex:none;border:0;border-radius:12px;background:var(--brand);color:#fff;display:grid;place-items:center;cursor:pointer}.send:disabled{opacity:.55}
.powered{font-size:10px;color:#a1a1aa;text-align:center;padding:0 10px 7px;background:#fff}.ready .powered{display:block}
@media(max-width:420px){.panel{border-radius:16px}.content{padding:13px}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
</style>
</head>
<body>
<div id="app">
<button id="launcher" class="launcher" type="button" aria-label="Abrir chat">
<svg width="27" height="27" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
</button>
<section class="panel" aria-label="Chat de atendimento">
<header class="header">
<div class="avatar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15a4 4 0 0 1-4 4H8l-4 2V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8Z" stroke="currentColor" stroke-width="2"/></svg></div>
<div class="heading"><strong id="title"></strong><span><i class="dot"></i> Atendimento disponível</span></div>
<button id="close" class="icon-button" type="button" aria-label="Fechar chat"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
</header>
<main class="content">
<div id="start" class="start">
<div><h2>Vamos conversar?</h2><p>Informe seus dados para iniciar o atendimento neste site.</p></div>
<div id="nameField" class="field"><label for="name">Seu nome</label><input id="name" autocomplete="name" maxlength="128"></div>
<div id="emailField" class="field"><label for="email">Seu e-mail</label><input id="email" type="email" autocomplete="email" maxlength="191"></div>
<p id="startError" class="error" role="alert"></p>
<button id="startButton" class="primary" type="button">Iniciar conversa</button>
</div>
<div id="chat" hidden><p class="intro">Você está conversando com a equipe de atendimento.</p><div id="messages" class="messages" aria-live="polite"></div></div>
</main>
<form id="composer" class="composer">
<textarea id="body" rows="1" maxlength="4000"></textarea>
<button id="send" class="send" type="submit" aria-label="Enviar mensagem"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M22 2 11 13" stroke="currentColor" stroke-width="2"/></svg></button>
</form>

</section>
</div>
<script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
(function(){
const c=@json($config),app=document.getElementById('app'),launcher=document.getElementById('launcher'),close=document.getElementById('close'),start=document.getElementById('start'),chat=document.getElementById('chat'),messages=document.getElementById('messages'),composer=document.getElementById('composer'),body=document.getElementById('body'),send=document.getElementById('send'),startButton=document.getElementById('startButton'),startError=document.getElementById('startError');
document.documentElement.style.setProperty('--brand',c.primaryColor);document.getElementById('title').textContent=c.title;body.placeholder=c.inputPlaceholder;
document.getElementById('nameField').style.display=c.requireName?'grid':'none';document.getElementById('emailField').style.display=c.requireEmail?'grid':'none';
let session='',lastId=0,pollTimer=null,busy=false;
const common={site:c.site,signature:c.signature};
function resize(open){app.classList.toggle('open',open);parent.postMessage({type:'respondesk:webchat:resize',open},'*');if(open&&!session&&!c.requireName&&!c.requireEmail)begin()}
launcher.addEventListener('click',()=>resize(true));close.addEventListener('click',()=>resize(false));
function showError(message){startError.textContent=message||'Não foi possível iniciar o atendimento. Tente novamente.';startError.classList.add('show')}
async function request(url,options={}){const response=await fetch(url,{...options,headers:{'Content-Type':'application/json','Accept':'application/json',...(options.headers||{})}});if(!response.ok){let data={};try{data=await response.json()}catch(e){}throw new Error(data.message||Object.values(data.errors||{})[0]?.[0]||'Falha na comunicação.')}return response.json()}
async function begin(){
if(busy)return;busy=true;startButton.disabled=true;startError.classList.remove('show');
try{const data=await request(c.sessionUrl+'?site='+encodeURIComponent(c.site)+'&signature='+encodeURIComponent(c.signature),{method:'POST',body:JSON.stringify({...common,visitor_id:c.visitor,name:document.getElementById('name').value,email:document.getElementById('email').value})});session=data.session;render(data.messages||[]);start.hidden=true;chat.hidden=false;app.classList.add('ready');body.focus();schedule()}
catch(e){showError(e.message)}finally{busy=false;startButton.disabled=false}}
startButton.addEventListener('click',begin);
function render(items){items.forEach(item=>{if(document.querySelector('[data-message-id="'+item.id+'"]'))return;lastId=Math.max(lastId,item.id);const el=document.createElement('div');el.className='message '+item.direction;el.dataset.messageId=item.id;const text=document.createElement('span');text.textContent=item.body||'';el.appendChild(text);if(item.sent_at){const time=document.createElement('span');time.className='time';time.textContent=new Date(item.sent_at).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});el.appendChild(time)}messages.appendChild(el)});if(items.length)messages.parentElement.scrollTop=messages.parentElement.scrollHeight}
async function poll(){if(!session)return;try{const url=c.messagesUrl+'?site='+encodeURIComponent(c.site)+'&signature='+encodeURIComponent(c.signature)+'&session='+encodeURIComponent(session)+'&after_id='+lastId;const data=await request(url);render(data.messages||[])}catch(e){}schedule()}
function schedule(){clearTimeout(pollTimer);pollTimer=setTimeout(poll,2500)}
composer.addEventListener('submit',async e=>{e.preventDefault();const text=body.value.trim();if(!text||busy)return;busy=true;send.disabled=true;body.value='';try{const data=await request(c.sendUrl+'?site='+encodeURIComponent(c.site)+'&signature='+encodeURIComponent(c.signature),{method:'POST',body:JSON.stringify({...common,session,body:text})});render([data.message])}catch(e){body.value=text;alert('A mensagem não foi enviada. Verifique sua conexão e tente novamente.')}finally{busy=false;send.disabled=false;body.focus();schedule()}});
body.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();composer.requestSubmit()}});
})();
</script>
</body>
</html>