(function(wp){
'use strict';
const h=wp.element.createElement;
const {useState,useEffect,useRef,useMemo}=wp.element;
const {registerBlockType,registerBlockVariation,createBlock}=wp.blocks;
const {InspectorControls,BlockControls,MediaUpload,MediaUploadCheck,useBlockProps}=wp.blockEditor;
const C=wp.components;
const {PanelBody,SelectControl,TextControl,TextareaControl,ToggleControl,RangeControl,Button,ToolbarGroup,ToolbarButton,DropdownMenu,Dropdown,Modal,Notice,ColorPalette,CheckboxControl,Spinner}=C;
const {__}=wp.i18n;
const apiFetch=wp.apiFetch;
const SSR=wp.serverSideRender && (wp.serverSideRender.default||wp.serverSideRender);
const CFG=window.NTC_EDITOR||{};
if(apiFetch&&apiFetch.use&&apiFetch.createNonceMiddleware&&CFG.nonce){apiFetch.use(apiFetch.createNonceMiddleware(CFG.nonce));}

const clone=o=>JSON.parse(JSON.stringify(o));
const clamp=(n,a,b)=>Math.max(a,Math.min(b,n));
const defaultColumns=()=>[{id:'c1',label:'Item',type:'text',unit:''},{id:'c2',label:'Value',type:'number',unit:''}];
const defaultRows=()=>[['Example','100']];
const tableAttributes={widthMode:{type:'string'},align:{type:'string',default:''},mode:{type:'string',default:'inline'},datasetId:{type:'number',default:0},viewId:{type:'number',default:0},columns:{type:'array',default:[{id:'c1',label:'Item',type:'text',unit:''},{id:'c2',label:'Value',type:'number',unit:''}]},rows:{type:'array',default:[['Example','100']]},config:{type:'object',default:{}},cellMeta:{type:'object',default:{}}};
const chartAttributes={widthMode:{type:'string'},align:{type:'string',default:''},mode:{type:'string',default:'inline'},datasetId:{type:'number',default:0},viewId:{type:'number',default:0},columns:{type:'array',default:[{id:'c1',label:'Product',type:'text',unit:''},{id:'c2',label:'Score',type:'number',unit:''}]},rows:{type:'array',default:[['Product A','100'],['Product B','85'],['Product C','72']]},config:{type:'object',default:{chartType:'horizontal-bar',title:'Benchmark',subtitle:'',labelColumn:0,valueColumns:[1],sortColumn:1,sortDirection:'desc',preset:'benchmark-dark',typographyPreset:'comfortable',density:'auto',accessibleDataMode:'screenreader',allowMultipleHighlights:false}},cellMeta:{type:'object',default:{}}};
const colOptions=cols=>cols.map((c,i)=>({label:(i+1)+'. '+(c.label||('Column '+(i+1))),value:String(i)}));
const chartTypes=[
 {label:__('Horizontal Bar','native-tables-charts'),value:'horizontal-bar'},
 {label:__('Dual-Metric Benchmark','native-tables-charts'),value:'dual-metric'},
 {label:__('Vertical Bar','native-tables-charts'),value:'vertical-bar'},
 {label:__('Grouped Bar','native-tables-charts'),value:'grouped-bar'},
 {label:__('Stacked Bar','native-tables-charts'),value:'stacked-bar'},
 {label:__('Line','native-tables-charts'),value:'line'},
 {label:__('Scatter','native-tables-charts'),value:'scatter'},
 {label:__('Donut','native-tables-charts'),value:'donut'}
];
const colTypes=['auto','text','number','currency','percent','url','time','iso_date','us_long_date','short_date','sparkline','delta'].map(v=>({label:v.replace(/_/g,' '),value:v}));


function CompactColorControl({label,value,onChange,placeholder}){
 const shown=String(value||'');const fallback=/^#[0-9a-f]{6}$/i.test(String(placeholder||''))?String(placeholder):'#000000';const swatch=/^#[0-9a-f]{6}$/i.test(shown)?shown:fallback;
 return h('div',{className:'ntc-color-control'},h('label',null,label),h('div',{className:'ntc-color-control-row'},h('input',{type:'color',value:swatch,'aria-label':label,onChange:e=>onChange(e.target.value)}),h('input',{type:'text',value:shown,placeholder:placeholder||'',onChange:e=>onChange(e.target.value)})));
}

const humanizeKey=v=>String(v||'').replace(/^custom:/,'').replace(/[-_]+/g,' ').replace(/\b\w/g,m=>m.toUpperCase());
function PresetPreview({type,settings,compact}){
 const st=settings||{};
 if(type==='chart')return h('div',{className:'ntc-preset-preview ntc-preset-preview-chart'+(compact?' is-compact':''),style:{background:st.background||'#09111b',color:st.textColor||'#fff'}},
   h('span',{className:'ntc-preset-preview-title',style:{background:st.textColor||'#fff'}}),
   h('span',{className:'ntc-preset-preview-grid',style:{borderColor:st.gridColor||'#263445'}}),
   h('span',{className:'ntc-preset-preview-bar',style:{background:st.primaryColor||'#624b8e',width:'88%'}}),
   h('span',{className:'ntc-preset-preview-bar',style:{background:st.highlightColor||'#9e2f5f',width:'68%'}}),
   h('span',{className:'ntc-preset-preview-bar',style:{background:st.secondaryColor||st.primaryColor||'#8b73b8',width:'50%'}})
 );
 return h('div',{className:'ntc-preset-preview ntc-preset-preview-table'+(compact?' is-compact':''),style:{background:st.oddBackground||'#fff',color:st.bodyColor||'#252a34',borderColor:st.borderColor||'#dfe3e8'}},
   h('span',{className:'ntc-preset-preview-table-head',style:{background:st.headerBackground||'#151922',color:st.headerColor||'#fff'}}),
   h('span',{style:{background:st.oddBackground||'#fff'}}),
   h('span',{style:{background:st.evenBackground||'#f6f7f8'}}),
   h('span',{style:{background:st.oddBackground||'#fff'}})
 );
}
function PresetBrowser({type,presets,customPresets,value,onApply,label}){
 const built=Object.keys(presets||{}).map(k=>({value:k,label:humanizeKey(k),settings:presets[k]||{},custom:false}));
 const custom=(customPresets||[]).filter(p=>p.type===type).map(p=>({value:'custom:'+p.id,label:p.name,settings:p.settings||{},custom:true}));
 const entries=built.concat(custom);const current=entries.find(x=>x.value===value)||entries[0]||{label:humanizeKey(value),settings:{}};
 return h('div',{className:'ntc-preset-browser'},
   h('div',{className:'ntc-preset-current'},h(PresetPreview,{type,settings:current.settings,compact:true}),h('div',null,h('strong',null,current.label),h('span',null,current.custom?__('Custom style','native-tables-charts'):__('Built-in style','native-tables-charts')))),
   h(Dropdown,{className:'ntc-preset-dropdown',contentClassName:'ntc-preset-popover',renderToggle:({isOpen,onToggle})=>h(Button,{variant:'secondary',onClick:onToggle,'aria-expanded':isOpen},label||(type==='chart'?__('Browse chart themes','native-tables-charts'):__('Browse table styles','native-tables-charts'))),renderContent:({onClose})=>h('div',{className:'ntc-preset-grid'},entries.map(item=>h('button',{type:'button',key:item.value,className:'ntc-preset-card'+(item.value===value?' is-selected':''),onClick:()=>{onApply(item.value);onClose();}},h(PresetPreview,{type,settings:item.settings}),h('span',{className:'ntc-preset-card-name'},item.label),item.custom&&h('small',null,__('Custom','native-tables-charts')))))})
 );
}
function ChartTypographyControls({config,onPatch}){
 const presets=CFG.chartTypographyPresets||{};const value=config.typographyPreset||'comfortable';
 const apply=v=>{const p=presets[v]||{};onPatch(Object.assign({typographyPreset:v},p));};
 const customRange=(label,key,min,max)=>h(RangeControl,{label,value:Number(config[key]||0),min,max,onChange:v=>onPatch({typographyPreset:'custom',[key]:v})});
 return h(wp.element.Fragment,null,
   h(SelectControl,{label:__('Typography preset','native-tables-charts'),value,options:[{label:__('Compact','native-tables-charts'),value:'compact'},{label:__('Comfortable','native-tables-charts'),value:'comfortable'},{label:__('Presentation','native-tables-charts'),value:'presentation'},{label:__('Custom','native-tables-charts'),value:'custom'}],onChange:apply}),
   customRange(__('Title size','native-tables-charts'),'titleFontSize',18,48),
   customRange(__('Subtitle size','native-tables-charts'),'subtitleFontSize',10,24),
   customRange(__('Product / category labels','native-tables-charts'),'labelFontSize',10,24),
   customRange(__('Value labels','native-tables-charts'),'valueFontSize',10,24),
   customRange(__('Axis labels','native-tables-charts'),'axisFontSize',9,18),
   customRange(__('Legend text','native-tables-charts'),'legendFontSize',9,20),
   customRange(__('Footer text','native-tables-charts'),'footerFontSize',9,18)
 );
}
function ChartDensityControls({config,onPatch,rowCount}){
 const presets=CFG.chartDensityPresets||{};const value=config.density||'auto';
 const apply=v=>onPatch(Object.assign({density:v},presets[v]||{}));
 return h(wp.element.Fragment,null,
   h(SelectControl,{label:__('Chart density','native-tables-charts'),value,options:[{label:__('Auto','native-tables-charts'),value:'auto'},{label:__('Spacious','native-tables-charts'),value:'spacious'},{label:__('Comfortable','native-tables-charts'),value:'comfortable'},{label:__('Compact','native-tables-charts'),value:'compact'},{label:__('Custom','native-tables-charts'),value:'custom'}],onChange:apply,help:value==='auto'?__('Auto adjusts bar thickness and spacing to the number of rows.','native-tables-charts'):undefined}),
   value==='custom'&&h(RangeControl,{label:__('Bar height','native-tables-charts'),value:Number(config.barHeight||26),min:12,max:52,onChange:v=>onPatch({density:'custom',barHeight:v})}),
   value==='custom'&&h(RangeControl,{label:__('Bar gap','native-tables-charts'),value:Number(config.barGap||10),min:0,max:30,onChange:v=>onPatch({density:'custom',barGap:v})}),
   value==='auto'&&h('p',{className:'ntc-inspector-note'},sprintfDensity(rowCount))
 );
}
function sprintfDensity(rowCount){const n=Number(rowCount||0);if(n<=4)return __('Auto currently uses a spacious layout for this small dataset.','native-tables-charts');if(n<=10)return __('Auto currently uses a comfortable layout for this dataset.','native-tables-charts');if(n<=18)return __('Auto currently uses a medium-density layout for this dataset.','native-tables-charts');return __('Auto currently uses a compact layout for this larger dataset.','native-tables-charts');}

function StyleControls({type,attributes,setAttributes,customPresets}){
 const isChart=type==='chart';
 const defaults=isChart?(CFG.chartDefaults||{}):(CFG.tableDefaults||{});
 const presets=isChart?(CFG.chartPresets||{}):(CFG.tablePresets||{});
 const raw=attributes.config||{};const presetValue=raw.preset||(isChart?'benchmark-dark':'editorial');let presetSettings={};
 if(String(presetValue).startsWith('custom:')){const p=(customPresets||[]).find(x=>String(x.id)===String(presetValue).split(':')[1]);presetSettings=(p&&p.settings)||{};}else presetSettings=presets[presetValue]||{};
 const config=Object.assign({},defaults,presetSettings,raw);
 const setConfig=patch=>setAttributes({config:Object.assign({},attributes.config||{},patch)});
 const applyPreset=v=>{
   if(v.startsWith('custom:')){const p=(customPresets||[]).find(x=>String(x.id)===v.split(':')[1]);if(p)setAttributes({config:Object.assign({},attributes.config||{},p.settings||{},{preset:v})});}
   else setAttributes({config:Object.assign({},attributes.config||{},presets[v]||{},{preset:v})});
 };
 if(isChart)return h(wp.element.Fragment,null,
   h(PresetBrowser,{type:'chart',presets,customPresets,value:config.preset||'benchmark-dark',onApply:applyPreset,label:__('Browse chart themes','native-tables-charts')}),
   h(SelectControl,{label:__('Typography','native-tables-charts'),value:config.typographyPreset||'comfortable',options:[{label:__('Compact','native-tables-charts'),value:'compact'},{label:__('Comfortable','native-tables-charts'),value:'comfortable'},{label:__('Presentation','native-tables-charts'),value:'presentation'},{label:__('Custom','native-tables-charts'),value:'custom'}],onChange:v=>setConfig(Object.assign({typographyPreset:v},(CFG.chartTypographyPresets||{})[v]||{}))}),
   h(SelectControl,{label:__('Density','native-tables-charts'),value:config.density||'auto',options:[{label:__('Auto','native-tables-charts'),value:'auto'},{label:__('Spacious','native-tables-charts'),value:'spacious'},{label:__('Comfortable','native-tables-charts'),value:'comfortable'},{label:__('Compact','native-tables-charts'),value:'compact'},{label:__('Custom','native-tables-charts'),value:'custom'}],onChange:v=>setConfig(Object.assign({density:v},(CFG.chartDensityPresets||{})[v]||{}))}),
   h(CompactColorControl,{label:__('Background','native-tables-charts'),value:config.background||'',placeholder:'#09111b',onChange:v=>setConfig({background:v})}),
   h(CompactColorControl,{label:__('Primary series','native-tables-charts'),value:config.primaryColor||'',placeholder:'#624b8e',onChange:v=>setConfig({primaryColor:v})}),
   h(CompactColorControl,{label:__('Secondary series','native-tables-charts'),value:config.secondaryColor||'',placeholder:'#8b73b8',onChange:v=>setConfig({secondaryColor:v})}),
   h(CompactColorControl,{label:__('Highlight colour','native-tables-charts'),value:config.highlightColor||'',placeholder:'#9e2f5f',onChange:v=>setConfig({highlightColor:v})}),
   h(CompactColorControl,{label:__('Text colour','native-tables-charts'),value:config.textColor||'',placeholder:'#f5f7fa',onChange:v=>setConfig({textColor:v})}),
   h(CompactColorControl,{label:__('Muted text','native-tables-charts'),value:config.mutedColor||'',placeholder:'#9ba8b8',onChange:v=>setConfig({mutedColor:v})}),
   h(CompactColorControl,{label:__('Grid colour','native-tables-charts'),value:config.gridColor||'',placeholder:'#263445',onChange:v=>setConfig({gridColor:v})})
 );
 return h(wp.element.Fragment,null,
   h(PresetBrowser,{type:'table',presets,customPresets,value:config.preset||'editorial',onApply:applyPreset,label:__('Browse table styles','native-tables-charts')}),
   h(CompactColorControl,{label:__('Header background','native-tables-charts'),value:config.headerBackground||'',placeholder:'#151922',onChange:v=>setConfig({headerBackground:v})}),
   h(CompactColorControl,{label:__('Header text','native-tables-charts'),value:config.headerColor||'',placeholder:'#ffffff',onChange:v=>setConfig({headerColor:v})}),
   h(CompactColorControl,{label:__('Odd row background','native-tables-charts'),value:config.oddBackground||'',placeholder:'#ffffff',onChange:v=>setConfig({oddBackground:v})}),
   h(CompactColorControl,{label:__('Even row background','native-tables-charts'),value:config.evenBackground||'',placeholder:'#f6f7f8',onChange:v=>setConfig({evenBackground:v})}),
   h(CompactColorControl,{label:__('Body text','native-tables-charts'),value:config.bodyColor||'',placeholder:'#252a34',onChange:v=>setConfig({bodyColor:v})}),
   h(CompactColorControl,{label:__('Link colour','native-tables-charts'),value:config.linkColor||'',placeholder:'#b51f56',onChange:v=>setConfig({linkColor:v})}),
   h(CompactColorControl,{label:__('Accent colour','native-tables-charts'),value:config.accentColor||'',placeholder:'#9e2f5f',onChange:v=>setConfig({accentColor:v})}),
   h(CompactColorControl,{label:__('Border colour','native-tables-charts'),value:config.borderColor||'',placeholder:'#dfe3e8',onChange:v=>setConfig({borderColor:v})}),
   h(RangeControl,{label:__('Header font size','native-tables-charts'),value:Number(config.headerFontSize||13),min:9,max:30,onChange:v=>setConfig({headerFontSize:v})}),
   h(RangeControl,{label:__('Body font size','native-tables-charts'),value:Number(config.fontSize||14),min:9,max:30,onChange:v=>setConfig({fontSize:v})}),
   h(RangeControl,{label:__('Border radius','native-tables-charts'),value:Number(config.borderRadius||0),min:0,max:30,onChange:v=>setConfig({borderRadius:v})})
 );
}
function StyleInspector(props){return h(InspectorControls,{group:'styles'},h(PanelBody,{title:props.type==='chart'?__('Chart appearance','native-tables-charts'):__('Table appearance','native-tables-charts'),initialOpen:true},h(StyleControls,props)));}
function QuickStyleModal(props){return h(Modal,{title:props.type==='chart'?__('Chart Style','native-tables-charts'):__('Table Style','native-tables-charts'),onRequestClose:props.onClose,className:'ntc-style-modal'},h('p',{className:'ntc-inspector-note'},__('These settings update the block preview immediately. Full layout, responsive and advanced controls remain in the block sidebar.','native-tables-charts')),h(StyleControls,props),h('div',{className:'ntc-inline-actions'},h(Button,{variant:'primary',onClick:props.onClose},__('Done','native-tables-charts'))));}

function parseDelimited(text,delimiter,headers){
 const matrix=[];let row=[],cur='',quoted=false;
 const pushField=()=>{row.push(cur);cur='';};
 const pushRow=()=>{if(row.some(v=>String(v).trim()!=='')){matrix.push(row);}row=[];};
 for(let i=0;i<text.length;i++){
   const ch=text[i];
   if(ch==='"'){
     if(quoted&&text[i+1]==='"'){cur+='"';i++;}
     else quoted=!quoted;
   }else if(ch===delimiter&&!quoted){pushField();}
   else if((ch==='\n'||ch==='\r')&&!quoted){if(ch==='\r'&&text[i+1]==='\n')i++;pushField();pushRow();}
   else cur+=ch;
 }
 if(cur!==''||row.length){pushField();pushRow();}
 if(!matrix.length)return {columns:[],rows:[]};
 let header;if(headers)header=matrix.shift();else header=Array.from({length:Math.max(...matrix.map(r=>r.length))},(_,i)=>'Column '+(i+1));
 const columns=header.slice(0,40).map((v,i)=>({id:'c'+(i+1),label:v||('Column '+(i+1)),type:i===0?'text':'auto',unit:''}));
 const rows=matrix.slice(0,10000).map(r=>Array.from({length:columns.length},(_,i)=>r[i]??''));
 return {columns,rows};
}

function parseJsonImport(text){
 const data=JSON.parse(text);let columns=[],rows=[];
 if(data&&Array.isArray(data.columns)&&Array.isArray(data.rows)){
   columns=data.columns.slice(0,40).map((c,i)=>({id:c.id||('c'+(i+1)),label:c.label||('Column '+(i+1)),type:c.type||'auto',unit:c.unit||''}));
   rows=data.rows.slice(0,10000).map(r=>Array.from({length:columns.length},(_,i)=>Array.isArray(r)?(r[i]??''):(r&&typeof r==='object'?(r[columns[i].id]??r[columns[i].label]??''):'')));
   return {columns,rows};
 }
 const arr=Array.isArray(data)?data:[];if(!arr.length)return {columns:[],rows:[]};
 if(Array.isArray(arr[0])){
   const width=Math.min(40,Math.max(...arr.map(r=>r.length)));columns=Array.from({length:width},(_,i)=>({id:'c'+(i+1),label:'Column '+(i+1),type:i===0?'text':'auto',unit:''}));rows=arr.slice(0,10000).map(r=>Array.from({length:width},(_,i)=>r[i]??''));return {columns,rows};
 }
 const keys=Object.keys(arr[0]||{}).slice(0,40);columns=keys.map((k,i)=>({id:'c'+(i+1),label:k,type:i===0?'text':'auto',unit:''}));rows=arr.slice(0,10000).map(r=>keys.map(k=>r[k]??''));return {columns,rows};
}

function DataImportModal({onClose,onImport}){
 const [text,setText]=useState('');const [headers,setHeaders]=useState(true);const [format,setFormat]=useState('auto');const [error,setError]=useState('');
 const doImport=()=>{try{setError('');let result;if(format==='json'||(format==='auto'&&text.trim().startsWith('['))||(format==='auto'&&text.trim().startsWith('{')))result=parseJsonImport(text);else{const d=format==='tsv'?'\t':format==='csv'?',':(text.includes('\t')?'\t':',');result=parseDelimited(text,d,headers);}if(!result.columns.length)throw new Error(__('No usable data was found.','native-tables-charts'));onImport(result);onClose();}catch(e){setError(e&&e.message?e.message:__('Could not import the data.','native-tables-charts'));}};
 const onFile=e=>{const f=e.target.files&&e.target.files[0];if(!f)return;const ext=(f.name.split('.').pop()||'').toLowerCase();if(['csv','tsv','json'].includes(ext))setFormat(ext);const r=new FileReader();r.onload=()=>setText(String(r.result||''));r.readAsText(f);};
 return h(Modal,{title:__('Import or Paste Data','native-tables-charts'),onRequestClose:onClose,className:'ntc-import-modal'},
  h('p',null,__('Paste from Excel or Google Sheets, or import CSV, TSV or JSON. Data stays inside WordPress.','native-tables-charts')),
  h('input',{type:'file',accept:'.csv,.tsv,.txt,.json,text/csv,text/tab-separated-values,application/json',onChange:onFile}),
  h(SelectControl,{label:__('Format','native-tables-charts'),value:format,options:[{label:__('Auto-detect','native-tables-charts'),value:'auto'},{label:'CSV',value:'csv'},{label:'TSV',value:'tsv'},{label:'JSON',value:'json'}],onChange:setFormat}),
  h(TextareaControl,{label:__('Data','native-tables-charts'),value:text,onChange:setText,rows:10,className:'ntc-paste-area'}),
  format!=='json'&&h(ToggleControl,{label:__('First row contains column headers','native-tables-charts'),checked:headers,onChange:setHeaders}),
  error&&h(Notice,{status:'error',isDismissible:false},error),
  h('div',{className:'ntc-inline-actions'},h(Button,{variant:'primary',onClick:doImport,disabled:!text.trim()},__('Import','native-tables-charts')),h(Button,{variant:'tertiary',onClick:onClose},__('Cancel','native-tables-charts')))
 );
}

function serializeDelimited(columns,rows,delimiter){
 const esc=v=>{let s=String(v??'');if(s.includes('"'))s=s.replace(/"/g,'""');return (s.includes(delimiter)||s.includes('\n')||s.includes('"'))?'"'+s+'"':s;};
 return [columns.map(c=>esc(c.label||'')).join(delimiter)].concat(rows.map(r=>columns.map((_,i)=>esc(r[i]??'')).join(delimiter))).join('\r\n');
}
function downloadData(columns,rows,format,name){
 const safe=(name||'native-data').replace(/[^a-z0-9_-]+/gi,'-').replace(/^-+|-+$/g,'')||'native-data';let body,mime,ext;
 if(format==='json'){body=JSON.stringify({columns,rows},null,2);mime='application/json';ext='json';}else{const tab=format==='tsv';body=serializeDelimited(columns,rows,tab?'\t':',');mime=tab?'text/tab-separated-values':'text/csv';ext=tab?'tsv':'csv';}
 const blob=new Blob([body],{type:mime+';charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=safe+'.'+ext;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
}
function ExportModal({columns,rows,onClose,type}){
 const [format,setFormat]=useState('csv');const [name,setName]=useState(type==='chart'?'chart-data':'table-data');
 return h(Modal,{title:__('Export Data','native-tables-charts'),onRequestClose:onClose},h(TextControl,{label:__('File name','native-tables-charts'),value:name,onChange:setName}),h(SelectControl,{label:__('Format','native-tables-charts'),value:format,options:[{label:'CSV',value:'csv'},{label:'TSV',value:'tsv'},{label:'JSON',value:'json'}],onChange:setFormat}),h('p',null,__('Exports the current data exactly as it appears in the editor.','native-tables-charts')),h('div',{className:'ntc-inline-actions'},h(Button,{variant:'primary',onClick:()=>{downloadData(columns,rows,format,name);onClose();}},__('Export','native-tables-charts')),h(Button,{variant:'tertiary',onClick:onClose},__('Cancel','native-tables-charts'))));
}

function DatasetPicker({onClose,onPick,type}){
 const [items,setItems]=useState(null);const [views,setViews]=useState(null);const [search,setSearch]=useState('');
 const load=()=>Promise.all([
   apiFetch({path:'/ntc/v1/datasets?per_page=250&search='+encodeURIComponent(search)}),
   apiFetch({path:'/ntc/v1/views?type='+encodeURIComponent(type||'')})
 ]).then(([d,v])=>{setItems(d||[]);const q=search.toLowerCase();setViews((v||[]).filter(x=>!q||String(x.name||'').toLowerCase().includes(q)));});
 useEffect(load,[]);
 return h(Modal,{title:__('Choose Existing Data or View','native-tables-charts'),onRequestClose:onClose},
  h('div',{className:'ntc-inline-actions'},h(TextControl,{label:__('Search','native-tables-charts'),value:search,onChange:setSearch}),h(Button,{variant:'secondary',onClick:load},__('Search','native-tables-charts'))),
  (items===null||views===null)?h(Spinner):h(wp.element.Fragment,null,
   h('h3',null,type==='chart'?__('Synced Charts','native-tables-charts'):__('Synced Tables','native-tables-charts')),
   views.length?h('div',{className:'ntc-dataset-picker'},views.map(v=>h(Button,{key:'v'+v.id,variant:'secondary',onClick:()=>onPick({kind:'view',item:v})},v.name))):h('p',{className:'ntc-inspector-note'},__('No matching synced views.','native-tables-charts')),
   h('h3',null,__('Reusable Datasets','native-tables-charts')),
   items.length?h('div',{className:'ntc-dataset-picker'},items.map(d=>h(Button,{key:'d'+d.id,variant:'secondary',onClick:()=>onPick({kind:'dataset',item:d})},d.name+' ('+d.row_count+' rows)'))):h('p',null,__('No datasets found.','native-tables-charts'))
  )
 );
}

function VirtualGrid({columns,rows,onColumns,onRows,selected,onSelect,onDeleteRow,onDeleteCol,focusValues,onToggleFocus,onFocusLabelChange,labelColumn,allowMultipleFocus}){
 const scroller=useRef(null);const [scrollTop,setScrollTop]=useState(0);const rowH=34;const viewH=370;const overscan=8;
 const start=clamp(Math.floor(scrollTop/rowH)-overscan,0,Math.max(0,rows.length-1));const count=Math.ceil(viewH/rowH)+overscan*2;const end=Math.min(rows.length,start+count);
 const updateCell=(ri,ci,value)=>{const next=rows.slice();const row=(next[ri]||[]).slice();while(row.length<columns.length)row.push('');const previous=row[ci]??'';row[ci]=value;next[ri]=row;onRows(next,ri);if(onFocusLabelChange&&ci===labelColumn&&String(previous)!==String(value))onFocusLabelChange(String(previous),String(value));};
 const updateCol=(ci,key,value)=>{const next=columns.map((c,i)=>i===ci?Object.assign({},c,{[key]:value}):c);onColumns(next);};
 const onPaste=(e,ri,ci)=>{const text=e.clipboardData&&e.clipboardData.getData('text/plain');if(!text||(!text.includes('\t')&&!text.includes('\n')))return;e.preventDefault();const parsed=parseDelimited(text,'\t',false);const next=rows.map(r=>r.slice());parsed.rows.forEach((r,rOff)=>{const target=ri+rOff;if(target>=10000)return;while(next.length<=target)next.push(Array(columns.length).fill(''));r.forEach((v,cOff)=>{const tc=ci+cOff;if(tc<columns.length)next[target][tc]=v;});});onRows(next,null);};
 const inSelection=(ri,ci)=>{if(!selected||selected.r<0)return false;const r1=Math.min(selected.r,selected.r2??selected.r),r2=Math.max(selected.r,selected.r2??selected.r),c1=Math.min(selected.c,selected.c2??selected.c),c2=Math.max(selected.c,selected.c2??selected.c);return ri>=r1&&ri<=r2&&ci>=c1&&ci<=c2;};
 const select=(e,r,c)=>onSelect({r,c,shift:!!(e&&e.shiftKey)});
 const keyNav=(e,ri,ci)=>{if(e.key==='Enter'){e.preventDefault();const nr=clamp(ri+(e.shiftKey?-1:1),0,rows.length-1);onSelect({r:nr,c:ci,shift:false});setTimeout(()=>{const el=scroller.current&&scroller.current.querySelector('[data-r="'+nr+'"][data-c="'+ci+'"]');if(el)el.focus();},0);}else if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();const dr=e.key==='ArrowUp'?-1:e.key==='ArrowDown'?1:0;const dc=e.key==='ArrowLeft'?-1:e.key==='ArrowRight'?1:0;const nr=clamp(ri+dr,0,Math.max(0,rows.length-1));const nc=clamp(ci+dc,0,Math.max(0,columns.length-1));onSelect({r:nr,c:nc,shift:!!e.shiftKey});setTimeout(()=>{const el=scroller.current&&scroller.current.querySelector('[data-r="'+nr+'"][data-c="'+nc+'"]');if(el)el.focus();},0);}};
 const hasFocusControls=!!onToggleFocus;
 return h('div',{className:'ntc-grid-wrap'+(hasFocusControls?' has-focus-controls':''),ref:scroller,onScroll:e=>setScrollTop(e.currentTarget.scrollTop)},
  h('div',{className:'ntc-grid-header'},h('div',{className:'ntc-grid-corner'+(hasFocusControls?' has-focus-controls':'')},hasFocusControls&&h('span',{className:'ntc-focus-column-head',title:__('Chart focus','native-tables-charts')},__('Focus','native-tables-charts')),h('span',{className:'ntc-row-number-head'},'#')),columns.map((c,ci)=>h('div',{className:'ntc-grid-colhead',key:ci},h('input',{value:c.label||'',title:__('Column name','native-tables-charts'),onFocus:e=>select(e,-1,ci),onClick:e=>select(e,-1,ci),onChange:e=>updateCol(ci,'label',e.target.value)}),columns.length>1&&h('button',{type:'button',title:__('Delete column','native-tables-charts'),onClick:()=>onDeleteCol(ci)},'×')))),
  h('div',{className:'ntc-grid-spacer',style:{height:(rows.length*rowH)+'px'}},Array.from({length:end-start},(_,k)=>{const ri=start+k;const row=rows[ri]||[];const isFocused=(focusValues||[]).includes(String(row[labelColumn]??''));return h('div',{className:'ntc-grid-row'+(isFocused?' is-chart-focus':''),key:ri,style:{top:(ri*rowH)+'px'}},h('div',{className:'ntc-grid-rownum'+(hasFocusControls?' has-focus-controls':'')},hasFocusControls&&h('button',{type:'button',className:'ntc-focus-toggle'+(isFocused?' is-active':''),title:isFocused?__('Clear chart focus','native-tables-charts'):__('Set as chart focus','native-tables-charts'),'aria-label':isFocused?__('Clear chart focus for this row','native-tables-charts'):__('Set this row as chart focus','native-tables-charts'),'aria-pressed':isFocused?'true':'false',onClick:e=>{e.preventDefault();e.stopPropagation();onToggleFocus(ri);}},h('span',{className:'ntc-focus-glyph','aria-hidden':'true'},isFocused?'★':'☆')),h('span',{className:'ntc-row-number'},String(ri+1))),columns.map((c,ci)=>h('input',{'data-r':ri,'data-c':ci,key:ci,className:'ntc-grid-cell'+(inSelection(ri,ci)?' is-selected':''),value:row[ci]??'',onFocus:e=>{if(!selected||selected.r!==ri||selected.c!==ci)select(e,ri,ci);},onMouseDown:e=>select(e,ri,ci),onChange:e=>updateCell(ri,ci,e.target.value),onPaste:e=>onPaste(e,ri,ci),onKeyDown:e=>keyNav(e,ri,ci)})))}))
 );
}

function CellProperties({selected,cellMeta,setCellMeta}){
 if(!selected)return null;
 const header=selected.r<0;const firstKey=header?'header:'+selected.c:selected.r+':'+selected.c;const meta=cellMeta[firstKey]||{};
 const f=Object.assign({textColor:true,backgroundColor:true,alignment:true,fontWeight:true,fontStyle:true,link:true,linkColor:true,openLinkNewTab:true,imageLeft:true,imageLeftLink:true,imageLeftNewTab:true,imageRight:true,imageRightLink:true,imageRightNewTab:true,formula:true,formulaData:true,html:true,rowSpan:true,columnSpan:true},CFG.cellFeatures||{});
 const keys=()=>{if(header)return [firstKey];const r1=Math.min(selected.r,selected.r2??selected.r),r2=Math.max(selected.r,selected.r2??selected.r),c1=Math.min(selected.c,selected.c2??selected.c),c2=Math.max(selected.c,selected.c2??selected.c);const out=[];for(let r=r1;r<=r2;r++)for(let c=c1;c<=c2;c++)out.push(r+':'+c);return out;};
 const updMany=values=>{const next=Object.assign({},cellMeta);keys().forEach(key=>{next[key]=Object.assign({},next[key]||meta,values);});setCellMeta(next);};
 const upd=(k,v)=>updMany({[k]:v});
 const reset=()=>{const next=Object.assign({},cellMeta);keys().forEach(key=>delete next[key]);setCellMeta(next);};
 const copy=()=>{window.NTC_CELL_PROPERTY_CLIPBOARD=clone(meta);};
 const paste=()=>{if(!window.NTC_CELL_PROPERTY_CLIPBOARD)return;const next=Object.assign({},cellMeta);keys().forEach(key=>next[key]=clone(window.NTC_CELL_PROPERTY_CLIPBOARD));setCellMeta(next);};
 return h('div',{className:'ntc-cell-properties'},
   h('div',{style:{gridColumn:'1/-1',display:'flex',gap:'8px',alignItems:'center'}},h('strong',null,header?__('Header cell properties','native-tables-charts'):__('Selected cell properties','native-tables-charts')),h(Button,{variant:'tertiary',onClick:copy},__('Copy properties','native-tables-charts')),h(Button,{variant:'tertiary',onClick:paste,disabled:!window.NTC_CELL_PROPERTY_CLIPBOARD},__('Paste properties','native-tables-charts')),h(Button,{variant:'tertiary',isDestructive:true,onClick:reset},__('Reset','native-tables-charts'))),
   f.link&&h(TextControl,{label:__('Link URL','native-tables-charts'),value:meta.link||'',onChange:v=>upd('link',v)}),
   f.openLinkNewTab&&h(ToggleControl,{label:__('Open link in new tab','native-tables-charts'),checked:!!meta.openLinkNewTab,onChange:v=>upd('openLinkNewTab',v)}),
   f.linkColor&&h(TextControl,{label:__('Link colour','native-tables-charts'),value:meta.linkColor||'',onChange:v=>upd('linkColor',v),placeholder:'#000000'}),
   f.alignment&&h(SelectControl,{label:__('Alignment','native-tables-charts'),value:meta.alignment||'',options:[{label:__('Default','native-tables-charts'),value:''},{label:__('Left','native-tables-charts'),value:'left'},{label:__('Center','native-tables-charts'),value:'center'},{label:__('Right','native-tables-charts'),value:'right'}],onChange:v=>upd('alignment',v)}),
   f.fontWeight&&h(SelectControl,{label:__('Font weight','native-tables-charts'),value:String(meta.fontWeight||'400'),options:['100','200','300','400','500','600','700','800','900'].map(v=>({label:v,value:v})),onChange:v=>upd('fontWeight',v)}),
   f.fontStyle&&h(SelectControl,{label:__('Font style','native-tables-charts'),value:meta.fontStyle||'normal',options:[{label:__('Normal','native-tables-charts'),value:'normal'},{label:__('Italic','native-tables-charts'),value:'italic'},{label:__('Oblique','native-tables-charts'),value:'oblique'}],onChange:v=>upd('fontStyle',v)}),
   f.textColor&&h(TextControl,{label:__('Text colour','native-tables-charts'),value:meta.textColor||'',onChange:v=>upd('textColor',v),placeholder:'#000000'}),
   f.backgroundColor&&h(TextControl,{label:__('Background colour','native-tables-charts'),value:meta.backgroundColor||'',onChange:v=>upd('backgroundColor',v),placeholder:'#ffffff'}),
   !header&&f.formula&&h(SelectControl,{label:__('Formula','native-tables-charts'),value:meta.formula||'',options:[{label:__('None','native-tables-charts'),value:''},{label:'SUM',value:'sum'},{label:'SUBTRACT',value:'subtract'},{label:'MIN',value:'min'},{label:'MAX',value:'max'},{label:'AVERAGE',value:'average'}],onChange:v=>upd('formula',v)}),
   !header&&f.formulaData&&h(TextControl,{label:__('Formula columns','native-tables-charts'),help:__('Comma-separated 1-based column numbers, e.g. 2,3,4.','native-tables-charts'),value:meta.formulaData||'',onChange:v=>upd('formulaData',v)}),
   f.rowSpan&&h(RangeControl,{label:__('Row span','native-tables-charts'),value:Number(meta.rowspan||1),min:1,max:20,onChange:v=>upd('rowspan',v)}),
   f.columnSpan&&h(RangeControl,{label:__('Column span','native-tables-charts'),value:Number(meta.colspan||1),min:1,max:20,onChange:v=>upd('colspan',v)}),
   f.html&&h(TextareaControl,{label:__('Custom HTML','native-tables-charts'),value:meta.html||'',onChange:v=>upd('html',v),rows:2}),
   f.imageLeft&&h('div',null,h(MediaUploadCheck,null,h(MediaUpload,{allowedTypes:['image'],onSelect:m=>updMany({imageLeft:m.url,imageLeftAlt:m.alt||''}),render:({open})=>h(Button,{variant:'secondary',onClick:open},meta.imageLeft?__('Change left image','native-tables-charts'):__('Add left image','native-tables-charts'))})),meta.imageLeft&&h(Button,{variant:'tertiary',isDestructive:true,onClick:()=>upd('imageLeft','')},__('Remove','native-tables-charts'))),
   f.imageLeft&&h(TextControl,{label:__('Left image alt text','native-tables-charts'),value:meta.imageLeftAlt||'',onChange:v=>upd('imageLeftAlt',v)}),
   f.imageLeftLink&&h(TextControl,{label:__('Left image link','native-tables-charts'),value:meta.imageLeftLink||'',onChange:v=>upd('imageLeftLink',v)}),
   f.imageLeftNewTab&&h(ToggleControl,{label:__('Open left image link in new tab','native-tables-charts'),checked:!!meta.imageLeftOpenLinkNewTab,onChange:v=>upd('imageLeftOpenLinkNewTab',v)}),
   f.imageRight&&h('div',null,h(MediaUploadCheck,null,h(MediaUpload,{allowedTypes:['image'],onSelect:m=>updMany({imageRight:m.url,imageRightAlt:m.alt||''}),render:({open})=>h(Button,{variant:'secondary',onClick:open},meta.imageRight?__('Change right image','native-tables-charts'):__('Add right image','native-tables-charts'))})),meta.imageRight&&h(Button,{variant:'tertiary',isDestructive:true,onClick:()=>upd('imageRight','')},__('Remove','native-tables-charts'))),
   f.imageRight&&h(TextControl,{label:__('Right image alt text','native-tables-charts'),value:meta.imageRightAlt||'',onChange:v=>upd('imageRightAlt',v)}),
   f.imageRightLink&&h(TextControl,{label:__('Right image link','native-tables-charts'),value:meta.imageRightLink||'',onChange:v=>upd('imageRightLink',v)}),
   f.imageRightNewTab&&h(ToggleControl,{label:__('Open right image link in new tab','native-tables-charts'),checked:!!meta.imageRightOpenLinkNewTab,onChange:v=>upd('imageRightOpenLinkNewTab',v)})
 );
}

function SortRules({config,setConfig,columns}){
 const rules=(config.defaultSort||[]).slice(0,5);const set=(i,k,v)=>{const n=rules.slice();while(n.length<=i)n.push({column:0,direction:'asc',type:'auto',dateFormat:'ddmmyyyy'});n[i]=Object.assign({},n[i],{[k]:v});setConfig(Object.assign({},config,{defaultSort:n}));};
 const dateFormats=[{label:__('Day / Month / Year (DD/MM/YYYY)','native-tables-charts'),value:'ddmmyyyy'},{label:__('Year / Month / Day (YYYY/MM/DD)','native-tables-charts'),value:'yyyymmdd'},{label:__('Month / Day / Year (MM/DD/YYYY)','native-tables-charts'),value:'mmddyyyy'}];
 return h('div',null,[0,1,2,3,4].map(i=>{const rule=rules[i]||{};return h('div',{key:i,style:{padding:'8px 0',borderTop:i?'1px solid #eee':'0'}},h(SelectControl,{label:__('Priority ','native-tables-charts')+(i+1),value:String(rule.column??0),options:colOptions(columns),onChange:v=>set(i,'column',Number(v))}),h(SelectControl,{label:__('Direction','native-tables-charts'),value:rule.direction||'asc',options:[{label:__('Ascending','native-tables-charts'),value:'asc'},{label:__('Descending','native-tables-charts'),value:'desc'}],onChange:v=>set(i,'direction',v)}),h(SelectControl,{label:__('Type','native-tables-charts'),value:rule.type||'auto',options:colTypes,onChange:v=>set(i,'type',v)}),(rule.type||'auto')==='short_date'&&h(SelectControl,{label:__('Short date format','native-tables-charts'),value:rule.dateFormat||'ddmmyyyy',options:dateFormats,onChange:v=>set(i,'dateFormat',v)}));}));
}

function AutoRules({config,setConfig,kind}){
 const key=kind==='align'?'autoAlignRules':'autoColorRules';const rules=Array.isArray(config[key])?config[key]:[];
 const save=next=>setConfig(Object.assign({},config,{[key]:next}));
 const update=(i,k,v)=>save(rules.map((r,x)=>x===i?Object.assign({},r,{[k]:v}):r));
 const toText=indexes=>(indexes||[]).map(x=>Number(x)+1).join(', ');
 const fromText=v=>v.split(',').map(x=>parseInt(x.trim(),10)).filter(x=>Number.isFinite(x)&&x>0).map(x=>x-1);
 return h('div',{className:'ntc-rule-editor'},rules.map((r,i)=>h('div',{className:'ntc-rule-row',key:i},
   h(SelectControl,{label:__('Apply to','native-tables-charts'),value:r.type||'row',options:[{label:__('Rows','native-tables-charts'),value:'row'},{label:__('Columns','native-tables-charts'),value:'column'}],onChange:v=>update(i,'type',v)}),
   h(TextControl,{label:r.type==='column'?__('Column numbers','native-tables-charts'):__('Row numbers','native-tables-charts'),help:__('Use 1-based numbers separated by commas.','native-tables-charts'),value:toText(r.indexes),onChange:v=>update(i,'indexes',fromText(v))}),
   kind==='align'?h(SelectControl,{label:__('Alignment','native-tables-charts'),value:r.align||'left',options:[{label:__('Left','native-tables-charts'),value:'left'},{label:__('Center','native-tables-charts'),value:'center'},{label:__('Right','native-tables-charts'),value:'right'},{label:__('Justify','native-tables-charts'),value:'justify'}],onChange:v=>update(i,'align',v)}):h(wp.element.Fragment,null,r.type==='column'&&h(CheckboxControl,{label:__('Heatmap scale (low to high)','native-tables-charts'),checked:!!r.heatmap,help:__('Colour numeric cells by value; background and text colours become the low/high ends.','native-tables-charts'),onChange:v=>update(i,'heatmap',v)}),h(TextControl,{label:__('Background','native-tables-charts'),value:r.background||'',placeholder:'#ffffff',onChange:v=>update(i,'background',v)}),h(TextControl,{label:__('Text colour','native-tables-charts'),value:r.color||'',placeholder:'#111111',onChange:v=>update(i,'color',v)})),
   h(Button,{variant:'tertiary',isDestructive:true,onClick:()=>save(rules.filter((_,x)=>x!==i))},__('Remove rule','native-tables-charts'))
 )),h(Button,{variant:'secondary',onClick:()=>save(rules.concat([kind==='align'?{type:'row',indexes:[],align:'left'}:{type:'row',indexes:[],background:'',color:''}]))},kind==='align'?__('Add alignment rule','native-tables-charts'):__('Add colour rule','native-tables-charts')));
}

function TableInspector({attributes,setAttributes,selected,customPresets}){
 const columns=attributes.columns||[];const raw=attributes.config||{};const presetValue=raw.preset||'editorial';let presetSettings={};
 if(String(presetValue).startsWith('custom:')){const p=(customPresets||[]).find(x=>String(x.id)===String(presetValue).split(':')[1]);presetSettings=(p&&p.settings)||{};}else presetSettings=(CFG.tablePresets||{})[presetValue]||{};
 const config=Object.assign({},CFG.tableDefaults||{},presetSettings,raw);const setConfig=n=>setAttributes({config:n});const cidx=selected?selected.c:0;const col=columns[cidx]||{};
 const updateCol=(k,v)=>{const next=columns.map((c,i)=>i===cidx?Object.assign({},c,{[k]:v}):c);setAttributes({columns:next});};
 const applyPreset=v=>{if(v.startsWith('custom:')){const p=(customPresets||[]).find(x=>String(x.id)===v.split(':')[1]);if(p)setConfig(Object.assign({},config,p.settings,{preset:v}));}else setConfig(Object.assign({},config,(CFG.tablePresets||{})[v]||{},{preset:v}));};
 const hideToggle=(key,v)=>{let a=(config[key]||[]).map(Number).filter(x=>x!==cidx);if(v)a.push(cidx);setConfig(Object.assign({},config,{[key]:a}));};
 const align=(config.columnAlign||{})[cidx]||'';
 return h(InspectorControls,null,
  h(PanelBody,{title:__('Table Style & Layout','native-tables-charts'),initialOpen:true},
    h(PresetBrowser,{type:'table',presets:CFG.tablePresets||{},customPresets,value:config.preset||'editorial',onApply:applyPreset,label:__('Browse table styles','native-tables-charts')}),
    h(SelectControl,{label:__('Table layout','native-tables-charts'),value:config.tableLayout||'auto',options:[{label:__('Automatic','native-tables-charts'),value:'auto'},{label:__('Fixed columns','native-tables-charts'),value:'fixed'}],onChange:v=>setConfig(Object.assign({},config,{tableLayout:v}))}),
    h(TextControl,{label:__('Table width','native-tables-charts'),value:String(config.width||'100%'),onChange:v=>setConfig(Object.assign({},config,{width:v}))}),
    h(TextControl,{label:__('Minimum width','native-tables-charts'),value:String(config.minWidth||'0'),onChange:v=>setConfig(Object.assign({},config,{minWidth:v}))}),
    h(RangeControl,{label:__('Maximum scroll-container height','native-tables-charts'),help:__('0 means automatic height.','native-tables-charts'),value:Number(config.maxHeight||0),min:0,max:1600,step:20,onChange:v=>setConfig(Object.assign({},config,{maxHeight:v}))}),
    h(RangeControl,{label:__('Maximum scroll-container width','native-tables-charts'),help:__('0 means use the available width.','native-tables-charts'),value:Number(config.containerWidth||0),min:0,max:2000,step:20,onChange:v=>setConfig(Object.assign({},config,{containerWidth:v}))}),
    h(ToggleControl,{label:__('Show header row','native-tables-charts'),checked:config.showHeader!==false,onChange:v=>setConfig(Object.assign({},config,{showHeader:v}))}),
    h(ToggleControl,{label:__('Sticky header','native-tables-charts'),checked:!!config.stickyHeader,onChange:v=>setConfig(Object.assign({},config,{stickyHeader:v}))}),
    h(ToggleControl,{label:__('Show caption','native-tables-charts'),checked:!!config.showCaption,onChange:v=>setConfig(Object.assign({},config,{showCaption:v}))}),
    config.showCaption&&h(TextControl,{label:__('Caption','native-tables-charts'),value:config.caption||'',onChange:v=>setConfig(Object.assign({},config,{caption:v}))}),
    config.showCaption&&h(SelectControl,{label:__('Caption position','native-tables-charts'),value:config.captionSide||'top',options:[{label:__('Top','native-tables-charts'),value:'top'},{label:__('Bottom','native-tables-charts'),value:'bottom'}],onChange:v=>setConfig(Object.assign({},config,{captionSide:v}))}),
    config.showCaption&&h(SelectControl,{label:__('Caption alignment','native-tables-charts'),value:config.captionTextAlign||'left',options:[{label:__('Left','native-tables-charts'),value:'left'},{label:__('Center','native-tables-charts'),value:'center'},{label:__('Right','native-tables-charts'),value:'right'},{label:__('Justify','native-tables-charts'),value:'justify'}],onChange:v=>setConfig(Object.assign({},config,{captionTextAlign:v}))})
  ),
  h(PanelBody,{title:__('Responsive','native-tables-charts'),initialOpen:false},
    h(SelectControl,{label:__('Mobile behaviour','native-tables-charts'),value:config.responsiveMode||'scroll',options:[{label:__('Horizontal scroll','native-tables-charts'),value:'scroll'},{label:__('Stack rows as cards','native-tables-charts'),value:'stack'},{label:__('Hide selected columns','native-tables-charts'),value:'hide'}],onChange:v=>setConfig(Object.assign({},config,{responsiveMode:v}))}),
    h(RangeControl,{label:__('Phone breakpoint','native-tables-charts'),value:Number(config.phoneBreakpoint||540),min:320,max:900,onChange:v=>setConfig(Object.assign({},config,{phoneBreakpoint:v}))}),
    h(RangeControl,{label:__('Tablet breakpoint','native-tables-charts'),value:Number(config.tabletBreakpoint||900),min:600,max:1400,onChange:v=>setConfig(Object.assign({},config,{tabletBreakpoint:v}))}),
    h(ToggleControl,{label:__('Hide images on phone','native-tables-charts'),checked:!!config.phoneHideImages,onChange:v=>setConfig(Object.assign({},config,{phoneHideImages:v}))}),
    h(ToggleControl,{label:__('Hide images on tablet','native-tables-charts'),checked:!!config.tabletHideImages,onChange:v=>setConfig(Object.assign({},config,{tabletHideImages:v}))}),
    h(RangeControl,{label:__('Phone header font size','native-tables-charts'),value:Number(config.phoneHeaderFontSize||12),min:8,max:24,onChange:v=>setConfig(Object.assign({},config,{phoneHeaderFontSize:v}))}),
    h(RangeControl,{label:__('Phone body font size','native-tables-charts'),value:Number(config.phoneBodyFontSize||12),min:8,max:24,onChange:v=>setConfig(Object.assign({},config,{phoneBodyFontSize:v}))}),
    h(RangeControl,{label:__('Phone caption font size','native-tables-charts'),value:Number(config.phoneCaptionFontSize||12),min:8,max:24,onChange:v=>setConfig(Object.assign({},config,{phoneCaptionFontSize:v}))}),
    h(RangeControl,{label:__('Tablet header font size','native-tables-charts'),value:Number(config.tabletHeaderFontSize||13),min:8,max:26,onChange:v=>setConfig(Object.assign({},config,{tabletHeaderFontSize:v}))}),
    h(RangeControl,{label:__('Tablet body font size','native-tables-charts'),value:Number(config.tabletBodyFontSize||13),min:8,max:26,onChange:v=>setConfig(Object.assign({},config,{tabletBodyFontSize:v}))}),
    h(RangeControl,{label:__('Tablet caption font size','native-tables-charts'),value:Number(config.tabletCaptionFontSize||13),min:8,max:26,onChange:v=>setConfig(Object.assign({},config,{tabletCaptionFontSize:v}))})
  ),
  h(PanelBody,{title:__('Sorting & Ranking','native-tables-charts'),initialOpen:false},
    h(ToggleControl,{label:__('Enable sorting','native-tables-charts'),help:__('Applies up to five default sort priorities when the table is rendered.','native-tables-charts'),checked:!!config.enableSorting,onChange:v=>setConfig(Object.assign({},config,{enableSorting:v}))}),
    config.enableSorting&&h(ToggleControl,{label:__('Allow visitors to re-sort columns','native-tables-charts'),checked:config.enableManualSorting!==false,onChange:v=>setConfig(Object.assign({},config,{enableManualSorting:v}))}),
    h(SelectControl,{label:__('Number format for currency sorting','native-tables-charts'),value:config.numberFormat||'us',options:[{label:__('Point decimal (US)','native-tables-charts'),value:'us'},{label:__('Comma decimal (EU)','native-tables-charts'),value:'eu'}],onChange:v=>setConfig(Object.assign({},config,{numberFormat:v}))}),
    h(ToggleControl,{label:__('Show position/ranking column','native-tables-charts'),checked:!!config.showPosition,onChange:v=>setConfig(Object.assign({},config,{showPosition:v}))}),
    config.showPosition&&h(TextControl,{label:__('Position label','native-tables-charts'),value:config.positionLabel||'#',onChange:v=>setConfig(Object.assign({},config,{positionLabel:v}))}),
    config.showPosition&&h(SelectControl,{label:__('Position side','native-tables-charts'),value:config.positionSide||'left',options:[{label:__('Left','native-tables-charts'),value:'left'},{label:__('Right','native-tables-charts'),value:'right'}],onChange:v=>setConfig(Object.assign({},config,{positionSide:v}))}),
    config.enableSorting&&h(SortRules,{config,setConfig,columns})
  ),
  h(PanelBody,{title:__('Selected Column','native-tables-charts'),initialOpen:false},selected?h('div',null,
    h(TextControl,{label:__('Column label','native-tables-charts'),value:col.label||'',onChange:v=>updateCol('label',v)}),
    h(SelectControl,{label:__('Data type','native-tables-charts'),value:col.type||'auto',options:colTypes,onChange:v=>updateCol('type',v)}),
    h(TextControl,{label:__('Unit','native-tables-charts'),value:col.unit||'',onChange:v=>updateCol('unit',v)}),
    h(TextControl,{label:__('Width (px or CSS length)','native-tables-charts'),value:String((config.columnWidths||{})[cidx]||''),onChange:v=>setConfig(Object.assign({},config,{columnWidths:Object.assign({},config.columnWidths||{},{[cidx]:v})}))}),
    h(SelectControl,{label:__('Column alignment','native-tables-charts'),value:align,options:[{label:__('Default','native-tables-charts'),value:''},{label:__('Left','native-tables-charts'),value:'left'},{label:__('Center','native-tables-charts'),value:'center'},{label:__('Right','native-tables-charts'),value:'right'},{label:__('Justify','native-tables-charts'),value:'justify'}],onChange:v=>setConfig(Object.assign({},config,{columnAlign:Object.assign({},config.columnAlign||{},{[cidx]:v})}))}),
    h(ToggleControl,{label:__('Hide this column on phone','native-tables-charts'),checked:(config.hidePhone||[]).map(Number).includes(cidx),onChange:v=>hideToggle('hidePhone',v)}),
    h(ToggleControl,{label:__('Hide this column on tablet','native-tables-charts'),checked:(config.hideTablet||[]).map(Number).includes(cidx),onChange:v=>hideToggle('hideTablet',v)})
  ):h('p',{className:'ntc-inspector-note'},__('Select a cell to edit its column settings.','native-tables-charts'))),
  h(PanelBody,{title:__('Automatic Formatting','native-tables-charts'),initialOpen:false},
    h('h4',null,__('Colour rules','native-tables-charts')),h(AutoRules,{config,setConfig,kind:'color'}),
    h('h4',null,__('Alignment rules','native-tables-charts')),h(AutoRules,{config,setConfig,kind:'align'})
  ),
  h(PanelBody,{title:__('Frontend Controls','native-tables-charts'),initialOpen:false},
    h(ToggleControl,{label:__('Show table search','native-tables-charts'),checked:!!config.enableSearch,onChange:v=>setConfig(Object.assign({},config,{enableSearch:v}))}),
    h(ToggleControl,{label:__('Paginate table rows','native-tables-charts'),checked:!!config.enablePagination,onChange:v=>setConfig(Object.assign({},config,{enablePagination:v}))}),
    config.enablePagination&&h(RangeControl,{label:__('Rows per page','native-tables-charts'),value:Number(config.rowsPerPage||10),min:1,max:100,onChange:v=>setConfig(Object.assign({},config,{rowsPerPage:v}))}),
    h(ToggleControl,{label:__('CSV download button','native-tables-charts'),checked:!!config.enableExport,onChange:v=>setConfig(Object.assign({},config,{enableExport:v}))})
  ),
  h(PanelBody,{title:__('Colours, Typography & Spacing','native-tables-charts'),initialOpen:false},
    h(TextControl,{label:__('Header background','native-tables-charts'),value:config.headerBackground||'',onChange:v=>setConfig(Object.assign({},config,{headerBackground:v}))}),
    h(TextControl,{label:__('Header text','native-tables-charts'),value:config.headerColor||'',onChange:v=>setConfig(Object.assign({},config,{headerColor:v}))}),
    h(TextControl,{label:__('Header link','native-tables-charts'),value:config.headerLinkColor||'',onChange:v=>setConfig(Object.assign({},config,{headerLinkColor:v}))}),
    h(TextControl,{label:__('Header border','native-tables-charts'),value:config.headerBorderColor||'',onChange:v=>setConfig(Object.assign({},config,{headerBorderColor:v}))}),
    h(TextControl,{label:__('Header font family','native-tables-charts'),help:__('Use inherit to follow the theme, or enter a CSS font-family stack.','native-tables-charts'),value:config.headerFontFamily||'inherit',onChange:v=>setConfig(Object.assign({},config,{headerFontFamily:v}))}),
    h(SelectControl,{label:__('Header font weight','native-tables-charts'),value:String(config.headerFontWeight||'700'),options:['100','200','300','400','500','600','700','800','900'].map(v=>({label:v,value:v})),onChange:v=>setConfig(Object.assign({},config,{headerFontWeight:v}))}),
    h(SelectControl,{label:__('Header font style','native-tables-charts'),value:config.headerFontStyle||'normal',options:[{label:__('Normal','native-tables-charts'),value:'normal'},{label:__('Italic','native-tables-charts'),value:'italic'},{label:__('Oblique','native-tables-charts'),value:'oblique'}],onChange:v=>setConfig(Object.assign({},config,{headerFontStyle:v}))}),
    h(SelectControl,{label:__('Position header alignment','native-tables-charts'),value:config.headerPositionAlign||'center',options:[{label:__('Left','native-tables-charts'),value:'left'},{label:__('Center','native-tables-charts'),value:'center'},{label:__('Right','native-tables-charts'),value:'right'}],onChange:v=>setConfig(Object.assign({},config,{headerPositionAlign:v}))}),
    h(TextControl,{label:__('Odd row background','native-tables-charts'),value:config.oddBackground||'',onChange:v=>setConfig(Object.assign({},config,{oddBackground:v}))}),
    h(TextControl,{label:__('Odd row text','native-tables-charts'),value:config.oddColor||'',onChange:v=>setConfig(Object.assign({},config,{oddColor:v}))}),
    h(TextControl,{label:__('Odd row link','native-tables-charts'),value:config.oddLinkColor||'',onChange:v=>setConfig(Object.assign({},config,{oddLinkColor:v}))}),
    h(TextControl,{label:__('Even row background','native-tables-charts'),value:config.evenBackground||'',onChange:v=>setConfig(Object.assign({},config,{evenBackground:v}))}),
    h(TextControl,{label:__('Even row text','native-tables-charts'),value:config.evenColor||'',onChange:v=>setConfig(Object.assign({},config,{evenColor:v}))}),
    h(TextControl,{label:__('Even row link','native-tables-charts'),value:config.evenLinkColor||'',onChange:v=>setConfig(Object.assign({},config,{evenLinkColor:v}))}),
    h(TextControl,{label:__('Body text fallback','native-tables-charts'),value:config.bodyColor||'',onChange:v=>setConfig(Object.assign({},config,{bodyColor:v}))}),
    h(TextControl,{label:__('Body link fallback','native-tables-charts'),value:config.linkColor||'',onChange:v=>setConfig(Object.assign({},config,{linkColor:v}))}),
    h(TextControl,{label:__('Body font family','native-tables-charts'),help:__('Use inherit to follow the theme, or enter a CSS font-family stack.','native-tables-charts'),value:config.bodyFontFamily||'inherit',onChange:v=>setConfig(Object.assign({},config,{bodyFontFamily:v}))}),
    h(SelectControl,{label:__('Body font weight','native-tables-charts'),value:String(config.bodyFontWeight||'400'),options:['100','200','300','400','500','600','700','800','900'].map(v=>({label:v,value:v})),onChange:v=>setConfig(Object.assign({},config,{bodyFontWeight:v}))}),
    h(SelectControl,{label:__('Body font style','native-tables-charts'),value:config.bodyFontStyle||'normal',options:[{label:__('Normal','native-tables-charts'),value:'normal'},{label:__('Italic','native-tables-charts'),value:'italic'},{label:__('Oblique','native-tables-charts'),value:'oblique'}],onChange:v=>setConfig(Object.assign({},config,{bodyFontStyle:v}))}),
    h(TextControl,{label:__('Caption colour','native-tables-charts'),value:config.captionColor||'',onChange:v=>setConfig(Object.assign({},config,{captionColor:v}))}),
    h(TextControl,{label:__('Caption font family','native-tables-charts'),help:__('Use inherit to follow the theme, or enter a CSS font-family stack.','native-tables-charts'),value:config.captionFontFamily||'inherit',onChange:v=>setConfig(Object.assign({},config,{captionFontFamily:v}))}),
    h(SelectControl,{label:__('Caption font weight','native-tables-charts'),value:String(config.captionFontWeight||'400'),options:['100','200','300','400','500','600','700','800','900'].map(v=>({label:v,value:v})),onChange:v=>setConfig(Object.assign({},config,{captionFontWeight:v}))}),
    h(SelectControl,{label:__('Caption font style','native-tables-charts'),value:config.captionFontStyle||'normal',options:[{label:__('Normal','native-tables-charts'),value:'normal'},{label:__('Italic','native-tables-charts'),value:'italic'},{label:__('Oblique','native-tables-charts'),value:'oblique'}],onChange:v=>setConfig(Object.assign({},config,{captionFontStyle:v}))}),
    h(TextControl,{label:__('Border colour','native-tables-charts'),value:config.borderColor||'',onChange:v=>setConfig(Object.assign({},config,{borderColor:v}))}),
    h(TextControl,{label:__('Highlight/accent','native-tables-charts'),value:config.accentColor||'',onChange:v=>setConfig(Object.assign({},config,{accentColor:v}))}),
    h(TextControl,{label:__('Cell padding','native-tables-charts'),value:config.cellPadding||'10px 12px',onChange:v=>setConfig(Object.assign({},config,{cellPadding:v}))}),
    h(RangeControl,{label:__('Header font size','native-tables-charts'),value:Number(config.headerFontSize||13),min:9,max:30,onChange:v=>setConfig(Object.assign({},config,{headerFontSize:v}))}),
    h(RangeControl,{label:__('Body font size','native-tables-charts'),value:Number(config.fontSize||14),min:9,max:30,onChange:v=>setConfig(Object.assign({},config,{fontSize:v}))}),
    h(RangeControl,{label:__('Caption font size','native-tables-charts'),value:Number(config.captionFontSize||13),min:9,max:30,onChange:v=>setConfig(Object.assign({},config,{captionFontSize:v}))}),
    h(RangeControl,{label:__('Border width','native-tables-charts'),value:Number(config.borderWidth||1),min:0,max:8,onChange:v=>setConfig(Object.assign({},config,{borderWidth:v}))}),
    h(RangeControl,{label:__('Border radius','native-tables-charts'),value:Number(config.borderRadius||0),min:0,max:30,onChange:v=>setConfig(Object.assign({},config,{borderRadius:v}))}),
    h(RangeControl,{label:__('Top margin','native-tables-charts'),value:Number(config.marginTop||0),min:0,max:100,onChange:v=>setConfig(Object.assign({},config,{marginTop:v}))}),
    h(RangeControl,{label:__('Bottom margin','native-tables-charts'),value:Number(config.marginBottom||0),min:0,max:100,onChange:v=>setConfig(Object.assign({},config,{marginBottom:v}))})
  ),
  h(PanelBody,{title:__('Advanced','native-tables-charts'),initialOpen:false},
    h(ToggleControl,{label:__('Enable per-cell properties','native-tables-charts'),checked:config.enableCellProperties!==false,onChange:v=>setConfig(Object.assign({},config,{enableCellProperties:v}))}),
    h(RangeControl,{label:__('Average formula decimals','native-tables-charts'),value:Number(config.averageDecimals||2),min:0,max:8,onChange:v=>setConfig(Object.assign({},config,{averageDecimals:v}))}),
    h(SelectControl,{label:__('Average formula rounding','native-tables-charts'),value:config.averageRound||'half_up',options:[{label:'PHP_ROUND_HALF_UP',value:'half_up'},{label:'PHP_ROUND_HALF_DOWN',value:'half_down'},{label:'PHP_ROUND_HALF_EVEN',value:'half_even'},{label:'PHP_ROUND_HALF_ODD',value:'half_odd'}],onChange:v=>setConfig(Object.assign({},config,{averageRound:v}))}),
    h(TextControl,{label:__('Custom class','native-tables-charts'),value:config.customClass||'',onChange:v=>setConfig(Object.assign({},config,{customClass:v}))}),
    h(ToggleControl,{label:__('Schema.org metadata','native-tables-charts'),checked:!!config.enableSchema,onChange:v=>setConfig(Object.assign({},config,{enableSchema:v}))}),
    h(ToggleControl,{label:__('Show last-updated date','native-tables-charts'),checked:!!config.showUpdatedDate,onChange:v=>setConfig(Object.assign({},config,{showUpdatedDate:v}))})
  )
 );
}

function ChartInspector({attributes,setAttributes,customPresets,onEditData,onFocusData}){
 const columns=attributes.columns||[];const rawConfig=attributes.config||{};const presets=CFG.chartPresets||{};const presetValue=rawConfig.preset||'benchmark-dark';let presetSettings={};
 if(String(presetValue).startsWith('custom:')){const p=(customPresets||[]).find(x=>String(x.id)===String(presetValue).split(':')[1]);presetSettings=(p&&p.settings)||{};}else presetSettings=presets[presetValue]||{};
 const baseConfig=Object.assign({},CFG.chartDefaults||{},presetSettings,rawConfig);const typographySettings=baseConfig.typographyPreset!=='custom'?((CFG.chartTypographyPresets||{})[baseConfig.typographyPreset]||{}):{};const densitySettings=(baseConfig.density&&baseConfig.density!=='auto'&&baseConfig.density!=='custom')?((CFG.chartDensityPresets||{})[baseConfig.density]||{}):{};const config=Object.assign({},baseConfig,typographySettings,densitySettings,rawConfig);const setConfig=n=>setAttributes({config:n});
 const applyPreset=v=>{if(v.startsWith('custom:')){const p=(customPresets||[]).find(x=>String(x.id)===v.split(':')[1]);if(p)setConfig(Object.assign({},rawConfig,p.settings||{},{preset:v}));}else setConfig(Object.assign({},rawConfig,presets[v]||{},{preset:v}));};
 const patch=next=>setAttributes({config:Object.assign({},rawConfig,next)});
 const values=(config.valueColumns||[]).map(Number);
 const labelColumn=Number(config.labelColumn||0);
 const rowLabels=(attributes.rows||[]).map((r,i)=>({label:String(r[labelColumn]??'')||__('Row','native-tables-charts')+' '+(i+1),value:String(r[labelColumn]??'')})).filter(x=>x.value!=='');
 const focusValues=(config.highlightValues||[]).map(String);
 const focusValue=focusValues[0]||'';
 const setPrimaryFocus=v=>patch({highlightValues:v?[v]:[]});
 const dataMode=config.accessibleDataMode||'screenreader';
 return h(InspectorControls,null,
  h(PanelBody,{title:__('Chart','native-tables-charts'),initialOpen:true},
    h(SelectControl,{label:__('Chart layout','native-tables-charts'),value:config.chartType||'horizontal-bar',options:chartTypes,onChange:v=>patch({chartType:v})}),
    h(PresetBrowser,{type:'chart',presets,customPresets,value:config.preset||'benchmark-dark',onApply:applyPreset,label:__('Browse chart themes','native-tables-charts')}),
    h(TextControl,{label:__('Title','native-tables-charts'),value:config.title||'',onChange:v=>patch({title:v})}),
    h(TextControl,{label:__('Subtitle','native-tables-charts'),value:config.subtitle||'',onChange:v=>patch({subtitle:v})}),
    h(SelectControl,{label:__('Performance direction','native-tables-charts'),value:config.direction||'higher',options:[{label:__('Higher is better','native-tables-charts'),value:'higher'},{label:__('Lower is better','native-tables-charts'),value:'lower'},{label:__('Neutral','native-tables-charts'),value:'neutral'}],onChange:v=>patch({direction:v,sortDirection:v==='lower'?'asc':config.sortDirection})})
  ),
  h(PanelBody,{title:__('Data','native-tables-charts'),initialOpen:true},
    h('p',{className:'ntc-inspector-note'},(attributes.rows||[]).length+' '+(((attributes.rows||[]).length===1)?__('row','native-tables-charts'):__('rows','native-tables-charts'))+' • '+Math.max(1,values.length)+' '+(Math.max(1,values.length)===1?__('metric','native-tables-charts'):__('metrics','native-tables-charts'))),
    h('div',{className:'ntc-inline-actions'},onEditData&&h(Button,{variant:'secondary',icon:'editor-table',onClick:onEditData},__('Edit data','native-tables-charts')),onFocusData&&h(Button,{variant:'tertiary',icon:'fullscreen-alt',onClick:onFocusData},__('Focus mode','native-tables-charts')))
  ),
  h(PanelBody,{title:__('Chart Focus','native-tables-charts'),initialOpen:true},
    h(SelectControl,{label:__('Focused row','native-tables-charts'),value:focusValue,options:[{label:__('No focused row','native-tables-charts'),value:''}].concat(rowLabels),onChange:setPrimaryFocus,help:__('Choose the product or row this chart is about. It will use the chart highlight colour.','native-tables-charts')}),
    focusValue&&h(Button,{variant:'tertiary',isDestructive:true,onClick:()=>setPrimaryFocus('')},__('Clear focus','native-tables-charts')),
    h(ToggleControl,{label:__('Allow multiple focused rows','native-tables-charts'),checked:config.allowMultipleHighlights===true,onChange:v=>patch({allowMultipleHighlights:v,highlightValues:v?focusValues:(focusValue?[focusValue]:[])})}),
    config.allowMultipleHighlights===true&&h('p',{className:'ntc-inspector-note'},__('In Data mode, use the Focus column to add or remove rows from the focus set.','native-tables-charts'))
  ),
  h(PanelBody,{title:__('Typography & Density','native-tables-charts'),initialOpen:true},
    h(ChartTypographyControls,{config,onPatch:patch}),
    h(ChartDensityControls,{config,onPatch:patch,rowCount:(attributes.rows||[]).length})
  ),
  h(PanelBody,{title:__('Data Mapping','native-tables-charts'),initialOpen:false},
    h(SelectControl,{label:__('Label column','native-tables-charts'),value:String(config.labelColumn||0),options:colOptions(columns),onChange:v=>patch({labelColumn:Number(v)})}),
    h('p',null,__('Value columns','native-tables-charts')),
    columns.map((col,i)=>i===Number(config.labelColumn)?null:h(CheckboxControl,{key:i,label:col.label||('Column '+(i+1)),checked:values.includes(i),onChange:checked=>{let next=values.filter(x=>x!==i);if(checked)next.push(i);patch({valueColumns:next.length?next:[i],sortColumn:next.length?next[0]:i});}})),
    h(SelectControl,{label:__('Sort by','native-tables-charts'),value:String(config.sortColumn??(values[0]||1)),options:colOptions(columns),onChange:v=>patch({sortColumn:Number(v)})}),
    h(SelectControl,{label:__('Sort direction','native-tables-charts'),value:config.sortDirection||'desc',options:[{label:__('Highest first','native-tables-charts'),value:'desc'},{label:__('Lowest first','native-tables-charts'),value:'asc'},{label:__('Keep data order','native-tables-charts'),value:'none'}],onChange:v=>patch({sortDirection:v})})
  ),
  h(PanelBody,{title:__('Labels & Footer','native-tables-charts'),initialOpen:false},
    h(TextControl,{label:__('Direction label override','native-tables-charts'),value:config.directionLabel||'',onChange:v=>patch({directionLabel:v})}),
    h(TextControl,{label:__('Legend label','native-tables-charts'),value:config.legendLabel||'',onChange:v=>patch({legendLabel:v})}),
    h(TextControl,{label:__('Axis label','native-tables-charts'),value:config.axisLabel||'',onChange:v=>patch({axisLabel:v})}),
    h(TextControl,{label:__('Sort annotation','native-tables-charts'),value:config.sortLabel||'',placeholder:__('Sorted by score','native-tables-charts'),onChange:v=>patch({sortLabel:v})}),
    h(TextControl,{label:__('Default unit','native-tables-charts'),value:config.unit||'',onChange:v=>patch({unit:v})}),
    h(SelectControl,{label:__('Decimals','native-tables-charts'),value:String(config.decimals??'auto'),options:[{label:'Auto',value:'auto'},{label:'0',value:'0'},{label:'1',value:'1'},{label:'2',value:'2'},{label:'3',value:'3'}],onChange:v=>patch({decimals:v})}),
    h(TextareaControl,{label:__('System/configuration footer','native-tables-charts'),value:config.footer||'',onChange:v=>patch({footer:v})}),
    h(TextareaControl,{label:__('Secondary footer','native-tables-charts'),value:config.secondaryFooter||'',onChange:v=>patch({secondaryFooter:v})}),
    h(TextControl,{label:__('Source','native-tables-charts'),value:config.source||'',onChange:v=>patch({source:v})})
  ),
  h(PanelBody,{title:__('Accessible Data','native-tables-charts'),initialOpen:false},
    h(SelectControl,{label:__('Chart data on the website','native-tables-charts'),value:dataMode,options:[{label:__('Screen readers only (recommended)','native-tables-charts'),value:'screenreader'},{label:__('Collapsible “View chart data”','native-tables-charts'),value:'collapsible'},{label:__('Always visible table','native-tables-charts'),value:'visible'},{label:__('Disabled','native-tables-charts'),value:'disabled'}],onChange:v=>patch({accessibleDataMode:v}),help:__('Screen readers only keeps the exact data available to assistive technology without adding a visible control below the chart.','native-tables-charts')}),
    dataMode==='disabled'&&h(Notice,{status:'warning',isDismissible:false},__('Disabling the alternate data table reduces accessibility. Use this only when the surrounding article provides the same information in another accessible form.','native-tables-charts'))
  ),
  h(PanelBody,{title:__('Responsive & Appearance','native-tables-charts'),initialOpen:false},
    h(SelectControl,{label:__('Aspect ratio','native-tables-charts'),value:config.aspectRatio||'auto',options:[{label:__('Automatic height','native-tables-charts'),value:'auto'},{label:'16:9',value:'16-9'},{label:'4:3',value:'4-3'},{label:__('Square','native-tables-charts'),value:'1-1'}],onChange:v=>patch({aspectRatio:v})}),
    h(RangeControl,{label:__('Mobile panel breakpoint','native-tables-charts'),value:Number(config.mobileBreakpoint||620),min:360,max:1000,onChange:v=>patch({mobileBreakpoint:v})}),
    h(ToggleControl,{label:__('Show numeric axis','native-tables-charts'),checked:config.showAxis!==false,onChange:v=>patch({showAxis:v})}),
    h(ToggleControl,{label:__('Show grid lines','native-tables-charts'),checked:config.showGrid!==false,onChange:v=>patch({showGrid:v})}),
    h(ToggleControl,{label:__('Show exact values','native-tables-charts'),checked:config.showValues!==false,onChange:v=>patch({showValues:v})}),
    h(CompactColorControl,{label:__('Background','native-tables-charts'),value:config.background||'',placeholder:'#09111b',onChange:v=>patch({background:v})}),
    h(CompactColorControl,{label:__('Primary series','native-tables-charts'),value:config.primaryColor||'',placeholder:'#624b8e',onChange:v=>patch({primaryColor:v})}),
    h(CompactColorControl,{label:__('Secondary series','native-tables-charts'),value:config.secondaryColor||'',placeholder:'#8b73b8',onChange:v=>patch({secondaryColor:v})}),
    h(CompactColorControl,{label:__('Highlight','native-tables-charts'),value:config.highlightColor||'',placeholder:'#9e2f5f',onChange:v=>patch({highlightColor:v})}),
    h(CompactColorControl,{label:__('Text colour','native-tables-charts'),value:config.textColor||'',placeholder:'#f5f7fa',onChange:v=>patch({textColor:v})}),
    h(CompactColorControl,{label:__('Muted text','native-tables-charts'),value:config.mutedColor||'',placeholder:'#9ba8b8',onChange:v=>patch({mutedColor:v})}),
    h(CompactColorControl,{label:__('Grid colour','native-tables-charts'),value:config.gridColor||'',placeholder:'#263445',onChange:v=>patch({gridColor:v})}),
    h(SelectControl,{label:__('Dark mode','native-tables-charts'),value:config.themeMode||'fixed',options:[{label:__('Fixed theme','native-tables-charts'),value:'fixed'},{label:__('Auto (follows system)','native-tables-charts'),value:'auto'}],onChange:v=>patch({themeMode:v})}),
    config.themeMode==='auto'&&h(wp.element.Fragment,null,
      h(CompactColorControl,{label:__('Dark background','native-tables-charts'),value:config.darkBackground||'',placeholder:'#0f131a',onChange:v=>patch({darkBackground:v})}),
      h(CompactColorControl,{label:__('Dark text','native-tables-charts'),value:config.darkTextColor||'',placeholder:'#e6e9ee',onChange:v=>patch({darkTextColor:v})}),
      h(CompactColorControl,{label:__('Dark muted text','native-tables-charts'),value:config.darkMutedColor||'',placeholder:'#9aa5b1',onChange:v=>patch({darkMutedColor:v})}),
      h(CompactColorControl,{label:__('Dark grid','native-tables-charts'),value:config.darkGridColor||'',placeholder:'#2a3442',onChange:v=>patch({darkGridColor:v})})
    ),
    h(ToggleControl,{label:__('Download buttons','native-tables-charts'),checked:!!config.enableExport,onChange:v=>patch({enableExport:v})}),
    h(ToggleControl,{label:__('Schema.org metadata','native-tables-charts'),checked:!!config.enableSchema,onChange:v=>patch({enableSchema:v})}),
    h(ToggleControl,{label:__('Show last-updated date','native-tables-charts'),checked:!!config.showUpdatedDate,onChange:v=>patch({showUpdatedDate:v})})
  )
 );
}

function DataEditor({attributes,setAttributes,type}){
 const columns=attributes.columns||defaultColumns();const rows=attributes.rows||defaultRows();
 const widthMode=attributes.widthMode||(attributes.align==='wide'?'wide':attributes.align==='full'?'full':'content');
 const widthClass='ntc-width-'+widthMode+(widthMode==='wide'?' alignwide':widthMode==='full'?' alignfull':'');
 const blockProps=useBlockProps({className:'ntc-block-editor-root '+widthClass});
 const setWidthMode=v=>setAttributes({widthMode:v,align:''});
 const [selected,setSelected]=useState({r:0,c:0});const [importOpen,setImportOpen]=useState(false);const [exportOpen,setExportOpen]=useState(false);const [pickerOpen,setPickerOpen]=useState(false);const [loading,setLoading]=useState(false);const [status,setStatus]=useState('');const [customPresets,setCustomPresets]=useState([]);const [previewMode,setPreviewMode]=useState('desktop');const [editorMode,setEditorMode]=useState(type==='chart'?'preview':'data');const [focusOpen,setFocusOpen]=useState(false);const [styleOpen,setStyleOpen]=useState(false);
 const dirtyRows=useRef(new Set());const saveTimer=useRef(null);const viewTimer=useRef(null);const latestRows=useRef(rows);const needsReplace=useRef(false);const loadingRemote=useRef(false);
 const chartConfig=type==='chart'?Object.assign({},CFG.chartDefaults||{},attributes.config||{}):{};
 const focusValues=type==='chart'?(chartConfig.highlightValues||[]).map(String):[];
 const labelColumn=type==='chart'?Number(chartConfig.labelColumn||0):0;
 const allowMultipleFocus=type==='chart'&&chartConfig.allowMultipleHighlights===true;
 const toggleRowFocus=ri=>{if(type!=='chart')return;const label=String((rows[ri]||[])[labelColumn]??'').trim();if(!label){setStatus(__('Add a label before setting chart focus.','native-tables-charts'));return;}let next;if(focusValues.includes(label)){next=focusValues.filter(v=>v!==label);}else{next=allowMultipleFocus?focusValues.concat([label]):[label];}setAttributes({config:Object.assign({},attributes.config||{},{highlightValues:next})});setStatus(next.includes(label)?__('Chart focus updated','native-tables-charts'):__('Chart focus cleared','native-tables-charts'));};
 const updateFocusLabel=(oldLabel,newLabel)=>{if(type!=='chart'||!focusValues.includes(String(oldLabel)))return;const clean=String(newLabel||'').trim();const next=focusValues.map(v=>v===String(oldLabel)?clean:v).filter(Boolean);setAttributes({config:Object.assign({},attributes.config||{},{highlightValues:Array.from(new Set(next))})});};
 useEffect(()=>{latestRows.current=rows;},[rows]);
 useEffect(()=>{apiFetch({path:'/ntc/v1/presets'}).then(r=>setCustomPresets(r.custom||[])).catch(()=>{});},[]);
 useEffect(()=>{
   if(!attributes.datasetId||attributes.mode==='inline')return;let cancelled=false;
   setLoading(true);loadingRemote.current=true;
   (async()=>{
     try{
       const datasetPromise=apiFetch({path:'/ntc/v1/datasets/'+attributes.datasetId});
       const viewPromise=attributes.viewId?apiFetch({path:'/ntc/v1/views/'+attributes.viewId}):Promise.resolve(null);
       const allRows=[];let offset=0,total=1;const chunk=750;
       while(offset<total&&allRows.length<10000){const r=await apiFetch({path:'/ntc/v1/datasets/'+attributes.datasetId+'/rows?limit='+chunk+'&offset='+offset});const part=r.rows||[];total=Math.min(10000,Number(r.total||0));allRows.push(...part);if(!part.length)break;offset+=part.length;}
       const d=await datasetPromise,v=await viewPromise;if(cancelled)return;const next={columns:d.columns||[],rows:allRows};
       if(v&&v.config){const vc=Object.assign({},v.config);if(type==='table'&&vc.cellMeta){next.cellMeta=vc.cellMeta;delete vc.cellMeta;}next.config=vc;}
       setAttributes(next);latestRows.current=next.rows;setStatus(attributes.viewId?__('Loaded synced view','native-tables-charts'):__('Loaded reusable dataset','native-tables-charts'));
     }catch(e){if(!cancelled)setStatus(__('Could not load reusable data','native-tables-charts'));}
     finally{if(!cancelled){setLoading(false);setTimeout(()=>{loadingRemote.current=false;},0);}}
   })();
   return()=>{cancelled=true;};
 },[attributes.datasetId,attributes.viewId]);

 const saveShared=async()=>{
   if(!attributes.datasetId||attributes.mode==='inline')return;
   const data=latestRows.current||[];
   try{
    setStatus(__('Saving dataset…','native-tables-charts'));
    if(needsReplace.current){
      needsReplace.current=false;dirtyRows.current.clear();
      await apiFetch({path:'/ntc/v1/datasets/'+attributes.datasetId+'/rows',method:'PUT',data:{replace:true,rows:[]}});
      for(let i=0;i<data.length;i+=250)await apiFetch({path:'/ntc/v1/datasets/'+attributes.datasetId+'/rows',method:'PUT',data:{rows:data.slice(i,i+250),startIndex:i}});
    }else if(dirtyRows.current.size){
      const indexed={};dirtyRows.current.forEach(i=>indexed[i]=data[i]||[]);dirtyRows.current.clear();
      await apiFetch({path:'/ntc/v1/datasets/'+attributes.datasetId+'/rows',method:'PATCH',data:{indexedRows:indexed}});
    }
    setStatus(__('Dataset saved','native-tables-charts'));
   }catch(e){setStatus(__('Dataset save failed','native-tables-charts'));}
 };
 const scheduleSave=(ri,structural)=>{
   if(!attributes.datasetId||attributes.mode==='inline')return;
   if(structural){needsReplace.current=true;}else if(ri===null){(latestRows.current||[]).forEach((_,i)=>dirtyRows.current.add(i));}else dirtyRows.current.add(ri);
   clearTimeout(saveTimer.current);saveTimer.current=setTimeout(saveShared,1200);setStatus(__('Unsaved dataset changes','native-tables-charts'));
 };
 const setRows=(next,ri,structural=false)=>{latestRows.current=next;setAttributes({rows:next});scheduleSave(ri,structural);};
 const setColumns=next=>{setAttributes({columns:next});if(attributes.datasetId&&attributes.mode!=='inline'){setStatus(__('Saving columns…','native-tables-charts'));apiFetch({path:'/ntc/v1/datasets/'+attributes.datasetId,method:'PUT',data:{columns:next}}).then(()=>setStatus(__('Dataset saved','native-tables-charts'))).catch(()=>setStatus(__('Column save failed','native-tables-charts')));}};

 const saveViewNow=()=>{
   if(!attributes.viewId||loadingRemote.current)return Promise.resolve();
   clearTimeout(viewTimer.current);
   const config=Object.assign({},attributes.config||{},type==='table'?{cellMeta:attributes.cellMeta||{}}:{});
   setStatus(__('Saving synced view…','native-tables-charts'));
   return apiFetch({path:'/ntc/v1/views/'+attributes.viewId,method:'PUT',data:{config}}).then(()=>setStatus(__('Synced view saved','native-tables-charts'))).catch(()=>setStatus(__('View save failed','native-tables-charts')));
 };
 useEffect(()=>{if(!attributes.viewId||loadingRemote.current)return;clearTimeout(viewTimer.current);viewTimer.current=setTimeout(saveViewNow,900);return()=>clearTimeout(viewTimer.current);},[attributes.config,attributes.cellMeta,attributes.viewId]);

 const bounds=()=>{const r1=selected&&selected.r>=0?Math.min(selected.r,selected.r2??selected.r):0;const r2=selected&&selected.r>=0?Math.max(selected.r,selected.r2??selected.r):0;const c1=selected?Math.min(selected.c,selected.c2??selected.c):0;const c2=selected?Math.max(selected.c,selected.c2??selected.c):0;return {r1,r2,c1,c2};};
 const transformCellMeta=(rowMap,colMap,extra={})=>{const src=attributes.cellMeta||{};const next={};Object.entries(src).forEach(([key,val])=>{let nr,nc;if(key.startsWith('header:')){nr=null;nc=Number(key.split(':')[1]);}else{const p=key.split(':');nr=Number(p[0]);nc=Number(p[1]);}if(!Number.isFinite(nc)||(nr!==null&&!Number.isFinite(nr)))return;const mc=colMap?colMap(nc):nc;const mr=nr===null?null:(rowMap?rowMap(nr):nr);if(mc===null||mr===null||mc<0||(mr!==null&&mr<0))return;next[mr===null?'header:'+mc:mr+':'+mc]=val;});Object.assign(next,extra);setAttributes({cellMeta:next});return next;};
 const remapTableColumnConfig=(mapFn)=>{if(type!=='table')return;const cfg=Object.assign({},attributes.config||{});const mapObj=obj=>{const out={};Object.entries(obj||{}).forEach(([k,v])=>{const nk=mapFn(Number(k));if(nk!==null&&nk>=0)out[nk]=v;});return out;};const mapIndexes=arr=>(arr||[]).map(Number).map(mapFn).filter(x=>x!==null&&x>=0);cfg.columnWidths=mapObj(cfg.columnWidths);cfg.columnAlign=mapObj(cfg.columnAlign);cfg.hidePhone=mapIndexes(cfg.hidePhone);cfg.hideTablet=mapIndexes(cfg.hideTablet);cfg.defaultSort=(cfg.defaultSort||[]).map(r=>Object.assign({},r,{column:mapFn(Number(r.column||0))})).filter(r=>r.column!==null&&r.column>=0);['autoColorRules','autoAlignRules'].forEach(k=>{cfg[k]=(cfg[k]||[]).map(r=>r.type==='column'?Object.assign({},r,{indexes:mapIndexes(r.indexes)}):r);});setAttributes({config:cfg});};
 const remapChartColumnConfig=(mapFn)=>{if(type!=='chart')return;const cfg=Object.assign({},attributes.config||{});const label=mapFn(Number(cfg.labelColumn||0));const values=(cfg.valueColumns||[]).map(Number).map(mapFn).filter(x=>x!==null&&x>=0);const sort=mapFn(Number(cfg.sortColumn??(values[0]||0)));cfg.labelColumn=label===null?0:label;cfg.valueColumns=values.length?values:[Math.min(1,Math.max(0,columns.length-2))];cfg.sortColumn=sort===null?(cfg.valueColumns[0]||0):sort;setAttributes({config:cfg});};
 const addRow=()=>{if(rows.length>=10000)return;setRows(rows.concat([Array(columns.length).fill('')]),null,true);};
 const insertRow=(at)=>{if(rows.length>=10000)return;at=clamp(at,0,rows.length);transformCellMeta(r=>r>=at?r+1:r,null);const next=rows.slice();next.splice(at,0,Array(columns.length).fill(''));setRows(next,null,true);setSelected({r:at,c:selected?selected.c:0,r2:at,c2:selected?selected.c:0});};
 const duplicateRows=()=>{if(rows.length>=10000)return;const {r1,r2}=bounds();const count=Math.min(r2-r1+1,10000-rows.length);if(count<=0)return;const at=r2+1;const srcMeta=attributes.cellMeta||{};const shifted={};Object.entries(srcMeta).forEach(([key,val])=>{if(key.startsWith('header:')){shifted[key]=val;return;}const [r,c]=key.split(':').map(Number);const nr=r>=at?r+count:r;shifted[nr+':'+c]=val;});for(let off=0;off<count;off++){const oldR=r1+off,newR=at+off;Object.entries(srcMeta).forEach(([key,val])=>{if(key.startsWith('header:'))return;const [r,c]=key.split(':').map(Number);if(r===oldR)shifted[newR+':'+c]=clone(val);});}setAttributes({cellMeta:shifted});const next=rows.slice();next.splice(at,0,...rows.slice(r1,r1+count).map(r=>r.slice()));setRows(next,null,true);setSelected({r:at,c:selected?selected.c:0,r2:at+count-1,c2:selected?selected.c2??selected.c:0});};
 const deleteRows=()=>{if(rows.length<=1)return;const {r1,r2}=bounds();const count=Math.min(r2-r1+1,rows.length-1);const end=r1+count-1;transformCellMeta(r=>r<r1?r:(r>end?r-count:null),null);const next=rows.filter((_,i)=>i<r1||i>end);setRows(next.length?next:[Array(columns.length).fill('')],null,true);setSelected({r:clamp(r1,0,Math.max(0,next.length-1)),c:selected?selected.c:0});};
 const moveRows=delta=>{const {r1,r2}=bounds();if(delta<0&&r1===0)return;if(delta>0&&r2===rows.length-1)return;const order=Array.from({length:rows.length},(_,i)=>i);const block=order.splice(r1,r2-r1+1);const insertAt=delta<0?r1-1:r1+1;order.splice(insertAt,0,...block);const map=new Map(order.map((oldI,newI)=>[oldI,newI]));transformCellMeta(r=>map.get(r),null);setRows(order.map(i=>rows[i]),null,true);const nr1=map.get(r1),nr2=map.get(r2);setSelected({r:Math.min(nr1,nr2),c:selected?selected.c:0,r2:Math.max(nr1,nr2),c2:selected?selected.c2??selected.c:0});};
 const addCol=()=>{if(columns.length>=40)return;const ci=columns.length;setColumns(columns.concat([{id:'c'+Date.now(),label:'Column '+(ci+1),type:'auto',unit:''}]));setRows(rows.map(r=>r.concat([''])),null,true);};
 const insertCol=(at,source=null)=>{if(columns.length>=40)return;at=clamp(at,0,columns.length);const mapFn=c=>c>=at?c+1:c;const shiftedMeta=transformCellMeta(null,mapFn);remapTableColumnConfig(mapFn);remapChartColumnConfig(mapFn);const newCol=source!==null?Object.assign({},columns[source],{id:'c'+Date.now()}):{id:'c'+Date.now(),label:'Column '+(at+1),type:'auto',unit:''};const nextCols=columns.slice();nextCols.splice(at,0,newCol);const nextRows=rows.map(r=>{const n=r.slice();n.splice(at,0,source!==null?(r[source]??''):'');return n;});setColumns(nextCols);setRows(nextRows,null,true);if(source!==null){const meta=Object.assign({},attributes.cellMeta||{});const copied={};Object.entries(meta).forEach(([key,val])=>{if(key.startsWith('header:')){const c=Number(key.split(':')[1]);if(c===source)copied['header:'+at]=clone(val);}else{const [r,c]=key.split(':').map(Number);if(c===source)copied[r+':'+at]=clone(val);}});setAttributes({cellMeta:Object.assign({},shiftedMeta||{},copied)});}setSelected({r:selected&&selected.r>=0?selected.r:-1,c:at});};
 const duplicateCols=()=>{const {c1,c2}=bounds();const count=Math.min(c2-c1+1,40-columns.length);if(count<=0)return;const at=c2+1;const mapFn=c=>c>=at?c+count:c;const shiftedMeta=transformCellMeta(null,mapFn);remapTableColumnConfig(mapFn);remapChartColumnConfig(mapFn);const copies=columns.slice(c1,c1+count).map((c,i)=>Object.assign({},c,{id:'c'+Date.now()+'-'+i,label:(c.label||('Column '+(c1+i+1)))+' Copy'}));const nextCols=columns.slice();nextCols.splice(at,0,...copies);const nextRows=rows.map(r=>{const n=r.slice();n.splice(at,0,...r.slice(c1,c1+count));return n;});const copiedMeta={};Object.entries(attributes.cellMeta||{}).forEach(([key,val])=>{if(key.startsWith('header:')){const oc=Number(key.split(':')[1]);if(oc>=c1&&oc<c1+count)copiedMeta['header:'+(at+(oc-c1))]=clone(val);}else{const [rr,oc]=key.split(':').map(Number);if(oc>=c1&&oc<c1+count)copiedMeta[rr+':'+(at+(oc-c1))]=clone(val);}});setAttributes({cellMeta:Object.assign({},shiftedMeta||{},copiedMeta)});setColumns(nextCols);setRows(nextRows,null,true);setSelected({r:selected&&selected.r>=0?selected.r:-1,c:at,c2:at+count-1});};
 const deleteCols=()=>{if(columns.length<=1)return;const {c1,c2}=bounds();const count=Math.min(c2-c1+1,columns.length-1);const end=c1+count-1;const mapFn=c=>c<c1?c:(c>end?c-count:null);transformCellMeta(null,mapFn);remapTableColumnConfig(mapFn);remapChartColumnConfig(mapFn);setColumns(columns.filter((_,i)=>i<c1||i>end));setRows(rows.map(r=>r.filter((_,i)=>i<c1||i>end)),null,true);setSelected({r:selected&&selected.r>=0?selected.r:-1,c:clamp(c1,0,columns.length-count-1)});};
 const moveCols=delta=>{const {c1,c2}=bounds();if(delta<0&&c1===0)return;if(delta>0&&c2===columns.length-1)return;const order=Array.from({length:columns.length},(_,i)=>i);const block=order.splice(c1,c2-c1+1);const insertAt=delta<0?c1-1:c1+1;order.splice(insertAt,0,...block);const map=new Map(order.map((oldI,newI)=>[oldI,newI]));const mapFn=c=>map.get(c);transformCellMeta(null,mapFn);remapTableColumnConfig(mapFn);remapChartColumnConfig(mapFn);setColumns(order.map(i=>columns[i]));setRows(rows.map(r=>order.map(i=>r[i]??'')),null,true);const nc1=map.get(c1),nc2=map.get(c2);setSelected({r:selected&&selected.r>=0?selected.r:-1,c:Math.min(nc1,nc2),c2:Math.max(nc1,nc2)});};
 const copySelection=async()=>{if(!selected||selected.r<0)return;const {r1,r2,c1,c2}=bounds();const text=rows.slice(r1,r2+1).map(r=>r.slice(c1,c2+1).map(v=>String(v??'')).join('\t')).join('\n');try{if(navigator.clipboard&&navigator.clipboard.writeText)await navigator.clipboard.writeText(text);else{const ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');ta.remove();}setStatus(__('Copied selected cells','native-tables-charts'));}catch(e){setStatus(__('Could not copy selected cells','native-tables-charts'));}};
 const cutSelection=async()=>{await copySelection();clearSelection();};
 const clearSelection=()=>{if(!selected)return;const {r1,r2,c1,c2}=bounds();if(selected.r<0)return;const next=rows.map((r,ri)=>r.map((v,ci)=>ri>=r1&&ri<=r2&&ci>=c1&&ci<=c2?'':v));const meta=Object.assign({},attributes.cellMeta||{});for(let r=r1;r<=r2;r++)for(let c=c1;c<=c2;c++)delete meta[r+':'+c];setAttributes({cellMeta:meta});setRows(next,null,false);};
 const mergeSelection=()=>{if(type!=='table'||!selected)return;const {r1,r2,c1,c2}=bounds();const header=selected.r<0;const key=header?'header:'+c1:r1+':'+c1;const next=Object.assign({},attributes.cellMeta||{});next[key]=Object.assign({},next[key]||{},{rowspan:header?1:(r2-r1+1),colspan:c2-c1+1});setAttributes({cellMeta:next});setStatus(__('Cells merged for frontend display','native-tables-charts'));};
 const unmergeSelection=()=>{if(type!=='table'||!selected)return;const {r1,c1}=bounds();const key=selected.r<0?'header:'+c1:r1+':'+c1;const next=Object.assign({},attributes.cellMeta||{});if(next[key])next[key]=Object.assign({},next[key],{rowspan:1,colspan:1});setAttributes({cellMeta:next});setStatus(__('Cell merge removed','native-tables-charts'));};
 const clearData=()=>{if(!window.confirm(__('Clear all table/chart data?','native-tables-charts')))return;setAttributes({cellMeta:{}});setRows([Array(columns.length).fill('')],null,true);setSelected({r:0,c:0});};
 const deleteRowAt=i=>{if(rows.length<=1)return;transformCellMeta(r=>r<i?r:(r>i?r-1:null),null);const next=rows.filter((_,x)=>x!==i);setRows(next,null,true);setSelected({r:clamp(i,0,next.length-1),c:selected?selected.c:0});};
 const deleteColAt=i=>{if(columns.length<=1)return;const mapFn=c=>c<i?c:(c>i?c-1:null);transformCellMeta(null,mapFn);remapTableColumnConfig(mapFn);remapChartColumnConfig(mapFn);setColumns(columns.filter((_,x)=>x!==i));setRows(rows.map(r=>r.filter((_,x)=>x!==i)),null,true);setSelected({r:selected&&selected.r>=0?selected.r:-1,c:clamp(i,0,columns.length-2)});};
 const importData=d=>{if(!d.columns.length)return;setAttributes({cellMeta:{}});setColumns(d.columns);setRows(d.rows.length?d.rows:[Array(d.columns.length).fill('')],null,true);};
 const pickExisting=choice=>{
   setPickerOpen(false);const item=choice.item;
   if(choice.kind==='view'){const vc=Object.assign({},item.config||{});const next={mode:'view',datasetId:Number(item.dataset_id),viewId:Number(item.id),config:vc};if(type==='table'&&vc.cellMeta){next.cellMeta=vc.cellMeta;delete vc.cellMeta;}setAttributes(next);}
   else setAttributes({mode:'dataset',datasetId:Number(item.id),viewId:0});
 };
 const createDataset=async name=>{
   const d=await apiFetch({path:'/ntc/v1/datasets',method:'POST',data:{name,columns,rows:[]}});
   for(let i=0;i<rows.length;i+=250)await apiFetch({path:'/ntc/v1/datasets/'+d.id+'/rows',method:'PUT',data:{rows:rows.slice(i,i+250),startIndex:i}});
   return Number(d.id);
 };
 const saveReusable=async()=>{const name=window.prompt(__('Dataset name','native-tables-charts'),type==='chart'?__('Benchmark Dataset','native-tables-charts'):__('Reusable Table Data','native-tables-charts'));if(!name)return;setStatus(__('Creating dataset…','native-tables-charts'));try{const id=await createDataset(name);setAttributes({mode:'dataset',datasetId:id,viewId:0});setStatus(__('Reusable dataset created','native-tables-charts'));}catch(e){setStatus(__('Could not create dataset','native-tables-charts'));}};
 const saveView=async()=>{
   if(attributes.viewId){await saveShared();await saveViewNow();return;}
   let datasetId=attributes.datasetId;
   if(!datasetId){const name=window.prompt(__('Dataset name','native-tables-charts'),__('Reusable Dataset','native-tables-charts'));if(!name)return;datasetId=await createDataset(name);setAttributes({datasetId,mode:'dataset'});}else await saveShared();
   const vname=window.prompt(__('Synced view name','native-tables-charts'),type==='chart'?__('Reusable Chart','native-tables-charts'):__('Reusable Table','native-tables-charts'));if(!vname)return;
   const view=await apiFetch({path:'/ntc/v1/views',method:'POST',data:{dataset_id:datasetId,type,name:vname,config:Object.assign({},attributes.config||{},type==='table'?{cellMeta:attributes.cellMeta||{}}:{})}});
   setAttributes({mode:'view',viewId:Number(view.id),datasetId});setStatus(__('Synced view saved','native-tables-charts'));
 };
 const detach=()=>{clearTimeout(saveTimer.current);clearTimeout(viewTimer.current);setAttributes({mode:'inline',datasetId:0,viewId:0});setStatus(__('Detached as an inline copy','native-tables-charts'));};
 const savePreset=async()=>{const name=window.prompt(__('Preset name','native-tables-charts'));if(!name)return;await apiFetch({path:'/ntc/v1/presets',method:'POST',data:{type,name,settings:attributes.config||{}}});const all=await apiFetch({path:'/ntc/v1/presets'});setCustomPresets(all.custom||[]);setStatus(__('Preset saved','native-tables-charts'));};


 const metricCount=type==='chart'?Math.max(1,(chartConfig.valueColumns||[]).length):columns.length;
 const dataKind=attributes.viewId?__('Synced view','native-tables-charts'):attributes.datasetId?__('Reusable dataset','native-tables-charts'):__('Inline data','native-tables-charts');
 const summaryText=type==='chart'?rows.length+' '+(rows.length===1?__('row','native-tables-charts'):__('rows','native-tables-charts'))+' • '+metricCount+' '+(metricCount===1?__('metric','native-tables-charts'):__('metrics','native-tables-charts'))+' • '+dataKind:rows.length+' '+(rows.length===1?__('row','native-tables-charts'):__('rows','native-tables-charts'))+' • '+columns.length+' '+(columns.length===1?__('column','native-tables-charts'):__('columns','native-tables-charts'))+' • '+dataKind;
 const previewWidth=previewMode==='mobile'?'380px':previewMode==='tablet'?'720px':'100%';
 const selectionLabel=()=>{if(!selected)return __('No selection','native-tables-charts');const b=bounds();if(selected.r<0)return (columns[b.c1]&&columns[b.c1].label?columns[b.c1].label:__('Column','native-tables-charts')+' '+(b.c1+1))+(b.c2>b.c1?' – '+(columns[b.c2]&&columns[b.c2].label?columns[b.c2].label:(b.c2+1)):'');if(b.r1===b.r2&&b.c1===b.c2)return __('Row','native-tables-charts')+' '+(b.r1+1)+' • '+(columns[b.c1]&&columns[b.c1].label?columns[b.c1].label:__('Column','native-tables-charts')+' '+(b.c1+1));return (b.r2-b.r1+1)+' × '+(b.c2-b.c1+1)+' '+__('cells selected','native-tables-charts');};
 const moreControls=()=>{
   const controls=[
     {title:__('Export data','native-tables-charts'),onClick:()=>setExportOpen(true)},
     {title:__('Use existing data or view','native-tables-charts'),onClick:()=>setPickerOpen(true)},
     attributes.mode==='inline'?{title:__('Save as reusable dataset','native-tables-charts'),onClick:saveReusable}:{title:__('Detach as inline copy','native-tables-charts'),onClick:detach},
     {title:attributes.viewId?__('Save synced view','native-tables-charts'):__('Save as synced view','native-tables-charts'),onClick:saveView},
     {title:__('Save style preset','native-tables-charts'),onClick:savePreset}
   ];
   if(attributes.mode!=='inline')controls.push({title:__('Save shared data now','native-tables-charts'),onClick:async()=>{await saveShared();if(attributes.viewId)await saveViewNow();}});
   if(selected&&selected.r>=0){controls.push(
     {title:__('Insert row above','native-tables-charts'),onClick:()=>insertRow(bounds().r1)},
     {title:__('Insert row below','native-tables-charts'),onClick:()=>insertRow(bounds().r2+1)},
     {title:__('Duplicate selected row(s)','native-tables-charts'),onClick:duplicateRows},
     {title:__('Move selected row(s) up','native-tables-charts'),onClick:()=>moveRows(-1)},
     {title:__('Move selected row(s) down','native-tables-charts'),onClick:()=>moveRows(1)},
     {title:__('Delete selected row(s)','native-tables-charts'),onClick:deleteRows},
     {title:__('Copy selected cells','native-tables-charts'),onClick:copySelection},
     {title:__('Cut selected cells','native-tables-charts'),onClick:cutSelection},
     {title:__('Clear selected cells','native-tables-charts'),onClick:clearSelection}
   );}
   controls.push(
     {title:__('Insert column left','native-tables-charts'),onClick:()=>insertCol(bounds().c1)},
     {title:__('Insert column right','native-tables-charts'),onClick:()=>insertCol(bounds().c2+1)},
     {title:__('Duplicate selected column(s)','native-tables-charts'),onClick:duplicateCols},
     {title:__('Move selected column(s) left','native-tables-charts'),onClick:()=>moveCols(-1)},
     {title:__('Move selected column(s) right','native-tables-charts'),onClick:()=>moveCols(1)},
     {title:__('Delete selected column(s)','native-tables-charts'),onClick:deleteCols}
   );
   if(type==='table'&&((CFG.cellFeatures||{}).rowSpan!==false||(CFG.cellFeatures||{}).columnSpan!==false))controls.push({title:__('Merge selected cells','native-tables-charts'),onClick:mergeSelection},{title:__('Unmerge selected cell','native-tables-charts'),onClick:unmergeSelection});
   controls.push({title:__('Clear all data','native-tables-charts'),onClick:clearData});
   return controls;
 };
 const MoreMenu=()=>C.DropdownMenu?h(C.DropdownMenu,{icon:'ellipsis',label:__('More data actions','native-tables-charts'),controls:moreControls(),toggleProps:{variant:'tertiary'}}):h(Button,{variant:'tertiary',onClick:()=>setExportOpen(true)},__('More','native-tables-charts'));
 const renderSelectionActions=()=>{
   if(!selected)return null;const b=bounds();
   if(selected.r<0)return h('div',{className:'ntc-selection-toolbar'},h('strong',null,selectionLabel()),h(Button,{variant:'tertiary',onClick:()=>insertCol(b.c1),disabled:columns.length>=40},__('Insert left','native-tables-charts')),h(Button,{variant:'tertiary',onClick:duplicateCols,disabled:columns.length>=40},__('Duplicate','native-tables-charts')),h(Button,{variant:'tertiary',onClick:()=>moveCols(-1)},__('Move left','native-tables-charts')),h(Button,{variant:'tertiary',onClick:()=>moveCols(1)},__('Move right','native-tables-charts')),h(Button,{variant:'tertiary',isDestructive:true,onClick:deleteCols,disabled:columns.length<=1},__('Delete','native-tables-charts')));
   return h('div',{className:'ntc-selection-toolbar'},h('strong',null,selectionLabel()),type==='chart'&&h(Button,{variant:focusValues.includes(String((rows[b.r1]||[])[labelColumn]??''))?'primary':'secondary',icon:focusValues.includes(String((rows[b.r1]||[])[labelColumn]??''))?'star-filled':'star-empty',onClick:()=>toggleRowFocus(b.r1)},focusValues.includes(String((rows[b.r1]||[])[labelColumn]??''))?__('Clear focus','native-tables-charts'):__('Set as chart focus','native-tables-charts')),h(Button,{variant:'tertiary',onClick:()=>insertRow(b.r1),disabled:rows.length>=10000},__('Insert above','native-tables-charts')),h(Button,{variant:'tertiary',onClick:duplicateRows,disabled:rows.length>=10000},__('Duplicate row','native-tables-charts')),h(Button,{variant:'tertiary',onClick:copySelection},__('Copy','native-tables-charts')),h(Button,{variant:'tertiary',onClick:clearSelection},__('Clear','native-tables-charts')),h(Button,{variant:'tertiary',isDestructive:true,onClick:deleteRows,disabled:rows.length<=1},__('Delete row','native-tables-charts')));
 };
 const renderDataWorkspace=(isFocus=false)=>h('div',{className:'ntc-data-workspace'+(isFocus?' is-focus':'')},
   h('div',{className:'ntc-data-toolbar'},
     type==='chart'&&!isFocus&&h(Button,{variant:'primary',icon:'yes',onClick:()=>setEditorMode('preview')},__('Done editing data','native-tables-charts')),
     isFocus&&h(Button,{variant:'primary',icon:'yes',onClick:()=>setFocusOpen(false)},__('Done','native-tables-charts')),
     h(Button,{variant:'secondary',onClick:addRow,disabled:rows.length>=10000},__('Add row','native-tables-charts')),
     h(Button,{variant:'secondary',onClick:addCol,disabled:columns.length>=40},__('Add column','native-tables-charts')),
     h(Button,{variant:'secondary',icon:'upload',onClick:()=>setImportOpen(true)},__('Paste / Import','native-tables-charts')),
     h(MoreMenu),
     !isFocus&&h(Button,{variant:'tertiary',icon:'fullscreen-alt',onClick:()=>setFocusOpen(true)},__('Focus mode','native-tables-charts')),
     h('span',{className:'ntc-status'},status||summaryText)
   ),
   attributes.mode!=='inline'&&h(Notice,{status:'info',isDismissible:false,className:'ntc-shared-data-notice'},attributes.viewId?__('This chart/table uses a synced view. Data edits can affect every visualization using the shared dataset.','native-tables-charts'):__('This chart/table uses a reusable dataset. Data edits can affect every visualization using it.','native-tables-charts')),
   renderSelectionActions(),
   h('div',{className:'ntc-editor-shell'+(isFocus?' is-focus':'')},
     loading?h('div',{className:'ntc-editor-empty'},h(Spinner)):h(VirtualGrid,{columns,rows,onColumns:setColumns,onRows:(next,ri)=>setRows(next,ri,false),selected,onSelect:sel=>setSelected(prev=>{if(sel.r<0)return {r:-1,c:sel.c,r2:-1,c2:sel.c};if(sel.shift&&prev&&prev.r>=0)return {r:prev.r,c:prev.c,r2:sel.r,c2:sel.c};return {r:sel.r,c:sel.c,r2:sel.r,c2:sel.c};}),onDeleteRow:deleteRowAt,onDeleteCol:deleteColAt,focusValues:type==='chart'?focusValues:null,onToggleFocus:type==='chart'?toggleRowFocus:null,onFocusLabelChange:type==='chart'?updateFocusLabel:null,labelColumn,allowMultipleFocus}),
     type==='table'&&h(CellProperties,{selected,cellMeta:attributes.cellMeta||{},setCellMeta:v=>setAttributes({cellMeta:v})})
   ),
   rows.length>(CFG.maxInlineRows||250)&&attributes.mode==='inline'&&h('div',{className:'ntc-large-note'},__('This inline dataset is large. Save it as a reusable dataset for better post-editor performance.','native-tables-charts'))
 );
 const renderPreview=()=>h('div',{className:'ntc-chart-preview-shell'},
   SSR&&rows.length<=500?h('div',{className:'ntc-preview-viewport is-'+previewMode,style:{maxWidth:previewWidth}},h(SSR,{block:type==='chart'?'ntc/chart':'ntc/table',attributes})):h('div',{className:'ntc-large-note'},__('In-editor rendering is disabled for datasets over 500 rows to keep Gutenberg fast. Use WordPress Preview to inspect the published layout.','native-tables-charts')),
   h('div',{className:'ntc-data-summary'},h(Button,{variant:'tertiary',onClick:()=>setEditorMode('data'),icon:'editor-table'},summaryText),type==='chart'&&h('span',{className:'ntc-preview-size'},previewMode==='desktop'?__('Desktop preview','native-tables-charts'):previewMode==='tablet'?__('Tablet preview','native-tables-charts'):__('Mobile preview','native-tables-charts')))
 );
 const widthMenu=h(DropdownMenu,{
   icon:widthMode==='full'?'fullscreen-alt':widthMode==='wide'?'align-wide':'align-center',
   label:__('Block width','native-tables-charts'),
   toggleProps:{'aria-label':__('Choose block width','native-tables-charts')},
   controls:[
     {title:__('Content width','native-tables-charts'),icon:'align-center',isActive:widthMode==='content',onClick:()=>setWidthMode('content')},
     {title:__('Wide width','native-tables-charts'),icon:'align-wide',isActive:widthMode==='wide',onClick:()=>setWidthMode('wide')},
     {title:__('Full width','native-tables-charts'),icon:'fullscreen-alt',isActive:widthMode==='full',onClick:()=>setWidthMode('full')}
   ]
 });
 const blockControls=type==='chart'?h(BlockControls,null,
   h(ToolbarGroup,null,widthMenu),
   h(ToolbarGroup,null,
     h(ToolbarButton,{icon:'visibility',label:__('Preview chart','native-tables-charts'),isPressed:editorMode==='preview',onClick:()=>setEditorMode('preview')}),
     h(ToolbarButton,{icon:'editor-table',label:__('Edit chart data','native-tables-charts'),isPressed:editorMode==='data',onClick:()=>setEditorMode('data')}),
     h(ToolbarButton,{icon:'columns',label:__('Split data and preview','native-tables-charts'),isPressed:editorMode==='split',onClick:()=>setEditorMode('split')}),
     h(ToolbarButton,{icon:'fullscreen-alt',label:__('Open data focus mode','native-tables-charts'),onClick:()=>setFocusOpen(true)}),
     h(ToolbarButton,{icon:'art',label:__('Edit chart style','native-tables-charts'),onClick:()=>setStyleOpen(true)})
   ),
   h(ToolbarGroup,null,
     h(ToolbarButton,{icon:'desktop',label:__('Desktop preview','native-tables-charts'),isPressed:previewMode==='desktop',onClick:()=>setPreviewMode('desktop')}),
     h(ToolbarButton,{icon:'tablet',label:__('Tablet preview','native-tables-charts'),isPressed:previewMode==='tablet',onClick:()=>setPreviewMode('tablet')}),
     h(ToolbarButton,{icon:'smartphone',label:__('Mobile preview','native-tables-charts'),isPressed:previewMode==='mobile',onClick:()=>setPreviewMode('mobile')})
   )
 ):h(BlockControls,null,
   h(ToolbarGroup,null,widthMenu),
   h(ToolbarGroup,null,
     h(ToolbarButton,{icon:'editor-table',label:__('Edit table data','native-tables-charts'),isPressed:editorMode==='data',onClick:()=>setEditorMode('data')}),
     h(ToolbarButton,{icon:'visibility',label:__('Preview table','native-tables-charts'),isPressed:editorMode==='preview',onClick:()=>setEditorMode('preview')}),
     h(ToolbarButton,{icon:'fullscreen-alt',label:__('Open table focus mode','native-tables-charts'),onClick:()=>setFocusOpen(true)}),
     h(ToolbarButton,{icon:'upload',label:__('Import/paste data','native-tables-charts'),onClick:()=>setImportOpen(true)}),
     h(ToolbarButton,{icon:'art',label:__('Edit table style','native-tables-charts'),onClick:()=>setStyleOpen(true)})
   )
 );

 return h(wp.element.Fragment,null,
   type==='table'?h(TableInspector,{attributes,setAttributes,selected,customPresets}):h(ChartInspector,{attributes,setAttributes,customPresets,onEditData:()=>setEditorMode('data'),onFocusData:()=>setFocusOpen(true)}),
   h(InspectorControls,null,h(PanelBody,{title:__('Layout','native-tables-charts'),initialOpen:false},h(SelectControl,{label:__('Width','native-tables-charts'),value:widthMode,options:[{label:__('Content width','native-tables-charts'),value:'content'},{label:__('Wide width','native-tables-charts'),value:'wide'},{label:__('Full width','native-tables-charts'),value:'full'}],onChange:setWidthMode}),h('p',{className:'ntc-inspector-note'},__('Content width matches the normal article column. Wide and Full are deliberate breakout layouts.','native-tables-charts')))),
   h(StyleInspector,{type,attributes,setAttributes,customPresets}),
   blockControls,
   h('div',blockProps,editorMode==='preview'?renderPreview():editorMode==='split'&&type==='chart'?h('div',{className:'ntc-split-layout'},renderDataWorkspace(false),renderPreview()):renderDataWorkspace(false)),
   focusOpen&&h(Modal,{title:(type==='chart'?(chartConfig.title||__('Chart','native-tables-charts')):__('Table','native-tables-charts'))+' — '+__('Data','native-tables-charts'),onRequestClose:()=>setFocusOpen(false),className:'ntc-focus-modal'},renderDataWorkspace(true)),
   styleOpen&&h(QuickStyleModal,{type,attributes,setAttributes,customPresets,onClose:()=>setStyleOpen(false)}),
   importOpen&&h(DataImportModal,{onClose:()=>setImportOpen(false),onImport:importData}),
   exportOpen&&h(ExportModal,{columns,rows,type,onClose:()=>setExportOpen(false)}),
   pickerOpen&&h(DatasetPicker,{type,onClose:()=>setPickerOpen(false),onPick:pickExisting})
 );

}

function TableEdit(props){return h(DataEditor,Object.assign({},props,{type:'table'}));}
function ChartEdit(props){return h(DataEditor,Object.assign({},props,{type:'chart'}));}

registerBlockType('ntc/table',{
 apiVersion:3,attributes:tableAttributes,supports:{anchor:true,html:false,spacing:{margin:true}},title:__('Native Data Table','native-tables-charts'),description:__('Responsive sortable tables with reusable datasets.','native-tables-charts'),icon:'editor-table',category:'ntc-data',keywords:[__('table','native-tables-charts'),__('benchmark','native-tables-charts')],edit:TableEdit,save:()=>null,
 transforms:{to:[{type:'block',blocks:['ntc/chart'],transform:a=>createBlock('ntc/chart',{widthMode:a.widthMode,align:a.align,mode:a.mode,datasetId:a.datasetId,columns:a.columns,rows:a.rows,config:Object.assign({},CFG.chartDefaults||{},{chartType:'horizontal-bar',title:'',labelColumn:0,valueColumns:[Math.min(1,(a.columns||[]).length-1)],sortColumn:Math.min(1,(a.columns||[]).length-1),preset:'benchmark-dark'})})}]}
});
registerBlockType('ntc/chart',{
 apiVersion:3,attributes:chartAttributes,supports:{anchor:true,html:false,spacing:{margin:true}},title:__('Native Data Chart','native-tables-charts'),description:__('Responsive charts rendered inside WordPress without an external service.','native-tables-charts'),icon:'chart-bar',category:'ntc-data',keywords:[__('chart','native-tables-charts'),__('benchmark','native-tables-charts')],edit:ChartEdit,save:()=>null,
 transforms:{to:[{type:'block',blocks:['ntc/table'],transform:a=>createBlock('ntc/table',{widthMode:a.widthMode,align:a.align,mode:a.mode,datasetId:a.datasetId,columns:a.columns,rows:a.rows,config:Object.assign({},CFG.tableDefaults||{},{preset:'editorial'})})}]}
});


if(registerBlockVariation){
 registerBlockVariation('ntc/table',{name:'blank-table',title:__('Blank Data Table','native-tables-charts'),description:__('Start with a simple two-column table.','native-tables-charts'),icon:'editor-table',scope:['inserter'],attributes:{columns:[{id:'c1',label:'Item',type:'text',unit:''},{id:'c2',label:'Value',type:'auto',unit:''}],rows:[['','']],config:{preset:'editorial'}}});
 registerBlockVariation('ntc/table',{name:'product-comparison',title:__('Product Comparison Table','native-tables-charts'),description:__('A responsive product comparison structure.','native-tables-charts'),icon:'screenoptions',scope:['inserter'],attributes:{columns:[{id:'product',label:'Product',type:'text',unit:''},{id:'rating',label:'Rating',type:'number',unit:''},{id:'pros',label:'Pros',type:'text',unit:''},{id:'cons',label:'Cons',type:'text',unit:''},{id:'price',label:'Price',type:'currency',unit:''}],rows:[['','','','','']],config:{preset:'comparison',responsiveMode:'stack'}}});
 registerBlockVariation('ntc/table',{name:'specifications',title:__('Specifications Table','native-tables-charts'),description:__('Two-column specification and value table.','native-tables-charts'),icon:'list-view',scope:['inserter'],attributes:{columns:[{id:'specification',label:'Specification',type:'text',unit:''},{id:'value',label:'Value',type:'text',unit:''}],rows:[['','']],config:{preset:'specifications',tableLayout:'fixed'}}});
 registerBlockVariation('ntc/table',{name:'ranking',title:__('Ranking Table','native-tables-charts'),description:__('Sortable rankings with an automatic position column.','native-tables-charts'),icon:'awards',scope:['inserter'],attributes:{columns:[{id:'product',label:'Product',type:'text',unit:''},{id:'score',label:'Score',type:'number',unit:''}],rows:[['','']],config:{preset:'ranking',showPosition:true,enableSorting:true,enableManualSorting:true,defaultSort:[{column:1,direction:'desc',type:'number'}]}}});
 registerBlockVariation('ntc/table',{name:'benchmark-results',title:__('Benchmark Results Table','native-tables-charts'),description:__('Compact benchmark results with numeric sorting.','native-tables-charts'),icon:'performance',scope:['inserter'],attributes:{columns:[{id:'product',label:'Product',type:'text',unit:''},{id:'score',label:'Score',type:'number',unit:''}],rows:[['','']],config:{preset:'compact',enableSorting:true,defaultSort:[{column:1,direction:'desc',type:'number'}]}}});
 registerBlockVariation('ntc/chart',{name:'horizontal-benchmark',title:__('Horizontal Benchmark Chart','native-tables-charts'),description:__('Responsive benchmark bars like gaming FPS charts.','native-tables-charts'),icon:'chart-bar',scope:['inserter'],attributes:{columns:[{id:'product',label:'Product',type:'text',unit:''},{id:'score',label:'Score',type:'number',unit:''}],rows:[['Product A','100'],['Product B','90'],['Product C','80']],config:{chartType:'horizontal-bar',title:'Benchmark',subtitle:'',direction:'higher',labelColumn:0,valueColumns:[1],sortColumn:1,sortDirection:'desc',preset:'benchmark-dark'}}});
 registerBlockVariation('ntc/chart',{name:'dual-metric-benchmark',title:__('Dual-Metric Benchmark Chart','native-tables-charts'),description:__('Two independently scaled benchmark panels, ideal for multi-core/single-core data.','native-tables-charts'),icon:'columns',scope:['inserter'],attributes:{columns:[{id:'product',label:'Product',type:'text',unit:''},{id:'metric1',label:'Metric One',type:'number',unit:''},{id:'metric2',label:'Metric Two',type:'number',unit:''}],rows:[['Product A','100','75'],['Product B','90','85'],['Product C','80','95']],config:{chartType:'dual-metric',title:'Benchmark',subtitle:'Metric One / Metric Two',direction:'higher',labelColumn:0,valueColumns:[1,2],sortColumn:1,sortDirection:'desc',preset:'benchmark-dark'}}});
 registerBlockVariation('ntc/chart',{name:'grouped-comparison',title:__('Grouped Comparison Chart','native-tables-charts'),description:__('Compare several numeric series for each item.','native-tables-charts'),icon:'chart-bar',scope:['inserter'],attributes:{columns:[{id:'product',label:'Product',type:'text',unit:''},{id:'series1',label:'Series One',type:'number',unit:''},{id:'series2',label:'Series Two',type:'number',unit:''}],rows:[['Product A','100','80'],['Product B','90','88']],config:{chartType:'grouped-bar',title:'Comparison',direction:'higher',labelColumn:0,valueColumns:[1,2],sortColumn:1,sortDirection:'desc',preset:'comparison'}}});
}
})(window.wp);
