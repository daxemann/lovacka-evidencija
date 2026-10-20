(function(){
var manjeKretanja=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
// uvod: računalo + mobitel se izmjenjuju
var r=document.querySelectorAll('[data-izmjena="racunalo"] img'),m=document.querySelectorAll('[data-izmjena="mobitel"] img'),o=document.getElementById('oznaka'),i=0;
function pokazi(n){r.forEach(function(e,k){e.classList.toggle('vidljiv',k===n)});m.forEach(function(e,k){e.classList.toggle('vidljiv',k===n)});if(o)o.textContent=r[n].alt+' · '+m[n].alt;}
if(r.length&&!manjeKretanja){setInterval(function(){i=(i+1)%r.length;pokazi(i)},4000)}
// iznutra: tabovi
var tabovi=[].slice.call(document.querySelectorAll('.tab')),slike=document.querySelectorAll('[data-izmjena="iznutra"] img'),t=0,rucno=manjeKretanja,tajmer;
function odaberi(n,traka){t=n;tabovi.forEach(function(b,k){b.setAttribute('aria-selected',k===n?'true':'false');b.tabIndex=k===n?0:-1;var s=b.querySelector('.traka');s.classList.remove('tece');if(k===n&&traka){void s.offsetWidth;s.classList.add('tece')}});slike.forEach(function(e,k){e.classList.toggle('vidljiv',k===n)})}
tabovi.forEach(function(b,k){b.addEventListener('click',function(){rucno=true;clearInterval(tajmer);odaberi(k,false)});b.addEventListener('keydown',function(e){var n=null;if(e.key==='ArrowDown'||e.key==='ArrowRight')n=(k+1)%tabovi.length;if(e.key==='ArrowUp'||e.key==='ArrowLeft')n=(k-1+tabovi.length)%tabovi.length;if(n!==null){e.preventDefault();rucno=true;clearInterval(tajmer);odaberi(n,false);tabovi[n].focus()}})});
if(tabovi.length){odaberi(0,!rucno);if(!rucno)tajmer=setInterval(function(){odaberi((t+1)%tabovi.length,true)},5000)}
})();