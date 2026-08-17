<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Renderer {
	private NTC_Repository $repo;
	public function __construct( NTC_Repository $repo ) {
		$this->repo = $repo; }

	public static function cell_feature_defaults(): array {
		return array(
			'textColor'        => true,
			'backgroundColor'  => true,
			'alignment'        => true,
			'fontWeight'       => true,
			'fontStyle'        => true,
			'link'             => true,
			'linkColor'        => true,
			'openLinkNewTab'   => true,
			'imageLeft'        => true,
			'imageLeftLink'    => true,
			'imageLeftNewTab'  => true,
			'imageRight'       => true,
			'imageRightLink'   => true,
			'imageRightNewTab' => true,
			'formula'          => true,
			'formulaData'      => true,
			'html'             => true,
			'rowSpan'          => true,
			'columnSpan'       => true,
		);
	}

	public static function table_defaults(): array {
		return array(
			'preset'                => 'editorial',
			'showHeader'            => true,
			'showCaption'           => false,
			'caption'               => '',
			'captionSide'           => 'top',
			'showPosition'          => false,
			'positionSide'          => 'left',
			'positionLabel'         => '#',
			'updatePositionOnSort'  => true,
			'enableSorting'         => false,
			'enableManualSorting'   => true,
			'numberFormat'          => 'us',
			'defaultSort'           => array(),
			'stickyHeader'          => false,
			'tableLayout'           => 'auto',
			'width'                 => '100%',
			'minWidth'              => '0',
			'maxHeight'             => '0',
			'containerWidth'        => '0',
			'phoneBreakpoint'       => 540,
			'tabletBreakpoint'      => 900,
			'responsiveMode'        => 'scroll',
			'phoneHeaderFontSize'   => 12,
			'phoneBodyFontSize'     => 12,
			'phoneCaptionFontSize'  => 12,
			'tabletHeaderFontSize'  => 13,
			'tabletBodyFontSize'    => 13,
			'tabletCaptionFontSize' => 13,
			'hidePhone'             => array(),
			'hideTablet'            => array(),
			'phoneHideImages'       => false,
			'tabletHideImages'      => false,
			'columnWidths'          => array(),
			'columnAlign'           => array(),
			'columnTypes'           => array(),
			'headerBackground'      => '',
			'headerColor'           => '',
			'bodyColor'             => '',
			'oddBackground'         => '',
			'evenBackground'        => '',
			'borderColor'           => '',
			'linkColor'             => '',
			'accentColor'           => '',
			'fontSize'              => 14,
			'headerFontSize'        => 13,
			'captionFontSize'       => 13,
			'cellPadding'           => '10px 12px',
			'borderWidth'           => 1,
			'borderRadius'          => 0,
			'marginTop'             => 20,
			'marginBottom'          => 20,
			'headerFontFamily'      => 'inherit',
			'headerFontWeight'      => '700',
			'headerFontStyle'       => 'normal',
			'bodyFontFamily'        => 'inherit',
			'bodyFontWeight'        => '400',
			'bodyFontStyle'         => 'normal',
			'captionTextAlign'      => 'left',
			'captionFontFamily'     => 'inherit',
			'captionFontWeight'     => '400',
			'captionFontStyle'      => 'normal',
			'captionColor'          => '',
			'headerLinkColor'       => '',
			'headerBorderColor'     => '',
			'headerPositionAlign'   => 'center',
			'oddColor'              => '',
			'evenColor'             => '',
			'oddLinkColor'          => '',
			'evenLinkColor'         => '',
			'averageDecimals'       => 2,
			'averageRound'          => 'half_up',
			'autoColorRules'        => array(),
			'autoAlignRules'        => array(),
			'enableCellProperties'  => true,
			'customClass'           => '',
			'enableSearch'          => false,
			'enablePagination'      => false,
			'rowsPerPage'           => 10,
			'enableExport'          => false,
			'enableSchema'          => false,
			'showUpdatedDate'       => false,
		);
	}

	public static function chart_defaults(): array {
		return array(
			'preset'                  => 'benchmark-dark',
			'chartType'               => 'horizontal-bar',
			'title'                   => '',
			'subtitle'                => '',
			'direction'               => 'higher',
			'directionLabel'          => '',
			'legendLabel'             => '',
			'axisLabel'               => '',
			'sortLabel'               => '',
			'labelColumn'             => 0,
			'valueColumns'            => array( 1 ),
			'sortColumn'              => 1,
			'sortDirection'           => 'desc',
			'highlightValues'         => array(),
			'allowMultipleHighlights' => false,
			'unit'                    => '',
			'decimals'                => 'auto',
			'showValues'              => true,
			'showAxis'                => true,
			'showGrid'                => true,
			'footer'                  => '',
			'secondaryFooter'         => '',
			'source'                  => '',
			'aspectRatio'             => 'auto',
			'background'              => '',
			'primaryColor'            => '',
			'secondaryColor'          => '',
			'highlightColor'          => '',
			'textColor'               => '',
			'mutedColor'              => '',
			'gridColor'               => '',
			'typographyPreset'        => 'comfortable',
			'titleFontSize'           => 28,
			'subtitleFontSize'        => 14,
			'directionFontSize'       => 12,
			'labelFontSize'           => 14,
			'valueFontSize'           => 14,
			'axisFontSize'            => 11,
			'legendFontSize'          => 12,
			'footerFontSize'          => 11,
			'panelTitleFontSize'      => 14,
			'mobileTitleFontSize'     => 24,
			'mobileLabelFontSize'     => 13,
			'mobileValueFontSize'     => 13,
			'mobileAxisFontSize'      => 10,
			'mobileLegendFontSize'    => 11,
			'mobileFooterFontSize'    => 10,
			'density'                 => 'auto',
			'barHeight'               => 26,
			'barGap'                  => 10,
			'panelGap'                => 30,
			'mobileBreakpoint'        => 620,
			'accessibleDataMode'      => 'screenreader',
			'customClass'             => '',
			'enableExport'            => false,
			'enableSchema'            => false,
			'themeMode'               => 'fixed',
			'darkBackground'          => '#0f131a',
			'darkTextColor'           => '#e6e9ee',
			'darkMutedColor'          => '#9aa5b1',
			'darkGridColor'           => '#2a3442',
			'showUpdatedDate'         => false,
		);
	}

	public static function chart_typography_presets(): array {
		return array(
			'compact'      => array(
				'titleFontSize'        => 23,
				'subtitleFontSize'     => 12,
				'directionFontSize'    => 11,
				'labelFontSize'        => 12,
				'valueFontSize'        => 12,
				'axisFontSize'         => 10,
				'legendFontSize'       => 11,
				'footerFontSize'       => 10,
				'panelTitleFontSize'   => 12,
				'mobileTitleFontSize'  => 21,
				'mobileLabelFontSize'  => 12,
				'mobileValueFontSize'  => 12,
				'mobileAxisFontSize'   => 10,
				'mobileLegendFontSize' => 10,
				'mobileFooterFontSize' => 9,
			),
			'comfortable'  => array(
				'titleFontSize'        => 28,
				'subtitleFontSize'     => 14,
				'directionFontSize'    => 12,
				'labelFontSize'        => 14,
				'valueFontSize'        => 14,
				'axisFontSize'         => 11,
				'legendFontSize'       => 12,
				'footerFontSize'       => 11,
				'panelTitleFontSize'   => 14,
				'mobileTitleFontSize'  => 24,
				'mobileLabelFontSize'  => 13,
				'mobileValueFontSize'  => 13,
				'mobileAxisFontSize'   => 10,
				'mobileLegendFontSize' => 11,
				'mobileFooterFontSize' => 10,
			),
			'presentation' => array(
				'titleFontSize'        => 34,
				'subtitleFontSize'     => 16,
				'directionFontSize'    => 13,
				'labelFontSize'        => 16,
				'valueFontSize'        => 16,
				'axisFontSize'         => 12,
				'legendFontSize'       => 14,
				'footerFontSize'       => 12,
				'panelTitleFontSize'   => 16,
				'mobileTitleFontSize'  => 28,
				'mobileLabelFontSize'  => 15,
				'mobileValueFontSize'  => 15,
				'mobileAxisFontSize'   => 11,
				'mobileLegendFontSize' => 12,
				'mobileFooterFontSize' => 10,
			),
		);
	}

	public static function chart_density_presets(): array {
		return array(
			'spacious'    => array(
				'barHeight' => 34,
				'barGap'    => 14,
			),
			'comfortable' => array(
				'barHeight' => 26,
				'barGap'    => 10,
			),
			'compact'     => array(
				'barHeight' => 18,
				'barGap'    => 7,
			),
		);
	}

	public static function table_presets(): array {
		return array(
			'editorial'      => array(
				'headerBackground' => '#151922',
				'headerColor'      => '#ffffff',
				'bodyColor'        => '#252a34',
				'oddBackground'    => '#ffffff',
				'evenBackground'   => '#f6f7f8',
				'borderColor'      => '#dfe3e8',
				'linkColor'        => '#b51f56',
				'accentColor'      => '#9e2f5f',
				'borderRadius'     => 4,
			),
			'comparison'     => array(
				'headerBackground' => '#111827',
				'headerColor'      => '#ffffff',
				'bodyColor'        => '#1f2937',
				'oddBackground'    => '#ffffff',
				'evenBackground'   => '#f3f4f6',
				'borderColor'      => '#d1d5db',
				'linkColor'        => '#8b2f5f',
				'accentColor'      => '#9e2f5f',
				'borderRadius'     => 6,
			),
			'ranking'        => array(
				'headerBackground' => '#0c1420',
				'headerColor'      => '#ffffff',
				'bodyColor'        => '#1e293b',
				'oddBackground'    => '#ffffff',
				'evenBackground'   => '#f8fafc',
				'borderColor'      => '#dbe2ea',
				'linkColor'        => '#9e2f5f',
				'accentColor'      => '#9e2f5f',
				'borderRadius'     => 4,
			),
			'specifications' => array(
				'headerBackground' => '#f2f3f5',
				'headerColor'      => '#1b1f24',
				'bodyColor'        => '#252a34',
				'oddBackground'    => '#ffffff',
				'evenBackground'   => '#fafafa',
				'borderColor'      => '#e4e6e8',
				'linkColor'        => '#9e2f5f',
				'accentColor'      => '#9e2f5f',
				'borderRadius'     => 0,
			),
			'minimal'        => array(
				'headerBackground' => 'transparent',
				'headerColor'      => '#111827',
				'bodyColor'        => '#1f2937',
				'oddBackground'    => 'transparent',
				'evenBackground'   => 'transparent',
				'borderColor'      => '#e5e7eb',
				'linkColor'        => '#9e2f5f',
				'accentColor'      => '#9e2f5f',
				'borderRadius'     => 0,
			),
			'compact'        => array(
				'headerBackground' => '#202630',
				'headerColor'      => '#ffffff',
				'bodyColor'        => '#202630',
				'oddBackground'    => '#ffffff',
				'evenBackground'   => '#f7f7f8',
				'borderColor'      => '#d9dde2',
				'linkColor'        => '#9e2f5f',
				'accentColor'      => '#9e2f5f',
				'cellPadding'      => '6px 8px',
				'fontSize'         => 12,
				'headerFontSize'   => 12,
			),
			'dark'           => array(
				'headerBackground' => '#101722',
				'headerColor'      => '#f8fafc',
				'bodyColor'        => '#e5e7eb',
				'oddBackground'    => '#131b26',
				'evenBackground'   => '#18222f',
				'borderColor'      => '#2b3949',
				'linkColor'        => '#f05a8d',
				'accentColor'      => '#cf2f69',
				'borderRadius'     => 4,
			),
		);
	}

	public static function chart_presets(): array {
		return array(
			'benchmark-dark'    => array(
				'background'       => '#09111b',
				'primaryColor'     => '#624b8e',
				'secondaryColor'   => '#8b73b8',
				'highlightColor'   => '#b02f66',
				'textColor'        => '#f5f7fa',
				'mutedColor'       => '#b2bdca',
				'gridColor'        => '#2b394b',
				'typographyPreset' => 'comfortable',
				'density'          => 'auto',
			),
			'benchmark-light'   => array(
				'background'       => '#f8fafc',
				'primaryColor'     => '#624b8e',
				'secondaryColor'   => '#8b73b8',
				'highlightColor'   => '#a9235e',
				'textColor'        => '#111827',
				'mutedColor'       => '#5f6b7a',
				'gridColor'        => '#d9e0e8',
				'typographyPreset' => 'comfortable',
				'density'          => 'auto',
			),
			'benchmark-compact' => array(
				'background'       => '#09111b',
				'primaryColor'     => '#624b8e',
				'secondaryColor'   => '#8b73b8',
				'highlightColor'   => '#c03772',
				'textColor'        => '#f5f7fa',
				'mutedColor'       => '#b2bdca',
				'gridColor'        => '#2b394b',
				'typographyPreset' => 'compact',
				'density'          => 'compact',
			),
			'editorial-light'   => array(
				'background'       => '#ffffff',
				'primaryColor'     => '#4f46e5',
				'secondaryColor'   => '#7c6ee6',
				'highlightColor'   => '#a9235e',
				'textColor'        => '#111827',
				'mutedColor'       => '#667085',
				'gridColor'        => '#e4e7ec',
				'typographyPreset' => 'comfortable',
				'density'          => 'comfortable',
			),
			'editorial-dark'    => array(
				'background'       => '#141821',
				'primaryColor'     => '#7b65aa',
				'secondaryColor'   => '#a08bc7',
				'highlightColor'   => '#d33b77',
				'textColor'        => '#f7f8fa',
				'mutedColor'       => '#b3bbc7',
				'gridColor'        => '#303846',
				'typographyPreset' => 'comfortable',
				'density'          => 'comfortable',
			),
			'minimal'           => array(
				'background'       => 'transparent',
				'primaryColor'     => '#374151',
				'secondaryColor'   => '#6b7280',
				'highlightColor'   => '#9e2f5f',
				'textColor'        => '#111827',
				'mutedColor'       => '#667085',
				'gridColor'        => '#e5e7eb',
				'typographyPreset' => 'comfortable',
				'density'          => 'comfortable',
			),
			'high-contrast'     => array(
				'background'       => '#000000',
				'primaryColor'     => '#f5f5f5',
				'secondaryColor'   => '#a3e635',
				'highlightColor'   => '#ff3b81',
				'textColor'        => '#ffffff',
				'mutedColor'       => '#e5e7eb',
				'gridColor'        => '#5f6368',
				'typographyPreset' => 'presentation',
				'density'          => 'comfortable',
			),
			'feature'           => array(
				'background'       => '#111827',
				'primaryColor'     => '#8b73b8',
				'secondaryColor'   => '#b7a3d8',
				'highlightColor'   => '#e03b78',
				'textColor'        => '#ffffff',
				'mutedColor'       => '#c2c9d2',
				'gridColor'        => '#374151',
				'typographyPreset' => 'presentation',
				'density'          => 'spacious',
			),
			'comparison'        => array(
				'background'       => '#101722',
				'primaryColor'     => '#624b8e',
				'secondaryColor'   => '#8d76b5',
				'highlightColor'   => '#c52f6b',
				'textColor'        => '#ffffff',
				'mutedColor'       => '#b7c0cc',
				'gridColor'        => '#2d3948',
				'typographyPreset' => 'comfortable',
				'density'          => 'comfortable',
			),
			'technical'         => array(
				'background'       => '#f3f5f7',
				'primaryColor'     => '#334155',
				'secondaryColor'   => '#64748b',
				'highlightColor'   => '#9e2f5f',
				'textColor'        => '#101828',
				'mutedColor'       => '#475467',
				'gridColor'        => '#cbd5e1',
				'typographyPreset' => 'compact',
				'density'          => 'compact',
			),
		);
	}

	private function resolve( array $attributes, string $type ): array {
		$mode       = $attributes['mode'] ?? 'inline';
		$columns    = is_array( $attributes['columns'] ?? null ) ? $attributes['columns'] : array();
		$rows       = is_array( $attributes['rows'] ?? null ) ? $attributes['rows'] : array();
		$config     = is_array( $attributes['config'] ?? null ) ? $attributes['config'] : array();
		$cell_meta  = is_array( $attributes['cellMeta'] ?? null ) ? $attributes['cellMeta'] : array();
		$dataset_id = absint( $attributes['datasetId'] ?? 0 );
		$view_id    = absint( $attributes['viewId'] ?? 0 );
		if ( $view_id ) {
			$view = $this->repo->get_view( $view_id );
			if ( $view && $view['type'] === $type ) {
				$dataset_id  = (int) $view['dataset_id'];
				$view_config = (array) $view['config'];
				if ( 'table' === $type && ! empty( $view_config['cellMeta'] ) && is_array( $view_config['cellMeta'] ) ) {
					$cell_meta = array_merge( $cell_meta, $view_config['cellMeta'] );
					unset( $view_config['cellMeta'] );
				}$config = array_merge( $config, $view_config );
				$mode    = 'view';}
		}
		$dataset_updated_at = null;
		$dataset_name       = null;
		if ( $dataset_id ) {
			$dataset = $this->repo->get_dataset( $dataset_id, false );
			if ( $dataset ) {
				$columns            = $dataset['columns'];
				$rows               = $this->repo->get_rows( $dataset_id );
				$dataset_updated_at = $dataset['updated_at'] ?? null;
				$dataset_name       = $dataset['name'] ?? null;
			}
		}
		return compact( 'mode', 'columns', 'rows', 'config', 'cell_meta', 'dataset_id', 'view_id', 'dataset_updated_at', 'dataset_name' );
	}

	private function schema_json( array $data, array $config, array $columns ): string {
		if ( empty( $config['enableSchema'] ) ) {
			return ''; }
		$name = (string) ( $config['title'] ?? '' );
		if ( '' === $name ) {
			$name = (string) ( $config['caption'] ?? '' ); }
		if ( '' === $name && ! empty( $data['dataset_name'] ) ) {
			$name = (string) $data['dataset_name']; }
		if ( '' === $name ) {
			return ''; }
		$date = $data['dataset_updated_at'] ?? '';
		if ( '' === $date ) {
			$post = get_post();
			$date = $post ? get_post_modified_time( 'c', true, $post ) : '';
		}
		$variables  = array();
		$value_cols = array_map( 'absint', (array) ( $config['valueColumns'] ?? array() ) );
		foreach ( $value_cols as $v ) {
			$col = $columns[ $v ] ?? null;
			if ( ! $col || in_array( (string) ( $col['type'] ?? 'auto' ), array( 'text', 'url', 'sparkline', 'delta' ), true ) ) {
				continue; }
			$variables[] = array(
				'@type'    => 'PropertyValue',
				'name'     => esc_html( $col['label'] ?? '' ),
				'unitText' => esc_html( $col['unit'] ?? '' ),
			);
		}
		$payload = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'Dataset',
			'name'         => esc_html( $name ),
			'description'  => esc_html( (string) ( $config['subtitle'] ?? '' ) ),
			'dateModified' => esc_html( (string) $date ),
		);
		if ( $variables ) {
			$payload['variableMeasured'] = $variables; }
		return '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}

	private function updated_date_html( array $config, array $data ): string {
		if ( empty( $config['showUpdatedDate'] ) || empty( $data['dataset_updated_at'] ) ) {
			return ''; }
		/* translators: %s: date and time. */
		return '<div class="ntc-updated">' . esc_html( sprintf( __( 'Last updated: %s', 'native-tables-charts' ), get_date_from_gmt( $data['dataset_updated_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ) . '</div>';
	}

	public function render_table( array $attributes ): string {
		$data       = $this->resolve( $attributes, 'table' );
		$columns    = $data['columns'];
		$rows       = $data['rows'];
		$cell_meta  = $data['cell_meta'];
		$config     = array_merge( self::table_defaults(), $data['config'] );
		$preset_key = sanitize_key( $config['preset'] ?? 'editorial' );
		$presets    = self::table_presets();
		if ( isset( $presets[ $preset_key ] ) ) {
			$config = array_merge( $config, $presets[ $preset_key ], $data['config'] );}
		if ( empty( $columns ) && ! empty( $rows[0] ) ) {
			foreach ( $rows[0] as $i => $v ) {
				$columns[] = array(
					'id'    => 'c' . ( $i + 1 ),
					'label' => 'Column ' . ( $i + 1 ),
					'type'  => 'auto',
					'unit'  => '',
				);}
		}
		if ( empty( $config['enableCellProperties'] ) ) {
			$cell_meta = array();}
		$rows       = NTC_Formulas::apply( $rows, $columns, $cell_meta, (int) $config['averageDecimals'], (string) $config['averageRound'] );
		$rows       = $this->sort_rows( $rows, $config, $columns );
		$heat       = $this->heatmap_stats( $rows, $config, $columns );
		$id         = wp_unique_id( 'ntc-table-' );
		$width_mode = sanitize_key( (string) ( $attributes['widthMode'] ?? '' ) );
		if ( ! in_array( $width_mode, array( 'content', 'wide', 'full' ), true ) ) {
			$legacy_align = sanitize_key( (string) ( $attributes['align'] ?? '' ) );
			$width_mode   = in_array( $legacy_align, array( 'wide', 'full' ), true ) ? $legacy_align : 'content';
		}
		$classes = array( 'ntc-table-wrap', 'ntc-width-' . $width_mode, 'ntc-responsive-' . sanitize_html_class( (string) $config['responsiveMode'] ) );
		if ( 'wide' === $width_mode ) {
			$classes[] = 'alignwide';
		} elseif ( 'full' === $width_mode ) {
			$classes[] = 'alignfull';}
		if ( ! empty( $config['customClass'] ) ) {
			$classes[] = sanitize_html_class( (string) $config['customClass'] );}
		$css                = $this->table_scoped_css( $id, $config, $columns );
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'id'    => $id,
				'class' => implode( ' ', $classes ),
				'style' => $this->table_vars( $config ) . 'margin-top:' . absint( $config['marginTop'] ) . 'px;margin-bottom:' . absint( $config['marginBottom'] ) . 'px;',
			)
		);
		$out                = '<div ' . $wrapper_attributes . '>' . $css;
		$tools              = '';
		if ( ! empty( $config['enableSearch'] ) && $rows ) {
			$tools .= '<input type="search" class="ntc-table-search" placeholder="' . esc_attr__( 'Search this table…', 'native-tables-charts' ) . '" aria-label="' . esc_attr__( 'Search this table', 'native-tables-charts' ) . '">'; }
		if ( ! empty( $config['enableExport'] ) ) {
			$tools .= '<button type="button" class="ntc-export-btn" data-format="csv">' . esc_html__( 'Download CSV', 'native-tables-charts' ) . '</button>'; }
		if ( '' !== $tools ) {
			$out .= '<div class="ntc-table-tools">' . $tools . '</div>'; }
		$scroll_styles = array();
		if ( (int) $config['maxHeight'] > 0 ) {
			$scroll_styles[] = 'max-height:' . absint( $config['maxHeight'] ) . 'px';
		}if ( (int) $config['containerWidth'] > 0 ) {
			$scroll_styles[] = 'max-width:' . absint( $config['containerWidth'] ) . 'px';
		}$out .= '<div class="ntc-table-scroll"' . ( $scroll_styles ? ' style="' . esc_attr( implode( ';', $scroll_styles ) ) . '"' : '' ) . '>';
		$out  .= '<table class="ntc-table" data-sortable="' . ( ! empty( $config['enableSorting'] ) ? '1' : '0' ) . '" data-update-position="' . ( ! empty( $config['updatePositionOnSort'] ) ? '1' : '0' ) . '">';
		if ( ! empty( $config['showCaption'] ) && '' !== (string) $config['caption'] ) {
			$out .= '<caption style="caption-side:' . ( 'bottom' === $config['captionSide'] ? 'bottom' : 'top' ) . '">' . wp_kses_post( $config['caption'] ) . '</caption>';}
		if ( ! empty( $config['showHeader'] ) ) {
			$out .= '<thead><tr>';
			if ( ! empty( $config['showPosition'] ) && 'left' === $config['positionSide'] ) {
				$out .= '<th scope="col" class="ntc-position-head">' . esc_html( $config['positionLabel'] ) . '</th>';}
			$header_skip = array();
			foreach ( $columns as $ci => $col ) {
				if ( isset( $header_skip[ $ci ] ) ) {
					continue;}
				$label    = $col['label'] ?? ( 'Column ' . ( $ci + 1 ) );
				$hmeta    = is_array( $cell_meta[ 'header:' . $ci ] ?? null ) ? $cell_meta[ 'header:' . $ci ] : array();
				$hrowspan = ! empty( $config['enableSorting'] ) ? 1 : max( 1, absint( $hmeta['rowspan'] ?? 1 ) );
				$hcolspan = ! empty( $config['enableSorting'] ) ? 1 : max( 1, absint( $hmeta['colspan'] ?? 1 ) );
				if ( $hcolspan > 1 ) {
					for ( $hc = $ci + 1;$hc < $ci + $hcolspan;$hc++ ) {
						$header_skip[ $hc ] = true;}
				}
				$hstyle = $this->cell_style( $hmeta, $config, -1, $ci, '', $heat );
				$hattrs = ' scope="col" data-col="' . esc_attr( $ci ) . '"' . ( ( ! empty( $config['enableSorting'] ) && ! empty( $config['enableManualSorting'] ) ) ? ' aria-sort="none"' : '' );
				if ( $hstyle ) {
					$hattrs .= ' style="' . esc_attr( $hstyle ) . '"';
				}if ( $hrowspan > 1 ) {
					$hattrs .= ' rowspan="' . $hrowspan . '"';
				}if ( $hcolspan > 1 ) {
					$hattrs .= ' colspan="' . $hcolspan . '"';
				}
				$out .= '<th' . $hattrs . '>';
				if ( ! empty( $config['enableSorting'] ) && ! empty( $config['enableManualSorting'] ) ) {
					$out .= '<button type="button" class="ntc-sort" data-column="' . esc_attr( $ci ) . '" aria-sort="none">' . $this->render_cell( $label, $hmeta, $config, true, $config['columnTypes'][ $ci ] ?? $col['type'] ?? 'auto' ) . '<span aria-hidden="true" class="ntc-sort-icon">↕</span></button>';
				} else {
					$out .= $this->render_cell( $label, $hmeta, $config, true, $config['columnTypes'][ $ci ] ?? $col['type'] ?? 'auto' );
				} $out .= '</th>';
			}
			if ( ! empty( $config['showPosition'] ) && 'right' === $config['positionSide'] ) {
				$out .= '<th scope="col" class="ntc-position-head">' . esc_html( $config['positionLabel'] ) . '</th>';}
			$out .= '</tr></thead>';
		}
		$out .= '<tbody>';
		$skip = array();
		foreach ( $rows as $ri => $row ) {
			$meta_ri = isset( $row['_ntc_index'] ) ? (int) $row['_ntc_index'] : $ri;
			$out    .= '<tr data-original-index="' . esc_attr( $meta_ri ) . '">';
			if ( ! empty( $config['showPosition'] ) && 'left' === $config['positionSide'] ) {
				$out .= '<th scope="row" class="ntc-position">' . esc_html( $ri + 1 ) . '</th>';}
			foreach ( $columns as $ci => $col ) {
				if ( isset( $skip[ $ri ][ $ci ] ) ) {
					continue;}
				$meta    = is_array( $cell_meta[ $meta_ri . ':' . $ci ] ?? null ) ? $cell_meta[ $meta_ri . ':' . $ci ] : array();
				$rowspan = ! empty( $config['enableSorting'] ) ? 1 : max( 1, absint( $meta['rowspan'] ?? $meta['rowSlots'] ?? 1 ) );
				$colspan = ! empty( $config['enableSorting'] ) ? 1 : max( 1, absint( $meta['colspan'] ?? $meta['columnSlots'] ?? 1 ) );
				if ( $rowspan > 1 || $colspan > 1 ) {
					for ( $rr = $ri;$rr < $ri + $rowspan;$rr++ ) {
						for ( $cc = $ci;$cc < $ci + $colspan;$cc++ ) {
							if ( $rr === $ri && $cc === $ci ) {
											continue;
							}$skip[ $rr ][ $cc ] = true;}
					}
				}
				$value       = $row[ $ci ] ?? '';
				$type        = $config['columnTypes'][ $ci ] ?? ( $col['type'] ?? 'auto' );
				$date_format = $col['format'] ?? '';
				if ( 'short_date' === strtolower( (string) $type ) ) {
					foreach ( (array) ( $config['defaultSort'] ?? array() ) as $sort_rule ) {
						if ( absint( $sort_rule['column'] ?? -1 ) === $ci && ! empty( $sort_rule['dateFormat'] ) ) {
							$date_format = (string) $sort_rule['dateFormat'];
							break;}
					}
				}
				$sort  = $this->sort_value( $value, $type, $date_format, $config['numberFormat'] ?? 'us' );
				$style = $this->cell_style( $meta, $config, $ri, $ci, $row[ $ci ] ?? '', $heat );
				$attrs = ' data-label="' . esc_attr( $col['label'] ?? '' ) . '" data-sort="' . esc_attr( $sort ) . '"';
				if ( $rowspan > 1 ) {
					$attrs .= ' rowspan="' . $rowspan . '"';
				}if ( $colspan > 1 ) {
					$attrs .= ' colspan="' . $colspan . '"';}
				if ( $style ) {
					$attrs .= ' style="' . esc_attr( $style ) . '"';}
				$out .= '<td' . $attrs . '>' . $this->render_cell( $value, $meta, $config, false, $type ) . '</td>';
			}
			if ( ! empty( $config['showPosition'] ) && 'right' === $config['positionSide'] ) {
				$out .= '<th scope="row" class="ntc-position">' . esc_html( $ri + 1 ) . '</th>';}
			$out .= '</tr>';
		}
		$out .= '</tbody></table></div>';
		if ( ! empty( $config['enablePagination'] ) ) {
			$size   = max( 1, min( 500, absint( $config['rowsPerPage'] ?? 10 ) ) );
			$hidden = count( $rows ) <= $size ? ' is-hidden' : '';
			$out   .= '<div class="ntc-table-pager' . $hidden . '" data-page-size="' . $size . '"><button type="button" class="ntc-pager-prev" aria-label="' . esc_attr__( 'Previous page', 'native-tables-charts' ) . '">‹</button><span class="ntc-pager-label">1 / ' . max( 1, (int) ceil( count( $rows ) / $size ) ) . '</span><button type="button" class="ntc-pager-next" aria-label="' . esc_attr__( 'Next page', 'native-tables-charts' ) . '">›</button></div>';
		}
		$out .= $this->updated_date_html( $config, $data );
		$out .= '</div>' . $this->schema_json( $data, $config, $columns );
		if ( ! empty( $config['enableSorting'] ) && ! empty( $config['enableManualSorting'] ) || ! empty( $config['enableSearch'] ) || ! empty( $config['enablePagination'] ) || ! empty( $config['enableExport'] ) ) {
			wp_enqueue_script( 'ntc-frontend' ); }
		return $out;
	}

	private function render_cell( $value, array $meta, array $config, bool $header = false, string $type = 'auto' ): string {
		// League Table treats custom HTML as the final cell payload rather than combining it with links/images.
		if ( ! empty( $meta['html'] ) ) {
			return $this->sanitize_cell_html( (string) $meta['html'] );}
		if ( ! empty( $meta['htmlContent'] ) ) {
			return $this->sanitize_cell_html( (string) $meta['htmlContent'] );}
		if ( 'sparkline' === $type ) {
			$nums = array();
			foreach ( preg_split( '/[\s,]+/', trim( wp_strip_all_tags( (string) $value ) ), -1, PREG_SPLIT_NO_EMPTY ) as $v ) {
				if ( is_numeric( $v ) ) {
					$nums[] = (float) $v; }
			}
			if ( count( $nums ) > 1 ) {
				$w    = 96;
				$h    = 28;
				$pad  = 3;
				$min  = min( $nums );
				$max  = max( $nums );
				$span = ( $max - $min ) ? ( $max - $min ) : 1;
				$n    = count( $nums );
				$pts  = array();
				foreach ( $nums as $i => $v ) {
					$x     = $pad + ( $w - 2 * $pad ) * ( $i / ( $n - 1 ) );
					$y     = $h - $pad - ( $h - 2 * $pad ) * ( ( $v - $min ) / $span );
					$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
				}
				return '<span class="ntc-sparkline" aria-hidden="true"><svg viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '"><polyline points="' . esc_attr( implode( ' ', $pts ) ) . '" fill="none" stroke="currentColor" stroke-width="2"/></svg></span><span class="ntc-sr-only">' . esc_html( (string) $value ) . '</span>';
			}
			return esc_html( (string) $value );
		}
		if ( 'delta' === $type ) {
			$plain = trim( wp_strip_all_tags( (string) $value ) );
			if ( preg_match( '/^([+-]?)([\d.,]+)\s*%?$/', $plain, $m ) ) {
				$num   = (float) ( $m[1] . str_replace( ',', '.', $m[2] ) );
				$cls   = 'ntc-delta ' . ( $num > 0 ? 'is-up' : ( $num < 0 ? 'is-down' : 'is-flat' ) );
				$glyph = $num > 0 ? '▲' : ( $num < 0 ? '▼' : '—' );
				return '<span class="' . esc_attr( $cls ) . '">' . esc_html( $glyph . ' ' . $plain ) . '</span>';
			}
			return esc_html( $plain );
		}
		$content            = esc_html( (string) $value );
		$interactive_header = $header && ! empty( $config['enableSorting'] ) && ! empty( $config['enableManualSorting'] );
		if ( ! empty( $meta['link'] ) && ! $interactive_header ) {
			$target  = ! empty( $meta['openLinkNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
			$lc      = ! empty( $meta['linkColor'] ) ? ' style="color:' . esc_attr( $meta['linkColor'] ) . '"' : '';
			$content = '<a href="' . esc_url( $meta['link'] ) . '"' . $target . $lc . '>' . $content . '</a>';}
		$left  = '';
		$right = '';
		foreach ( array(
			'imageLeft'  => 'left',
			'imageRight' => 'right',
		) as $key => $side ) {
			if ( empty( $meta[ $key ] ) ) {
				continue;
			}$alt = (string) ( $meta[ $key . 'Alt' ] ?? '' );
			$img  = '<img class="ntc-cell-image ntc-image-' . $side . '" src="' . esc_url( $meta[ $key ] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
			$lk   = $key . 'Link';
			if ( ! empty( $meta[ $lk ] ) && ! $interactive_header ) {
				$tk  = ! empty( $meta[ $key . 'OpenLinkNewTab' ] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
				$img = '<a href="' . esc_url( $meta[ $lk ] ) . '"' . $tk . '>' . $img . '</a>';
			}if ( 'left' === $side ) {
				$left = $img;
			} else {
				$right = $img;}
		}
		return $left . $content . $right;
	}

	private function cell_style( array $meta, array $config, int $ri, int $ci, $value = '', array $heat = array() ): string {
		$styles    = array();
		$map       = array(
			'textColor'       => 'color',
			'backgroundColor' => 'background-color',
			'fontWeight'      => 'font-weight',
			'fontStyle'       => 'font-style',
			'alignment'       => 'text-align',
			'verticalAlign'   => 'vertical-align',
		);
		$whitelist = array(
			'alignment'     => array( 'left', 'center', 'right', 'justify' ),
			'verticalAlign' => array( 'top', 'middle', 'bottom' ),
			'fontWeight'    => array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ),
			'fontStyle'     => array( 'normal', 'italic', 'oblique' ),
		);
		foreach ( $map as $k => $css ) {
			if ( empty( $meta[ $k ] ) ) {
				continue; }
			$v = (string) $meta[ $k ];
			if ( isset( $whitelist[ $k ] ) && ! in_array( $v, $whitelist[ $k ], true ) ) {
				continue; }
			if ( str_contains( $k, 'Color' ) && ! preg_match( '/^(#[0-9a-f]{3,8}|rgb|hsl|var\()/i', $v ) ) {
				continue; }
			$styles[] = $css . ':' . $v;
		}
		foreach ( (array) $config['autoColorRules'] as $rule ) {
			if ( ! $this->rule_applies( $rule, $ri, $ci ) ) {
				continue; }
			if ( ! empty( $rule['heatmap'] ) && 'column' === ( $rule['type'] ?? 'row' ) && isset( $heat[ $ci ] ) ) {
				if ( ! preg_match( '/^-?[\d.,]+$/', trim( (string) $value ) ) ) {
					continue; }
				$n     = NTC_Formulas::numeric( $value );
				$range = $heat[ $ci ];
				$t     = ( $n - $range[0] ) / ( $range[1] - $range[0] );
				$bg    = self::lerp_color( (string) ( $rule['background'] ?? '#ffffff' ), (string) ( $rule['color'] ?? '#9e2f5f' ), (float) $t );
				if ( $bg ) {
					$styles[] = 'background-color:' . $bg; }
				continue;
			}
			if ( ! empty( $rule['background'] ) && preg_match( '/^(#[0-9a-f]{3,8}|rgb|hsl|var\()/i', (string) $rule['background'] ) ) {
				$styles[] = 'background-color:' . $rule['background']; }
			if ( ! empty( $rule['color'] ) && preg_match( '/^(#[0-9a-f]{3,8}|rgb|hsl|var\()/i', (string) $rule['color'] ) ) {
				$styles[] = 'color:' . $rule['color']; }
		}
		foreach ( (array) ( $config['autoAlignRules'] ?? array() ) as $rule ) {
			if ( $this->rule_applies( $rule, $ri, $ci ) && ! empty( $rule['align'] ) && in_array( $rule['align'], array( 'left', 'center', 'right', 'justify' ), true ) ) {
				$styles[] = 'text-align:' . $rule['align']; }
		}
		return implode( ';', $styles );
	}

	private static function hex_to_rgb( string $hex ): ?array {
		$hex = ltrim( trim( $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return null; }
		return array_map( 'hexdec', str_split( $hex, 2 ) );
	}

	private static function lerp_color( string $a, string $b, float $t ): string {
		$ra = self::hex_to_rgb( $a );
		$rb = self::hex_to_rgb( $b );
		if ( ! $ra || ! $rb ) {
			return $a; }
		$t = max( 0.0, min( 1.0, $t ) );
		$c = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$c[] = (int) round( $ra[ $i ] + ( $rb[ $i ] - $ra[ $i ] ) * $t ); }
		return sprintf( '#%02x%02x%02x', $c[0], $c[1], $c[2] );
	}

	private function heatmap_stats( array $rows, array $config, array $columns ): array {
		$heat_cols = array();
		foreach ( (array) ( $config['autoColorRules'] ?? array() ) as $rule ) {
			if ( ! empty( $rule['heatmap'] ) && 'column' === ( $rule['type'] ?? 'row' ) ) {
				$heat_cols = array_merge( $heat_cols, array_map( 'intval', (array) $rule['indexes'] ) );
			}
		}
		if ( ! $heat_cols ) {
			return array(); }
		$stats = array();
		foreach ( $heat_cols as $ci ) {
			$stats[ $ci ] = array( PHP_FLOAT_MAX, -PHP_FLOAT_MAX ); }
		foreach ( $rows as $row ) {
			foreach ( $heat_cols as $ci ) {
				$raw = trim( (string) ( $row[ $ci ] ?? '' ) );
				if ( ! preg_match( '/^-?[\d.,]+$/', $raw ) ) {
					continue; }
				$n = NTC_Formulas::numeric( $raw );
				if ( $n < $stats[ $ci ][0] ) {
					$stats[ $ci ][0] = $n; }
				if ( $n > $stats[ $ci ][1] ) {
					$stats[ $ci ][1] = $n; }
			}
		}
		foreach ( $stats as $ci => $range ) {
			if ( PHP_FLOAT_MAX === $range[0] || $range[0] === $range[1] ) {
				unset( $stats[ $ci ] ); }
		}
		return $stats;
	}

	private function rule_applies( array $rule, int $ri, int $ci ): bool {
		$type    = $rule['type'] ?? 'row';
		$indexes = array_map( 'intval', (array) ( $rule['indexes'] ?? array() ) );
		return 'column' === $type ? in_array( $ci, $indexes, true ) : in_array( $ri, $indexes, true );
	}

	private function sort_rows( array $rows, array $config, array $columns ): array {
		foreach ( $rows as $i => &$r ) {
			if ( is_array( $r ) ) {
				$r['_ntc_index'] = $i;
			}
		} unset( $r );
		$sorts = array_slice( (array) ( $config['defaultSort'] ?? array() ), 0, 5 );
		if ( ! $sorts ) {
			return $rows;
		}
		usort(
			$rows,
			function ( $a, $b ) use ( $sorts, $columns, $config ) {
				foreach ( $sorts as $s ) {
					$col  = absint( $s['column'] ?? 0 );
					$type = $s['type'] ?? ( $columns[ $col ]['type'] ?? 'auto' );
					$av   = $this->sort_value( $a[ $col ] ?? '', $type, $s['dateFormat'] ?? '', $config['numberFormat'] ?? 'us' );
					$bv   = $this->sort_value( $b[ $col ] ?? '', $type, $s['dateFormat'] ?? '', $config['numberFormat'] ?? 'us' );
					$cmp  = is_numeric( $av ) && is_numeric( $bv ) ? ( (float) $av <=> (float) $bv ) : strnatcasecmp( (string) $av, (string) $bv );
					if ( 0 !== $cmp ) {
						return ( ( $s['direction'] ?? 'asc' ) === 'desc' ) ? -$cmp : $cmp;
					}
				}return 0;
			}
		);
		return $rows;
	}

	private function sort_value( $value, string $type, string $format = '', string $number_format = 'us' ) {
		$plain = trim( wp_strip_all_tags( (string) $value ) );
		$type  = strtolower( $type );
		if ( in_array( $type, array( 'digit', 'number', 'currency', 'percent' ), true ) ) {
			if ( 'eu' === $number_format ) {
				$n = str_replace( '.', '', $plain );
				$n = str_replace( ',', '.', $n );
				$n = preg_replace( '/[^0-9.\-]+/', '', $n );
				return is_numeric( $n ) ? (float) $n : 0;
			}return NTC_Formulas::numeric( $plain );}
		if ( 'time' === $type ) {
			$t = strtotime( $plain );
			return false === $t ? 0 : $t;}
		if ( 'short_date' === $type ) {
			$t = $this->short_date_timestamp( $plain, $format ? $format : 'ddmmyyyy' );
			return false === $t ? 0 : $t;}
		if ( in_array( $type, array( 'iso_date', 'date', 'us_long_date' ), true ) ) {
			$t = strtotime( $plain );
			return false === $t ? 0 : $t;}
		if ( 'url' === $type ) {
			return strtolower( $plain );
		} if ( 'auto' === $type && is_numeric( str_replace( array( ',', '$', '%' ), '', $plain ) ) ) {
			return NTC_Formulas::numeric( $plain );
		} return strtolower( $plain );
	}

	private function short_date_timestamp( string $value, string $format ) {
		$format = in_array( $format, array( 'ddmmyyyy', 'yyyymmdd', 'mmddyyyy' ), true ) ? $format : 'ddmmyyyy';
		$clean  = trim( preg_replace( '/[\-.,]+/', '/', $value ) );
		$parts  = array_values( array_filter( preg_split( '/[\/\s]+/', $clean ), static fn( $v )=>'' !== $v ) );
		if ( 1 === count( $parts ) && preg_match( '/^\d{8}$/', $parts[0] ) ) {
			$raw = $parts[0];
			if ( 'yyyymmdd' === $format ) {
				$parts = array( substr( $raw, 0, 4 ), substr( $raw, 4, 2 ), substr( $raw, 6, 2 ) );
			} else {
				$parts = array( substr( $raw, 0, 2 ), substr( $raw, 2, 2 ), substr( $raw, 4, 4 ) );}
		}
		if ( 3 !== count( $parts ) ) {
			return false;
		}
		if ( 'yyyymmdd' === $format ) {
			[$year, $month, $day] = array_map( 'intval', $parts );
		} elseif ( 'mmddyyyy' === $format ) {
			[$month, $day, $year] = array_map( 'intval', $parts );
		} else {
			[$day, $month, $year] = array_map( 'intval', $parts );}
		if ( $year < 1000 || $year > 9999 || ! checkdate( $month, $day, $year ) ) {
			return false;
		}
		return gmmktime( 0, 0, 0, $month, $day, $year );
	}

	private function configured_allowed_html(): array {
		$spec = trim( (string) get_option( 'ntc_kses_allowed_html_tags', '' ) );
		if ( '' === $spec ) {
			return wp_kses_allowed_html( 'post' );
		}
		$allowed = array();
		foreach ( explode( ',', $spec ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( ! preg_match( '/^([a-z][a-z0-9-]*)(.*)$/i', $entry, $match ) ) {
				continue;
			}
			$tag   = strtolower( $match[1] );
			$tail  = (string) ( $match[2] ?? '' );
			$attrs = array();
			if ( '' !== trim( $tail ) ) {
				preg_match_all( '/\[([a-z][a-z0-9_:\-]*)\]/i', $tail, $attr_matches );
				$leftover = preg_replace( '/\[[a-z][a-z0-9_:\-]*\]/i', '', $tail );
				if ( '' !== trim( (string) $leftover ) ) {
					continue;
				}
				foreach ( (array) ( $attr_matches[1] ?? array() ) as $attr ) {
					$attrs[ strtolower( (string) $attr ) ] = true;}
			}
			$allowed[ $tag ] = $attrs;
		}
		return $allowed ? $allowed : wp_kses_allowed_html( 'post' );
	}

	private function configured_protocols(): array {
		$spec = trim( (string) get_option( 'ntc_kses_allowed_protocols', '' ) );
		if ( '' === $spec ) {
			return wp_allowed_protocols();
		}
		$protocols = array();
		foreach ( explode( ',', $spec ) as $protocol ) {
			$protocol = strtolower( trim( $protocol ) );
			if ( preg_match( '/^[a-z][a-z0-9+.-]*$/', $protocol ) ) {
				$protocols[] = $protocol;
			}
		}
		$filtered = array_values( array_unique( $protocols ) );
		return $filtered ? $filtered : wp_allowed_protocols();
	}

	private function sanitize_cell_html( string $html ): string {
		$allowed   = apply_filters( 'ntc_allowed_html', $this->configured_allowed_html() );
		$protocols = apply_filters( 'ntc_allowed_protocols', $this->configured_protocols() );
		return wp_kses(
			$html,
			is_array( $allowed ) ? $allowed : wp_kses_allowed_html( 'post' ),
			is_array( $protocols ) ? $protocols : wp_allowed_protocols()
		);
	}

	private static function safe_css_value( $v ): string {
		return trim( preg_replace( '/[;\{\}<>]/', '', (string) $v ) );}
	private static function css_length( $v ): string {
		return preg_match( '/^\d+(\.\d+)?(px|em|rem|%|vw|ch|ex)?$/', (string) $v ) ? (string) $v : '';}

	private function table_vars( array $c ): string {
		$vars = array(
			'--ntc-header-bg'      => $c['headerBackground'],
			'--ntc-header-color'   => $c['headerColor'],
			'--ntc-body-color'     => $c['bodyColor'],
			'--ntc-odd-bg'         => $c['oddBackground'],
			'--ntc-even-bg'        => $c['evenBackground'],
			'--ntc-border'         => $c['borderColor'],
			'--ntc-link'           => $c['linkColor'],
			'--ntc-accent'         => $c['accentColor'],
			'--ntc-font-size'      => absint( $c['fontSize'] ) . 'px',
			'--ntc-header-size'    => absint( $c['headerFontSize'] ) . 'px',
			'--ntc-caption-size'   => absint( $c['captionFontSize'] ) . 'px',
			'--ntc-header-font'    => $c['headerFontFamily'],
			'--ntc-header-weight'  => $c['headerFontWeight'],
			'--ntc-header-style'   => $c['headerFontStyle'],
			'--ntc-body-font'      => $c['bodyFontFamily'],
			'--ntc-body-weight'    => $c['bodyFontWeight'],
			'--ntc-body-style'     => $c['bodyFontStyle'],
			'--ntc-caption-font'   => $c['captionFontFamily'],
			'--ntc-caption-weight' => $c['captionFontWeight'],
			'--ntc-caption-style'  => $c['captionFontStyle'],
			'--ntc-caption-color'  => ! empty( $c['captionColor'] ) ? $c['captionColor'] : $c['bodyColor'],
			'--ntc-header-link'    => ! empty( $c['headerLinkColor'] ) ? $c['headerLinkColor'] : $c['headerColor'],
			'--ntc-header-border'  => ! empty( $c['headerBorderColor'] ) ? $c['headerBorderColor'] : $c['borderColor'],
			'--ntc-odd-color'      => ! empty( $c['oddColor'] ) ? $c['oddColor'] : $c['bodyColor'],
			'--ntc-even-color'     => ! empty( $c['evenColor'] ) ? $c['evenColor'] : $c['bodyColor'],
			'--ntc-odd-link'       => ! empty( $c['oddLinkColor'] ) ? $c['oddLinkColor'] : $c['linkColor'],
			'--ntc-even-link'      => ! empty( $c['evenLinkColor'] ) ? $c['evenLinkColor'] : $c['linkColor'],
			'--ntc-caption-align'  => $c['captionTextAlign'],
			'--ntc-table-layout'   => $c['tableLayout'],
			'--ntc-border-width'   => absint( $c['borderWidth'] ) . 'px',
			'--ntc-padding'        => $c['cellPadding'],
			'--ntc-radius'         => absint( $c['borderRadius'] ) . 'px',
			'--ntc-width'          => self::css_length( $c['width'] ) ? self::css_length( $c['width'] ) : '100%',
			'--ntc-min-width'      => is_numeric( $c['minWidth'] ) ? absint( $c['minWidth'] ) . 'px' : self::css_length( $c['minWidth'] ),
		);
		$out  = '';
		foreach ( $vars as $k => $v ) {
			if ( '' !== $v && null !== $v ) {
				$out .= $k . ':' . esc_attr( self::safe_css_value( $v ) ) . ';';
			}
		}return $out;
	}

	private function table_scoped_css( string $id, array $c, array $columns ): string {
		$phone        = max( 320, absint( $c['phoneBreakpoint'] ) );
		$tablet       = max( $phone + 1, absint( $c['tabletBreakpoint'] ) );
		$prefix       = '#' . esc_attr( $id );
		$base         = '';
		$tablet_rules = '';
		$phone_rules  = '';
		$offset       = ( ! empty( $c['showPosition'] ) && 'left' === $c['positionSide'] ) ? 1 : 0;
		foreach ( (array) $c['columnWidths'] as $i => $w ) {
			$len = is_numeric( $w ) ? absint( $w ) . 'px' : self::css_length( $w );
			if ( '' !== $len ) {
				$base .= $prefix . ' .ntc-table th:nth-child(' . ( (int) $i + 1 + $offset ) . '),' . $prefix . ' .ntc-table td:nth-child(' . ( (int) $i + 1 + $offset ) . '){width:' . esc_attr( $len ) . ';}';
			}
		}
		foreach ( (array) $c['columnAlign'] as $i => $align ) {
			if ( in_array( $align, array( 'left', 'center', 'right', 'justify' ), true ) ) {
				$n     = (int) $i + 1 + $offset;
				$base .= $prefix . ' .ntc-table th:nth-child(' . $n . '),' . $prefix . ' .ntc-table td:nth-child(' . $n . '){text-align:' . $align . ';}';}
		}
		$pos_align = in_array( $c['headerPositionAlign'] ?? 'center', array( 'left', 'center', 'right' ), true ) ? $c['headerPositionAlign'] : 'center';
		$base     .= $prefix . ' .ntc-position-head{text-align:' . $pos_align . '!important;}';
		if ( ! empty( $c['stickyHeader'] ) ) {
			$base .= $prefix . ' thead th{position:sticky;top:0;z-index:3;}';}
		if ( ! empty( $c['hideTablet'] ) ) {
			$selectors = array();
			foreach ( $c['hideTablet'] as $i ) {
				$n           = (int) $i + 1 + $offset;
				$selectors[] = $prefix . ' th:nth-child(' . $n . ')';
				$selectors[] = $prefix . ' td:nth-child(' . $n . ')';
			}$tablet_rules .= implode( ',', $selectors ) . '{display:none!important;}';}
		if ( ! empty( $c['hidePhone'] ) ) {
			$selectors = array();
			foreach ( $c['hidePhone'] as $i ) {
				$n           = (int) $i + 1 + $offset;
				$selectors[] = $prefix . ' th:nth-child(' . $n . ')';
				$selectors[] = $prefix . ' td:nth-child(' . $n . ')';
			}$phone_rules .= implode( ',', $selectors ) . '{display:none!important;}';}
		$tablet_rules .= $prefix . ' .ntc-table thead th{font-size:' . absint( $c['tabletHeaderFontSize'] ) . 'px}' . $prefix . ' .ntc-table tbody{font-size:' . absint( $c['tabletBodyFontSize'] ) . 'px}' . $prefix . ' .ntc-table caption{font-size:' . absint( $c['tabletCaptionFontSize'] ) . 'px}';
		$phone_rules  .= $prefix . ' .ntc-table thead th{font-size:' . absint( $c['phoneHeaderFontSize'] ) . 'px}' . $prefix . ' .ntc-table tbody{font-size:' . absint( $c['phoneBodyFontSize'] ) . 'px}' . $prefix . ' .ntc-table caption{font-size:' . absint( $c['phoneCaptionFontSize'] ) . 'px}';
		if ( ! empty( $c['tabletHideImages'] ) ) {
			$tablet_rules .= $prefix . ' .ntc-cell-image{display:none!important;}';}
		if ( ! empty( $c['phoneHideImages'] ) ) {
			$phone_rules .= $prefix . ' .ntc-cell-image{display:none!important;}';}
		if ( 'stack' === $c['responsiveMode'] ) {
			$phone_rules .= $prefix . ' .ntc-table thead{display:none}' . $prefix . ' .ntc-table,' . $prefix . ' tbody,' . $prefix . ' tr,' . $prefix . ' td{display:block;width:100%!important}' . $prefix . ' tr{padding:8px 0;border-bottom:1px solid var(--ntc-border)}' . $prefix . ' td{display:grid!important;grid-template-columns:minmax(110px,42%) 1fr;gap:12px;border:0!important;padding:8px 12px}' . $prefix . ' td:before{content:attr(data-label);font-weight:600;color:var(--ntc-body-color)}' . $prefix . ' .ntc-position{display:none}';}
		$css = '<style>' . $base;
		if ( '' !== $tablet_rules ) {
			$css .= '@container (max-width:' . $tablet . 'px){' . $tablet_rules . '}@media (max-width:' . $tablet . 'px){' . $tablet_rules . '}';}
		if ( '' !== $phone_rules ) {
			$css .= '@container (max-width:' . $phone . 'px){' . $phone_rules . '}@media (max-width:' . $phone . 'px){' . $phone_rules . '}';}
		return $css . '</style>';
	}

	private function apply_chart_presentation( array $config, int $row_count ): array {
		$typography         = (string) ( $config['typographyPreset'] ?? 'comfortable' );
		$typography_presets = self::chart_typography_presets();
		if ( 'custom' !== $typography && isset( $typography_presets[ $typography ] ) ) {
			$config = array_merge( $config, $typography_presets[ $typography ], array_intersect_key( $config, array_flip( array( 'background', 'primaryColor', 'secondaryColor', 'highlightColor', 'textColor', 'mutedColor', 'gridColor', 'customClass', 'accessibleDataMode', 'chartType', 'title', 'subtitle', 'direction', 'directionLabel', 'legendLabel', 'axisLabel', 'sortLabel', 'labelColumn', 'valueColumns', 'sortColumn', 'sortDirection', 'highlightValues', 'allowMultipleHighlights', 'unit', 'decimals', 'showValues', 'showAxis', 'showGrid', 'footer', 'secondaryFooter', 'source', 'aspectRatio', 'mobileBreakpoint', 'preset', 'typographyPreset', 'density' ) ) ) );}
		$density         = (string) ( $config['density'] ?? 'auto' );
		$density_presets = self::chart_density_presets();
		if ( 'auto' === $density ) {
			if ( $row_count <= 4 ) {
				$config['barHeight'] = 34;
				$config['barGap']    = 14;
			} elseif ( $row_count <= 10 ) {
				$config['barHeight'] = 26;
				$config['barGap']    = 10;
			} elseif ( $row_count <= 18 ) {
				$config['barHeight'] = 22;
				$config['barGap']    = 8;
			} else {
				$config['barHeight'] = 18;
				$config['barGap']    = 6;}
		} elseif ( 'custom' !== $density && isset( $density_presets[ $density ] ) ) {
			$config = array_merge( $config, $density_presets[ $density ] );}
		return $config;
	}

	public function render_chart( array $attributes ): string {
		$data    = $this->resolve( $attributes, 'chart' );
		$columns = $data['columns'];
		$rows    = $data['rows'];
		$config  = array_merge( self::chart_defaults(), $data['config'] );
		$presets = self::chart_presets();
		$pk      = sanitize_key( $config['preset'] ?? 'benchmark-dark' );
		if ( isset( $presets[ $pk ] ) ) {
			$config = array_merge( $config, $presets[ $pk ], $data['config'] );}
		if ( ! $columns || ! $rows ) {
			$wm = sanitize_key( (string) ( $attributes['widthMode'] ?? '' ) );
			if ( ! in_array( $wm, array( 'content', 'wide', 'full' ), true ) ) {
				$la = sanitize_key( (string) ( $attributes['align'] ?? '' ) );
				$wm = in_array( $la, array( 'wide', 'full' ), true ) ? $la : 'content';
			}$wc = 'ntc-empty ntc-width-' . $wm . ( 'wide' === $wm ? ' alignwide' : ( 'full' === $wm ? ' alignfull' : '' ) );
			return '<div ' . get_block_wrapper_attributes( array( 'class' => $wc ) ) . '>' . esc_html__( 'Add data to display this chart.', 'native-tables-charts' ) . '</div>';}
		$id         = wp_unique_id( 'ntc-chart-' );
		$chart_rows = $this->prepare_chart_rows( $rows, $columns, $config );
		$config     = $this->apply_chart_presentation( $config, count( $chart_rows ) );
		$type       = sanitize_key( $config['chartType'] );
		$width_mode = sanitize_key( (string) ( $attributes['widthMode'] ?? '' ) );
		if ( ! in_array( $width_mode, array( 'content', 'wide', 'full' ), true ) ) {
			$legacy_align = sanitize_key( (string) ( $attributes['align'] ?? '' ) );
			$width_mode   = in_array( $legacy_align, array( 'wide', 'full' ), true ) ? $legacy_align : 'content';
		}
		$width_class        = 'ntc-width-' . $width_mode . ( 'wide' === $width_mode ? ' alignwide' : ( 'full' === $width_mode ? ' alignfull' : '' ) );
		$classes            = 'ntc-chart ntc-chart-' . $type . ( ! empty( $config['showGrid'] ) ? '' : ' ntc-no-grid' ) . ' ' . $width_class . ' ' . sanitize_html_class( (string) $config['customClass'] );
		$aspect             = in_array( (string) ( $config['aspectRatio'] ?? 'auto' ), array( 'auto', '16-9', '4-3', '1-1' ), true ) ? (string) $config['aspectRatio'] : 'auto';
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'id'              => $id,
				'class'           => trim( $classes ),
				'style'           => $this->chart_vars( $config ),
				'data-chart-type' => $type,
				'data-aspect'     => $aspect,
			)
		);
		$out                = '<figure ' . $wrapper_attributes . '>' . $this->chart_scoped_css( $id, $config );
		$out               .= '<div class="ntc-chart-heading">';
		if ( $config['title'] ) {
			$out .= '<h3 class="ntc-chart-title">' . esc_html( $config['title'] ) . '</h3>';
		}if ( $config['subtitle'] ) {
			$out .= '<div class="ntc-chart-subtitle">' . esc_html( $config['subtitle'] ) . '</div>';
		}if ( $config['directionLabel'] || 'neutral' !== $config['direction'] ) {
			$direction = ! empty( $config['directionLabel'] ) ? $config['directionLabel'] : ( 'lower' === $config['direction'] ? __( 'Lower is better', 'native-tables-charts' ) : __( 'Higher is better', 'native-tables-charts' ) );
			$out      .= '<div class="ntc-chart-direction">' . esc_html( $direction ) . '</div>';
		}$out  .= '</div>';
		$legend = (string) ( $config['legendLabel'] ?? '' );
		if ( '' === $legend ) {
			$first_value = absint( $config['valueColumns'][0] ?? 1 );
			$legend      = (string) ( $columns[ $first_value ]['label'] ?? '' );
		}if ( '' !== $legend ) {
			$out .= '<div class="ntc-chart-meta"><div class="ntc-chart-legend"><i aria-hidden="true"></i>' . esc_html( $legend ) . '</div>';
			if ( ! empty( $config['sortLabel'] ) ) {
				$out .= '<div class="ntc-chart-sort-label">' . esc_html( $config['sortLabel'] ) . '</div>';
			}$out .= '</div>';}
		$value_cols = array_slice( array_map( 'absint', (array) ( $config['valueColumns'] ?? array() ) ), 0, 6 );
		if ( count( $value_cols ) > 1 && in_array( $type, array( 'grouped-bar', 'stacked-bar', 'line', 'scatter' ), true ) ) {
			$out .= '<div class="ntc-series-legend">';
			foreach ( $value_cols as $si => $v ) {
				/* translators: %d: series number. */
				$out .= '<span><i class="ntc-series-' . ( $si % 6 ) . '" aria-hidden="true"></i>' . esc_html( $columns[ $v ]['label'] ?? sprintf( __( 'Series %d', 'native-tables-charts' ), $si + 1 ) ) . '</span>';
			}$out .= '</div>';
		}
		$out .= '<div class="ntc-chart-body">';
		switch ( $type ) {
			case 'dual-metric':
				$out .= $this->chart_dual( $chart_rows, $columns, $config );
				break;
			case 'vertical-bar':
				$out .= $this->chart_vertical( $chart_rows, $columns, $config );
				break;
			case 'grouped-bar':
				$out .= $this->chart_grouped( $chart_rows, $columns, $config, false );
				break;
			case 'stacked-bar':
				$out .= $this->chart_grouped( $chart_rows, $columns, $config, true );
				break;
			case 'line':
				$out .= $this->chart_line( $chart_rows, $columns, $config, false );
				break;
			case 'scatter':
				$out .= $this->chart_line( $chart_rows, $columns, $config, true );
				break;
			case 'donut':
				$out .= $this->chart_donut( $chart_rows, $columns, $config );
				break;
			case 'horizontal-bar':
			default:
				$out .= $this->chart_horizontal( $chart_rows, $columns, $config );
				break;
		}
		$out .= '</div>';
		if ( ! empty( $config['axisLabel'] ) ) {
			$out .= '<div class="ntc-chart-axis-label">' . esc_html( $config['axisLabel'] ) . '</div>';}
		$updated_line = $this->updated_date_html( $config, $data );
		if ( $config['footer'] || $config['secondaryFooter'] || $config['source'] || '' !== $updated_line ) {
			$out .= '<figcaption class="ntc-chart-footer">';
			if ( $config['footer'] ) {
				$out .= '<div>' . esc_html( $config['footer'] ) . '</div>';
			}if ( $config['secondaryFooter'] ) {
				$out .= '<div>' . esc_html( $config['secondaryFooter'] ) . '</div>';
			}if ( $config['source'] ) {
				$out .= '<div>' . esc_html( $config['source'] ) . '</div>';
			}if ( '' !== $updated_line ) {
				$out .= $updated_line;
			}$out .= '</figcaption>'; }
		$data_mode = sanitize_key( (string) ( $config['accessibleDataMode'] ?? 'screenreader' ) );
		if ( ! in_array( $data_mode, array( 'screenreader', 'collapsible', 'visible', 'disabled' ), true ) ) {
			$data_mode = 'screenreader';}
		if ( 'collapsible' === $data_mode ) {
			$out .= '<details class="ntc-chart-data"><summary>' . esc_html__( 'View chart data', 'native-tables-charts' ) . '</summary>' . $this->accessible_chart_table( $chart_rows, $columns, $config ) . '</details>';
		} elseif ( 'visible' === $data_mode ) {
			$out .= '<section class="ntc-chart-data ntc-chart-data-visible"><h4>' . esc_html__( 'Chart data', 'native-tables-charts' ) . '</h4>' . $this->accessible_chart_table( $chart_rows, $columns, $config ) . '</section>';
		} elseif ( 'screenreader' === $data_mode ) {
			$out .= '<div class="ntc-chart-data-sr ntc-sr-only">' . $this->accessible_chart_table( $chart_rows, $columns, $config ) . '</div>';}
		if ( ! empty( $config['enableExport'] ) ) {
			$png  = in_array( $type, array( 'donut', 'line', 'scatter' ), true );
			$out .= '<div class="ntc-chart-tools"><button type="button" class="ntc-export-btn" data-format="csv">' . esc_html__( 'Download CSV', 'native-tables-charts' ) . '</button>' . ( $png ? '<button type="button" class="ntc-export-btn" data-format="png">' . esc_html__( 'Download PNG', 'native-tables-charts' ) . '</button>' : '' ) . '</div>';
			$out .= '<div class="ntc-chart-export-data ntc-sr-only">' . $this->accessible_chart_table( $chart_rows, $columns, $config ) . '</div>';
			wp_enqueue_script( 'ntc-frontend' );
		}
		$out .= '</figure>' . $this->schema_json( $data, $config, $columns );
		return $out;
	}

	private function prepare_chart_rows( array $rows, array $columns, array $c ): array {
		$sort = absint( $c['sortColumn'] ?? ( $c['valueColumns'][0] ?? 1 ) );
		$dir  = $c['sortDirection'] ?? 'desc';
		if ( 'none' !== $dir ) {
			usort(
				$rows,
				function ( $a, $b ) use ( $sort, $dir ) {
					$cmp = NTC_Formulas::numeric( $a[ $sort ] ?? 0 ) <=> NTC_Formulas::numeric( $b[ $sort ] ?? 0 );
					return 'desc' === $dir ? -$cmp : $cmp;
				}
			);}
		return $rows;
	}

	private function is_highlight( $label, array $c ): bool {
		return in_array( (string) $label, array_map( 'strval', (array) $c['highlightValues'] ), true );}
	private function format_value( $v, array $c, array $col = array() ): string {
		$n   = NTC_Formulas::numeric( $v );
		$dec = $c['decimals'];
		if ( 'auto' === $dec ) {
			$d = ( (float) (int) $n === $n ) ? 0 : 1;
		} else {
			$d = max( 0, min( 6, absint( $dec ) ) );
		}$unit = (string) ( $col['unit'] ?? $c['unit'] ?? '' );
		return number_format_i18n( $n, $d ) . ( '' !== $unit ? ' ' . $unit : '' );}
	private function max_for( array $rows, int $col ): float {
		$max = 0;
		foreach ( $rows as $r ) {
			$max = max( $max, abs( NTC_Formulas::numeric( $r[ $col ] ?? 0 ) ) );
		}return $max ? $max : 1;}
	private function nice_max( float $max ): float {
		if ( $max <= 0 ) {
			return 1;
		}$power   = pow( 10, floor( log10( $max ) ) );
		$fraction = $max / $power;
		if ( $fraction <= 1 ) {
			$nice = 1;
		} elseif ( $fraction <= 2 ) {
			$nice = 2;
		} elseif ( $fraction <= 2.5 ) {
			$nice = 2.5;
		} elseif ( $fraction <= 5 ) {
			$nice = 5;
		} else {
			$nice = 10;
		}
		return $nice * $power;}
	private function chart_axis( float $max, array $c ): string {
		if ( empty( $c['showAxis'] ) ) {
			return '';
		}$ticks = '';for ( $i = 0;$i <= 5;$i++ ) {
			$v      = $max * ( $i / 5 );
			$d      = ( (float) (int) $v === $v ) ? 0 : 1;
			$ticks .= '<span>' . esc_html( number_format_i18n( $v, $d ) ) . '</span>';
		}return '<div class="ntc-hbar-axis"><span aria-hidden="true"></span><div class="ntc-axis-ticks">' . $ticks . '</div><span aria-hidden="true"></span></div>';}

	private function chart_horizontal( array $rows, array $columns, array $c ): string {
		$l   = absint( $c['labelColumn'] );
		$v   = absint( $c['valueColumns'][0] ?? 1 );
		$max = $this->nice_max( $this->max_for( $rows, $v ) );
		$out = '<div class="ntc-hbars" role="group" aria-label="' . esc_attr( ! empty( $c['title'] ) ? $c['title'] : __( 'Horizontal bar chart', 'native-tables-charts' ) ) . '">';
		foreach ( $rows as $r ) {
			$label = (string) ( $r[ $l ] ?? '' );
			$val   = NTC_Formulas::numeric( $r[ $v ] ?? 0 );
			$pct   = max( 0, min( 100, ( $val / $max ) * 100 ) );
			$hl    = $this->is_highlight( $label, $c );
			$aria  = $label . ': ' . $this->format_value( $val, $c, $columns[ $v ] ?? array() );
			$out  .= '<div class="ntc-hbar-row' . ( $hl ? ' is-highlight' : '' ) . '" tabindex="0" aria-label="' . esc_attr( $aria ) . '"><div class="ntc-hbar-label">' . esc_html( $label ) . '</div><div class="ntc-hbar-track"><span class="ntc-hbar-fill" style="width:' . esc_attr( $pct ) . '%"></span></div>';
			if ( $c['showValues'] ) {
				$out .= '<div class="ntc-hbar-value">' . esc_html( $this->format_value( $val, $c, $columns[ $v ] ?? array() ) ) . '</div>';
			}$out .= '</div>';}
		$out .= $this->chart_axis( $max, $c );
		return $out . '</div>';
	}

	private function chart_dual( array $rows, array $columns, array $c ): string {
		$l    = absint( $c['labelColumn'] );
		$vals = array_slice( array_map( 'absint', (array) $c['valueColumns'] ), 0, 2 );
		if ( count( $vals ) < 2 ) {
			$vals = array( $vals[0] ?? 1, 2 );
		}$out = '<div class="ntc-dual" style="--ntc-chart-breakpoint:' . absint( $c['mobileBreakpoint'] ) . 'px">';
		foreach ( $vals as $v ) {
			$max  = $this->nice_max( $this->max_for( $rows, $v ) );
			$out .= '<section class="ntc-dual-panel"><h4>' . esc_html( $columns[ $v ]['label'] ?? ( 'Metric ' . ( $v + 1 ) ) ) . '</h4><div class="ntc-hbars">';
			foreach ( $rows as $r ) {
				$label = (string) ( $r[ $l ] ?? '' );
				$val   = NTC_Formulas::numeric( $r[ $v ] ?? 0 );
				$pct   = max( 0, min( 100, ( $val / $max ) * 100 ) );
				$hl    = $this->is_highlight( $label, $c );
				$aria  = $label . ': ' . $this->format_value( $val, $c, $columns[ $v ] ?? array() );
				$out  .= '<div class="ntc-hbar-row' . ( $hl ? ' is-highlight' : '' ) . '" tabindex="0" aria-label="' . esc_attr( $aria ) . '"><div class="ntc-hbar-label">' . esc_html( $label ) . '</div><div class="ntc-hbar-track"><span class="ntc-hbar-fill" style="width:' . esc_attr( $pct ) . '%"></span></div>';
				if ( $c['showValues'] ) {
					$out .= '<div class="ntc-hbar-value">' . esc_html( $this->format_value( $val, $c, $columns[ $v ] ?? array() ) ) . '</div>';
				}$out .= '</div>';
			}$out .= '</div>' . $this->chart_axis( $max, $c ) . '</section>';}
		return $out . '</div>';
	}

	private function chart_vertical( array $rows, array $columns, array $c ): string {
		$l   = absint( $c['labelColumn'] );
		$v   = absint( $c['valueColumns'][0] ?? 1 );
		$max = $this->max_for( $rows, $v );
		$out = '<div class="ntc-vbars">';
		foreach ( $rows as $r ) {
			$label = (string) ( $r[ $l ] ?? '' );
			$val   = NTC_Formulas::numeric( $r[ $v ] ?? 0 );
			$pct   = max( 1, min( 100, ( $val / $max ) * 100 ) );
			$aria  = $label . ': ' . $this->format_value( $val, $c, $columns[ $v ] ?? array() );
			$out  .= '<div class="ntc-vbar-item' . ( $this->is_highlight( $label, $c ) ? ' is-highlight' : '' ) . '" tabindex="0" aria-label="' . esc_attr( $aria ) . '">';
			if ( $c['showValues'] ) {
				$out .= '<div class="ntc-vbar-value">' . esc_html( $this->format_value( $val, $c, $columns[ $v ] ?? array() ) ) . '</div>';
			} else {
				$out .= '<div class="ntc-vbar-value" aria-hidden="true"></div>';
			}
			$out .= '<div class="ntc-vbar-track"><span class="ntc-vbar-fill" style="height:' . esc_attr( $pct ) . '%"></span></div><div class="ntc-vbar-label">' . esc_html( $label ) . '</div></div>';
		}return $out . '</div>';
	}

	private function chart_grouped( array $rows, array $columns, array $c, bool $stacked ): string {
		$l    = absint( $c['labelColumn'] );
		$vals = array_slice( array_map( 'absint', (array) $c['valueColumns'] ), 0, 6 );
		if ( ! $vals ) {
			$vals = array( 1 );
		}$max = 1;
		foreach ( $vals as $v ) {
			$max = max( $max, $this->max_for( $rows, $v ) );
		}$out = '<div class="ntc-grouped ' . ( $stacked ? 'is-stacked' : '' ) . '">';
		foreach ( $rows as $r ) {
			$label = (string) ( $r[ $l ] ?? '' );
			$hl    = $this->is_highlight( $label, $c );
			$out  .= '<div class="ntc-group-row' . ( $hl ? ' is-highlight' : '' ) . '"><div class="ntc-group-label">' . esc_html( $label ) . '</div><div class="ntc-group-bars">';
			if ( $stacked ) {
				$sum = 0;
				foreach ( $vals as $v ) {
					$sum += max( 0, NTC_Formulas::numeric( $r[ $v ] ?? 0 ) );
				}foreach ( $vals as $si => $v ) {
					$val  = max( 0, NTC_Formulas::numeric( $r[ $v ] ?? 0 ) );
					$pct  = $sum ? ( $val / $sum ) * 100 : 0;
					$out .= '<span class="ntc-stack-seg ntc-series-' . ( $si % 6 ) . '" style="width:' . esc_attr( $pct ) . '%" title="' . esc_attr( ( $columns[ $v ]['label'] ?? '' ) . ': ' . $this->format_value( $val, $c, $columns[ $v ] ?? array() ) ) . '"></span>';
				}
			} else {
				foreach ( $vals as $si => $v ) {
						$val  = NTC_Formulas::numeric( $r[ $v ] ?? 0 );
						$pct  = max( 0, min( 100, ( $val / $max ) * 100 ) );
						$out .= '<div class="ntc-group-bar"><span class="ntc-series-' . ( $si % 6 ) . '" style="width:' . esc_attr( $pct ) . '%"></span>';
					if ( $c['showValues'] ) {
						$out .= '<em>' . esc_html( $this->format_value( $val, $c, $columns[ $v ] ?? array() ) ) . '</em>';
					}$out .= '</div>';
				}
			}$out .= '</div></div>';
		}return $out . '</div>';
	}

	private function chart_line( array $rows, array $columns, array $c, bool $scatter ): string {
		$l      = absint( $c['labelColumn'] );
		$series = array_slice( array_map( 'absint', (array) $c['valueColumns'] ), 0, 6 );
		if ( ! $series ) {
			$series = array( 1 );
		}
		$all = array();
		foreach ( $series as $v ) {
			foreach ( $rows as $r ) {
				$all[] = NTC_Formulas::numeric( $r[ $v ] ?? 0 );
			}
		}$min = min( $all ? $all : array( 0 ) );
		$max  = max( $all ? $all : array( 1 ) );
		if ( $max === $min ) {
			$max = $min + 1;
		}
		$w      = 1000;
		$h      = 440;
		$pad_l  = 70;
		$pad_r  = 40;
		$pad_t  = 30;
		$pad_b  = 90;
		$pw     = $w - $pad_l - $pad_r;
		$ph     = $h - $pad_t - $pad_b;
		$n      = max( 1, count( $rows ) - 1 );
		$colors = array( 'var(--ntc-primary)', 'var(--ntc-secondary)', '#8b5cf6', '#14b8a6', '#f59e0b', '#ef4444' );
		$out    = '<svg class="ntc-svg-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr( ! empty( $c['title'] ) ? $c['title'] : ( $scatter ? __( 'Scatter chart', 'native-tables-charts' ) : __( 'Line chart', 'native-tables-charts' ) ) ) . '">';
		for ( $g = 0;$g <= 4;$g++ ) {
			$y    = $pad_t + $ph * ( $g / 4 );
			$out .= '<line x1="' . $pad_l . '" y1="' . $y . '" x2="' . ( $w - $pad_r ) . '" y2="' . $y . '" class="ntc-svg-grid"/>';}
		foreach ( $series as $si => $v ) {
			$pts = array();
			foreach ( $rows as $i => $r ) {
				$val   = NTC_Formulas::numeric( $r[ $v ] ?? 0 );
				$x     = $pad_l + ( $pw * ( $i / $n ) );
				$y     = $pad_t + $ph - ( ( $val - $min ) / ( $max - $min ) ) * $ph;
				$pts[] = array( $x, $y, $r[ $l ] ?? '', $val );}
			$poly = implode( ' ', array_map( fn( $p )=>round( $p[0], 1 ) . ',' . round( $p[1], 1 ), $pts ) );
			if ( ! $scatter ) {
				$out .= '<polyline points="' . $poly . '" class="ntc-svg-line" style="stroke:' . $colors[ $si % count( $colors ) ] . '" fill="none"/>';
			}
			foreach ( $pts as $p ) {
				$out .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="6" class="ntc-svg-point" style="fill:' . $colors[ $si % count( $colors ) ] . '"><title>' . esc_html( ( $columns[ $v ]['label'] ?? '' ) . ', ' . $p[2] . ': ' . $this->format_value( $p[3], $c, $columns[ $v ] ?? array() ) ) . '</title></circle>';}
		}
		foreach ( $rows as $i => $r ) {
			$x    = $pad_l + ( $pw * ( $i / $n ) );
			$out .= '<text x="' . $x . '" y="' . ( $h - 50 ) . '" class="ntc-svg-label" text-anchor="middle">' . esc_html( $this->truncate( (string) ( $r[ $l ] ?? '' ), 18 ) ) . '</text>';}
		return $out . '</svg>';
	}

	private function chart_donut( array $rows, array $columns, array $c ): string {
		$l   = absint( $c['labelColumn'] );
		$v   = absint( $c['valueColumns'][0] ?? 1 );
		$sum = 0;
		foreach ( $rows as $r ) {
			$sum += max( 0, NTC_Formulas::numeric( $r[ $v ] ?? 0 ) );
		}$sum   = $sum ? $sum : 1;
		$r      = 120;
		$circ   = 2 * pi() * $r;
		$offset = 0;
		$colors = array( 'var(--ntc-primary)', 'var(--ntc-secondary)', '#8b5cf6', '#14b8a6', '#f59e0b', '#ef4444' );
		$out    = '<div class="ntc-donut-wrap"><svg class="ntc-donut" viewBox="0 0 320 320" role="img" aria-label="' . esc_attr( ! empty( $c['title'] ) ? $c['title'] : __( 'Donut chart', 'native-tables-charts' ) ) . '"><circle cx="160" cy="160" r="' . $r . '" class="ntc-donut-bg"/>';
		foreach ( $rows as $i => $row ) {
			$val       = max( 0, NTC_Formulas::numeric( $row[ $v ] ?? 0 ) );
			$len       = $circ * ( $val / $sum );
			$hl        = $this->is_highlight( (string) ( $row[ $l ] ?? '' ), $c );
			$seg_color = $hl ? 'var(--ntc-highlight)' : $colors[ $i % count( $colors ) ];
			$out      .= '<circle cx="160" cy="160" r="' . $r . '" class="ntc-donut-seg' . ( $hl ? ' is-highlight' : '' ) . '" style="stroke:' . $seg_color . ';stroke-dasharray:' . $len . ' ' . ( $circ - $len ) . ';stroke-dashoffset:-' . $offset . '"><title>' . esc_html( ( $row[ $l ] ?? '' ) . ': ' . $this->format_value( $val, $c, $columns[ $v ] ?? array() ) ) . '</title></circle>';
			$offset   += $len;
		}$out .= '</svg><div class="ntc-donut-legend">';
		foreach ( $rows as $i => $row ) {
			$legend = (string) ( $row[ $l ] ?? '' );
			if ( $c['showValues'] ) {
				$legend .= ' — ' . $this->format_value( $row[ $v ] ?? 0, $c, $columns[ $v ] ?? array() );
			}$hl          = $this->is_highlight( (string) ( $row[ $l ] ?? '' ), $c );
			$legend_color = $hl ? 'var(--ntc-highlight)' : $colors[ $i % count( $colors ) ];
			$out         .= '<span class="' . ( $hl ? 'is-highlight' : '' ) . '"><i style="background:' . $legend_color . '"></i>' . esc_html( $legend ) . '</span>';
		}$out .= '</div></div>';
		return $out;
	}

	private function accessible_chart_table( array $rows, array $columns, array $c ): string {
		$l    = absint( $c['labelColumn'] );
		$vals = array_map( 'absint', (array) $c['valueColumns'] );
		if ( ! $vals ) {
			$vals = array( 1 );
		}$out = '<table><thead><tr><th scope="col">' . esc_html( $columns[ $l ]['label'] ?? __( 'Item', 'native-tables-charts' ) ) . '</th>';
		foreach ( $vals as $v ) {
			$out .= '<th scope="col">' . esc_html( $columns[ $v ]['label'] ?? __( 'Value', 'native-tables-charts' ) ) . '</th>';
		}$out .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$label = (string) ( $row[ $l ] ?? '' );
			$out  .= '<tr' . ( $this->is_highlight( $label, $c ) ? ' class="is-highlight"' : '' ) . '><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( $vals as $v ) {
				$out .= '<td>' . esc_html( $this->format_value( $row[ $v ] ?? 0, $c, $columns[ $v ] ?? array() ) ) . '</td>';
			}$out .= '</tr>';
		}$out .= '</tbody></table>';
		return $out;
	}

	private function chart_scoped_css( string $id, array $c ): string {
		$bp     = max( 360, min( 1200, absint( $c['mobileBreakpoint'] ?? 620 ) ) );
		$prefix = '#' . esc_attr( $id );
		$css    = '<style>';
		$css   .= '@container (max-width:' . $bp . 'px){' . $prefix . ' .ntc-chart-title{font-size:var(--ntc-mobile-title-size)}' . $prefix . ' .ntc-hbar-label,' . $prefix . ' .ntc-vbar-label,' . $prefix . ' .ntc-group-label,' . $prefix . ' .ntc-donut-legend span{font-size:var(--ntc-mobile-label-size)}' . $prefix . ' .ntc-hbar-value,' . $prefix . ' .ntc-vbar-value,' . $prefix . ' .ntc-group-bar em{font-size:var(--ntc-mobile-value-size)}' . $prefix . ' .ntc-axis-ticks,' . $prefix . ' .ntc-chart-axis-label{font-size:var(--ntc-mobile-axis-size)}' . $prefix . ' .ntc-chart-meta,' . $prefix . ' .ntc-series-legend{font-size:var(--ntc-mobile-legend-size)}' . $prefix . ' .ntc-chart-footer{font-size:var(--ntc-mobile-footer-size)}' . $prefix . ' .ntc-dual{grid-template-columns:1fr}' . $prefix . ' .ntc-hbar-row{grid-template-columns:1fr auto;gap:5px 8px}' . $prefix . ' .ntc-hbar-label{grid-column:1/-1;text-align:left}' . $prefix . ' .ntc-hbar-track{grid-column:1}' . $prefix . ' .ntc-hbar-value{grid-column:2}' . $prefix . ' .ntc-hbar-axis{grid-template-columns:1fr auto;gap:8px}' . $prefix . ' .ntc-hbar-axis>span:first-child{display:none}' . $prefix . ' .ntc-group-row{grid-template-columns:1fr}' . $prefix . ' .ntc-group-label{text-align:left}' . $prefix . ' .ntc-donut-wrap{grid-template-columns:1fr}}';
		$css   .= '@media (max-width:' . $bp . 'px){' . $prefix . ' .ntc-dual{grid-template-columns:1fr}' . $prefix . ' .ntc-hbar-row{grid-template-columns:1fr auto;gap:5px 8px}' . $prefix . ' .ntc-hbar-label{grid-column:1/-1;text-align:left}' . $prefix . ' .ntc-hbar-axis{grid-template-columns:1fr auto;gap:8px}' . $prefix . ' .ntc-hbar-axis>span:first-child{display:none}}';
		if ( 'auto' === ( $c['themeMode'] ?? 'fixed' ) ) {
			$css .= '@media (prefers-color-scheme: dark){' . $prefix . '{--ntc-chart-bg:' . esc_attr( self::safe_css_value( $c['darkBackground'] ) ) . ';--ntc-chart-text:' . esc_attr( self::safe_css_value( $c['darkTextColor'] ) ) . ';--ntc-muted:' . esc_attr( self::safe_css_value( $c['darkMutedColor'] ) ) . ';--ntc-grid:' . esc_attr( self::safe_css_value( $c['darkGridColor'] ) ) . '}}';
		}
		return $css . '</style>';
	}

	private function chart_vars( array $c ): string {
		$vars = array(
			'--ntc-chart-bg'           => $c['background'],
			'--ntc-primary'            => $c['primaryColor'],
			'--ntc-secondary'          => $c['secondaryColor'],
			'--ntc-highlight'          => $c['highlightColor'],
			'--ntc-chart-text'         => $c['textColor'],
			'--ntc-muted'              => $c['mutedColor'],
			'--ntc-grid'               => $c['gridColor'],
			'--ntc-bar-height'         => absint( $c['barHeight'] ) . 'px',
			'--ntc-bar-gap'            => absint( $c['barGap'] ) . 'px',
			'--ntc-title-size'         => absint( $c['titleFontSize'] ) . 'px',
			'--ntc-subtitle-size'      => absint( $c['subtitleFontSize'] ) . 'px',
			'--ntc-direction-size'     => absint( $c['directionFontSize'] ) . 'px',
			'--ntc-label-size'         => absint( $c['labelFontSize'] ) . 'px',
			'--ntc-value-size'         => absint( $c['valueFontSize'] ) . 'px',
			'--ntc-axis-size'          => absint( $c['axisFontSize'] ) . 'px',
			'--ntc-legend-size'        => absint( $c['legendFontSize'] ) . 'px',
			'--ntc-footer-size'        => absint( $c['footerFontSize'] ) . 'px',
			'--ntc-panel-title-size'   => absint( $c['panelTitleFontSize'] ) . 'px',
			'--ntc-mobile-title-size'  => absint( $c['mobileTitleFontSize'] ) . 'px',
			'--ntc-mobile-label-size'  => absint( $c['mobileLabelFontSize'] ) . 'px',
			'--ntc-mobile-value-size'  => absint( $c['mobileValueFontSize'] ) . 'px',
			'--ntc-mobile-axis-size'   => absint( $c['mobileAxisFontSize'] ) . 'px',
			'--ntc-mobile-legend-size' => absint( $c['mobileLegendFontSize'] ) . 'px',
			'--ntc-mobile-footer-size' => absint( $c['mobileFooterFontSize'] ) . 'px',
		);
		$out  = '';
		foreach ( $vars as $k => $v ) {
			$out .= $k . ':' . esc_attr( self::safe_css_value( $v ) ) . ';';
		}return $out;
	}
	private function truncate( string $s, int $n ): string {
		return function_exists( 'mb_strlen' ) && mb_strlen( $s ) > $n ? mb_substr( $s, 0, $n - 1 ) . '…' : ( strlen( $s ) > $n ? substr( $s, 0, $n - 1 ) . '…' : $s );}
}
