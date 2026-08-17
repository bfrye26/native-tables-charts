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
function ntcGuardCell(v){var s=String(v??'');return /^[=+\-@]/.test(s)&&!/^[=+\-@][0-9.,]+$/.test(s)?("'"+s):s;}
function ntcCsvCell(v,d){var s=String(v??'');if(/["\n\r]/.test(s)||s.indexOf(d)>=0){s='"'+s.replace(/"/g,'""')+'"';}return s;}
function ntcTableToCsv(table,d){d=d||',';var lines=[];var head=table.querySelector('thead');if(head){lines.push(Array.prototype.map.call(head.querySelectorAll('tr th'),function(th){return ntcCsvCell(ntcGuardCell((th.textContent||'').trim()),d);}).join(d));}Array.prototype.forEach.call(table.tBodies[0].rows,function(tr){lines.push(Array.prototype.map.call(tr.cells,function(td){return ntcCsvCell(ntcGuardCell((td.textContent||'').trim()),d);}).join(d));});return lines.join('\r\n');}
function ntcDownload(body,name,mime){var blob=new Blob(['\ufeff'+body],{type:mime+';charset=utf-8'});var url=URL.createObjectURL(blob);var a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(url);},1000);}
function ntcBindTable(table){
	var tbody=table.tBodies[0];if(!tbody)return;
	var wrap=table.closest('.ntc-table-wrap');if(!wrap)return;
	var search=wrap.querySelector('.ntc-table-search');
	var pager=wrap.querySelector('.ntc-table-pager');
	var size=Number(pager&&pager.dataset.pageSize||0);
	var page=1;
	var allRows=Array.prototype.slice.call(tbody.rows);
	var filtered=allRows;
	var totalPages=function(){return size?Math.max(1,Math.ceil(filtered.length/size)):1;};
	var renderPage=function(){
		if(!pager)return;
		page=Math.min(page,totalPages());
		var start=(page-1)*size,end=Math.min(filtered.length,start+size);
		allRows.forEach(function(r){r.style.display='none';});
		filtered.slice(start,end).forEach(function(r){r.style.display='';});
		if(size&&filtered.length<=size){pager.classList.add('is-hidden');return;}
		pager.classList.remove('is-hidden');
		var label=pager.querySelector('.ntc-pager-label');
		if(label)label.textContent=page+' / '+totalPages();
		var prev=pager.querySelector('.ntc-pager-prev'),next=pager.querySelector('.ntc-pager-next');
		if(prev)prev.disabled=page<=1;
		if(next)next.disabled=page>=totalPages();
	};
	if(search){search.addEventListener('input',function(){
		var q=search.value.toLowerCase();
		filtered=q?allRows.filter(function(r){return r.textContent.toLowerCase().indexOf(q)>=0;}):allRows;
		page=1;
		if(pager&&size){renderPage();}
		else{
			var shown=new Set(filtered);
			allRows.forEach(function(r){r.style.display=shown.has(r)?'':'none';});
		}
	});}
	if(pager&&size){
		var prev=pager.querySelector('.ntc-pager-prev'),next=pager.querySelector('.ntc-pager-next');
		if(prev)prev.addEventListener('click',function(){if(page>1){page--;renderPage();}});
		if(next)next.addEventListener('click',function(){if(page<totalPages()){page++;renderPage();}});
		renderPage();
	}
	var csvBtn=wrap.querySelector('.ntc-export-btn[data-format="csv"]');
	if(csvBtn)csvBtn.addEventListener('click',function(){ntcDownload(ntcTableToCsv(table,','),'table-data.csv','text/csv');});
}
function ntcBindChart(fig){
	var csvBtn=fig.querySelector('.ntc-export-btn[data-format="csv"]');
	if(csvBtn){csvBtn.addEventListener('click',function(){
		var data=fig.querySelector('.ntc-chart-export-data table')||fig.querySelector('.ntc-chart-data-sr table')||fig.querySelector('.ntc-chart-data table');
		if(data)ntcDownload(ntcTableToCsv(data,','),'chart-data.csv','text/csv');
	});}
	var pngBtn=fig.querySelector('.ntc-export-btn[data-format="png"]');
	if(pngBtn)pngBtn.addEventListener('click',function(){
		var svg=fig.querySelector('svg.ntc-donut,svg.ntc-svg-chart');
		if(!svg)return;
		try{
			var styleSel='.ntc-svg-line,.ntc-svg-point,.ntc-svg-grid,.ntc-svg-label,.ntc-donut-bg,.ntc-donut-seg';
			var origEls=svg.querySelectorAll(styleSel);
			var clone=svg.cloneNode(true);
			clone.setAttribute('xmlns','http://www.w3.org/2000/svg');
			var box=svg.viewBox&&svg.viewBox.baseVal?svg.viewBox.baseVal:{width:960,height:540};
			var w=Math.max(100,box.width||960),h=Math.max(100,box.height||540),scale=2;
			clone.setAttribute('width',w);clone.setAttribute('height',h);
			Array.prototype.forEach.call(clone.querySelectorAll(styleSel),function(el,i){
				var orig=origEls[i];if(!orig)return;
				var cs=window.getComputedStyle(orig);
				['stroke','fill','stroke-width'].forEach(function(p){
					var v=cs.getPropertyValue(p);
					if(v&&v!=='none')el.style[p]=v;
				});
			});
			var xml=new XMLSerializer().serializeToString(clone);
			var img=new Image();
			img.onload=function(){
				var canvas=document.createElement('canvas');canvas.width=w*scale;canvas.height=h*scale;
				var ctx=canvas.getContext('2d');
				var bg=window.getComputedStyle(fig).getPropertyValue('--ntc-chart-bg')||'#ffffff';
				ctx.fillStyle=bg;ctx.fillRect(0,0,canvas.width,canvas.height);
				ctx.drawImage(img,0,0,canvas.width,canvas.height);
				canvas.toBlob(function(blob){if(!blob)return;var url=URL.createObjectURL(blob);var a=document.createElement('a');a.href=url;a.download='chart.png';document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(url);},1000);},'image/png');
			};
			img.src='data:image/svg+xml;charset=utf-8,'+encodeURIComponent(xml);
		}catch(e){}
	});
}
document.addEventListener('DOMContentLoaded',function(){
	document.querySelectorAll('.ntc-table').forEach(ntcBindTable);
	document.querySelectorAll('.ntc-chart').forEach(ntcBindChart);
});
window.NTC_TEST={ntcGuardCell:ntcGuardCell,ntcCsvCell:ntcCsvCell,ntcTableToCsv:ntcTableToCsv};
})();
