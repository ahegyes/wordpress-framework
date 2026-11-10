<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Support;

trait RunsProcessesTrait {
	// Runs the command without a shell, writing the input to its standard input when given, and returns its exit code, standard output and standard error.
	protected static function run_process( array $command, ?string $cwd = null, ?array $env = null, ?string $input = null ): array {
		$descriptors = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		if ( ! \is_null( $input ) ) {
			$descriptors[0] = array( 'pipe', 'r' );
		}

		$process = \proc_open( $command, $descriptors, $pipes, $cwd, $env ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs the command directly, without a shell.
		self::assertIsResource( $process );
		if ( ! \is_null( $input ) ) {
			\fwrite( $pipes[0], $input );
			\fclose( $pipes[0] );
		}

		$output = (string) \stream_get_contents( $pipes[1] );
		$errors = (string) \stream_get_contents( $pipes[2] );
		\fclose( $pipes[1] );
		\fclose( $pipes[2] );

		return array( \proc_close( $process ), $output, $errors );
	}
}
