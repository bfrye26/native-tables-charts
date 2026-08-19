# Core/Flexible Table → NTC Block Transforms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let editors transform `core/table` and `flexible-table-block/table` blocks into `ntc/table` blocks from the Gutenberg block switcher.

**Architecture:** One pure conversion function (`convertTableBlock(attributes)`) in the existing handwritten `assets/js/block-editor.js`, wired as `transforms.from` on the existing `ntc/table` registration. Behavior is verified by extending the existing node vm harness (`tests/block-editor-node-test.js`).

**Tech Stack:** WordPress Gutenberg JS (no build step — plain JS file, `wp.element.createElement` calls), Node.js for tests, PHP untouched.

## Global Constraints

- Spec: `docs/specs/2026-08-19-ntc-table-block-transforms-design.md` (approved).
- No new files for production code — edits to `assets/js/block-editor.js` and `tests/block-editor-node-test.js` only.
- Keep the handwritten ES-style conventions of `block-editor.js` (no build step, no JSX, `const {} = wp.blocks` destructuring at top).
- No DOM usage in the converter (node vm tests have no DOM). Entity decoding uses a 5-entry manual replacement.
- Preset for converted tables: `editorial`.
- Do not touch `flexible-table-block` or core files.

---

### Task 1: Failing tests for the converter and transform wiring

**Files:**
- Modify: `tests/block-editor-node-test.js` (sandbox + new assertions)

**Interfaces:**
- Consumes: nothing new.
- Produces: `window.NTC_BLOCK_EDITOR_TEST.convertTableBlock` (defined in Task 2), and `registered['ntc/table'].transforms.from[0]` (defined in Task 2).

- [ ] **Step 1: Change the sandbox so block registrations and `createBlock` calls are captured**

In `tests/block-editor-node-test.js`, replace the `blocks` sandbox entry (currently `blocks: { registerBlockType: noop, registerBlockVariation: null, createBlock: noop }`) with:

```js
const registered = {};
sandbox.window.wp = {
	element: { createElement: component, useState: noop, useEffect: noop, useRef: noop, useMemo: noop, Fragment: component },
	blocks: {
		registerBlockType: ( name, settings ) => { registered[ name ] = settings; },
		registerBlockVariation: null,
		createBlock: ( name, attributes ) => ( { name, attributes } ),
	},
	blockEditor: { InspectorControls: component, BlockControls: component, MediaUpload: component, MediaUploadCheck: component, useBlockProps: noop },
	components: new Proxy( {}, { get: () => component } ),
	i18n: { __: value => value, sprintf: value => value },
	apiFetch: sandbox.apiFetch,
	serverSideRender: null,
};
```

- [ ] **Step 2: Add the failing assertions at the end of the file, before `process.exit`**

After the existing `gridKeyTarget` block, add:

```js
const convert = () => sandbox.window.NTC_BLOCK_EDITOR_TEST.convertTableBlock;

const coreAttrs = {
	head: [ { cells: [ { content: 'Name', tag: 'th' }, { content: '<strong>Score</strong>', tag: 'th' } ] } ],
	body: [
		{ cells: [ { content: 'Alpha', tag: 'td' }, { content: '1,200', tag: 'td', align: 'right' } ] },
		{ cells: [ { content: 'Beta', tag: 'td' }, { content: '900', tag: 'td' } ] },
	],
	foot: [ { cells: [ { content: 'Total', tag: 'td', colspan: '2' } ] } ],
	caption: 'Results',
	hasFixedLayout: true,
};

assert( typeof convert() === 'function', 'convertTableBlock is exported for tests' );
assert( convert().columns.length === 2, 'core table produces two columns' );
assert( convert().columns[ 1 ].type === 'number', 'numeric column is detected' );
assert( convert().columns[ 0 ].label === 'Name', 'header labels come from the head row' );
assert( convert().rows.length === 3, 'foot rows are appended to body rows' );
assert( convert().rows[ 2 ][ 0 ] === 'Total', 'foot cell text is preserved' );
assert( convert().cellMeta[ 'header:1' ].html === '<strong>Score</strong>', 'cell HTML is preserved in cellMeta' );
assert( convert().cellMeta[ '0:1' ].alignment === 'right', 'core align maps to alignment' );
assert( convert().cellMeta[ '2:0' ].colspan === 2, 'colspan maps to cellMeta' );
assert( convert().config.tableLayout === 'fixed', 'hasFixedLayout maps to tableLayout' );
assert( convert().config.caption === 'Results' && convert().config.captionSide === 'bottom', 'caption maps with bottom side' );
assert( convert().config.preset === 'editorial', 'converted tables use the editorial preset' );

const ftbAttrs = {
	head: [ { cells: [ { content: 'Item', tag: 'th' }, { content: 'Value', tag: 'th' } ] } ],
	body: [ { cells: [ { content: 'One', tag: 'td', styles: 'color: #fff;background-color: #000;text-align: center;font-weight: 700;font-style: italic' }, { content: '50%', tag: 'td' } ] } ],
	foot: [],
	caption: 'My caption',
	captionSide: 'top',
	hasFixedLayout: false,
	isStackedOnMobile: true,
	sticky: 'header',
};

assert( convert( ftbAttrs ).rows[ 0 ][ 1 ] === '50%', 'percent values stay plain text' );
assert( convert( ftbAttrs ).columns[ 1 ].type === 'number', 'percent column is detected as numeric' );
assert( convert( ftbAttrs ).cellMeta[ '0:0' ].textColor === '#fff', 'inline color style maps to textColor' );
assert( convert( ftbAttrs ).cellMeta[ '0:0' ].backgroundColor === '#000', 'inline background style maps to backgroundColor' );
assert( convert( ftbAttrs ).cellMeta[ '0:0' ].alignment === 'center', 'inline text-align maps to alignment' );
assert( convert( ftbAttrs ).cellMeta[ '0:0' ].fontWeight === '700', 'inline font-weight maps to fontWeight' );
assert( convert( ftbAttrs ).cellMeta[ '0:0' ].fontStyle === 'italic', 'inline font-style maps to fontStyle' );
assert( convert( ftbAttrs ).config.responsiveMode === 'stack', 'isStackedOnMobile maps to stack mode' );
assert( convert( ftbAttrs ).config.stickyHeader === true, 'sticky header maps to stickyHeader' );
assert( convert( ftbAttrs ).config.captionSide === 'top', 'caption side is preserved' );

assert( convert( { head: [], body: [ { cells: [ { content: 'A &amp; B', tag: 'td' } ] } ], foot: [] } ).rows[ 0 ][ 0 ] === 'A & B', 'entities are decoded in plain cells' );
assert( convert( { head: [], body: [ { cells: [ { content: '<em>1 &amp; 2</em>', tag: 'td' } ] } ], foot: [] } ).cellMeta[ '0:0' ].html === '<em>1 &amp; 2</em>', 'entity encoding is kept inside preserved HTML' );

const ntcTable = registered[ 'ntc/table' ];
assert( !! ntcTable && Array.isArray( ntcTable.transforms && ntcTable.transforms.from ), 'ntc/table has a from transform' );
assert( ( ntcTable.transforms.from[ 0 ].blocks || [] ).indexOf( 'core/table' ) >= 0 && ( ntcTable.transforms.from[ 0 ].blocks || [] ).indexOf( 'flexible-table-block/table' ) >= 0, 'transform accepts core/table and flexible-table-block/table' );
const transformed = ntcTable.transforms.from[ 0 ].transform( coreAttrs );
assert( transformed.name === 'ntc/table', 'transform creates an ntc/table block' );
assert( transformed.attributes.mode === 'inline' && transformed.attributes.rows.length === 3, 'transform output carries inline mode and data' );
```

- [ ] **Step 3: Run the tests and confirm they fail**

Run: `node tests/block-editor-node-test.js` (from the plugin root `C:\laragon\www\CGM-New-2\wp-content\plugins\native-tables-charts`)

Expected: existing `gridKeyTarget`/`fetchReusableData` checks still pass; the new checks FAIL with `TypeError: convert() is not a function` (the first new assertion). `process.exit` code is 1.

---

### Task 2: Implement `convertTableBlock` and wire the transform

**Files:**
- Modify: `assets/js/block-editor.js:953-956` (registration) and add the converter + export near line 973

**Interfaces:**
- Consumes: nothing new (uses `createBlock` already destructured at line 5).
- Produces: `convertTableBlock(attributes) → { columns, rows, config, cellMeta, widthMode, align }` — pure, no `createBlock`.

- [ ] **Step 1: Add the converter functions just above the `registerBlockType('ntc/table', …)` call**

Insert before line 953:

```js
function decodeEntities(s){return String(s).replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"').replace(/&#39;/g,"'").replace(/&nbsp;/g,' ');}
function hasHtmlTag(s){return /<[a-z][\s\S]*>/i.test(String(s));}
function plainText(s){return decodeEntities(String(s).replace(/<br\s*\/?>/gi,' ').replace(/<\/(p|div|li|h[1-6]|tr)>/gi,' ').replace(/<[^>]+>/g,''));}
function isNumericCell(v){if(v===''||v==null)return true;var n=String(v).replace(/,/g,'');if(/%$/.test(n))n=n.slice(0,-1);n=n.trim();if(n===''||n==='-'||n==='.')return false;return isFinite(Number(n));}
function parseInlineStyles(styles,meta){var out=meta||{};(String(styles||'')).split(';').forEach(function(part){var kv=part.split(':').map(function(x){return x.trim();});if(kv.length!==2)return;var k=kv[0].toLowerCase(),v=kv[1];if(k==='text-align'&&['left','center','right'].indexOf(v)>=0)out.alignment=v;else if(k==='color')out.textColor=v;else if(k==='background-color')out.backgroundColor=v;else if(k==='font-weight')out.fontWeight=v;else if(k==='font-style')out.fontStyle=v;});return out;}
function convertTableBlock(attrs){
 attrs=attrs||{};var head=Array.isArray(attrs.head)?attrs.head:[];var body=Array.isArray(attrs.body)?attrs.body:[];var foot=Array.isArray(attrs.foot)?attrs.foot:[];
 var sections=[].concat(head,body,foot),cells=function(row){return (row&&Array.isArray(row.cells)?row.cells:[]).map(function(c){return c||{};});};
 var headerRow=head.length?cells(head[0]):null;
 var rows=[],cellMeta={};
 sections.forEach(function(row,si){if(head.length&&si===0)return;var out=[];cells(row).forEach(function(cell,ci){var text=plainText(cell.content||'');out.push(text);var meta={};if(cell.styles)parseInlineStyles(cell.styles,meta);if(cell.align&&['left','center','right'].indexOf(cell.align)>=0)meta.alignment=cell.align;if(hasHtmlTag(cell.content))meta.html=String(cell.content);var span=parseInt(cell.colSpan||cell.colspan,10);if(span>1)meta.colspan=span;var rspan=parseInt(cell.rowSpan||cell.rowspan,10);if(rspan>1)meta.rowspan=rspan;if(Object.keys(meta).length)cellMeta[(rows.length)+':'+ci]=meta;});rows.push(out);});
 var colCount=headerRow?headerRow.length:0;rows.forEach(function(r){colCount=Math.max(colCount,r.length);});
 var columns=[],colTypes=[];for(var c=0;c<colCount;c++){var label='Column '+(c+1),allNumeric=true,hasValue=false;if(headerRow&&headerRow[c]){label=plainText(headerRow[c].content||'');var hm={};if(headerRow[c].styles)parseInlineStyles(headerRow[c].styles,hm);if(headerRow[c].align&&['left','center','right'].indexOf(headerRow[c].align)>=0)hm.alignment=headerRow[c].align;if(hasHtmlTag(headerRow[c].content))hm.html=String(headerRow[c].content);var hspan=parseInt(headerRow[c].colSpan||headerRow[c].colspan,10);if(hspan>1)hm.colspan=hspan;var hrspan=parseInt(headerRow[c].rowSpan||headerRow[c].rowspan,10);if(hrspan>1)hm.rowspan=hrspan;if(Object.keys(hm).length)cellMeta['header:'+c]=hm;}
 rows.forEach(function(r){var v=r[c];if(v===''||v==null)return;hasValue=true;if(!isNumericCell(v))allNumeric=false;});columns.push({id:'c'+(c+1),label:label,type:hasValue&&allNumeric?'number':'text',unit:'',format:''});}
 var config={preset:'editorial',showHeader:!!headerRow,responsiveMode:attrs.isStackedOnMobile?'stack':'scroll',tableLayout:attrs.hasFixedLayout?'fixed':'auto',stickyHeader:attrs.sticky==='header'};
 if(attrs.caption){config.showCaption=true;config.caption=String(attrs.caption);config.captionSide=attrs.captionSide==='top'?'top':'bottom';}
 var widthMode='content',align='';if(attrs.align==='wide')widthMode='wide';if(attrs.align==='full')widthMode='full';if(attrs.align==='left'||attrs.align==='right')align=attrs.align;
 return {columns:columns,rows:rows,config:config,cellMeta:cellMeta,widthMode:widthMode,align:align};
}
```

- [ ] **Step 2: Add the `from` transform to the `ntc/table` registration**

Replace the `transforms:` property on `registerBlockType('ntc/table', …)` (line 955) with:

```js
 transforms:{from:[{type:'block',blocks:['core/table','flexible-table-block/table'],transform:a=>createBlock('ntc/table',Object.assign({},convertTableBlock(a),{mode:'inline',datasetId:0,viewId:0}))}],to:[{type:'block',blocks:['ntc/chart'],transform:a=>createBlock('ntc/chart',{widthMode:a.widthMode,align:a.align,mode:a.mode,datasetId:a.datasetId,columns:a.columns,rows:a.rows,config:Object.assign({},CFG.chartDefaults||{},{chartType:'horizontal-bar',title:'',labelColumn:0,valueColumns:[Math.min(1,(a.columns||[]).length-1)],sortColumn:Math.min(1,(a.columns||[]).length-1),preset:'benchmark-dark'})})}]}
```

- [ ] **Step 3: Export the converter for tests**

Replace line 973 (`window.NTC_BLOCK_EDITOR_TEST={gridKeyTarget,fetchReusableData};`) with:

```js
window.NTC_BLOCK_EDITOR_TEST={gridKeyTarget,fetchReusableData,convertTableBlock};
```

- [ ] **Step 4: Run the tests and confirm they pass**

Run: `node tests/block-editor-node-test.js` (plugin root)

Expected: every assertion logs `ok …`, exit code 0.

- [ ] **Step 5: Manual smoke check in the editor**

Open any post in Gutenberg on the test site with both blocks present (add one `Table` block and one `Flexible Table` block). Select each, open the block switcher (the block-type icon in the toolbar), confirm "Native Data Table" appears under Transform To, click it, and confirm the resulting NTC block shows the expected rows/columns/styling. Confirm undo restores the original block.

---

## Self-Review

**Spec coverage:** header→columns, head/foot append, plain text + `cellMeta.html`, spans, alignment, colors, fontWeight/fontStyle, caption + side, fixed layout, stacked mobile, sticky header, `editorial` preset, numeric heuristic, entity decoding, test hooks — all implemented in Task 2 and asserted in Task 1. Dropped items (padding/borders/classes/first-column sticky/stripes) are intentionally not asserted.

**Placeholder scan:** none.

**Type consistency:** `convertTableBlock` signature and return shape match between Task 1 assertions and Task 2 implementation; test hook name matches export; `transforms.from[0].blocks` matches the registration.
