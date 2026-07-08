<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\UnknownNoticeStoreException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use DeepWebSolutions\Framework\Utilities\Exceptions\InvalidGlobalNamePrefixException;
use DeepWebSolutions\Framework\Storage\MemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNoticesService::class )]
#[UsesClass( NoticeStore::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
#[UsesClass( MemoryStore::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Utilities\AdminNotices\is_valid_notice_id' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_global_name_prefix' )]
final class AdminNoticesServiceTest extends TestCase {
	public function test_constructs_with_a_default_memory_store(): void {
		$service = new AdminNoticesService();

		self::assertArrayHasKey( 'memory', $service->stores );
		self::assertInstanceOf( NoticeStore::class, $service->stores['memory'] );
	}

	public function test_empty_stores_array_registers_no_stores(): void {
		$service = new AdminNoticesService( array() );

		self::assertSame( array(), $service->stores );
	}

	public function test_add_notice_routes_to_the_default_store(): void {
		$service = new AdminNoticesService();
		$notice  = new AdminNotice( 'x', 'msg' );

		$service->add_notice( $notice );

		self::assertEquals( $notice, $service->stores['memory']->get( 'x' ) );
	}

	public function test_add_notice_routes_to_a_named_store(): void {
		$service = new AdminNoticesService(
			array(
				'memory' => new NoticeStore( new MemoryStore() ),
				'extra'  => new NoticeStore( new MemoryStore() ),
			),
		);
		$notice  = new AdminNotice( 'x', 'msg' );

		$service->add_notice( $notice, 'extra' );

		self::assertEquals( $notice, $service->stores['extra']->get( 'x' ) );
		self::assertNull( $service->stores['memory']->get( 'x' ) );
	}

	public function test_add_notice_throws_on_an_unknown_store(): void {
		$service = new AdminNoticesService();

		$this->expectException( UnknownNoticeStoreException::class );

		$service->add_notice( new AdminNotice( 'x', 'msg' ), 'typo-store' );
	}

	public function test_remove_notice_throws_on_an_unknown_named_store(): void {
		$service = new AdminNoticesService();

		$this->expectException( UnknownNoticeStoreException::class );

		$service->remove_notice( 'x', 'typo-store' );
	}

	public function test_remove_notice_from_a_named_store(): void {
		$service = new AdminNoticesService();
		$service->add_notice( new AdminNotice( 'x', 'msg' ) );

		self::assertTrue( $service->remove_notice( 'x', 'memory' ) );
		self::assertFalse( $service->stores['memory']->has( 'x' ) );
	}

	public function test_remove_notice_with_null_store_searches_every_store(): void {
		$service = new AdminNoticesService(
			array(
				'memory' => new NoticeStore( new MemoryStore() ),
				'extra'  => new NoticeStore( new MemoryStore() ),
			),
		);
		$service->add_notice( new AdminNotice( 'x', 'msg' ), 'extra' );

		self::assertTrue( $service->remove_notice( 'x' ) );
		self::assertFalse( $service->stores['extra']->has( 'x' ) );
	}

	public function test_remove_notice_returns_false_when_absent_everywhere(): void {
		$service = new AdminNoticesService();

		self::assertFalse( $service->remove_notice( 'missing' ) );
	}

	public function test_remove_notice_named_targets_only_that_store(): void {
		$service = new AdminNoticesService(
			array(
				'memory' => new NoticeStore( new MemoryStore() ),
				'extra'  => new NoticeStore( new MemoryStore() ),
			),
		);
		$service->add_notice( new AdminNotice( 'x', 'msg' ), 'memory' );
		$service->add_notice( new AdminNotice( 'x', 'msg' ), 'extra' );

		self::assertTrue( $service->remove_notice( 'x', 'memory' ) );
		self::assertFalse( $service->stores['memory']->has( 'x' ) );
		self::assertTrue( $service->stores['extra']->has( 'x' ) );
	}

	public function test_dismiss_action_exposes_the_configured_action(): void {
		$service = new AdminNoticesService( dismiss_action: 'dws_test_dismiss_notice' );

		self::assertSame( 'dws_test_dismiss_notice', $service->dismiss_action );
	}

	public function test_dismiss_action_is_null_when_unconfigured(): void {
		self::assertNull( ( new AdminNoticesService() )->dismiss_action );
	}

	public function test_rejects_a_dismiss_action_outside_the_global_name_charset(): void {
		$this->expectException( InvalidGlobalNamePrefixException::class );

		new AdminNoticesService( dismiss_action: 'Dws Bad Action' );
	}

	public function test_accepts_an_underscore_prefixed_dismiss_action(): void {
		$service = new AdminNoticesService( dismiss_action: '_dws_dismiss' );

		self::assertSame( '_dws_dismiss', $service->dismiss_action );
	}

	public function test_print_dismiss_script_is_noop_without_a_dismiss_action(): void {
		$service = new AdminNoticesService();

		\ob_start();
		$service->print_dismiss_script();

		self::assertSame( '', (string) \ob_get_clean() );
	}

	public function test_print_dismiss_script_is_noop_without_a_tracker(): void {
		// Action set but no tracker means nowhere to record a dismissal, so nothing is printed.
		$service = new AdminNoticesService( dismiss_action: 'dws_test_dismiss_notice' );

		\ob_start();
		$service->print_dismiss_script();

		self::assertSame( '', (string) \ob_get_clean() );
	}
}
