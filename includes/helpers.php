<?php

namespace COURTIQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helpers {

	public static function get_frontend_config(): array {
		$dict = [];
		foreach ( ['en', 'ar'] as $lang ) {
			$f = CTQ_CACHE_DIR . '/dict-' . $lang . '.json';
			$dict[$lang] = file_exists( $f ) ? ( json_decode( file_get_contents( $f ), true ) ?: [] ) : [];
		}

		return [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'ctq_nonce' ),
			'dict'      => $dict,
			'version'   => CTQ_VERSION,
			'batchSize' => CTQ_BATCH_LIMIT,
			'pageLimit' => CTQ_PAGE_LIMIT,
			'hasKey'    => ( CTQ_GEMINI_KEY !== '__NOT_SET__' && strlen( CTQ_GEMINI_KEY ) > 20 ),
		];
	}

	public static function load_cache( string $file ): array {
		if ( ! file_exists( $file ) ) {
			return [];
		}
		return json_decode( file_get_contents( $file ), true ) ?: [];
	}

	public static function save_cache( string $file, array $data ): bool {
		return file_put_contents(
			$file,
			json_encode(
				$data,
				JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			)
		) !== false;
	}

	public static function nocache_headers(): void {
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'Expires: Thu, 01 Jan 1970 00:00:00 GMT' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'X-Accel-Expires: 0' );
	}

	public static function log( string $msg ): void {
		error_log( '[CTQ v' . CTQ_VERSION . '] ' . $msg );
	}

	public static function get_stats(): array {
		$stats = [];
		foreach ( ['en', 'ar'] as $lang ) {
			$f = CTQ_CACHE_DIR . '/dict-' . $lang . '.json';
			$count = file_exists( $f ) ? count( json_decode( file_get_contents( $f ), true ) ?? [] ) : 0;
			$size = file_exists( $f ) ? round( filesize( $f ) / 1024, 1 ) . ' Ko' : '—';
			$stats[$lang] = ['count' => $count, 'size' => $size];
		}
		return $stats;
	}
}
