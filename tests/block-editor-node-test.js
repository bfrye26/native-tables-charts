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
sandbox.window.wp = {
	element: { createElement: component, useState: noop, useEffect: noop, useRef: noop, useMemo: noop, Fragment: component },
	blocks: { registerBlockType: noop, registerBlockVariation: null, createBlock: noop },
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
