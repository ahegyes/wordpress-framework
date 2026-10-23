<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework;

function make_options_page(): Settings\AdminPage\OptionsPage {
	return new Settings\AdminPage\OptionsPage();
}
