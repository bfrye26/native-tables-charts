<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-ntc-formulas.php';
require dirname( __DIR__ ) . '/includes/class-ntc-renderer.php';
require dirname( __DIR__ ) . '/includes/class-ntc-rest.php';
$fails = 0;
$assert = function ( $cond, $label ) use ( &$fails ) { if ( $cond ) { echo "ok   $label\n"; } else { echo "FAIL $label\n"; $fails++; } };
$assert( "'=SUM(A1)" === NTC_REST::guard_csv_cell( '=SUM(A1)' ), 'guard prefixes =' );
$assert( 'hello' === NTC_REST::guard_csv_cell( 'hello' ), 'guard passthrough' );
$assert( '-12.5' === NTC_REST::guard_csv_cell( '-12.5' ), 'guard keeps plain negative numbers' );
echo $fails ? "FAILURES: $fails\n" : "ALL PASS\n";
exit( $fails ? 1 : 0 );
