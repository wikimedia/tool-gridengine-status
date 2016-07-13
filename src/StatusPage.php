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

use Wikimedia\Slimapp\Controller;

class StatusPage extends Controller {
	/**
	 * @var Qstat
	 */
	protected $qstat;

	public function setQstat( $qstat ) {
		$this->qstat = $qstat;
	}

	protected function handleGet() {
		$qhosts = $this->qstat->getHosts();
		$qjobs = $this->qstat->getJobs();

		$hosts = [];
		foreach ( $qhosts as $name => $host ) {
			$freeVmem = self::safeGet( $host, 'h_vmem', 0 );
			foreach ( $host['jobs'] as $job ) {
				$freeVmem -= self::safeGet( $jobs[$job], 'h_vmem', 0 );
			}
			if ( $freeVmem < 0 ) {
				$freeVmem = 0;
			}
			$loadAvg = self::safeGet( $host, 'load_avg', 0 );
			$procs = self::safeGet( $host, 'num_proc', 1 );

			$hosts[$name] = [
				'load' => (int) ( ( $loadAvg * 100 ) / $procs ),
				'mem' => (int) ( self::safeGet( $host, 'mem', 0 ) * 100 ),
				'vmem' => (int) ( $freeVmem / 1024 / 1024 ),
				'jobs' => [],
			];
		}

		foreach ( $qjobs as $jobid => $job ) {
			if ( array_key_exists( 'host', $job ) ) {
				$hosts[$job['host']]['jobs'][$jobid] = $job;
			}
		}

		$this->view->set( 'hosts', $hosts );
		$this->render( 'status.html' );
	}

	protected static function safeGet( array $arr, $key, $default = '' ) {
		return array_key_exists( $key, $arr ) ? $arr[$key] : $default;
	}
}