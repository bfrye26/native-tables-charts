<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Posts_Query {
	public static function rows_for( array $config ): array {
		$post_type  = sanitize_key( (string) ( $config['post_type'] ?? 'post' ) );
		$ppp        = max( 1, min( 200, absint( $config['posts_per_page'] ?? 50 ) ) );
		$label_meta = sanitize_text_field( (string) ( $config['meta_label'] ?? '' ) );
		$value_meta = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $config['meta_value'] ?? array() ) ) ) );
		$query      = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $ppp,
			'orderby'             => in_array( $config['orderby'] ?? '', array( 'date', 'title', 'meta_value_num', 'meta_value' ), true ) ? $config['orderby'] : 'date',
			'order'               => 'ASC' === strtoupper( (string) ( $config['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( 'meta_value_num' === $query['orderby'] && $value_meta ) {
			$query['meta_key'] = $value_meta[0]; }
		$posts = get_posts( $query );
		$rows  = array();
		foreach ( $posts as $p ) {
			$row = array();
			if ( $label_meta ) {
				$row[] = (string) get_post_meta( $p->ID, $label_meta, true );
			} else {
				$row[] = (string) get_the_title( $p );
			}
			foreach ( $value_meta as $k ) {
				$row[] = (string) get_post_meta( $p->ID, $k, true );
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
