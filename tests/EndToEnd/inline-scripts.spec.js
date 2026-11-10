const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Inline scripts', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		// A fresh activation queues the site notice again.
		await requestUtils.deactivatePlugin( 'acme-end-to-end' );
		await requestUtils.activatePlugin( 'acme-end-to-end' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'acme-end-to-end' );
	} );

	test( 'a dismissed notice stays dismissed', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const notice = page.locator( '#acme_e2e-site-notice-welcome' );
		await expect( notice ).toBeVisible();

		const dismissal = page.waitForResponse( ( response ) =>
			decodeURIComponent( response.url() ).includes(
				'/acme-e2e/v1/notices/dismissals'
			)
		);
		await notice
			.getByRole( 'button', { name: 'Dismiss this notice.' } )
			.click();
		expect( ( await dismissal ).status() ).toBe( 200 );

		await page.reload();
		await expect( notice ).toHaveCount( 0 );
	} );

	test( 'a notice queued outside the block editor shows as it opens', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Acme',
			status: 'draft',
		} );
		await admin.editPost( post.id );
		await expect(
			page
				.locator( '.components-notice' )
				.getByRole( 'link', { name: 'the guide' } )
		).toBeVisible();
	} );

	test( 'a post saved in the block editor keeps its object form and shows its delivered notice as markup, read aloud as text', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost( { title: 'Acme' } );
		// The pane's resize handle covers its toggle, so a pointer click lands on the handle.
		await page
			.getByRole( 'button', { name: 'Meta Boxes' } )
			.press( 'Enter' );
		const form = page.locator( '#acme_e2e_details' );
		const nonce = form.locator( '#acme_e2e_details_nonce' );
		await form.getByLabel( 'Note' ).fill( 'Kept' );

		// WordPress asks for fresh meta box nonces five minutes after the editor loads and sends them for a stale heartbeat nonce, so the spec asks at once with one.
		await nonce.evaluate( ( input ) => ( input.value = 'stale' ) );
		await page.evaluate( () => {
			const postId = window.wp.data
				.select( 'core/editor' )
				.getCurrentPostId();
			window.jQuery( document ).on( 'heartbeat-send', ( event, data ) => {
				data[ 'wp-refresh-metabox-loader-nonces' ] = {
					post_id: postId,
				};
			} );
			window.heartbeatSettings.nonce = 'stale';
			window.wp.heartbeat.connectNow();
		} );
		await expect( nonce ).not.toHaveValue( 'stale' );

		// The live regions keep only their latest message, so every message they receive is recorded.
		await page.evaluate( () => {
			window.acmeSpoken = [];
			const observer = new window.MutationObserver( () =>
				document
					.querySelectorAll( '.a11y-speak-region' )
					.forEach( ( region ) => {
						if ( region.textContent ) {
							window.acmeSpoken.push( region.textContent );
						}
					} )
			);
			document
				.querySelectorAll( '.a11y-speak-region' )
				.forEach( ( region ) =>
					observer.observe( region, {
						childList: true,
						characterData: true,
						subtree: true,
					} )
				);
		} );

		await editor.saveDraft();
		await expect(
			page
				.locator( '.components-notice' )
				.getByRole( 'link', { name: 'the guide' } )
		).toBeVisible();
		await expect
			.poll( () => page.evaluate( () => window.acmeSpoken ) )
			.toContain( 'Saved & filed. Read the guide.' );

		await page.reload();
		await page
			.getByRole( 'button', { name: 'Meta Boxes' } )
			.press( 'Enter' );
		await expect(
			page.locator( '#acme_e2e_details' ).getByLabel( 'Note' )
		).toHaveValue( 'Kept' );
	} );
} );
