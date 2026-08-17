<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NTC_Activator {
	public static function activate(): void {
		self::create_tables();
		self::add_caps();
		if ( ! wp_next_scheduled( 'ntc_sync_remote_datasets' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'ntc_sync_remote_datasets' );
		}
		update_option( 'ntc_db_version', NTC_VERSION );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'ntc_sync_remote_datasets' );
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$datasets = $wpdb->prefix . 'ntc_datasets';
		$rows     = $wpdb->prefix . 'ntc_rows';
		$views    = $wpdb->prefix . 'ntc_views';
		$presets  = $wpdb->prefix . 'ntc_presets';
		$backups  = $wpdb->prefix . 'ntc_backups';

		dbDelta( "CREATE TABLE {$datasets} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT NULL,
			columns_json LONGTEXT NOT NULL,
			author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			source_url VARCHAR(2048) NOT NULL DEFAULT '',
			source_last_sync DATETIME NULL,
			source_error TEXT NULL,
			PRIMARY KEY (id),
			KEY updated_at (updated_at),
			KEY author_id (author_id)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$rows} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dataset_id BIGINT UNSIGNED NOT NULL,
			row_index INT UNSIGNED NOT NULL,
			row_json LONGTEXT NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY dataset_row (dataset_id,row_index),
			KEY dataset_id (dataset_id)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$views} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dataset_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(255) NOT NULL DEFAULT '',
			type VARCHAR(20) NOT NULL DEFAULT 'table',
			config_json LONGTEXT NOT NULL,
			author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY dataset_id (dataset_id),
			KEY type (type)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$presets} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			type VARCHAR(24) NOT NULL,
			settings_json LONGTEXT NOT NULL,
			author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug_type (slug,type)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$backups} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id VARCHAR(64) NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			original_content LONGTEXT NOT NULL,
			migrated_content LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY batch_id (batch_id),
			KEY post_id (post_id)
		) {$charset};" );
	}

	private static function add_caps(): void {
		$caps = array(
			'ntc_create_datasets', 'ntc_edit_datasets', 'ntc_delete_datasets',
			'ntc_manage_presets', 'ntc_import', 'ntc_export', 'ntc_manage_settings', 'ntc_migrate',
		);
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) { continue; }
			foreach ( $caps as $cap ) {
				if ( 'editor' === $role_name && in_array( $cap, array( 'ntc_manage_settings', 'ntc_migrate' ), true ) ) { continue; }
				$role->add_cap( $cap );
			}
		}
	}

	public static function uninstall(): void {
		wp_clear_scheduled_hook( 'ntc_sync_remote_datasets' );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ntc_lt_view_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::remove_caps();
		if ( ! get_option( 'ntc_delete_data_on_uninstall', false ) ) { return; }
		global $wpdb;
		foreach ( array( 'ntc_rows', 'ntc_views', 'ntc_presets', 'ntc_datasets', 'ntc_backups' ) as $suffix ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$suffix}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		delete_option( 'ntc_db_version' );
		delete_option( 'ntc_delete_data_on_uninstall' );
		delete_option( 'ntc_migration_map' );
		delete_option( 'ntc_cell_features' );
		delete_option( 'ntc_kses_allowed_html_tags' );
		delete_option( 'ntc_kses_allowed_protocols' );
	}

	private static function remove_caps(): void {
		$caps = array(
			'ntc_create_datasets', 'ntc_edit_datasets', 'ntc_delete_datasets',
			'ntc_manage_presets', 'ntc_import', 'ntc_export', 'ntc_manage_settings', 'ntc_migrate',
		);
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) { continue; }
			foreach ( $caps as $cap ) { $role->remove_cap( $cap ); }
		}
	}

}
