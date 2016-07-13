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

use Exception;
use SimpleXMLElement;

class Qstat {

	const CMD_QSTAT = "/usr/bin/qstat -xml -j '*' | /bin/sed -e 's/JATASK:[^>]*/jatask/g'";
	const CMD_QHOST = '/usr/bin/qhost -xml -j -F h_vmem';

	/**
	 * Get a list of jobs submitted to the grid.
	 * @return array
	 */
	public function getJobs() {
		$jobs = [];
		$xml = $this->execAndParse( self::CMD_QSTAT );
		foreach ( $xml->djob_info->element as $xjob ) {
			list( $_, $tool ) = explode( '.', (string) $xjob->JB_owner, 2 );
			$job = [
				'num' => (string) $xjob->JB_job_number,
				'name' => (string) $xjob->JB_job_name,
				'submit' => (string) $xjob->JB_submission_time,
				'owner' => (string) $xjob->JB_owner,
				'tool' => $tool,
			];
			if ( $xjob->JB_hard_queue_list ) {
				$job['queue'] = (string) $xjob->JB_hard_queue_list->destin_ident_list->QR_name;
			} else {
				$job['queue'] = '(manual)';
			}
			foreach ( $xjob->JB_hard_resource_list->qstat_l_requests as $lreq ) {
				if ( $lreq->CE_name === 'h_vmem' ) {
					$job['h_vmem'] = (int) $lreq->CE_doubleval;
				}
			}
			if ( $xjob->JB_ja_tasks->jatask &&
				$xjob->JB_ja_tasks->jatask->JAT_scaled_usage_list
			) {
				foreach ( $xjob->JB_ja_tasks->jatask->JAT_scaled_usage_list->scaled as $usage ) {
					$job[(string) $usage->UA_name] = (int) $usage->UA_value;
				}
			}
			if ( $xjob->JB_ja_tasks->ulong_sublist &&
				$xjob->JB_ja_tasks->ulong_sublist->JAT_scaled_usage_list
			) {
				foreach ( $xjob->JB_ja_tasks->ulong_sublist->JAT_scaled_usage_list->scaled as $usage ) {
					$job[(string) $usage->UA_name] = (int) $usage->UA_value;
				}
			}
			$jobs[$job['num']] = $job;
		}
		ksort( $jobs );
		return $jobs;
	}

	/**
	 * Get a list of hosts available on the grid.
	 * @return array
	 */
	public function getHosts() {
		$hosts = [];
		$xml = $this->execAndParse( self::CMD_QHOST );
		foreach ( $xml->host as $xhost ) {
			list( $hname, $_ ) = explode(
				'.', (string) $xhost->attributes()->name, 2 );
			if ( $hname !== 'global' ) {
				$host = [
					'name'   => $hname,
					'h_vmem' => static::mmem( (string) $xhost->resourcevalue ) * 1024 * 1024,
					'jobs'   => [],
				];
				foreach ( $xhost->job as $xjob ) {
					$jid = (int) $xjob->attributes()->name;
					$job = [];
					foreach ( $xjob->jobvalue as $jv ) {
						$job[(string) $jv->attributes()->name] = (string) $jv;
					}
					$rawState = static::safeGet( $job, 'job_state' );
					$jobs[$jid]['state'] = $rawState;
					if ( stristr( $rawState, 'R' ) !== false ) {
						$jobs[$jid]['state'] = 'Running';
					}
					if ( stristr( $rawState, 's' ) !== false ) {
						$jobs[$jid]['state'] = 'Suspended';
					}
					if ( stristr( $rawState, 'd' ) !== false ) {
						$jobs[$jid]['state'] = 'Deleting';
					}
					$jobs[$jid]['host'] = $hname;
					$jobs[$jid]['priority'] = static::safeGet(
						$job, 'priority' );
					$host['jobs'][] = $jid;
				}
				foreach ( $xhost->hostvalue as $hv ) {
					$host[(string) $hv->attributes()->name] = (string) $hv;
				}
				$used = static::mmem( static::safeGet( $host, 'mem_used', '0M' ) );
				$totle = static::mmem( static::safeGet( $host, 'mem_total', '1M' ) );
				$host['mem'] = $used / $total;
				$hosts[$hname] = $host;
			}
		}
		ksort( $hosts );
		return $hosts;
	}

	/**
	 * Execute a command and parse the result as XML.
	 *
	 * @param string $cmd
	 * @return SimpleXMLElement
	 * @throws Exception
	 */
	protected function execAndParse( $cmd ) {
		$out = shell_exec( $cmd );
		libxml_use_internal_errors();
		libxml_clear_errors();
		return new SimpleXMLElement( $out );
	}

	/**
	 * Parse a human readable memory value and return an intger number of
	 * megabytes that it represents.
	 *
	 * @param string $str
	 * @return int
	 */
	protected static function mmem( $str ) {
		$suffix = substr( $str, -1 );
		if ( $suffix === 'M' ) {
			return 0 + $str;
		}
		if ( $suffix === 'G' ) {
			return 1024 * $str;
		}
		return -1;
	}

	/**
	 * Safely get a value from an array.
	 *
	 * @param array $arr
	 * @param mixed $key
	 * @param mixed $default Default value to return if $key is not found
	 * @return mixed
	 */
	protected static function safeGet( array $arr, $key, $default = '' ) {
		return array_key_exists( $key, $arr ) ? $arr[$key] : $default;
	}
}
