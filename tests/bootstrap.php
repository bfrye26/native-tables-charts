<?php
// phpcs:ignoreFile WordPress.Files.FileName,Generic.Files.OneObjectStructurePerFile,WordPress.NamingConventions.ValidFunctionName -- test stubs mimic WordPress core APIs (class/file names and ArrayAccess method names are fixed by upstream).
define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
function __( $s, $d = null ) {
	return $s; }
function esc_html__( $s, $d = null ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html_e( $s, $d = null ) {
	echo htmlspecialchars( (string) $s, ENT_QUOTES ); }
function absint( $v ) {
	return abs( (int) $v ); }
function esc_attr( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) {
	return (string) $v; }
function esc_url_raw( $v ) {
	return (string) $v; }
function sanitize_text_field( $v ) {
	return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) {
	return preg_replace( '/[^a-z0-9_\-]/i', '', strtolower( (string) $v ) ); }
function sanitize_title( $v ) {
	return preg_replace( '/[^a-z0-9_\-]/i', '-', strtolower( trim( (string) $v ) ) ); }
function sanitize_html_class( $v ) {
	return preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ); }
function wp_strip_all_tags( $v, $r = false ) {
	return preg_replace( '/<[^>]*>/', '', (string) $v ); }
function wp_check_invalid_utf8( $v, $s = false ) {
	return (string) $v; }
function wp_json_encode( $v, $f = 0 ) {
	return json_encode( $v, $f ); }
function is_wp_error( $v ) {
	return $v instanceof WP_Error; }
function current_time( $f, $g = false ) {
	return gmdate( 'Y-m-d H:i:s' ); }
function get_current_user_id() {
	return 1; }
function get_option( $k, $d = false ) {
	return $d; }
function get_date_from_gmt( $t, $f = '' ) {
	return (string) $t; }
function wp_unique_id( $p = '' ) {
	static $i = 0;
	return $p . ( ++$i ); }
function get_block_wrapper_attributes( $a = array() ) {
	$o = '';
	foreach ( $a as $k => $v ) {
		$o .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES ) . '"';
	} return $o; }
function wp_enqueue_script( $h ) {
	$GLOBALS['enqueued'][] = $h; }
function wp_kses_post( $v ) {
	return (string) $v; }
function wp_kses( $v, $a = array(), $p = array() ) {
	return (string) $v; }
function wp_kses_allowed_html( $c = '' ) {
	return array(); }
function wp_allowed_protocols() {
	return array( 'http', 'https' ); }
function wp_next_scheduled( $h ) {
	return false; }
function wp_schedule_event( $t, $r, $h ) {
	return true; }
function wp_clear_scheduled_hook( $h ) {
	$GLOBALS['cleared_hook'] = $h; }
function get_post() {
	return null; }
function get_post_modified_time( $d = 'U', $g = false, $p = null ) {
	return '2026-08-17T00:00:00+00:00'; }
function wp_remote_get( $url, $args = array() ) {
	return $GLOBALS['fake_http'] ?? array(
		'response' => array( 'code' => 404 ),
		'body'     => '',
	); }
function wp_remote_retrieve_response_code( $r ) {
	return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) {
	return $r['body'] ?? ''; }
class WP_Error {
	private $msg;
	public function __construct( $c = '', $m = '', $d = array() ) {
		$this->msg = $m;
	} public function get_error_message() {
		return $this->msg; }
}
class WP_REST_Request {
	private $p;
	private $g;
	public function __construct( $p = array(), $g = array() ) {
		$this->p = $p;
		$this->g = $g;
	} public function get_json_params() {
		return $this->p;
	} public function offsetExists( $k ) {
		return isset( $this->g[ $k ] );
	} public function offsetGet( $k ) {
		return $this->g[ $k ] ?? null; }
}
class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $d = null, $s = 200, $h = array() ) {
		$this->data   = $d;
		$this->status = $s; }
}
function rest_ensure_response( $d ) {
	return $d instanceof WP_REST_Response ? $d : new WP_REST_Response( $d ); }
function add_action( $h, $c ) {}
function add_filter( $h, $c ) {}
function add_shortcode( $t, $c ) {}
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = is_array( $atts ) ? $atts : array();
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
function register_activation_hook( $f, $c ) {}
function register_deactivation_hook( $f, $c ) {}
function register_uninstall_hook( $f, $c ) {}
function plugins_loaded() {}
function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . '/'; }
function plugin_dir_url( $file ) {
	return ''; }
$GLOBALS['wpdb'] = new class() {
	public $prefix      = 'wp_';
	public $insert_id   = 42;
	public $last_insert = array();
	public function get_var( $q ) {
		if ( $GLOBALS['fake_slug_taken'] ) {
			$GLOBALS['fake_slug_taken'] = false;
			return 7;
		} return null; }
	public function get_row( $q, $o = 'OBJECT' ) {
		return null; }
	public function get_results( $q, $o = 'OBJECT' ) {
		return array(); }
	public function get_col( $q ) {
		return array(); }
	public function insert( $t, $d, $f = array() ) {
		$this->last_insert = $d;
		return 1; }
	public function update( $t, $d, $w, $f = array(), $wf = array() ) {
		$GLOBALS['last_update'] = array( $d, $w );
		return 1; }
	public function delete( $t, $w, $f = array() ) {
		return 1; }
	public function query( $q ) {
		return 1; }
	public function prepare( $q, ...$a ) {
		return $q; }
};
require dirname( __DIR__ ) . '/includes/class-ntc-repository.php';
