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
