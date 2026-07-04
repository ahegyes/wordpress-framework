# wordpress-framework

DWS v2 WordPress framework — monorepo for 7 packages (bootstrap, shared, storage, core, utilities, settings, woocommerce). Auto-splits to `wp-framework-*` mirrors via `.github/workflows/split-packages.yml` (splitsh-lite v1.0.1).

Historical decision rationale, superseded designs, and per-session audit/remediation history live in the project vault (`01_Projects/dws-wordpress/decisions/` and `handoffs/`) and in git history. This file states only the current as-built invariants.

## Status

All seven packages are implemented. `shared` (Error / Exception / Result / ValueObject / Version scaffolding + Reflection helpers), `storage` (KeyValueStore + Memory / Options / UserMeta backends), `core` (kernel + feature / lifecycle / rendering / installer contracts), `utilities` (rollup package over Hooks / AdminNotices / Caching / Conditionals / Scheduling / Permissions / Logging / Helpers), and `settings` (descriptors + aggregator + WordPress options, MetaField object-meta surfaces, and REST) sit on `bootstrap`. `woocommerce` carries the settings backend (`WooCommerceSettingsBackend` + `DescriptorBackedWCSettingsPage` + `WCSettingsBuilder`), the **product-data settings tab** (`ProductData/`), the **order-data field store** (`OrderData/`), WooCommerce version / db-version conditionals (`Conditionals/`), and a `WC_Logger` PSR-3 logger (`Logging/`).

## Monorepo structure

```
wordpress-framework/
├── packages/
│   ├── bootstrap/          # ahegyes/wp-framework-bootstrap (PHP 5.6+, pre-autoload)
│   │   ├── functions.php   # autoload-files aggregator — requires per-concern function files below
│   │   └── src/
│   │       ├── Environment/   # is_php_compatible, is_wp_compatible (defensive WP version-check wrappers)
│   │       ├── Plugin/        # get_plugin_metadata (cached get_plugin_data reader)
│   │       ├── Requirements/  # FRAMEWORK_MIN_PHP/WP constants + check_requirements (explicit chain — no are_requirements_met)
│   │       └── Notice/        # output_requirements_error (admin-notice renderer)
│   ├── shared/             # ahegyes/wp-framework-shared (PHP 8.5+, substrate kernel — runtime-WP-aware, no WP-specific abstractions)
│   │   ├── functions.php      # autoload-files aggregator — requires nested <namespace>/functions.php files
│   │   └── src/
│   │       ├── Error/         # ErrorInterface (marker)
│   │       ├── Exception/     # ExceptionInterface + AbstractException / AbstractInvalidArgumentException / AbstractRuntimeException
│   │       ├── Result/        # AbstractResult + Success + Failure (sealed-type sim of Result<T, E>)
│   │       ├── ValueObject/   # ValueObjectInterface + AbstractValueObject + Exceptions/InvalidValueObjectException
│   │       ├── Version/       # Version VO (version_compare semantics) + Exceptions/InvalidVersionException
│   │       └── Reflection/    # get_public_property_names + convert_to_primitives (underpin VO base)
│   ├── storage/            # ahegyes/wp-framework-storage (PHP 8.5+, zero-dep leaf — KV storage backends)
│   │   ├── functions.php      # autoload-files aggregator (placeholder; storage has no namespace functions yet)
│   │   └── src/
│   │       ├── KeyValueStoreInterface.php  # get/set/has/delete/get_all/clear contract
│   │       ├── MemoryStore.php             # per-request array store
│   │       ├── OptionsStore.php            # wp_options-backed (one row per store; autoload policy flag)
│   │       └── UserMetaStore.php           # per-user meta-backed (optional explicit user id)
│   ├── core/               # ahegyes/wp-framework-core
│   │   ├── functions.php      # autoload-files aggregator (placeholder; core has no namespace functions yet)
│   │   └── src/
│   │       ├── PluginInterface.php          # primary: consumer contract (get_plugin_file/_header/_container/_feature_classes/_installer)
│   │       ├── PluginKernel.php             # primary: runtime engine + static register_lifecycle_hooks()
│   │       ├── ValueObjects/                # PluginHeader + PluginBootReport (+ BootStatus enum)
│   │       ├── Feature/                     # FeatureInterface (static get_conditional_classes + get_component_classes) + Exceptions/
│   │       ├── Composite/                   # CompositeComponentInterface (static get_child_component_classes; kernel-dispatched subtree)
│   │       ├── Conditional/ConditionalInterface.php  # pre-resolution gate (is_met)
│   │       ├── Enabled/EnabledInterface.php          # post-resolution gate (is_enabled)
│   │       ├── Lifecycle/                   # kernel-dispatched markers: Hookable/, Initializable/
│   │       ├── Rendering/                   # self-dispatched markers: Renderable/, Outputtable/
│   │       └── Installer/                   # install/update/activate/deactivate/uninstall + version I/O (get/set stored, get current)
│   ├── utilities/          # ahegyes/wp-framework-utilities (rollup: Hooks + AdminNotices + Caching + Conditionals + Scheduling + Permissions + Logging + Helpers)
│   ├── settings/           # ahegyes/wp-framework-settings (declarative settings: descriptors + aggregator + WP options + MetaField object-meta surfaces + REST; depends on storage + shared)
│   └── woocommerce/        # ahegyes/wp-framework-woocommerce (Backend/ settings stack + ProductData/ + OrderData/ + Conditionals/ + Logging/)
├── tests/Fixtures/
│   ├── consumer-smoke/             # plugin-template-shaped scoping smoke fixture (namespaced scoping prefix)
│   ├── consumer-smoke-b/           # minimal second scoped consumer (bootstrap + shared + PHP-DI, distinct prefix)
│   └── smoke-coexistence.php       # loads both scoped consumers into one PHP process (multi-plugin coexistence)
├── composer.json                   # path repos for all 7 packages + VCS for wordpress-configs + WP Packages registry for wp-plugin/woocommerce
└── .github/workflows/
```

`wp-plugin/woocommerce` is installed as a dev dep at `vendor/wp-plugin/woocommerce/` for source-grep + integration-test runtime when developing the woocommerce package. Mounted into wp-env via `.wp-env.tests.json` `mappings`.

## Local development

Commands run from monorepo root:

```sh
composer packages-install   # PHP deps (--ignore-platform-reqs wrapped)
npm install                 # wp-env
npm run wp-env:start        # Start WordPress on port 8801
composer quality-check      # lint:php + test (unit + integration — boots wp-env)
composer test:integration   # WP integration tests via wp-env
composer test:mutation      # Infection mutation tests (no Docker; Unit suite only)
```

`composer lint:php` aggregates PHPCS + PHPStan + **deptrac** (architecture-rule check via `deptrac.yaml`) + composer-require-checker. Cache lives at `tests/.cache/deptrac/`.

wp-env runs on **port 8801** (per workspace port scheme — see `feedback_wp_env_port_scheme` memory).

Per-package install isn't supported during dev: cross-package requires (e.g., core's `ahegyes/wp-framework-bootstrap`) only resolve via the root's path-repo declarations; per-package `require-dev: ahegyes/wordpress-configs` is fetchable only via the root's VCS repo declaration.

Integration tests run in wp-env's `cli` container; `.wp-env.tests.json` declares `"testsEnvironment": false`.

## Cross-package testing

PHPUnit runs at the root level:

```sh
vendor/bin/phpunit packages/bootstrap/tests/
vendor/bin/phpunit --testsuite=Unit
```

Integration via `composer test:integration` → `npm run wp-env:start` → `wp-env --config .wp-env.tests.json run cli --env-cwd=wp-content/mu-plugins/wp-framework vendor/bin/phpunit --testsuite=Integration`.

## Releases

`.github/workflows/split-packages.yml` runs on push to `trunk`. splitsh-lite splits each `packages/<X>/` subdirectory and force-pushes to `github.com/ahegyes/wp-framework-<X>` via `MONOREPO_SPLIT_TOKEN` PAT.

Per-package changelogger fragments live in `packages/<X>/changelog/` and aggregate to `packages/<X>/CHANGELOG.md` on `composer changelog:write:<package>`.

## Architectural decisions

### Plugin entry-point shape: interface + final + composition

Consumer plugins define a `final class Plugin implements PluginInterface` (singleton-per-plugin, matches every successful WP plugin rewrite). `PluginKernel::run( Plugin::get_instance() )` is the canonical bootstrap call. The kernel is `final` — composition only, no inheritance.

No abstract base class for Plugin. Storage lives in the separate `wp-framework-storage` package, namespace `DeepWebSolutions\Framework\Storage`.

Core lifecycle markers are `HookableInterface` and `InitializableInterface`; rendering markers are `RenderableInterface` and `OutputtableInterface`. Plugin activation, deactivation, and uninstall live on the plugin's `InstallerInterface`, not on per-component interfaces.

Component enablement is a single positive predicate: `Enabled/EnabledInterface::is_enabled()`. There is no `core/src/State/` split.

`InitializationException` lives in `Lifecycle/Initializable/Exceptions/`; `ActivationException`, `DeactivationException`, `InstallationException`, `UpdateException`, and `UninstallationException` live in `Installer/Exceptions/`.

### Helpers: deferred, discover-on-demand

No standalone helpers package. The closed ecosystem plus PHP 8.5 strict types means most helper pressure belongs in native PHP, native WordPress APIs, or consumer-local code.

`utilities/src/Helpers/` exists as a sanctioned early carve-out (owner decision, build-ahead-when-demand-certain): a lean bag of cross-plugin stateless helpers with guaranteed wave-2 call sites (`Arrays`, `Assets`, `Request`). The ≥10 threshold below governs FURTHER topic growth into the folder, not the folder's existence.

During plugin migration: replace v1 `Helpers\*` call sites per the rule in the v1 helpers inventory (project vault, `references/`) (strict types → native PHP → native WP → inline → plugin-local final class → framework topic folder). If ≥10 cross-plugin stateless helpers accumulate without fitting an existing topic, grow `utilities/src/Helpers/` at that point. The inventory preserves the v1 surface for cherry-picking.

### Subtree gating: kernel-dispatched composites

A component subtree is declared by `CompositeComponentInterface::get_child_component_classes()`. The kernel is the only lifecycle dispatcher; group components never hand-dispatch child lifecycle methods.

### Service interfaces: only on plurality

Service-level interfaces exist only when there are multiple implementations (current or imminent). Single-impl services are concrete `final` classes; consumers typehint the concrete class.

Applied: `HookHandlerInterface` has Direct/Buffered/Scoped implementations; `SettingsBackendInterface` has WordPress and WooCommerce implementations, with MetaBox deferred until demand. There is no `HooksServiceInterface` because `HooksService` has one implementation.

Trade-off: `final class` is harder to mock without `dg/bypass-finals`. Plugin tests should use real service instances with recording handlers — see `HooksServiceTest::recording_handler()` for the pattern.

### Unified hook-registrar abstraction: deferred

There is no shared "any hook registrar" abstraction. `HookHandlerInterface` is handler-only and `callable`-native; `HooksService` carries `$handler_id` for routing. Re-unifying would require a real consumer because the handler surface is 4-arg and the service surface is 5-arg with routing.

Reconsider when a concrete consumer wants substitutability — e.g., a logging/metrics decorator wrapping any registrar, or an embedded feature module typehinting "any registrar". Don't pre-add the abstraction.

### Persistent admin notices

`AdminNoticesService` queues notices in memory. Cross-request and sticky-dismissal notices use a `NoticeStore` interface with Memory/Options/UserMeta backends, an `is_persistent` flag on `AdminNotice`, and `DismissedNoticesTracker` dismissal via user_meta keyed by notice id. The service's `$stores` default lives in the constructor parameter itself (an omitted argument yields the single in-memory store; an explicit empty array registers none — there is no nullable-coalesce shape), and the service self-wires via `register_hooks()`: one consumer call binds `render_notices()`/`print_dismiss_script()`/`handle_dismiss()` to `admin_notices`/`admin_footer`/`wp_ajax_{action}` through instance-addressed callables.

Persistent notices are required for post-redirect-get error UX: queue notice, redirect, render on the next request, and remember dismissal.

Dismissal is recorded only for a queued persistent dismissible notice the current user may see; the AJAX endpoint keeps the nonce/login checks and rejects arbitrary well-formed IDs.

### wp-framework-shared package: substrate kernel

`ahegyes/wp-framework-shared` is a sibling of `bootstrap` in the dep tree; both have zero internal framework deps. It is required directly by `core`, `utilities`, `settings`, and `woocommerce`, not transitively through `core`.

Contents (all PHP 8.5+):
- `Error/ErrorInterface` — marker for failure payloads carried by `Result\Failure`.
- `Exception/{ExceptionInterface, AbstractException, AbstractInvalidArgumentException, AbstractRuntimeException}` — scaffolding for framework-side exception hierarchies.
- `Result/{AbstractResult, Success, Failure}` — sealed-type simulation of `Result<TValue, TError>`.
- `ValueObject/{ValueObjectInterface, AbstractValueObject}` with reflection-driven `equals()` + `jsonSerialize()` + `Exceptions/InvalidValueObjectException`.
- `Version/{Version, Exceptions/InvalidVersionException}` — a `version_compare()`-semantics value object.
- `Reflection/functions.php` — `get_public_property_names()` + `convert_to_primitives()` helpers underpinning the VO base (files-autoloaded).

Shared primitives change for different reasons than core's Plugin/Lifecycle surface and release independently. `utilities` and `woocommerce` may depend on `shared` without inheriting `core`.

**Runtime-WP-aware, not WP-agnostic.** The package assumes WordPress is loaded at the point of use (it always is — every consumer of shared lives inside a WP plugin context). Uses `wp_json_encode()` for safer JSON output (handles invalid UTF-8 via `_wp_json_sanity_check`, applies WP filters). What shared does NOT do: depend on WP-specific abstractions (Options API, Hooks, Posts, Settings) — those are the line between this package and `utilities`/`core`. Tests that exercise wp_* calls run in the Integration suite under wp-env.

Enforcement: deptrac `Shared: ~` ruleset (zero internal framework deps allowed); composer constraint `"php": ">=8.5"` only.

Strict scope: substrate primitives only. WP-aware helpers DO NOT belong here — if they emerge during migration, they live first in the consumer plugin's local `Helpers/` per the discover-on-demand helpers decision, promoted to `utilities/` if cross-plugin, or eventually a separate `wp-framework-wp-helpers` package only at sustained volume.

### Folder naming: singular concept / plural bag

| Form | Role | Contents | Example |
|---|---|---|---|
| **Singular** | The CONCEPT | Type definitions, interfaces, abstract bases. One canonical of each thing. | `Conditional/`, `Lifecycle/`, `Enabled/`, `Result/`, `ValueObject/`, `Error/`, `Exception/` |
| **Plural** | A BAG of concrete instances | Final classes that implement the concept. | `Installer/Exceptions/`, `ValueObject/Exceptions/`, `Hooks/Handlers/`, `Conditionals/Dependencies/` |

Exception: collective-noun folders (`Conditionals/`, `Hooks/`, `AdminNotices/`) keep plural names because their natural English is plural — don't fight English; the plural folder still acts as a bag of related concrete utilities.

When a singular concept folder grows mixed contents (interface + abstract base + concrete bag), split: nest a plural subfolder under the singular concept. Example: `Installer/` (singular — the interface) + `Installer/Exceptions/` (plural, concrete exception classes).

The split `ValueObjects/` (core — plural bag of concrete VOs such as `PluginHeader`) vs `ValueObject/` (shared — singular concept: `ValueObjectInterface` + `AbstractValueObject`) is deliberate: shared owns the contract + base, core holds concrete instances.

### Abstract base classes: allowed when pattern intrinsically requires

Amends the workspace-level "interfaces + final + composition, no abstract base class hierarchies" principle. The general rule still holds: composition over inheritance, single-impl services as concrete finals, no inheritance-driven "memory tree" plugin hierarchies (the v1 anti-pattern).

Exception, narrow: patterns whose value depends on abstract-base scaffolding may use abstract bases.

1. **Sealed-type simulation.** `Result<TValue, TError>` requires `abstract AbstractResult` + `final Success` + `final Failure` to fake a union type PHP doesn't natively support. Substituting pure interfaces drops `match()` ergonomics and the shared `is_success`/`is_failure` derivation.
2. **Reflection-driven shared behavior.** `AbstractValueObject::equals()` walks public properties via reflection and works for ANY subclass without each subclass reimplementing equality. Pure interface + per-class implementation forces every VO to duplicate equality logic — defeating the point of the base.
3. **Exception-family subtyping.** `InvalidValueObjectException` (`shared/ValueObject/Exceptions/`) is the abstract base each value object's invalidity exception extends (`InvalidVersionException` supplies its `value_object_type`), so the family shares one message format and a single `catch ( InvalidValueObjectException )` point. The abstract is intrinsic: exceptions dispatch by type, so a family of distinct catchable subtypes sharing message-building needs the subtype hierarchy — a lone `final` class collapses the subtypes, and composition yields no catchable type. Descriptors are not value objects, so their invalidity exceptions extend the generic `Abstract{InvalidArgument,Runtime}Exception` bases directly.

These exceptions live in `wp-framework-shared` (runtime-WP-aware substrate; the only legitimate home for abstract-base scaffolding). Consumer plugins MAY extend these abstract bases (e.g., `PluginHeader extends AbstractValueObject`); they MUST NOT introduce new abstract base hierarchies of their own without similar intrinsic-requirement justification.

Rule of thumb: if you can implement the pattern as `interface + final + composition` without losing essential semantics, do that. Abstract base is the escape hatch, not the default.

### Core kernel and contracts

**Primary contracts at `core/src/` root:** `PluginInterface.php` (consumer contract with `get_*`-prefixed accessors) and `PluginKernel.php` (runtime engine). Aspect VOs grouped under `ValueObjects/` (e.g., `PluginHeader`).

**Pre-resolution gating:** `Conditional/ConditionalInterface::is_met()`. Checked by the kernel BEFORE container resolution of any Feature; cheap, side-effect-free. Standard implementations live in `utilities/Conditionals/{Dependencies,Context}/`.

**Post-resolution gating:** `Enabled/EnabledInterface::is_enabled()` — single positive predicate. Rare user+framework gating composes inline inside `is_enabled()`.

**Feature wrapper unit:** `Feature/FeatureInterface` declares `static get_conditional_classes()` (class-strings, evaluated before the Feature is constructed) and `get_component_classes()`. There is no `register_services()` hook. All container wiring, plugin-wide and Feature-private alike, lives in the plugin's own container-definitions file; the PHP-DI write surface stays plugin-side.

**Centralized installer:** `Installer/InstallerInterface` — `install()`, `update( Version $from_version )`, `activate( bool $network_wide )`, `deactivate( bool $network_deactivating )`, `uninstall()`, plus the version I/O the kernel drives on boot: `get_current_version()`, `get_stored_version()`, `set_stored_version( Version )`. One implementation per plugin; `update()` uses the `shared/Version/Version` VO for type-safe comparison and each migration step MUST be idempotent.

**Kernel boot sequence** (`PluginKernel::boot()`, idempotent via a `$booted` guard): run the installer version check (install/update, persisting the version only on success; any `Throwable` is logged via the optional PSR-3 logger with class + message in the log message, leaves the stored version for the next boot to retry, and stops the boot so a broken migration cannot fatal every request; stored version > current code version also logs and stops without running `update()` or overwriting the stored version) → gate each Feature on its `static get_conditional_classes()` BEFORE constructing it → resolve the surviving Features → validate the declared component graph → flatten every Feature's component tree pre-order, pruning any subtree whose node is disabled (`EnabledInterface::is_enabled()`) → record any enabled component that implements neither lifecycle marker as inert and debug-log it via the optional logger → `initialize()` every initializable component, THEN `register_hooks()` on every hookable component. Initializing all before any registers hooks is the guarantee: a hook callback may safely reach a peer component in another Feature. The whole component phase is a hook-table transaction: the kernel snapshots `$wp_filter` (plain array copies of each `WP_Hook`'s public `->callbacks`) before resolving any Feature, and any `Throwable` in the phase — resolution, `initialize()`, or `register_hooks()` — diff-unwinds the table back to its window-start state through `add_filter()`/`remove_filter()` only (safe while a hook is mid-dispatch; live WP_Hook internals are never manipulated directly — a tag the unwind empties is recreated by WordPress as a fresh registry object), so no hook registered through the WordPress hook API by any phase of a failed boot survives (constructor, `initialize()`, `register_hooks()`) — including hook-table mutations third-party code made synchronously inside the window, e.g. reactions to an action a component fired mid-pass. A residue left by direct hook-table manipulation cannot be removed through the API and is detected and logged after rollback. `$wp_current_filter`, `$wp_actions`, and non-hook side effects (options writes, post-type registration, …) are not transactional. Kernel diagnostics are best-effort: a throwing logger never alters the boot outcome. The public `$kernel->boot_report` property (a `final readonly` `PluginBootReport` carrier with a `BootStatus` enum, replaced wholesale at boot start and at each terminal point — blocked/failed/completed) exposes the gated Features, pruned components, runnable components, inert components, initialized components, hook-registered components, status, and failure summary from the boot attempt; its constructor defaults are the single source of the not-started shape. Activation/deactivation are wired separately via `PluginKernel::register_lifecycle_hooks()` at plugin-include scope.

**Component discipline under the boot transaction:** components MUST NOT do work in constructors — a disabled node is still constructed to answer `is_enabled()`; only its subtree is construction-free. Hooks belong in `register_hooks()`; `initialize()` is pre-hook setup. Components MUST NOT fire consumer-visible actions from `register_hooks()` — third-party reactions inside the window are unwound by a failed boot.

**Method-name convention:** `get_*` prefix on getter methods (`get_plugin_file`, `get_plugin_header`, `get_container`, `get_feature_classes`, `get_installer`, `get_conditional_classes`, `get_component_classes`, `get_child_component_classes` — class-string getters keep the `_classes` suffix). Predicates use `is_*` (`is_met`, `is_enabled`). Action verbs keep bare form (`register_hooks`, `initialize`, `render`, `activate`, `deactivate`). Plain data exposure uses public (asymmetric-visibility) properties; `get_*()` methods are for derived or lazily-computed access.

**Cross-package surface:**
- `shared/Version/Version.php` — final readonly VO extending `AbstractValueObject` with `is_greater_than` / `is_less_than` / `is_equal_to` (all `version_compare()` semantics) / `from_string` / `from_parts`, and a `__toString()` override returning the raw version string (no separate `to_string()`). `is_equal_to()` follows `version_compare()` — numerically-equal segments match, omitted components are not padded, build metadata participates — distinct from the inherited structural `equals()`.
- `utilities/Conditionals/Dependencies/` — bag of `WPPluginActiveConditional`, `WPPluginVersionConditional` (active AND plugin `Version` header at/above a validated minimum; raw-string `version_compare()`, total), `PHPExtensionLoadedConditional`, `PHPFunctionExistsConditional`, `PHPVersionConditional`, `WPVersionConditional`, `PHPIniSizeConditional`.
- `utilities/Conditionals/Context/` — `IsAdminConditional`, `IsAjaxConditional`, `IsCliConditional`, `CurrentUserCanConditional`.
- Workspace-wide package-root `functions.php` aggregator pattern — each package with namespace function files gets a single composer-autoloaded `functions.php` (sibling of `composer.json`) that `require_once`'s nested `src/<namespace>/functions.php` files. Matches the `bootstrap` package's `functions.php` location + the Symfony Polyfill ecosystem convention.

### Composite components, installer orchestration, and activation wiring

**`CompositeComponentInterface` — sole-dispatcher, no escape hatch.** A composite declares its children via `static get_child_component_classes()` and never dispatches them itself; the kernel walks the declared class-strings. Static declarations let the kernel validate the whole graph (duplicate + cycle) construction-free and enablement-independently, so a duplicate hidden under a disabled parent is still caught. Dispatch walks the declared class-string, not the resolved instance's runtime class, so a resolved component can never reach a child the graph validation did not see. A leaf shared by two parents (diamond) throws as a duplicate.

**Duplicate / cycle throws `FeatureException`** (not a new contract-specific type — its docblock already scopes it to boot-time contract violations).

**Component-level conditionals deliberately NOT added** (YAGNI): `ConditionalInterface` is Feature-level / pre-resolution; `EnabledInterface` is the per-component post-resolution gate. A second component-level axis has zero consumers.

**`is_enabled()` is a COARSE attach-time gate** — evaluated once at `plugins_loaded`, before the current user and other plugins' capability filters have settled. A component performing a privileged action MUST re-check the capability inside the hook callback; the enablement gate is not a substitute.

An enabled component that implements neither `InitializableInterface` nor `HookableInterface` remains inert; the kernel records it in the boot report and emits a debug-level diagnostic through the optional logger.

**Installer orchestration on boot lives in the kernel (core)**, not in utilities. Version I/O routes through three `InstallerInterface` methods — `get_current_version()` / `get_stored_version()` / `set_stored_version()` — keeping `boot()` WP-free. Persistence + failure-notice UX live in the consumer's concrete installer and logger wiring, which may depend on storage for persistence and utilities for notices; failure UX rides the optional PSR-3 logger. A diagnostic sink such as WooCommerce logging is paired with `AdminNoticeLogger` through `Utilities\Logging\CompositeLogger` so the operator-facing notice path stays live.

**Activation wiring cannot live in `boot()`.** `register_activation_hook` only schedules a callback for the activation request, which fires during the plugin include — before any `plugins_loaded`-deferred `boot()`. So `PluginKernel::register_lifecycle_hooks( $plugin )` is static and called at plugin-include scope; it wires `activate( bool $network_wide )` / `deactivate( bool $network_deactivating )` to the plugin's installer.

### Storage and utilities hardening

The `utilities` package is a rollup over Hooks, AdminNotices, Caching, Conditionals, Scheduling, Permissions, Logging, and Helpers. The storage rules below apply to the separate `wp-framework-storage` package.

- **Storage null-correctness:** all three stores use `array_key_exists()`, not `?? $default` — a key stored as `null` returns `null`, not the default (which would contradict `has()`).
- **`UserMetaStore` per-user targeting is concrete-only.** `set/get/has/delete/get_all/clear` take a trailing optional `int $user_id = 0` (0 ⇒ current user; the anonymous guard is `< 1` after resolution). This widens the implementations but is NOT lifted to `KeyValueStoreInterface` (meaningless for Memory/Options) — callers targeting another user typehint the concrete store.
- **Grouped persistent stores are read-modify-write.** `OptionsStore` and `UserMetaStore` serialize all entries into one option/meta row; individual key writes load the row, mutate one key, and save the row. Concurrent writes to different keys in the same store are not atomic.
- **`HooksService::__construct( array $initial_handlers = array( new DirectHookHandler() ) )`:** omitted ⇒ one default `DirectHookHandler`; `array()` ⇒ zero handlers. `DirectHookHandler::DEFAULT_ID = 'direct'` single-sources the handler id across HooksService's method defaults.
- **`BufferedHookHandler` removals sync WordPress** (`remove_*` / `remove_all_*` also call WP `remove_action`/`remove_filter`, not just mutate the queue) — only adds are buffered; removes are immediate. **`ScopedHookHandler::register_lifecycle()` is idempotent** — it wires stable `array($buffer, 'flush'/'reset')` callbacks (first-class-callable closures get distinct hashes WP won't de-dup, so repeated registration would stack listeners).
- **Version conditionals are total.** `PHPVersionConditional` / `WPVersionConditional` compare the raw runtime version string via `version_compare()` — parsing it into a `Version` VO would throw on a dashless RC or a 4-segment `$wp_version`, and `is_met(): bool` must not throw (it would crash the fail-closed kernel boot). `Version` is kept only for the validated developer-supplied minimum. WPVersion also strips current's pre-release suffix and a trailing `.0` from a 3-part minimum (mirrors `is_wp_version_compatible()`); PHPVersion does not strip (mirrors the total `is_php_version_compatible()`).
- **`PHPIniSizeConditional`** is deliberately size-directive-only: byte-minimum `>=` via `wp_convert_hr_to_bytes()`; `-1` (unlimited) passes; an unknown directive (`ini_get` ⇒ false) is unmet. A blanket byte-`>=` over arbitrary ini directives corrupts boolean/string directives and mis-ranks `-1`, and a generic exact-match probe has no consumer (YAGNI).
- **AdminNotices `render_one()` keeps passing the raw message to core `wp_admin_notice()`** — core sanitizes the generated markup with `wp_kses_post( wp_get_admin_notice(...) )`. The call is unconditional: `wp_admin_notice()` ships in WP 6.4 and the framework floor is WP 7.0, so there is no fallback path.
- **Scheduling is an ordered-backend-list facade.** `SchedulerBackendInterface` carries `is_ready()` (whether the backend may be consulted for schedule, clear, and query calls); `Scheduler` holds a non-empty `list<SchedulerBackendInterface>` in declaration order. Writes target the first ready backend (falling back to the last backend when none is ready); clears consult every ready backend with the first failure in declaration order winning; queries OR/min over ready backends; `register_lifecycle()` runs on ALL backends unconditionally. The consumer states its backends (the `HooksService::$initial_handlers` pattern — there is no factory): an omitted constructor argument yields the always-ready `WPCronBackend` baseline alone, and a consumer preferring Action Scheduler passes `ActionSchedulerBackend` (readiness = injectable probe defaulting to `action_scheduler_is_ready()`) explicitly, first. v1-relapse guard: no `register_backend()` mutator, no caller-side backend IDs, and no `SchedulerInterface` while one facade implementation exists; if the backends ever collapse to one, delete the facade.
- **deptrac `Core_*` sublayers: SKIPPED.** The committed core is ~3 internal edges across single-interface files in one release granule; deptrac's value is at package boundaries (already enforced). Revisit only as `Core_Kernel` (sink) + `Core_Contracts` if doc-as-enforcement is wanted.

### Framework i18n: consumer-domain via scope-time textdomain rewrite

The framework ships no translation catalogs (i18n-catalogs-drop decision), but user-facing framework strings MUST stay translatable. They are translatable only under the *consumer plugin's* text domain (resolved by the consumer's catalog + WP just-in-time loading) — not a framework-owned domain, and not WP core's `'default'` (which has no entries for custom strings).

The mechanism is a build-time php-scoper patcher (`wordpress-configs` `contrib/wp-framework.inc.php`) that rewrites each framework `wp-framework-<package>` text domain → the consumer's `extra.text-domain` at scope time (v1 did this via `$dws_framework_language_domains`). The `"text-domain"` composer field in the template + every plugin feeds it; framework translatable strings currently span `wp-framework-bootstrap`, `wp-framework-settings`, and `wp-framework-utilities` (including the WPCron synthetic-schedule label), so the patcher is deliberately generic over `wp-framework-*`, not a bootstrap-only find-target. The `consumer-smoke` fixture exercises the rewrite and CI asserts zero residual `wp-framework-*` domains (and unprefixed WC symbols) in the scoped output.

**The end-to-end runtime path (framework string → rewritten domain → consumer catalog → WP just-in-time loading) stays unproven until a plugin ships** — verify it at the first consumer migration. Do NOT scatter per-package runtime dynamic-domain reads instead — the one scope-time mechanism is the design.

### Bootstrap requirements API: explicit `check_requirements` chain, no `are_requirements_met()`

`wp-framework-bootstrap` exposes a two-call requirements chain and ships **no** `are_requirements_met()` boolean wrapper:

- `Requirements\check_requirements( string $plugin_basename ): true|\WP_Error` — `true` when the consumer's effective PHP/WP minimums (its `Requires PHP` / `Requires at least` headers, floored by `FRAMEWORK_MIN_PHP` / `FRAMEWORK_MIN_WP`) are met, otherwise a `\WP_Error` carrying `plugin_php_incompatible` / `plugin_wp_incompatible` codes, each with `min` + `current` data.
- `Notice\output_requirements_error( string $plugin_basename, \WP_Error $error ): void` — hooks an `all_admin_notices` callback — deliberately an anonymous closure (fail-safe by construction; bootstrap exposes no public rendering API) — that renders those two codes across site, user, and network admin; no-op on an empty bag. The renderer is locked to the PHP/WP codes it owns.

A boolean wrapper would collapse the `WP_Error` to a bare `false`, discarding which requirement failed and the `min`/`current` data the renderer needs. The explicit chain hands the consumer the structured error so it can branch, layer its own gates (a WooCommerce-active check, a PHP-extension probe) before bailing, and still render the framework notice. Consumer entrypoints call the chain directly — `$req = check_requirements( $basename ); if ( $req instanceof \WP_Error ) { output_requirements_error( $basename, $req ); return; }` — ahead of autoload.

The no-anonymous-callbacks-on-WP-hooks rule governs component/service runtime code, where third parties must be able to unhook; bootstrap's requirements-notice closure is deliberately anonymous — fail-safe by construction, with no public API to unhook.

### Bootstrap-era extras: dropped

The framework does not port these bootstrap-era capability families:

- **Whitelabel / rebranding helpers** — DWS is defunct, so rebranding the framework's own admin output is meaningless.
- **Temp / upload-dir constants** — consumers derive paths via WP natives per the no-directory-assumptions rule.
- **INIT constants / `init_status` gating** — replaced by the explicit `check_requirements()` chain + `PluginKernel::run()`; the kernel's `$booted` guard is the only "already initialized" state v2 keeps.
- **Init-failure output helper** — requirement reporting lives in the single fixed `output_requirements_error()` renderer: no separate init-failure template, timing guard, or template-extension hook surface.

This does NOT touch `wp-framework-bootstrap`'s PHP-5.6+/old-WP runtime-compatibility branches, which run before the version gate and are load-bearing — see the `project_bootstrap_legacy_runtime` decision; those stay.

### Framework i18n translation catalogs: dropped

The framework ships **no** `.pot`/`.po`/`.mo` files of its own. User-facing framework strings currently live in bootstrap, settings, and utilities under their own `wp-framework-*` domains, including the WPCron synthetic-schedule label.

Those strings stay translatable — but under the **consumer plugin's** text domain, via the build-time php-scoper textdomain rewrite (the `wp-framework-*` → consumer-domain patcher), not a framework-owned catalog. See the "Framework i18n: consumer-domain via scope-time textdomain rewrite" block above for the mechanism. Catalog-drop and consumer-domain-rewrite are the two halves of one story: the framework owns no catalog; the consumer's catalog covers the rewritten strings.

### Settings backends: ACF backend dropped

The framework ships no ACF settings backend. `WordPressSettingsBackend` and `WooCommerceSettingsBackend` are built; MetaBox stays on-demand. ACF returns only if a revived plugin actually needs it, and then as a final backend against the current settings contract.

### Multisite / network-scoped settings storage: out of scope

Settings and storage are single-site: `OptionsStore` and the settings backends read and write plain `get_option`/`update_option` rows, with no `get_blog_option`/`get_network_option` routing. Zero consumer demand; revisit only with a real multisite consumer.

### Shortcodes + templating services: dropped

The framework ships no `utilities/Shortcodes/` or `utilities/Templating/` service.

A revived **QR-WC** registers shortcodes via direct `add_shortcode()` — no service indirection — and, if its frontend templating is real, gets a plugin-local template loader, promoted to a framework topic folder only at cross-plugin volume per the discover-on-demand helpers rule. Do not resurrect the dropped service shape.

### Caching: lean `utilities/Caching/`

Caching is built ahead under the demand-certain rule.

Final classes, no facade: `TransientCache` (`get`/`set`/`delete`/`remember()`, per-plugin key prefix, versioned-group invalidation) and `ObjectCache` (a wrapper over WP's object cache with false-safe reads + versioned-group invalidation).

### Permissions-as-capabilities: in each plugin's Installer

The framework has no permissions framework. Capability grants live in each plugin's concrete `InstallerInterface` implementation: **grant on `install()` and `update()`, revoke on `uninstall()`** — not on deactivate, since capabilities persist across a deactivate/reactivate cycle, matching WP norms. The shared caps-diff helper is `utilities/Permissions/CapabilityRegistrar`: it grants on install, reconciles on update (diffing against the prior version's map, so a capability moved between roles is cleaned up), and revokes on uninstall, idempotently; a plugin's installer drives it. The worked recipe lives in the project vault.

### Install / update: silent versioned migrations, notice on failure only

Migrations are **silent**: the kernel orchestrates install/update on boot, persists only on success, retries on the next boot after a failure, rejects stored-version downgrades by stopping the boot without running `update()`, and surfaces a notice **only when a migration fails or a downgrade guard blocks the boot**.

There is no framework `InstallationManager`. The kernel (core) owns orchestration; each plugin's concrete `Installer` owns persistence (`{slug}_version` via the `storage` package's `OptionsStore`) and the failure-notice UX (a consumer-injected PSR-3 logger whose error handler queues a persistent `AdminNotice`; pair it with diagnostic sinks through `CompositeLogger`). `update()`'s migration steps MUST be idempotent — silent retry-on-boot depends on it. The worked recipe lives in the project vault alongside the permissions recipe (one `Installer` does both).

### Concrete generic exceptions: deferred to first consumer

`shared/Exception/` holds the interface + three abstract bases only and adds concrete exceptions back on demand. Add `final NotFoundException` and `final NotSupportedException` (each extending the fitting abstract) at the first consumer that throws them. Do not add speculative generic concretes. Once concretes mix with the abstracts, they nest in a plural `shared/Exception/Exceptions/` bag per the singular-concept / plural-bag rule.

### WooCommerce package: version conditionals + `WC_Logger` PSR-3 adapter

Both are thin finals in the woocommerce package:

- **WC version conditionals** — `Shared\Version\Version` covers version-compare mechanics, but WC's plugin-version and **db-version** checks (the latter has no analogue anywhere in v2) become `ConditionalInterface` implementations in the woocommerce package, mirroring the `utilities/Conditionals/Dependencies/` shape.
- **`WC_Logger` PSR-3 bridge** — a thin `final` adapter implementing `Psr\Log\LoggerInterface` over WC's `WC_Logger`, so framework/plugin code logs through the standard PSR-3 surface and the kernel's optional logger can route to WC's log viewer.

Both are built ahead under the demand-certain rule.

### wp-core-calls tooling: archived, not active

There is no active `wp-core-calls.json` manifest tooling. The only implementation lives in `Archive/Tools/wordpress-configs`; active `wordpress-configs` does not carry it. Re-enable in `wordpress-configs` only if the manifest proves wanted during migration.

### WooCommerce settings backend: WC owns render+save, per-page distinct subclass + static map

The WC half of the settings stack has three classes, mirroring the settings package's pure-core / WP-coupled-shell split:

- **`WCSettingsBuilder`** (pure, WP-free, Infection-covered) — translates a `SettingsPage` descriptor into WooCommerce's settings-array shape: each section → a `title`/`sectionend` group (the page's first editable section renders on WC's default section; each later section is its own native WC sub-tab via `get_own_sections()`), each field → `{id: {slug}_{field}, type, title, default, options?, custom_attributes?}`. Faithful to WC's own render/save expectations (verified against `includes/admin/class-wc-admin-settings.php` + `includes/admin/settings/class-wc-settings-page.php`): a checkbox boolean default → `'yes'`/`'no'` (WC's `checked()` string-compares against `'yes'`); option labels stringified (`is_scalar()?(string):''`, since WC `esc_html()`s them); multiselect defaults stringified (WC's strict `in_array( (string)$key, …, true )`); choice fields always emit an options array (WC iterates it unconditionally); attributes filtered to the `FieldRenderer` allow-list (WC `esc_attr()`s but does not reject `on*` handlers). Field-type tokens pass through unchanged — the framework taxonomy is a verbatim subset of WC's `$types`, so the §8 mapping is identity (no mapper class).
- **`DescriptorBackedWCSettingsPage`** (abstract, `extends \WC_Settings_Page`) — a consumer declares one empty `final` subclass per page; a `static array<class-string, SettingsPage>` map holds descriptors, keyed by the concrete subclass. **A distinct subclass per page is required**: WooCommerce rebuilds settings-page objects each request and recovers them by class name, so the descriptor must be recoverable from a distinct class-string — two plugins ⇒ two subclasses ⇒ no map collision. Allowed abstract base (WC's API forces subclassing — per the "abstract base when pattern intrinsically requires" decision). The constructor sets the WC tab id (`location ?? slug`) and label before `parent::__construct()` wires WC's hooks; an unbound instantiation throws `UnboundSettingsPageException`. Non-default descriptor section ids are keyed and matched through `sanitize_title()`, because WooCommerce round-trips section request values through that normalization before rendering and saving a sub-tab.
- **`WooCommerceSettingsBackend`** (`implements SettingsBackendInterface`) — `__construct( class-string<DescriptorBackedWCSettingsPage> $page_class )` binds the per-page subclass (the locked `register_page(SettingsPage)` signature can't carry it). `register_page()` adds the `woocommerce_get_settings_pages` filter and bridges each field's descriptor `sanitize` onto `woocommerce_admin_settings_sanitize_option_{id}`. `get/set/has/delete` address each field by its own prefixed `wp_options` row (`{slug}_{field}` — WC-native, REST-correct; **not** `OptionsStore`, not a grouped array), `has()` using a sentinel to distinguish a stored value from an absent option; `option_keys( SettingsPage )` — a `SettingsBackendInterface` method — enumerates those per-field rows from the descriptor alone for the consumer's uninstall cleanup, as the WordPress backend enumerates its per-section `{slug}-{section_id}` rows.

**Binding is deferred into the filter callback.** WooCommerce's autoloader does **not** resolve `WC_Settings_Page` (`WC_Autoloader::autoload` maps `wc_settings_page` to `includes/class-wc-settings-page.php`, but the file is at `includes/admin/settings/`); only `WC_Admin_Settings::get_settings_pages()` includes it, just before applying the filter. So `register_page()` must not touch the page subclass at `plugins_loaded` — it would fatal on the missing parent. `bind()` + `new $page_class()` therefore run inside the `woocommerce_get_settings_pages` callback.

**`validate` not bridged; boolean `false` not stored.** WC's save path has no per-field validate seam (only the sanitize filter), so the descriptor `validate` closure stays a WordPress-backend concern. Values round-trip as WooCommerce stores them (an off checkbox is `'no'`), never the boolean `false` WordPress cannot keep distinct from an absent option. WooCommerce's own multiselect form-save `array_filter`s the submitted set, so a multiselect option keyed by a falsy value (`'0'`/`''`) is dropped on save — keep multiselect option keys truthy. (The backend's own `set()`/`get()` round-trip `'0'` correctly; this is WC's form-save behavior, delegated to per "WC owns save".)

**Per-field capability enforced.** `SettingsField::$capability` (distinct from the *page* cap, which WC fixes to `manage_woocommerce`) is a primitive-capability gate honored like the WordPress backend: a field the current user lacks the capability for is dropped before WooCommerce renders it (so it is neither shown nor saved), and its per-option sanitize filter preserves the stored value against a tampered save. Radio/multiselect option-membership and unknown-type validation stay with WC's save path; the descriptor `sanitize` closure is the consumer's seam for stricter rules.

**deptrac:** `WooCommerce: [Core, Shared, Settings]` — Settings for the contract + descriptors + reused exceptions; Shared for `UnboundSettingsPageException`'s base. No `Storage` edge (WC-native options, not `OptionsStore`).

### WC product-data settings tab: final store + reused descriptors, native render, framework-owned save, dual default-injection

`woocommerce/src/ProductData/` (namespace `…\WooCommerce\ProductData`) holds a final engine + descriptors for product-data panels:

- **`ProductDataTab`** (`final readonly`) — descriptor: `slug`, `label`, `meta_key_prefix`, `sections` (reusing `Settings\…\SettingsSection`, whose fields are `SettingsField`), `classes` (`list<string>|Closure`), `priority` (65), an optional `supports_product` gate, and a `custom_renderers` type→closure registry. An invalid slug or WordPress-global meta key prefix throws `InvalidProductDataTabException`.
- **`ProductDataFieldRenderer`** (`final`) — pure `args()` maps a `SettingsField` to the `woocommerce_wp_*` arg array; `render()` dispatches to the native control. WC renders its own (the settings `FieldRenderer` docblock says so), so the tab renders with the product panel's markup — NOT `FieldRenderer`. A checkbox value normalizes to WC's `yes`/`no`; multiselect gains `[]`/`multiple`; `on*`/malformed attributes are filtered (the allow-list `WCSettingsBuilder` enforces).
- **`ProductDataFieldStore`** (`final`) — the engine; one store drives one tab. `register_tab()` wires the three product hooks (tabs/panels/`process_product_meta`) plus the two default filters; field-addressed `get/set/has/delete`; `meta_keys()` for uninstall.

**Dual default-injection is the must-not-drop behavior:** the store registers BOTH `default_post_metadata` (covers `get_post_meta`, honors `$single`, and returns one default row for non-single reads) and the dynamic `woocommerce_data_store_wp_post_read_meta` (`meta_type='post'`; splices a synthetic `meta_id=0` row into `WC_Product`'s bulk read), so a product predating a field renders its descriptor default instead of a blank. The value comes from `SettingsField::$default` (a checkbox default normalized to `yes`/`no`); gated on **field membership / missing owned key (cheap) before product support (costly)** and on field existence, not truthiness. **Injection is a read concern, NOT capability-gated**; render + save are.

**Save is framework-owned**, unlike the WC settings page (where `WC_Admin_Settings` saves): WooCommerce verifies the product-edit nonce + `edit_post` before `woocommerce_process_product_meta` fires, so the store re-checks neither — but it DOES gate each field on `SettingsField::$capability`. Taxonomy fields process through the reused `FieldProcessor`; a checkbox stores `yes`/`no` (its submit convention); a non-taxonomy "custom" field renders via `custom_renderers[type]` and saves via its own `sanitize`. Every editable field is written and the product saved once, so a field left at its default holds a real value after the first save.

**CRUD semantics:** `has()` reports a *real* stored value (`metadata_exists`, excluding the injected default); `get()` returns the effective value (stored, or the injected default while none is stored). Meta key = `{meta_key_prefix}{section_id}_{field_id}`, or a per-field `SettingsField::$meta_key` override for byte-exact legacy keys. A duplicate resolved meta key throws `DuplicateSettingsFieldException`. **Uninstall stays the consumer's `InstallerInterface` concern** — the store owns no `uninstall()`; `meta_keys()` exposes the exact key set.

**Cross-package surface:** `SettingsField` carries an optional `description`, rendered by the WP-backed `FieldRenderer` too (WP options page + object meta box). `desc_tip` stays WC-only (defaulted true in the product renderer). `php-stubs/woocommerce-stubs` is in the woocommerce package's php-scoper `scoping-stubs` (the product-data + WC settings code reference WC symbols a consumer's scoper must leave external).

**deptrac:** `WooCommerce: [Core, Shared, Settings]` covers the reuse of `SettingsField`/`SettingsSection`/`FieldProcessor`/`FieldType`/`OptionsResolver` + the settings exceptions; `Shared` for `InvalidProductDataTabException`'s base. No abstract base (the "intrinsically requires" exception does not fire — a tab registers via filters, with no WC class to subclass) and no new interface (single impl).

### Component grammar — interface placement

One rule replaces three improvised conventions. An interface lives at the ROOT of the concept folder it names; concrete variants live in plural sub-bags beneath it (`Handlers/`, `Stores/`, `Backends/`, `ValueObjects/`, `Exceptions/`). A package whose ENTIRE content is a single concept keeps its contract flat at the package `src/` root — the package boundary IS the concept boundary (e.g. `storage`: `KeyValueStoreInterface` beside its stores). The locked core entrypoint pair (`PluginInterface` / `PluginKernel`) is the one explicit flat exception; do not generalize it. There is NO generic `Contracts/` subfolder — an interface is the concept's root type, not a nested artifact. Placement test for a new interface: does its concept share the package with other concepts? → concept-folder root. Is the package a single concept (or the locked entrypoint)? → package root. Applied: `HookHandlerInterface` sits at `Hooks/HookHandlerInterface`, with its implementations in `Hooks/Handlers/`. (`settings`' `Schema` concept is organized into `Field/`, `Options/`, and `Aggregation/` concept folders — the field-type/render/process classes, the options resolver + provider, and the field aggregator + provider respectively — with `ValueObjects/`/`Exceptions/`/`Errors/` as its plural bags.)

### Component grammar — value object vs descriptor

`ValueObjects/` folders hold immutable carriers of two kinds; the folder name is kept, and the kinds are distinguished by base class + docblock, not by a marker interface. **Value object** — extends `Shared\ValueObject\AbstractValueObject`; use when the carrier is SEMANTICALLY a value (identity-free, immutable, interchangeable when attribute-equal) AND every public property participates safely in structural equality across its whole type graph — no closures/callables, no runtime providers, no WordPress/WooCommerce objects, no mixed/untyped-array holes. Whether `equals()` / `jsonSerialize()` is currently consumed is NOT a criterion. Applied: `Version`, `PluginHeader`, `AdminNotice`, `FieldProcessingError`, `MetaBoxPlacement`; docblocks open "Value object …". **Descriptor** — a bare `final readonly` class with no base; the default immutable carrier, and REQUIRED when it holds closures/callables, runtime providers, WordPress/WooCommerce objects, or untyped-array holes (`SettingsField`, `SettingsPage`, `SettingsSection`, `ProductDataTab`, `DependencyRequirement` — it carries a `ConditionalInterface`; `SchedulingError` — its context array is untyped); its docblock opens "Descriptor for …". A marker `DescriptorInterface` is deferred until real code must typehint a polymorphic descriptor collection — a taxonomy-only marker is ceremony (service interfaces only on plurality).

**Invalidity exceptions follow the same split.** A value object's invalidity exception extends `Shared\ValueObject\Exceptions\InvalidValueObjectException` (the VO-exception-family base), supplying its `value_object_type` — e.g. `InvalidVersionException` for `Version`, `InvalidAdminNoticeException` for `AdminNotice`. A descriptor's invalidity exception extends the generic `Abstract{InvalidArgument,Runtime}Exception` base directly (it is not a value object); the choice between the two follows the failure's nature — `AbstractInvalidArgumentException` when it validates a malformed, caller-supplied descriptor argument (e.g. `InvalidSettingsField`/`Section`/`Page`/`CustomFieldType`, the MetaField `InvalidFieldGroup`/`InvalidTermFieldGroup`, `InvalidDependencyRequirementException`, and `InvalidProductDataTabException` — the last also covering an incomplete custom-field wiring caught at tab registration), `AbstractRuntimeException` for a runtime fault (`Duplicate…`, `Unknown…`, `Unsupported…`, `Unbound…`, options-resolution). The base is the one sanctioned abstract-exception family (see the abstract-base-classes block).

### DeprecatedHooksDispatcher: speculative-by-choice

`utilities/Hooks/DeprecatedHooksDispatcher` fires a current hook plus a deprecated legacy alias in lock-step via WP's `do_action_deprecated()` / `apply_filters_deprecated()`. It is one of the two deliberate build-aheads in the framework (the other is `utilities/Logging/RedactingLogger`, the `<sensitive>…</sensitive>`-stripping PSR-3 decorator): a plugin that renames a public hook is the obvious consumer, and each primitive is self-contained and tested.

### Zero-consumer affordances: rendering markers + placeholder aggregators stay

`core/src/Rendering/` markers stay: `RenderableInterface` is the return-markup WordPress-callback shape, `OutputtableInterface` the echo shape — a deliberate zero-consumer affordance whose public docs stay clean. The `core` and `storage` placeholder package-root `functions.php` files also stay (aggregator-pattern consistency across all packages; their docblocks describe the role truthfully). Do not relitigate either.

### Error/ErrorInterface: kept distinct, not folded into Result/

`shared/Error/ErrorInterface` stays distinct from `Result/`. It is load-bearing beyond `Result`: `utilities/Scheduling/Errors/SchedulingError` implements it, it is the `TError` bound across `AbstractResult` / `Failure`, and it is the deliberate counterpart to `Exception/ExceptionInterface` — errors are expected-failure-as-data carried by `Failure`; exceptions are unexpected and propagate via throw/catch. Folding it into `Result/` would couple a backend-agnostic error marker to the Result carrier and collapse that semantic boundary.

### Result idiom: failure-as-data for latent, enumerable failures; void or throw elsewhere

`shared/Result/` (the `Success`/`Failure` sealed pair over an `ErrorInterface` payload) carries an EXPECTED, caller-actionable failure as data — the error-as-data half of the split the `Error/ErrorInterface` ↔ `Exception/ExceptionInterface` docblocks draw. A fallible operation returns a `Result` rather than `void`/`throw` only at that deliberate boundary.

An operation returns `Result<TValue, TError>` only when BOTH hold: its failure is an expected outcome — not a programmer error, not an infrastructure outage — AND the caller is expected to branch on which failure occurred. The reference consumer is `utilities/Scheduling`: a schedule request can fail because Action Scheduler is not loaded, the interval is non-positive, the group is unsupported, or the backend rejected it — each an enumerable `SchedulingErrorReason` a caller acts on, and the scheduled work is latent (it runs on a later request), so a dropped failure silently means "never scheduled." Expected, enumerable, and caller-actionable is the test — latency, as here, only sharpens the cost of dropping the failure.

Everything else stays `void`/`throw`. Fire-and-forget persistence and cache writes — `KeyValueStoreInterface::set`, the settings backends' `set`, the meta/order field stores' `set`, the cache writes — return `void`: a failed `update_option`/`wp_cache_set` is an environment fault, not a domain branch the caller selects a recovery for, and the next request re-reconciles; threading a `Result` through every store write would be ceremony. A misuse fixable only in code throws an exception, never a `Failure`.

Enforcement: each public operation that returns a `Result` carries `#[\NoDiscard]`, so a latent failure cannot be silently dropped — dropping a `Failure` from a fire-and-forget-looking call is the exact silent-unscheduled-job bug the idiom exists to prevent. `void` writes need no guard, and the internal result-mapping helpers, always consumed via `return`, are not caller-facing discard points. (`#[\NoDiscard]` is not inherited in PHP, so it sits on each concrete dispatched method; the interface declaration also carries it as the contract.)

`utilities/Scheduling` and `settings`' `FieldProcessor` are the `Result` consumers today — the operations whose failures are expected, enumerable, and caller-actionable. Scheduling's are latent (a dropped `Failure` means a job is never scheduled); the field processor is the synchronous validating-domain-service case the rule anticipates — its `process_or_reject()` returns a `Failure<FieldProcessingError>` carrying the enumerable `FieldProcessingErrorReason` for the save path to branch on (its sibling `process()` instead folds a rejection to a fallback value), while the settings backend's `set` write stays `void`. A new store/cache/settings *write* still does not adopt `Result`.

### Mutation testing: strict-Unit + non-strict-Integration profiles

Two Infection profiles. The default (`composer test:mutation`, `infection.json`) mutates the Unit-covered code from the strict root PHPUnit config. A second (`composer test:mutation:integration`, `infection.integration.json`) mutates the Integration-covered code (the WC/settings backends, object-field/order/product stores, storage, utilities) inside the wp-env `cli` container.

The Integration profile points at a **non-strict** PHPUnit config (`tests/mutation/phpunit.dist.xml`, the coverage-metadata strictness pair off): integration tests boot WP and traverse broad core stacks, so under coverage they "execute undeclared code" en masse; Infection mutates from real line coverage, not Covers/Uses metadata, and a risky-flagged test counted as a kill would inflate MSI. Only the coverage-metadata strictness pair is relaxed (`requireCoverageMetadata` and `beStrictAboutCoverageMetadata` both off) — `failOnRisky` and `failOnWarning` stay true, and the canonical `composer test:integration` (root config) stays strict + fail-on-risky. `Backend/DescriptorBackedWCSettingsPage` is excluded from this profile: it `extends \WC_Settings_Page`, which WC's autoloader does not resolve, so Infection's static analysis (WP not booted) fatals on the unresolved parent; the exclude drops it from mutation scoring only, not its integration tests (it is the sole `extends \WC_*`/`\WP_*` class in src).

Per-profile `minCoveredMsi` floors guard regressions. The coverage driver in the `cli` container is the docker-official PHP's pcov (the Alpine system PHP's `php85-pecl-pcov` targets the wrong binary); `test:mutation:integration` preflights for a driver and names the fix. Both profiles run in the weekly tests-mutation workflow; the Integration job rides reusable-phpunit's `wp-env-xdebug: coverage` input (pcov stays the local-dev route per the preflight).

### Static-analysis stubs: WP 7.0 via inline alias

PHPStan analyses against `php-stubs/wordpress-stubs` 7.0.0, pulled via a Composer inline alias (`"php-stubs/wordpress-stubs": "7.0.0 as 6.9999.0"` in the root `composer.json` require-dev) because `php-stubs/woocommerce-stubs` caps `wordpress-stubs` below 7.0. An inline alias requires an exact left-hand version, so this **hard-pins** wordpress-stubs to exactly 7.0.0 — it will not float to 7.0.x/7.x. Maintenance: (a) drop the alias for a plain `^7.0` once `woocommerce-stubs` widens its cap to include `^7.0`; (b) until then, bump the `7.0.0` string on each new WP 7.x stubs release. `wp-plugin/woocommerce` + `woocommerce-stubs` are pinned to `^10.9`. Per-package `phpstan.neon` configs enforce the package boundaries; there is no root `phpstan.dist.neon`, so a bare or IDE PHPStan run fails loudly rather than passing under a lenient global set.

### OrderData / ProductData naming: per-domain meta surfaces, kept

The woocommerce package groups its field machinery under `OrderData/` (`OrderFieldStore` + `OrderMetaRepository`) and `ProductData/` (`ProductDataTab` + `ProductDataFieldRenderer` + `ProductDataFieldStore`). The folder names denote the WooCommerce domain object whose meta the folder manages — the order vs. the product — not a layer; they are the concept folders for two distinct meta surfaces with different WC integration points (HPOS order screens vs. product-data panels). Kept as-is: the `Store`/`Repository`/`Tab`/`Renderer` suffixes carry the role within each.

### MetaField: object-meta repositories, shared form engine, and surface stores

`settings/src/MetaField/` holds the shared object-meta field machinery: `ObjectMetaRepositoryInterface`, the WordPress core metadata-backed `MetadataRepository`, and `ObjectFieldForm`, the shared render/save engine for object-field surfaces. The settings package provides `PostMetaFieldStore`, `TermFieldStore`, and `UserProfileFieldStore` over post, term, and user meta; the woocommerce package provides `OrderData/OrderFieldStore` over order meta and `ProductData/ProductDataFieldStore` for product-data panels. The four group-registering stores also expose descriptor-addressed CRUD — `get`/`set`/`has`/`delete( FieldGroup, object id, field id )` over the same storage keys the form path resolves (single-sourced in `ObjectFieldForm::meta_key_of()`) and its store-or-revoke semantics (checkbox canonicalization, revoke-on-empty); a CRUD write is programmatic — the descriptor's sanitize/validate seam applies to form submissions only, and on the form path the processed value is stored verbatim (validation is the final transformation before persistence) — plus `meta_keys( FieldGroup )` for the consumer's uninstall cleanup, evaluated through the group's fields provider at object id 0; reads never fall back to the field default.

Object-field save semantics are revoke-based. An absent or empty submission deletes the meta key; a present-but-invalid submission preserves the prior value. A present checkbox submission is normalized to the canonical `yes`/`no` string by the field processor before its sanitize/validate seam runs; the processed value — a custom sanitizer's output included — is what gets stored. Built-in semantic field types are sanitized by default through `wordpress_field_type_sanitizers()`. `TermFieldStore` covers both native term surfaces: `{taxonomy}_add_form_fields` / `created_{taxonomy}` and `{taxonomy}_edit_form_fields` / `edited_{taxonomy}`.

### Settings REST exposure: section-level opt-in, type-only schema

The WordPress settings backend registers one grouped option per section with `register_setting`. REST exposure is section-level: every field in the section must set `SettingsField::$show_in_rest`, and REST writes replace the whole section row. The backend rejects partial-section exposure because grouped storage cannot expose or write only part of the row safely.

The generated REST schema is **type-only**: a choice field is typed by its value (string/integer), not constrained to an `enum` of its current options. Option sets can be dynamic (a `Closure`/provider resolved per request), so baking them into a static REST schema would rot or wrongly reject a valid runtime option; membership validation stays the descriptor's `sanitize`/`validate` seam. The WooCommerce backend leaves REST to WC-native option storage.

### Identifier validation: single-sourced free functions

A user-supplied identifier reused as a storage key, form-field-name segment, nonce key, or DOM id is validated against a single-sourced charset free function at every construction site, so a malformed id fails at wiring time rather than at use. `settings` uses `Schema\is_valid_identifier`; WordPress-global prefixes/keys use `Schema\is_valid_global_name_prefix` (optional leading underscore, then lowercase `a-z`, digits, `_`, `-`) where the settings/WooCommerce packages derive global names, including `ProductDataTab::$meta_key_prefix`; `utilities` mirrors the same semantics in its package-root `Utilities\is_valid_global_name_prefix` (throwing `Exceptions\InvalidGlobalNamePrefixException`), applied at the `TransientCache` ctor (key prefix, which also feeds the generation option key), the `ObjectCache` ctor (group + generation option key), and the `AdminNoticesService` ctor (the dismiss action feeding the `wp_ajax_` hook name); `utilities` also uses `AdminNotices\is_valid_notice_id` (lowercase `a-z`, digits, `_`, `-` — matching `sanitize_key`'s retained set, so a passing id round-trips WP's `data-dismissible` dismissal key unchanged). An `AdminNotice` id is validated at the `AdminNotice` ctor, the `AdminNoticeLogger` ctor (so a bad id throws at logger construction, before the fail-closed kernel-boot installer path that calls the logger), and explicit `DependencyRequirement` ids; a derived `dep_…` id is provably within the charset by construction, so it never throws.

### composer-require-checker: declared-dependency completeness, the third boundary axis

`composer lint:php` runs a third dependency-boundary check beside PHPStan (symbol existence under the loaded stubs) and deptrac (internal package edges): `maglnet/composer-require-checker` verifies every package declares each Composer-dependency symbol it uses, catching a package that reaches a transitively-installed dependency without a direct `require`.

The packages install only through the monorepo root vendor (they are path repositories there), so `bin/composer-require-check.php` checks each one against that root vendor: it points `packages/<pkg>/vendor` at the root vendor for the duration of the check, derives the WordPress/WooCommerce stub files to treat as host symbols from the package's own `extra.scoping-stubs` (the source the scoper already reads), and layers the `composer-require-checker.json` allow-list on top. `scan-files` feeds a scanned stub into BOTH the defined and the used symbol sets, so the comprehensive WordPress/WooCommerce stubs surface their own external references (PHP-extension classes, WordPress runtime constants, PSR contracts, WooCommerce internals); the allow-list clears exactly those host symbols. `utilities` keeps its `php-stubs/woocommerce-stubs:woocommerce-packages-stubs.php` declaration for exactly this host-symbol derivation (its `as_*` calls); the *scoping exclusion* for `as_*` does not depend on it — wordpress-configs ships and self-declares its own Action Scheduler catalog, so the exclusion survives a consumer dropping the WooCommerce stubs.

**No framework symbol is ever whitelisted** — a framework symbol used across a package boundary must resolve through a declared `require`, not the allow-list, and `psr/log` / `psr/container` are declared requires deliberately absent from the allow-list so an undeclared use of either still fails. Two framework free functions are therefore made resolvable for static analysis rather than whitelisted: a same-namespace free-function call carries the `namespace\` prefix (a bare call is recorded by the analyzer as a global symbol and never matches the namespaced definition — `shared`'s `convert_to_primitives` calls `namespace\get_public_property_names`); and `bootstrap`, the only functions-only package (no psr-4, so its `src/` is otherwise unscanned), declares a `classmap` for `src/` so its public functions are discoverable through its own autoload. The `files` aggregator still loads them at runtime, and a class-free `src/` yields an empty class map, so the `classmap` is inert at runtime — it exists purely to expose the functions to the analyzer. Keep `bootstrap/src` class-free: a class added there is already loaded by the aggregator's `require_once`, so its classmap entry would be a redundant, confusing duplicate.

`Psr\Http\*` and `Psr\SimpleCache\CacheInterface` are the only allow-list entries that are PHP-FIG standard contracts mapping to a `psr/*` package a framework package would declare if it adopted them (the WordPress / WooCommerce / Action Scheduler symbols are runtime-host symbols, never a framework `require`); they are present solely because the WordPress stub references them, and the framework uses neither. If a package ever genuinely depends on a PSR-18 HTTP client or PSR-16 cache, drop the matching entry and declare the package so the check stays honest.

### CI: PHP checks via the reusable composer-script-matrix workflow

The `Quality` workflow runs every PHP check — phpcs, phpstan, deptrac, composer-require-checker, and changelog validation — as a single `lint-php` job that calls `wordpress-configs`' `reusable-php-lint.yml`, which fans a caller-supplied JSON list of composer scripts into a parallel matrix in a standard PHP environment. Each check's command lives in this repo's `composer.json` (`lint:php:*` + `changelog:validate`), single-sourced between local dev and CI; the reusable workflow owns only the environment (checkout, PHP, composer install).

This shape is load-bearing for PHPStan: it runs seven per-package configs that each isolate one package's dependency/stub surface (only `woocommerce` and `utilities` scan the WooCommerce stubs — see the static-analysis-stubs decision), and a single `phpstan analyse -c <one-config>` run cannot reproduce that isolation, so `composer lint:php:phpstan` (the seven-run sweep) is the unit the workflow invokes. Delegating to consumer composer scripts is the de-facto standard among the configs reusable workflows; policy-enforcing workflows (supply-chain audit, schema conformance, release) keep their command hardcoded so a consumer cannot weaken them.

A reusable workflow was chosen over a composite action deliberately: a composite action cannot declare a `strategy.matrix` (a job-level key), so it would push a matrix-job wrapper into every consumer; the reusable workflow owns the matrix and consumers pass only their script list.
