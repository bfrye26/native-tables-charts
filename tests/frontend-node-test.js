const fs = require( 'fs' );
const vm = require( 'vm' );
const src = fs.readFileSync( __dirname + '/../assets/js/frontend.js', 'utf8' );
const sandbox = {
	window: {},
	document: { addEventListener() {} },
	console,
	Blob: function () {},
	URL: { createObjectURL() { return 'x'; }, revokeObjectURL() {} },
	setTimeout, clearTimeout,
};
vm.createContext( sandbox );
vm.runInContext( src, sandbox );
const fails = [];
const assert = ( c, l ) => { if ( c ) { console.log( 'ok   ' + l ); } else { console.log( 'FAIL ' + l ); fails.push( l ); } };
assert( sandbox.window.NTC_TEST.ntcGuardCell( '=SUM(A1)' ) === "'=SUM(A1)", 'guard =' );
assert( sandbox.window.NTC_TEST.ntcGuardCell( 'hello' ) === 'hello', 'guard passthrough' );
assert( sandbox.window.NTC_TEST.ntcCsvCell( 'a,b', ',' ) === '"a,b"', 'csv quote' );
assert( sandbox.window.NTC_TEST.ntcCsvCell( 'a"b', ',' ) === '"a""b"', 'csv quote escape' );
process.exit( fails.length ? 1 : 0 );
