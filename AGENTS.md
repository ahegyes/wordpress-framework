# wordpress-framework

DWS v2 WordPress framework — monorepo for 5 packages (bootstrap, shared, core, utilities, woocommerce). Auto-splits to `wp-framework-*` mirrors via `.github/workflows/split-packages.yml` (splitsh-lite v1.0.1).

## Status

`bootstrap`, `shared` (Error / Exception / Result / ValueObject scaffolding + Reflection helpers), `core`, and `utilities` (Hooks + AdminNotices + Storage + Conditionals) have implementations. `woocommerce` is a skeleton, awaiting implementation as plugin migration drives demand.

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
│   ├── utilities/          # ahegyes/wp-framework-utilities (Hooks + AdminNotices + Storage + Conditionals)
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

No abstract base class for Plugin. `KeyValueStoreInterface` moved from `core/` to `utilities/Storage/` to co-locate with its impls.

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

**Installer orchestration on boot lives in the kernel (core)**, not a utilities orchestrator (the migration-plan "utilities `InstallationManager`" was a phantom — an orchestrator there would violate deptrac). Version I/O routes through three `InstallerInterface` methods — `get_current_version()` / `get_stored_version()` / `set_stored_version()` — keeping `boot()` WP-free (unit-testable, deptrac-clean: core must not depend on utilities). Persistence + failure-notice UX live in the consumer's concrete installer (which may legally depend on utilities); failure UX rides the optional PSR-3 logger.

**Activation wiring cannot live in `boot()`.** `register_activation_hook` only schedules a callback for the activation request, which fires during the plugin include — before any `plugins_loaded`-deferred `boot()`. So `PluginKernel::register_lifecycle_hooks( $plugin )` is static and called at plugin-include scope; it wires `activate( bool $network_wide )` / `deactivate( bool $network_deactivating )` to the plugin's installer.

### utilities phase-0 land + harden (2026-06-13)

Landed the implemented-but-unreviewed `utilities` package (Hooks, AdminNotices, Storage, Conditionals) per migration-plan Tasks 0.4–0.6, treating every line as new work. Decisions, verified against `packages/utilities/src/`:

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
