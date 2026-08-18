<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Plugin {
	private static ?self $instance = null;
	private NTC_Repository $repo;
	private NTC_Renderer $renderer;
	private NTC_Migrator $migrator;
	private NTC_Admin $admin;
	public static function instance(): self {
		return self::$instance ??= new self(); }
	private function __construct() {
		$this->repo     = new NTC_Repository();
		$this->renderer = new NTC_Renderer( $this->repo );
		$this->migrator = new NTC_Migrator( $this->repo );
		$this->admin    = new NTC_Admin( $this->repo, $this->migrator );
	}
	public function boot(): void {
		if ( get_option( 'ntc_db_version' ) !== NTC_VERSION ) {
			NTC_Activator::activate(); }
		add_action( 'init', array( $this, 'register_assets_and_blocks' ) );
		add_action( 'rest_api_init', array( new NTC_REST( $this->repo ), 'register' ) );
		NTC_Sync::register();
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'admin_assets' ) );
		add_action( 'admin_init', array( $this->admin, 'handle_actions' ) );
		add_filter( 'block_categories_all', array( $this, 'block_category' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ), 1 );
		add_action( 'save_post', array( $this, 'invalidate_usage_cache' ) );
		add_shortcode( 'ntc_dataset', array( $this, 'shortcode_dataset' ) );
	}

	public function shortcode_dataset( $atts ): string {
		$a  = shortcode_atts(
			array(
				'id'   => 0,
				'view' => 0,
				'type' => 'table',
			),
			$atts,
			'ntc_dataset'
		);
		$id = absint( $a['id'] );
		if ( ! $id ) {
			return ''; }
		$view_id    = absint( $a['view'] );
		$attributes = array(
			'mode'      => 'dataset',
			'datasetId' => $id,
			'viewId'    => $view_id,
			'columns'   => array(),
			'rows'      => array(),
			'config'    => array(),
			'cellMeta'  => array(),
			'widthMode' => 'content',
			'align'     => '',
		);
		if ( $view_id ) {
			$view = $this->repo->get_view( $view_id );
			if ( $view ) {
				$attributes['mode']   = 'view';
				$attributes['config'] = (array) $view['config'];
				if ( 'table' === $view['type'] && ! empty( $view['config']['cellMeta'] ) && is_array( $view['config']['cellMeta'] ) ) {
					$attributes['cellMeta'] = $view['config']['cellMeta'];
					unset( $attributes['config']['cellMeta'] );
				}
				return 'chart' === $view['type'] ? $this->renderer->render_chart( $attributes ) : $this->renderer->render_table( $attributes );
			}
		}
		$type_attr = is_array( $a['type'] ) ? (string) end( $a['type'] ) : (string) $a['type'];
		if ( 'chart' === sanitize_key( $type_attr ) ) {
			$attributes['config'] = array(
				'chartType'    => 'horizontal-bar',
				'labelColumn'  => 0,
				'valueColumns' => array( 1 ),
				'preset'       => 'benchmark-dark',
			);
			return $this->renderer->render_chart( $attributes );
		}
		return $this->renderer->render_table( $attributes );
	}

	public function register_frontend_assets(): void {
		wp_register_script( 'ntc-frontend', NTC_URL . 'assets/js/frontend.js', array(), NTC_VERSION, true );
	}

	public function register_assets_and_blocks(): void {
		wp_register_script( 'ntc-block-editor', NTC_URL . 'assets/js/block-editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-api-fetch', 'wp-data', 'wp-server-side-render', 'wp-notices' ), NTC_VERSION, true );
		wp_register_style( 'ntc-block-editor-style', NTC_URL . 'assets/css/editor.css', array( 'wp-edit-blocks' ), NTC_VERSION );
		wp_register_style( 'ntc-frontend-style', NTC_URL . 'assets/css/frontend.css', array(), NTC_VERSION );
		wp_set_script_translations( 'ntc-block-editor', 'native-tables-charts' );
		wp_add_inline_script(
			'ntc-block-editor',
			'window.NTC_EDITOR=' . wp_json_encode(
				array(
					'restBase'               => esc_url_raw( rest_url( 'ntc/v1/' ) ),
					'nonce'                  => wp_create_nonce( 'wp_rest' ),
					'tableDefaults'          => NTC_Renderer::table_defaults(),
					'chartDefaults'          => NTC_Renderer::chart_defaults(),
					'tablePresets'           => NTC_Renderer::table_presets(),
					'chartPresets'           => NTC_Renderer::chart_presets(),
					'chartTypographyPresets' => NTC_Renderer::chart_typography_presets(),
					'chartDensityPresets'    => NTC_Renderer::chart_density_presets(),
					'cellFeatures'           => array_merge( NTC_Renderer::cell_feature_defaults(), (array) get_option( 'ntc_cell_features', array() ) ),
					'maxInlineRows'          => 250,
				)
			) . ';',
			'before'
		);
		register_block_type( NTC_DIR . 'blocks/table', array( 'render_callback' => array( $this->renderer, 'render_table' ) ) );
		register_block_type( NTC_DIR . 'blocks/chart', array( 'render_callback' => array( $this->renderer, 'render_chart' ) ) );
		register_block_pattern(
			'ntc/review-card',
			array(
				'title'       => __( 'Review Card (Native Tables & Charts)', 'native-tables-charts' ),
				'description' => __( 'Gauge score, category radar chart and pros/cons table for reviews.', 'native-tables-charts' ),
				'categories'  => array( 'ntc-data' ),
				'content'     => '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"width":"33%"} --><div class="wp-block-column" style="flex-basis:33%"><!-- wp:ntc/chart {"config":{"chartType":"gauge","title":"Score","labelColumn":0,"valueColumns":[1],"preset":"benchmark-dark","schemaType":"off"}} /--></div><!-- /wp:column --><!-- wp:column {"width":"67%"} --><div class="wp-block-column" style="flex-basis:67%"><!-- wp:ntc/chart {"config":{"chartType":"radar","title":"Category Scores","labelColumn":0,"valueColumns":[1],"preset":"benchmark-dark"}} /--></div><!-- /wp:column --></div><!-- /wp:columns --><!-- wp:ntc/table {"config":{"preset":"editorial","responsiveMode":"scroll"}} /-->',
			)
		);
	}
	public function invalidate_usage_cache(): void {
		delete_transient( 'ntc_dataset_usage_counts' );
		update_option( 'ntc_post_source_version', (int) get_option( 'ntc_post_source_version', 0 ) + 1 ); }
	public function block_category( array $categories, $context ): array {
		array_unshift(
			$categories,
			array(
				'slug'  => 'ntc-data',
				'title' => __( 'Data & Visualizations', 'native-tables-charts' ),
				'icon'  => 'chart-bar',
			)
		);
		return $categories;}
}
