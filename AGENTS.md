# wordpress-framework

DWS v2 WordPress framework — monorepo for 7 packages (bootstrap, shared, storage, core, utilities, settings, woocommerce). Auto-splits to `wp-framework-*` mirrors via `.github/workflows/split-packages.yml` (splitsh-lite v1.0.1).

## Status

All seven packages are implemented. `shared` (Error / Exception / Result / ValueObject / Version scaffolding + Reflection helpers), `storage` (KeyValueStore + Memory / Options / UserMeta backends), `core` (kernel + feature / lifecycle / rendering / installer contracts), `utilities` (Hooks + AdminNotices + Conditionals + Caching + Scheduling + Permissions), and `settings` (descriptors + aggregator + WordPress options & object-field backends + REST) sit on `bootstrap`. `woocommerce` carries the settings backend (`WooCommerceSettingsBackend` + `DescriptorBackedWCSettingsPage` + `WCSettingsBuilder`), the **product-data settings tab** (`ProductData/`), the **order-data field store** (`OrderData/`), WooCommerce version / db-version conditionals (`Conditionals/`), and a `WC_Logger` PSR-3 logger (`Logging/`).

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
│   │       ├── ValueObjects/PluginHeader.php
│   │       ├── Feature/                     # FeatureInterface (static get_conditional_classes + get_component_classes) + Exceptions/
│   │       ├── Composite/                   # CompositeComponentInterface (static get_child_component_classes; kernel-dispatched subtree)
│   │       ├── Conditional/ConditionalInterface.php  # pre-resolution gate (is_met)
│   │       ├── Enabled/EnabledInterface.php          # post-resolution gate (is_enabled; collapsed from rev-1 Active+Disabled)
│   │       ├── Lifecycle/                   # kernel-dispatched markers: Hookable/, Initializable/
│   │       ├── Rendering/                   # self-dispatched markers: Renderable/, Outputtable/
│   │       └── Installer/                   # install/update/activate/deactivate/uninstall + version I/O (get/set stored, get current)
│   ├── utilities/          # ahegyes/wp-framework-utilities (Hooks + AdminNotices + Conditionals + Caching + Scheduling + Permissions)
│   ├── settings/           # ahegyes/wp-framework-settings (declarative settings: descriptors + aggregator + WP options & object-field backends; depends on storage + shared)
│   └── woocommerce/        # ahegyes/wp-framework-woocommerce (Backend/ settings stack + ProductData/ + OrderData/ + Conditionals/ + Logging/)
├── tests/Fixtures/consumer-smoke/  # plugin-template-shaped scoping smoke fixture
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

`composer lint:php` aggregates PHPCS + PHPStan + **deptrac** (architecture-rule check via `deptrac.yaml`). Cache lives at `tests/.cache/deptrac/`.

wp-env runs on **port 8801** (per workspace port scheme — see `feedback_wp_env_port_scheme` memory).

Per-package install isn't supported during dev: cross-package requires (e.g., core's `ahegyes/wp-framework-bootstrap`) only resolve via the root's path-repo declarations; per-package `require-dev: ahegyes/wordpress-configs` is fetchable only via the root's VCS repo declaration.

Integration tests run in wp-env's `cli` container (the deprecated `tests-cli` sub-env was dropped per Gutenberg PR #75341 — `.wp-env.tests.json` declares `"testsEnvironment": false`).

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

### Plugin entry-point shape: interface + final + composition (Option 5, 2026-05-14)

Locked in the framework kernel design spec (2026-05-14; design history archived to the project vault) after 9 audits (v1 retro, v1 plugin verification, 2 industry surveys, PSR pattern survey, WP plugin rewrites retrospective, Jetpack history, v1 framework naming audit, v1 plugin naming audit).

Consumer plugins define a `final class Plugin implements PluginInterface` (singleton-per-plugin, matches every successful WP plugin rewrite). `PluginKernel::run( Plugin::get_instance() )` is the canonical bootstrap call. The kernel is `final` — composition only, no inheritance.

No abstract base class for Plugin. `KeyValueStoreInterface` moved from `core/` to `utilities/Storage/` to co-locate with its impls. *(Superseded 2026-06-14, Task 3.0: the Storage module — `KeyValueStoreInterface` + the Memory/Options/UserMeta stores — was extracted to the `wp-framework-storage` package, namespace `…\Storage`.)*

Rev-1 placed lifecycle interfaces in `core/src/Lifecycle/`: `HookableInterface`, `InitializableInterface`, `ActivatableInterface` (WP plugin activate/deactivate), `UninstallableInterface`, `RenderableInterface` (marker, no methods). *(Superseded — see rev-2 + as-built below: per-component `Activatable`/`Uninstallable` dropped, `activate`/`deactivate`/`uninstall` moved onto `InstallerInterface`; surviving markers are `Hookable`/`Initializable`/`Renderable`/`Outputtable`.)*

Rev-1 placed state interfaces in `core/src/State/`: `ActiveStateInterface` (user-domain "should this run") + `DisabledStateInterface` (framework-domain kill switch). *(Superseded — rev-2 collapsed both into the single `Enabled/EnabledInterface::is_enabled()`; there is no `core/src/State/`.)*

Rev-1 placed exceptions in `core/src/Lifecycle/Exceptions/` (plural bag of concretes nested under the owning concept per the singular/plural convention): `InitializationException`, `ActivationException`, `DeactivationException` (final, extend `Shared\Exception\AbstractRuntimeException`, no `Failure` infix). *(Superseded — as-built: `InitializationException` lives in `Lifecycle/Initializable/Exceptions/`; `ActivationException`/`DeactivationException` moved to `Installer/Exceptions/` alongside `InstallationException`/`UpdateException`/`UninstallationException`.)*

**Update 2026-05-16:** rev-2 redesign supersedes parts of this block — see "v2 core redesign rev 2" decision block below.

### Helpers: deferred, discover-on-demand (2026-05-14)

v1 shipped a `wordpress-framework-helpers` package (22 files, ~280 imports across all v1 plugins). v2 does NOT. No standalone helpers package, no `Helpers/` topic folder.

Rationale: closed ecosystem (no external consumers), no actual midpoint-dep-chain split in v1 history, PHP 8.5 + strict_types absorbs most v1 DataType helpers, and a comparable active WP framework (Pink-Crab Perique) keeps its `Utils/` at 2 files and externalizes pure-PHP helpers entirely.

During plugin migration: replace v1 `Helpers\*` call sites per the rule in the v1 helpers inventory (project vault, `references/`) (strict types → native PHP → native WP → inline → plugin-local final class → framework topic folder). If ≥10 cross-plugin stateless helpers accumulate without fitting an existing topic, carve out `utilities/src/Helpers/` at that point. The inventory preserves the v1 surface for cherry-picking.

### Subtree gating: composition pattern, not interface (2026-05-14)

Rev-1's plan preserved v1's `is_active_local()` subtree gating via component composition: group components (e.g., IC's `Output`, `Comments`) held their children as constructor-promoted readonly properties, and the group's `register_hooks()` iterated those children, gating each via `ActiveStateInterface`/`DisabledStateInterface` — no framework-side recursive walker, no `GroupInterface`.

In that model the kernel knew only top-level components — leaves were not registered with `PluginKernel`; they lived as constructor-injected children of their group parent, dispatched by the group's `register_hooks()`.

**Update 2026-06-13:** superseded by the kernel-branch `CompositeComponentInterface` decision below — the kernel is the sole lifecycle dispatcher, walking each composite's static `get_child_component_classes()`; group `register_hooks()` hand-dispatch is gone.

### Service interfaces: only on plurality (2026-05-09)

Service-level interfaces exist only when there are multiple implementations (current or imminent). Single-impl services are concrete `final` classes; consumers typehint the concrete class.

Applied: `HookHandlerInterface` (3 impls: Direct/Buffered/Scoped) — keep. `SettingsBackend` (3 impls planned: WP/MetaBox/WooCommerce) — add when impls exist. `HooksServiceInterface` removed (1 impl, 0 consumers — full-mirror dead weight).

Trade-off: `final class` is harder to mock without `dg/bypass-finals`. Plugin tests should use real service instances with recording handlers — see `HooksServiceTest::recording_handler()` for the pattern.

### Unified hook-registrar abstraction: deferred (2026-05-09)

v1 had `HooksAdapterInterface` — a shared contract that both `HooksService` and individual handlers implemented. Let consumers typehint "any hook registrar" without caring about routing.

v2 split the surfaces: `HookHandlerInterface` is handler-only and `callable`-native; `HooksService` carries `$handler_id` for routing. Cleaner separation but loses substitutability. Re-unifying now would require refactoring `HooksService::add_action()` — the diverged signatures aren't substitutable as-is (handler is 4-arg, service is 5-arg with `$handler_id`).

Reconsider when a concrete consumer wants substitutability — e.g., a logging/metrics decorator wrapping any registrar, or an embedded feature module typehinting "any registrar". Don't pre-add the abstraction.

### Persistent admin notices: built in Phase 2 (2026-05-09)

v1 had three notice storage backends — `MemoryStore` (per-request), `OptionsStore` (cross-request via wp_options), `UserMetaStore` (per-user, dismissal-sticky) — combined with `DismissibleNoticesHandler` for "remember dismissed forever" semantics. v2's `AdminNoticesService` queues notices in-memory; Phase 2 added the persistent `NoticeStore` (below) for cross-request and sticky-dismissal notices.

Load-bearing in v1 plugins: LO-WC and QR-WC use `DismissibleAdminNotice` + `'user-meta'` store across 5+ call sites for post-redirect-get error UX (queue notice → `wp_safe_redirect` → render on next page load → user dismisses, never sees again). Without the persistent store, the redirect drops the notice.

**Built in Phase 2** as designed: a `NoticeStore` interface + 3 backends (Memory/Options/UserMeta), an `is_persistent` flag on `AdminNotice`, and `DismissedNoticesTracker` dismissal via user_meta keyed by notice id.

### wp-framework-shared package: substrate kernel (2026-05-15)

5th framework package: `ahegyes/wp-framework-shared`. Sits as a sibling of `bootstrap` in the dep tree (both have zero internal framework deps), and is required directly by `core` / `utilities` / `settings` / `woocommerce` — not transitively through `core`.

Contents (all PHP 8.5+):
- `Error/ErrorInterface` — marker for failure payloads carried by `Result\Failure`.
- `Exception/{ExceptionInterface, AbstractException, AbstractInvalidArgumentException, AbstractRuntimeException}` — scaffolding for framework-side exception hierarchies.
- `Result/{AbstractResult, Success, Failure}` — sealed-type simulation of `Result<TValue, TError>`.
- `ValueObject/{ValueObjectInterface, AbstractValueObject}` with reflection-driven `equals()` + `jsonSerialize()` + `Exceptions/InvalidValueObjectException`.
- `Version/{Version, Exceptions/InvalidVersionException}` — a `version_compare()`-semantics value object (added with the rev-2 installer).
- `Reflection/functions.php` — `get_public_property_names()` + `convert_to_primitives()` helpers underpinning the VO base (files-autoloaded).

Patterns adapted from A8C's customer360 `shared/` layer — same semantic role (DDD Shared Kernel applied within the framework family) but repackaged as a standalone Composer package per **Common Closure Principle** (Robert C. Martin: "classes that change together belong together") and **Reuse-Release Equivalence Principle** (the granule of reuse is the granule of release). Shared primitives change for different reasons than core's Plugin/Lifecycle, and should release independently. This also lets `utilities` and `woocommerce` depend on `shared` directly without inheriting core's Plugin/Lifecycle surface when they only need a Result type.

**Runtime-WP-aware, not WP-agnostic.** The package assumes WordPress is loaded at the point of use (it always is — every consumer of shared lives inside a WP plugin context). Uses `wp_json_encode()` for safer JSON output (handles invalid UTF-8 via `_wp_json_sanity_check`, applies WP filters). What shared does NOT do: depend on WP-specific abstractions (Options API, Hooks, Posts, Settings) — those are the line between this package and `utilities`/`core`. Tests that exercise wp_* calls run in the Integration suite under wp-env.

Enforcement: deptrac `Shared: ~` ruleset (zero internal framework deps allowed); composer constraint `"php": ">=8.5"` only.

Strict scope: substrate primitives only. WP-aware helpers DO NOT belong here — if they emerge during migration, they live first in the consumer plugin's local `Helpers/` per the discover-on-demand helpers decision, promoted to `utilities/` if cross-plugin, or eventually a separate `wp-framework-wp-helpers` package only at sustained volume.

### Folder naming: singular concept / plural bag (2026-05-15)

| Form | Role | Contents | Example |
|---|---|---|---|
| **Singular** | The CONCEPT | Type definitions, interfaces, abstract bases. One canonical of each thing. | `Conditional/`, `Lifecycle/`, `Enabled/`, `Result/`, `ValueObject/`, `Error/`, `Exception/` |
| **Plural** | A BAG of concrete instances | Final classes that implement the concept. | `Installer/Exceptions/`, `ValueObject/Exceptions/`, `Hooks/Handlers/`, `Conditionals/Dependencies/` |

Verified against A8C customer360 (`shared/exception/` singular = scaffolding; `domain/core/contact-identifier/exceptions/` plural = bag).

Exception: collective-noun folders (`Conditionals/`, `Hooks/`, `AdminNotices/`) keep plural names because their natural English is plural — don't fight English; the plural folder still acts as a bag of related concrete utilities.

When a singular concept folder grows mixed contents (interface + abstract base + concrete bag), split: nest a plural subfolder under the singular concept. Example: `Installer/` (singular — the interface) + `Installer/Exceptions/` (plural, concrete exception classes).

The split `ValueObjects/` (core — plural bag of concrete VOs such as `PluginHeader`) vs `ValueObject/` (shared — singular concept: `ValueObjectInterface` + `AbstractValueObject`) is DELIBERATE, not a defect: shared owns the contract + base, core holds concrete instances.

### Abstract base classes: allowed when pattern intrinsically requires (2026-05-15)

Amends the workspace-level "interfaces + final + composition, no abstract base class hierarchies" principle. The general rule still holds: composition over inheritance, single-impl services as concrete finals, no inheritance-driven "memory tree" plugin hierarchies (the v1 anti-pattern).

Exception, narrow and verified: patterns whose value depends on abstract-base scaffolding may use abstract bases.

1. **Sealed-type simulation.** `Result<TValue, TError>` requires `abstract AbstractResult` + `final Success` + `final Failure` to fake a union type PHP doesn't natively support. Substituting pure interfaces drops `match()` ergonomics and the shared `is_success`/`is_failure` derivation.
2. **Reflection-driven shared behavior.** `AbstractValueObject::equals()` walks public properties via reflection and works for ANY subclass without each subclass reimplementing equality. Pure interface + per-class implementation forces every VO to duplicate equality logic — defeating the point of the base.
3. **Exception-family subtyping.** `InvalidValueObjectException` (`shared/ValueObject/Exceptions/`) is the abstract base each value object's invalidity exception extends (`InvalidVersionException` supplies its `value_object_type`), so the family shares one message format and a single `catch ( InvalidValueObjectException )` point. The abstract is intrinsic: exceptions dispatch by type, so a family of distinct catchable subtypes sharing message-building needs the subtype hierarchy — a lone `final` class collapses the subtypes, and composition yields no catchable type. Descriptors are not value objects, so their invalidity exceptions extend the generic `Abstract{InvalidArgument,Runtime}Exception` bases directly.

These exceptions live in `wp-framework-shared` (runtime-WP-aware substrate; the only legitimate home for abstract-base scaffolding). Consumer plugins MAY extend these abstract bases (e.g., `PluginHeader extends AbstractValueObject`); they MUST NOT introduce new abstract base hierarchies of their own without similar intrinsic-requirement justification.

Rule of thumb: if you can implement the pattern as `interface + final + composition` without losing essential semantics, do that. Abstract base is the escape hatch, not the default.

### v2 core redesign rev 2 (2026-05-16)

Locked in the v2 core-redesign design spec (2026-05-16; archived to the project vault), reconciled against committed `packages/core/src/` (the source of truth where it diverges). Supersedes the 2026-05-14 Spec A reorg and the rev-1 design.

**Primary contracts at `core/src/` root:** `PluginInterface.php` (consumer contract with `get_*`-prefixed accessors) and `PluginKernel.php` (runtime engine). Aspect VOs grouped under `ValueObjects/` (e.g., `PluginHeader`).

**Pre-resolution gating:** `Conditional/ConditionalInterface::is_met()`. Checked by the kernel BEFORE container resolution of any Feature; cheap, side-effect-free. Standard implementations live in `utilities/Conditionals/{Dependencies,Context}/`.

**Post-resolution gating:** `Enabled/EnabledInterface::is_enabled()` — single positive predicate. Collapsed from rev-1's `ActiveStateInterface` + `DisabledStateInterface` split (v1 audit: 20/21 plugin components used Active alone; 0 components implemented both locally; zero of 10 surveyed frameworks split into two interfaces; AMP / Yoast / WC / Jetpack / Laravel Pennant all use single positive predicate). Rare user+framework gating composes inline inside `is_enabled()`.

**Feature wrapper unit:** `Feature/FeatureInterface` declares `static get_conditional_classes()` (class-strings, evaluated before the Feature is constructed) and `get_component_classes()`. There is no `register_services()` hook — a rev-2-era `RegistersServicesInterface` was dropped before implementation (no v1 precedent, zero plugin usage, and it forced PHP-DI write semantics into a framework contract). All container wiring, plugin-wide and Feature-private alike, lives in the plugin's own container-definitions file; the PHP-DI write surface stays plugin-side.

**Centralized installer:** `Installer/InstallerInterface` — `install()`, `update( Version $from_version )`, `activate( bool $network_wide )`, `deactivate( bool $network_deactivating )`, `uninstall()`, plus the version I/O the kernel drives on boot: `get_current_version()`, `get_stored_version()`, `set_stored_version( Version )`. One implementation per plugin; `update()` uses the `shared/Version/Version` VO for type-safe comparison and each migration step MUST be idempotent (silent retry-on-boot depends on it). Per-component `Activatable` / `Uninstallable` interfaces dropped.

**Kernel boot sequence** (`PluginKernel::boot()`, idempotent via a `$booted` guard): run the installer version check (install/update, persisting the version only on success; any `Throwable` is logged via the optional PSR-3 logger, leaves the stored version for the next boot to retry, and stops the boot so a broken migration cannot fatal every request) → gate each Feature on its `static get_conditional_classes()` BEFORE constructing it → resolve the surviving Features → validate the declared component graph (a class reached twice — two Features, two composites, or a Feature-and-composite — throws `FeatureException`; the walk reads composites' `static get_child_component_classes()`, so it catches cycles enablement-independently) → flatten every Feature's component tree pre-order, pruning any subtree whose node is disabled (`EnabledInterface::is_enabled()`) → `initialize()` every runnable component, THEN `register_hooks()` on every runnable component. Initializing all before any registers hooks is the real guarantee: a hook callback may safely reach a peer component in another Feature. Activation/deactivation are wired separately via `PluginKernel::register_lifecycle_hooks()` at plugin-include scope (see the kernel-branch block below).

**Method-name convention:** `get_*` prefix on getter methods (`get_plugin_file`, `get_plugin_header`, `get_container`, `get_feature_classes`, `get_installer`, `get_conditional_classes`, `get_component_classes`, `get_child_component_classes` — class-string getters keep the `_classes` suffix). Predicates use `is_*` (`is_met`, `is_enabled`). Action verbs keep bare form (`register_hooks`, `initialize`, `render`, `activate`, `deactivate`).

**Cross-package additions:**
- `shared/Version/Version.php` — final readonly VO extending `AbstractValueObject` with `is_greater_than` / `is_less_than` / `is_equal_to` (all `version_compare()` semantics) / `from_string` / `from_parts`, and a `__toString()` override returning the raw version string (no separate `to_string()`). `is_equal_to()` follows `version_compare()` — numerically-equal segments match, omitted components are not padded, build metadata participates — distinct from the inherited structural `equals()`.
- `utilities/Conditionals/Dependencies/` — bag of `WPPluginActiveConditional`, `PHPExtensionLoadedConditional`, `PHPFunctionExistsConditional`, `PHPVersionConditional`, `WPVersionConditional`, `PHPIniSizeConditional`.
- `utilities/Conditionals/Context/` — `IsAdminConditional`, `IsAjaxConditional`, `IsCliConditional`, `CurrentUserCanConditional`.
- Workspace-wide package-root `functions.php` aggregator pattern — each package with namespace function files gets a single composer-autoloaded `functions.php` (sibling of `composer.json`) that `require_once`'s nested `src/<namespace>/functions.php` files. Matches the `bootstrap` package's `functions.php` location + the Symfony Polyfill ecosystem convention.

### v2 core redesign rev 2 — kernel-branch as-built (2026-06-13)

Refines the rev-2 block above with what the committed `PluginKernel` actually does; where this and the rev-2 block diverge, `packages/core/src/` is the source of truth.

**`CompositeComponentInterface` — sole-dispatcher, no escape hatch.** A composite declares its children via `static get_child_component_classes()` and never dispatches them itself; the kernel walks the declared class-strings. Static so the kernel validates the whole declared graph (duplicate + cycle) construction-free and enablement-independently — a duplicate hidden under a disabled parent is still caught. Dispatch walks the declared class-string, not the resolved instance's runtime class, so a resolved component can never reach a child the graph validation did not see. This **supersedes the 2026-05-14 "subtree gating: composition pattern" block** — group `register_hooks()` hand-dispatch is gone; the kernel is the single lifecycle dispatcher. A leaf shared by two parents (diamond) throws as a duplicate — no consumer needs it; revisit only if one does.

**Duplicate / cycle throws `FeatureException`** (not a new contract-specific type — its docblock already scopes it to boot-time contract violations).

**Component-level conditionals deliberately NOT added** (YAGNI): `ConditionalInterface` is Feature-level / pre-resolution; `EnabledInterface` is the per-component post-resolution gate. A second component-level axis has zero consumers.

**`is_enabled()` is a COARSE attach-time gate** — evaluated once at `plugins_loaded`, before the current user and other plugins' capability filters have settled. A component performing a privileged action MUST re-check the capability inside the hook callback; the enablement gate is not a substitute.

**Installer orchestration on boot lives in the kernel (core)**, not a utilities orchestrator (the migration-plan "utilities `InstallationManager`" was a phantom — an orchestrator there would violate deptrac). Version I/O routes through three `InstallerInterface` methods — `get_current_version()` / `get_stored_version()` / `set_stored_version()` — keeping `boot()` WP-free (unit-testable, deptrac-clean: core must not depend on utilities). Persistence + failure-notice UX live in the consumer's concrete installer (which may legally depend on storage for persistence and utilities for notices); failure UX rides the optional PSR-3 logger.

**Activation wiring cannot live in `boot()`.** `register_activation_hook` only schedules a callback for the activation request, which fires during the plugin include — before any `plugins_loaded`-deferred `boot()`. So `PluginKernel::register_lifecycle_hooks( $plugin )` is static and called at plugin-include scope; it wires `activate( bool $network_wide )` / `deactivate( bool $network_deactivating )` to the plugin's installer.

### utilities phase-0 land + harden (2026-06-13)

Landed the implemented-but-unreviewed `utilities` package (Hooks, AdminNotices, Storage, Conditionals) per migration-plan Tasks 0.4–0.6, treating every line as new work. Decisions, verified against `packages/utilities/src/`: *(Storage was later extracted to the `wp-framework-storage` package — 2026-06-14, Task 3.0; the Storage decisions below hold there unchanged, namespace `…\Storage`.)*

- **Storage null-correctness:** all three stores use `array_key_exists()`, not `?? $default` — a key stored as `null` returns `null`, not the default (which would contradict `has()`).
- **`UserMetaStore` per-user targeting is concrete-only.** `set/get/has/delete/get_all/clear` take a trailing optional `int $user_id = 0` (0 ⇒ current user; the anonymous guard is `< 1` after resolution). This widens the implementations but is NOT lifted to `KeyValueStoreInterface` (meaningless for Memory/Options) — callers targeting another user typehint the concrete store.
- **`HooksService::__construct( ?array $initial_handlers = null )`:** `null` ⇒ one default `DirectHookHandler`; `array()` ⇒ zero handlers. `DirectHookHandler::DEFAULT_ID = 'direct'` single-sources the handler id across HooksService's method defaults.
- **`BufferedHookHandler` removals sync WordPress** (`remove_*` / `remove_all_*` also call WP `remove_action`/`remove_filter`, not just mutate the queue) — only adds are buffered; removes are immediate. **`ScopedHookHandler::register_lifecycle()` is idempotent** — it wires stable `array($buffer, 'flush'/'reset')` callbacks (first-class-callable closures get distinct hashes WP won't de-dup, so repeats had stacked listeners).
- **Version conditionals are total.** `PHPVersionConditional` / `WPVersionConditional` compare the raw runtime version string via `version_compare()` — parsing it into a `Version` VO would throw on a dashless RC or a 4-segment `$wp_version`, and `is_met(): bool` must not throw (it would crash the fail-closed kernel boot). `Version` is kept only for the validated developer-supplied minimum. WPVersion also strips current's pre-release suffix and a trailing `.0` from a 3-part minimum (mirrors `is_wp_version_compatible()`); PHPVersion does not strip (mirrors the total `is_php_version_compatible()`).
- **`PHPIniSizeConditional`** (replacing a generic ini conditional): byte-minimum `>=` via `wp_convert_hr_to_bytes()`; `-1` (unlimited) passes; an unknown directive (`ini_get` ⇒ false) is unmet. A blanket byte-`>=` over all ini directives was wrong (corrupts boolean/string directives, mis-ranks `-1`); the generic exact-match was dropped (no consumer, YAGNI).
- **AdminNotices `render_one()` left fully as-is** — passing the raw message to core `wp_admin_notice()` is safe: core does `echo wp_kses_post( wp_get_admin_notice(...) )`, sanitizing the entire markup (the audit's "XSS" flag confused `wp_get_admin_notice()` with `wp_admin_notice()`). The pre-6.4 `function_exists` fallback is dead at the WP 7.0 floor but kept, harmless.
- **deptrac `Core_*` sublayers: SKIPPED.** The committed core is ~3 internal edges across single-interface files in one release granule; deptrac's value is at package boundaries (already enforced). Revisit only as `Core_Kernel` (sink) + `Core_Contracts` if doc-as-enforcement is wanted.

### Framework i18n: consumer-domain via scope-time textdomain rewrite (2026-06-13)

The framework ships no translation catalogs (i18n-catalogs-drop decision), but user-facing framework strings MUST stay translatable. They are translatable only under the *consumer plugin's* text domain (resolved by the consumer's catalog + WP just-in-time loading) — not a framework-owned domain, and not WP core's `'default'` (which has no entries for custom strings).

The mechanism is a build-time php-scoper patcher (`wordpress-configs` `contrib/wp-framework.inc.php`) that rewrites each framework `wp-framework-<package>` text domain → the consumer's `extra.text-domain` at scope time (v1 did this via `$dws_framework_language_domains`). The `"text-domain"` composer field in the template + every plugin feeds it; the framework's only translatable strings (the 3 in `bootstrap/src/Notice/functions.php`, domain `'wp-framework-bootstrap'`) are the patcher's find-targets. The `consumer-smoke` fixture exercises the rewrite and CI asserts zero residual `wp-framework-*` domains (and unprefixed WC symbols) in the scoped output.

Built and wired. **The end-to-end runtime path (framework string → rewritten domain → consumer catalog → WP just-in-time loading) stays unproven until a plugin ships** — verify it at the first Phase-4 migration. Do NOT scatter per-package runtime dynamic-domain reads instead — the one scope-time mechanism is the design.

### Bootstrap requirements API: explicit `check_requirements` chain, no `are_requirements_met()` (2026-05-17, promoted 2026-06-12)

Gap-analysis #13 + #26; locked as triage decision 8 (promoted from the 2026-05-17 PM session handoff, marked do-not-re-litigate). Phase 0 already removed the stale `are_requirements_met` mention from the structure tree; this records the API and its rationale.

`wp-framework-bootstrap` exposes a two-call requirements chain and ships **no** `are_requirements_met()` boolean wrapper:

- `Requirements\check_requirements( string $plugin_basename ): true|\WP_Error` — `true` when the consumer's effective PHP/WP minimums (its `Requires PHP` / `Requires at least` headers, floored by `FRAMEWORK_MIN_PHP` / `FRAMEWORK_MIN_WP`) are met, otherwise a `\WP_Error` carrying `plugin_php_incompatible` / `plugin_wp_incompatible` codes, each with `min` + `current` data.
- `Notice\output_requirements_error( string $plugin_basename, \WP_Error $error ): void` — hooks an `admin_notices` callback that renders those two codes; no-op on an empty bag. The renderer is locked to the PHP/WP codes it owns.

A boolean wrapper would collapse the `WP_Error` to a bare `false`, discarding which requirement failed and the `min`/`current` data the renderer needs. The explicit chain hands the consumer the structured error so it can branch, layer its own gates (a WooCommerce-active check, a PHP-extension probe) before bailing, and still render the framework notice. Consumer entrypoints call the chain directly — `$req = check_requirements( $basename ); if ( $req instanceof \WP_Error ) { output_requirements_error( $basename, $req ); return; }` — ahead of autoload. The `wordpress-plugin-template` + all three plugins get rewired to this shape during migration (gap #25/#30).

### v1 bootstrap-era extras: dropped (DWS defunct) (2026-06-12)

Gap-analysis #23 + matrix rows 5/6/8/64; triage decision 5. v1 carried four bootstrap-era capability families — spread across the v1 `wordpress-framework-bootstrapper`, `-foundations`, and `-core` packages — that v2 does not port:

- **Whitelabel / rebranding family** (`whitelabel.php` — `get_whitelabel_*` helpers referenced across the v1 plugin bootstraps) — DWS is defunct, so rebranding the framework's own admin output is meaningless.
- **Temp / upload-dir constants trio** (`DWS_WP_FRAMEWORK_TEMP_DIR_PATH` & siblings) — already absent from v1's own abandoned 2.0 line (they survive only as stale plugin references); consumers derive paths via WP natives per the no-directory-assumptions rule.
- **INIT-constant chain + `init_status` gating** (every v1 `bootstrap.php`; e.g. `wordpress-framework-foundations/bootstrap.php`) — replaced by the explicit `check_requirements()` chain (above) + `PluginKernel::run()`; the kernel's `$booted` guard is the only "already initialized" state v2 keeps.
- **Init-failure output helper** (archived `wordpress-framework-core`'s `dws_wp_framework_output_initialization_error()` rendering `src/templates/initialization/error.php`, guarding a late call after `admin_notices` fires with `_doing_it_wrong`) — v2 folds requirement reporting into the single fixed `output_requirements_error()` renderer: no separate init-failure template, no timing guard, no template-extension hook surface.

This drops these v1 bootstrap-era features. It does NOT touch v2's `wp-framework-bootstrap` package's PHP-5.6+/old-WP runtime-compatibility branches, which run before the version gate and are load-bearing — see the `project_bootstrap_legacy_runtime` decision; those stay.

### Framework i18n translation catalogs: dropped (2026-06-12)

Gap-analysis row 7; triage decision 5 (the DWS-defunct drop bundle, sibling to #23). The framework ships **no** `.pot`/`.po`/`.mo` files of its own. v1 loaded a per-package framework text domain at runtime in every package's `bootstrap.php` (`load_plugin_textdomain()`) and shipped catalogs (`.pot` plus translated `de_DE`/`ro_RO` for the bootstrapper); v2 drops both the catalogs and that loader machinery — a closed ecosystem (DWS defunct) with no external translators to keep them current. The framework's only user-facing strings are the three requirements-notice strings in `bootstrap/src/Notice/functions.php`.

Those strings stay translatable — but under the **consumer plugin's** text domain, via the build-time php-scoper textdomain rewrite (the `wp-framework-*` → consumer-domain patcher), not a framework-owned catalog. See the "Framework i18n: consumer-domain via scope-time textdomain rewrite" block above for the mechanism. Catalog-drop and consumer-domain-rewrite are the two halves of one story: the framework owns no catalog; the consumer's catalog covers the rewritten strings.

### Settings backends: ACF backend dropped (2026-06-12)

Gap-analysis row 43 + #24; triage decision 5. v1's `ACFSettingsAdapter` has **zero** successor (IC/LO/LPM) usage — its apparent priority came only from non-successor plugins. v2 ships no ACF settings backend. The Spec C settings stack defines `WordPressSettingsBackend` + `WooCommerceSettingsBackend` (both built); MetaBox stays a planned on-demand backend (matrix row 44). ACF returns only if a wave-2 plugin that actually used it is revived, and then as a final backend against the Spec C contract — not the v1 adapter shape.

### Shortcodes + templating services: dropped; wave-2 triggers pre-seeded (2026-06-12)

Gap-analysis rows 54/55 + #24; triage decision 5. v1's `utilities/Shortcodes/` and `utilities/Templating/` services have **zero** successor usage (their high v1 import counts came from QR-WC / PPW, both wave-2). v2 ships neither service.

Wave-2 re-entry triggers (recorded so the drop is reversible, not amnesia): a revived **QR-WC** registers shortcodes via direct `add_shortcode()` — no service indirection — and, if its frontend templating is real, gets a plugin-local template loader, promoted to a framework topic folder only at cross-plugin volume per the discover-on-demand helpers rule. Do not resurrect the v1 service shape.

### Caching: lean `utilities/Caching/` built in Phase 1, not deferred (2026-06-12)

Gap-analysis row 51 + #24; triage decision 4. The deliberate **exception** to the zero-successor drops above. v1's caching service also has zero *current* successor usage, but wave-2 (QR-WC / WR-WC) demand is certain from the archive call sites, so caching is built as a Phase-1 task rather than discover-on-demand — availability beats a later scramble when the consumer arrives (`feedback-build-ahead-when-demand-certain`).

Built (Task 1.2): final classes, no facade. `TransientCache` (`get`/`set`/`delete`/`remember()`, per-plugin key prefix, versioned-group invalidation) and `ObjectCache` (a wrapper over WP's object cache with false-safe reads + versioned-group invalidation). The earlier *object-cache-stays-WP-native* stance was reversed when the demand-certain build-ahead added the `ObjectCache` wrapper.

### Permissions-as-capabilities: in each plugin's Installer (2026-06-12)

Gap-analysis #17 + matrix row 63; triage decision 1. The #1 successor-impact framework gap (v1 `AbstractPermissions*Functionality`: recursive collection, role `add_cap`/`remove_cap`, permission versioning + caching — 28 files / 51 hits across all three successors).

v2 has no permissions framework. Capability grants live in each plugin's concrete `InstallerInterface` implementation: **grant on `install()` and `update()`, revoke on `uninstall()`** — not on deactivate, since capabilities persist across a deactivate/reactivate cycle, matching WP norms. IC's current activate-time grant gets realigned to this during its migration (Task 4.1). The shared caps-diff helper is built ahead under the demand-certain rule — `utilities/Permissions/CapabilityRegistrar` grants on install, reconciles on update (diffing against the prior version's map, so a capability moved between roles is cleaned up), and revokes on uninstall, idempotently; a plugin's installer drives it. The worked recipe (IC's capability list; the install/update/uninstall triplet) lives in the project vault.

### Install / update: silent versioned migrations, notice on failure only (2026-06-12)

Gap-analysis #18 + matrix row 61; triage decision 2. v1's `InstallationFunctionality` ran user-confirmed AJAX migrations with result notices. v2 migrations are **silent**: the kernel orchestrates install/update on boot (see the kernel-branch "Installer orchestration on boot" block — `run_installer()` compares stored vs current version, dispatches `install()`/`update( $from )`, persists only on success, retries on the next boot after a failure) and surfaces a notice **only when a migration fails**.

There is no framework `InstallationManager` — an orchestrator in `utilities` was a migration-plan phantom (it would violate deptrac Core↛Utilities). The mechanism splits cleanly: the kernel (core) owns orchestration; each plugin's concrete `Installer` owns persistence (`{slug}_version` via the `storage` package's `OptionsStore`) and the failure-notice UX (a consumer-injected PSR-3 logger whose error handler queues a persistent `AdminNotice` — recipe in the project vault). `update()`'s migration steps MUST be idempotent — silent retry-on-boot depends on it. The worked recipe lives in the project vault alongside the permissions recipe (one `Installer` does both).

### Concrete generic exceptions: deferred to first consumer (2026-06-12)

Gap-analysis #22 + matrix row 24. v1 shipped five generic concrete exceptions. v2 `shared/Exception/` holds the interface + three abstract bases only and adds concretes back on demand. Add `final NotFoundException` and `final NotSupportedException` (each extending the fitting abstract) at the first consumer that throws them: `NotSupportedException` already has surviving-code precedent (v1 `DWS_Order_Node` / `DWS_Internal_Comment` models throw it), and `NotFoundException` is the likely first call for the stores/settings backends. The other three v1 concretes — `InexistentPropertyException`, `NotImplementedException`, `ReadOnlyPropertyException` — were thrown only by v1 framework constructs that v2 drops or redesigns (the validation handlers, the dependency-state / admin-notice / `*Aware` traits, the WC validated-options abstracts, the magic-property accessors), so they have no v2 home. Once concretes mix with the abstracts they nest in a plural `shared/Exception/Exceptions/` bag per the singular-concept / plural-bag rule; until then, no speculative classes.

### WooCommerce package: version conditionals + `WC_Logger` PSR-3 adapter — built ahead (2026-06-12)

Gap-analysis #21 + matrix rows 65/66. *(Superseded: the WC settings backend landed (Spec C Task 3.2), then both helpers below were built ahead under the demand-certain rule rather than waiting for the first WC-plugin migration — they are no longer on-demand.)* Both are thin finals in the woocommerce package:

- **WC version conditionals** — `Shared\Version\Version` covers version-compare mechanics, but WC's plugin-version and **db-version** checks (the latter has no analogue anywhere in v2) become `ConditionalInterface` implementations in the woocommerce package, mirroring the `utilities/Conditionals/Dependencies/` shape.
- **`WC_Logger` PSR-3 bridge** — a thin `final` adapter implementing `Psr\Log\LoggerInterface` over WC's `WC_Logger` (v1's `WC_LoggingHandler`), so framework/plugin code logs through the standard PSR-3 surface and the kernel's optional logger can route to WC's log viewer.

Both are thin finals, built ahead under the demand-certain rule rather than waiting for the LO-WC migration.

### wp-core-calls tooling: archived, not active (2026-06-12)

Gap-analysis matrix row 9 (Codex round-3 adjudication). v1's `wp-core-calls.json` tooling (a bootstrapper/helpers composer hook generating a manifest of WP core calls) has no v2 home and is NOT carried by the active `wordpress-configs` — the only implementation lives in `Archive/Tools/wordpress-configs`, superseded. Status: archived-not-active; low priority. Re-enable in `wordpress-configs` only if the manifest proves wanted during migration. The earlier "lives in wordpress-configs" refutation overstated availability — it lives only in the archive.

### WooCommerce settings backend: WC owns render+save, per-page distinct subclass + static map (Spec C Task 3.2, 2026-06-15)

Spec C §3.7/§8; landed on `feat/spec-c-wc-settings`. The `woocommerce` package's first real implementation — the WC half of the settings stack — built and TDD-resolved against `wp-plugin/woocommerce` under wp-env (Integration 12). Three classes, mirroring the settings package's pure-core / WP-coupled-shell split:

- **`WCSettingsBuilder`** (pure, WP-free, Infection-covered) — translates a `SettingsPage` descriptor into WooCommerce's settings-array shape: each section → a `title`/`sectionend` group on WC's default section, each field → `{id: {slug}_{field}, type, title, default, options?, custom_attributes?}`. Faithful to WC's own render/save expectations (verified against `includes/admin/class-wc-admin-settings.php` + `includes/admin/settings/class-wc-settings-page.php`): a checkbox boolean default → `'yes'`/`'no'` (WC's `checked()` string-compares against `'yes'`); option labels stringified (`is_scalar()?(string):''`, since WC `esc_html()`s them); multiselect defaults stringified (WC's strict `in_array( (string)$key, …, true )`); choice fields always emit an options array (WC iterates it unconditionally); attributes filtered to the `FieldRenderer` allow-list (WC `esc_attr()`s but does not reject `on*` handlers). Field-type tokens pass through unchanged — the framework taxonomy is a verbatim subset of WC's `$types`, so the §8 mapping is identity (no mapper class).
- **`DescriptorBackedWCSettingsPage`** (abstract, `extends \WC_Settings_Page`) — a consumer declares one empty `final` subclass per page; a `static array<class-string, SettingsPage>` map holds descriptors, keyed by the concrete subclass. **A distinct subclass per page is required**: WooCommerce rebuilds settings-page objects each request and recovers them by class name, so the descriptor must be recoverable from a distinct class-string — two plugins ⇒ two subclasses ⇒ no map collision. Allowed abstract base (WC's API forces subclassing — per the "abstract base when pattern intrinsically requires" decision). The constructor sets the WC tab id (`location ?? slug`) and label before `parent::__construct()` wires WC's hooks; an unbound instantiation throws `UnboundSettingsPageException`.
- **`WooCommerceSettingsBackend`** (`implements SettingsBackendInterface`) — `__construct( class-string<DescriptorBackedWCSettingsPage> $page_class )` binds the per-page subclass (the locked `register_page(SettingsPage)` signature can't carry it). `register_page()` adds the `woocommerce_get_settings_pages` filter and bridges each field's descriptor `sanitize` onto `woocommerce_admin_settings_sanitize_option_{id}`. `get/set/has/delete` address each field by its own prefixed `wp_options` row (`{slug}_{field}` — WC-native, REST-correct; **not** `OptionsStore`, not a grouped array), `has()` using a sentinel to distinguish a stored value from an absent option.

**Binding is deferred into the filter callback.** WooCommerce's autoloader does **not** resolve `WC_Settings_Page` (`WC_Autoloader::autoload` maps `wc_settings_page` to `includes/class-wc-settings-page.php`, but the file is at `includes/admin/settings/`); only `WC_Admin_Settings::get_settings_pages()` includes it, just before applying the filter. So `register_page()` must not touch the page subclass at `plugins_loaded` — it would fatal on the missing parent. `bind()` + `new $page_class()` therefore run inside the `woocommerce_get_settings_pages` callback (a dedicated unbound fixture proves the deferral). *(Codex caught this — it would have shipped a per-request fatal; the tests masked it with a `require_once`.)*

**`validate` not bridged; boolean `false` not stored.** WC's save path has no per-field validate seam (only the sanitize filter), so the descriptor `validate` closure stays a WordPress-backend concern. Values round-trip as WooCommerce stores them (an off checkbox is `'no'`), never the boolean `false` WordPress cannot keep distinct from an absent option. WooCommerce's own multiselect form-save `array_filter`s the submitted set, so a multiselect option keyed by a falsy value (`'0'`/`''`) is dropped on save — keep multiselect option keys truthy. (The backend's own `set()`/`get()` round-trip `'0'` correctly; this is WC's form-save behavior, delegated to per "WC owns save".)

**Per-field capability enforced.** `SettingsField::$capability` (distinct from the *page* cap, which WC fixes to `manage_woocommerce`) is honored like the WordPress backend: a field the current user lacks the capability for is dropped before WooCommerce renders it (so it is neither shown nor saved), and its per-option sanitize filter preserves the stored value against a tampered save. *(Holistic-review finding — the fixed page-level `manage_woocommerce` alone would let any settings-capable user overwrite a more privileged field. The two rejected siblings — radio/multiselect option-membership and unknown-type validation — are deliberately WC's job per §3.5/§3.7: WC owns save and the framework's processing layer is not reused; the descriptor `sanitize` closure is the consumer's seam for stricter rules.)*

**deptrac:** `WooCommerce: [Core, Shared, Settings]` — Settings for the contract + descriptors + reused exceptions; Shared for `UnboundSettingsPageException`'s base. No `Storage` edge (WC-native options, not `OptionsStore`). This realizes the `SettingsBackend` "3 impls" note (WP done in 3.1, WC here; MetaBox still on-demand) from the *"Service interfaces: only on plurality"* block.

### WC product-data settings tab: final store + reused descriptors, native render, framework-owned save, dual default-injection (2026-06-15)

Resolves parity finding A1 (the v1→v2 parity reconciliation — reports archived to the project vault `audits/`) — the one net-new framework component it surfaced. Replaces the v1 abstract pair `WC_AbstractValidatedProductSettingsTab`/`GroupFunctionality` with a `final` engine + descriptors, built **ahead** of its Phase-5 consumers (Quote Requests, Warranty Requests; PPW registers no product-data tab). Independent Claude×Codex design derivation converged at round 1; TDD-built against `wp-plugin/woocommerce` 10.8.1 under wp-env. Three classes under `woocommerce/src/ProductData/` (namespace `…\WooCommerce\ProductData`):

- **`ProductDataTab`** (`final readonly`) — descriptor: `slug`, `label`, `meta_key_prefix`, `sections` (reusing `Settings\…\SettingsSection`, whose fields are `SettingsField`), `classes` (`list<string>|Closure` for v1's dynamic `show_if_{type}`), `priority` (65), an optional `supports_product` gate, and a `custom_renderers` type→closure registry. An invalid slug throws `InvalidProductDataTabException`.
- **`ProductDataFieldRenderer`** (`final`) — pure `args()` maps a `SettingsField` to the `woocommerce_wp_*` arg array; `render()` dispatches to the native control. WC renders its own (the settings `FieldRenderer` docblock says so), so the tab renders with the product panel's markup — NOT `FieldRenderer`. A checkbox value normalizes to WC's `yes`/`no`; multiselect gains `[]`/`multiple`; `on*`/malformed attributes are filtered (the allow-list `WCSettingsBuilder` enforces).
- **`ProductDataFieldStore`** (`final`) — the engine; one store drives one tab. `register_tab()` wires the three product hooks (tabs/panels/`process_product_meta`) plus the two default filters; field-addressed `get/set/has/delete`; `meta_keys()` for uninstall.

**Dual default-injection is the must-not-drop behavior** (zero prior v2 analogue; v1 group `:67-68`/`:527-571`): the store registers BOTH `default_post_metadata` (covers `get_post_meta`) and the dynamic `woocommerce_data_store_wp_post_read_meta` (`meta_type='post'`; splices a synthetic `meta_id=0` row into `WC_Product`'s bulk read — `class-wc-data-store-wp.php:121`), so a product predating a field renders its descriptor default instead of a blank. The value comes from `SettingsField::$default` (a checkbox default normalized to `yes`/`no`); gated on **field membership (cheap) before product support (costly)** — a perf win over v1's reverse order — and on field existence, not truthiness. **Injection is a read concern, NOT capability-gated**; render + save are.

**Save is framework-owned**, unlike the WC settings page (where `WC_Admin_Settings` saves): WooCommerce verifies the product-edit nonce + `edit_post` before `woocommerce_process_product_meta` fires, so the store re-checks neither — but it DOES gate each field on `SettingsField::$capability` (a hardening over v1). Taxonomy fields process through the reused `FieldProcessor`; a checkbox stores `yes`/`no` (its submit convention); a non-taxonomy "custom" field renders via `custom_renderers[type]` and saves via its own `sanitize` — the v2 form of v1's `output_field` action seam (Warranty Requests' `dws_relative_date_selector`). Every editable field is written and the product saved once (v1's store-all-on-save), so a field left at its default holds a real value after the first save.

**CRUD semantics:** `has()` reports a *real* stored value (`metadata_exists`, excluding the injected default); `get()` returns the effective value (stored, or the injected default while none is stored). Meta key = `{meta_key_prefix}{section_id}_{field_id}`, or a per-field `SettingsField::$meta_key` override for byte-exact v1 keys (so a migrating consumer reads existing product meta with no data migration). A duplicate resolved meta key throws `DuplicateSettingsFieldException`. **Uninstall stays the consumer's `InstallerInterface` concern** — the store owns no `uninstall()`; `meta_keys()` exposes the exact key set (safer than v1's `LIKE`-prefix delete).

**Cross-package change:** `SettingsField` gains an optional `description` (additive; the WP-backed `FieldRenderer` now renders it too, so the WP options page + object meta box benefit). `desc_tip` stays WC-only (defaulted true in the product renderer). `php-stubs/woocommerce-stubs` added to the woocommerce package's php-scoper `scoping-stubs` (the product-data + existing WC settings code reference WC symbols a consumer's scoper must leave external).

**deptrac:** unchanged — `WooCommerce: [Core, Shared, Settings]` already covers the reuse of `SettingsField`/`SettingsSection`/`FieldProcessor`/`FieldType`/`OptionsResolver` + the settings exceptions; `Shared` for `InvalidProductDataTabException`'s base. No abstract base (the "intrinsically requires" exception does not fire — a tab registers via filters, with no WC class to subclass) and no new interface (single impl). Gates at landing: Unit 288 · Integration 261 · PHPStan clean · deptrac 0 · Infection 100% covered-code MSI on `woocommerce/src`. Design spec in the project vault (`drafts/2026-06-15-wc-product-settings-tab-mini-spec`).

### Component grammar — interface placement (2026-06-21)

One rule replaces three improvised conventions. An interface lives at the ROOT of the concept folder it names; concrete variants live in plural sub-bags beneath it (`Handlers/`, `Stores/`, `Backends/`, `ValueObjects/`, `Exceptions/`). A package whose ENTIRE content is a single concept keeps its contract flat at the package `src/` root — the package boundary IS the concept boundary (e.g. `storage`: `KeyValueStoreInterface` beside its stores). The locked core entrypoint pair (`PluginInterface` / `PluginKernel`) is the one explicit flat exception; do not generalize it. There is NO generic `Contracts/` subfolder — an interface is the concept's root type, not a nested artifact. Placement test for a new interface: does its concept share the package with other concepts? → concept-folder root. Is the package a single concept (or the locked entrypoint)? → package root. Applied: `Hooks/Contracts/HookHandlerInterface` moved to `Hooks/HookHandlerInterface`. (`settings`' `Schema` concept is organized into `Field/`, `Options/`, and `Aggregation/` concept folders — the field-type/render/process classes, the options resolver + provider, and the field aggregator + provider respectively — with `ValueObjects/`/`Exceptions/`/`Errors/` as its plural bags.)

### Component grammar — value object vs descriptor (2026-06-21)

`ValueObjects/` folders hold immutable carriers of two kinds; the folder name is kept, and the kinds are distinguished by base class + docblock, not by a marker interface. **Value object** — extends `Shared\ValueObject\AbstractValueObject`; use ONLY when reflection-driven structural `equals()` / `jsonSerialize()` is actually consumed and every public property can participate safely in structural equality (`PluginHeader`, `Version`); its docblock opens "Value object …". **Descriptor** — a bare `final readonly` class with no base; the default immutable carrier, and REQUIRED when it holds closures/callables, runtime providers, or WordPress/WooCommerce objects (`SettingsField`, `SettingsPage`, `SettingsSection`, `ObjectMetaBox`, `AdminNotice`, `DependencyRequirement`); its docblock opens "Descriptor for …". A marker `DescriptorInterface` is deferred until real code must typehint a polymorphic descriptor collection — a taxonomy-only marker is ceremony (service interfaces only on plurality).

**Invalidity exceptions follow the same split.** A value object's invalidity exception extends `Shared\ValueObject\Exceptions\InvalidValueObjectException` (the VO-exception-family base), supplying its `value_object_type` — e.g. `InvalidVersionException` for `Version`. A descriptor's invalidity exception extends the generic `Abstract{InvalidArgument,Runtime}Exception` base directly (it is not a value object); the choice between the two follows the failure's nature — `AbstractInvalidArgumentException` when it validates a malformed, caller-supplied descriptor argument (e.g. `InvalidSettingsField`/`Section`/`Page`/`CustomFieldType`, the MetaField `InvalidFieldGroup`/`InvalidTermFieldGroup`, `InvalidAdminNoticeException`, and `InvalidProductDataTabException` — the last also covering an incomplete custom-field wiring caught at tab registration), `AbstractRuntimeException` for a runtime fault (`Duplicate…`, `Unknown…`, `Unsupported…`, `Unbound…`, options-resolution). The base is the one sanctioned abstract-exception family (see the abstract-base-classes block).

### DeprecatedHooksDispatcher: speculative-by-choice (2026-06-22)

`utilities/Hooks/DeprecatedHooksDispatcher` (fires a current hook plus a deprecated legacy alias in lock-step via WP's `do_action_deprecated()` / `apply_filters_deprecated()`) has zero consumers and no v1 precedent — it does NOT clear the demand-certain build-ahead bar (no archive call sites; the closed ecosystem has no third-party hook consumers a legacy alias would serve). It is KEPT as a deliberate, recorded speculative exception, not an oversight: a v2 plugin that renames a public hook is the obvious consumer, the primitive is self-contained and tested, and recording it keeps the build-ahead boundary principled rather than arbitrary. This is the one consciously-speculative utility in the framework.

### Error/ErrorInterface: kept distinct, not folded into Result/ (2026-06-22)

Considered folding `shared/Error/ErrorInterface` into `Result/` (it reads as a single-file folder). KEPT. It is load-bearing beyond `Result`: `utilities/Scheduling/Errors/SchedulingError` implements it, it is the `TError` bound across `AbstractResult` / `Failure`, and it is the deliberate counterpart to `Exception/ExceptionInterface` — the error-vs-exception split (errors are expected-failure-as-data carried by `Failure`; exceptions are unexpected and propagate via throw/catch). Folding it into `Result/` would couple a backend-agnostic error marker to the Result carrier (forcing `SchedulingError` to depend on `Result/`) and collapse that semantic boundary. The folder mirrors `Exception/` and earns its place.

### Result idiom: failure-as-data for latent, enumerable failures; void or throw elsewhere (2026-06-27)

`shared/Result/` (the `Success`/`Failure` sealed pair over an `ErrorInterface` payload) carries an EXPECTED, caller-actionable failure as data — the error-as-data half of the split the `Error/ErrorInterface` ↔ `Exception/ExceptionInterface` docblocks draw. This block fixes WHEN a fallible operation returns a `Result` rather than `void`/`throw`, so the idiom stays a deliberate boundary instead of spreading by habit (or being read as the only fallible-write convention).

An operation returns `Result<TValue, TError>` only when BOTH hold: its failure is an expected outcome — not a programmer error, not an infrastructure outage — AND the caller is expected to branch on which failure occurred. The reference consumer is `utilities/Scheduling`: a schedule request can fail because Action Scheduler is not loaded, the interval is non-positive, the group is unsupported, or the backend rejected it — each an enumerable `SchedulingErrorReason` a caller acts on, and the scheduled work is latent (it runs on a later request), so a dropped failure silently means "never scheduled." Expected, enumerable, and caller-actionable is the test — latency, as here, only sharpens the cost of dropping the failure.

Everything else stays `void`/`throw`. Fire-and-forget persistence and cache writes — `KeyValueStoreInterface::set`, the settings backends' `set`, the meta/order field stores' `set`, the cache writes — return `void`: a failed `update_option`/`wp_cache_set` is an environment fault, not a domain branch the caller selects a recovery for, and the next request re-reconciles; threading a `Result` through every store write would be ceremony. A misuse fixable only in code throws an exception, never a `Failure`.

Enforcement: each public operation that returns a `Result` carries `#[\NoDiscard]`, so a latent failure cannot be silently dropped — dropping a `Failure` from a fire-and-forget-looking call is the exact silent-unscheduled-job bug the idiom exists to prevent. `void` writes need no guard, and the internal result-mapping helpers, always consumed via `return`, are not caller-facing discard points. (`#[\NoDiscard]` is not inherited in PHP, so it sits on each concrete dispatched method; the interface declaration also carries it as the contract.)

`utilities/Scheduling` and `settings`' `FieldProcessor` are the `Result` consumers today — the operations whose failures are expected, enumerable, and caller-actionable. Scheduling's are latent (a dropped `Failure` means a job is never scheduled); the field processor is the synchronous validating-domain-service case the rule anticipates — its `process_or_reject()` returns a `Failure<FieldProcessingError>` carrying the enumerable `FieldProcessingErrorReason` for the save path to branch on (its sibling `process()` instead folds a rejection to a fallback value), while the settings backend's `set` write stays `void`. A new store/cache/settings *write* still does not adopt `Result`.

### Mutation testing: strict-Unit + non-strict-Integration profiles (2026-06-28)

Two Infection profiles. The default (`composer test:mutation`, `infection.json`) mutates the Unit-covered code from the strict root PHPUnit config. A second (`composer test:mutation:integration`, `infection.integration.json`) mutates the Integration-covered code (the WC/settings backends, object-field/order/product stores, storage, utilities) inside the wp-env `cli` container.

The Integration profile points at a **non-strict** PHPUnit config (`tests/mutation/phpunit.dist.xml`, `beStrictAboutCoverageMetadata` off): integration tests boot WP and traverse broad core stacks, so under coverage they "execute undeclared code" en masse; Infection mutates from real line coverage, not Covers/Uses metadata, and a risky-flagged test counted as a kill would inflate MSI. Only `beStrictAboutCoverageMetadata` is relaxed — `failOnRisky` stays true, and the canonical `composer test:integration` (root config) stays strict + fail-on-risky. `Backend/DescriptorBackedWCSettingsPage` is excluded from this profile: it `extends \WC_Settings_Page`, which WC's autoloader does not resolve, so Infection's static analysis (WP not booted) fatals on the unresolved parent; the exclude drops it from mutation scoring only, not its integration tests (it is the sole `extends \WC_*`/`\WP_*` class in src).

Per-profile `minCoveredMsi` floors guard regressions. The coverage driver in the `cli` container is the docker-official PHP's pcov (the Alpine system PHP's `php85-pecl-pcov` targets the wrong binary); `test:mutation:integration` preflights for a driver and names the fix. CI enforces only the Unit profile today; wiring the Integration profile needs a coverage input on the `wordpress-configs` reusable PHPUnit workflow — a cross-repo follow-up.

### Static-analysis stubs: WP 7.0 via inline alias (2026-06-28)

PHPStan analyses against `php-stubs/wordpress-stubs` 7.0.0, pulled via a Composer inline alias (`"php-stubs/wordpress-stubs": "7.0.0 as 6.9999.0"` in the root `composer.json` require-dev) because `php-stubs/woocommerce-stubs` caps `wordpress-stubs` below 7.0. An inline alias requires an exact left-hand version, so this **hard-pins** wordpress-stubs to exactly 7.0.0 — it will not float to 7.0.x/7.x. Maintenance: (a) drop the alias for a plain `^7.0` once `woocommerce-stubs` widens its cap to include `^7.0`; (b) until then, bump the `7.0.0` string on each new WP 7.x stubs release. `wp-plugin/woocommerce` + `woocommerce-stubs` are pinned to `^10.9`. Per-package `phpstan.neon` configs enforce the package boundaries; the root `phpstan.dist.neon` was removed so a bare or IDE PHPStan run fails loudly rather than passing under a lenient global set.

### OrderData / ProductData naming: per-domain meta surfaces, kept (2026-06-28)

The woocommerce package groups its field machinery under `OrderData/` (`OrderFieldStore` + `OrderMetaRepository`) and `ProductData/` (`ProductDataTab` + `ProductDataFieldRenderer` + `ProductDataFieldStore`). The folder names denote the WooCommerce domain object whose meta the folder manages — the order vs. the product — not a layer; they are the concept folders for two distinct meta surfaces with different WC integration points (HPOS order screens vs. product-data panels). Kept as-is: the `Store`/`Repository`/`Tab`/`Renderer` suffixes carry the role within each.

### Settings REST exposure: opt-in, type-only schema (2026-06-28)

The WordPress settings backend registers one grouped option per section with `register_setting`; when a section's fields opt into REST (`SettingsField::$show_in_rest`), it attaches a generated `object` schema (`rest_schema_for_field` per exposed field) so the section's option is REST-exposed. The generated schema is **type-only**: a choice field is typed by its value (string/integer), not constrained to an `enum` of its current options. Option sets can be dynamic (a `Closure`/provider resolved per request), so baking them into a static REST schema would rot or wrongly reject a valid runtime option; membership validation stays the descriptor's `sanitize`/`validate` seam. REST exposure is opt-in per field (`show_in_rest` defaults false); the WooCommerce backend leaves REST to WC-native option storage.

### Identifier validation: single-sourced free functions (2026-06-28)

A user-supplied identifier reused as a storage key, form-field-name segment, nonce key, or DOM id is validated against a single-sourced charset free function at every construction site, so a malformed id fails at wiring time rather than at use. `settings` uses `Schema\is_valid_identifier`; `utilities` uses `AdminNotices\is_valid_notice_id` (lowercase `a-z`, digits, `_`, `-` — matching `sanitize_key`'s retained set, so a passing id round-trips WP's `data-dismissible` dismissal key unchanged). An `AdminNotice` id is validated at the `AdminNotice` ctor, the `AdminNoticeLogger` ctor (so a bad id throws at logger construction, before the fail-closed kernel-boot installer path that calls the logger), and explicit `DependencyRequirement` ids; a derived `dep_…` id is provably within the charset by construction, so it never throws.

### composer-require-checker: declared-dependency completeness, the third boundary axis (2026-06-28)

`composer lint:php` runs a third dependency-boundary check beside PHPStan (symbol existence under the loaded stubs) and deptrac (internal package edges): `maglnet/composer-require-checker` verifies every package declares each Composer-dependency symbol it uses, catching a package that reaches a transitively-installed dependency without a direct `require`.

The packages install only through the monorepo root vendor (they are path repositories there), so `bin/composer-require-check.php` checks each one against that root vendor: it points `packages/<pkg>/vendor` at the root vendor for the duration of the check, derives the WordPress/WooCommerce stub files to treat as host symbols from the package's own `extra.scoping-stubs` (the source the scoper already reads), and layers the `composer-require-checker.json` allow-list on top. `scan-files` feeds a scanned stub into BOTH the defined and the used symbol sets, so the comprehensive WordPress/WooCommerce stubs surface their own external references (PHP-extension classes, WordPress runtime constants, PSR contracts, WooCommerce internals); the allow-list clears exactly those host symbols.

**No framework symbol is ever whitelisted** — a framework symbol used across a package boundary must resolve through a declared `require`, not the allow-list, and `psr/log` / `psr/container` are declared requires deliberately absent from the allow-list so an undeclared use of either still fails. Two framework free functions are therefore made resolvable for static analysis rather than whitelisted: a same-namespace free-function call carries the `namespace\` prefix (a bare call is recorded by the analyzer as a global symbol and never matches the namespaced definition — `shared`'s `convert_to_primitives` calls `namespace\get_public_property_names`); and `bootstrap`, the only functions-only package (no psr-4, so its `src/` is otherwise unscanned), declares a `classmap` for `src/` so its public functions are discoverable through its own autoload. The `files` aggregator still loads them at runtime, and a class-free `src/` yields an empty class map, so the `classmap` is inert at runtime — it exists purely to expose the functions to the analyzer. Keep `bootstrap/src` class-free: a class added there is already loaded by the aggregator's `require_once`, so its classmap entry would be a redundant, confusing duplicate.

`Psr\Http\*` and `Psr\SimpleCache\CacheInterface` are the only allow-list entries that are PHP-FIG standard contracts mapping to a `psr/*` package a framework package would declare if it adopted them (the WordPress / WooCommerce / Action Scheduler symbols are runtime-host symbols, never a framework `require`); they are present solely because the WordPress stub references them, and the framework uses neither. If a package ever genuinely depends on a PSR-18 HTTP client or PSR-16 cache, drop the matching entry and declare the package so the check stays honest.
