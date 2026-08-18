<?php
/**
 * Specialized native SVG/HTML chart renderers.
 *
 * These renderers intentionally share the chart block's label/x/value mapping
 * instead of introducing a second data model for advanced visualizations.
 */
final class NTC_Advanced_Charts {
	private const WIDTH  = 760;
	private const HEIGHT = 400;

	public static function types(): array {
		return array(
			'combo', 'histogram', 'boxplot', 'waterfall', 'bullet', 'bubble', 'funnel', 'range-bar', 'timeline', 'slope',
			'treemap', 'sunburst', 'sankey', 'candlestick', 'error-bar', 'calendar-heatmap', 'population-pyramid', 'likert',
			'pareto', 'streamgraph', 'parallel-coordinates', 'network', 'choropleth', 'polar-area',
		);
	}

	public static function render( string $type, array $rows, array $columns, array $config ): string {
		if ( ! in_array( $type, self::types(), true ) ) {
			return '';
		}
		$method = 'chart_' . str_replace( '-', '_', $type );
		return self::$method( $rows, $columns, $config );
	}

	private static function value_columns( array $config, int $limit = 8 ): array {
		$columns = array_values( array_unique( array_map( 'absint', (array) ( $config['valueColumns'] ?? array( 1 ) ) ) ) );
		return array_slice( $columns ?: array( 1 ), 0, $limit );
	}

	private static function label( array $row, array $config ): string {
		return (string) ( $row[ absint( $config['labelColumn'] ?? 0 ) ] ?? '' );
	}

	private static function number( $value ): float {
		$clean = preg_replace( '/[^0-9eE.\-+]/', '', (string) $value );
		return is_numeric( $clean ) ? (float) $clean : 0.0;
	}

	private static function numbers( array $rows, int $column ): array {
		return array_map( static fn( $row ) => self::number( $row[ $column ] ?? 0 ), $rows );
	}

	private static function color( int $index ): string {
		$palette = array( 'var(--ntc-primary)', 'var(--ntc-secondary)', 'var(--ntc-highlight)', '#14b8a6', '#f59e0b', '#ef4444' );
		return $palette[ $index % count( $palette ) ];
	}

	private static function empty_note( string $message ): string {
		return '<div class="ntc-chart-empty-note">' . esc_html( $message ) . '</div>';
	}

	private static function svg( string $body, string $class = '' ): string {
		return '<svg class="ntc-svg-chart ntc-advanced-svg ' . esc_attr( $class ) . '" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '" role="img" aria-hidden="true">' . $body . '</svg>';
	}

	private static function line_path( array $points ): string {
		return implode( ' ', array_map( static fn( $point, $i ) => ( 0 === $i ? 'M' : 'L' ) . round( $point[0], 2 ) . ',' . round( $point[1], 2 ), $points, array_keys( $points ) ) );
	}

	private static function chart_combo( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 2 );
		if ( count( $values ) < 2 ) {
			return self::empty_note( __( 'Choose two metrics for a combo chart.', 'native-tables-charts' ) );
		}
		$bar_values = self::numbers( $rows, $values[0] );
		$line_values = self::numbers( $rows, $values[1] );
		$max = max( array_merge( array( 1.0 ), $bar_values, $line_values ) );
		$count = max( 1, count( $rows ) );
		$step = 650 / $count;
		$body = '<line x1="70" y1="350" x2="730" y2="350" class="ntc-svg-grid"/>';
		$points = array();
		foreach ( $rows as $i => $row ) {
			$x = 78 + ( $i * $step );
			$height = 290 * ( $bar_values[ $i ] / $max );
			$body .= '<rect x="' . round( $x, 2 ) . '" y="' . round( 350 - $height, 2 ) . '" width="' . max( 8, $step * .52 ) . '" height="' . round( $height, 2 ) . '" fill="var(--ntc-primary)" opacity=".78"/>';
			$body .= '<text x="' . round( $x + ( $step * .26 ), 2 ) . '" y="372" text-anchor="middle" class="ntc-svg-label">' . esc_html( self::truncate( self::label( $row, $config ), 12 ) ) . '</text>';
			$points[] = array( $x + ( $step * .26 ), 350 - ( 290 * ( $line_values[ $i ] / $max ) ) );
		}
		$body .= '<path d="' . esc_attr( self::line_path( $points ) ) . '" fill="none" stroke="var(--ntc-highlight)" stroke-width="4"/>';
		foreach ( $points as $point ) {
			$body .= '<circle cx="' . $point[0] . '" cy="' . $point[1] . '" r="5" fill="var(--ntc-highlight)"/>';
		}
		return self::svg( $body, 'ntc-combo-svg' );
	}

	private static function chart_histogram( array $rows, array $columns, array $config ): string {
		$values = self::numbers( $rows, self::value_columns( $config, 1 )[0] );
		$bins = max( 3, min( 20, absint( $config['histogramBins'] ?? 8 ) ) );
		$min = min( $values ?: array( 0 ) );
		$max = max( $values ?: array( 1 ) );
		$span = max( .0001, $max - $min );
		$counts = array_fill( 0, $bins, 0 );
		foreach ( $values as $value ) {
			++$counts[ min( $bins - 1, (int) floor( ( ( $value - $min ) / $span ) * $bins ) ) ];
		}
		$peak = max( array_merge( array( 1 ), $counts ) );
		$step = 650 / $bins;
		$body = '<line x1="70" y1="350" x2="730" y2="350" class="ntc-svg-grid"/>';
		foreach ( $counts as $i => $count ) {
			$height = 280 * ( $count / $peak );
			$body .= '<rect x="' . round( 75 + $i * $step, 2 ) . '" y="' . round( 350 - $height, 2 ) . '" width="' . round( $step - 2, 2 ) . '" height="' . round( $height, 2 ) . '" fill="var(--ntc-primary)"/><text x="' . round( 75 + ( $i + .5 ) * $step, 2 ) . '" y="372" text-anchor="middle" class="ntc-svg-label">' . esc_html( (string) round( $min + ( $i * $span / $bins ), 1 ) ) . '</text>';
		}
		return self::svg( $body, 'ntc-histogram-svg' );
	}

	private static function chart_boxplot( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 5 );
		if ( count( $values ) < 5 ) {
			return self::empty_note( __( 'Choose five metrics: minimum, Q1, median, Q3 and maximum.', 'native-tables-charts' ) );
		}
		$all = array();
		foreach ( $rows as $row ) {
			foreach ( $values as $column ) {
				$all[] = self::number( $row[ $column ] ?? 0 );
			}
		}
		$min = min( $all ?: array( 0 ) );
		$max = max( $all ?: array( 1 ) );
		$span = max( 1, $max - $min );
		$body = '';
		$step = 320 / max( 1, count( $rows ) );
		foreach ( $rows as $i => $row ) {
			$x = 100 + ( $i * ( 620 / max( 1, count( $rows ) ) ) );
			$coords = array_map( static fn( $column ) => 350 - ( 285 * ( ( self::number( $row[ $column ] ?? 0 ) - $min ) / $span ) ), $values );
			$body .= '<line x1="' . $x . '" y1="' . $coords[0] . '" x2="' . $x . '" y2="' . $coords[4] . '" stroke="var(--ntc-muted)" stroke-width="2"/><rect x="' . ( $x - 22 ) . '" y="' . min( $coords[1], $coords[3] ) . '" width="44" height="' . abs( $coords[3] - $coords[1] ) . '" fill="var(--ntc-primary)" opacity=".72"/><line x1="' . ( $x - 22 ) . '" y1="' . $coords[2] . '" x2="' . ( $x + 22 ) . '" y2="' . $coords[2] . '" stroke="var(--ntc-highlight)" stroke-width="4"/><text x="' . $x . '" y="382" text-anchor="middle" class="ntc-svg-label">' . esc_html( self::truncate( self::label( $row, $config ), 10 ) ) . '</text>';
		}
		return self::svg( $body, 'ntc-boxplot-svg' );
	}

	private static function chart_waterfall( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		$total = 0.0;
		$max_abs = max( 1.0, array_sum( array_map( 'abs', self::numbers( $rows, $column ) ) ) );
		$step = 650 / max( 1, count( $rows ) );
		$body = '<line x1="70" y1="210" x2="730" y2="210" class="ntc-svg-grid"/>';
		foreach ( $rows as $i => $row ) {
			$value = self::number( $row[ $column ] ?? 0 );
			$before = $total;
			$total += $value;
			$y1 = 210 - ( $before / $max_abs * 135 );
			$y2 = 210 - ( $total / $max_abs * 135 );
			$body .= '<rect x="' . ( 78 + $i * $step ) . '" y="' . min( $y1, $y2 ) . '" width="' . max( 10, $step * .62 ) . '" height="' . max( 2, abs( $y2 - $y1 ) ) . '" fill="' . ( $value < 0 ? 'var(--ntc-negative,#dc2626)' : 'var(--ntc-positive,#16a34a)' ) . '"/><text x="' . ( 78 + $i * $step + $step * .31 ) . '" y="370" text-anchor="middle" class="ntc-svg-label">' . esc_html( self::truncate( self::label( $row, $config ), 11 ) ) . '</text>';
		}
		return self::svg( $body, 'ntc-waterfall-svg' );
	}

	private static function chart_bullet( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 3 );
		if ( count( $values ) < 2 ) {
			return self::empty_note( __( 'Choose actual and target metrics.', 'native-tables-charts' ) );
		}
		$out = '<div class="ntc-bullets">';
		foreach ( $rows as $row ) {
			$actual = self::number( $row[ $values[0] ] ?? 0 );
			$target = self::number( $row[ $values[1] ] ?? 0 );
			$max = isset( $values[2] ) ? self::number( $row[ $values[2] ] ?? 0 ) : max( $actual, $target ) * 1.2;
			$max = max( 1, $max );
			$out .= '<div class="ntc-bullet-row"><span>' . esc_html( self::label( $row, $config ) ) . '</span><div class="ntc-bullet-track"><i style="width:' . min( 100, 100 * $actual / $max ) . '%"></i><b style="left:' . min( 100, 100 * $target / $max ) . '%"></b></div><strong>' . esc_html( (string) $actual ) . '</strong></div>';
		}
		return $out . '</div>';
	}

	private static function chart_bubble( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 3 );
		if ( count( $values ) < 3 ) {
			return self::empty_note( __( 'Choose X, Y and bubble-size metrics.', 'native-tables-charts' ) );
		}
		$x_values = self::numbers( $rows, $values[0] );
		$y_values = self::numbers( $rows, $values[1] );
		$s_values = self::numbers( $rows, $values[2] );
		$xmax = max( array_merge( array( 1 ), $x_values ) );
		$ymax = max( array_merge( array( 1 ), $y_values ) );
		$smax = max( array_merge( array( 1 ), $s_values ) );
		$body = '<line x1="65" y1="350" x2="730" y2="350" class="ntc-svg-grid"/><line x1="65" y1="45" x2="65" y2="350" class="ntc-svg-grid"/>';
		foreach ( $rows as $i => $row ) {
			$x = 70 + 640 * $x_values[ $i ] / $xmax;
			$y = 345 - 290 * $y_values[ $i ] / $ymax;
			$r = 7 + 24 * sqrt( abs( $s_values[ $i ] ) / $smax );
			$body .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="' . self::color( $i ) . '" opacity=".7"><title>' . esc_html( self::label( $row, $config ) ) . '</title></circle>';
		}
		return self::svg( $body, 'ntc-bubble-svg' );
	}

	private static function chart_funnel( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		$values = self::numbers( $rows, $column );
		$max = max( array_merge( array( 1 ), $values ) );
		$out = '<div class="ntc-funnel">';
		foreach ( $rows as $i => $row ) {
			$out .= '<div class="ntc-funnel-stage" style="width:' . max( 18, 100 * $values[ $i ] / $max ) . '%;background:' . self::color( $i ) . '"><span>' . esc_html( self::label( $row, $config ) ) . '</span><strong>' . esc_html( (string) $values[ $i ] ) . '</strong></div>';
		}
		return $out . '</div>';
	}

	private static function chart_range_bar( array $rows, array $columns, array $config ): string {
		return self::range_chart( $rows, $config, false );
	}

	private static function chart_timeline( array $rows, array $columns, array $config ): string {
		return self::range_chart( $rows, $config, true );
	}

	private static function range_chart( array $rows, array $config, bool $dates ): string {
		$values = self::value_columns( $config, 2 );
		if ( count( $values ) < 2 ) {
			return self::empty_note( __( 'Choose start and end metrics.', 'native-tables-charts' ) );
		}
		$prepared = array();
		foreach ( $rows as $row ) {
			$start = $dates ? strtotime( (string) ( $row[ $values[0] ] ?? '' ) ) : self::number( $row[ $values[0] ] ?? 0 );
			$end = $dates ? strtotime( (string) ( $row[ $values[1] ] ?? '' ) ) : self::number( $row[ $values[1] ] ?? 0 );
			if ( false !== $start && false !== $end ) {
				$prepared[] = array( self::label( $row, $config ), (float) $start, (float) $end );
			}
		}
		$min = min( array_column( $prepared ?: array( array( '', 0, 1 ) ), 1 ) );
		$max = max( array_column( $prepared ?: array( array( '', 0, 1 ) ), 2 ) );
		$span = max( 1, $max - $min );
		$out = '<div class="ntc-range-chart">';
		foreach ( $prepared as $i => $item ) {
			$out .= '<div class="ntc-range-row"><span>' . esc_html( $item[0] ) . '</span><div><i style="left:' . ( 100 * ( $item[1] - $min ) / $span ) . '%;width:' . max( 1, 100 * ( $item[2] - $item[1] ) / $span ) . '%;background:' . self::color( $i ) . '"></i></div></div>';
		}
		return $out . '</div>';
	}

	private static function chart_slope( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 2 );
		if ( count( $values ) < 2 ) {
			return self::empty_note( __( 'Choose starting and ending metrics.', 'native-tables-charts' ) );
		}
		$all = array_merge( self::numbers( $rows, $values[0] ), self::numbers( $rows, $values[1] ) );
		$min = min( $all ?: array( 0 ) );
		$max = max( $all ?: array( 1 ) );
		$span = max( 1, $max - $min );
		$body = '';
		foreach ( $rows as $i => $row ) {
			$y1 = 345 - 280 * ( self::number( $row[ $values[0] ] ?? 0 ) - $min ) / $span;
			$y2 = 345 - 280 * ( self::number( $row[ $values[1] ] ?? 0 ) - $min ) / $span;
			$color = self::color( $i );
			$body .= '<line x1="170" y1="' . $y1 . '" x2="590" y2="' . $y2 . '" stroke="' . $color . '" stroke-width="3"/><circle cx="170" cy="' . $y1 . '" r="5" fill="' . $color . '"/><circle cx="590" cy="' . $y2 . '" r="5" fill="' . $color . '"/><text x="155" y="' . ( $y1 + 4 ) . '" text-anchor="end" class="ntc-svg-label">' . esc_html( self::truncate( self::label( $row, $config ), 14 ) ) . '</text>';
		}
		return self::svg( $body, 'ntc-slope-svg' );
	}

	private static function chart_treemap( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		$values = self::numbers( $rows, $column );
		$total = max( 1, array_sum( array_map( 'abs', $values ) ) );
		$x = 20.0;
		$body = '';
		foreach ( $rows as $i => $row ) {
			$width = 720 * abs( $values[ $i ] ) / $total;
			$body .= '<rect x="' . $x . '" y="35" width="' . max( 2, $width ) . '" height="320" fill="' . self::color( $i ) . '" opacity=".82"/><text x="' . ( $x + 8 ) . '" y="60" class="ntc-treemap-label">' . esc_html( self::truncate( self::label( $row, $config ), max( 4, (int) ( $width / 9 ) ) ) ) . '</text>';
			$x += $width;
		}
		return self::svg( $body, 'ntc-treemap-svg' );
	}

	private static function chart_sunburst( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		$values = self::numbers( $rows, $column );
		$total = max( 1, array_sum( array_map( 'abs', $values ) ) );
		$offset = 0.0;
		$body = '<circle cx="380" cy="200" r="38" fill="var(--ntc-chart-bg)"/>';
		$groups = array();
		foreach ( $rows as $i => $row ) {
			$parts = explode( '/', self::label( $row, $config ), 2 );
			$groups[ $parts[0] ] = ( $groups[ $parts[0] ] ?? 0 ) + abs( $values[ $i ] );
		}
		$group_offset = 0.0;
		foreach ( array_values( $groups ) as $i => $group_value ) {
			$length = 376.99 * $group_value / $total;
			$body .= '<circle cx="380" cy="200" r="60" fill="none" stroke="' . self::color( $i ) . '" stroke-width="38" stroke-dasharray="' . $length . ' ' . ( 376.99 - $length ) . '" stroke-dashoffset="-' . $group_offset . '" transform="rotate(-90 380 200)"/>';
			$group_offset += $length;
		}
		foreach ( $rows as $i => $row ) {
			$length = 628.32 * abs( $values[ $i ] ) / $total;
			$body .= '<circle cx="380" cy="200" r="100" fill="none" stroke="' . self::color( $i ) . '" stroke-width="34" stroke-dasharray="' . $length . ' ' . ( 628.32 - $length ) . '" stroke-dashoffset="-' . $offset . '" transform="rotate(-90 380 200)"><title>' . esc_html( self::label( $row, $config ) ) . '</title></circle>';
			$offset += $length;
		}
		return self::svg( $body, 'ntc-sunburst-svg' );
	}

	private static function chart_sankey( array $rows, array $columns, array $config ): string {
		$target_column = null !== ( $config['xColumn'] ?? null ) ? absint( $config['xColumn'] ) : ( self::value_columns( $config, 2 )[0] ?? 1 );
		$weight_column = self::value_columns( $config, 2 )[1] ?? self::value_columns( $config, 1 )[0];
		$sources = array_values( array_unique( array_map( static fn( $row ) => self::label( $row, $config ), $rows ) ) );
		$targets = array_values( array_unique( array_map( static fn( $row ) => (string) ( $row[ $target_column ] ?? '' ), $rows ) ) );
		$body = '';
		foreach ( $sources as $i => $source ) {
			$y = 45 + $i * ( 320 / max( 1, count( $sources ) ) );
			$body .= '<rect x="40" y="' . $y . '" width="16" height="22" fill="var(--ntc-primary)"/><text x="64" y="' . ( $y + 16 ) . '" class="ntc-svg-label">' . esc_html( self::truncate( $source, 16 ) ) . '</text>';
		}
		foreach ( $targets as $i => $target ) {
			$y = 45 + $i * ( 320 / max( 1, count( $targets ) ) );
			$body .= '<rect x="704" y="' . $y . '" width="16" height="22" fill="var(--ntc-secondary)"/><text x="696" y="' . ( $y + 16 ) . '" text-anchor="end" class="ntc-svg-label">' . esc_html( self::truncate( $target, 16 ) ) . '</text>';
		}
		foreach ( $rows as $row ) {
			$si = array_search( self::label( $row, $config ), $sources, true );
			$ti = array_search( (string) ( $row[ $target_column ] ?? '' ), $targets, true );
			$sy = 56 + $si * ( 320 / max( 1, count( $sources ) ) );
			$ty = 56 + $ti * ( 320 / max( 1, count( $targets ) ) );
			$weight = max( 2, min( 22, abs( self::number( $row[ $weight_column ] ?? 1 ) ) ) );
			$body .= '<path d="M56,' . $sy . ' C270,' . $sy . ' 490,' . $ty . ' 704,' . $ty . '" fill="none" stroke="var(--ntc-primary)" stroke-width="' . $weight . '" opacity=".25"/>';
		}
		return self::svg( $body, 'ntc-sankey-svg' );
	}

	private static function chart_candlestick( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 4 );
		if ( count( $values ) < 4 ) {
			return self::empty_note( __( 'Choose open, high, low and close metrics.', 'native-tables-charts' ) );
		}
		$all = array();
		foreach ( $values as $column ) {
			$all = array_merge( $all, self::numbers( $rows, $column ) );
		}
		$min = min( $all ?: array( 0 ) );
		$max = max( $all ?: array( 1 ) );
		$span = max( 1, $max - $min );
		$step = 650 / max( 1, count( $rows ) );
		$body = '';
		foreach ( $rows as $i => $row ) {
			$x = 85 + $i * $step;
			$v = array_map( static fn( $column ) => self::number( $row[ $column ] ?? 0 ), $values );
			$y = array_map( static fn( $value ) => 350 - 285 * ( $value - $min ) / $span, $v );
			$up = $v[3] >= $v[0];
			$body .= '<line x1="' . $x . '" y1="' . $y[1] . '" x2="' . $x . '" y2="' . $y[2] . '" stroke="var(--ntc-chart-text)"/><rect x="' . ( $x - 10 ) . '" y="' . min( $y[0], $y[3] ) . '" width="20" height="' . max( 2, abs( $y[3] - $y[0] ) ) . '" fill="' . ( $up ? 'var(--ntc-positive,#16a34a)' : 'var(--ntc-negative,#dc2626)' ) . '"/>';
		}
		return self::svg( $body, 'ntc-candlestick-svg' );
	}

	private static function chart_error_bar( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 3 );
		if ( count( $values ) < 3 ) {
			return self::empty_note( __( 'Choose value, lower-bound and upper-bound metrics.', 'native-tables-charts' ) );
		}
		$all = array_merge( self::numbers( $rows, $values[1] ), self::numbers( $rows, $values[2] ) );
		$min = min( $all ?: array( 0 ) );
		$max = max( $all ?: array( 1 ) );
		$span = max( 1, $max - $min );
		$step = 650 / max( 1, count( $rows ) );
		$body = '';
		foreach ( $rows as $i => $row ) {
			$x = 80 + ( $i + .5 ) * $step;
			$center = 350 - 285 * ( self::number( $row[ $values[0] ] ?? 0 ) - $min ) / $span;
			$low = 350 - 285 * ( self::number( $row[ $values[1] ] ?? 0 ) - $min ) / $span;
			$high = 350 - 285 * ( self::number( $row[ $values[2] ] ?? 0 ) - $min ) / $span;
			$body .= '<line x1="' . $x . '" y1="' . $low . '" x2="' . $x . '" y2="' . $high . '" stroke="var(--ntc-primary)" stroke-width="3"/><line x1="' . ( $x - 10 ) . '" y1="' . $low . '" x2="' . ( $x + 10 ) . '" y2="' . $low . '" stroke="var(--ntc-primary)"/><line x1="' . ( $x - 10 ) . '" y1="' . $high . '" x2="' . ( $x + 10 ) . '" y2="' . $high . '" stroke="var(--ntc-primary)"/><circle cx="' . $x . '" cy="' . $center . '" r="5" fill="var(--ntc-highlight)"/>';
		}
		return self::svg( $body, 'ntc-error-bar-svg' );
	}

	private static function chart_calendar_heatmap( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		$values = self::numbers( $rows, $column );
		$max = max( array_merge( array( 1 ), array_map( 'abs', $values ) ) );
		$out = '<div class="ntc-calendar-heatmap">';
		foreach ( $rows as $i => $row ) {
			$opacity = .12 + .88 * abs( $values[ $i ] ) / $max;
			$out .= '<span style="background:color-mix(in srgb,var(--ntc-primary) ' . round( $opacity * 100 ) . '%,transparent)" title="' . esc_attr( self::label( $row, $config ) . ': ' . $values[ $i ] ) . '"><i>' . esc_html( gmdate( 'j', strtotime( self::label( $row, $config ) ) ?: 0 ) ) . '</i></span>';
		}
		return $out . '</div>';
	}

	private static function chart_population_pyramid( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 2 );
		if ( count( $values ) < 2 ) {
			return self::empty_note( __( 'Choose left and right population metrics.', 'native-tables-charts' ) );
		}
		$max = max( array_merge( array( 1 ), self::numbers( $rows, $values[0] ), self::numbers( $rows, $values[1] ) ) );
		$out = '<div class="ntc-pyramid">';
		foreach ( $rows as $row ) {
			$left = self::number( $row[ $values[0] ] ?? 0 );
			$right = self::number( $row[ $values[1] ] ?? 0 );
			$out .= '<div class="ntc-pyramid-row"><i style="width:' . 100 * $left / $max . '%"></i><span>' . esc_html( self::label( $row, $config ) ) . '</span><b style="width:' . 100 * $right / $max . '%"></b></div>';
		}
		return $out . '</div>';
	}

	private static function chart_likert( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 7 );
		if ( count( $values ) < 3 ) {
			return self::empty_note( __( 'Choose at least three response metrics.', 'native-tables-charts' ) );
		}
		$out = '<div class="ntc-likert">';
		foreach ( $rows as $row ) {
			$total = max( 1, array_sum( array_map( static fn( $column ) => abs( self::number( $row[ $column ] ?? 0 ) ), $values ) ) );
			$out .= '<div class="ntc-likert-row"><span>' . esc_html( self::label( $row, $config ) ) . '</span><div>';
			foreach ( $values as $i => $column ) {
				$out .= '<i class="ntc-likert-' . $i . '" style="width:' . 100 * abs( self::number( $row[ $column ] ?? 0 ) ) / $total . '%"></i>';
			}
			$out .= '</div></div>';
		}
		return $out . '</div>';
	}

	private static function chart_pareto( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		usort( $rows, static fn( $a, $b ) => self::number( $b[ $column ] ?? 0 ) <=> self::number( $a[ $column ] ?? 0 ) );
		$values = self::numbers( $rows, $column );
		$total = max( 1, array_sum( $values ) );
		$running = 0.0;
		$config['valueColumns'] = array( $column, $column );
		foreach ( $rows as $i => $row ) {
			$running += $values[ $i ];
			$rows[ $i ][ $column ] = $values[ $i ];
			$rows[ $i ][] = 100 * $running / $total;
		}
		$config['valueColumns'] = array( $column, count( $rows[0] ?? array() ) - 1 );
		return self::chart_combo( $rows, $columns, $config );
	}

	private static function chart_streamgraph( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 6 );
		$count = max( 1, count( $rows ) );
		$step = 650 / max( 1, $count - 1 );
		$totals = array_fill( 0, $count, 0.0 );
		foreach ( $values as $column ) {
			foreach ( self::numbers( $rows, $column ) as $i => $value ) {
				$totals[ $i ] += abs( $value );
			}
		}
		$peak = max( array_merge( array( 1 ), $totals ) );
		$lower = array_fill( 0, $count, 200.0 );
		$body = '';
		foreach ( $values as $si => $column ) {
			$upper_points = array();
			$lower_points = array();
			foreach ( self::numbers( $rows, $column ) as $i => $value ) {
				$height = abs( $value ) / $peak * 250;
				$upper_points[] = array( 55 + $i * $step, $lower[ $i ] - $height / 2 );
				$lower_points[] = array( 55 + $i * $step, $lower[ $i ] + $height / 2 );
				$lower[ $i ] += $height * .34;
			}
			$path = self::line_path( $upper_points );
			foreach ( array_reverse( $lower_points ) as $point ) {
				$path .= ' L' . round( $point[0], 2 ) . ',' . round( $point[1], 2 );
			}
			$path .= ' Z';
			$body .= '<path d="' . esc_attr( $path ) . '" fill="' . self::color( $si ) . '" opacity=".68"/>';
		}
		return self::svg( $body, 'ntc-streamgraph-svg' );
	}

	private static function chart_parallel_coordinates( array $rows, array $columns, array $config ): string {
		$values = self::value_columns( $config, 8 );
		if ( count( $values ) < 3 ) {
			return self::empty_note( __( 'Choose at least three metrics.', 'native-tables-charts' ) );
		}
		$step = 640 / max( 1, count( $values ) - 1 );
		$mins = $maxes = array();
		foreach ( $values as $column ) {
			$nums = self::numbers( $rows, $column );
			$mins[] = min( $nums ?: array( 0 ) );
			$maxes[] = max( $nums ?: array( 1 ) );
		}
		$body = '';
		foreach ( $values as $i => $column ) {
			$x = 60 + $i * $step;
			$body .= '<line x1="' . $x . '" y1="45" x2="' . $x . '" y2="350" class="ntc-svg-grid"/><text x="' . $x . '" y="378" text-anchor="middle" class="ntc-svg-label">' . esc_html( self::truncate( $columns[ $column ]['label'] ?? '', 10 ) ) . '</text>';
		}
		foreach ( $rows as $ri => $row ) {
			$points = array();
			foreach ( $values as $i => $column ) {
				$span = max( 1, $maxes[ $i ] - $mins[ $i ] );
				$points[] = array( 60 + $i * $step, 350 - 300 * ( self::number( $row[ $column ] ?? 0 ) - $mins[ $i ] ) / $span );
			}
			$body .= '<path d="' . esc_attr( self::line_path( $points ) ) . '" fill="none" stroke="' . self::color( $ri ) . '" stroke-width="2" opacity=".7"><title>' . esc_html( self::label( $row, $config ) ) . '</title></path>';
		}
		return self::svg( $body, 'ntc-parallel-svg' );
	}

	private static function chart_network( array $rows, array $columns, array $config ): string {
		$target_column = null !== ( $config['xColumn'] ?? null ) ? absint( $config['xColumn'] ) : ( self::value_columns( $config, 1 )[0] ?? 1 );
		$nodes = array();
		foreach ( $rows as $row ) {
			$nodes[] = self::label( $row, $config );
			$nodes[] = (string) ( $row[ $target_column ] ?? '' );
		}
		$nodes = array_values( array_filter( array_unique( $nodes ) ) );
		$positions = array();
		foreach ( $nodes as $i => $node ) {
			$angle = 2 * M_PI * $i / max( 1, count( $nodes ) ) - M_PI / 2;
			$positions[ $node ] = array( 380 + cos( $angle ) * 145, 200 + sin( $angle ) * 145 );
		}
		$body = '';
		foreach ( $rows as $row ) {
			$source = self::label( $row, $config );
			$target = (string) ( $row[ $target_column ] ?? '' );
			if ( isset( $positions[ $source ], $positions[ $target ] ) ) {
				$body .= '<line x1="' . $positions[ $source ][0] . '" y1="' . $positions[ $source ][1] . '" x2="' . $positions[ $target ][0] . '" y2="' . $positions[ $target ][1] . '" stroke="var(--ntc-grid)" stroke-width="2"/>';
			}
		}
		foreach ( $nodes as $i => $node ) {
			$body .= '<circle cx="' . $positions[ $node ][0] . '" cy="' . $positions[ $node ][1] . '" r="12" fill="' . self::color( $i ) . '"/><text x="' . $positions[ $node ][0] . '" y="' . ( $positions[ $node ][1] + 28 ) . '" text-anchor="middle" class="ntc-svg-label">' . esc_html( self::truncate( $node, 13 ) ) . '</text>';
		}
		return self::svg( $body, 'ntc-network-svg' );
	}

	private static function chart_choropleth( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		$values = self::numbers( $rows, $column );
		$max = max( array_merge( array( 1 ), array_map( 'abs', $values ) ) );
		$out = '<div class="ntc-tile-map" role="img" aria-label="' . esc_attr__( 'Region choropleth', 'native-tables-charts' ) . '">';
		foreach ( $rows as $i => $row ) {
			$opacity = .16 + .84 * abs( $values[ $i ] ) / $max;
			$out .= '<div style="background:color-mix(in srgb,var(--ntc-primary) ' . round( 100 * $opacity ) . '%,var(--ntc-chart-bg))"><strong>' . esc_html( self::truncate( self::label( $row, $config ), 12 ) ) . '</strong><span>' . esc_html( (string) $values[ $i ] ) . '</span></div>';
		}
		return $out . '</div>';
	}

	private static function chart_polar_area( array $rows, array $columns, array $config ): string {
		$column = self::value_columns( $config, 1 )[0];
		$values = self::numbers( $rows, $column );
		$max = max( array_merge( array( 1 ), array_map( 'abs', $values ) ) );
		$count = max( 1, count( $rows ) );
		$body = '';
		foreach ( $rows as $i => $row ) {
			$start = 2 * M_PI * $i / $count - M_PI / 2;
			$end = 2 * M_PI * ( $i + .92 ) / $count - M_PI / 2;
			$r = 40 + 130 * abs( $values[ $i ] ) / $max;
			$x1 = 380 + cos( $start ) * $r;
			$y1 = 200 + sin( $start ) * $r;
			$x2 = 380 + cos( $end ) * $r;
			$y2 = 200 + sin( $end ) * $r;
			$large = ( $end - $start ) > M_PI ? 1 : 0;
			$body .= '<path d="M380,200 L' . $x1 . ',' . $y1 . ' A' . $r . ',' . $r . ' 0 ' . $large . ' 1 ' . $x2 . ',' . $y2 . ' Z" fill="' . self::color( $i ) . '" opacity=".78"><title>' . esc_html( self::label( $row, $config ) ) . ': ' . esc_html( (string) $values[ $i ] ) . '</title></path>';
		}
		return self::svg( $body, 'ntc-polar-area-svg' );
	}

	private static function truncate( string $text, int $length ): string {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $length ) {
			return mb_substr( $text, 0, max( 1, $length - 1 ) ) . '…';
		}
		return strlen( $text ) > $length ? substr( $text, 0, max( 1, $length - 1 ) ) . '…' : $text;
	}
}
