<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFORGE_Logger {

	const MAX = 300;

	public static function log( $msg, $level = 'info' ) {
		$log = get_option( SFORGE_LOG_OPT, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}
		array_unshift( $log, [
			'time'  => current_time( 'mysql' ),
			'level' => $level,
			'msg'   => $msg,
		] );
		$log = array_slice( $log, 0, self::MAX );
		update_option( SFORGE_LOG_OPT, $log, false );
	}

	public static function get() {
		$log = get_option( SFORGE_LOG_OPT, [] );
		return is_array( $log ) ? $log : [];
	}

	public static function clear() {
		delete_option( SFORGE_LOG_OPT );
	}
}
