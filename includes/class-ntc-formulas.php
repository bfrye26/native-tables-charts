<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Formulas {
	public static function apply( array $rows, array $columns, array $cell_meta, int $average_decimals = 2, string $average_round = 'round' ): array {
		foreach ( $cell_meta as $key => $meta ) {
			if ( empty( $meta['formula'] ) ) {
				continue; }
			if ( ! preg_match( '/^(\d+):(\d+)$/', (string) $key, $m ) ) {
				continue; }
			$r = (int) $m[1];
			$c = (int) $m[2];
			if ( ! isset( $rows[ $r ] ) ) {
				continue; }
			$source = $meta['formulaData'] ?? $meta['formula_data'] ?? '';
			$values = self::resolve_values( $source, $rows, $r );
			if ( ! $values ) {
				continue; }
			$formula = strtolower( (string) $meta['formula'] );
			$value   = null;
			switch ( $formula ) {
				case 'sum':
					$value = array_sum( $values );
					break;
				case 'sub':
				case 'subtract':
					$value = array_shift( $values );
					foreach ( $values as $v ) {
						$value -= $v;
					} break;
				case 'min':
					$value = min( $values );
					break;
				case 'max':
					$value = max( $values );
					break;
				case 'average':
				case 'avg':
					$value = array_sum( $values ) / count( $values );
					$dec   = max( 0, min( 8, $average_decimals ) );
					$mode  = match ( $average_round ) {
						'half_down' => PHP_ROUND_HALF_DOWN,
						'half_even' => PHP_ROUND_HALF_EVEN,
						'half_odd'  => PHP_ROUND_HALF_ODD,
						default     => PHP_ROUND_HALF_UP,
					};
					$value = round( $value, $dec, $mode );
					break;
				default:
					break;
			}
			if ( null !== $value ) {
				$rows[ $r ][ $c ] = (string) $value; }
		}
		return $rows;
	}

	private static function resolve_values( $source, array $rows, int $row_index ): array {
		$values = array();
		if ( is_array( $source ) ) {
			$tokens = $source; } else {
			$tokens = preg_split( '/\s*,\s*/', (string) $source, -1, PREG_SPLIT_NO_EMPTY ); }
			foreach ( $tokens as $token ) {
				if ( is_numeric( $token ) ) {
					$col = max( 0, (int) $token - 1 );
					if ( isset( $rows[ $row_index ][ $col ] ) ) {
						$values[] = (float) self::numeric( $rows[ $row_index ][ $col ] );}
				} elseif ( preg_match( '/^([A-Z]+)(\d+)$/i', (string) $token, $m ) ) {
					$col = self::column_index( $m[1] );
					$row = max( 0, (int) $m[2] - 1 );
					if ( isset( $rows[ $row ][ $col ] ) ) {
						$values[] = (float) self::numeric( $rows[ $row ][ $col ] );}
				}
			}
			return array_values( array_filter( $values, 'is_finite' ) );
	}

	public static function numeric( $value ): float {
		$value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
		$value = preg_replace( '/[^0-9.\-]+/', '', $value );
		return is_numeric( $value ) ? (float) $value : 0.0;
	}

	private static function column_index( string $letters ): int {
		$letters = strtoupper( $letters );
		$n       = 0;
		for ( $i = 0;$i < strlen( $letters );$i++ ) {
			$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );}
		return max( 0, $n - 1 );
	}
}
