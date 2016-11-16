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
use ForceUTF8\Encoding;
use SimpleXMLElement;

/**
 * Get (Oracle|Open)GridEngine job and host status.
 */
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
			$tool = (string) $xjob->JB_owner;
			if ( substr( $tool, 0, 6 ) === 'tools.' ) {
				$tool = substr( $tool, 6 );
			}
			$job = [
				'num' => (int) $xjob->JB_job_number,
				'name' => (string) $xjob->JB_job_name,
				'submit' => (int) $xjob->JB_submission_time,
				'owner' => (string) $xjob->JB_owner,
				'tool' => $tool,
			];
			if ( $xjob->JB_hard_queue_list ) {
				$job['queue'] = (string) $xjob->JB_hard_queue_list->destin_ident_list->QR_name;
			} else {
				$job['queue'] = '(manual)';
			}
			if ( $xjob->JB_hard_resource_list &&
				$xjob->JB_hard_resource_list->qstat_l_requests
			) {
				foreach ( $xjob->JB_hard_resource_list->qstat_l_requests as $lreq ) {
					$name = (string) $lreq->CE_name;
					if ( $name === 'h_vmem' ) {
						$job['h_vmem'] = (int) $lreq->CE_doubleval;
					}
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
			$parts = explode( '.', (string) $xhost->attributes()->name, 2 );
			$hname = $parts[0];
			if ( $hname !== 'global' ) {
				$jobs = [];
				$host = [
					'name' => $hname,
					'h_vmem' => static::mmem( (string) $xhost->resourcevalue ) * 1024 * 1024,
				];
				foreach ( $xhost->hostvalue as $hv ) {
					$name = (string) $hv->attributes()->name;
					$val = (string) $hv;
					if ( is_numeric( $val ) ) {
						$val = $val + 0;
					}
					$host[$name] = $val;
				}
				foreach ( $xhost->job as $xjob ) {
					$jid = (int) $xjob->attributes()->name;
					$job = [];
					foreach ( $xjob->jobvalue as $jv ) {
						$name = (string) $jv->attributes()->name;
						$val = (string) $jv;
						if ( $name === 'priority' ) {
							$val = (float) trim( $val, "'" );
						} elseif ( is_numeric( $val ) ) {
							$val = $val + 0;
						}
						$job[$name] = $val;
					}
					$rawState = static::safeGet( $job, 'job_state' );
					$job['state'] = $rawState;
					if ( stristr( $rawState, 'R' ) !== false ) {
						$job['state'] = 'Running';
					}
					if ( stristr( $rawState, 's' ) !== false ) {
						$job['state'] = 'Suspended';
					}
					if ( stristr( $rawState, 'd' ) !== false ) {
						$job['state'] = 'Deleting';
					}
					$job['host'] = $hname;
					$jobs[$jid] = $job;
				}
				ksort( $jobs );
				$used = static::mmem( static::safeGet( $host, 'mem_used', '0M' ) );
				$total = static::mmem( static::safeGet( $host, 'mem_total', '1M' ) );
				$host['mem'] = $used / $total;
				$host['jobs']  = $jobs;
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
		$out = Encoding::toUTF8( $out );
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
