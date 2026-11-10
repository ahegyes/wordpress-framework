# Security policy

## Reporting a vulnerability

Report it privately through [GitHub's security advisory form](https://github.com/ahegyes/wordpress-framework/security/advisories/new), never in a public issue. The first response comes within 7 days, and the advisory credits the reporter once a fix is released, unless they ask to stay anonymous.

## Supported versions

Only the latest release line of each package receives security updates:

| Package                            | Supported version |
| ---------------------------------- | ----------------- |
| `ahegyes/wp-framework-bootstrap`   | latest 2.x        |
| `ahegyes/wp-framework-shared`      | latest 2.x        |
| `ahegyes/wp-framework-core`        | latest 2.x        |
| `ahegyes/wp-framework-settings`    | latest 2.x        |
| `ahegyes/wp-framework-woocommerce` | latest 2.x        |

Older minor releases may receive critical fixes at the maintainer's discretion.

## Scope

In scope: vulnerabilities in this framework's PHP code or its CI configuration.

Out of scope: vulnerabilities in WordPress core, WooCommerce, or upstream Composer dependencies — report those to their respective maintainers.
