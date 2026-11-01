=== KAGG Generator ===
Contributors: kaggdesign
Tags: generate posts, generate pages, development, bulk generate
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 2.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The plugin generates posts/pages. Useful to generate millions of records in the wp_posts table.

== Description ==

In WordPress development, sometimes it is necessary to generate extensive databases with hundreds of thousands of posts/pages. Existing plugins can generate test content very slowly, with the usual rate of 1,000 posts per hour.

The Fast Post Generator plugin can generate millions of posts/pages in minutes, which is 20,000 times faster than similar plugins.

= Features =

* The plugin generates posts/pages with random content.
* Supports MySQL/MariaDB and WordPress SQLite Database Integration.
* Uses fast LOAD DATA imports on MySQL and batched inserts on SQLite.

== Plugin Support ==

* [Support Forum](https://wordpress.org/support/plugin/kagg-fast-post-generator/)

== Installation ==

1. Upload the `kagg-fast-post-generator` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Can I contribute? =

Yes, you can!

* Join in on our [GitHub repository](https://github.com/kagg-design/kagg-fast-post-generator)

== Changelog ==

= 2.3.0 =
* Added support for SQLite databases.
* Tested with WordPress 7.1.4.

= 2.2.0 =
* The minimum required PHP version is now 7.4.
* The minimum required WordPress version is now 6.0.

= 2.1.0 =
* Fixed "Function _load_textdomain_just_in_time was called incorrectly" notice.
* Tested with WordPress 6.8.

= 2.0.1 =
* Fixed the error on deleting a temporary table.

= 2.0.0 =
* Dropped support for PHP 7.0. The minimum required PHP version is now 7.2.
* Fixed kagg_generator_comment_max_nesting_level filter name.
* Fixed the inability to create a temporary table when it exists after previous operations.
* Fixed deprecation errors with PHP 8.4.
* Tested with WordPress 6.7.
* Tested with PHP 8.4.

[See changelog for all versions](https://plugins.svn.wordpress.org/kagg-fast-post-generator/trunk/changelog.txt).
