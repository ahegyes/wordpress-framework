<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared;

function read_site_url(): mixed {
	return \get_option( 'siteurl' );
}
