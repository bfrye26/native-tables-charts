<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_REST {
	private NTC_Repository $repo;
	public function __construct( NTC_Repository $repo ) {
		$this->repo = $repo; }

	public function register(): void {
		register_rest_route(
			'ntc/v1',
			'/datasets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'datasets_list' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'per_page' => array(
							'sanitize_callback' => 'absint',
							'default'           => 100,
						),
						'offset'   => array(
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'search'   => array(
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'datasets_create' ),
					'permission_callback' => array( $this, 'can_create' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/datasets/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'dataset_get' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'dataset_update' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'dataset_delete' ),
					'permission_callback' => array( $this, 'can_delete' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/datasets/(?P<id>\d+)/rows',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rows_get' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'limit'  => array(
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'offset' => array(
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
					),
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'rows_save' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/views',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'views_list' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'dataset_id' => array( 'sanitize_callback' => 'absint' ),
						'type'       => array(
							'sanitize_callback' => 'sanitize_key',
							'default'           => '',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'views_create' ),
					'permission_callback' => array( $this, 'can_create' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/views/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'view_get' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'view_update' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'view_delete' ),
					'permission_callback' => array( $this, 'can_delete' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/presets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'presets_list' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'preset_create' ),
					'permission_callback' => array( $this, 'can_presets' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/presets/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'preset_delete' ),
					'permission_callback' => array( $this, 'can_presets' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/import',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'import_data' ),
					'permission_callback' => array( $this, 'can_import' ),
				),
			)
		);
		register_rest_route(
			'ntc/v1',
			'/export/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'export_data' ),
					'permission_callback' => array( $this, 'can_export' ),
					'args'                => array(
						'format' => array(
							'sanitize_callback' => 'sanitize_key',
							'default'           => 'json',
						),
					),
				),
			)
		);
	}

	public function can_edit(): bool {
		return current_user_can( 'ntc_edit_datasets' ) || current_user_can( 'manage_options' ); }
	public function can_create(): bool {
		return current_user_can( 'ntc_create_datasets' ) || current_user_can( 'manage_options' ); }
	public function can_delete(): bool {
		return current_user_can( 'ntc_delete_datasets' ) || current_user_can( 'manage_options' ); }
	public function can_presets(): bool {
		return current_user_can( 'ntc_manage_presets' ) || current_user_can( 'manage_options' ); }
	public function can_import(): bool {
		return current_user_can( 'ntc_import' ) || current_user_can( 'manage_options' ); }
	public function can_export(): bool {
		return current_user_can( 'ntc_export' ) || current_user_can( 'manage_options' ); }

	public function datasets_list( WP_REST_Request $r ): WP_REST_Response {
		return rest_ensure_response( $this->repo->list_datasets( (int) ( ! empty( $r['per_page'] ) ? $r['per_page'] : 100 ), (int) ( ! empty( $r['offset'] ) ? $r['offset'] : 0 ), (string) ( ! empty( $r['search'] ) ? $r['search'] : '' ) ) ); }
	public function datasets_create( WP_REST_Request $r ) {
		$p  = $r->get_json_params();
		$id = $this->repo->create_dataset( (string) ( $p['name'] ?? '' ), (array) ( $p['columns'] ?? array() ), (array) ( $p['rows'] ?? array() ), (string) ( $p['description'] ?? '' ) );
		if ( ! $id ) {
			return new WP_Error( 'ntc_create_failed', __( 'Could not create dataset.', 'native-tables-charts' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( $this->repo->get_dataset( $id, true ) );
	}
	public function dataset_get( WP_REST_Request $r ) {
		$d = $this->repo->get_dataset( (int) $r['id'], false );
		return $d ? rest_ensure_response( $d ) : new WP_Error( 'ntc_not_found', __( 'Dataset not found.', 'native-tables-charts' ), array( 'status' => 404 ) );}
	public function dataset_update( WP_REST_Request $r ) {
		$ok = $this->repo->update_dataset( (int) $r['id'], (array) $r->get_json_params() );
		return rest_ensure_response( array( 'success' => $ok ) );}
	public function dataset_delete( WP_REST_Request $r ) {
		return rest_ensure_response( array( 'success' => $this->repo->delete_dataset( (int) $r['id'] ) ) );}
	public function rows_get( WP_REST_Request $r ): WP_REST_Response {
		$id     = (int) $r['id'];
		$limit  = (int) ( ! empty( $r['limit'] ) ? $r['limit'] : 0 );
		$offset = (int) ( ! empty( $r['offset'] ) ? $r['offset'] : 0 );
		return rest_ensure_response(
			array(
				'rows'  => $this->repo->get_rows( $id, $limit, $offset ),
				'total' => $this->repo->row_count( $id ),
			)
		);}
	public function rows_save( WP_REST_Request $r ) {
		$p  = (array) $r->get_json_params();
		$id = (int) $r['id'];
		if ( ! empty( $p['replace'] ) ) {
			$ok = $this->repo->replace_rows( $id, (array) ( $p['rows'] ?? array() ) );
		} elseif ( isset( $p['indexedRows'] ) ) {
			$ok = $this->repo->patch_rows( $id, (array) $p['indexedRows'] );
		} else {
			$ok = $this->repo->upsert_rows( $id, (array) ( $p['rows'] ?? array() ), absint( $p['startIndex'] ?? 0 ) );
		}return rest_ensure_response(
			array(
				'success' => $ok,
				'total'   => $this->repo->row_count( $id ),
			)
		);}
	public function views_list( WP_REST_Request $r ): WP_REST_Response {
		$dataset = isset( $r['dataset_id'] ) ? (int) $r['dataset_id'] : null;
		return rest_ensure_response( $this->repo->list_views( $dataset, (string) ( ! empty( $r['type'] ) ? $r['type'] : '' ) ) );}
	public function views_create( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params();if ( ! $this->repo->get_dataset( absint( $p['dataset_id'] ?? 0 ) ) ) {
			return new WP_Error( 'ntc_not_found', __( 'Dataset not found.', 'native-tables-charts' ), array( 'status' => 404 ) );
		}$id = $this->repo->create_view( absint( $p['dataset_id'] ?? 0 ), (string) ( $p['type'] ?? 'table' ), (string) ( $p['name'] ?? '' ), (array) ( $p['config'] ?? array() ) );
		return rest_ensure_response( $this->repo->get_view( $id ) );}
	public function view_get( WP_REST_Request $r ) {
		$v = $this->repo->get_view( (int) $r['id'] );
		return $v ? rest_ensure_response( $v ) : new WP_Error( 'ntc_not_found', __( 'View not found.', 'native-tables-charts' ), array( 'status' => 404 ) );}
	public function view_update( WP_REST_Request $r ) {
		return rest_ensure_response( array( 'success' => $this->repo->update_view( (int) $r['id'], (array) $r->get_json_params() ) ) );}
	public function view_delete( WP_REST_Request $r ) {
		return rest_ensure_response( array( 'success' => $this->repo->delete_view( (int) $r['id'] ) ) );}
	public function presets_list( WP_REST_Request $r ): WP_REST_Response {
		return rest_ensure_response(
			array(
				'custom'        => $this->repo->list_presets( (string) ( ! empty( $r['type'] ) ? $r['type'] : '' ) ),
				'tableBuiltins' => NTC_Renderer::table_presets(),
				'chartBuiltins' => NTC_Renderer::chart_presets(),
			)
		);}
	public function preset_create( WP_REST_Request $r ) {
		$p  = (array) $r->get_json_params();
		$id = $this->repo->create_preset( (string) ( $p['type'] ?? 'table' ), (string) ( $p['name'] ?? __( 'Custom preset', 'native-tables-charts' ) ), (array) ( $p['settings'] ?? array() ) );
		return rest_ensure_response( array( 'id' => $id ) );}
	public function preset_delete( WP_REST_Request $r ) {
		return rest_ensure_response( array( 'success' => $this->repo->delete_preset( (int) $r['id'] ) ) );}

	public function import_data( WP_REST_Request $r ) {
		$p      = (array) $r->get_json_params();
		$format = sanitize_key( $p['format'] ?? 'csv' );
		$raw    = (string) ( $p['data'] ?? '' );
		$name   = (string) ( $p['name'] ?? __( 'Imported Dataset', 'native-tables-charts' ) );
		try {
			$parsed = NTC_Sync::parse( $this->repo, $raw, $format );
		} catch ( Exception $e ) {
			return new WP_Error( 'ntc_import_error', $e->getMessage(), array( 'status' => 400 ) ); }
		$id = $this->repo->create_dataset( $name, $parsed['columns'], $parsed['rows'] );
		return rest_ensure_response(
			array(
				'id'      => $id,
				'columns' => $parsed['columns'],
				'rows'    => $parsed['rows'],
			)
		);
	}

	public static function guard_csv_cell( string $cell ): string {
		// Prevent spreadsheet formula injection from cells that start with = + - @ (OWASP CSV injection).
		if ( preg_match( '/^[=+\-@]/', $cell ) && ! preg_match( '/^[=+\-@][0-9.,]+$/', $cell ) ) {
			return "'" . $cell;
		}
		return $cell;
	}

	public function export_data( WP_REST_Request $r ) {
		$d = $this->repo->get_dataset( (int) $r['id'], true );
		if ( ! $d ) {
			return new WP_Error( 'ntc_not_found', __( 'Dataset not found.', 'native-tables-charts' ), array( 'status' => 404 ) );
		}$format = sanitize_key( ! empty( $r['format'] ) ? $r['format'] : 'json' );
		if ( 'json' === $format ) {
			return rest_ensure_response(
				array(
					'name'    => $d['name'],
					'columns' => $d['columns'],
					'rows'    => $d['rows'],
				)
			);
		}$delimiter = 'tsv' === $format ? "\t" : ',';
		$fh         = fopen( 'php://temp', 'r+' );
		fputcsv( $fh, array_map( array( __CLASS__, 'guard_csv_cell' ), array_map( fn( $c )=>$c['label'] ?? '', $d['columns'] ) ), $delimiter, '"', '' );
		foreach ( $d['rows'] as $row ) {
			fputcsv( $fh, array_map( array( __CLASS__, 'guard_csv_cell' ), $row ), $delimiter, '"', '' );
		}rewind( $fh );
		$body = stream_get_contents( $fh );
		fclose( $fh );
		return new WP_REST_Response(
			$body,
			200,
			array(
				'Content-Type'        => 'tsv' === $format ? 'text/tab-separated-values' : 'text/csv',
				'Content-Disposition' => 'attachment; filename="' . sanitize_file_name( $d['name'] ) . '.' . $format . '"',
			)
		);}
}
