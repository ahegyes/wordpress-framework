# wordpress-framework

DWS v2 WordPress framework — monorepo for 7 packages (bootstrap, shared, storage, core, utilities, settings, woocommerce). Auto-splits to `wp-framework-*` mirrors via `.github/workflows/split-packages.yml` (splitsh-lite v1.0.1).

## Status

`bootstrap`, `shared` (Error / Exception / Result / ValueObject scaffolding + Reflection helpers), `storage` (KeyValueStore + Memory / Options / UserMeta backends), `core`, `utilities` (Hooks + AdminNotices + Conditionals + Caching), and `settings` (descriptors + aggregator + WordPress options & object-field backends) have implementations. `woocommerce` has a settings-backend implementation (`WooCommerceSettingsBackend` + `DescriptorBackedWCSettingsPage` + `WCSettingsBuilder`, Spec C Task 3.2) and a **product-data settings tab** primitive (`ProductData/` — `ProductDataTab` + `ProductDataFieldRenderer` + `ProductDataFieldStore`, 2026-06-15); its remaining WC helpers (version conditionals, `WC_Logger` PSR-3 bridge) await plugin-migration demand.

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
│   │       ├── Lifecycle/                   # per-action markers: Hookable/, Initializable/, Renderable/, Outputtable/
│   │       └── Installer/                   # install/update/activate/deactivate/uninstall + version I/O (get/set stored, get current)
│   ├── utilities/          # ahegyes/wp-framework-utilities (Hooks + AdminNotices + Conditionals + Caching)
│   ├── settings/           # ahegyes/wp-framework-settings (declarative settings: descriptors + aggregator + WP options & object-field backends; depends on storage + shared)
│   └── woocommerce/        # ahegyes/wp-framework-woocommerce (WooCommerceSettingsBackend + page base + WCSettingsBuilder; ProductData/ tab primitive)
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

### Persistent admin notices: planned for Phase 2 (2026-05-09)

v1 had three notice storage backends — `MemoryStore` (per-request), `OptionsStore` (cross-request via wp_options), `UserMetaStore` (per-user, dismissal-sticky) — combined with `DismissibleNoticesHandler` for "remember dismissed forever" semantics. v2's current `AdminNoticesService` is in-memory only.

Load-bearing in v1 plugins: LO-WC and QR-WC use `DismissibleAdminNotice` + `'user-meta'` store across 5+ call sites for post-redirect-get error UX (queue notice → `wp_safe_redirect` → render on next page load → user dismisses, never sees again). Without the persistent store, the redirect drops the notice.

**Build before LO-WC migration starts.** Likely shape: `NoticeStore` interface + 3 backends (Memory/Options/UserMeta) + `is_persistent` flag on `AdminNotice` + dismissal tracking via user_meta keyed by notice ID.

### wp-framework-shared package: substrate kernel (2026-05-15)

5th framework package: `ahegyes/wp-framework-shared`. Sits as a sibling of `bootstrap` in the dep tree (both have zero internal framework deps), and is required directly by `core` / `utilities` / `woocommerce` — not transitively through `core`.

Contents (all PHP 8.5+):
- `Error/ErrorInterface` — marker for failure payloads carried by `Result\Failure`.
- `Exception/{ExceptionInterface, AbstractException, AbstractInvalidArgumentException, AbstractRuntimeException}` — scaffolding for framework-side exception hierarchies.
- `Result/{AbstractResult, Success, Failure}` — sealed-type simulation of `Result<TValue, TError>`.
- `ValueObject/{ValueObjectInterface, AbstractValueObject}` with reflection-driven `equals()` + `jsonSerialize()` + `Exceptions/InvalidValueObjectException`.
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

### Framework i18n: consumer-domain via scope-time textdomain rewrite — deferred (2026-06-13)

The framework ships no translation catalogs (i18n-catalogs-drop decision), but user-facing framework strings MUST stay translatable. They are translatable only under the *consumer plugin's* text domain (resolved by the consumer's catalog + WP just-in-time loading) — not a framework-owned domain, and not WP core's `'default'` (which has no entries for custom strings).

The intended mechanism is a build-time php-scoper patcher that rewrites a framework token → the consumer's text domain at scope time (v1 did this via `$dws_framework_language_domains`; the v2 `wordpress-configs` scoper rewrite dropped it). The `"text-domain"` composer field already exists in the template + every plugin but is wired to nothing — a half-built feature. The framework's only translatable strings today are the 3 in `bootstrap/src/Notice/functions.php` (domain `'wp-framework-bootstrap'`, left as the patcher's find-target).

**Deferred to a `wordpress-configs` task** (it belongs with the scoping pipeline, migration-plan Task 4.0; the framework has no real consumers until Phase 4 plugin migration, so nothing ships untranslatable provided the patcher lands by then). Do NOT scatter per-package runtime dynamic-domain reads instead — finish the one scope-time mechanism.

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

Gap-analysis row 43 + #24; triage decision 5. v1's `ACFSettingsAdapter` has **zero** successor (IC/LO/LPM) usage — its apparent priority came only from non-successor plugins. v2 ships no ACF settings backend. The Spec C settings stack will define `WordPressSettingsBackend` (+ `WooCommerceSettingsBackend` — both still unwritten); MetaBox stays a planned on-demand backend (matrix row 44). ACF returns only if a wave-2 plugin that actually used it is revived, and then as a final backend against the Spec C contract — not the v1 adapter shape.

### Shortcodes + templating services: dropped; wave-2 triggers pre-seeded (2026-06-12)

Gap-analysis rows 54/55 + #24; triage decision 5. v1's `utilities/Shortcodes/` and `utilities/Templating/` services have **zero** successor usage (their high v1 import counts came from QR-WC / PPW, both wave-2). v2 ships neither service.

Wave-2 re-entry triggers (recorded so the drop is reversible, not amnesia): a revived **QR-WC** registers shortcodes via direct `add_shortcode()` — no service indirection — and, if its frontend templating is real, gets a plugin-local template loader, promoted to a framework topic folder only at cross-plugin volume per the discover-on-demand helpers rule. Do not resurrect the v1 service shape.

### Caching: lean `utilities/Caching/` built in Phase 1, not deferred (2026-06-12)

Gap-analysis row 51 + #24; triage decision 4. The deliberate **exception** to the zero-successor drops above. v1's caching service also has zero *current* successor usage, but wave-2 (QR-WC / WR-WC) demand is certain from the archive call sites, so caching is built as a Phase-1 task rather than discover-on-demand — availability beats a later scramble when the consumer arrives (`feedback-build-ahead-when-demand-certain`).

Planned shape (Task 1.2, design-bearing, not yet landed — mini-spec first): final classes, no facade. `TransientCache` (`get`/`set`/`delete`/`remember()`, per-plugin key prefix, versioned-group invalidation). The object cache stays **WP-native** — consumers call `wp_cache_*` directly, with `wp_cache_flush_group()` (WP 6.1+) for group invalidation; the framework does not wrap it. Task 1.2 carries the API-mapping table against the QR/WR v1 call sites.

### Permissions-as-capabilities: in each plugin's Installer (2026-06-12)

Gap-analysis #17 + matrix row 63; triage decision 1. The #1 successor-impact framework gap (v1 `AbstractPermissions*Functionality`: recursive collection, role `add_cap`/`remove_cap`, permission versioning + caching — 28 files / 51 hits across all three successors).

v2 has no permissions framework. Capability grants live in each plugin's concrete `InstallerInterface` implementation: **grant on `install()` and `update()`, revoke on `uninstall()`** — not on deactivate, since capabilities persist across a deactivate/reactivate cycle, matching WP norms. IC's current activate-time grant gets realigned to this during its migration (Task 4.1). A shared caps-diff helper is carved out only on 2+-plugin duplication, not pre-built. The worked recipe (IC's capability list; the install/update/uninstall triplet) lives in the project vault.

### Install / update: silent versioned migrations, notice on failure only (2026-06-12)

Gap-analysis #18 + matrix row 61; triage decision 2. v1's `InstallationFunctionality` ran user-confirmed AJAX migrations with result notices. v2 migrations are **silent**: the kernel orchestrates install/update on boot (see the kernel-branch "Installer orchestration on boot" block — `run_installer()` compares stored vs current version, dispatches `install()`/`update( $from )`, persists only on success, retries on the next boot after a failure) and surfaces a notice **only when a migration fails**.

There is no framework `InstallationManager` — an orchestrator in `utilities` was a migration-plan phantom (it would violate deptrac Core↛Utilities). The mechanism splits cleanly: the kernel (core) owns orchestration; each plugin's concrete `Installer` owns persistence (`{slug}_version` via the `storage` package's `OptionsStore`) and the failure-notice UX (a consumer-injected PSR-3 logger whose error handler queues a persistent `AdminNotice` — recipe in the project vault). `update()`'s migration steps MUST be idempotent — silent retry-on-boot depends on it. The worked recipe lives in the project vault alongside the permissions recipe (one `Installer` does both).

### Concrete generic exceptions: deferred to first consumer (2026-06-12)

Gap-analysis #22 + matrix row 24. v1 shipped five generic concrete exceptions. v2 `shared/Exception/` holds the interface + three abstract bases only and adds concretes back on demand. Add `final NotFoundException` and `final NotSupportedException` (each extending the fitting abstract) at the first consumer that throws them: `NotSupportedException` already has surviving-code precedent (v1 `DWS_Order_Node` / `DWS_Internal_Comment` models throw it), and `NotFoundException` is the likely first call for the stores/settings backends. The other three v1 concretes — `InexistentPropertyException`, `NotImplementedException`, `ReadOnlyPropertyException` — were thrown only by v1 framework constructs that v2 drops or redesigns (the validation handlers, the dependency-state / admin-notice / `*Aware` traits, the WC validated-options abstracts, the magic-property accessors), so they have no v2 home. Once concretes mix with the abstracts they nest in a plural `shared/Exception/Exceptions/` bag per the singular-concept / plural-bag rule; until then, no speculative classes.

### WooCommerce package: version conditionals + `WC_Logger` PSR-3 adapter, on demand (2026-06-12)

Gap-analysis #21 + matrix rows 65/66. *(Partially superseded 2026-06-15: the WC settings backend landed via Spec C Task 3.2 — see the decision block below — so the package is no longer a skeleton. These two helpers remain on-demand.)* Two WC helpers land at the first WC-plugin migration (LO-WC, Phase 4), both thin finals:

- **WC version conditionals** — `Shared\Version\Version` covers version-compare mechanics, but WC's plugin-version and **db-version** checks (the latter has no analogue anywhere in v2) become `ConditionalInterface` implementations in the woocommerce package, mirroring the `utilities/Conditionals/Dependencies/` shape.
- **`WC_Logger` PSR-3 bridge** — a thin `final` adapter implementing `Psr\Log\LoggerInterface` over WC's `WC_Logger` (v1's `WC_LoggingHandler`), so framework/plugin code logs through the standard PSR-3 surface and the kernel's optional logger can route to WC's log viewer.

Both are trivial finals best shaped against their first real consumer, so they ride the LO-WC migration rather than being pre-built in the skeleton now.

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

One rule replaces three improvised conventions. An interface lives at the ROOT of the concept folder it names; concrete variants live in plural sub-bags beneath it (`Handlers/`, `Stores/`, `Backends/`, `ValueObjects/`, `Exceptions/`). A package whose ENTIRE content is a single concept keeps its contract flat at the package `src/` root — the package boundary IS the concept boundary (e.g. `storage`: `KeyValueStoreInterface` beside its stores). The locked core entrypoint pair (`PluginInterface` / `PluginKernel`) is the one explicit flat exception; do not generalize it. There is NO generic `Contracts/` subfolder — an interface is the concept's root type, not a nested artifact. Placement test for a new interface: does its concept share the package with other concepts? → concept-folder root. Is the package a single concept (or the locked entrypoint)? → package root. Applied: `Hooks/Contracts/HookHandlerInterface` moved to `Hooks/HookHandlerInterface`. (`settings` is multi-concept but currently under-foldered; its concept-folder reorganization is tracked as a separate item.)

### Component grammar — value object vs descriptor (2026-06-21)

`ValueObjects/` folders hold immutable carriers of two kinds; the folder name is kept, and the kinds are distinguished by base class + docblock, not by a marker interface. **Value object** — extends `Shared\ValueObject\AbstractValueObject`; use ONLY when reflection-driven structural `equals()` / `jsonSerialize()` is actually consumed and every public property can participate safely in structural equality (`PluginHeader`, `Version`); its docblock opens "Value object …". **Descriptor** — a bare `final readonly` class with no base; the default immutable carrier, and REQUIRED when it holds closures/callables, runtime providers, or WordPress/WooCommerce objects (`SettingsField`, `SettingsPage`, `SettingsSection`, `ObjectMetaBox`, `AdminNotice`, `DependencyRequirement`); its docblock opens "Descriptor for …". A marker `DescriptorInterface` is deferred until real code must typehint a polymorphic descriptor collection — a taxonomy-only marker is ceremony (service interfaces only on plurality).

**Invalidity exceptions follow the same split.** A value object's invalidity exception extends `Shared\ValueObject\Exceptions\InvalidValueObjectException` (the VO-exception-family base), supplying its `value_object_type` — e.g. `InvalidVersionException` for `Version`. A descriptor's invalidity exception extends the generic `Abstract{InvalidArgument,Runtime}Exception` base directly (`InvalidSettingsFieldException`, …) — it is not a value object. The base is the one sanctioned abstract-exception family (see the abstract-base-classes block).
