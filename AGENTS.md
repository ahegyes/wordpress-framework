# wordpress-framework

DWS v2 WordPress framework — monorepo for 5 packages (bootstrap, shared, core, utilities, woocommerce). Auto-splits to `wp-framework-*` mirrors via `.github/workflows/split-packages.yml` (splitsh-lite v1.0.1).

## Status

`bootstrap`, `shared` (Error / Exception / Result / ValueObject scaffolding + Reflection helpers), `core`, and `utilities` (Hooks + AdminNotices + Storage) have implementations. `woocommerce` is a skeleton, awaiting implementation as plugin migration drives demand.

## Monorepo structure

```
wordpress-framework/
├── packages/
│   ├── bootstrap/          # ahegyes/wp-framework-bootstrap (PHP 5.6+, pre-autoload)
│   │   ├── functions.php   # autoload-files aggregator — requires per-concern function files below
│   │   └── src/
│   │       ├── Environment/   # is_php_compatible, is_wp_compatible (defensive WP version-check wrappers)
│   │       ├── Plugin/        # get_plugin_metadata (cached get_plugin_data reader)
│   │       ├── Requirements/  # FRAMEWORK_MIN_PHP/WP constants + check_requirements + are_requirements_met
│   │       └── Notice/        # output_requirements_error (admin-notice renderer)
│   ├── shared/             # ahegyes/wp-framework-shared (PHP 8.5+, substrate kernel — runtime-WP-aware, no WP-specific abstractions)
│   │   ├── functions.php      # autoload-files aggregator — requires nested <namespace>/functions.php files
│   │   └── src/
│   │       ├── Error/         # ErrorInterface (marker)
│   │       ├── Exception/     # ExceptionInterface + AbstractException / AbstractInvalidArgumentException / AbstractRuntimeException
│   │       ├── Result/        # AbstractResult + Success + Failure (sealed-type sim of Result<T, E>)
│   │       ├── ValueObject/   # ValueObjectInterface + AbstractValueObject + Exceptions/InvalidValueObjectException
│   │       └── Reflection/    # get_public_property_names + convert_to_primitives (underpin VO base)
│   ├── core/               # ahegyes/wp-framework-core
│   │   ├── functions.php      # autoload-files aggregator (placeholder; core has no namespace functions yet)
│   │   └── src/
│   │       ├── PluginInterface.php          # primary: consumer contract
│   │       ├── PluginKernel.php             # primary: runtime engine (three-pass boot)
│   │       ├── ValueObjects/PluginHeader.php
│   │       ├── Feature/                     # FeatureInterface + optional RegistersServicesInterface + Exceptions/
│   │       ├── Conditional/ConditionalInterface.php  # pre-resolution gate
│   │       ├── Enablement/EnabledInterface.php       # post-resolution gate (collapsed from rev-1 Active+Disabled)
│   │       ├── Lifecycle/                   # per-Action: Hookable/, Initializable/, Renderable/
│   │       └── Installer/                   # centralized install/update/activate/deactivate/uninstall
│   ├── utilities/          # ahegyes/wp-framework-utilities (Hooks + AdminNotices + Storage)
│   └── woocommerce/        # ahegyes/wp-framework-woocommerce (skeleton)
├── tests/Fixtures/consumer-smoke/  # plugin-template-shaped scoping smoke fixture
├── composer.json                   # path repos for all 5 packages + VCS for wordpress-configs + WP Packages registry for wp-plugin/woocommerce
└── .github/workflows/
```

`wp-plugin/woocommerce` is installed as a dev dep at `vendor/wp-plugin/woocommerce/` for source-grep + integration-test runtime when developing the woocommerce package. Mounted into wp-env via `.wp-env.tests.json` `mappings`.

## Local development

Commands run from monorepo root:

```sh
composer packages-install   # PHP deps (--ignore-platform-reqs wrapped)
npm install                 # wp-env
npm run wp-env:start        # Start WordPress on port 8801
composer quality-check      # lint + unit tests (no Docker)
composer test:integration   # full WP integration tests via wp-env
composer test:mutation      # Infection mutation tests (no Docker)
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

Integration via `composer test:integration` → `npm run test:integration` → `wp-env run cli vendor/bin/phpunit --testsuite=Integration`.

## Releases

`.github/workflows/split-packages.yml` runs on push to `trunk`. splitsh-lite splits each `packages/<X>/` subdirectory and force-pushes to `github.com/ahegyes/wp-framework-<X>` via `MONOREPO_SPLIT_TOKEN` PAT.

Per-package changelogger fragments live in `packages/<X>/changelog/` and aggregate to `packages/<X>/CHANGELOG.md` on `composer changelog:write:<package>`.

## Architectural decisions

### Plugin entry-point shape: interface + final + composition (Option 5, 2026-05-14)

Locked in `docs/superpowers/specs/2026-05-14-framework-kernel-design.md` after 9 audits (v1 retro, v1 plugin verification, 2 industry surveys, PSR pattern survey, WP plugin rewrites retrospective, Jetpack history, v1 framework naming audit, v1 plugin naming audit).

Consumer plugins define a `final class Plugin implements PluginInterface` (singleton-per-plugin, matches every successful WP plugin rewrite). `PluginKernel::run( Plugin::get_instance() )` is the canonical bootstrap call. The kernel is `final` — composition only, no inheritance.

No abstract base class for Plugin. `KeyValueStoreInterface` moved from `core/` to `utilities/Storage/` to co-locate with its impls.

Lifecycle interfaces live in `core/src/Lifecycle/`: `HookableInterface`, `InitializableInterface`, `ActivatableInterface` (WP plugin activate/deactivate), `UninstallableInterface`, `RenderableInterface` (marker, no methods).

State interfaces live in `core/src/State/`: `ActiveStateInterface` (user-domain "should this run") + `DisabledStateInterface` (framework-domain kill switch). Components opt in to either or both; kernel gates components on `is_active() && ! is_disabled()`.

Exceptions live in `core/src/Lifecycle/Exceptions/` (plural bag of concretes nested under the owning concept per the singular/plural convention): `InitializationException`, `ActivationException`, `DeactivationException` (final, extend `Shared\Exception\AbstractRuntimeException`, no `Failure` infix).

**Update 2026-05-16:** rev-2 redesign supersedes parts of this block — see "v2 core redesign rev 2" decision block below.

### Helpers: deferred, discover-on-demand (2026-05-14)

v1 shipped a `wordpress-framework-helpers` package (22 files, ~280 imports across all v1 plugins). v2 does NOT. No standalone helpers package, no `Helpers/` topic folder.

Rationale: closed ecosystem (no external consumers), no actual midpoint-dep-chain split in v1 history, PHP 8.5 + strict_types absorbs most v1 DataType helpers, and a comparable active WP framework (Pink-Crab Perique) keeps its `Utils/` at 2 files and externalizes pure-PHP helpers entirely.

During plugin migration: replace v1 `Helpers\*` call sites per the rule in `docs/references/v1-helpers-inventory.md` (strict types → native PHP → native WP → inline → plugin-local final class → framework topic folder). If ≥10 cross-plugin stateless helpers accumulate without fitting an existing topic, carve out `utilities/src/Helpers/` at that point. Reference doc preserves the v1 surface for cherry-picking.

### Subtree gating: composition pattern, not interface (2026-05-14)

v1's `is_active_local()` subtree gating preserved via component composition. Group components (e.g., IC's `Output`, `Comments`) hold their children as constructor-promoted readonly properties; the group's `register_hooks()` iterates children, gating each via `ActiveStateInterface`/`DisabledStateInterface`. No framework-side recursive walker, no `GroupInterface`. Each component owns its subtree.

The kernel only knows about top-level components — leaves are NOT registered with `PluginKernel`; they live as constructor-injected children of their group parent and get dispatched by the group's `register_hooks()`.

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
- `Reflection/reflection.php` — `get_public_property_names()` + `convert_to_primitives()` helpers underpinning the VO base (files-autoloaded).

Patterns adapted from A8C's customer360 `shared/` layer — same semantic role (DDD Shared Kernel applied within the framework family) but repackaged as a standalone Composer package per **Common Closure Principle** (Robert C. Martin: "classes that change together belong together") and **Reuse-Release Equivalence Principle** (the granule of reuse is the granule of release). Shared primitives change for different reasons than core's Plugin/Lifecycle/State, and should release independently. This also lets `utilities` and `woocommerce` depend on `shared` directly without inheriting core's Plugin/Lifecycle/State surface when they only need a Result type.

**Runtime-WP-aware, not WP-agnostic.** The package assumes WordPress is loaded at the point of use (it always is — every consumer of shared lives inside a WP plugin context). Uses `wp_json_encode()` for safer JSON output (handles invalid UTF-8 via `_wp_json_sanity_check`, applies WP filters). What shared does NOT do: depend on WP-specific abstractions (Options API, Hooks, Posts, Settings) — those are the line between this package and `utilities`/`core`. Tests that exercise wp_* calls run in the Integration suite under wp-env.

Enforcement: deptrac `Shared: ~` ruleset (zero internal framework deps allowed); composer constraint `"php": ">=8.5"` only.

Strict scope: substrate primitives only. WP-aware helpers DO NOT belong here — if they emerge during migration, they live first in the consumer plugin's local `Helpers/` per the discover-on-demand helpers decision, promoted to `utilities/` if cross-plugin, or eventually a separate `wp-framework-wp-helpers` package only at sustained volume.

### Folder naming: singular concept / plural bag (2026-05-15)

| Form | Role | Contents | Example |
|---|---|---|---|
| **Singular** | The CONCEPT | Type definitions, interfaces, abstract bases. One canonical of each thing. | `Plugin/`, `Lifecycle/`, `State/`, `Result/`, `ValueObject/`, `Error/`, `Exception/` |
| **Plural** | A BAG of concrete instances | Final classes that implement the concept. | `Lifecycle/Exceptions/`, `ValueObject/Exceptions/`, `Hooks/Handlers/`, `Storage/Stores/` |

Verified against A8C customer360 (`shared/exception/` singular = scaffolding; `domain/core/contact-identifier/exceptions/` plural = bag).

Exception: collective-noun folders (`Utils/`, `Hooks/`, `AdminNotices/`) keep plural names because their natural English is plural — don't fight English; the plural folder still acts as a bag of related concrete utilities.

When a singular concept folder grows mixed contents (interface + abstract base + concrete bag), split: nest a plural subfolder under the singular concept. Example: `Lifecycle/` (singular, interfaces) + `Lifecycle/Exceptions/` (plural, concrete exception classes).

### Abstract base classes: allowed when pattern intrinsically requires (2026-05-15)

Amends the workspace-level "interfaces + final + composition, no abstract base class hierarchies" principle. The general rule still holds: composition over inheritance, single-impl services as concrete finals, no inheritance-driven "memory tree" plugin hierarchies (the v1 anti-pattern).

Exception, narrow and verified: patterns whose value depends on abstract-base scaffolding may use abstract bases.

1. **Sealed-type simulation.** `Result<TValue, TError>` requires `abstract AbstractResult` + `final Success` + `final Failure` to fake a union type PHP doesn't natively support. Substituting pure interfaces drops `match()` ergonomics and the shared `is_success`/`is_failure` derivation.
2. **Reflection-driven shared behavior.** `AbstractValueObject::equals()` walks public properties via reflection and works for ANY subclass without each subclass reimplementing equality. Pure interface + per-class implementation forces every VO to duplicate equality logic — defeating the point of the base.

These exceptions live in `wp-framework-shared` (WP-agnostic substrate; the only legitimate home for abstract-base scaffolding). Consumer plugins MAY extend these abstract bases (e.g., `PluginHeader extends AbstractValueObject`); they MUST NOT introduce new abstract base hierarchies of their own without similar intrinsic-requirement justification.

Rule of thumb: if you can implement the pattern as `interface + final + composition` without losing essential semantics, do that. Abstract base is the escape hatch, not the default.

### v2 core redesign rev 2 (2026-05-16)

Locked in `docs/superpowers/specs/2026-05-16-v2-core-redesign-design.md` and implemented per `docs/superpowers/plans/2026-05-16-v2-core-redesign-implementation.md`. Supersedes the 2026-05-14 Spec A reorg and the rev-1 design within the same spec file.

**Primary contracts at `core/src/` root:** `PluginInterface.php` (consumer contract with `get_*`-prefixed accessors) and `PluginKernel.php` (runtime engine). Aspect VOs grouped under `ValueObjects/` (e.g., `PluginHeader`).

**Pre-resolution gating:** `Conditional/ConditionalInterface::is_met()`. Checked by the kernel BEFORE container resolution of any Feature; cheap, side-effect-free. Standard implementations live in `utilities/Conditionals/{Dependencies,Context}/`.

**Post-resolution gating:** `Enablement/EnabledInterface::is_enabled()` — single positive predicate. Collapsed from rev-1's `ActiveStateInterface` + `DisabledStateInterface` split (v1 audit: 20/21 plugin components used Active alone; 0 components implemented both locally; zero of 10 surveyed frameworks split into two interfaces; AMP / Yoast / WC / Jetpack / Laravel Pennant all use single positive predicate). Rare user+framework gating composes inline inside `is_enabled()`.

**Feature wrapper unit:** `Feature/FeatureInterface` declares `get_conditionals()` and `get_components()`. Optional `Feature/RegistersServicesInterface::register_services()` for Features with private container bindings — most Features don't implement this (autowiring handles the common case). Plugin-level cross-cutting bindings live in the plugin's container-builder script; Feature-private bindings live in `register_services()`.

**Centralized installer:** `Installer/InstallerInterface` (install, update, activate, deactivate, uninstall). One implementation per plugin. `update( Version $from_version )` uses the `shared/Version/Version` VO for type-safe SemVer comparison. Per-component `Activatable` / `Uninstallable` interfaces dropped.

**Three-pass plugin-level kernel boot:** all surviving Features `register_services()` → resolve all components from all features → filter via `EnabledInterface::is_enabled()` → `initialize()` on each runnable component → `register_hooks()` on each runnable component. Matches Laravel ServiceProvider + customer360 ServiceProviderInterface two-phase model. Enables cross-feature service access during init+hooks phases.

**Method-name convention:** `get_*` prefix on getter methods (`get_plugin_file`, `get_header`, `get_container`, `get_features`, `get_installer`, `get_conditionals`, `get_components`). Predicates use `is_*` (`is_met`, `is_enabled`). Action verbs keep bare form (`register_hooks`, `initialize`, `render`, `activate`).

**Cross-package additions:**
- `shared/Version/Version.php` — final readonly VO extending `AbstractValueObject` with `is_greater_than` / `is_less_than` / `is_equal_to` / `from_string` / `to_string`. Inherits structural equality via reflection.
- `utilities/Conditionals/Dependencies/` — bag of `WPPluginActiveConditional`, `PHPExtensionLoadedConditional`, `PHPFunctionExistsConditional`, `PHPVersionConditional`, `WPVersionConditional`, `PHPIniSettingConditional`.
- `utilities/Conditionals/Context/` — `IsAdminConditional`, `IsAjaxConditional`, `IsCliConditional`, `CurrentUserCanConditional`.
- Workspace-wide package-root `functions.php` aggregator pattern — each package with namespace function files gets a single composer-autoloaded `functions.php` (sibling of `composer.json`) that `require_once`'s nested `src/<namespace>/functions.php` files. Matches the `bootstrap` package's `functions.php` location + the Symfony Polyfill ecosystem convention.
