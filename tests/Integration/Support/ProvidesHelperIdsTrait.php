<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

trait ProvidesHelperIdsTrait {
	public static function invalid_helper_ids(): array {
		return array(
			'empty'                   => array( '' ),
			'leading-digit'           => array( '1a' ),
			'uppercase'               => array( 'A' ),
			'trailing-newline'        => array( "a\n" ),
			'underscore-only'         => array( '_' ),
			'two-leading-underscores' => array( '__a' ),
			'leading-hyphen'          => array( '-a' ),
			'space'                   => array( 'a b' ),
			'dot'                     => array( 'a.b' ),
		);
	}

	public static function v1_helper_ids(): array {
		return array(
			'option-key'                      => array( 'dws_lowc_version' ),
			'meta-key'                        => array( '_dws_lo_parent' ),
			'prefix-with-trailing-underscore' => array( '_dws_owwc_' ),
			'hyphenated-prefix'               => array( '_dws-qrwc_' ),
			'slug'                            => array( 'linked-orders-for-woocommerce' ),
		);
	}
}
