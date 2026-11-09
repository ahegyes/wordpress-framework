<?php declare( strict_types=1 );
/**
 * Plugin Name: Acme End-to-End
 *
 * Shows a dismissible site notice, adds an object form to posts and queues a one-shot notice with a link whenever a post saves other than as an auto-draft, so the end-to-end spec runs the framework's inline scripts in a browser.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeType;
use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\Framework\Settings\EditScreen\Hosts\PostMetaBox;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;

// wp-env maps the repository to wp-content/mu-plugins/wp-framework and this directory into the plugins directory.
require_once WPMU_PLUGIN_DIR . '/wp-framework/vendor/autoload.php';

$acme_notices = new AdminNotices( 'acme_e2e', new ErrorLogLogger( 'acme-end-to-end' ) );

register_activation_hook(
	__FILE__,
	static function () use ( $acme_notices ): void {
		$acme_notices->add( new Notice( 'welcome', 'Welcome to the end-to-end site.', NoticeType::Info, NoticeScope::Site, persistent: true, capability: 'manage_options' ) );
	}
);

register_deactivation_hook(
	__FILE__,
	static function () use ( $acme_notices ): void {
		$acme_notices->remove( 'welcome', NoticeScope::Site );
	}
);

add_action(
	'plugins_loaded',
	static function () use ( $acme_notices ): void {
		$acme_notices->register_hooks();

		$definition = new class() implements ObjectFormDefinitionInterface {
			#[\Override]
			public function get_object_form( string $form_id, int $object_id ): array {
				return array(
					'title'      => 'Acme details',
					'capability' => 'edit_posts',
					'fields'     => array(
						array(
							'id'    => 'acme_e2e_note',
							'type'  => 'text',
							'title' => 'Note',
						),
					),
				);
			}
		};
		new ObjectForm( 'acme_e2e_details', $definition, new PostMetaBox( array( 'post' ) ), $acme_notices )->register_hooks();
	}
);

add_action(
	'save_post_post',
	static function ( int $post_id, WP_Post $post ) use ( $acme_notices ): void {
		// Opening a new post saves an auto-draft, whose notice the editor would deliver as it loads.
		if ( 'auto-draft' !== $post->post_status ) {
			$acme_notices->add( new Notice( 'saved', 'Saved. Read <a href="https://example.org/guide">the guide</a>.', NoticeType::Success ) );
		}
	},
	10,
	2
);
