<?php
/**
 * Generator registration contract, independent of WordPress.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Tests\Unit;

use KAGG\Generator\Generator\Comment;
use KAGG\Generator\Generator\Generator;
use KAGG\Generator\Generator\Page;
use KAGG\Generator\Generator\Post;
use KAGG\Generator\Generator\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Generator class to ensure correct mapping of item types to generator classes.
 */
class GeneratorTest extends TestCase {
	/**
	 * Verify each supported item type maps to its expected generator class.
	 *
	 * @return void
	 */
	public function test_registers_all_supported_item_types(): void {
		self::assertSame(
			[
				'post'    => Post::class,
				'page'    => Page::class,
				'comment' => Comment::class,
				'user'    => User::class,
			],
			( new Generator() )->get_registered_items()
		);
	}
}
