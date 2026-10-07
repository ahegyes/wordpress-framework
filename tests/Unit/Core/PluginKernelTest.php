<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core;

use DeepWebSolutions\Framework\ComponentInterface;
use DeepWebSolutions\Framework\CompositeComponentInterface;
use DeepWebSolutions\Framework\ConditionalComponentInterface;
use DeepWebSolutions\Framework\PluginKernel;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Tests\Fixtures\Core\GatedOutComposite;
use DeepWebSolutions\Framework\Tests\Fixtures\Core\UnloadableComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class Journal {
	public array $entries = array();
}

final class FakeContainer implements ContainerInterface {
	public function __construct(
		protected Journal $journal,
		protected array $definitions = array()
	) {}

	#[\Override]
	public function get( string $id ): mixed {
		return isset( $this->definitions[ $id ] ) ? $this->definitions[ $id ]( $this->journal ) : new $id( $this->journal );
	}

	#[\Override]
	public function has( string $id ): bool {
		return isset( $this->definitions[ $id ] ) || \class_exists( $id );
	}
}

abstract class RecordingComponent implements ComponentInterface {
	public function __construct(
		protected Journal $journal
	) {
		$this->record( 'construct' );
	}

	#[\Override]
	public function register_hooks(): void {
		$this->record( 'register' );
	}

	protected function record( string $event ): void {
		$this->journal->entries[] = "$event " . ( new \ReflectionClass( $this ) )->getShortName();
	}
}

final class CompositeA extends RecordingComponent implements CompositeComponentInterface {
	#[\Override]
	public static function get_child_component_classes(): array {
		return array( ComponentB::class, ComponentC::class );
	}
}

final class ComponentB extends RecordingComponent {}

final class ComponentC extends RecordingComponent {}

final class ComponentD extends RecordingComponent {}

final class CompositeP extends RecordingComponent implements CompositeComponentInterface {
	#[\Override]
	public static function get_child_component_classes(): array {
		return array( LoadingL1::class, GatedOutL2::class, UngatedL3::class );
	}
}

final class LoadingL1 extends RecordingComponent implements ConditionalComponentInterface {
	#[\Override]
	public static function should_load(): bool {
		return true;
	}
}

final class GatedOutL2 extends RecordingComponent implements ConditionalComponentInterface {
	#[\Override]
	public static function should_load(): bool {
		return false;
	}
}

final class UngatedL3 extends RecordingComponent {}

final class ConditionalOnly implements ConditionalComponentInterface {
	#[\Override]
	public static function should_load(): bool {
		throw new \RuntimeException( 'should_load() ran.' );
	}
}

final class CycleStart extends RecordingComponent implements CompositeComponentInterface {
	#[\Override]
	public static function get_child_component_classes(): array {
		return array( CycleEnd::class );
	}
}

final class CycleEnd extends RecordingComponent implements CompositeComponentInterface {
	#[\Override]
	public static function get_child_component_classes(): array {
		return array( CycleStart::class );
	}
}

final class ThrowingComponent extends RecordingComponent {
	public function __construct(
		Journal $journal
	) {
		throw new \RuntimeException( 'Construction failed.' );
	}
}

#[CoversClass( PluginKernel::class )]
final class PluginKernelTest extends TestCase {
	public static function invalid_trees(): array {
		return array(
			'repeated-root'    => array( array( ComponentD::class, ComponentD::class ) ),
			'repeated-child'   => array( array( CompositeA::class, ComponentB::class ) ),
			'gated-out-repeat' => array( array( GatedOutL2::class, GatedOutL2::class ) ),
			'cycle'            => array( array( CycleStart::class ) ),
		);
	}

	public static function failing_constructions(): array {
		return array(
			'definition'  => array( array( ComponentD::class => static fn (): never => throw new \RuntimeException( 'Construction failed.' ) ), ComponentD::class ),
			'constructor' => array( array(), ThrowingComponent::class ),
		);
	}

	public function test_boot_constructs_every_component_depth_first_before_registering_any(): void {
		$journal = new Journal();
		$kernel  = new PluginKernel( new FakeContainer( $journal ) );

		$kernel->boot( array( CompositeA::class, ComponentD::class ) );

		self::assertSame(
			array( 'construct CompositeA', 'construct ComponentB', 'construct ComponentC', 'construct ComponentD', 'register CompositeA', 'register ComponentB', 'register ComponentC', 'register ComponentD' ),
			$journal->entries
		);
		self::assertSame( array( CompositeA::class, ComponentB::class, ComponentC::class, ComponentD::class ), $kernel->resolved );
	}

	public function test_a_gated_out_composite_is_autoloaded_and_skipped_and_its_children_are_never_autoloaded(): void {
		$kernel = new PluginKernel( new FakeContainer( new Journal(), array( GatedOutComposite::class => static fn (): never => throw new \RuntimeException( 'GatedOutComposite was constructed.' ) ) ) );
		self::assertFalse( \class_exists( GatedOutComposite::class, false ) );

		$kernel->boot( array( GatedOutComposite::class ) );

		self::assertTrue( \class_exists( GatedOutComposite::class, false ) );
		self::assertSame( array( GatedOutComposite::class ), $kernel->skipped );
		self::assertFalse( \class_exists( UnloadableComponent::class, false ) );
	}

	public function test_a_gated_out_child_is_skipped_while_its_siblings_load(): void {
		$journal = new Journal();
		$kernel  = new PluginKernel( new FakeContainer( $journal ) );

		$kernel->boot( array( CompositeP::class ) );

		self::assertSame(
			array( 'construct CompositeP', 'construct LoadingL1', 'construct UngatedL3', 'register CompositeP', 'register LoadingL1', 'register UngatedL3' ),
			$journal->entries
		);
		self::assertSame( array( CompositeP::class, LoadingL1::class, UngatedL3::class ), $kernel->resolved );
		self::assertSame( array( GatedOutL2::class ), $kernel->skipped );
	}

	public function test_a_class_that_is_only_conditional_throws_before_its_gate_runs(): void {
		$thrown = self::boot_and_catch( new PluginKernel( new FakeContainer( new Journal() ) ), array( ConditionalOnly::class ) );

		self::assertInstanceOf( LogicException::class, $thrown );
	}

	#[DataProvider( 'invalid_trees' )]
	public function test_a_repeated_class_throws_before_any_component_registers( array $roots ): void {
		$journal = new Journal();

		$thrown = self::boot_and_catch( new PluginKernel( new FakeContainer( $journal ) ), $roots );

		self::assertInstanceOf( LogicException::class, $thrown );
		self::assertSame( array(), \preg_grep( '/^register /', $journal->entries ) );
	}

	#[DataProvider( 'failing_constructions' )]
	public function test_a_failing_construction_propagates_before_any_component_registers( array $definitions, string $failing ): void {
		$journal = new Journal();

		$thrown = self::boot_and_catch( new PluginKernel( new FakeContainer( $journal, $definitions ) ), array( CompositeA::class, $failing ) );

		self::assertInstanceOf( \RuntimeException::class, $thrown );
		self::assertSame( 'Construction failed.', $thrown->getMessage() );
		self::assertSame( array( 'construct CompositeA', 'construct ComponentB', 'construct ComponentC' ), $journal->entries );
	}

	public function test_an_entry_of_another_class_throws_before_any_component_registers(): void {
		$journal = new Journal();
		$kernel  = new PluginKernel( new FakeContainer( $journal, array( ComponentD::class => static fn (): \stdClass => new \stdClass() ) ) );

		$thrown = self::boot_and_catch( $kernel, array( CompositeA::class, ComponentD::class ) );

		self::assertInstanceOf( LogicException::class, $thrown );
		self::assertStringContainsString( "'stdClass'", $thrown->getMessage() );
		self::assertSame( array( 'construct CompositeA', 'construct ComponentB', 'construct ComponentC' ), $journal->entries );
	}

	protected static function boot_and_catch( PluginKernel $kernel, array $roots ): \Throwable {
		try {
			$kernel->boot( $roots );
		} catch ( \Throwable $thrown ) {
			return $thrown;
		}

		self::fail( 'boot() did not throw.' );
	}
}
