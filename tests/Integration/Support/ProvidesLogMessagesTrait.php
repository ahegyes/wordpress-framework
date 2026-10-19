<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

trait ProvidesLogMessagesTrait {
	public static function log_messages(): array {
		$stringable  = new class() implements \Stringable {
			public function __toString(): string {
				return 'stringable';
			}
		};
		$unprintable = new class() implements \Stringable {
			public function __toString(): string {
				throw new \LogicException( 'Not printable.' );
			}
		};
		$erroring    = new class() implements \Stringable {
			public function __toString(): string {
				throw new \TypeError( 'Not a string.' );
			}
		};

		return array(
			'string'       => array( 'Hello {name}.', array( 'name' => 'world' ), 'Hello world.' ),
			'integer'      => array( '{count} items', array( 'count' => 3 ), '3 items' ),
			'float'        => array( '{ratio} ratio', array( 'ratio' => 1.5 ), '1.5 ratio' ),
			'boolean'      => array( 'flag {flag}', array( 'flag' => true ), 'flag 1' ),
			'null'         => array( 'value [{value}]', array( 'value' => null ), 'value []' ),
			'stringable'   => array( 'from {object}', array( 'object' => $stringable ), 'from stringable' ),
			'array'        => array( 'data {data}', array( 'data' => array( 1 ) ), 'data {data}' ),
			'plain-object' => array( 'data {data}', array( 'data' => new \stdClass() ), 'data {data}' ),
			'missing-key'  => array( 'Hello {name}.', array(), 'Hello {name}.' ),
			'unprintable'  => array( 'from {object}', array( 'object' => $unprintable ), 'from {object}' ),
			'type-error'   => array( 'from {object}', array( 'object' => $erroring ), 'from {object}' ),
		);
	}
}
