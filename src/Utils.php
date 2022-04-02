<?php

namespace Tools\GridEngineStatus;

class Utils {
	private const RE_NUMBER = '/(\d+)/';

	private static function splitForNaturalSort( string $input ): array {
		return preg_split( self::RE_NUMBER, $input, -1, PREG_SPLIT_DELIM_CAPTURE );
	}

	public static function naturalSort( string $first, string $second ): int {
		$firstSplit = self::splitForNaturalSort( $first );
		$secondSplit = self::splitForNaturalSort( $second );

		while ( true ) {
			if ( empty( $firstSplit ) && empty( $secondSplit ) ) {
				return 0;
			} elseif ( empty( $firstSplit ) ) {
				return -1;
			} elseif ( empty( $secondSplit ) ) {
				return 1;
			}

			$firstElem = array_shift( $firstSplit );
			$secondElem = array_shift( $secondSplit );

			if ( is_numeric( $firstElem ) && is_numeric( $secondElem ) ) {
				$result = (int)$firstElem - (int)$secondElem;
			} else {
				$result = strcmp( $firstElem, $secondElem );
			}

			if ( $result !== 0 ) {
				return $result;
			}
		}
	}
}
