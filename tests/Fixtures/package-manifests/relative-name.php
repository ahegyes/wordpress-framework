<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework;

function make_options_page(): Settings\OptionsPage {
	return new Settings\OptionsPage();
}
