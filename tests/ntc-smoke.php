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
$assert( NTC_Sync::MAX_BYTES + 1 === $GLOBALS['safe_remote_args']['limit_response_size'], 'fetch limits the remote response during download' );
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
$review_schema = $call(
	'schema_json',
	array( 'dataset_updated_at' => '2026-08-17 10:00:00' ),
	array(
		'schemaType'   => 'review',
		'title'        => 'Game X',
		'subtitle'     => 'A great game',
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
	),
	array(),
	array( array( 'Game X', '8.5' ) )
);
$assert( false !== strpos( $review_schema, '"@type":"Review"' ), 'review schema emits Review type' );
$assert( false !== strpos( $review_schema, '"ratingValue":8.5' ), 'review schema emits ratingValue 8.5' );
$xss_review = $call(
	'schema_json',
	array(),
	array(
		'schemaType'   => 'review',
		'title'        => '</script><script>alert(1)</script>',
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
	),
	array(),
	array( array( 'X', 5 ) )
);
$assert( false === strpos( $xss_review, '<script>alert' ), 'review schema escapes malicious title script tag' );
$clamp_schema = $call(
	'schema_json',
	array(),
	array(
		'schemaType'   => 'review',
		'title'        => 'Game X',
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'ratingMax'    => 10,
	),
	array(),
	array( array( 'X', '12.5' ) )
);
$assert( false !== strpos( $clamp_schema, '"ratingValue":10' ) && false !== strpos( $clamp_schema, '"bestRating":10' ), 'review schema clamps ratingValue to bestRating' );
$assert( '' === $call( 'schema_json', array(), array( 'schemaType' => 'off' ), array() ), 'schemaType off returns empty' );
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
$topn = $call(
	'prepare_chart_rows',
	array(
		array( 'A', 10 ),
		array( 'B', 20 ),
		array( 'C', 30 ),
		array( 'D', 40 ),
		array( 'E', 50 ),
	),
	array(),
	array(
		'topN'          => 3,
		'labelColumn'   => 0,
		'valueColumns'  => array( 1 ),
		'sortColumn'    => 1,
		'sortDirection' => 'asc',
	)
);
$assert( 4 === count( $topn ), 'topN rolls rest into Others row' );
$assert( 'Others' === $topn[3][0], 'Others row label' );
$assert( '90' === (string) $topn[3][1], 'Others row value sums rest' );
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
$assert( false !== strpos( $table_html, 'ntc-table-fits' ), 'simple tables suppress an unnecessary horizontal scrollbar' );
$assert( false !== strpos( $table_html, 'border:0px solid' ), 'table outer frame is disabled by default' );
$assert( false === strpos( $table_html, 'ntc-table-search' ), 'table search is hidden by default' );
$assert( false === strpos( $table_html, 'ntc-export-btn' ), 'table CSV download is hidden by default' );
$assert( false === strpos( $table_html, 'ntc-table-pager' ), 'table pagination is hidden by default' );
$controls_html = $call(
	'render_table',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'A',
				'type'  => 'auto',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'a' ) ),
		'config'  => array(
			'enableSearch'     => true,
			'enablePagination' => true,
			'enableExport'     => true,
		),
	)
);
$assert( false !== strpos( $controls_html, 'ntc-table-search' ), 'table search renders when enabled' );
$assert( false !== strpos( $controls_html, 'ntc-export-btn' ), 'table CSV download renders when enabled' );
$assert( false !== strpos( $controls_html, 'ntc-table-pager' ), 'table pagination renders when enabled' );
$framed_table = $call(
	'render_table',
	array(
		'columns' => array( array( 'id' => 'c1', 'label' => 'A', 'type' => 'text', 'unit' => '' ) ),
		'rows'    => array( array( 'a' ) ),
		'config'  => array( 'frameWidth' => 4, 'frameColor' => '#123456', 'frameRadius' => 8 ),
	)
);
$assert( false !== strpos( $framed_table, 'border:4px solid #123456' ) && false !== strpos( $framed_table, 'border-radius:8px' ), 'table outer frame renders only when configured' );
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
$assert( 3 === NTC_Migrator::MIGRATION_STATE_VERSION, 'migration rejects incompatible saved progress before continuing' );
$assert( 20 === NTC_Migrator::POST_BATCH_SIZE, 'migration post batches stay below proxy timeout thresholds' );
$call_m                     = function ( string $m, ...$a ) use ( $migrator ) {
	$mm = ( new ReflectionClass( 'NTC_Migrator' ) )->getMethod( $m );
	$mm->setAccessible( true );
	return $mm->invokeArgs( $migrator, $a );
};
$GLOBALS['fake_transients']['ntc_migration_targets_stale-batch'] = array( 'ids' => array( 1 ) );
$migrator->clear_migration_state( 'stale-batch' );
$assert( false === get_transient( 'ntc_migration_targets_stale-batch' ), 'migration reset clears an incompatible target snapshot' );
$GLOBALS['fake_dataset']                = array( 'id' => 42, 'columns_json' => '[]' );
$GLOBALS['fake_options']                = array( 'ntc_migration_map' => array( 1 => 42, 2 => 42 ) );
$GLOBALS['fake_transients']['ntc_migration_targets_batch-tables'] = array( 'ids' => array( 7, 9 ), 'instances' => 3 );
$GLOBALS['fake_legacy_tables']          = array( array( 'id' => 1 ), array( 'id' => 2 ) );
$GLOBALS['fake_legacy_table_remaining'] = 0;
$table_phase = $call_m( 'run_migration_batch', 'batch-tables', true, 0, 0, 20, 2 );
$assert( 'tables' === $table_phase['phase'] && empty( $table_phase['migration_complete'] ), 'migration keeps table import in a separate request phase' );
$assert( 2 === $table_phase['tables_processed'], 'migration table phase reports its bounded cursor progress' );
$assert( 0 === $table_phase['processed'] && 0 === $table_phase['posts_updated'], 'table phase does not update post content in the same request' );
$assert( 2 === $table_phase['posts_total'] && 3 === $table_phase['instances_total'], 'migration preserves identified post and shortcode counts during table import' );
unset( $GLOBALS['fake_dataset'], $GLOBALS['fake_options'], $GLOBALS['fake_legacy_tables'], $GLOBALS['fake_legacy_table_remaining'] );
unset( $GLOBALS['fake_transients']['ntc_migration_targets_batch-tables'] );
$GLOBALS['wpdb']->queries                 = array();
$GLOBALS['fake_dataset']                  = array( 'id' => 42, 'columns_json' => '[]' );
$GLOBALS['fake_legacy_table_remaining']   = 5;
$GLOBALS['fake_legacy_tables']            = array();
$legacy_map                               = array();
for ( $i = 1; $i <= 25; $i++ ) {
	$GLOBALS['fake_legacy_tables'][] = array( 'id' => $i );
	$legacy_map[ $i ]                = 42;
}
$table_batch = $call_m( 'migrate_tables', $legacy_map, 0, 20 );
$assert( 20 === $table_batch['processed'] && 20 === $table_batch['next_cursor'], 'legacy table import uses a bounded ID-cursor page' );
$assert( 5 === $table_batch['remaining'], 'legacy table import reports remaining tables' );
$assert( false !== strpos( implode( ' ', $GLOBALS['wpdb']->queries ), 'id > 0 ORDER BY id ASC LIMIT 20' ), 'legacy table import pages without OFFSET' );
unset( $GLOBALS['fake_dataset'], $GLOBALS['fake_legacy_tables'], $GLOBALS['fake_legacy_table_remaining'] );
$GLOBALS['wpdb']->queries = array();
$GLOBALS['fake_dataset']  = array( 'id' => 42, 'columns_json' => '[]' );
$GLOBALS['fake_view']     = array( 'id' => 99, 'config_json' => '{"cellMeta":[]}' );
$GLOBALS['fake_options']  = array( 'ntc_migration_map' => array() );
$GLOBALS['fake_legacy_table_remaining'] = 1;
$GLOBALS['fake_legacy_data'] = array();
for ( $i = 1; $i <= 250; $i++ ) {
	$GLOBALS['fake_legacy_data'][] = array( 'row_index' => $i, 'content' => wp_json_encode( array( 'Row ' . $i, $i ) ) );
}
$GLOBALS['fake_legacy_cells'] = array();
for ( $i = 1; $i <= 250; $i++ ) {
	$GLOBALS['fake_legacy_cells'][] = array( 'id' => $i, 'row_index' => $i, 'column_index' => 1, 'text_color' => '#111111' );
}
$GLOBALS['fake_transients']['ntc_migration_table_paged-table'] = array(
	'old_id'      => 1,
	'dataset_id'  => 42,
	'view_id'     => 99,
	'phase'       => 'rows',
	'row_cursor'  => 0,
	'row_offset'  => 0,
	'cell_offset' => 0,
);
$paged_rows_1 = $call_m( 'migrate_tables', array(), 0, 20, 'paged-table' );
$row_state_1  = get_transient( 'ntc_migration_table_paged-table' );
$assert( 0 === $paged_rows_1['processed'] && 'rows' === $paged_rows_1['table_stage'], 'large legacy tables keep row import resumable' );
$assert( 200 === $row_state_1['row_cursor'] && 200 === $row_state_1['row_offset'], 'legacy table row import advances by a bounded 200-row page' );
$assert( false !== strpos( implode( ' ', $GLOBALS['wpdb']->queries ), 'row_index > 0 ORDER BY row_index ASC LIMIT 200' ), 'legacy table row query has a strict page limit' );
$paged_rows_2 = $call_m( 'migrate_tables', array(), 0, 20, 'paged-table' );
$row_state_2  = get_transient( 'ntc_migration_table_paged-table' );
$assert( 250 === $row_state_2['row_cursor'] && 'cells' === $row_state_2['phase'], 'legacy table row import switches to cell properties after the final row page' );
$paged_cells_1 = $call_m( 'migrate_tables', array(), 0, 20, 'paged-table' );
$cell_state_1  = get_transient( 'ntc_migration_table_paged-table' );
$assert( 0 === $paged_cells_1['processed'] && 200 === $cell_state_1['cell_offset'], 'legacy cell properties advance by a bounded 200-cell page' );
$GLOBALS['fake_legacy_table_remaining'] = 0;
$paged_cells_2 = $call_m( 'migrate_tables', array(), 0, 20, 'paged-table' );
$assert( 1 === $paged_cells_2['processed'] && 42 === $paged_cells_2['map'][1], 'resumable legacy table import completes only after all row and cell pages' );
$assert( false === get_transient( 'ntc_migration_table_paged-table' ), 'completed legacy table import clears its resumable state' );
unset( $GLOBALS['fake_dataset'], $GLOBALS['fake_view'], $GLOBALS['fake_legacy_data'], $GLOBALS['fake_legacy_cells'], $GLOBALS['fake_legacy_table_remaining'] );
$GLOBALS['wpdb']->queries = array();
$GLOBALS['fake_posts']    = array();
for ( $i = 0; $i < 200; $i++ ) {
	$GLOBALS['fake_posts'][] = array(
		'ID'           => $i + 1,
		'post_content' => '[lt id="1"] x',
	);
}
$target_ids = range( 1, 50 );
$GLOBALS['wp_update_post_calls'] = 0;
$GLOBALS['cleaned_post_ids']     = array();
$r          = $call_m( 'convert_posts', array( 1 => 5 ), 'batch-1', 0, 20, $target_ids );
$queries    = implode( ' ', $GLOBALS['wpdb']->queries );
$assert( 20 === $r['processed'] && 50 === $r['total'], 'convert_posts processes only a bounded target-post page' );
$assert( 20 === $r['next_cursor'] && 30 === $r['remaining'] && 20 === $r['target_offset'], 'convert_posts returns resumable target progress' );
$assert( false !== strpos( $queries, 'ID IN (1,2,3,4,5' ), 'convert_posts queries the snapshotted target IDs directly' );
$assert( false === strpos( $queries, 'post_content LIKE' ) && false === strpos( $queries, 'ID >' ), 'convert_posts does not scan unrelated posts' );
$assert( 0 === $GLOBALS['wp_update_post_calls'], 'migration content updates bypass expensive save_post hooks' );
$assert( 20 === count( $GLOBALS['cleaned_post_ids'] ), 'migration content updates clear each changed post cache' );
$r2 = $call_m( 'convert_posts', array( 1 => 5 ), 'batch-1', 20, 20, $target_ids );
$assert( 20 === $r2['processed'] && 40 === $r2['next_cursor'] && 10 === $r2['remaining'] && 40 === $r2['target_offset'], 'convert_posts resumes at the next target ID batch' );
$GLOBALS['wpdb']->queries = array();
$GLOBALS['fake_posts']    = array( array( 'ID' => 1, 'post_content' => '[lt id="1"]' ) );
$r3 = $call_m( 'convert_posts', array( 1 => 5 ), 'batch-1', 0, 20, array( 1, 999 ) );
$assert( 2 === $r3['processed'] && 0 === $r3['remaining'] && 2 === $r3['target_offset'], 'convert_posts advances past a deleted target without scanning for replacements' );
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
$radar = $call(
	'chart_radar',
	array(
		array( '<b>Speed</b>', 80, 60 ),
		array( 'Power', 90, 70 ),
		array( 'Agility', 70, 90 ),
		array( 'Stamina', 85, 80 ),
	),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1, 2 ),
		'radarMax'     => 0,
		'title'        => 'Stats',
	)
);
$assert( 4 === substr_count( $radar, 'ntc-radar-ring' ), 'radar renders 4 rings' );
$assert( 4 === substr_count( $radar, 'ntc-radar-spoke' ), 'radar renders one spoke per row' );
$assert( 2 === substr_count( $radar, 'ntc-radar-series' ), 'radar renders one series polygon per value column' );
$assert( false !== strpos( $radar, '&lt;b&gt;Speed&lt;/b&gt;' ) && false === strpos( $radar, '<b>Speed</b>' ), 'radar escapes axis labels' );
$radar_two_rows = $call(
	'chart_radar',
	array( array( 'A', 1 ), array( 'B', 2 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
	)
);
$assert( false !== strpos( $radar_two_rows, 'ntc-chart-empty-note' ), 'radar shows empty note under 3 rows' );
$assert( 0 === NTC_Renderer::chart_defaults()['radarMax'], 'chart_defaults includes radarMax 0' );
$assert( 0 === NTC_Renderer::chart_defaults()['gaugeMin'] && 100 === NTC_Renderer::chart_defaults()['gaugeMax'], 'chart_defaults includes gaugeMin 0 and gaugeMax 100' );
$assert( array() === NTC_Renderer::chart_defaults()['seriesRules'], 'chart_defaults includes seriesRules' );
$assert( false === NTC_Renderer::chart_defaults()['legendToggles'], 'chart_defaults includes legendToggles false' );
$line_series = $call(
	'chart_line',
	array( array( 'A', 5 ), array( 'B', 10 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'xColumn'      => null,
	),
	false,
	false
);
$assert( false !== strpos( $line_series, 'ntc-svg-line ntc-series-0' ), 'chart_line polyline carries series class' );
$assert( false === NTC_Renderer::chart_defaults()['enableBrush'], 'chart_defaults includes enableBrush false' );
$line_brush = $call(
	'chart_line',
	array( array( 'A', 5 ), array( 'B', 10 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'xColumn'      => null,
		'enableBrush'  => true,
	),
	false,
	false
);
$assert( false !== strpos( $line_brush, 'data-pad-l="70"' ) && false !== strpos( $line_brush, 'data-w="1000"' ) && false !== strpos( $line_brush, 'data-h="440"' ), 'chart_line brush svg carries pad and size data attributes' );
$assert( false !== strpos( $line_brush, 'data-series="0"' ) && false !== strpos( $line_brush, 'data-points="[[0,5],[1,10]]"' ), 'chart_line brush polyline carries series and raw point data' );
$assert( false !== strpos( $line_brush, 'data-x="0"' ) && false !== strpos( $line_brush, 'data-v="5"' ), 'chart_line brush circles carry data-x and data-v' );
preg_match( '/data-points="([^"]*)"/', $line_brush, $m_points );
$assert( isset( $m_points[1] ) && is_array( json_decode( $m_points[1], true ) ), 'chart_line brush data-points is valid JSON' );
$line_empty_xcol = $call(
	'chart_line',
	array(),
	array( 0 => array( 'type' => 'number' ) ),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'xColumn'      => 0,
	),
	false,
	false
);
$assert( false !== strpos( $line_empty_xcol, '<svg' ), 'chart_line empty rows with xColumn does not fatal' );
$tip_prefix = $call(
	'chart_line',
	array( array( 'A', 5 ), array( 'B', 10 ) ),
	array(
		1 => array(
			'label' => 'Score',
			'type'  => 'number',
			'unit'  => 'fps',
		),
	),
	array(
		'labelColumn'    => 0,
		'valueColumns'   => array( 1 ),
		'xColumn'        => null,
		'enableTooltips' => true,
	),
	false,
	false
);
$assert( false !== strpos( $tip_prefix, 'data-tip="Score, A: 5 fps"' ), 'chart_line tooltip carries series column label prefix' );
$grouped_stacked = $call(
	'chart_grouped',
	array( array( 'A', '30', '70' ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1, 2 ),
	),
	true
);
$assert( false !== strpos( $grouped_stacked, 'data-series="0"' ), 'chart_grouped stacked segments carry data-series attributes' );
$dumbbell_units = $call(
	'chart_dumbbell',
	array( array( 'A', '10', '20' ) ),
	array(
		1 => array(
			'label' => 'X',
			'unit'  => 'u1',
		),
		2 => array(
			'label' => 'Y',
			'unit'  => 'u2',
		),
	),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1, 2 ),
	)
);
$assert( false !== strpos( $dumbbell_units, '10 u1' ) && false !== strpos( $dumbbell_units, '20 u2' ), 'dumbbell lo/hi labels use their source column units' );
$scatter_brush = $call(
	'chart_line',
	array( array( 'A', 5 ), array( 'B', 10 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'xColumn'      => null,
		'enableBrush'  => true,
	),
	true,
	false
);
$assert( false === strpos( $scatter_brush, 'data-pad-l' ), 'chart_line scatter ignores enableBrush' );
$assert( array() === NTC_Renderer::chart_defaults()['annotations'], 'chart_defaults includes annotations' );
$line_annotations = $call(
	'chart_line',
	array( array( 'A', 5, 0 ), array( 'B', 10, 1 ), array( 'C', 7, 2 ) ),
	array( 2 => array( 'type' => 'number' ) ),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'xColumn'      => 2,
		'annotations'  => array(
			array(
				'type'  => 'marker',
				'at'    => 'B',
				'label' => 'Peak',
			),
			array(
				'type'  => 'region',
				'from'  => 'A',
				'to'    => 'C',
				'label' => 'Span',
			),
			array(
				'type'  => 'marker',
				'at'    => 'Z',
				'label' => 'Nope',
			),
		),
	),
	false,
	false
);
$assert( false !== strpos( $line_annotations, 'ntc-svg-annotation' ), 'chart_line renders annotation marker at label' );
$assert( false !== strpos( $line_annotations, 'ntc-svg-region' ), 'chart_line renders annotation region A to C' );
$assert( false === strpos( $line_annotations, 'Nope' ), 'chart_line skips unknown annotation labels' );
$scatter_annotations = $call(
	'chart_line',
	array( array( 'A', 5, 0 ), array( 'B', 10, 1 ), array( 'C', 7, 2 ) ),
	array( 2 => array( 'type' => 'number' ) ),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'xColumn'      => 2,
		'annotations'  => array(
			array(
				'type' => 'marker',
				'at'   => 'B',
			),
		),
	),
	true,
	false
);
$assert( false === strpos( $scatter_annotations, 'ntc-svg-annotation' ), 'chart_line scatter renders no annotations' );
$donut_series = $call(
	'chart_donut',
	array( array( 'A', 30 ), array( 'B', 70 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'showValues'   => false,
	)
);
$assert( false !== strpos( $donut_series, 'ntc-donut-seg ntc-series-0' ), 'chart_donut segment carries series class' );
$series_cfg = array(
	'seriesRules' => array(
		array(
			'column' => 1,
			'ranges' => array(
				array(
					'min'   => 50,
					'max'   => 100,
					'color' => '#00ff00',
				),
			),
		),
	),
);
$assert( '#00ff00' === $call( 'series_color', '75', 1, $series_cfg, '#fff' ), 'series_color matches range to color' );
$assert( '#fff' === $call( 'series_color', 10, 1, $series_cfg, '#fff' ), 'series_color falls back outside range' );
$empty_color_cfg = array(
	'seriesRules' => array(
		array(
			'column' => 1,
			'ranges' => array(
				array(
					'min'   => 50,
					'max'   => 100,
					'color' => '',
				),
			),
		),
	),
);
$assert( '#fff' === $call( 'series_color', '75', 1, $empty_color_cfg, '#fff' ), 'series_color empty color falls back' );
$assert( '#fff' === $call( 'series_color', '75', 2, $series_cfg, '#fff' ), 'series_color non-matching column falls back' );
$hbar_series = $call(
	'chart_horizontal',
	array( array( 'A', 75 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'showValues'   => true,
		'seriesRules'  => array(
			array(
				'column' => 1,
				'ranges' => array(
					array(
						'min'   => 50,
						'max'   => 100,
						'color' => '#00ff00',
					),
				),
			),
		),
	)
);
$assert( false !== strpos( $hbar_series, 'background:#00ff00' ), 'chart_horizontal fill uses series rule color' );
$hbar_highlight = $call(
	'chart_horizontal',
	array( array( 'A', 75 ) ),
	array(),
	array(
		'labelColumn'     => 0,
		'valueColumns'    => array( 1 ),
		'showValues'      => true,
		'highlightValues' => array( 'A' ),
		'seriesRules'     => array(
			array(
				'column' => 1,
				'ranges' => array(
					array(
						'min'   => 50,
						'max'   => 100,
						'color' => '#00ff00',
					),
				),
			),
		),
	)
);
$assert( false === strpos( $hbar_highlight, 'background:#00ff00' ), 'highlighted row keeps class highlight color' );
$gauge = $call(
	'chart_gauge',
	array( array( 'Score', 50 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
		'gaugeMin'     => 0,
		'gaugeMax'     => 100,
		'title'        => 'Score gauge',
	)
);
$assert( false !== strpos( $gauge, 'ntc-gauge-value' ) && false !== strpos( $gauge, '>50</text>' ), 'gauge renders value arc and 50' );
$assert( false !== strpos( $gauge, 'viewBox="0 0 320 280"' ), 'gauge viewBox fits full arc' );
$assert( false !== strpos( $gauge, 'Score' ) && false !== strpos( $gauge, 'Score gauge' ), 'gauge renders label and aria title' );
$gauge_band = $call(
	'chart_gauge',
	array( array( 'Score', 50 ) ),
	array(),
	array(
		'labelColumn'    => 0,
		'valueColumns'   => array( 1 ),
		'referenceLines' => array(
			array(
				'value' => 75,
				'color' => '#ef4444',
			),
		),
	)
);
$assert( false !== strpos( $gauge_band, 'ntc-gauge-band' ) && false !== strpos( $gauge_band, '#ef4444' ), 'gauge renders reference band' );
$gauge_at_max   = $call(
	'chart_gauge',
	array( array( 'Score', 100 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
	)
);
$gauge_over_max = $call(
	'chart_gauge',
	array( array( 'Score', 150 ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
	)
);
preg_match( '/d="([^"]+)" class="ntc-gauge-value"/', $gauge_at_max, $m_max );
preg_match( '/d="([^"]+)" class="ntc-gauge-value"/', $gauge_over_max, $m_over );
$assert( isset( $m_max[1], $m_over[1] ) && $m_max[1] === $m_over[1], 'gauge value clamps at max' );
$gauge_chart = $call(
	'render_chart',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'Score',
				'type'  => 'number',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'Score', 50 ) ),
		'config'  => array(
			'chartType'    => 'gauge',
			'labelColumn'  => 0,
			'valueColumns' => array( 1 ),
		),
	)
);
$assert( false !== strpos( $gauge_chart, 'ntc-gauge' ), 'render_chart dispatches gauge type' );
$change = $call(
	'chart_change',
	array( array( 'A', '120', '100' ), array( 'B', '90', '100' ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1, 2 ),
	)
);
$assert( false !== strpos( $change, 'ntc-change-bar is-up' ), 'change bars render up delta' );
$assert( false !== strpos( $change, 'ntc-change-bar is-down' ), 'change bars render down delta' );
$flat_change = $call(
	'chart_change',
	array( array( 'C', '50', '50' ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1, 2 ),
	)
);
$assert( false !== strpos( $flat_change, 'ntc-change-value is-flat' ), 'change bars render flat state for zero delta' );
$assert( false === strpos( $flat_change, 'is-up' ), 'zero delta change bar has no up state' );
$dumbbell = $call(
	'chart_dumbbell',
	array( array( 'A', '10', '30' ), array( 'B', '5', '8' ) ),
	array(),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1, 2 ),
	)
);
$assert( 4 === substr_count( $dumbbell, 'ntc-dumbbell-dot' ), 'dumbbell renders 2 dots per row' );
$assert( false !== strpos( $dumbbell, '10 – 30' ) && false !== strpos( $dumbbell, '5 – 8' ), 'dumbbell renders value labels' );
$change_chart = $call(
	'render_chart',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'A',
				'type'  => 'text',
				'unit'  => '',
			),
			array(
				'id'    => 'c2',
				'label' => 'Now',
				'type'  => 'number',
				'unit'  => '',
			),
			array(
				'id'    => 'c3',
				'label' => 'Prev',
				'type'  => 'number',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'A', 120, 100 ), array( 'B', 90, 100 ) ),
		'config'  => array(
			'chartType'    => 'change',
			'labelColumn'  => 0,
			'valueColumns' => array( 1, 2 ),
		),
	)
);
$assert( false !== strpos( $change_chart, 'ntc-change' ), 'render_chart dispatches change type' );
$dumbbell_chart = $call(
	'render_chart',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'A',
				'type'  => 'text',
				'unit'  => '',
			),
			array(
				'id'    => 'c2',
				'label' => 'Now',
				'type'  => 'number',
				'unit'  => '',
			),
			array(
				'id'    => 'c3',
				'label' => 'Prev',
				'type'  => 'number',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'A', 10, 30 ), array( 'B', 5, 8 ) ),
		'config'  => array(
			'chartType'    => 'dumbbell',
			'labelColumn'  => 0,
			'valueColumns' => array( 1, 2 ),
		),
	)
);
$assert( false !== strpos( $dumbbell_chart, 'ntc-dumbbells' ), 'render_chart dispatches dumbbell type' );
$assert( 3 === NTC_Renderer::chart_defaults()['multiplesPerRow'], 'chart_defaults includes multiplesPerRow 3' );
$mini = $call(
	'chart_small_multiples',
	array( array( 'A', '10 20 30' ), array( 'B', '5,8' ), array( 'C', 'n/a' ) ),
	array(),
	array(
		'labelColumn'     => 0,
		'valueColumns'    => array( 1 ),
		'highlightValues' => array( 'B' ),
	)
);
$assert( 3 === substr_count( $mini, 'ntc-mini-cell' ), 'small multiples renders one cell per row' );
$assert( 2 === substr_count( $mini, '<polyline' ), 'small multiples renders polyline for rows with 2+ numeric values' );
$assert( false !== strpos( $mini, 'class="ntc-mini-svg"></svg>' ), 'small multiples renders empty svg for row under 2 values' );
$assert( false !== strpos( $mini, 'ntc-mini-cell is-highlight' ), 'small multiples highlights matching row' );
$assert( false !== strpos( $mini, '--ntc-mini-per:3' ), 'small multiples defaults to 3 per row' );
$mini_chart = $call(
	'render_chart',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'A',
				'type'  => 'text',
				'unit'  => '',
			),
			array(
				'id'    => 'c2',
				'label' => 'Values',
				'type'  => 'text',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'A', '10 20 30' ), array( 'B', '5,8' ) ),
		'config'  => array(
			'chartType'    => 'small-multiples',
			'labelColumn'  => 0,
			'valueColumns' => array( 1 ),
		),
	)
);
$assert( false !== strpos( $mini_chart, 'ntc-mini-grid' ), 'render_chart dispatches small-multiples type' );
$hm_defaults = NTC_Renderer::chart_defaults();
$assert( '#ffffff' === $hm_defaults['heatmapLow'] && '#624b8e' === $hm_defaults['heatmapHigh'] && true === $hm_defaults['heatmapLabels'], 'chart_defaults includes heatmap colors and labels' );
$heatmap = $call(
	'chart_heatmap',
	array(
		array( 'A', 0, 100 ),
		array( 'B', 50, 25 ),
	),
	array(
		array(
			'id'    => 'c1',
			'label' => 'Item',
			'type'  => 'text',
			'unit'  => '',
		),
		array(
			'id'    => 'c2',
			'label' => 'Q1',
			'type'  => 'number',
			'unit'  => '',
		),
		array(
			'id'    => 'c3',
			'label' => 'Q2',
			'type'  => 'number',
			'unit'  => '',
		),
	),
	array(
		'labelColumn'   => 0,
		'valueColumns'  => array( 1, 2 ),
		'heatmapLow'    => '#ffffff',
		'heatmapHigh'   => '#624b8e',
		'heatmapLabels' => true,
	)
);
$assert( false !== strpos( $heatmap, '<td style="background:#ffffff">0</td>' ), 'heatmap low cell uses low color with label' );
$assert( false !== strpos( $heatmap, '<td style="background:#624b8e">100</td>' ), 'heatmap high cell uses high color with label' );
$assert( false !== strpos( $heatmap, 'background:#b1a5c7' ), 'heatmap mid cell lerps between colors' );
$hm_no_labels = $call(
	'chart_heatmap',
	array( array( 'A', 0, 100 ) ),
	array(
		array(
			'id'    => 'c1',
			'label' => 'Item',
			'type'  => 'text',
			'unit'  => '',
		),
		array(
			'id'    => 'c2',
			'label' => 'Q1',
			'type'  => 'number',
			'unit'  => '',
		),
		array(
			'id'    => 'c3',
			'label' => 'Q2',
			'type'  => 'number',
			'unit'  => '',
		),
	),
	array(
		'labelColumn'   => 0,
		'valueColumns'  => array( 1, 2 ),
		'heatmapLabels' => false,
	)
);
$assert( false !== strpos( $hm_no_labels, '<td style="background:#ffffff"></td>' ) && false === strpos( $hm_no_labels, '>100</td>' ), 'heatmap labels absent when disabled' );
$hm_escaped = $call(
	'chart_heatmap',
	array( array( '<b>X</b>', 1 ) ),
	array(
		array(
			'id'    => 'c1',
			'label' => '<i>Item</i>',
			'type'  => 'text',
			'unit'  => '',
		),
		array(
			'id'    => 'c2',
			'label' => '<i>Val</i>',
			'type'  => 'number',
			'unit'  => '',
		),
	),
	array(
		'labelColumn'  => 0,
		'valueColumns' => array( 1 ),
	)
);
$assert( false !== strpos( $hm_escaped, '&lt;b&gt;X&lt;/b&gt;' ) && false !== strpos( $hm_escaped, '&lt;i&gt;Item&lt;/i&gt;' ) && false !== strpos( $hm_escaped, '&lt;i&gt;Val&lt;/i&gt;' ), 'heatmap escapes row and column headers' );
$heatmap_chart = $call(
	'render_chart',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'Item',
				'type'  => 'text',
				'unit'  => '',
			),
			array(
				'id'    => 'c2',
				'label' => 'Q1',
				'type'  => 'number',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'A', 0 ), array( 'B', 100 ) ),
		'config'  => array(
			'chartType'    => 'heatmap',
			'labelColumn'  => 0,
			'valueColumns' => array( 1 ),
		),
	)
);
$assert( false !== strpos( $heatmap_chart, 'ntc-heatmap' ), 'render_chart dispatches heatmap type' );
$assert( 24 === count( NTC_Advanced_Charts::types() ), 'advanced chart suite exposes 24 specialized types' );
$release_presets = NTC_Renderer::chart_presets();
foreach ( array( 'editorial-light', 'dashboard', 'accessible', 'print-grayscale', 'financial', 'scientific', 'soft-neutral', 'high-impact-dark', 'brand-inherit', 'compact-mobile' ) as $release_preset ) {
	$assert( isset( $release_presets[ $release_preset ] ), 'chart preset exists: ' . $release_preset );
}
$advanced_columns = array(
	array( 'id' => 'label', 'label' => 'Label', 'type' => 'text', 'unit' => '' ),
	array( 'id' => 'one', 'label' => 'One', 'type' => 'number', 'unit' => '' ),
	array( 'id' => 'two', 'label' => 'Two', 'type' => 'number', 'unit' => '' ),
	array( 'id' => 'three', 'label' => 'Three', 'type' => 'number', 'unit' => '' ),
	array( 'id' => 'four', 'label' => 'Four', 'type' => 'number', 'unit' => '' ),
	array( 'id' => 'five', 'label' => 'Five', 'type' => 'number', 'unit' => '' ),
);
$advanced_rows = array(
	array( 'A', 10, 20, 30, 40, 50 ),
	array( 'B', 20, 35, 45, 55, 65 ),
	array( 'C', 15, 25, 38, 48, 70 ),
	array( 'D', 30, 42, 52, 66, 80 ),
);
foreach ( NTC_Advanced_Charts::types() as $advanced_type ) {
	$advanced_html = $call(
		'render_chart',
		array(
			'columns' => $advanced_columns,
			'rows'    => $advanced_rows,
			'config'  => array(
				'chartType'     => $advanced_type,
				'labelColumn'   => 0,
				'xColumn'       => 1,
				'valueColumns'  => array( 1, 2, 3, 4, 5 ),
				'sortDirection' => 'none',
			),
		)
	);
	$assert( false !== strpos( $advanced_html, 'data-chart-type="' . $advanced_type . '"' ), 'render_chart dispatches ' . $advanced_type );
	$assert( '' !== trim( NTC_Advanced_Charts::render( $advanced_type, $advanced_rows, $advanced_columns, array( 'labelColumn' => 0, 'xColumn' => 1, 'valueColumns' => array( 1, 2, 3, 4, 5 ) ) ) ), 'advanced renderer produces output: ' . $advanced_type );
}
$area_chart = $call(
	'render_chart',
	array(
		'columns' => array(
			array(
				'id'    => 'c1',
				'label' => 'Item',
				'type'  => 'text',
				'unit'  => '',
			),
			array(
				'id'    => 'c2',
				'label' => 'Value',
				'type'  => 'number',
				'unit'  => '',
			),
		),
		'rows'    => array( array( 'A', 10 ), array( 'B', 20 ) ),
		'config'  => array(
			'chartType'    => 'area',
			'labelColumn'  => 0,
			'valueColumns' => array( 1 ),
			'enableExport' => true,
		),
	)
);
$assert( false !== strpos( $area_chart, 'data-format="png"' ), 'area chart type includes PNG export button' );
$GLOBALS['fake_wp_posts'] = array(
	new WP_Post( 1, 'Post A' ),
	new WP_Post( 2, 'Post B' ),
);
$GLOBALS['fake_meta']     = array(
	1 => array(
		'score_label' => array( 'Alpha' ),
		'score'       => array( '10' ),
	),
	2 => array(
		'score_label' => array( 'Beta' ),
		'score'       => array( '20' ),
	),
);
$post_rows                = NTC_Posts_Query::rows_for(
	array(
		'meta_label'     => 'score_label',
		'meta_value'     => array( 'score' ),
		'posts_per_page' => 5,
	)
);
$assert( array( array( 'Alpha', '10' ), array( 'Beta', '20' ) ) === $post_rows, 'posts query maps meta label and value' );
$title_rows = NTC_Posts_Query::rows_for(
	array(
		'meta_value'     => array( 'score' ),
		'posts_per_page' => 5,
	)
);
$assert( array( array( 'Post A', '10' ), array( 'Post B', '20' ) ) === $title_rows, 'posts query falls back to post title' );
$assert( $repo->set_post_source( 1, 'posts', array( 'post_type' => 'post' ) ), 'set_post_source returns true' );
$assert( 'posts' === $GLOBALS['last_update'][0]['source_mode'], 'set_post_source stores mode posts' );
$assert( '{"post_type":"post"}' === $GLOBALS['last_update'][0]['source_config'], 'set_post_source stores json config' );
$repo->set_post_source( 1, '', array() );
$assert( '' === $GLOBALS['last_update'][0]['source_mode'], 'set_post_source empty mode clears source' );
$assert( null === $GLOBALS['last_update'][0]['source_config'], 'set_post_source empty mode nulls config' );
$GLOBALS['fake_dataset']     = array(
	'id'           => 99,
	'name'         => 'Post Scores',
	'columns_json' => wp_json_encode(
		array(
			array(
				'id'    => 'c1',
				'label' => 'Label',
				'type'  => 'text',
				'unit'  => '',
			),
			array(
				'id'    => 'c2',
				'label' => 'Value',
				'type'  => 'number',
				'unit'  => '',
			),
		)
	),
	'updated_at'   => '2026-08-17 10:00:00',
);
$GLOBALS['fake_post_source'] = array(
	'source_mode'   => 'posts',
	'source_config' => '{"meta_label":"score_label","meta_value":["score"],"posts_per_page":5}',
);
$GLOBALS['asked_options']    = array();
$post_driven                 = $ntc->shortcode_dataset(
	array(
		'id'   => 99,
		'type' => 'table',
	)
);
$assert( false !== strpos( $post_driven, 'Alpha' ) && false !== strpos( $post_driven, 'Beta' ), 'resolve post source renders post-driven rows' );
$assert( in_array( 'ntc_post_source_version', $GLOBALS['asked_options'], true ), 'resolve uses version-stamped cache option' );
$assert( isset( $GLOBALS['fake_transients']['ntc_post_source_99_0'] ) && array( array( 'Alpha', '10' ), array( 'Beta', '20' ) ) === $GLOBALS['fake_transients']['ntc_post_source_99_0'], 'resolve caches post rows under stamped key' );
unset( $GLOBALS['fake_dataset'], $GLOBALS['fake_post_source'] );
$ntc->invalidate_usage_cache();
$assert( 'ntc_post_source_version' === $GLOBALS['last_option'][0] && 1 === $GLOBALS['last_option'][1], 'invalidate_usage_cache bumps version option' );
$reg = new ReflectionMethod( 'NTC_Plugin', 'register_assets_and_blocks' );
$reg->invoke( $ntc );
$assert( isset( $GLOBALS['pattern_slug'] ) && 'ntc/review-card' === $GLOBALS['pattern_slug'], 'review-card pattern registered via register_assets_and_blocks' );
echo $fails ? "FAILURES: $fails\n" : "ALL PASS\n";
exit( $fails ? 1 : 0 );
