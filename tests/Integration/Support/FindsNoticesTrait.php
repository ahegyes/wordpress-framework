<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use DeepWebSolutions\Framework\Core\AdminNotice\NoticeContext;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeStoreInterface;
use DeepWebSolutions\Framework\Core\AdminNotice\StoredNotice;

trait FindsNoticesTrait {
	protected static function find( NoticeStoreInterface $store, string $id, NoticeContext $context ): ?StoredNotice {
		return \array_find( $store->all( $context ), static fn ( StoredNotice $record ): bool => $record->notice->id === $id );
	}
}
