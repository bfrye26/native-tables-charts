const fs = require( 'fs' );
const vm = require( 'vm' );
const src = fs.readFileSync( __dirname + '/../assets/js/block-editor.js', 'utf8' );
const noop = () => {};
const component = () => null;
const sandbox = {
	window: { NTC_EDITOR: {} },
	console,
	setTimeout,
	clearTimeout,
};
const apiCalls = [];
sandbox.apiFetch = ( { path } ) => {
	apiCalls.push( path );
	if ( path === '/ntc/v1/datasets/7' ) return Promise.resolve( { columns: [ { label: 'Product' }, { label: 'Score' } ], row_count: 1 } );
	if ( path === '/ntc/v1/views/3' ) return Promise.resolve( { config: { preset: 'compact', cellMeta: { '0:0': { fontWeight: '700' } } } } );
	if ( path.includes( '/rows?' ) ) return Promise.resolve( { rows: [ [ 'A', '10' ] ], total: 1 } );
	return Promise.reject( new Error( 'Unexpected API path: ' + path ) );
};
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
vm.createContext( sandbox );
vm.runInContext( src, sandbox );

const target = sandbox.window.NTC_BLOCK_EDITOR_TEST.gridKeyTarget;
const fetchReusableData = sandbox.window.NTC_BLOCK_EDITOR_TEST.fetchReusableData;
const fails = [];
const assert = ( condition, label ) => {
	if ( condition ) {
		console.log( 'ok   ' + label );
	} else {
		console.log( 'FAIL ' + label );
		fails.push( label );
	}
};
const same = ( actual, expected ) => JSON.stringify( actual ) === JSON.stringify( expected );

assert( target( 'ArrowLeft', false, 2, 2, 4, 1, 1, 3, 3 ) === null, 'left arrow edits within a cell' );
assert( target( 'ArrowRight', false, 2, 2, 4, 1, 1, 3, 3 ) === null, 'right arrow edits within a cell' );
assert( target( 'ArrowLeft', false, 0, 0, 4, 1, 1, 3, 3 ).c === 0, 'left arrow moves cells at the text boundary' );
assert( target( 'ArrowRight', false, 4, 4, 4, 1, 1, 3, 3 ).c === 2, 'right arrow moves cells at the text boundary' );
assert( target( 'ArrowLeft', false, 0, 2, 4, 1, 1, 3, 3 ) === null, 'arrow keys collapse a text selection natively' );
assert( same( target( 'Enter', false, 0, 0, 2, 0, 1, 3, 3 ), { r: 1, c: 1, shift: false } ), 'enter moves down' );
assert( same( target( 'Enter', true, 0, 0, 2, 1, 1, 3, 3 ), { r: 0, c: 1, shift: false } ), 'shift enter moves up' );
assert( same( target( 'Tab', false, 0, 0, 2, 0, 2, 3, 3 ), { r: 1, c: 0, shift: false } ), 'tab wraps to the next row' );
assert( target( 'Tab', false, 0, 0, 2, 2, 2, 3, 3 ) === null, 'tab can leave the final cell' );

const convert = sandbox.window.NTC_BLOCK_EDITOR_TEST.convertTableBlock;

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

assert( typeof convert === 'function', 'convertTableBlock is exported for tests' );
assert( convert( coreAttrs ).columns.length === 2, 'core table produces two columns' );
assert( convert( coreAttrs ).columns[ 1 ].type === 'number', 'numeric column is detected' );
assert( convert( coreAttrs ).columns[ 0 ].label === 'Name', 'header labels come from the head row' );
assert( convert( coreAttrs ).rows.length === 3, 'foot rows are appended to body rows' );
assert( convert( coreAttrs ).rows[ 2 ][ 0 ] === 'Total', 'foot cell text is preserved' );
assert( convert( coreAttrs ).cellMeta[ 'header:1' ].html === '<strong>Score</strong>', 'cell HTML is preserved in cellMeta' );
assert( convert( coreAttrs ).cellMeta[ '0:1' ].alignment === 'right', 'core align maps to alignment' );
assert( convert( coreAttrs ).cellMeta[ '2:0' ].colspan === 2, 'colspan maps to cellMeta' );
assert( convert( coreAttrs ).config.tableLayout === 'fixed', 'hasFixedLayout maps to tableLayout' );
assert( convert( coreAttrs ).config.caption === 'Results' && convert( coreAttrs ).config.captionSide === 'bottom', 'caption maps with bottom side' );
assert( convert( coreAttrs ).config.preset === 'editorial', 'converted tables use the editorial preset' );

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

( async () => {
	const loaded = await fetchReusableData( 7, 3, 'table' );
	assert( loaded.rows.length === 1 && loaded.rows[ 0 ][ 1 ] === '10', 'reusable rows load before selection is applied' );
	assert( loaded.columns.length === 2, 'reusable columns load with the rows' );
	assert( loaded.config.preset === 'compact' && loaded.config.cellMeta === undefined, 'table view config is separated from cell metadata' );
	assert( loaded.cellMeta[ '0:0' ].fontWeight === '700', 'table cell metadata loads atomically with its view' );
	assert( apiCalls.includes( '/ntc/v1/datasets/7' ) && apiCalls.includes( '/ntc/v1/views/3' ), 'dataset and view are both requested' );
	process.exit( fails.length ? 1 : 0 );
} )().catch( error => {
	console.error( error );
	process.exit( 1 );
} );
