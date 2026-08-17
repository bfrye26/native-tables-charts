<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Migrator {
	private NTC_Repository $repo;
	public function __construct( NTC_Repository $repo ) {
		$this->repo = $repo;}

	public function detect(): array {
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
		if ( $count ) {
			$posts = $wpdb->get_results( "SELECT ID,post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft') AND (post_content LIKE '%[lt %' OR post_content LIKE '%wp:dalt/table%')", ARRAY_A );
			if ( ! $posts ) {
				$posts = array();
			}
			$post_count = count( $posts );
			foreach ( $posts as $p ) {
				preg_match_all( '/\[lt\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i', $p['post_content'], $m );
				$instance_count += count( $m[0] );
				$instance_count += substr_count( $p['post_content'], 'wp:dalt/table' );}
		}
		return array(
			'available' => $count > 0,
			'tables'    => $count,
			'posts'     => $post_count,
			'instances' => $instance_count,
			'schema'    => $exists,
		);
	}

	public function dry_run(): array {
		global $wpdb;
		$d      = $this->detect();
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

	public function migrate( bool $convert_content = true ): array {
		if ( ! current_user_can( 'ntc_migrate' ) && ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Permission denied.', 'native-tables-charts' ),
			);
		}
		global $wpdb;
		$detect = $this->detect();
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
		$source=$wpdb->prefix.'dalt_table';$tables=$wpdb->get_results("SELECT * FROM {$source} WHERE temporary=0 ORDER BY id ASC",ARRAY_A)?:array(); // phpcs:ignore
		$map     = (array) get_option( 'ntc_migration_map', array() );
		$created = 0;
		$errors  = array();
		foreach ( $tables as $t ) {
			$old = (int) $t['id'];
			if ( isset( $map[ $old ] ) && $this->repo->get_dataset( (int) $map[ $old ], false ) ) {
				continue;
			}try {
				$new = $this->migrate_table( $t );
				if ( $new ) {
					$map[ $old ] = $new;
						++$created;
				}
			} catch ( Throwable $e ) {
				$errors[] = 'Table ' . $old . ': ' . $e->getMessage();}
		}
		update_option( 'ntc_migration_map', $map, false );
		$batch     = 'lt-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
		$posts     = 0;
		$instances = 0;
		if ( $convert_content ) {
			$result    = $this->convert_posts( $map, $batch );
			$posts     = $result['posts'];
			$instances = $result['instances'];
			$errors    = array_merge( $errors, $result['errors'] );}
		return array(
			'success'             => empty( $errors ),
			'datasets_created'    => $created,
			'posts_updated'       => $posts,
			'instances_converted' => $instances,
			'batch_id'            => $batch,
			'errors'              => $errors,
			'map'                 => $map,
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
		$columns = array();
		$count   = max( (int) ( $t['columns'] ?? count( $header ) ), count( $header ) );
		for ( $i = 0;$i < $count;$i++ ) {
			$columns[] = array(
				'id'     => 'c' . ( $i + 1 ),
				'label'  => sanitize_text_field( $header[ $i ] ?? 'Column ' . ( $i + 1 ) ),
				'type'   => 'auto',
				'unit'   => '',
				'format' => '',
			);
		}
		$id = $this->repo->create_dataset( (string) ( ! empty( $t['name'] ) ? $t['name'] : 'League Table ' . $old ), $columns, $matrix, (string) ( $t['description'] ?? '' ) );
		if ( ! $id ) {
			return array(
				'dataset_id' => 0,
				'view_id'    => 0,
			);
		}
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
		$config             = $this->table_config( $t );
		$config['cellMeta'] = $cell_meta;
		$view               = $this->repo->create_view( $id, 'table', (string) ( ! empty( $t['name'] ) ? $t['name'] : 'League Table ' . $old ), $config );
		return array(
			'dataset_id' => $id,
			'view_id'    => $view,
		);
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

	private function convert_posts( array $map, string $batch ): array {
		global $wpdb;
		$posts = $wpdb->get_results( "SELECT ID,post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft') AND (post_content LIKE '%[lt %' OR post_content LIKE '%wp:dalt/table%')", ARRAY_A );
		if ( ! $posts ) {
			$posts = array();
		}
		$updated   = 0;
		$instances = 0;
		$errors    = array();
		foreach ( $posts as $p ) {
			$original    = $p['post_content'];
			$new         = $original;
			$new         = preg_replace_callback(
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
					$new = preg_replace_callback(
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
				$wpdb->insert(
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
				$ok = wp_update_post(
					array(
						'ID'           => (int) $p['ID'],
						'post_content' => $new,
					),
					true
				);
				if ( is_wp_error( $ok ) ) {
					$errors[] = 'Post ' . $p['ID'] . ': ' . $ok->get_error_message();
				} else {
					++$updated;}
			}
		}
		return array(
			'posts'     => $updated,
			'instances' => $instances,
			'errors'    => $errors,
		);
	}

	public function rollback( string $batch ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntc_backups WHERE batch_id=%s ORDER BY id DESC", $batch ), ARRAY_A );
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
			'success'  => empty( $errors ),
			'restored' => $restored,
			'errors'   => $errors,
		);}
}
