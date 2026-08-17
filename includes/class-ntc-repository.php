<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Repository {
	public function list_datasets( int $limit = 100, int $offset = 0, string $search = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ntc_datasets';
		$where = '';
		$args  = array();
		if ( '' !== $search ) {
			$where  = 'WHERE name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$sql    = "SELECT d.*, (SELECT COUNT(*) FROM {$wpdb->prefix}ntc_rows r WHERE r.dataset_id=d.id) AS row_count, (SELECT COUNT(*) FROM {$wpdb->prefix}ntc_views v WHERE v.dataset_id=d.id) AS view_count FROM {$table} d {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
		$args[] = max( 1, min( 500, $limit ) );
		$args[] = max( 0, $offset );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $rows ? $rows : array();
	}

	public function get_dataset( int $id, bool $include_rows = false ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntc_datasets WHERE id=%d", $id ), ARRAY_A );
		if ( ! $row ) {
			return null; }
		$row['columns'] = $this->decode( $row['columns_json'], array() );
		unset( $row['columns_json'] );
		if ( $include_rows ) {
			$row['rows'] = $this->get_rows( $id );
		}
		return $row;
	}

	public function create_dataset( string $name, array $columns, array $rows = array(), string $description = '' ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$wpdb->prefix . 'ntc_datasets',
			array(
				'name'         => sanitize_text_field( $name ? $name : __( 'Untitled Dataset', 'native-tables-charts' ) ),
				'description'  => sanitize_textarea_field( $description ),
				'columns_json' => wp_json_encode( $this->sanitize_columns( $columns ) ),
				'author_id'    => get_current_user_id(),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		if ( $id && $rows ) {
			$this->replace_rows( $id, $rows ); }
		return $id;
	}

	public function update_dataset( int $id, array $data ): bool {
		global $wpdb;
		$update  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );
		if ( array_key_exists( 'name', $data ) ) {
			$update['name'] = sanitize_text_field( (string) $data['name'] );
			$formats[]      = '%s'; }
		if ( array_key_exists( 'description', $data ) ) {
			$update['description'] = sanitize_textarea_field( (string) $data['description'] );
			$formats[]             = '%s'; }
		if ( array_key_exists( 'columns', $data ) ) {
			$update['columns_json'] = wp_json_encode( $this->sanitize_columns( (array) $data['columns'] ) );
			$formats[]              = '%s'; }
		return false !== $wpdb->update( $wpdb->prefix . 'ntc_datasets', $update, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	public function delete_dataset( int $id ): bool {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'ntc_rows', array( 'dataset_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ntc_views', array( 'dataset_id' => $id ), array( '%d' ) );
		return false !== $wpdb->delete( $wpdb->prefix . 'ntc_datasets', array( 'id' => $id ), array( '%d' ) );
	}

	public function get_source( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT source_url,source_last_sync,source_error FROM {$wpdb->prefix}ntc_datasets WHERE id=%d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	public function set_source( int $id, string $url ): bool {
		global $wpdb;
		$url = trim( $url );
		if ( '' !== $url && ! preg_match( '#^https?://#i', $url ) ) {
			$url = ''; }
		return false !== $wpdb->update( $wpdb->prefix . 'ntc_datasets', array( 'source_url' => $url ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	public function record_sync( int $id, string $error = '' ): bool {
		global $wpdb;
		return false !== $wpdb->update(
			$wpdb->prefix . 'ntc_datasets',
			array(
				'source_last_sync' => current_time( 'mysql', true ),
				'source_error'     => '' !== $error ? $error : null,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public function get_rows( int $dataset_id, int $limit = 0, int $offset = 0 ): array {
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT row_index,row_json FROM {$wpdb->prefix}ntc_rows WHERE dataset_id=%d ORDER BY row_index ASC", $dataset_id );
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', min( 10000, $limit ), max( 0, $offset ) );
		}
		$items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $items ) {
			$items = array();
		}
		$out = array();
		foreach ( $items as $item ) {
			$out[] = $this->decode( $item['row_json'], array() ); }
		return $out;
	}

	public function row_count( int $dataset_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ntc_rows WHERE dataset_id=%d", $dataset_id ) );
	}

	public function replace_rows( int $dataset_id, array $rows ): bool {
		global $wpdb;
		$rows = array_slice( array_values( $rows ), 0, 10000 );
		$wpdb->query( 'START TRANSACTION' );
		$deleted = $wpdb->delete( $wpdb->prefix . 'ntc_rows', array( 'dataset_id' => $dataset_id ), array( '%d' ) );
		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return false; }
		$ok = $this->upsert_rows( $dataset_id, $rows, 0 );
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return false; }
		$wpdb->query( 'COMMIT' );
		$this->touch_dataset( $dataset_id );
		return true;
	}

	public function upsert_rows( int $dataset_id, array $rows, int $start_index = 0 ): bool {
		global $wpdb;
		$table       = $wpdb->prefix . 'ntc_rows';
		$now         = current_time( 'mysql', true );
		$start_index = max( 0, min( 9999, $start_index ) );
		$rows        = array_slice( array_values( $rows ), 0, max( 0, 10000 - $start_index ) );
		foreach ( $rows as $i => $row ) {
			$index = $start_index + $i;
			$json  = wp_json_encode( $this->sanitize_row( is_array( $row ) ? $row : array( $row ) ) );
			$sql   = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
				"INSERT INTO {$table} (dataset_id,row_index,row_json,updated_at) VALUES (%d,%d,%s,%s) ON DUPLICATE KEY UPDATE row_json=VALUES(row_json),updated_at=VALUES(updated_at)",
				$dataset_id,
				$index,
				$json,
				$now
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is prepared above with a table identifier interpolated.
			if ( false === $wpdb->query( $sql ) ) {
				return false; } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$this->touch_dataset( $dataset_id );
		return true;
	}

	public function patch_rows( int $dataset_id, array $indexed_rows ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'ntc_rows';
		$now   = current_time( 'mysql', true );
		foreach ( $indexed_rows as $index => $row ) {
			$idx = absint( $index );
			if ( $idx >= 10000 ) {
				continue; }
			$json = wp_json_encode( $this->sanitize_row( is_array( $row ) ? $row : array( $row ) ) );
			$sql  = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
				"INSERT INTO {$table} (dataset_id,row_index,row_json,updated_at) VALUES (%d,%d,%s,%s) ON DUPLICATE KEY UPDATE row_json=VALUES(row_json),updated_at=VALUES(updated_at)",
				$dataset_id,
				$idx,
				$json,
				$now
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is prepared above with a table identifier interpolated.
			if ( false === $wpdb->query( $sql ) ) {
				return false; } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$this->touch_dataset( $dataset_id );
		return true;
	}

	public function get_view( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntc_views WHERE id=%d", $id ), ARRAY_A );
		if ( ! $row ) {
			return null; }
		$row['config'] = $this->decode( $row['config_json'], array() );
		unset( $row['config_json'] );
		return $row;
	}

	public function list_views( ?int $dataset_id = null, string $type = '' ): array {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array();
		if ( null !== $dataset_id ) {
			$where[] = 'dataset_id=%d';
			$args[]  = $dataset_id; }
		if ( '' !== $type ) {
			$where[] = 'type=%s';
			$args[]  = $type; }
		$sql = "SELECT * FROM {$wpdb->prefix}ntc_views WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC';
		if ( $args ) {
			$sql = $wpdb->prepare( $sql, $args ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built from internal whitelisted fragments.
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			$rows = array();
		}
		foreach ( $rows as &$row ) {
			$row['config'] = $this->decode( $row['config_json'], array() );
			unset( $row['config_json'] ); }
		return $rows;
	}

	public function create_view( int $dataset_id, string $type, string $name, array $config ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$wpdb->prefix . 'ntc_views',
			array(
				'dataset_id'  => $dataset_id,
				'name'        => sanitize_text_field( $name ? $name : ucfirst( $type ) ),
				'type'        => in_array( $type, array( 'table', 'chart' ), true ) ? $type : 'table',
				'config_json' => wp_json_encode( $this->sanitize_config( $config ) ),
				'author_id'   => get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function update_view( int $id, array $data ): bool {
		global $wpdb;
		$update  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );
		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$formats[]      = '%s'; }
		if ( isset( $data['config'] ) ) {
			$update['config_json'] = wp_json_encode( $this->sanitize_config( (array) $data['config'] ) );
			$formats[]             = '%s'; }
		if ( isset( $data['dataset_id'] ) ) {
			$update['dataset_id'] = absint( $data['dataset_id'] );
			$formats[]            = '%d'; }
		return false !== $wpdb->update( $wpdb->prefix . 'ntc_views', $update, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	public function delete_view( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $wpdb->prefix . 'ntc_views', array( 'id' => $id ), array( '%d' ) );
	}

	public function get_preset( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntc_presets WHERE id=%d", $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}$row['settings'] = $this->decode( $row['settings_json'], array() );
		unset( $row['settings_json'] );
		return $row;
	}

	public function list_presets( string $type = '' ): array {
		global $wpdb;
		$sql = "SELECT * FROM {$wpdb->prefix}ntc_presets";
		if ( '' !== $type ) {
			$sql .= $wpdb->prepare( ' WHERE type=%s', $type ); }
		$sql .= ' ORDER BY name ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			$rows = array();
		}
		foreach ( $rows as &$row ) {
			$row['settings'] = $this->decode( $row['settings_json'], array() );
			unset( $row['settings_json'] ); }
		return $rows;
	}

	public function create_preset( string $type, string $name, array $settings ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'ntc_presets';
		$type  = sanitize_key( $type );
		$base  = sanitize_title( $name );
		$slug  = $base;
		$i     = 2;
		// phpcs:ignore WordPress.DB.PreparedSQL -- table names are internal identifiers, cannot be prepared.
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s AND type=%s", $slug, $type ) ) ) {
			$slug = $base . '-' . $i;
			++$i;
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'name'          => sanitize_text_field( $name ),
				'slug'          => $slug,
				'type'          => $type,
				'settings_json' => wp_json_encode( $this->sanitize_config( $settings ) ),
				'author_id'     => get_current_user_id(),
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function delete_preset( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $wpdb->prefix . 'ntc_presets', array( 'id' => $id ), array( '%d' ) );
	}

	private function touch_dataset( int $dataset_id ): void {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'ntc_datasets', array( 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $dataset_id ), array( '%s' ), array( '%d' ) );
	}

	public function sanitize_columns( array $columns ): array {
		$out = array();
		foreach ( array_slice( array_values( $columns ), 0, 40 ) as $i => $col ) {
			if ( ! is_array( $col ) ) {
				$col = array( 'label' => (string) $col ); }
			$out[] = array(
				'id'     => sanitize_key( $col['id'] ?? 'c' . ( $i + 1 ) ),
				'label'  => sanitize_text_field( $col['label'] ?? 'Column ' . ( $i + 1 ) ),
				'type'   => sanitize_key( $col['type'] ?? 'auto' ),
				'unit'   => sanitize_text_field( $col['unit'] ?? '' ),
				'format' => sanitize_key( $col['format'] ?? '' ),
			);
		}
		return $out;
	}

	public function sanitize_row( array $row ): array {
		// ponytail: cell data is escaped at render time; kses here would corrupt literal values like "x < y".
		$out = array();
		foreach ( array_slice( array_values( $row ), 0, 40 ) as $cell ) {
			if ( is_scalar( $cell ) || null === $cell ) {
				$out[] = wp_check_invalid_utf8( str_replace( "\0", '', (string) $cell ), true ); } else {
				$out[] = wp_json_encode( $cell ); }
		}
		return $out;
	}

	public function sanitize_config( array $config ): array {
		// Config values are recursively sanitized while retaining a JSON-friendly structure.
		$clean = function ( $value ) use ( &$clean ) {
			if ( is_array( $value ) ) {
				$out = array();
				foreach ( $value as $k => $v ) {
					$safe_key         = preg_replace( '/[^A-Za-z0-9_:\-]/', '', (string) $k );
					$out[ $safe_key ] = $clean( $v );
				} return $out; }
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value; }
			return wp_kses_post( (string) $value );
		};
		return $clean( $config );
	}

	private function decode( string $json, array $fallback ): array {
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : $fallback;
	}
}
