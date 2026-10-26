# wp-framework-settings

Settings screens and edit-screen fields declared as data, so WordPress's own APIs render, validate and save them and a plugin never hand-writes form handling.

It builds on wp-framework-core.

`AdminPage/` pages store each tab's fields in one option through the Settings API; `EditScreen/` forms store each field on the edited object through a store.

This package is developed in the [wordpress-framework](https://github.com/ahegyes/wordpress-framework) monorepo, which holds its documentation, issues, pull requests and changelog.
