<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/native-tables-charts.php';
$fails  = 0;
$assert = function ( $cond, $label ) use ( &$fails ) {
	if ( $cond ) {
		echo "ok   $label\n";
	} else {
		echo "FAIL $label\n";
		++$fails;
	} };
$assert( "'=SUM(A1)" === NTC_REST::guard_csv_cell( '=SUM(A1)' ), 'guard prefixes =' );
$assert( 'hello' === NTC_REST::guard_csv_cell( 'hello' ), 'guard passthrough' );
$assert( '-12.5' === NTC_REST::guard_csv_cell( '-12.5' ), 'guard keeps plain negative numbers' );
$GLOBALS['fake_slug_taken'] = true;
$repo                       = new NTC_Repository();
$repo->create_preset( 'table', 'My Style', array() );
$assert( 'my-style-2' === $GLOBALS['wpdb']->last_insert['slug'], 'slug collision suffixes -2' );
$GLOBALS['fake_slug_taken'] = false;
$repo->create_preset( 'table', 'My Style', array() );
$assert( 'my-style' === $GLOBALS['wpdb']->last_insert['slug'], 'free slug used as-is' );
NTC_Activator::deactivate();
$assert( 'ntc_sync_remote_datasets' === $GLOBALS['cleared_hook'], 'deactivate clears cron hook' );
$assert( $repo->set_source( 1, 'javascript:alert(1)' ), 'set_source returns true (normalizes)' );
$assert( '' === $GLOBALS['last_update'][0]['source_url'], 'set_source rejects non-http URL' );
$repo->set_source( 1, 'https://example.com/data.csv' );
$assert( 'https://example.com/data.csv' === $GLOBALS['last_update'][0]['source_url'], 'set_source accepts https URL' );
$repo->record_sync( 1, 'boom' );
$assert( 'boom' === $GLOBALS['last_update'][0]['source_error'], 'record_sync stores error' );
$parsed = NTC_Sync::parse( $repo, "Name,Score\nA,10\nB,20", 'csv' );
$assert( 2 === count( $parsed['columns'] ) && 2 === count( $parsed['rows'] ), 'csv parse columns/rows' );
$assert( 'A' === $parsed['rows'][0][0], 'csv parse first cell' );
$assert( 'tsv' === NTC_Sync::detect_format( "a\tb\n1\t2" ), 'detect_format tsv' );
$assert( 'csv' === NTC_Sync::detect_format( "Name,Note\nA,\"x\ty\"" ), 'detect_format csv with tab in quoted cell' );
$GLOBALS['fake_http'] = array(
	'response' => array( 'code' => 200 ),
	'body'     => "a,b\n1,2",
);
$assert( "a,b\n1,2" === NTC_Sync::fetch( 'https://example.com/x.csv' ), 'fetch returns body' );
$GLOBALS['fake_http'] = array(
	'response' => array( 'code' => 404 ),
	'body'     => '',
);
$threw                = false;
try {
	NTC_Sync::fetch( 'https://example.com/x.csv' );
} catch ( Throwable $e ) {
	$threw = true; }
$assert( $threw, 'fetch throws on 404' );
$r    = ( new ReflectionClass( 'NTC_Renderer' ) )->newInstanceWithoutConstructor();
$call = function ( string $m, ...$a ) use ( $r ) {
	$mm = ( new ReflectionClass( 'NTC_Renderer' ) )->getMethod( $m );
	$mm->setAccessible( true );
	return $mm->invokeArgs( $r, $a );
};
$assert( '' === $call( 'schema_json', array( 'dataset_updated_at' => '2026-08-17 10:00:00' ), array( 'enableSchema' => false ), array() ), 'schema off returns empty' );
$schema = $call(
	'schema_json',
	array(
		'dataset_updated_at' => '2026-08-17 10:00:00',
		'dataset_name'       => 'Scores',
	),
	array(
		'enableSchema' => true,
		'title'        => 'Bench',
		'valueColumns' => array( 1 ),
	),
	array(
		array(),
		array(
			'label' => 'Score',
			'type'  => 'number',
			'unit'  => 'fps',
		),
	)
);
$assert( false !== strpos( $schema, '"@type":"Dataset"' ) && false !== strpos( $schema, 'variableMeasured' ), 'schema emits Dataset + variables' );
$assert( false === strpos( $schema, '</script><script' ), 'schema values escaped' );
$assert( '' === $call( 'updated_date_html', array( 'showUpdatedDate' => false ), array( 'dataset_updated_at' => 'x' ) ), 'updated date off returns empty' );
$xss_schema = $call(
	'schema_json',
	array( 'dataset_updated_at' => '2026-08-17 10:00:00' ),
	array(
		'enableSchema' => true,
		'title'        => 'Bench',
		'valueColumns' => array( 1 ),
	),
	array(
		array(),
		array(
			'label' => '</script><script>alert(1)</script>',
			'type'  => 'number',
			'unit'  => 'fps',
		),
	)
);
$assert( false === strpos( $xss_schema, '<script>alert' ), 'schema_json escapes column label script tag' );
$assert( false !== strpos( $xss_schema, '&lt;' ), 'schema_json emits escaped entities for label' );
$updated = $call( 'updated_date_html', array( 'showUpdatedDate' => true ), array( 'dataset_updated_at' => '2026-08-17 10:00:00' ) );
$assert( false !== strpos( $updated, 'Last updated:' ), 'updated date renders Last updated line' );
$assert( false !== strpos( $updated, '2026-08-17 10:00:00' ), 'updated date includes raw datetime' );
$assert( 'redbackground:url(x)' === $call( 'safe_css_value', 'red;background:url(x)' ), 'safe_css_value strips ; {}<>' );
$assert( '' === $call( 'css_length', '100%;background:red' ), 'css_length rejects injection' );
$assert( false !== strpos( $call( 'tip_text', 'GPU', '100', array( 'unit' => 'fps' ), array() ), 'GPU: 100 fps' ), 'tip_text formats value with unit' );
$ticks = $call( 'time_ticks', 0.0, 100.0 );
$assert( 5 === count( $ticks ) && array( 0.0, 25.0, 50.0, 75.0, 100.0 ) === array_map( 'floatval', $ticks ), 'time_ticks returns 5 evenly spaced values' );
$assert( 'Jan 15' === $call( 'tick_label', gmmktime( 0, 0, 0, 1, 15, 2026 ), 20 * DAY_IN_SECONDS ), 'tick_label formats month-day for 20-day span' );
$ref = $call(
	'ref_lines',
	100.0,
	array(
		'referenceLines' => array(
			array(
				'value' => 50,
				'label' => 'Avg',
				'color' => '#f00',
			),
		),
	)
);
$assert( false !== strpos( $ref, 'left:50%' ) && false !== strpos( $ref, '>Avg<' ), 'ref_lines positions span at 50% with label' );
$assert(
	'' === $call(
		'ref_lines',
		100.0,
		array(
			'referenceLines' => array(
				array(
					'value' => 150,
					'label' => 'X',
				),
			),
		)
	),
	'ref_lines skips value above max'
);
$heat_cfg = array(
	'autoColorRules' => array(
		array(
			'type'       => 'column',
			'indexes'    => array( 1 ),
			'heatmap'    => true,
			'background' => '#ffffff',
			'color'      => '#000000',
		),
	),
);
$assert( false !== strpos( $call( 'cell_style', array(), $heat_cfg, 0, 1, '50', array( 1 => array( 0, 100 ) ) ), 'background-color:#808080' ), 'heatmap midpoint' );
$assert( false !== strpos( $call( 'cell_style', array(), $heat_cfg, 0, 1, '0', array( 1 => array( 0, 100 ) ) ), 'background-color:#ffffff' ), 'heatmap low bound' );
$assert( false !== strpos( $call( 'cell_style', array(), $heat_cfg, 0, 1, '100', array( 1 => array( 0, 100 ) ) ), 'background-color:#000000' ), 'heatmap high bound' );
$assert( false === strpos( $call( 'cell_style', array(), $heat_cfg, 0, 1, 'n/a', array( 1 => array( 0, 100 ) ) ), 'background-color' ), 'heatmap skips non-numeric cells' );
$heat_stats = $call(
	'heatmap_stats',
	array( array( 10 ), array( 20 ), array( 'n/a' ) ),
	array(
		'autoColorRules' => array(
			array(
				'type'    => 'column',
				'indexes' => array( 0 ),
				'heatmap' => true,
			),
		),
	),
	array()
);
$assert( isset( $heat_stats[0] ) && 10.0 === $heat_stats[0][0] && 20.0 === $heat_stats[0][1], 'heatmap stats exclude non-numeric cells' );
$heat_neg_stats = $call(
	'heatmap_stats',
	array( array( -100 ), array( -50 ) ),
	array(
		'autoColorRules' => array(
			array(
				'type'    => 'column',
				'indexes' => array( 0 ),
				'heatmap' => true,
			),
		),
	),
	array()
);
$assert( isset( $heat_neg_stats[0] ) && -100.0 === $heat_neg_stats[0][0] && -50.0 === $heat_neg_stats[0][1], 'heatmap stats min/max on all-negative column' );
$heat_neg_cfg = array(
	'autoColorRules' => array(
		array(
			'type'       => 'column',
			'indexes'    => array( 0 ),
			'heatmap'    => true,
			'background' => '#ffffff',
			'color'      => '#000000',
		),
	),
);
$assert( false !== strpos( $call( 'cell_style', array(), $heat_neg_cfg, 0, 0, '-50', array( 0 => array( -100, -50 ) ) ), 'background-color:#000000' ), 'heatmap high bound on all-negative column' );
$table_html = $call(
	'render_table',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'A',
				'type'  => 'auto',
				'unit'  => '',
			),
			array(
				'id'    => 'c2',
				'label' => 'B',
				'type'  => 'auto',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'a', 20 ), array( 'b', 'n/a' ) ),
		'config'  => array(
			'autoColorRules' => array(
				array(
					'type'       => 'column',
					'indexes'    => array( 1 ),
					'heatmap'    => true,
					'background' => '#ffffff',
					'color'      => '#000000',
				),
			),
		),
	)
);
$assert( false !== strpos( $table_html, '<thead' ) && false !== strpos( $table_html, '<tbody' ), 'render_table computes heat before header' );
$assert( false !== strpos( $call( 'render_cell', '10,20,30', array(), array(), false, 'sparkline' ), '<svg' ), 'sparkline renders svg' );
$assert( false !== strpos( $call( 'render_cell', '10,20,30', array(), array(), false, 'sparkline' ), '10,20,30' ), 'sparkline keeps raw value (sr-only)' );
$assert( false !== strpos( $call( 'render_cell', '+12.5%', array(), array(), false, 'delta' ), 'is-up' ), 'delta positive class' );
$assert( false !== strpos( $call( 'render_cell', '-3', array(), array(), false, 'delta' ), 'is-down' ), 'delta negative class' );
$ntc = NTC_Plugin::instance();
$assert( '' === $ntc->shortcode_dataset( array( 'id' => 0 ) ), 'shortcode empty without id' );
$out = $ntc->shortcode_dataset(
	array(
		'id'   => 99,
		'type' => 'table',
	)
);
$assert( false !== strpos( $out, 'ntc-table-wrap' ), 'shortcode renders table markup' );
$dup = $ntc->shortcode_dataset(
	array(
		'id'   => 99,
		'type' => array( 'table', 'chart' ),
	)
);
$assert( false !== strpos( $dup, 'ntc-empty' ), 'shortcode duplicated type attr does not fatal' );
$dup_table = $ntc->shortcode_dataset(
	array(
		'id'   => 99,
		'type' => 'table',
	)
);
$assert( false !== strpos( $dup_table, 'ntc-table-wrap' ), 'shortcode table type still renders table markup' );
$GLOBALS['wpdb']->queries = array();
$repo->upsert_rows( 1, array_fill( 0, 1001, array( 'x' ) ) );
$assert( 3 === count( $GLOBALS['wpdb']->queries ), 'upsert_rows batches 1001 rows into 3 statements' );
$assert( 500 === substr_count( $GLOBALS['wpdb']->queries[0], '(%d,%d,%s,%s)' ), 'first statement holds 500 rows' );
$assert( 1 === substr_count( $GLOBALS['wpdb']->queries[2], '(%d,%d,%s,%s)' ), 'last statement holds 1 row' );
$GLOBALS['wpdb']->queries = array();
$repo->patch_rows( 1, array_fill( 0, 1200, array( 'y' ) ) );
$assert( 3 === count( $GLOBALS['wpdb']->queries ), 'patch_rows batches 1200 rows into 3 statements' );
$assert( 200 === substr_count( $GLOBALS['wpdb']->queries[2], '(%d,%d,%s,%s)' ), 'patch last statement holds 200 rows' );
require_once dirname( __DIR__ ) . '/includes/class-ntc-migrator.php';
$migrator                   = new NTC_Migrator( $repo );
$call_m                     = function ( string $m, ...$a ) use ( $migrator ) {
	$mm = ( new ReflectionClass( 'NTC_Migrator' ) )->getMethod( $m );
	$mm->setAccessible( true );
	return $mm->invokeArgs( $migrator, $a );
};
$GLOBALS['wpdb']->queries   = array();
$GLOBALS['fake_post_count'] = 550;
$GLOBALS['fake_posts']      = array();
for ( $i = 0; $i < 200; $i++ ) {
	$GLOBALS['fake_posts'][] = array(
		'ID'           => $i + 1,
		'post_content' => '[lt id="1"] x',
	);
}
$r = $call_m( 'convert_posts', array( 1 => 5 ), 'batch-1', 0, 200 );
$assert( 200 === $r['processed'] && 550 === $r['total'], 'convert_posts processes a 200-post page and reports total' );
$assert( false !== strpos( implode( ' ', $GLOBALS['wpdb']->queries ), 'LIMIT 200 OFFSET 0' ), 'convert_posts pages with LIMIT/OFFSET' );
$GLOBALS['fake_backup_count'] = 450;
$GLOBALS['fake_backups']      = array();
for ( $i = 0; $i < 200; $i++ ) {
	$GLOBALS['fake_backups'][] = array(
		'post_id'          => $i + 1,
		'original_content' => 'old',
	);
}
$rb = $call_m( 'rollback', 'batch-1', 0, 200 );
$assert( 450 === $rb['total'] && 250 === $rb['remaining'] && 200 === $rb['processed'], 'rollback pages backups and reports remaining' );
echo $fails ? "FAILURES: $fails\n" : "ALL PASS\n";
exit( $fails ? 1 : 0 );
