=== KAGG Generator ===
Contributors: kaggdesign
Tags: generate posts, generate pages, development, bulk generate
Requires at least: 5.3
Tested up to: 6.5
Stable tag: 1.11.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The plugin generates posts/pages. Useful to generate millions of records in the wp_posts table.

== Description ==

In WordPress development, sometimes it is needed to generate extensive databases with hundreds of thousands of posts/pages. Existing plugins can generate test content very slowly, with the usual rate of 1,000 posts per hour.

The Fast Post Generator plugin can generate millions of posts/pages in minutes, which is 20,000 times faster than similar plugins.

= Features =

* The plugin generates posts/pages with random content.

== Plugin Support ==

* [Support Forum](https://wordpress.org/support/plugin/kagg-fast-post-generator/)

== Installation ==

1. Upload `kagg-fast-post-generator` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Can I contribute? =

Yes, you can!

* Join in on our [GitHub repository](https://github.com/kagg-design/kagg-fast-post-generator)

== Changelog ==

= 1.11.0 =
* Added admin notice about the unusable system temp directory.

= 1.10.0 =
* Tested with WordPress 6.5.
* Tested with PHP 8.3.
* Fixed fatal error with WP 6.3+.
* Fixed deprecation errors with PHP 8.

= 1.9.0 =
* Dropped support for PHP 5.6. The minimum required PHP version is now 7.0.
* Tested with WordPress 6.3.

= 1.8.0 =
* Tested with WordPress 6.2.

= 1.7.0 =
* Improved plugin behavior in admin.
* Fixed: Item generation time now is properly distributed within the default period.
* Added comments from not logged-in users.
* Added filter for item's initial time shift.
* Added filter for comment's random posts count.
* Added filter for comment's random IPs count.
* Added filter for comment's maximum nesting level.
* Added filter for comment's nesting percentage.
* Added filter for comment's max sentences.
* Added filter for random user's count.
* Added filter for logged-in user's percentage.
* Added filter for paragraphs in the post.
* Added filter for words in title.

= 1.6.0 =
* Tested with WordPress 6.1.
* Fixed fatal error with WP 6.1 and SHORTINIT.

= 1.5.0 =
* Added generation of SQL files.

= 1.4.0 =
* Added generation of comments, with hierarchy.
* Improved posts' generation, now with random date and author.
* Added generation of users.

= 1.3.1 =
* Tested with WordPress 6.0.
* The minimal WordPress version is now 5.3.

= 1.2.0 =
* Added ability to work on Linux servers.

= 1.1.0 =
* Added writing of all post fields initially created by WP Core for a post.

= 1.0.0 =
* Initial release.
