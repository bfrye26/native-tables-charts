<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NTC_Sync {
	const MAX_BYTES = 2 * MB_IN_BYTES;

	public static function register(): void {
		add_action( 'ntc_sync_remote_datasets', array( __CLASS__, 'cron_run' ) );
	}

	public static function fetch( string $url ): string {
		if ( ! preg_match( '#^https?://#i', $url ) ) { throw new Exception( 'Invalid source URL.' ); }
		$response = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'sslverify' => true, 'user-agent' => 'NativeTablesAndCharts/' . NTC_VERSION ) );
		if ( is_wp_error( $response ) ) { throw new Exception( $response->get_error_message() ); }
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) { throw new Exception( 'Source returned HTTP ' . $code . '.' ); }
		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BYTES ) { throw new Exception( 'Source file exceeds the 2 MB limit.' ); }
		return $body;
	}

	public static function sync_dataset( NTC_Repository $repo, int $id ): array {
		$source = $repo->get_source( $id );
		if ( ! $source || '' === trim( (string) $source['source_url'] ) ) { return array( 'success' => false, 'error' => 'No source URL configured.' ); }
		try {
			$body = self::fetch( (string) $source['source_url'] );
			$parsed = self::parse( $repo, $body, str_contains( $body, "\t" ) ? 'tsv' : 'csv' );
			if ( ! $parsed['columns'] ) { throw new Exception( 'No usable data found in source.' ); }
			$repo->replace_rows( $id, $parsed['rows'] );
			$repo->update_dataset( $id, array( 'columns' => $parsed['columns'] ) );
			$repo->record_sync( $id );
			return array( 'success' => true );
		} catch ( Throwable $e ) {
			$repo->record_sync( $id, $e->getMessage() );
			return array( 'success' => false, 'error' => $e->getMessage() );
		}
	}

	public static function cron_run(): void {
		global $wpdb;
		$repo = new NTC_Repository();
		$ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}ntc_datasets WHERE source_url <> '' ORDER BY id ASC LIMIT 25" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $ids as $id ) { self::sync_dataset( $repo, (int) $id ); }
	}

	public static function parse( NTC_Repository $repo, string $raw, string $format ): array {
		if ( 'json' === $format ) {
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) { throw new Exception( 'Invalid JSON.' ); }
			if ( isset( $data['columns'], $data['rows'] ) ) {
				$cols = $repo->sanitize_columns( (array) $data['columns'] );
				$width = count( $cols );
				$rows = array_slice( (array) $data['rows'], 0, 10000 );
				$rows = array_map( fn( $row ) => array_slice( array_pad( (array) $row, $width, '' ), 0, $width ), $rows );
				return array( 'columns' => $cols, 'rows' => $rows );
			}
			$rows = array_slice( array_values( $data ), 0, 10000 );
			if ( ! $rows ) { return array( 'columns' => array(), 'rows' => array() ); }
			if ( array_is_list( $rows[0] ) ) {
				$width = min( 40, count( $rows[0] ) );
				if ( $width < 1 ) { return array( 'columns' => array(), 'rows' => array() ); }
				$cols = array_map( fn( $i ) => array( 'id' => 'c' . ( $i + 1 ), 'label' => 'Column ' . ( $i + 1 ), 'type' => 'auto', 'unit' => '' ), range( 0, $width - 1 ) );
				$rows = array_map( fn( $row ) => array_slice( array_pad( (array) $row, $width, '' ), 0, $width ), $rows );
				return array( 'columns' => $cols, 'rows' => $rows );
			}
			$keys = array_slice( array_keys( (array) $rows[0] ), 0, 40 );
			$cols = array_map( fn( $k ) => array( 'id' => sanitize_key( (string) $k ), 'label' => sanitize_text_field( (string) $k ), 'type' => 'auto', 'unit' => '' ), $keys );
			return array( 'columns' => $cols, 'rows' => array_map( fn( $r ) => array_map( fn( $k ) => ( array_key_exists( $k, (array) $r ) ? $r[ $k ] : '' ), $keys ), $rows ) );
		}
		$delimiter = 'tsv' === $format ? "\t" : ',';
		$matrix = array();
		$fh = fopen( 'php://temp', 'r+' );
		if ( false === $fh ) { throw new Exception( 'Could not parse the import.' ); }
		fwrite( $fh, $raw );
		rewind( $fh );
		while ( false !== ( $record = fgetcsv( $fh, 0, $delimiter, '"', '' ) ) ) {
			if ( 1 === count( $record ) && '' === trim( (string) $record[0] ) ) { continue; }
			$matrix[] = $record;
			if ( count( $matrix ) > 10001 ) { break; }
		}
		fclose( $fh );
		if ( ! $matrix ) { return array( 'columns' => array(), 'rows' => array() ); }
		$header = array_slice( array_shift( $matrix ), 0, 40 );
		$cols = array();
		foreach ( $header as $i => $h ) { $cols[] = array( 'id' => sanitize_key( $h ?: 'c' . ( $i + 1 ) ), 'label' => sanitize_text_field( $h ?: 'Column ' . ( $i + 1 ) ), 'type' => 'auto', 'unit' => '' ); }
		$width = count( $cols );
		$rows = array_slice( array_map( fn( $row ) => array_slice( array_pad( (array) $row, $width, '' ), 0, $width ), $matrix ), 0, 10000 );
		return array( 'columns' => $cols, 'rows' => $rows );
	}
}
