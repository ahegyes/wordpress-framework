<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\AdminNotice;

use DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;

trait AdminNoticeFixturesTrait {
	protected function notice( string $id, NoticeScope $scope = NoticeScope::User, string $message = '', mixed ...$arguments ): Notice {
		$arguments['scope']        = $scope;
		$arguments['capability'] ??= NoticeScope::Site === $scope || NoticeScope::Network === $scope ? 'manage_options' : null;

		return new Notice( $id, '' === $message ? "Notice $id." : $message, ...$arguments );
	}

	protected function dom_id( string $scope, string $id ): string {
		return "$this->prefix-$scope-notice-$id";
	}

	protected static function ids( array $notices ): array {
		return \array_map( static fn ( Notice $notice ): string => $notice->id, $notices );
	}
}
