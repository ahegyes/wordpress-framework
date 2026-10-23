# wordpress-framework

A WordPress plugin framework published as five Composer packages under `ahegyes/`, PHP namespace `DeepWebSolutions\Framework\`, GPL-2.0-or-later. splitsh splits each `packages/<dir>/` to its read-only `wp-framework-<dir>` mirror on every push to `trunk`; consumers install the mirrors through `vcs` repositories, not Packagist, and prefix them with php-scoper or Strauss. The packages form a stack: core requires bootstrap and shared, settings requires core, and woocommerce requires settings, each requiring only the layer below it, while shared also installs alone; a plugin ships only the closure of the highest package it uses.

## Layout

```
wordpress-framework/
├── packages/bootstrap/    ahegyes/wp-framework-bootstrap    php >=7.4 · no require · no autoload: loaded by path only
│   ├── load.php             require_once's src/Requirements.php and src/Updater.php   (path = public API)
│   ├── uninstall.php        require_once's src/Updater.php and src/Uninstaller.php   (path = public API)
│   └── src/
│       ├── Requirements     ::check( string $plugin_file ): bool · ::print_admin_notice( string $message, array $args = array( 'type' => 'error' ) ): void
│       ├── Updater          ::register( string $plugin_file ): void · ::delete_cached_releases( string $plugin_file ): void (@internal)
│       └── Uninstaller      ::run( string $plugin_file, array $footprint, array $args = array() ): void
├── packages/shared/       ahegyes/wp-framework-shared       php >=8.5 · no package require · WordPress-free
│   └── src/               psr-4 DeepWebSolutions\Framework\Shared\ · Error/, Exception/, Result/, ValueObject/, Versioning/
├── packages/core/         ahegyes/wp-framework-core         php >=8.5, ext-filter · bootstrap ^2.0, shared ^2.0, psr/clock ^1, psr/container ^2, psr/log ^3, psr/simple-cache ^3
│   └── src/               psr-4 DeepWebSolutions\Framework\Core\ · PluginKernel, PluginBoot, ComponentInterface, CompositeComponentInterface, ConditionalComponentInterface
│       ├── AdminNotice/{AdminNotices, Notice, NoticeType, NoticeScope, NoticeContext, NoticeIdentity, StoredNotice, RecipientState, NoticeStoreInterface, RecipientStateStoreInterface, NoticeSelector (@internal), Stores/ (@internal)}
│       ├── Caching/{TransientCache, Exceptions/InvalidCacheKeyException}
│       ├── Clock/SystemClock · Logging/{AbstractLogger, ErrorLogLogger, GuardedLogger (@internal)}
│       ├── Migration/{MigrationRunner, BatchedMigrationStepInterface}
│       ├── Permission/CapabilityGrants
│       ├── Scheduling/{SchedulerInterface, Scheduler, ScheduledHook, Recurrence, Backends/ (@internal)}
│       └── Storage/{OptionRows, OptionRow, MetaRows, MetaRow, Database (@internal)}
├── packages/settings/     ahegyes/wp-framework-settings     php >=8.5 · core ^2.0
│   └── src/               psr-4 DeepWebSolutions\Framework\Settings\ · Forms (render_control, sanitize_value, sanitize_fields, is_disabled, is_group, get_title, get_rejection_message, check_helper_id, render_hidden_input, escape_attribute, get_rows (@internal))
│       ├── AdminPage/OptionsPage
│       └── EditScreen/{ObjectForm, ObjectFormHostInterface, ObjectStoreInterface, FormTable (@internal), Hosts/{PostMetaBox, UserProfile, TermForm}, Stores/MetadataStore}
├── packages/woocommerce/  ahegyes/wp-framework-woocommerce  php >=8.5 · settings ^2.0 (WC ^11.2 as host)
│   ├── uninstall.php        require_once's bootstrap/WooCommerceUninstaller.php   (path = public API)
│   ├── bootstrap/           PHP 7.4-parseable, by path: WooCommerceUninstaller::get_meta_types(): array
│   └── src/               psr-4 DeepWebSolutions\Framework\WooCommerce\ · WooCommerceRequirement
│       ├── EditScreen/{Hosts/OrderMetaBox, Stores/OrderMetaStore}
│       ├── Logging/WooCommerceLogger
│       ├── ProductData/{ProductDataTab, ProductMeta}
│       └── Setting/{SettingsTab, SettingsSection, Fields (@internal)}
├── tests/{Unit, PHPStan, Integration, Fixtures/{consumer-a (WC), consumer-b (non-WC), personas/{bootstrap,shared,core,settings,woocommerce}}}
├── phpcs.dist.xml         PHPCS over production code; phpcs.tests.dist.xml lints tests/
├── phpstan.shared.neon    PHPStan over packages/shared and tests/PHPStan/Shared, with no WordPress symbol known
├── phpstan.wordpress.neon PHPStan over packages/core and packages/settings, with the WordPress stubs and extensions
├── phpstan.woocommerce.neon PHPStan over packages/woocommerce/src, with the WordPress and WooCommerce stubs
├── phpstan.bootstrap.neon PHPStan over the by-path files at PHP 7.4 and WordPress 5.2, the oldest they run on
├── .wp-env.json           developer environment: single site with WooCommerce, as local, on port 8800
├── .wp-env.tests.json     Integration environment: single site with WooCommerce, as production
├── .wp-env.multisite.json Integration environment for the `multisite` and `without-woocommerce` groups: a network without WooCommerce, as production
└── composer.json          one `packages/*` path repository
```

## Commands

```sh
composer packages-install
composer quality-check          # validation, PHPCS (both profiles), PHPStan, Unit and Integration tests
composer test:unit
composer test:integration       # starts .wp-env.tests.json, then runs test:integration:run
composer test:integration:run   # Integration tests in the running .wp-env.tests.json, without the multisite and without-woocommerce groups; WP_ENVIRONMENT_TYPE=production
composer test:integration:multisite      # starts .wp-env.multisite.json, then runs test:integration:multisite:run
composer test:integration:multisite:run  # the multisite and without-woocommerce groups in the running .wp-env.multisite.json
composer audit --locked
npm audit --omit=dev --audit-level=high
actionlint
zizmor .github/
```

## Conventions

1. **Gates and toggles.** `should_load()` is static, pure and total: no hooks, writes, notices, translation, user reads or construction. Read options with an explicit default. Detect companions by include-time constants, classes or functions, aware of network activation. A feature's toggle and settings UI live outside its gate; a gate mixing an environment check with a setting exposes the environment half as a static method.
2. **Nodes and the container.** Node classes never extend, implement or `use` optional or late-loaded types. Never constructor-inject a gated component; inject ungated services. Definitions are `static fn ( ContainerInterface $c )` closures or autowiring, never PHP-DI helpers. Hold the built container as `ContainerInterface`. Container reads belong in the kernel and composition wiring: a class-entry read checks the entry's type before use, and a factory collecting contributors re-applies their `should_load()` before resolving them. Framework classes take their logger injected; the composition root chooses the logger.
3. **Constructors and `register_hooks()`.** Constructors take services only. `register_hooks()` adds `array( $this, 'method' )` hooks, or constructs an `OptionsPage`, `ProductDataTab` or `ObjectForm` and calls its `register_hooks()`. It never fires actions. Registration-time hooks register unconditionally and authorize in the callback. Scheduled-hook callbacks register on every request, because Action Scheduler runs actions in admin-ajax, WP-Cron and WP-CLI requests, and tolerate a duplicate or a missing invocation.
4. **Packages and `Shared\`.** Package edges stay as in the file map: a package requires only the layer below it, declaring a lower one only to raise its floor, and builds on everything beneath it, as woocommerce also builds on WooCommerce; a new edge needs the owner. Settings and WC helpers and hosts take data and implement no core type, because the kernel builds nodes by class-string. Every reachable `Shared\` symbol is API, and WordPress never enters the shared package. Shared holds kernel types that code without core can use; a primitive several packages need lives in the lowest of them, public only when it fills a gap that PHP, its extensions and PHPStan's typing leave, with its siblings decided together. A kernel type enters only when it would work unchanged in any PHP project, and on the owner's ruling. Result serves only an internal operation with two or more expected failure reasons a caller branches on, declared as `AbstractResult<T, E>` under `#[\NoDiscard]`; WordPress and WooCommerce APIs, filters, REST and AJAX responses and v1 public functions use `X|WP_Error`. Carriers are `final readonly` and extend `AbstractValueObject` only when something compares them. `Version` parses only versions the plugin controls, never on the boot path. Framework code throws the `Shared\Exception` mirrors.
5. **Safeguards.** Everything a safeguard calls lives in `includes/safeguards.php` or files it requires. Relaxation happens only through the safeguard's own filter, after `{prefix}_initialized`, on an explicit `false`.
6. **Shared logic.** Logic that global functions or templates need lives in static methods of final classes. Per-request state lives in a static holder or `WC()->session`. No service locator.
7. **Migration steps.** Append-only; a retired step keeps its code or throws. Converge from any partial state; verify writes and throw on failure, including a scheduler write that returns `WP_Error`. Role capabilities are granted by `Permission\CapabilityGrants`, never by a step. Create resources resumably (lookup marker, explicit `post_author`). Use WC CRUD for orders and products. A plain step finishes well under 10 minutes; bulk work is a `BatchedMigrationStepInterface` step, run only from the runner's scheduled hook, at about 1–2 s per batch. Steps run on `wp_loaded`, so every `init` registration exists. One linear list serves every build variant.
8. **Settings.** Full keys are `Options` constants; WC ids are flat. Boot-time defaults are constants; translated defaults come from `Options::text()` after `init`. Validate on write, guard on read. Direct meta writes go through `wp_slash()`. Framework components store data only under the consumer's `{prefix}_`, so the plugin's footprint covers it; a `TransientCache` prefix and any generation option it names start with it, so the footprint covers the generation option, and its entries, transients that all carry a timeout, expire on their own. The bootstrap updater's release caches carry the text domain instead, and the uninstall engine deletes them itself. WooCommerce multiselect option keys are truthy, because WooCommerce's save drops `'0'` and `''`.
9. **Object forms** render through `Forms::render_control()`, print their nonce bound to the object and verify it before they evaluate their definition; every framework saver goes through `Forms::sanitize_fields()` and leaves absent (unrendered or disabled) fields untouched.
10. **Translation, by-path files, Plugin Check.** Never translate at include or registration time. Framework strings use only the `'wp-framework'` domain, owned by core, settings and woocommerce; bootstrap and shared ship none, so a boot notice builds its translated message in its render callback and prints it through `Requirements::print_admin_notice()`. The by-path files (the bootstrap package, the WC package's `uninstall.php` and `bootstrap/`) stay 7.4-parseable, loaded by path, outside every `autoload` key, and define code only as final classes with public static entry points; consumers require only the loader files, whose paths are public API. Code is Plugin-Check-clean, using inline `phpcs:ignore` with a reason; local PHPCS runs the full `WordPress` standard, which covers the WordPress sniffs Plugin Check runs (its own `PluginCheck` sniffs and file checks run only at release).

## Contract

The 2.x contract is every public class, interface, enum, method and constant, the protected members of non-final classes, and the hook and priority each public callback method is registered on, unless tagged `@internal`. `@internal`, `private` and protected-on-final members are not contract, and an `@internal` symbol serves only its own package, because a caret edge installs a package beside an older or newer release of another. Hook names and their arguments, option and meta keys, REST routes and the by-path loader paths are contract too.

A package README may name namespaces whose symbols are internal, and a class there is internal even without its tag; core's are `Core\AdminNotice\Stores\` and `Core\Scheduling\Backends\`.

Releases follow SemVer on this contract from the first tag: a break of it ships as the next major, and internals change in any release.

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`. Every package directory holds the stub `index.php`, `<?php declare( strict_types=1 ); // Silence is golden.`, and every other file outside the shared package carries `\defined( 'ABSPATH' ) || exit;` after its `use` block. Code uses `array()`, `final` classes by default and `#[\Override]` on every override, and `protected` rather than `private`, except for the internals of a non-final class, which are `private` because its protected members are contract.
- No package ships free functions or a Composer `files` entry. A function family is a final class with public static entry points, protected static helpers and a protected constructor. Logic several classes share has one implementation: when its callers are in one package, an `@internal` final collaborator or an `@internal` public static on the class that owns the concept, and when they span packages, the smallest public API in the lowest package that needs it. A protected copy needs the owner's ruling. A lone call to a WordPress, WooCommerce or PHP API is not a copy, and neither is an idiom WPCS needs to see at each call site; the framework knowledge wrapped around such a call is. A copy also stays where no single home loads for every caller, as between PHP 7.4 by-path code and autoloaded code. The by-path classes register hooks with static closures, since a named callback would have to be a public method.
- A name imported from `Psr\` takes a `Psr` alias, as in `use Psr\Log\LoggerInterface as PsrLoggerInterface;`, so every use shows that the type is a PSR interface or class.
- Every element outside `tests/` has a docblock: one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why. Property and constant docblocks open with "The …", constructors read "Constructor.", and an override reads `{@inheritDoc}`. Tag values align at column 10, a file docblock's `@package` sits in its own group after `@since` and `@version`, each `@param` stays on one line, `@throws` reads "Thrown when …", `@since` names the release that added the element, and `@version` the last release that changed its behavior or signature.
- Every `do_action()` and `apply_filters()` with a public hook name carries a WordPress hook docblock directly above the call: "Fires …" or "Filters …", `@since`, `@version` and one `@param` per argument, which PHPStan checks against the call's arguments.
- Classes and traits outside `tests/` group members in `// region NAME` blocks named, in order, TRAITS, FIELDS AND CONSTANTS, MAGIC METHODS, GETTERS, SETTERS, INHERITED METHODS, METHODS, FACTORY METHODS, HOOKS and HELPERS; exception classes, interfaces and enums carry none.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change. A `phpcs:ignore` trails its line, names the sniff and gives a one-clause reason; a `@phpstan-ignore` names its identifier.
- Folders are PascalCase. A concept folder is singular, or a gerund or mass noun, named in WordPress's words for its concept or the admin surface it serves and never only after one class it holds (`AdminNotice/`, `EditScreen/`, `Versioning/`); the folder that defines a kind's base types is singular (`Exception/`); a folder of a kind's concrete members is plural (`Exceptions/`, `Stores/`, `Hosts/`), and every concept that defines an exception has an `Exceptions/` folder. A package's `src/` root holds only what is quintessential to the package, and a WooCommerce variant sits in the folder named as its lower counterpart's.
- Tests mirror the namespace below the package segment and are `final` classes named `<Class>[<Aspect>]Test`, or after the family they cover, with `test_snake_case` methods, `#[DataProvider]` keys in kebab-case, real instances and recording spies, and no docblocks.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

1. A throw in `register_hooks()` leaves earlier hooks live, and side effects are never undone. Safeguards still ignore relaxations, because `_initialized` never fires.
2. Gates evaluate once, at `plugins_loaded`. Schema-gated components switch on one request after the migrating request.
3. A request killed mid-migration holds the lock for 10 minutes, then the step re-runs and the error row names the step that did not finish. One primary database is assumed. While a failure persists, each request issues one `INSERT IGNORE` plus one conditional `UPDATE`.
4. After a rollback, older code runs on newer data until the plugin is upgraded again; the written-back revision then re-runs the newer steps, and `{option}_high_water` keeps the highest revision reached. Mixed-version rolling deploys may re-run steps, which converge.
5. Uninstall visits every site of a network and cleans each one that consents. A web request on a large network refuses and names `wp plugin uninstall`; on a network of thousands of sites a web request can still exceed the server's time limit, and a retry or `wp plugin uninstall` completes it. Where the object cache cannot flush its runtime cache, every visited site's options stay in memory until the request ends. Network-global user meta goes only on a single site or on the consent constant. Action Scheduler actions stay queued when it is not loaded at uninstall, and fail once it runs them, since no callback is registered; on a site without its tables, unscheduling logs a database error and does nothing else; a job that cannot be unscheduled is logged and left.
6. Over WC REST, `validate` receives WooCommerce's normalized value: unknown multiselect choices are already dropped and text already sanitized. A rejected value is not stored, but the response echoes it. Grouped `option[key]` ids would bypass the bridge, hence flat ids. WC REST lists only WooCommerce's own field types, so custom-type and `checkboxes` fields are not reachable there. A product field's `validate` runs only on the classic product edit form; WC REST `meta_data`, CSV import and CRUD writes store what they are given. An object form field's `validate` runs only on its form; REST, imports, meta and order CRUD writes store what they are given, and so does a Custom Fields box for a key without a leading underscore: core's on a post type that supports `custom-fields`, orders in the posts table included, and WooCommerce's on the order tables' edit screen.
7. WC-rendered controls carry WooCommerce's markup, and the row printed around a custom-type or `checkboxes` field on a WooCommerce settings screen or the product data panel imitates it. The framework guarantees markup only for `Forms::render_control()`. `OptionsPage` and `ProductDataTab` report a rejected field in the page's notices, not beside the field, which after the redirect shows its stored value rather than the rejected input. `ObjectForm` reports it as a one-shot user notice through the plugin's `AdminNotices`, on the user's next site, network or user admin screen outside the block editor, so a rejection saved from the block editor waits for a classic screen, and a rejection whose notice the store refuses goes unreported. On the profile and in the block editor, which never validate the form, a number the browser cannot parse posts as empty and clears the stored value.
8. Partial bootstrap package: if `load.php` exists but a file it requires is missing, the `Error` is thrown before the template's catch and the plugin fatals at include. A missing `vendor-prefixed/` also unregisters the updater and skips the floor gate, so safeguards then load on whatever PHP runs (two faults). Core and woocommerce print their boot notices through bootstrap's `Requirements`, so a plugin that boots them without requiring `load.php` fatals when one of those notices renders.
9. Deactivation, recovery mode and below-floor runtimes unload safeguards, as for any plugin. Plugins whose data would leak (IC) hide it at the data level.
10. Scheduling and one-shot notices are best effort. Either scheduler can run an invocation twice or not at all, and work advances only with site traffic, Action Scheduler's admin dispatch, or a system cron or WP-CLI runner. On hosts that replace WP-Cron's store, the WP-Cron backend still reads only the `cron` option, so it may not see the host's events. Action Scheduler compares hook names and arguments under the database collation, so identities that differ only in letter case are one there. Two concurrent renders can both show a one-shot notice, and a request that dies after output shows it again; notices wait out block-editor screens, which hide them.
11. Update offers come only from WordPress's own plugin update check. While its wordpress.org request fails, the check offers no Update URI release, so a new release waits for a later check that succeeds. A plugin active only on sites other than the main one is never offered a release, because WordPress checks only on the main site and in the network admin.
12. Capability grants act on `wp_loaded` after the declaration changes or a declared role appears or is deleted. A granted capability removed or denied by hand stays so; a declared role deleted and created again is granted again, unless it was deleted while the plugin was inactive, which then reads as a hand removal. WP_Roles writes the whole roles row from the request's copy, so another request's role write in between is lost. A change the row does not keep is logged and made again on every request, which never ends on a site whose roles are defined in code (`$wp_user_roles`) or locked.
13. `TransientCache` is best effort. On the options table a value holding invalid UTF-8 is refused and `set()` returns false. An object whose class no longer loads reads back as `__PHP_Incomplete_Class`, so a plugin clears its caches when cached classes change. `clear()` leaves earlier entries in place until they expire, and behind a multi-node object cache that is not coherent, a node may read the old generation until the option reaches it. Concurrent misses all recompute; nothing locks a key.
