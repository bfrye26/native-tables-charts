<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Admin {
	private NTC_Repository $repo;
	private NTC_Migrator $migrator;
	public function __construct( NTC_Repository $repo, NTC_Migrator $migrator ) {
		$this->repo     = $repo;
		$this->migrator = $migrator;}

	public function register_menu(): void {
		add_menu_page( __( 'Data Tables & Charts', 'native-tables-charts' ), __( 'Tables & Charts', 'native-tables-charts' ), 'ntc_edit_datasets', 'ntc-data', array( $this, 'library_page' ), 'dashicons-chart-bar', 58 );
		add_submenu_page( 'ntc-data', __( 'Data Library', 'native-tables-charts' ), __( 'Data Library', 'native-tables-charts' ), 'ntc_edit_datasets', 'ntc-data', array( $this, 'library_page' ) );
		add_submenu_page( 'ntc-data', __( 'Style Presets', 'native-tables-charts' ), __( 'Style Presets', 'native-tables-charts' ), 'ntc_manage_presets', 'ntc-presets', array( $this, 'presets_page' ) );
		add_submenu_page( 'ntc-data', __( 'Import & Export', 'native-tables-charts' ), __( 'Tools', 'native-tables-charts' ), 'ntc_export', 'ntc-tools', array( $this, 'tools_page' ) );
		add_submenu_page( 'ntc-data', __( 'League Table Migration', 'native-tables-charts' ), __( 'Migration', 'native-tables-charts' ), 'ntc_migrate', 'ntc-migration', array( $this, 'migration_page' ) );
		add_submenu_page( 'ntc-data', __( 'Settings', 'native-tables-charts' ), __( 'Settings', 'native-tables-charts' ), 'ntc_manage_settings', 'ntc-settings', array( $this, 'settings_page' ) );
	}

	public function admin_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'ntc-' ) ) {
			return;
		}
		wp_enqueue_style( 'ntc-admin', NTC_URL . 'assets/css/admin.css', array(), NTC_VERSION );
	}

	public function handle_actions(): void {
		if ( ! is_admin() || ! isset( $_POST['ntc_action'] ) ) {
			return;
		}
		check_admin_referer( 'ntc_admin_action' );
		$action = sanitize_key( wp_unslash( $_POST['ntc_action'] ) );
		if ( 'delete_dataset' === $action && current_user_can( 'ntc_delete_datasets' ) ) {
			$this->repo->delete_dataset( absint( $_POST['dataset_id'] ?? 0 ) );
			$this->redirect( 'ntc-data', 'deleted=1' );}
		if ( 'duplicate_dataset' === $action && current_user_can( 'ntc_create_datasets' ) ) {
			$source_id = absint( $_POST['dataset_id'] ?? 0 );
			$source    = $this->repo->get_dataset( $source_id, true );
			if ( $source ) {
				/* translators: %s: dataset name. */
				$new_id = $this->repo->create_dataset( sprintf( __( '%s Copy', 'native-tables-charts' ), $source['name'] ), $source['columns'], $source['rows'] ?? array(), $source['description'] ?? '' );
				if ( $new_id ) {
					foreach ( $this->repo->list_views( $source_id ) as $view ) {
								/* translators: %s: view name. */
								$this->repo->create_view( $new_id, $view['type'], sprintf( __( '%s Copy', 'native-tables-charts' ), $view['name'] ), $view['config'] );
					}
				}
			}$this->redirect( 'ntc-data', 'duplicated=1' );}
		if ( 'delete_preset' === $action && current_user_can( 'ntc_manage_presets' ) ) {
			$this->repo->delete_preset( absint( $_POST['preset_id'] ?? 0 ) );
			$this->redirect( 'ntc-presets', 'deleted=1' );}
		if ( 'export_preset' === $action && current_user_can( 'ntc_manage_presets' ) ) {
			$preset = $this->repo->get_preset( absint( $_POST['preset_id'] ?? 0 ) );
			if ( ! $preset ) {
				wp_die( esc_html__( 'Preset not found.', 'native-tables-charts' ) );
			}$payload = array(
				'nativeTablesChartsPreset' => 1,
				'name'                     => $preset['name'],
				'type'                     => $preset['type'],
				'settings'                 => $preset['settings'],
			);
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( ! empty( $preset['slug'] ) ? $preset['slug'] : 'ntc-preset' ) . '.json"' );
			echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;}
		if ( 'import_preset' === $action && current_user_can( 'ntc_manage_presets' ) ) {
			$raw = '';
			if ( ! empty( $_FILES['preset_file']['tmp_name'] ) && is_uploaded_file( $_FILES['preset_file']['tmp_name'] ) && (int) ( $_FILES['preset_file']['size'] ?? 0 ) <= 2 * MB_IN_BYTES ) {
				$raw = (string) file_get_contents( $_FILES['preset_file']['tmp_name'] );
			} elseif ( isset( $_POST['preset_json'] ) ) {
				$raw = (string) wp_unslash( $_POST['preset_json'] );
			}$data = json_decode( $raw, true );
			if ( is_array( $data ) && in_array( $data['type'] ?? '', array( 'table', 'chart' ), true ) && is_array( $data['settings'] ?? null ) ) {
				$this->repo->create_preset( $data['type'], sanitize_text_field( $data['name'] ?? __( 'Imported preset', 'native-tables-charts' ) ), $data['settings'] );
				$this->redirect( 'ntc-presets', 'imported=1' );
			}$this->redirect( 'ntc-presets', 'import_error=1' );}
		if ( 'export_dataset_bundle' === $action && current_user_can( 'ntc_export' ) ) {
			$id      = absint( $_POST['dataset_id'] ?? 0 );
			$payload = $this->build_backup_payload( $id ? array( $id ) : array() );
			if ( empty( $payload['datasets'] ) ) {
				wp_die( esc_html__( 'Dataset not found.', 'native-tables-charts' ) );
			}$this->send_json_download( $payload, 'native-tables-charts-dataset-' . $id . '.json' );}
		if ( 'export_all_bundle' === $action && current_user_can( 'ntc_export' ) ) {
			$this->send_json_download( $this->build_backup_payload(), 'native-tables-charts-backup-' . gmdate( 'Y-m-d' ) . '.json' );}
		if ( 'import_native_bundle' === $action && current_user_can( 'ntc_import' ) ) {
			$result = array(
				'success' => false,
				'error'   => __( 'No Native Tables & Charts JSON file was uploaded.', 'native-tables-charts' ),
			);
			$size   = (int) ( $_FILES['native_bundle']['size'] ?? 0 );
			if ( ! empty( $_FILES['native_bundle']['tmp_name'] ) && is_uploaded_file( $_FILES['native_bundle']['tmp_name'] ) && $size <= 50 * MB_IN_BYTES ) {
				$raw     = (string) file_get_contents( $_FILES['native_bundle']['tmp_name'] );
				$payload = json_decode( $raw, true );
				$result  = $this->import_backup_payload( is_array( $payload ) ? $payload : array() );
			} elseif ( $size > 50 * MB_IN_BYTES ) {
				$result = array(
					'success' => false,
					'error'   => __( 'The Native Tables & Charts backup is larger than the 50 MB import limit.', 'native-tables-charts' ),
				);
			}set_transient( 'ntc_tools_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-tools', 'imported=1' );}
		if ( 'migration_dry_run' === $action && current_user_can( 'ntc_migrate' ) ) {
			set_transient( 'ntc_migration_report_' . get_current_user_id(), $this->migrator->dry_run(), 10 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-migration', 'dryrun=1' );}
		if ( 'migration_run' === $action && current_user_can( 'ntc_migrate' ) ) {
			$batch_id    = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
			$uid         = get_current_user_id();
			$prev        = '' !== $batch_id ? get_transient( 'ntc_migration_progress_' . $uid ) : false;
			$convert     = is_array( $prev ) ? ! empty( $prev['convert'] ) : ! empty( $_POST['convert_content'] );
			$offset      = absint( $_POST['offset'] ?? ( is_array( $prev ) ? ( $prev['offset'] ?? 0 ) : 0 ) );
			$cursor      = absint( $_POST['cursor'] ?? ( is_array( $prev ) ? ( $prev['cursor'] ?? 0 ) : 0 ) );
			$table_cursor = absint( $_POST['table_cursor'] ?? ( is_array( $prev ) ? ( $prev['table_cursor'] ?? 0 ) : 0 ) );
			$chunk       = NTC_Migrator::POST_BATCH_SIZE;
			if ( '' !== $batch_id ) {
				$result = $this->migrator->continue_migration( $batch_id, $cursor, $chunk, $table_cursor, $convert );
			} else {
				$result = $this->migrator->migrate( $convert, 0, $chunk );
			}
			$batch_id         = (string) ( $result['batch_id'] ?? $batch_id );
			$total            = is_array( $prev ) ? (int) ( $prev['total'] ?? 0 ) : (int) ( $result['posts_total'] ?? 0 );
			$new_offset       = $offset + (int) ( $result['processed'] ?? 0 );
			$next_cursor      = (int) ( $result['next_cursor'] ?? $cursor );
			$table_total      = is_array( $prev ) && isset( $prev['table_total'] ) ? (int) $prev['table_total'] : (int) ( $result['tables_total'] ?? 0 );
			$table_offset     = ( is_array( $prev ) ? (int) ( $prev['table_offset'] ?? 0 ) : 0 ) + (int) ( $result['tables_processed'] ?? 0 );
			$next_table_cursor = (int) ( $result['next_table_cursor'] ?? $table_cursor );
			$done             = empty( $batch_id ) || ! empty( $result['migration_complete'] );
			$acc_errors       = array_merge( is_array( $prev ) ? (array) ( $prev['errors'] ?? array() ) : array(), (array) ( $result['errors'] ?? array() ) );
			$acc_datasets     = ( is_array( $prev ) ? (int) ( $prev['datasets'] ?? 0 ) : 0 ) + (int) ( $result['datasets_created'] ?? 0 );
			$acc_posts        = ( is_array( $prev ) ? (int) ( $prev['posts'] ?? 0 ) : 0 ) + (int) ( $result['posts_updated'] ?? 0 );
			$acc_instances    = ( is_array( $prev ) ? (int) ( $prev['instances'] ?? 0 ) : 0 ) + (int) ( $result['instances_converted'] ?? 0 );
			if ( $done ) {
				$result['success']             = empty( $acc_errors );
				$result['errors']              = $acc_errors;
				$result['datasets_created']    = $acc_datasets;
				$result['posts_updated']       = $acc_posts;
				$result['instances_converted'] = $acc_instances;
				delete_transient( 'ntc_migration_progress_' . $uid );
				set_transient( 'ntc_migration_result_' . $uid, $result, 30 * MINUTE_IN_SECONDS );
				$this->redirect( 'ntc-migration', 'migrated=1' );
			}
			set_transient(
				'ntc_migration_progress_' . $uid,
				array(
					'done'      => false,
					'convert'   => $convert,
					'batch_id'  => $batch_id,
					'offset'    => $new_offset,
					'cursor'    => $next_cursor,
					'table_offset' => $table_offset,
					'table_cursor' => $next_table_cursor,
					'table_total' => $table_total,
					'total'     => $total,
					'phase'     => (string) ( $result['phase'] ?? 'tables' ),
					'errors'    => $acc_errors,
					'datasets'  => $acc_datasets,
					'posts'     => $acc_posts,
					'instances' => $acc_instances,
				),
				HOUR_IN_SECONDS
			);
			$this->redirect( 'ntc-migration', 'migrated=1' );
		}
		if ( 'migration_xml_import' === $action && ( current_user_can( 'ntc_migrate' ) || current_user_can( 'ntc_import' ) ) ) {
			$result = array(
				'success' => false,
				'error'   => __( 'No XML file was uploaded.', 'native-tables-charts' ),
			);
			if ( ! empty( $_FILES['league_xml']['tmp_name'] ) && is_uploaded_file( $_FILES['league_xml']['tmp_name'] ) && (int) ( $_FILES['league_xml']['size'] ?? 0 ) <= 20 * MB_IN_BYTES ) {
				$xml = file_get_contents( $_FILES['league_xml']['tmp_name'] );
				if ( false !== $xml ) {
					$result = $this->migrator->import_xml( $xml );
				}
			} elseif ( ! empty( $_FILES['league_xml']['size'] ) && (int) $_FILES['league_xml']['size'] > 20 * MB_IN_BYTES ) {
				$result = array(
					'success' => false,
					'error'   => __( 'The League Table XML file is larger than the 20 MB import limit.', 'native-tables-charts' ),
				);
			}set_transient( 'ntc_migration_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-migration', 'xmlimport=1' );}
		if ( 'migration_rollback' === $action && current_user_can( 'ntc_migrate' ) ) {
			$batch        = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
			$offset       = absint( $_POST['offset'] ?? 0 );
			$result       = $this->migrator->rollback( $batch, $offset, 200 );
			$uid          = get_current_user_id();
			$prev         = get_transient( 'ntc_rollback_progress_' . $uid );
			$acc_errors   = array_merge( is_array( $prev ) ? (array) ( $prev['errors'] ?? array() ) : array(), (array) ( $result['errors'] ?? array() ) );
			$acc_restored = ( is_array( $prev ) ? (int) ( $prev['restored'] ?? 0 ) : 0 ) + (int) ( $result['restored'] ?? 0 );
			$new_offset   = $offset + (int) ( $result['processed'] ?? 0 );
			$done         = 0 === (int) ( $result['remaining'] ?? 0 );
			if ( $done ) {
				$result['errors']   = $acc_errors;
				$result['restored'] = $acc_restored;
				delete_transient( 'ntc_rollback_progress_' . $uid );
				set_transient( 'ntc_migration_result_' . $uid, $result, 30 * MINUTE_IN_SECONDS );
				$this->redirect( 'ntc-migration', 'rolledback=1' );
			}
			set_transient(
				'ntc_rollback_progress_' . $uid,
				array(
					'done'     => false,
					'batch_id' => $batch,
					'offset'   => $new_offset,
					'total'    => (int) ( $result['total'] ?? 0 ),
					'errors'   => $acc_errors,
					'restored' => $acc_restored,
				),
				HOUR_IN_SECONDS
			);
			$this->redirect( 'ntc-migration', 'rolledback=1' );
		}
		if ( 'save_settings' === $action && current_user_can( 'ntc_manage_settings' ) ) {
			update_option( 'ntc_delete_data_on_uninstall', ! empty( $_POST['delete_data'] ) ? 1 : 0 );
			$features = array();
			foreach ( array_keys( NTC_Renderer::cell_feature_defaults() ) as $key ) {
				$features[ $key ] = ! empty( $_POST['cell_features'][ $key ] );
			}update_option( 'ntc_cell_features', $features, false );
			update_option( 'ntc_kses_allowed_html_tags', sanitize_text_field( wp_unslash( $_POST['kses_allowed_html_tags'] ?? '' ) ), false );
			update_option( 'ntc_kses_allowed_protocols', sanitize_text_field( wp_unslash( $_POST['kses_allowed_protocols'] ?? '' ) ), false );
			$this->redirect( 'ntc-settings', 'saved=1' );}
		if ( 'update_source' === $action && current_user_can( 'manage_options' ) ) {
			$this->repo->set_source( absint( $_POST['dataset_id'] ?? 0 ), (string) wp_unslash( $_POST['source_url'] ?? '' ) );
			$this->redirect( 'ntc-data', 'source=1' );
		}
		if ( 'refresh_source' === $action && current_user_can( 'manage_options' ) ) {
			$id     = absint( $_POST['dataset_id'] ?? 0 );
			$result = NTC_Sync::sync_dataset( $this->repo, $id );
			set_transient( 'ntc_tools_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-data', 'source=1' );
		}
		if ( 'update_post_source' === $action && current_user_can( 'ntc_import' ) ) {
			$mode   = 'posts' === sanitize_key( wp_unslash( $_POST['source_mode'] ?? '' ) ) ? 'posts' : '';
			$config = array(
				'post_type'  => sanitize_key( wp_unslash( $_POST['post_type'] ?? 'post' ) ),
				'meta_label' => sanitize_text_field( wp_unslash( $_POST['meta_label'] ?? '' ) ),
				'meta_value' => array_values( array_filter( array_map( 'trim', explode( ',', (string) wp_unslash( $_POST['meta_value'] ?? '' ) ) ) ) ),
			);
			$this->repo->set_post_source( absint( $_POST['dataset_id'] ?? 0 ), $mode, $config );
			$this->redirect( 'ntc-data', 'source=1' );
		}
	}

	private function redirect( string $page, string $query = '' ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . ( $query ? '&' . $query : '' ) ) );
		exit;}

	private function send_json_download( array $payload, string $filename ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	private function build_backup_payload( array $only_ids = array() ): array {
		$datasets = array();
		$ids      = array_map( 'absint', $only_ids );
		if ( ! $ids ) {
			$offset = 0;
			do {
				$page = $this->repo->list_datasets( 500, $offset );
				foreach ( $page as $item ) {
					$ids[] = (int) $item['id'];
				}$offset += count( $page );
			} while ( count( $page ) === 500 );}
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $id ) {
			$dataset = $this->repo->get_dataset( $id, true );
			if ( ! $dataset ) {
				continue;
			}$views = array();
			foreach ( $this->repo->list_views( $id ) as $view ) {
				$views[] = array(
					'name'   => $view['name'],
					'type'   => $view['type'],
					'config' => $view['config'],
				);
			}$datasets[] = array(
				'name'        => $dataset['name'],
				'description' => $dataset['description'] ?? '',
				'columns'     => $dataset['columns'] ?? array(),
				'rows'        => $dataset['rows'] ?? array(),
				'views'       => $views,
			);}
		$presets = array();
		if ( ! $only_ids && current_user_can( 'ntc_manage_presets' ) ) {
			foreach ( $this->repo->list_presets() as $preset ) {
				$presets[] = array(
					'name'     => $preset['name'],
					'type'     => $preset['type'],
					'settings' => $preset['settings'],
				);}
		}
		return array(
			'nativeTablesChartsBackup' => 1,
			'pluginVersion'            => NTC_VERSION,
			'exportedAt'               => gmdate( 'c' ),
			'scope'                    => $only_ids ? 'dataset' : 'complete',
			'datasets'                 => $datasets,
			'presets'                  => $presets,
		);
	}

	private function import_backup_payload( array $payload ): array {
		// Accept full NTC backups, single-dataset bundles, and raw NTC data JSON exports.
		if ( isset( $payload['columns'], $payload['rows'] ) && ! isset( $payload['datasets'] ) ) {
			$payload = array(
				'datasets' => array(
					array(
						'name'        => $payload['name'] ?? __( 'Imported Dataset', 'native-tables-charts' ),
						'description' => $payload['description'] ?? '',
						'columns'     => $payload['columns'],
						'rows'        => $payload['rows'],
						'views'       => $payload['views'] ?? array(),
					),
				),
				'presets'  => array(),
			);}
		$datasets = (array) ( $payload['datasets'] ?? array() );
		if ( ! $datasets ) {
			return array(
				'success' => false,
				'error'   => __( 'The file does not contain any usable Native Tables & Charts datasets.', 'native-tables-charts' ),
			);
		}
		$created         = 0;
		$views_created   = 0;
		$presets_created = 0;
		$errors          = array();
		foreach ( array_slice( $datasets, 0, 500 ) as $index => $dataset ) {
			if ( ! is_array( $dataset ) || empty( $dataset['columns'] ) || ! is_array( $dataset['columns'] ) || ! is_array( $dataset['rows'] ?? null ) ) {
				/* translators: %d: dataset number. */
				$errors[] = sprintf( __( 'Dataset %d is missing columns or rows.', 'native-tables-charts' ), $index + 1 );
				continue;
			}$id = $this->repo->create_dataset( (string) ( $dataset['name'] ?? __( 'Imported Dataset', 'native-tables-charts' ) ), (array) $dataset['columns'], (array) $dataset['rows'], (string) ( $dataset['description'] ?? '' ) );
			if ( ! $id ) {
				/* translators: %d: dataset number. */
				$errors[] = sprintf( __( 'Dataset %d could not be created.', 'native-tables-charts' ), $index + 1 );
				continue;
			}++$created;
			foreach ( array_slice( (array) ( $dataset['views'] ?? array() ), 0, 250 ) as $view ) {
				if ( ! is_array( $view ) ) {
						continue;
				}$type = in_array( $view['type'] ?? '', array( 'table', 'chart' ), true ) ? $view['type'] : 'table';
				if ( $this->repo->create_view( $id, $type, (string) ( $view['name'] ?? ucfirst( $type ) ), (array) ( $view['config'] ?? array() ) ) ) {
					++$views_created;
				}
			}
		}
		if ( current_user_can( 'ntc_manage_presets' ) ) {
			foreach ( array_slice( (array) ( $payload['presets'] ?? array() ), 0, 500 ) as $preset ) {
				if ( ! is_array( $preset ) || ! in_array( $preset['type'] ?? '', array( 'table', 'chart' ), true ) || ! is_array( $preset['settings'] ?? null ) ) {
					continue;
				}if ( $this->repo->create_preset( $preset['type'], (string) ( $preset['name'] ?? __( 'Imported preset', 'native-tables-charts' ) ), $preset['settings'] ) ) {
					++$presets_created;
				}
			}
		}
		return array(
			'success'          => empty( $errors ),
			'datasets_created' => $created,
			'views_created'    => $views_created,
			'presets_created'  => $presets_created,
			'errors'           => $errors,
		);
	}

	private function dataset_post_usage(): array {
		$key    = 'ntc_dataset_usage_counts';
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE '%wp:ntc/%'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			$rows = array();
		}
		$usage = array();
		foreach ( $rows as $row ) {
			preg_match_all( '/"datasetId"\s*:\s*(\d+)/', $row['post_content'], $m );
			$ids = array_unique( array_map( 'intval', $m[1] ?? array() ) );
			foreach ( $ids as $id ) {
				if ( ! $id ) {
						continue;
				}if ( ! isset( $usage[ $id ] ) ) {
					$usage[ $id ] = array(
						'count' => 0,
						'posts' => array(),
					);
				}++$usage[ $id ]['count'];
				if ( count( $usage[ $id ]['posts'] ) < 5 ) {
					$usage[ $id ]['posts'][] = array(
						'id'    => (int) $row['ID'],
						/* translators: %d: post ID. */
						'title' => ! empty( $row['post_title'] ) ? $row['post_title'] : sprintf( __( 'Post #%d', 'native-tables-charts' ), $row['ID'] ),
					);
				}
			}
		}
		set_transient( $key, $usage, 5 * MINUTE_IN_SECONDS );
		return $usage;
	}

	public function library_page(): void {
		$search      = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$datasets    = $this->repo->list_datasets( 250, 0, $search );
		$views       = $this->repo->list_views();
		$usage       = $this->dataset_post_usage();
		$view_counts = array();
		foreach ( $views as $v ) {
			$view_counts[ (int) $v['dataset_id'] ][] = $v;
		}
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Data Library', 'native-tables-charts' ); ?></h1><p><?php esc_html_e( 'Reusable datasets power synced tables and charts. Create inline data directly in Gutenberg, or save it here when you want the same data used across multiple posts.', 'native-tables-charts' ); ?></p>
		<?php
		if ( isset( $_GET['source'] ) ) :
			$source_result = get_transient( 'ntc_tools_result_' . get_current_user_id() );if ( is_array( $source_result ) ) :
				?>
			<pre class="ntc-result"><?php echo esc_html( wp_json_encode( $source_result, JSON_PRETTY_PRINT ) ); ?></pre>
				<?php
		endif;
endif;
		?>
		<form method="get" class="ntc-search"><input type="hidden" name="page" value="ntc-data"><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search datasets…', 'native-tables-charts' ); ?>"><button class="button"><?php esc_html_e( 'Search', 'native-tables-charts' ); ?></button></form>
		<table class="widefat striped ntc-list"><thead><tr><th><?php esc_html_e( 'Dataset', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Rows', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Views', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Posts', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Updated', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Actions', 'native-tables-charts' ); ?></th></tr></thead><tbody>
		<?php
		if ( ! $datasets ) :
			?>
			<tr><td colspan="6"><?php esc_html_e( 'No reusable datasets yet. Add a Table or Chart block in Gutenberg and choose “Save as reusable dataset.”', 'native-tables-charts' ); ?></td></tr><?php endif; ?>
		<?php
		foreach ( $datasets as $d ) :
			$id = (int) $d['id'];
			?>
			<tr><td><strong><?php echo esc_html( $d['name'] ); ?></strong>
			<?php
			if ( $d['description'] ) :
				?>
			<div class="description"><?php echo esc_html( $d['description'] ); ?></div><?php endif; ?>
			<?php
			if ( ! empty( $d['source_url'] ?? '' ) ) :
				?>
	<div class="description"><?php esc_html_e( 'Source:', 'native-tables-charts' ); ?> <?php echo esc_html( $d['source_url'] ); ?></div><?php endif; ?>
			<?php
			if ( ! empty( $d['source_error'] ?? '' ) ) :
				?>
	<div class="description" style="color:#d63638"><?php echo esc_html( $d['source_error'] ); ?></div><?php endif; ?>
			<?php
			if ( ! empty( $d['source_last_sync'] ?? '' ) ) :
				?>
	<div class="description"><?php esc_html_e( 'Synced:', 'native-tables-charts' ); ?> <?php echo esc_html( get_date_from_gmt( $d['source_last_sync'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></div><?php endif; ?>
			<?php
			if ( 'posts' === ( $d['source_mode'] ?? '' ) ) :
				?>
	<span class="ntc-source-badge"><?php esc_html_e( 'Posts', 'native-tables-charts' ); ?></span><?php endif; ?></td><td><?php echo esc_html( $d['row_count'] ); ?></td><td><?php echo esc_html( $d['view_count'] ); ?>
			<?php
			if ( ! empty( $view_counts[ $id ] ) ) :
				?>
	<div class="ntc-view-list">
				<?php
				foreach ( $view_counts[ $id ] as $v ) :
					?>
	<span><?php echo esc_html( ucfirst( $v['type'] ) . ': ' . $v['name'] ); ?></span><?php endforeach; ?></div><?php endif; ?></td><td>
			<?php
			$u = $usage[ $id ] ?? array(
				'count' => 0,
				'posts' => array(),
			);
			echo esc_html( $u['count'] ); if ( ! empty( $u['posts'] ) ) :
				?>
		<div class="ntc-view-list">
				<?php
				foreach ( $u['posts'] as $post ) :
					?>
	<a href="<?php echo esc_url( get_edit_post_link( $post['id'] ) ); ?>"><?php echo esc_html( $post['title'] ); ?></a><?php endforeach; ?></div><?php endif; ?></td><td><?php echo esc_html( get_date_from_gmt( $d['updated_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td><td><a class="button" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'format'   => 'json',
						'_wpnonce' => wp_create_nonce( 'wp_rest' ),
					),
					rest_url( 'ntc/v1/export/' . $id )
				)
			);
			?>
"><?php esc_html_e( 'Data JSON', 'native-tables-charts' ); ?></a> <form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="export_dataset_bundle"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button"><?php esc_html_e( 'Full Bundle', 'native-tables-charts' ); ?></button></form> <form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="duplicate_dataset"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button"><?php esc_html_e( 'Duplicate', 'native-tables-charts' ); ?></button></form>
			<?php
			if ( current_user_can( 'manage_options' ) ) :
				?>
<form method="post" class="ntc-inline-form ntc-source-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="update_source"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><input type="url" name="source_url" value="<?php echo esc_attr( $d['source_url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'CSV/TSV source URL…', 'native-tables-charts' ); ?>" class="ntc-source-input"><button class="button"><?php esc_html_e( 'Save URL', 'native-tables-charts' ); ?></button></form> <form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="refresh_source"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button" <?php disabled( empty( $d['source_url'] ?? '' ) ); ?>><?php esc_html_e( 'Refresh now', 'native-tables-charts' ); ?></button></form> <?php endif; ?>
			<?php
			if ( current_user_can( 'ntc_import' ) ) :
				$post_cfg = json_decode( (string) ( $d['source_config'] ?? '' ), true );
				$post_cfg = is_array( $post_cfg ) ? $post_cfg : array();
				?>
<form method="post" class="ntc-inline-form ntc-source-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="update_post_source"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><select name="source_mode"><option value="" <?php selected( '' === ( $d['source_mode'] ?? '' ) ); ?>><?php esc_html_e( 'None', 'native-tables-charts' ); ?></option><option value="posts" <?php selected( 'posts' === ( $d['source_mode'] ?? '' ) ); ?>><?php esc_html_e( 'Posts', 'native-tables-charts' ); ?></option></select><input type="text" name="post_type" value="<?php echo esc_attr( $post_cfg['post_type'] ?? 'post' ); ?>" placeholder="<?php esc_attr_e( 'Post type…', 'native-tables-charts' ); ?>"><input type="text" name="meta_label" value="<?php echo esc_attr( $post_cfg['meta_label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Label meta key…', 'native-tables-charts' ); ?>"><input type="text" name="meta_value" value="<?php echo esc_attr( implode( ',', (array) ( $post_cfg['meta_value'] ?? array() ) ) ); ?>" placeholder="<?php esc_attr_e( 'Value meta keys (CSV)…', 'native-tables-charts' ); ?>"><button class="button"><?php esc_html_e( 'Save Post Source', 'native-tables-charts' ); ?></button></form> <?php endif; ?> <form method="post" class="ntc-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this dataset and its synced views?', 'native-tables-charts' ) ); ?>')"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="delete_dataset"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button button-link-delete"><?php esc_html_e( 'Delete', 'native-tables-charts' ); ?></button></form></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	public function presets_page(): void {
		$custom = $this->repo->list_presets();
		$tables = NTC_Renderer::table_presets();
		$charts = NTC_Renderer::chart_presets();
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Style Presets', 'native-tables-charts' ); ?></h1><p><?php esc_html_e( 'Built-in presets are always available in Gutenberg. Editors can also save the current table or chart style as a custom preset from the block toolbar.', 'native-tables-charts' ); ?></p>
		<?php
		if ( isset( $_GET['import_error'] ) ) :
			?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'The preset could not be imported. Use a Native Tables & Charts preset JSON file.', 'native-tables-charts' ); ?></p></div><?php endif; ?>
		<form method="post" enctype="multipart/form-data" class="ntc-preset-import"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="import_preset"><label><strong><?php esc_html_e( 'Import style preset', 'native-tables-charts' ); ?></strong> <input type="file" name="preset_file" accept=".json,application/json" required></label> <button class="button"><?php esc_html_e( 'Import Preset', 'native-tables-charts' ); ?></button></form>
		<div class="ntc-cards"><section><h2><?php esc_html_e( 'Built-in Table Styles', 'native-tables-charts' ); ?></h2>
		<?php
		foreach ( $tables as $slug => $settings ) :
			?>
			<div class="ntc-preset-row"><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $slug ) ) ); ?></strong><code><?php echo esc_html( $slug ); ?></code></div><?php endforeach; ?></section><section><h2><?php esc_html_e( 'Built-in Chart Styles', 'native-tables-charts' ); ?></h2>
			<?php
			foreach ( $charts as $slug => $settings ) :
				?>
			<div class="ntc-preset-row"><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $slug ) ) ); ?></strong><code><?php echo esc_html( $slug ); ?></code></div><?php endforeach; ?></section></div>
		<h2><?php esc_html_e( 'Custom Presets', 'native-tables-charts' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Type', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Slug', 'native-tables-charts' ); ?></th><th></th></tr></thead><tbody>
		<?php
		if ( ! $custom ) :
			?>
			<tr><td colspan="4"><?php esc_html_e( 'No custom presets yet.', 'native-tables-charts' ); ?></td></tr><?php endif; ?>
			<?php
			foreach ( $custom as $p ) :
				?>
			<tr><td><?php echo esc_html( $p['name'] ); ?></td><td><?php echo esc_html( $p['type'] ); ?></td><td><code><?php echo esc_html( $p['slug'] ); ?></code></td><td><form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="export_preset"><input type="hidden" name="preset_id" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="button"><?php esc_html_e( 'Export', 'native-tables-charts' ); ?></button></form> <form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="delete_preset"><input type="hidden" name="preset_id" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="button button-link-delete"><?php esc_html_e( 'Delete', 'native-tables-charts' ); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
		<?php
	}

	public function tools_page(): void {
		$result = get_transient( 'ntc_tools_result_' . get_current_user_id() );
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Import & Export', 'native-tables-charts' ); ?></h1><p><?php esc_html_e( 'Create a complete portable backup of reusable datasets, synced table/chart views and custom presets, or import a backup created by this plugin.', 'native-tables-charts' ); ?></p>
		<div class="ntc-cards"><section><h2><?php esc_html_e( 'Export Complete Backup', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Exports all reusable datasets and their synced views. Custom presets are included when you have permission to manage presets. Post content is not changed.', 'native-tables-charts' ); ?></p><form method="post"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="export_all_bundle"><button class="button button-primary"><?php esc_html_e( 'Download Complete JSON Backup', 'native-tables-charts' ); ?></button></form></section>
		<section><h2><?php esc_html_e( 'Import Native Backup', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Imports complete backups, single-dataset bundles, or raw Native Tables & Charts JSON data exports. Imported items are created as new records; existing datasets are not overwritten.', 'native-tables-charts' ); ?></p><form method="post" enctype="multipart/form-data"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="import_native_bundle"><input type="file" name="native_bundle" accept=".json,application/json" required><p><button class="button"><?php esc_html_e( 'Import JSON', 'native-tables-charts' ); ?></button></p></form></section></div>
		<?php
		if ( is_array( $result ) ) :
			?>
			<h2><?php esc_html_e( 'Latest Import Result', 'native-tables-charts' ); ?></h2><pre class="ntc-result"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT ) ); ?></pre><?php endif; ?></div>
		<?php
	}

	public function migration_page(): void {
		$migration_progress = get_transient( 'ntc_migration_progress_' . get_current_user_id() );
		$detect             = $this->migrator->detect( ! is_array( $migration_progress ) || ! empty( $migration_progress['done'] ), ! is_array( $migration_progress ) || ! empty( $migration_progress['done'] ) );
		$report             = get_transient( 'ntc_migration_report_' . get_current_user_id() );
		$result             = get_transient( 'ntc_migration_result_' . get_current_user_id() );
		$rollback_progress  = get_transient( 'ntc_rollback_progress_' . get_current_user_id() );
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'League Table Migration', 'native-tables-charts' ); ?></h1><div class="ntc-callout <?php echo $detect['available'] ? 'is-good' : 'is-neutral'; ?>"><strong><?php echo $detect['available'] ? esc_html__( 'League Table data detected.', 'native-tables-charts' ) : esc_html__( 'No League Table data detected.', 'native-tables-charts' ); ?></strong>
		<?php
		if ( $detect['available'] ) :
			?>
			<?php /* translators: 1: number of tables, 2: number of posts, 3: number of shortcode/block instances. */ ?>
			<p><?php printf( esc_html__( '%1$d tables, %2$d posts and %3$d shortcode/block instances were found.', 'native-tables-charts' ), $detect['tables'], $detect['posts'], $detect['instances'] ); ?></p><?php endif; ?></div>
		<?php
		if ( $detect['available'] ) :
			?>
			<div class="ntc-cards"><section><h2><?php esc_html_e( '1. Dry Run', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Analyze the existing League Table database before changing anything.', 'native-tables-charts' ); ?></p><form method="post"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_dry_run"><button class="button button-secondary"><?php esc_html_e( 'Run Dry-Run Report', 'native-tables-charts' ); ?></button></form></section><section><h2><?php esc_html_e( '2. Migrate', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Create reusable Native Tables datasets and views. Existing League Table database tables are never deleted.', 'native-tables-charts' ); ?></p><form method="post" id="ntc-migrate-form" onsubmit="return this.elements.batch_id.value!==''||confirm('<?php echo esc_js( __( 'Start the migration now? A backup of every changed post will be kept for rollback.', 'native-tables-charts' ) ); ?>')"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_run"><label><input type="checkbox" name="convert_content" value="1" <?php checked( ! is_array( $migration_progress ) || ! empty( $migration_progress['convert'] ) ); ?>> <?php esc_html_e( 'Replace [lt] shortcodes and dalt/table blocks in post content', 'native-tables-charts' ); ?></label><input type="hidden" name="batch_id" value="<?php echo esc_attr( $migration_progress['batch_id'] ?? '' ); ?>"><input type="hidden" name="offset" value="<?php echo esc_attr( $migration_progress['offset'] ?? '0' ); ?>"><input type="hidden" name="cursor" value="<?php echo esc_attr( $migration_progress['cursor'] ?? '0' ); ?>"><input type="hidden" name="table_cursor" value="<?php echo esc_attr( $migration_progress['table_cursor'] ?? '0' ); ?>"><p><button class="button button-primary"><?php esc_html_e( 'Run Migration', 'native-tables-charts' ); ?></button></p></form></section></div><?php endif; ?>
		<div class="ntc-cards"><section><h2><?php esc_html_e( 'Import League Table XML', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'If you exported tables from League Table on another site, import its XML backup directly into Native Tables & Charts. This creates reusable datasets and synced table views without requiring the old plugin to be active.', 'native-tables-charts' ); ?></p><form method="post" enctype="multipart/form-data"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_xml_import"><input type="file" name="league_xml" accept=".xml,text/xml,application/xml" required><p><button class="button"><?php esc_html_e( 'Import XML', 'native-tables-charts' ); ?></button></p></form></section></div>
		<?php
		if ( is_array( $report ) ) :
			?>
			<h2><?php esc_html_e( 'Dry-Run Report', 'native-tables-charts' ); ?></h2><table class="widefat striped ntc-report"><tbody>
			<?php
			foreach ( array(
				'tables'         => 'Tables',
				'posts'          => 'Posts using League Table',
				'instances'      => 'Shortcode/block instances',
				'formula_tables' => 'Tables using formulas',
				'merged_tables'  => 'Tables using merged cells',
				'html_cells'     => 'HTML cells',
				'image_cells'    => 'Image cells',
				'link_cells'     => 'Linked cells',
			) as $k => $label ) :
				?>
	<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $report[ $k ] ?? 0 ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
		<?php
		if ( is_array( $result ) || ! empty( $rollback_progress['batch_id'] ) ) :
			if ( is_array( $result ) ) :
				?>
			<h2><?php esc_html_e( 'Latest Migration Result', 'native-tables-charts' ); ?></h2><pre class="ntc-result"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT ) ); ?></pre>
				<?php
			endif;
			if ( ! empty( $result['batch_id'] ) || ! empty( $rollback_progress['batch_id'] ) ) :
				?>
			<form method="post" id="ntc-rollback-form" onsubmit="return this.elements.offset.value!=0||confirm('<?php echo esc_js( __( 'Restore the original post content from this migration batch?', 'native-tables-charts' ) ); ?>')"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_rollback"><input type="hidden" name="batch_id" value="<?php echo esc_attr( ( $rollback_progress['batch_id'] ?? ( $result['batch_id'] ?? '' ) ) ); ?>"><input type="hidden" name="offset" value="<?php echo esc_attr( $rollback_progress['offset'] ?? '0' ); ?>"><button class="button"><?php esc_html_e( 'Rollback This Batch', 'native-tables-charts' ); ?></button></form>
				<?php
			endif;
		endif;
		?>
		<?php if ( is_array( $migration_progress ) && empty( $migration_progress['done'] ) ) : ?>
			<?php if ( 'tables' === ( $migration_progress['phase'] ?? 'tables' ) ) : ?>
				<?php /* translators: 1: number of legacy tables scanned so far, 2: total legacy tables. */ ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'Migration in progress — %1$d of %2$d legacy tables scanned. Keep this tab open.', 'native-tables-charts' ), (int) $migration_progress['table_offset'], (int) $migration_progress['table_total'] ); ?></p></div>
			<?php else : ?>
				<?php /* translators: %d: number of posts processed so far. */ ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'Migration in progress — %d posts processed. Keep this tab open.', 'native-tables-charts' ), (int) $migration_progress['offset'] ); ?></p></div>
			<?php endif; ?>
		<script>(function(){setTimeout(function(){var f=document.getElementById('ntc-migrate-form');if(f){(f.requestSubmit||f.submit).call(f);}},600);})();</script>
		<?php endif; ?>
		<?php if ( is_array( $rollback_progress ) && empty( $rollback_progress['done'] ) ) : ?>
			<?php /* translators: 1: number of posts restored so far, 2: total posts to restore. */ ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'Rollback in progress — %1$d of %2$d posts restored. Keep this tab open.', 'native-tables-charts' ), (int) $rollback_progress['offset'], (int) $rollback_progress['total'] ); ?></p></div>
		<script>(function(){setTimeout(function(){var f=document.getElementById('ntc-rollback-form');if(f){(f.requestSubmit||f.submit).call(f);}},600);})();</script>
		<?php endif; ?>
		</div>
		<?php
	}

	public function settings_page(): void {
		$defaults = NTC_Renderer::cell_feature_defaults();
		$saved    = array_merge( $defaults, (array) get_option( 'ntc_cell_features', array() ) );
		$labels   = array(
			'textColor'        => __( 'Text colour', 'native-tables-charts' ),
			'backgroundColor'  => __( 'Background colour', 'native-tables-charts' ),
			'alignment'        => __( 'Alignment', 'native-tables-charts' ),
			'fontWeight'       => __( 'Font weight', 'native-tables-charts' ),
			'fontStyle'        => __( 'Font style', 'native-tables-charts' ),
			'link'             => __( 'Cell link', 'native-tables-charts' ),
			'linkColor'        => __( 'Link colour', 'native-tables-charts' ),
			'openLinkNewTab'   => __( 'Open cell link in new tab', 'native-tables-charts' ),
			'imageLeft'        => __( 'Left image', 'native-tables-charts' ),
			'imageLeftLink'    => __( 'Left image link', 'native-tables-charts' ),
			'imageLeftNewTab'  => __( 'Open left image link in new tab', 'native-tables-charts' ),
			'imageRight'       => __( 'Right image', 'native-tables-charts' ),
			'imageRightLink'   => __( 'Right image link', 'native-tables-charts' ),
			'imageRightNewTab' => __( 'Open right image link in new tab', 'native-tables-charts' ),
			'formula'          => __( 'Formula', 'native-tables-charts' ),
			'formulaData'      => __( 'Formula data', 'native-tables-charts' ),
			'html'             => __( 'Custom HTML', 'native-tables-charts' ),
			'rowSpan'          => __( 'Row span / merging', 'native-tables-charts' ),
			'columnSpan'       => __( 'Column span / merging', 'native-tables-charts' ),
		);
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Native Tables & Charts Settings', 'native-tables-charts' ); ?></h1>
		<?php
		if ( isset( $_GET['saved'] ) ) :
			?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'native-tables-charts' ); ?></p></div><?php endif; ?><form method="post"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="save_settings">
		<h2><?php esc_html_e( 'Editor Cell Properties', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Choose which advanced cell controls are shown to editors. Existing saved cell properties continue to render even when a control is hidden.', 'native-tables-charts' ); ?></p><div class="ntc-setting-grid">
		<?php
		foreach ( $labels as $key => $label ) :
			?>
			<label><input type="checkbox" name="cell_features[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $saved[ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></div>
		<h2><?php esc_html_e( 'Custom HTML Safety', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( "Custom HTML cells are always sanitized. Leave these fields blank to use WordPress' normal allowed post HTML and protocols, or enter a League Table-style restricted allow-list.", 'native-tables-charts' ); ?></p><table class="form-table"><tr><th scope="row"><label for="ntc-kses-tags"><?php esc_html_e( 'Allowed HTML tags', 'native-tables-charts' ); ?></label></th><td><input id="ntc-kses-tags" class="regular-text" type="text" name="kses_allowed_html_tags" value="<?php echo esc_attr( (string) get_option( 'ntc_kses_allowed_html_tags', '' ) ); ?>" placeholder="a[href][title], br, em, strong"><p class="description"><?php esc_html_e( 'Example: a[href][title], br, em, strong', 'native-tables-charts' ); ?></p></td></tr><tr><th scope="row"><label for="ntc-kses-protocols"><?php esc_html_e( 'Allowed link protocols', 'native-tables-charts' ); ?></label></th><td><input id="ntc-kses-protocols" class="regular-text" type="text" name="kses_allowed_protocols" value="<?php echo esc_attr( (string) get_option( 'ntc_kses_allowed_protocols', '' ) ); ?>" placeholder="http, https, mailto"></td></tr></table>
		<h2><?php esc_html_e( 'Uninstall', 'native-tables-charts' ); ?></h2><table class="form-table"><tr><th scope="row"><?php esc_html_e( 'Uninstall behaviour', 'native-tables-charts' ); ?></th><td><label><input type="checkbox" name="delete_data" value="1" <?php checked( (bool) get_option( 'ntc_delete_data_on_uninstall', false ) ); ?>> <?php esc_html_e( 'Delete Native Tables & Charts datasets, views, presets and migration backups when the plugin is uninstalled. Leave this off on production sites unless you intentionally want to remove all plugin data.', 'native-tables-charts' ); ?></label></td></tr></table><?php submit_button(); ?></form></div>
		<?php
	}
}
