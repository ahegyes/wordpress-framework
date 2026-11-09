const { defineConfig } = require( '@playwright/test' );
// The port of .wp-env.tests.json, so the suite runs on the production tests environment and leaves the development one's data alone.
const baseConfig =
	require( '@ahegyes/wordpress-configs/node/playwright.config.base.js' )( {
		port: 8801,
	} );

module.exports = defineConfig( {
	...baseConfig,
	webServer: {
		...baseConfig.webServer,
		command: 'npm run wp-env:tests:start',
	},
} );
