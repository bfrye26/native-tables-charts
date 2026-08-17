(function(){
'use strict';
function isNumericValue(v){return /^[-+]?(?:\d+\.?\d*|\.\d+)$/.test(String(v??'').trim());}
function cmp(a,b){const av=String(a??''),bv=String(b??'');if(isNumericValue(av)&&isNumericValue(bv))return Number(av)-Number(bv);return av.localeCompare(bv,undefined,{numeric:true,sensitivity:'base'});}
document.addEventListener('click',function(e){
 const btn=e.target.closest('.ntc-sort');if(!btn)return;const table=btn.closest('.ntc-table');if(!table)return;const col=Number(btn.dataset.column||0);const tbody=table.tBodies[0];if(!tbody)return;
 const current=btn.getAttribute('aria-sort')||'none';const next=current==='ascending'?'descending':'ascending';
 table.querySelectorAll('.ntc-sort').forEach(function(b){b.setAttribute('aria-sort','none');const th=b.closest('th');if(th)th.setAttribute('aria-sort','none');});
 btn.setAttribute('aria-sort',next);const activeTh=btn.closest('th');if(activeTh)activeTh.setAttribute('aria-sort',next);
 const firstHead=table.querySelector('thead th');const posLeft=firstHead&&firstHead.classList.contains('ntc-position-head');const offset=posLeft?1:0;const rows=Array.from(tbody.rows);
 rows.sort(function(ra,rb){const ca=ra.cells[col+offset],cb=rb.cells[col+offset];const v=cmp(ca?ca.dataset.sort:'',cb?cb.dataset.sort:'');return next==='descending'?-v:v;});
 rows.forEach(function(r,i){tbody.appendChild(r);if(table.dataset.updatePosition==='1'){const p=r.querySelector('.ntc-position');if(p)p.textContent=String(i+1);}});
});
})();
