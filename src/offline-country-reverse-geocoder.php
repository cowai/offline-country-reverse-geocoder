<?php

namespace DaveRoss\OfflineCountryReverseGeocoder;

class FileException extends \Exception {
}

;

/**
 * Retrieve an array of country codes & the corresponding coordinates
 * for their borders.
 * @return array
 * @throws FileException
 */
function countries_array() {

	// Memoize the data so its only read once
	static $country_data;

	if ( ! isset( $country_data ) ) {

		$country_data = array();

		$fh = fopen( __DIR__ . '/polygons.properties', 'r' );
		if ( ! $fh ) {
			throw new FileException( 'Could not open the polygons.properties file' );
		}

		while ( ! feof( $fh ) ) {

			$row = fgets( $fh );
			if ( 0 === strlen( $row ) ) {
				continue;
			}

			$row = trim( $row );
			list( $country_code, $data ) = explode( '=', $row, 2 );
			list( $polygon_type, $data ) = explode( ' ', $data, 2 );
			$data = trim( $data );
			$data = trim( $data, '(' );
			$data = trim( $data, ')' );

			if ( 'POLYGON' === strtoupper( $polygon_type ) ) {

				$country_data[] = array( $country_code, $data );

			} else if ( 'MULTIPOLYGON' === strtoupper( $polygon_type ) ) {

				$polygons = explode( ')),((', $data );
				array_walk( $polygons, function ( $polygon ) use ( $country_code, &$country_data ) {
					$country_data[] = array( $country_code, $polygon );
				} );

			}

		}

	}

	return $country_data;

}

/**
 *  The function will return YES if the point x,y is inside the polygon, or
 *  NO if it is not.  If the point is exactly on the edge of the polygon,
 *  then the function may return YES or NO.
 *
 *  Note that division by zero is avoided because the division is protected
 *  by the "if" clause which surrounds it.
 *
 * @param double $targetX
 * @param double $targetY
 * @param string $points_string
 *
 * @see http://alienryderflex.com/polygon/
 */
function pointInPolygon( $targetX, $targetY, $points_string ) {

	$points      = explode( ',', $points_string );
	$polyCorners = count( $points );
	$polyX       = array();
	$polyY       = array();

	foreach ( $points as $point ) {

		list( $pointX, $pointY ) = explode( ' ', $point );
		$pointX = trim($pointX, '()');
		$pointY = trim($pointY, '()');
		/**
		 * Performance optimization:
		 * If the first pair of coordinates are more than 90deg
		 * (1/4 of the Earth's circumference) in any direction,
		 * the answer is "no".
		 */
		if ( $targetY && ( intval( abs( $pointY - $targetY ) ) > 90 ) ) {
			return false;
		}
		if ( $targetX && ( intval( abs( $pointX - $targetX ) ) > 90 ) ) {
			return false;
		}

		$polyX[] = doubleval( $pointX );
		$polyY[] = doubleval( $pointY );

	}

	$j        = $polyCorners - 1;
	$oddNodes = false;

	for ( $i = 0; $i < $polyCorners; $i ++ ) {
		if ( ( $polyY[ $i ] < $targetY && $polyY[ $j ] >= $targetY
		       || $polyY[ $j ] < $targetY && $polyY[ $i ] >= $targetY )
		     && ( $polyX[ $i ] <= $targetX || $polyX[ $j ] <= $targetX )
		) {
			$oddNodes ^= ( $polyX[ $i ] + ( $targetY - $polyY[ $i ] ) / ( $polyY[ $j ] - $polyY[ $i ] ) * ( $polyX[ $j ] - $polyX[ $i ] ) < $targetX );
		}
		$j = $i;
	}

	return $oddNodes;
}

/**
 * Get the country code for a pair of lat/long coordinates
 *
 * @param double $longitude decimal longitude
 * @param double $latitude  decimal latitude
 *
 * @return string country code or empty
 */
function get_country( $longitude, $latitude ) {

	$countries = countries_array();

	foreach ( $countries as $country ) {
		list( $country_code, $country_boundary ) = $country;
		if ( pointInPolygon( $longitude, $latitude, $country_boundary, $country_code ) ) {
			return $country_code;
		}
	}

	return '';

}
