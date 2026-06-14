# Security policy

## Reporting a vulnerability

Please report security vulnerabilities privately via [GitHub's security advisory form](https://github.com/ahegyes/wordpress-framework/security/advisories/new). Do not open a public issue.

You can expect an initial response within 7 days. Once the report is triaged, you'll receive updates as fixes land. Once a fix is released, credit is given in the advisory unless you request anonymity.

## Supported versions

Only the latest release line of each package receives security updates:

| Package                            | Supported version |
| ---------------------------------- | ----------------- |
| `ahegyes/wp-framework-bootstrap`   | latest 2.x        |
| `ahegyes/wp-framework-shared`      | latest 2.x        |
| `ahegyes/wp-framework-storage`     | latest 2.x        |
| `ahegyes/wp-framework-core`        | latest 2.x        |
| `ahegyes/wp-framework-utilities`   | latest 2.x        |
| `ahegyes/wp-framework-woocommerce` | latest 2.x        |

Older minor releases may receive critical fixes at the maintainer's discretion.

## Scope

In scope: vulnerabilities in this framework's PHP code, its scoping pipeline, or its CI configuration.

Out of scope: vulnerabilities in WordPress core, WooCommerce, or upstream Composer dependencies — report those to their respective maintainers. The transitive `roave/security-advisories` constraint will fail `composer install --dev` on any known CVE in the dep graph.
