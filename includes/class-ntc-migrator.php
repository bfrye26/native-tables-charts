<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Migrator {
	public const MIGRATION_STATE_VERSION = 3;
	public const POST_BATCH_SIZE         = 20;
	private const TABLE_ROW_BATCH_SIZE   = 200;
	private const TABLE_CELL_BATCH_SIZE  = 200;
	private const POST_TIME_BUDGET_SECS  = 12.0;

	private NTC_Repository $repo;
	public function __construct( NTC_Repository $repo ) {
		$this->repo = $repo;}
	public function clear_migration_state( string $batch_id ): void {
		if ( '' !== $batch_id ) {
			$table_state = get_transient( $this->migration_table_key( $batch_id ) );
			if ( is_array( $table_state ) && ! empty( $table_state['dataset_id'] ) ) {
				$map = (array) get_option( 'ntc_migration_map', array() );
				$old = (int) ( $table_state['old_id'] ?? 0 );
				if ( (int) ( $map[ $old ] ?? 0 ) !== (int) $table_state['dataset_id'] ) {
					$this->repo->delete_dataset( (int) $table_state['dataset_id'] );
					delete_option( 'ntc_lt_view_' . $old );
				}
			}
			delete_transient( $this->migration_target_key( $batch_id ) );
			delete_transient( $this->migration_table_key( $batch_id ) );
		}
	}

	public function detect( bool $include_instances = true, bool $include_posts = true ): array {
		global $wpdb;
		$tables = array(
			'table' => $wpdb->prefix . 'dalt_table',
			'data'  => $wpdb->prefix . 'dalt_data',
			'cell'  => $wpdb->prefix . 'dalt_cell',
		);
		$exists = array();
		foreach ( $tables as $k => $name ) {
			$exists[ $k ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) === $name;}
		$count          = $exists['table'] ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['table']} WHERE temporary=0" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
		$post_count     = 0;
		$instance_count = 0;
		$post_ids       = array();
		if ( $count && $include_posts ) {
			$where = "post_status NOT IN ('trash','auto-draft') AND (post_content LIKE '%[lt %' OR post_content LIKE '%wp:dalt/table%')";
			if ( $include_instances ) {
				$posts = $wpdb->get_results( "SELECT ID,post_content FROM {$wpdb->posts} WHERE {$where}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- $where is a fixed internal fragment and the table name cannot be prepared.
				if ( ! $posts ) {
					$posts = array();
				}
				$post_count = count( $posts );
				foreach ( $posts as $p ) {
					$post_ids[] = (int) $p['ID'];
					preg_match_all( '/\[lt\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i', $p['post_content'], $m );
					$instance_count += count( $m[0] );
					$instance_count += substr_count( $p['post_content'], 'wp:dalt/table' );}
			} else {
				$post_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL -- $where is a fixed internal fragment and the table name cannot be prepared.
			}
		}
		return array(
			'available' => $count > 0,
			'tables'    => $count,
			'posts'     => $post_count,
			'instances' => $instance_count,
			'post_ids'  => $post_ids,
			'schema'    => $exists,
		);
	}

	public function dry_run(): array {
		global $wpdb;
		$d      = $this->detect();
		unset( $d['post_ids'] );
		$report = array_merge(
			$d,
			array(
				'formula_tables' => 0,
				'merged_tables'  => 0,
				'html_cells'     => 0,
				'image_cells'    => 0,
				'link_cells'     => 0,
				'warnings'       => array(),
			)
		);
		if ( ! $d['available'] ) {
			return $report;
		}
		$cells                    = $wpdb->prefix . 'dalt_cell';
		$report['formula_tables'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT table_id) FROM {$cells} WHERE formula_data<>''" ); // phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
		$report['merged_tables']  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT table_id) FROM {$cells} WHERE row_slots>1 OR column_slots>1" ); // phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
		$report['html_cells']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cells} WHERE html_content<>''" ); // phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
		$report['image_cells']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cells} WHERE image_left<>'' OR image_right<>''" ); // phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
		$report['link_cells']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cells} WHERE link<>''" ); // phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
		return $report;
	}

	public function migrate( bool $convert_content = true, int $cursor = 0, int $chunk = self::POST_BATCH_SIZE, array $target_ids = array(), int $target_instances = 0 ): array {
		if ( ! current_user_can( 'ntc_migrate' ) && ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Permission denied.', 'native-tables-charts' ),
			);
		}
		$detect = $this->detect( false, false );
		if ( ! $detect['available'] ) {
			return array(
				'success' => false,
				'error'   => __( 'League Table database tables were not found.', 'native-tables-charts' ),
			);
		}
		// Preserve League Table's configurable HTML allow-list when Native Tables & Charts has not been configured yet.
		// Custom HTML remains sanitized in this plugin even if League Table had its unsafe KSES bypass disabled.
		if ( '' === (string) get_option( 'ntc_kses_allowed_html_tags', '' ) ) {
			$legacy_tags = (string) get_option( 'dalt_kses_allowed_html_tags', '' );
			if ( '' !== trim( $legacy_tags ) ) {
				update_option( 'ntc_kses_allowed_html_tags', sanitize_text_field( $legacy_tags ), false );
			}
		}
		if ( '' === (string) get_option( 'ntc_kses_allowed_protocols', '' ) ) {
			$legacy_protocols = (string) get_option( 'dalt_kses_allowed_protocols', '' );
			if ( '' !== trim( $legacy_protocols ) ) {
				update_option( 'ntc_kses_allowed_protocols', sanitize_text_field( $legacy_protocols ), false );
			}
		}
		if ( null === get_option( 'ntc_cell_features', null ) ) {
			$legacy_feature_map   = array(
				'textColor'        => 'dalt_enable_text_color_cell_property',
				'backgroundColor'  => 'dalt_enable_background_color_cell_property',
				'alignment'        => 'dalt_enable_alignment_cell_property',
				'fontWeight'       => 'dalt_enable_font_weight_cell_property',
				'fontStyle'        => 'dalt_enable_font_style_cell_property',
				'link'             => 'dalt_enable_link_cell_property',
				'linkColor'        => 'dalt_enable_link_color_cell_property',
				'openLinkNewTab'   => 'dalt_enable_open_link_new_tab_cell_property',
				'imageLeft'        => 'dalt_enable_image_left_cell_property',
				'imageLeftLink'    => 'dalt_enable_image_left_link_cell_property',
				'imageLeftNewTab'  => 'dalt_enable_image_left_open_link_new_tab_cell_property',
				'imageRight'       => 'dalt_enable_image_right_cell_property',
				'imageRightLink'   => 'dalt_enable_image_right_link_cell_property',
				'imageRightNewTab' => 'dalt_enable_image_right_open_link_new_tab_cell_property',
				'formula'          => 'dalt_enable_formula_cell_property',
				'formulaData'      => 'dalt_enable_formula_data_cell_property',
				'html'             => 'dalt_enable_html_content_cell_property',
				'rowSpan'          => 'dalt_enable_row_slots_cell_property',
				'columnSpan'       => 'dalt_enable_column_slots_cell_property',
			);
			$legacy_features      = array();
			$legacy_feature_found = false;
			foreach ( $legacy_feature_map as $native => $legacy ) {
				$raw = get_option( $legacy, null );
				if ( null !== $raw ) {
					$legacy_feature_found       = true;
					$legacy_features[ $native ] = 1 === (int) $raw;}
			}
			if ( $legacy_feature_found ) {
				update_option( 'ntc_cell_features', array_merge( NTC_Renderer::cell_feature_defaults(), $legacy_features ), false );
			}
		}
		$batch = 'lt-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
		if ( $convert_content ) {
			if ( $target_ids ) {
				$target_ids = $this->normalize_target_ids( $target_ids );
			} else {
				$target_detect    = $this->detect( true, true );
				$target_ids       = (array) ( $target_detect['post_ids'] ?? array() );
				$target_instances = (int) ( $target_detect['instances'] ?? 0 );
			}
			set_transient( $this->migration_target_key( $batch ), array( 'ids' => $target_ids, 'instances' => max( 0, $target_instances ) ), DAY_IN_SECONDS );
		}
		return $this->run_migration_batch( $batch, $convert_content, 0, $cursor, $chunk, (int) $detect['tables'], 0 );
	}

	public function continue_migration( string $batch_id, int $cursor, int $chunk = self::POST_BATCH_SIZE, int $table_cursor = 0, bool $convert_content = true, int $target_offset = 0 ): array {
		if ( ! current_user_can( 'ntc_migrate' ) && ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Permission denied.', 'native-tables-charts' ),
			);
		}
		$target_state = $convert_content ? get_transient( $this->migration_target_key( $batch_id ) ) : false;
		$targets_refreshed = false;
		if ( $convert_content && ! is_array( $target_state ) ) {
			$detected = $this->detect( true, true );
			set_transient( $this->migration_target_key( $batch_id ), array( 'ids' => (array) ( $detected['post_ids'] ?? array() ), 'instances' => (int) ( $detected['instances'] ?? 0 ) ), DAY_IN_SECONDS );
			$target_offset = 0;
			$targets_refreshed = true;
		}
		$result                      = $this->run_migration_batch( $batch_id, $convert_content, $table_cursor, $cursor, $chunk, 0, $target_offset );
		$result['targets_refreshed'] = $targets_refreshed;
		return $result;
	}

	private function run_migration_batch( string $batch_id, bool $convert_content, int $table_cursor, int $post_cursor, int $post_chunk, int $table_total = 0, int $target_offset = 0 ): array {
		$map          = (array) get_option( 'ntc_migration_map', array() );
		$target_state = $convert_content ? get_transient( $this->migration_target_key( $batch_id ) ) : false;
		$targets      = is_array( $target_state ) ? $this->normalize_target_ids( (array) ( $target_state['ids'] ?? array() ) ) : array();
		$tables = $this->migrate_tables( $map, $table_cursor, 20, $batch_id );
		$map    = $tables['map'];
		update_option( 'ntc_migration_map', $map, false );
		$base = array(
			'success'             => empty( $tables['errors'] ),
			'datasets_created'    => $tables['created'],
			'posts_updated'       => 0,
			'instances_converted' => 0,
			'batch_id'            => $batch_id,
			'errors'              => $tables['errors'],
			'map'                 => $map,
			'posts_total'         => count( $targets ),
			'instances_total'     => is_array( $target_state ) ? (int) ( $target_state['instances'] ?? 0 ) : 0,
			'processed'           => 0,
			'posts_remaining'     => max( 0, count( $targets ) - $target_offset ),
			'next_cursor'         => max( 0, $post_cursor ),
			'target_offset'       => max( 0, $target_offset ),
			'tables_total'        => $table_total > 0 ? $table_total : $tables['processed'] + $tables['remaining'],
			'tables_processed'    => $tables['processed'],
			'tables_remaining'    => $tables['remaining'],
			'next_table_cursor'   => $tables['next_cursor'],
			'table_stage'         => (string) ( $tables['table_stage'] ?? '' ),
			'current_table'       => (int) ( $tables['current_table'] ?? 0 ),
			'phase'               => 'tables',
			'migration_complete'  => false,
		);
		// Always end the request after scanning or importing legacy tables. This keeps
		// a potentially expensive table import separate from post updates.
		if ( $tables['processed'] > 0 || $tables['remaining'] > 0 ) {
			return $base;
		}
		if ( ! $convert_content ) {
			delete_transient( $this->migration_target_key( $batch_id ) );
			$base['phase']              = 'complete';
			$base['migration_complete'] = true;
			return $base;
		}
		$result = $this->convert_posts( $map, $batch_id, $target_offset, $post_chunk, $targets );
		if ( 0 === $result['remaining'] ) {
			delete_transient( $this->migration_target_key( $batch_id ) );
		}
		return array(
			'success'             => empty( $base['errors'] ) && empty( $result['errors'] ),
			'datasets_created'    => $base['datasets_created'],
			'posts_updated'       => $result['posts'],
			'instances_converted' => $result['instances'],
			'batch_id'            => $batch_id,
			'errors'              => array_merge( $base['errors'], $result['errors'] ),
			'map'                 => $map,
			'posts_total'         => $result['total'],
			'instances_total'     => $base['instances_total'],
			'processed'           => $result['processed'],
			'posts_remaining'     => $result['remaining'],
			'next_cursor'         => $result['next_cursor'],
			'target_offset'       => $result['target_offset'],
			'tables_total'        => $base['tables_total'],
			'tables_processed'    => 0,
			'tables_remaining'    => 0,
			'next_table_cursor'   => $tables['next_cursor'],
			'table_stage'         => '',
			'current_table'       => 0,
			'phase'               => $result['remaining'] > 0 ? 'posts' : 'complete',
			'migration_complete'  => 0 === $result['remaining'],
		);
	}

	private function migrate_tables( array $map, int $cursor = 0, int $limit = 20, string $batch_id = '' ): array {
		global $wpdb;
		$cursor = max( 0, $cursor );
		$limit  = max( 1, min( 100, $limit ) );
		$source = $wpdb->prefix . 'dalt_table';
		if ( '' !== $batch_id ) {
			$table_state = get_transient( $this->migration_table_key( $batch_id ) );
			if ( is_array( $table_state ) ) {
				$old = (int) ( $table_state['old_id'] ?? 0 );
				if ( $old > 0 && isset( $map[ $old ] ) && $this->repo->get_dataset( (int) $map[ $old ], false ) ) {
					delete_transient( $this->migration_table_key( $batch_id ) );
					$cursor = max( $cursor, $old );
				} else {
					try {
						return $this->continue_table_migration( $map, $cursor, $batch_id, $table_state );
					} catch ( Throwable $e ) {
						return $this->table_migration_result( $map, 0, $cursor, $source, array( 'Table ' . $old . ': ' . $e->getMessage() ), (string) ( $table_state['phase'] ?? 'rows' ), $old );
					}
				}
			}
		}
		$tables = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$source} WHERE temporary=0 AND id > %d ORDER BY id ASC LIMIT %d", $cursor, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- the table name is an internal identifier and cannot be prepared.
		if ( ! $tables ) {
			$tables = array();
		}
		$created   = 0;
		$processed = 0;
		$errors    = array();
		$next      = $cursor;
		foreach ( $tables as $table ) {
			$old  = (int) $table['id'];
			if ( isset( $map[ $old ] ) && $this->repo->get_dataset( (int) $map[ $old ], false ) ) {
				$next = max( $next, $old );
				++$processed;
				continue;
			}
			try {
				if ( '' !== $batch_id ) {
					$this->start_table_migration( $table, $batch_id );
					return $this->table_migration_result( $map, $processed, $next, $source, $errors, 'rows', $old, $created );
				} else {
					$new = $this->migrate_table( $table );
					if ( $new ) {
						$map[ $old ] = $new;
						++$created;
					}
					$next = max( $next, $old );
					++$processed;
				}
			} catch ( Throwable $e ) {
				$errors[] = 'Table ' . $old . ': ' . $e->getMessage();
			}
			break;
		}
		return $this->table_migration_result( $map, $processed, $next, $source, $errors, '', 0, $created );
	}

	private function start_table_migration( array $table, string $batch_id ): void {
		global $wpdb;
		$old        = (int) $table['id'];
		$header_row = $wpdb->get_row( $wpdb->prepare( "SELECT row_index,content FROM {$wpdb->prefix}dalt_data WHERE table_id=%d ORDER BY row_index ASC LIMIT 1", $old ), ARRAY_A );
		$header     = is_array( $header_row ) ? json_decode( (string) $header_row['content'], true ) : array();
		$header     = is_array( $header ) ? $header : array();
		$dataset_id = $this->repo->create_dataset( (string) ( ! empty( $table['name'] ) ? $table['name'] : 'League Table ' . $old ), $this->legacy_columns( $table, $header ), array(), (string) ( $table['description'] ?? '' ) );
		if ( ! $dataset_id ) {
			throw new RuntimeException( __( 'Could not create the destination dataset.', 'native-tables-charts' ) );
		}
		try {
			$config             = $this->table_config( $table );
			$config['cellMeta'] = array();
			$view_id            = $this->repo->create_view( $dataset_id, 'table', (string) ( ! empty( $table['name'] ) ? $table['name'] : 'League Table ' . $old ), $config );
			if ( ! $view_id ) {
				throw new RuntimeException( __( 'Could not create the destination table view.', 'native-tables-charts' ) );
			}
		} catch ( Throwable $e ) {
			$this->repo->delete_dataset( $dataset_id );
			throw $e;
		}
		update_option( 'ntc_lt_view_' . $old, $view_id, false );
		set_transient(
			$this->migration_table_key( $batch_id ),
			array(
				'old_id'      => $old,
				'dataset_id'  => $dataset_id,
				'view_id'     => $view_id,
				'phase'       => 'rows',
				'row_cursor'  => is_array( $header_row ) ? (int) $header_row['row_index'] : -1,
				'row_offset'  => 0,
				'cell_offset' => 0,
			),
			DAY_IN_SECONDS
		);
	}

	private function continue_table_migration( array $map, int $cursor, string $batch_id, array $state ): array {
		global $wpdb;
		$old        = (int) ( $state['old_id'] ?? 0 );
		$dataset_id = (int) ( $state['dataset_id'] ?? 0 );
		$view_id    = (int) ( $state['view_id'] ?? 0 );
		$source     = $wpdb->prefix . 'dalt_table';
		if ( $old < 1 || $dataset_id < 1 || $view_id < 1 || ! $this->repo->get_dataset( $dataset_id, false ) ) {
			throw new RuntimeException( __( 'The resumable table import state is incomplete.', 'native-tables-charts' ) );
		}
		if ( 'rows' === ( $state['phase'] ?? 'rows' ) ) {
			$row_cursor = (int) ( $state['row_cursor'] ?? -1 );
			$raw        = $wpdb->get_results( $wpdb->prepare( "SELECT row_index,content FROM {$wpdb->prefix}dalt_data WHERE table_id=%d AND row_index > %d ORDER BY row_index ASC LIMIT %d", $old, $row_cursor, self::TABLE_ROW_BATCH_SIZE ), ARRAY_A );
			$raw        = $raw ? $raw : array();
			$rows       = array();
			foreach ( $raw as $row ) {
				$decoded = json_decode( (string) $row['content'], true );
				$rows[]  = is_array( $decoded ) ? $decoded : array();
			}
			if ( $rows && ! $this->repo->upsert_rows( $dataset_id, $rows, (int) ( $state['row_offset'] ?? 0 ) ) ) {
				throw new RuntimeException( __( 'Could not write a page of destination table rows.', 'native-tables-charts' ) );
			}
			if ( $raw ) {
				$last                = end( $raw );
				$state['row_cursor'] = (int) $last['row_index'];
				$state['row_offset'] = (int) ( $state['row_offset'] ?? 0 ) + count( $rows );
			}
			if ( count( $raw ) < self::TABLE_ROW_BATCH_SIZE ) {
				$state['phase'] = 'cells';
			}
			set_transient( $this->migration_table_key( $batch_id ), $state, DAY_IN_SECONDS );
			return $this->table_migration_result( $map, 0, $cursor, $source, array(), (string) $state['phase'], $old );
		}

		$cell_offset = max( 0, (int) ( $state['cell_offset'] ?? 0 ) );
		$cell_rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dalt_cell WHERE table_id=%d ORDER BY row_index ASC,column_index ASC LIMIT %d OFFSET %d", $old, self::TABLE_CELL_BATCH_SIZE, $cell_offset ), ARRAY_A );
		$cell_rows   = $cell_rows ? $cell_rows : array();
		if ( $cell_rows ) {
			$view = $this->repo->get_view( $view_id );
			if ( ! $view ) {
				throw new RuntimeException( __( 'The destination table view could not be loaded.', 'native-tables-charts' ) );
			}
			$config             = (array) $view['config'];
			$config['cellMeta'] = array_merge( (array) ( $config['cellMeta'] ?? array() ), $this->legacy_cell_meta( $cell_rows ) );
			if ( ! $this->repo->update_view( $view_id, array( 'config' => $config ) ) ) {
				throw new RuntimeException( __( 'Could not write a page of destination cell properties.', 'native-tables-charts' ) );
			}
			$state['cell_offset'] = $cell_offset + count( $cell_rows );
		}
		if ( count( $cell_rows ) >= self::TABLE_CELL_BATCH_SIZE ) {
			set_transient( $this->migration_table_key( $batch_id ), $state, DAY_IN_SECONDS );
			return $this->table_migration_result( $map, 0, $cursor, $source, array(), 'cells', $old );
		}
		$map[ $old ] = $dataset_id;
		update_option( 'ntc_migration_map', $map, false );
		delete_transient( $this->migration_table_key( $batch_id ) );
		return $this->table_migration_result( $map, 1, $old, $source, array(), '', 0, 1 );
	}

	private function table_migration_result( array $map, int $processed, int $cursor, string $source, array $errors = array(), string $stage = '', int $current_table = 0, int $created = 0 ): array {
		global $wpdb;
		return array(
			'map'           => $map,
			'created'       => $created,
			'processed'     => $processed,
			'remaining'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$source} WHERE temporary=0 AND id > %d", max( 0, $cursor ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL -- the table name is an internal identifier and cannot be prepared.
			'next_cursor'   => max( 0, $cursor ),
			'errors'        => $errors,
			'table_stage'   => $stage,
			'current_table' => $current_table,
		);
	}

	private function migrate_table( array $t ): int {
		global $wpdb;
		$old = (int) $t['id'];
		$raw = $wpdb->get_results( $wpdb->prepare( "SELECT row_index,content FROM {$wpdb->prefix}dalt_data WHERE table_id=%d ORDER BY row_index ASC", $old ), ARRAY_A );
		if ( ! $raw ) {
			$raw = array();
		}
		$matrix = array();
		foreach ( $raw as $row ) {
			$decoded  = json_decode( $row['content'], true );
			$matrix[] = is_array( $decoded ) ? $decoded : array();}
		$cell_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dalt_cell WHERE table_id=%d", $old ), ARRAY_A );
		if ( ! $cell_rows ) {
			$cell_rows = array();
		}
		$result = $this->create_from_legacy( $t, $matrix, $cell_rows );
		if ( $result['dataset_id'] ) {
			update_option( 'ntc_lt_view_' . $old, $result['view_id'], false );
		}
		return $result['dataset_id'];
	}

	private function create_from_legacy( array $t, array $matrix, array $cell_rows ): array {
		$old     = (int) ( $t['id'] ?? 0 );
		$header  = $matrix ? array_shift( $matrix ) : array();
		$columns = $this->legacy_columns( $t, $header );
		$id = $this->repo->create_dataset( (string) ( ! empty( $t['name'] ) ? $t['name'] : 'League Table ' . $old ), $columns, $matrix, (string) ( $t['description'] ?? '' ) );
		if ( ! $id ) {
			return array(
				'dataset_id' => 0,
				'view_id'    => 0,
			);
		}
		$cell_meta = $this->legacy_cell_meta( $cell_rows );
		$config             = $this->table_config( $t );
		$config['cellMeta'] = $cell_meta;
		$view               = $this->repo->create_view( $id, 'table', (string) ( ! empty( $t['name'] ) ? $t['name'] : 'League Table ' . $old ), $config );
		return array(
			'dataset_id' => $id,
			'view_id'    => $view,
		);
	}

	private function legacy_columns( array $table, array $header ): array {
		$columns = array();
		$count   = max( (int) ( $table['columns'] ?? count( $header ) ), count( $header ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$columns[] = array(
				'id'     => 'c' . ( $i + 1 ),
				'label'  => sanitize_text_field( $header[ $i ] ?? 'Column ' . ( $i + 1 ) ),
				'type'   => 'auto',
				'unit'   => '',
				'format' => '',
			);
		}
		return $columns;
	}

	private function legacy_cell_meta( array $cell_rows ): array {
		$cell_meta = array();
		foreach ( $cell_rows as $cell ) {
			$source_ri = (int) ( $cell['row_index'] ?? 0 );
			$ci        = (int) ( $cell['column_index'] ?? 0 );
			$ri        = $source_ri - 1;
			$meta      = array();
			if ( ( $cell['html_content'] ?? '' ) !== '' ) {
				$meta['html'] = $cell['html_content'];
			}
			if ( ! empty( $cell['text_color'] ) ) {
				$meta['textColor'] = $cell['text_color'];
			}if ( ! empty( $cell['background_color'] ) ) {
				$meta['backgroundColor'] = $cell['background_color'];
			}
			if ( ! empty( $cell['font_weight'] ) ) {
				$meta['fontWeight'] = $cell['font_weight'];
			}if ( ! empty( $cell['font_style'] ) ) {
				$meta['fontStyle'] = $cell['font_style'];
			}
			if ( ! empty( $cell['link'] ) ) {
				$meta['link'] = $cell['link'];
			}if ( ! empty( $cell['link_color'] ) ) {
				$meta['linkColor'] = $cell['link_color'];
			}if ( ! empty( $cell['open_link_new_tab'] ) ) {
				$meta['openLinkNewTab'] = true;
			}
			if ( ! empty( $cell['image_left'] ) ) {
				$meta['imageLeft'] = $cell['image_left'];
			}if ( ! empty( $cell['image_left_link'] ) ) {
				$meta['imageLeftLink'] = $cell['image_left_link'];
			}if ( ! empty( $cell['image_left_open_link_new_tab'] ) ) {
				$meta['imageLeftOpenLinkNewTab'] = true;
			}
			if ( ! empty( $cell['image_right'] ) ) {
				$meta['imageRight'] = $cell['image_right'];
			}if ( ! empty( $cell['image_right_link'] ) ) {
				$meta['imageRightLink'] = $cell['image_right_link'];
			}if ( ! empty( $cell['image_right_open_link_new_tab'] ) ) {
				$meta['imageRightOpenLinkNewTab'] = true;
			}
			if ( ! empty( $cell['alignment'] ) ) {
				$meta['alignment'] = $cell['alignment'];
			}
			if ( ! empty( $cell['formula_data'] ) ) {
				$meta['formula']     = $this->lt_formula( (string) ( $cell['formula'] ?? 'sum' ) );
				$meta['formulaData'] = $cell['formula_data'];}
			if ( (int) ( $cell['row_slots'] ?? 1 ) > 1 ) {
				$meta['rowspan'] = (int) $cell['row_slots'];
			}if ( (int) ( $cell['column_slots'] ?? 1 ) > 1 ) {
				$meta['colspan'] = (int) $cell['column_slots'];
			}
			if ( $meta ) {
				$key               = 0 === $source_ri ? 'header:' . $ci : $ri . ':' . $ci;
				$cell_meta[ $key ] = $meta;}
		}
		return $cell_meta;
	}

	public function import_xml( string $xml ): array {
		if ( ! current_user_can( 'ntc_import' ) && ! current_user_can( 'ntc_migrate' ) && ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Permission denied.', 'native-tables-charts' ),
			);
		}
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The SimpleXML PHP extension is required for League Table XML imports.', 'native-tables-charts' ),
			);
		}
		libxml_use_internal_errors( true );
		$root = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		if ( ! $root ) {
			return array(
				'success' => false,
				'error'   => __( 'The uploaded file is not valid League Table XML.', 'native-tables-charts' ),
			);
		}
		$created = 0;
		$views   = 0;
		$errors  = array();
		foreach ( $root->table as $table_node ) {
			try {
				$t = array();
				foreach ( $table_node->children() as $key => $value ) {
					if ( in_array( $key, array( 'data', 'cell' ), true ) ) {
						continue;
					}$t[ $key ] = (string) $value;}
				$matrix = array();
				foreach ( $table_node->data->record as $record ) {
					$decoded  = json_decode( (string) $record->content, true );
					$matrix[] = is_array( $decoded ) ? $decoded : array();}
				$cells = array();
				foreach ( $table_node->cell->record as $record ) {
					$cell = array();
					foreach ( $record->children() as $key => $value ) {
						$cell[ $key ] = (string) $value;
					}$cells[] = $cell;}
				$result = $this->create_from_legacy( $t, $matrix, $cells );
				if ( $result['dataset_id'] ) {
					++$created;
					if ( $result['view_id'] ) {
						++$views;
					}
				}
			} catch ( Throwable $e ) {
				$errors[] = $e->getMessage();}
		}
		return array(
			'success'          => empty( $errors ),
			'datasets_created' => $created,
			'views_created'    => $views,
			'errors'           => $errors,
		);
	}

	private function table_config( array $t ): array {
		$sorts = array();if ( ! empty( $t['enable_sorting'] ) ) {
			for ( $i = 1;$i <= 5;$i++ ) {
				$order = (int) ( $t[ 'order_desc_asc_' . $i ] ?? 0 );
				if ( 1 !== $order && 2 !== $order ) {
					continue;
				}$col    = max( 0, (int) ( $t[ 'order_by_' . $i ] ?? 1 ) - 1 );
				$sorts[] = array(
					'column'     => $col,
					'direction'  => 1 === $order ? 'desc' : 'asc',
					'type'       => $this->lt_type( $t[ 'order_data_type_' . $i ] ?? 'auto' ),
					'dateFormat' => $t[ 'order_date_format_' . $i ] ?? '',
				);}
		}
		$widths = array();
		if ( 1 === (int) $t['column_width'] ) {
			$vals = array_filter( array_map( 'trim', explode( ',', (string) $t['column_width_value'] ) ), 'strlen' );if ( count( $vals ) === 1 ) {
				for ( $i = 0;$i < (int) $t['columns'];$i++ ) {
					$widths[ $i ] = (int) $vals[0];
				}
			} else {
				foreach ( $vals as $i => $v ) {
					$widths[ $i ] = (int) $v;
				}
			}
		}
		$rules       = array();
		$color_order = ( ( $t['autocolors_priority'] ?? 'rows' ) === 'columns' ) ? array(
			'rows'    => 'row',
			'columns' => 'column',
		) : array(
			'columns' => 'column',
			'rows'    => 'row',
		);foreach ( $color_order as $src => $type ) {
			for ( $i = 1;$i <= 5;$i++ ) {
				$list = $t[ 'autocolors_affected_' . $src . '_' . $i ] ?? '';
				if ( '' !== $list ) {
					$idx     = array_map( fn( $v )=>max( 0, (int) $v - 1 ), preg_split( '/\s*,\s*/', $list ) );
					$rules[] = array(
						'type'       => $type,
						'indexes'    => $idx,
						'background' => $t[ 'autocolors_' . $src . '_background_color_' . $i ] ?? '',
						'color'      => $t[ 'autocolors_' . $src . '_font_color_' . $i ] ?? '',
					);}
			}
		}
		$align       = array();
		$align_order = ( ( $t['autoalignment_priority'] ?? 'rows' ) === 'columns' ) ? array(
			'row'    => 'rows',
			'column' => 'columns',
		) : array(
			'column' => 'columns',
			'row'    => 'rows',
		);
		foreach ( $align_order as $type => $src ) {
			foreach ( array( 'left', 'center', 'right' ) as $a ) {
				$list = $t[ 'autoalignment_affected_' . $src . '_' . $a ] ?? '';
				if ( '' !== $list ) {
					$align[] = array(
						'type'    => $type,
						'indexes' => array_map( fn( $v )=>max( 0, (int) $v - 1 ), preg_split( '/\s*,\s*/', $list ) ),
						'align'   => $a,
					);
				}
			}
		}
		return array(
			'preset'                => 'editorial',
			'showHeader'            => (bool) $t['show_header'],
			'showCaption'           => (bool) $t['caption_show_caption'],
			'caption'               => $t['caption'],
			'captionSide'           => 0 === (int) $t['caption_caption_side'] ? 'top' : 'bottom',
			'captionTextAlign'      => $this->lt_align_from_int( $t['caption_text_align'] ?? 1 ),
			'captionFontSize'       => (int) $t['caption_font_size'],
			'captionFontFamily'     => $t['caption_font_family'] ?? 'inherit',
			'captionFontWeight'     => $t['caption_font_weight'] ?? '400',
			'captionFontStyle'      => $t['caption_font_style'] ?? 'normal',
			'captionColor'          => $t['caption_font_color'] ?? '',
			'showPosition'          => (bool) $t['show_position'],
			'positionSide'          => $t['position_side'],
			'positionLabel'         => $t['position_label'],
			'enableSorting'         => (bool) $t['enable_sorting'],
			'enableManualSorting'   => (bool) $t['enable_manual_sorting'],
			'numberFormat'          => 1 === (int) $t['number_format'] ? 'us' : 'eu',
			'defaultSort'           => $sorts,
			'stickyHeader'          => (bool) $t['sticky_header'],
			'tableLayout'           => 1 === (int) $t['table_layout'] ? 'fixed' : 'auto',
			'width'                 => 1 === (int) $t['table_width'] ? ( (int) $t['table_width_value'] . 'px' ) : '100%',
			'minWidth'              => (int) $t['table_minimum_width'] . 'px',
			'maxHeight'             => (bool) $t['enable_container'] ? (int) $t['container_height'] : 0,
			'containerWidth'        => (bool) $t['enable_container'] ? (int) $t['container_width'] : 0,
			'marginTop'             => (int) $t['table_margin_top'],
			'marginBottom'          => (int) $t['table_margin_bottom'],
			'columnWidths'          => $widths,
			'phoneBreakpoint'       => (int) $t['phone_breakpoint'],
			'tabletBreakpoint'      => (int) $t['tablet_breakpoint'],
			'phoneHeaderFontSize'   => (int) $t['phone_header_font_size'],
			'phoneBodyFontSize'     => (int) $t['phone_body_font_size'],
			'phoneCaptionFontSize'  => (int) $t['phone_caption_font_size'],
			'tabletHeaderFontSize'  => (int) $t['tablet_header_font_size'],
			'tabletBodyFontSize'    => (int) $t['tablet_body_font_size'],
			'tabletCaptionFontSize' => (int) $t['tablet_caption_font_size'],
			'hidePhone'             => $this->index_list( $t['hide_phone_list'] ),
			'hideTablet'            => $this->index_list( $t['hide_tablet_list'] ),
			'phoneHideImages'       => (bool) $t['phone_hide_images'],
			'tabletHideImages'      => (bool) $t['tablet_hide_images'],
			'headerBackground'      => $t['header_background_color'],
			'headerColor'           => $t['header_font_color'],
			'headerLinkColor'       => $t['header_link_color'] ?? $t['header_font_color'],
			'headerBorderColor'     => $t['header_border_color'] ?? $t['rows_border_color'],
			'headerPositionAlign'   => $t['header_position_alignment'] ?? 'center',
			'headerFontSize'        => (int) $t['header_font_size'],
			'headerFontFamily'      => $t['header_font_family'] ?? 'inherit',
			'headerFontWeight'      => $t['header_font_weight'] ?? '700',
			'headerFontStyle'       => $t['header_font_style'] ?? 'normal',
			'bodyColor'             => $t['even_rows_font_color'],
			'oddColor'              => $t['odd_rows_font_color'],
			'evenColor'             => $t['even_rows_font_color'],
			'oddBackground'         => $t['odd_rows_background_color'],
			'evenBackground'        => $t['even_rows_background_color'],
			'borderColor'           => $t['rows_border_color'],
			'linkColor'             => $t['even_rows_link_color'],
			'oddLinkColor'          => $t['odd_rows_link_color'],
			'evenLinkColor'         => $t['even_rows_link_color'],
			'fontSize'              => (int) $t['body_font_size'],
			'bodyFontFamily'        => $t['body_font_family'] ?? 'inherit',
			'bodyFontWeight'        => $t['body_font_weight'] ?? '400',
			'bodyFontStyle'         => $t['body_font_style'] ?? 'normal',
			'enableCellProperties'  => (bool) ( $t['enable_cell_properties'] ?? 1 ),
			'averageDecimals'       => (int) ( $t['formula_average_decimals'] ?? 2 ),
			'averageRound'          => $this->lt_round_mode( $t['formula_average_round'] ?? 1 ),
			'autoColorRules'        => $rules,
			'autoAlignRules'        => $align,
			'responsiveMode'        => 'scroll',
		);
	}
	private function lt_align_from_int( $v ): string {
		return match ( (int) $v ) {
			0=>'left', 2=>'right', 3=>'justify', default=>'center'}; }
	private function index_list( string $s ): array {
		if ( trim( $s ) === '' ) {
			return array();
		}return array_map( fn( $v )=>max( 0, (int) $v - 1 ), preg_split( '/\s*,\s*/', $s ) );}
	private function lt_formula( string $f ): string {
		return match ( $f ) {
			'subtrac', 'subtraction'=>'subtract', 'minimum'=>'min', 'maximum'=>'max', 'average'=>'average', default=>$f ? $f : 'sum'};}
	private function lt_round_mode( $v ): string {
		return match ( (int) $v ) {
			2=>'half_down', 3=>'half_even', 4=>'half_odd', default=>'half_up'};}
	private function lt_type( string $t ): string {
		return match ( $t ) {
			'digit'=>'number', 'isoDate'=>'iso_date', 'usLongDate'=>'us_long_date', 'shortDate'=>'short_date', default=>sanitize_key( $t ? $t : 'auto' )};}

	private function migration_target_key( string $batch ): string {
		return 'ntc_migration_targets_' . sanitize_key( $batch );
	}

	private function migration_table_key( string $batch ): string {
		return 'ntc_migration_table_' . sanitize_key( $batch );
	}

	private function normalize_target_ids( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	private function convert_posts( array $map, string $batch, int $target_offset = 0, int $chunk = self::POST_BATCH_SIZE, array $target_ids = array() ): array {
		global $wpdb;
		$chunk         = max( 1, min( 100, $chunk ) );
		$target_ids    = $this->normalize_target_ids( $target_ids );
		$target_offset = max( 0, $target_offset );
		$batch_ids     = array_slice( $target_ids, $target_offset, $chunk );
		$posts_by_id   = array();
		if ( $batch_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $batch_ids ), '%d' ) );
			$posts        = $wpdb->get_results( $wpdb->prepare( "SELECT ID,post_content FROM {$wpdb->posts} WHERE ID IN ({$placeholders}) ORDER BY ID ASC", ...$batch_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- placeholders are generated internally and every ID is prepared as an integer.
			foreach ( $posts ? $posts : array() as $post ) {
				$posts_by_id[ (int) $post['ID'] ] = $post;
			}
		}
		$updated   = 0;
		$instances = 0;
		$errors    = array();
		$processed = 0;
		$next      = 0;
		$started   = microtime( true );
		foreach ( $batch_ids as $post_id ) {
			if ( $processed > 0 && microtime( true ) - $started >= self::POST_TIME_BUDGET_SECS ) {
				break;
			}
			$next = $post_id;
			if ( empty( $posts_by_id[ $post_id ] ) ) {
				++$processed;
				continue;
			}
			$p        = $posts_by_id[ $post_id ];
			$original = $p['post_content'];
			$new      = $original;
			$new      = preg_replace_callback(
				'/\[lt\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i',
				function ( $m ) use ( $map, &$instances ) {
					$old = (int) $m[1];
					if ( empty( $map[ $old ] ) ) {
						return $m[0];
					}$view = (int) get_option( 'ntc_lt_view_' . $old, 0 );
					$instances++;
					return '<!-- wp:ntc/table {"mode":"view","datasetId":' . (int) $map[ $old ] . ',"viewId":' . $view . '} /-->';
				},
				$new
			);
			$new      = preg_replace_callback(
				'/<!--\s+wp:dalt\/table\s+(\{.*?\})\s*\/-->/s',
				function ( $m ) use ( $map, &$instances ) {
					$a   = json_decode( $m[1], true );
					$old = (int) ( $a['tableId'] ?? 0 );
					if ( empty( $map[ $old ] ) ) {
						return $m[0];
					}$view = (int) get_option( 'ntc_lt_view_' . $old, 0 );
					$instances++;
					return '<!-- wp:ntc/table {"mode":"view","datasetId":' . (int) $map[ $old ] . ',"viewId":' . $view . '} /-->';
				},
				$new
			);
			if ( $new !== $original ) {
				$backed_up = $wpdb->insert(
					$wpdb->prefix . 'ntc_backups',
					array(
						'batch_id'         => $batch,
						'post_id'          => (int) $p['ID'],
						'original_content' => $original,
						'migrated_content' => $new,
						'created_at'       => current_time( 'mysql', true ),
					),
					array( '%s', '%d', '%s', '%s', '%s' )
				);
				if ( false === $backed_up ) {
					$errors[] = 'Post ' . $p['ID'] . ': ' . __( 'The rollback backup could not be saved.', 'native-tables-charts' );
					++$processed;
					continue;
				}
				$ok = $wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new ),
					array( 'ID' => (int) $p['ID'] ),
					array( '%s' ),
					array( '%d' )
				);
				if ( false === $ok ) {
					$errors[] = 'Post ' . $p['ID'] . ': ' . __( 'The database update failed.', 'native-tables-charts' );
				} else {
					clean_post_cache( (int) $p['ID'] );
					++$updated;}
			}
			++$processed;
		}
		$next_offset = $target_offset + $processed;
		$remaining   = max( 0, count( $target_ids ) - $next_offset );
		return array(
			'posts'       => $updated,
			'instances'   => $instances,
			'errors'      => $errors,
			'total'       => count( $target_ids ),
			'processed'   => $processed,
			'remaining'   => $remaining,
			'next_cursor' => $next,
			'target_offset' => $next_offset,
		);
	}

	public function rollback( string $batch, int $offset = 0, int $chunk = 200 ): array {
		global $wpdb;
		$chunk = max( 1, min( 1000, $chunk ) );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ntc_backups WHERE batch_id=%s", $batch ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntc_backups WHERE batch_id=%s ORDER BY id DESC LIMIT %d OFFSET %d", $batch, $chunk, max( 0, $offset ) ), ARRAY_A );
		if ( ! $rows ) {
			$rows = array();
		}
		$restored = 0;
		$errors   = array();
		foreach ( $rows as $r ) {
			$ok = wp_update_post(
				array(
					'ID'           => (int) $r['post_id'],
					'post_content' => $r['original_content'],
				),
				true
			);
			if ( is_wp_error( $ok ) ) {
				$errors[] = $ok->get_error_message();
			} else {
				++$restored;
			}
		}return array(
			'success'   => empty( $errors ),
			'restored'  => $restored,
			'errors'    => $errors,
			'total'     => $total,
			'processed' => count( $rows ),
			'remaining' => max( 0, $total - count( $rows ) - max( 0, $offset ) ),
		);}
}
