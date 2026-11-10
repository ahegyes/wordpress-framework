<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

trait RecordsDoingItWrongTrait {
	protected array $doing_it_wrong = array();

	// Records each _doing_it_wrong() call's function name in place of its notice; the test's IsolatesHooksTrait removes the recorder.
	protected function record_doing_it_wrong(): void {
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		add_action(
			'doing_it_wrong_run',
			function ( string $function_name ): void {
				$this->doing_it_wrong[] = $function_name;
			}
		);
	}
}
