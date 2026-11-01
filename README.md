# KAGG Generator

The plugin generates posts/pages. Useful to generate millions of records in wp_posts table.

In WordPress development, sometimes it is needed to generate extensive databases with hundreds of thousands of posts/pages. Existing plugins can generate test content very slowly, with the usual rate of 1,000 posts per hour.

The Fast Post Generator plugin can generate millions of posts/pages in minutes, which is 20,000 times faster than similar plugins.

![](./.wordpress-org/banner-772x250.png)

## Features

* The plugin generates posts/pages. Useful to generate millions of records in the wp_posts table.
* Supports MySQL/MariaDB and WordPress SQLite Database Integration. MySQL uses
  `LOAD DATA`; SQLite uses batched inserts in a transaction for each generation chunk.

In WordPress development, sometimes it is needed to generate extensive databases with hundreds of thousands of posts/pages. Existing plugins can generate test content very slowly, with the usual rate of 1,000 posts per hour.

The Fast Post Generator plugin can generate millions of posts/pages in minutes, which is 20,000 faster than similar plugins.

## Download

You can download and activate the plugin from [wp.org](https://wordpress.org/plugins/kagg-fast-post-generator/).

## Install from GitHub

```
git clone https://github.com/kagg-design/kagg-fast-post-generator.git
cd kagg-fast-post-generator
composer install --no-dev
```

## Development checks

Install development tools with `composer install`. The lockfile resolves tools for
PHP 7.4 and later. Run `composer validate --strict`, `composer audit`, `composer phpcs`,
`composer unit`, and `node --check assets/js/admin.js` before review.

Edit release notes in `readme.txt`, then run `composer changelog` to merge every
listed version into `changelog.txt`. Run `composer changelog:check` to verify it.
The shell entrypoint `.github/scripts/update-changelog.sh` accepts `--check` too.
Keep the current major version's full history in the readme; older releases remain
in the generated archive. The generator preserves historical two-component versions.

Tests follow the same layout as cyr2lat: `tests/unit` contains plain PHPUnit tests,
and `tests/integration` uses the standard WordPress PHPUnit suite from
`wp-phpunit/wp-phpunit`. PHPUnit 9.6 keeps the development tools compatible with PHP 7.4.

Run `composer unit` (or `composer test`) without WordPress or a database. This covers
changelog merging and item registration. Run `composer integration` for plugin loading,
all four generators, imports, deletion, AJAX handlers and comment chronology.
`composer test:all` runs both suites. Use `composer integration -- --filter CommentDatesTest`
to select a class or test. The PHPUnit configurations can also be used directly in PhpStorm.

For integration tests, create a dedicated empty database and download WordPress core
7.1.4. Copy `tests/integration/_config/params.example.php` to `params.local.php` in the
same directory and set the core path and test database credentials. Alternatively set
`KAGG_TEST_PARAMS` to your private parameters file, or configure `KAGG_TEST_WORDPRESS_DIR`
and `KAGG_TEST_DB_HOST`, `KAGG_TEST_DB_NAME`, `KAGG_TEST_DB_USER`, `KAGG_TEST_DB_PASSWORD`.
`WP_PHPUNIT__TESTS_CONFIG` can select an external WordPress test configuration;
`WP_TESTS_DIR` can select an external test library.

The WordPress test suite installs its own tables and **erases existing test tables**.
Use a database dedicated to this plugin, never a working site or another project's
test database. Import/deletion tests use disposable tables because the plugin commits
transactions and replaces tables; cleanup runs after each PHPUnit test.

CI runs both suites on PHP 7.4 through 8.5 for pull requests, and on the minimum and
latest PHP versions for pushes and manual runs. It also runs integration tests with
SQLite Database Integration 3.1.1, including native SQL export, text escaping and
rollback of failed batches. Comment tests cover three timezones and three chunk sizes.
To run SQLite locally, install that database drop-in in the downloaded WordPress core,
enable `pdo_sqlite`, set `DB_ENGINE` to `sqlite` and `DB_PATH` to a dedicated SQLite file
in your params file (or set `KAGG_TEST_DB_ENGINE=sqlite` and `KAGG_TEST_SQLITE_PATH`).

SQLite also supports downloading a native SQLite SQL file. Its import speed differs
from the MySQL `LOAD DATA` path; reduce the generation chunk size if requests time out.

## License

The WordPress Plugin KAGG Fast Post Generator is licensed under the GPL v2 or later.

> This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License, version 2, as published by the Free Software Foundation.

> This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

> You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA

A copy of the license is included in the root of the plugin’s directory. The file is named `LICENSE`.

## Credits

The current version of the KAGG Fast Post Generator was developed by KAGG Design.
