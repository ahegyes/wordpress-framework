<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

trait CreatesSitesTrait {
	protected function make_site( string $slug ): int {
		$existing = \get_sites(
			array(
				'path'   => "/$slug/",
				'fields' => 'ids',
			)
		);
		if ( array() !== $existing ) {
			return (int) $existing[0];
		}

		$site_id = \wp_insert_site(
			array(
				'domain' => \get_network()->domain,
				'path'   => "/$slug/",
			)
		);
		self::assertIsInt( $site_id );

		return $site_id;
	}
}
