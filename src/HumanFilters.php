<?php
/**
 * This file is part of GridEngine-Status
 * Copyright (C) 2016  Wikimedia Foundation and contributors
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tools\GridEngineStatus;

use Twig_Extension;
use Twig_SimpleFilter;

/**
 * Twig filters to format numbers for humans.
 */
class HumanFilters extends Twig_Extension {

	public function getName() {
		return 'humanfilters';
	}

	public function getFilters() {
		return [
			new Twig_SimpleFilter(
				'humanmem', [ $this, 'humanmemFilterCallback' ]
			),
			new Twig_SimpleFilter(
				'humantime', [ $this, 'humantimeFilterCallback' ]
			),
		];
	}

	public function humanmemFilterCallback( $megs ) {
		if ( $megs > 1024 )  {
			$megs = (int) ( $megs / 102.4 );
			$megs /= 10.0;
			return "{$megs}G";
		}
		$megs = (int) ( $megs * 10 );
		$megs /= 10.0;
		return "{$megs}M";
	}

	public function humantimeFilterCallback( $secs ) {
		$parts = [];
		if ( $secs > 86400 ) {
			$days = (int) ( $secs / 86400 );
			$secs = $secs % 86400;
			$parts[] = "{$days}d";
		}
		if ( $secs > 3600 ) {
			$hours = (int) ( $secs / 3600 );
			$secs = $secs % 3600;
			$parts[] = "{$hours}h";
		} elseif ( $parts ) {
			$parts[] = '0h';
		}
		if ( $secs > 60 ) {
			$mins = (int) ( $secs / 60 );
			$secs = $secs % 60;
			$parts[] = "{$mins}m";
		} elseif ( $parts ) {
			$parts[] = '0m';
		}
		$parts[] = "{$secs}s";
		return implode( '', $parts );
	}
}
