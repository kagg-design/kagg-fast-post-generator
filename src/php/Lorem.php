<?php
/**
 * Lorem class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator;

use InvalidArgumentException;

/**
 * Class Lorem.
 *
 * Based on https://github.com/fzaninotto/Faker.
 */
class Lorem {
	const RANDOM_KEYS_COUNT = 1000;

	/**
	 * Word list.
	 *
	 * @var string[]
	 */
	protected static $word_list = [
		'alias',
		'consequatur',
		'aut',
		'perferendis',
		'sit',
		'voluptatem',
		'accusantium',
		'doloremque',
		'aperiam',
		'eaque',
		'ipsa',
		'quae',
		'ab',
		'illo',
		'inventore',
		'veritatis',
		'et',
		'quasi',
		'architecto',
		'beatae',
		'vitae',
		'dicta',
		'sunt',
		'explicabo',
		'aspernatur',
		'aut',
		'odit',
		'aut',
		'fugit',
		'sed',
		'quia',
		'consequuntur',
		'magni',
		'dolores',
		'eos',
		'qui',
		'ratione',
		'voluptatem',
		'sequi',
		'nesciunt',
		'neque',
		'dolorem',
		'ipsum',
		'quia',
		'dolor',
		'sit',
		'amet',
		'consectetur',
		'adipisci',
		'velit',
		'sed',
		'quia',
		'non',
		'numquam',
		'eius',
		'modi',
		'tempora',
		'incidunt',
		'ut',
		'labore',
		'et',
		'dolore',
		'magnam',
		'aliquam',
		'quaerat',
		'voluptatem',
		'ut',
		'enim',
		'ad',
		'minima',
		'veniam',
		'quis',
		'nostrum',
		'exercitationem',
		'ullam',
		'corporis',
		'nemo',
		'enim',
		'ipsam',
		'voluptatem',
		'quia',
		'voluptas',
		'sit',
		'suscipit',
		'laboriosam',
		'nisi',
		'ut',
		'aliquid',
		'ex',
		'ea',
		'commodi',
		'consequatur',
		'quis',
		'autem',
		'vel',
		'eum',
		'iure',
		'reprehenderit',
		'qui',
		'in',
		'ea',
		'voluptate',
		'velit',
		'esse',
		'quam',
		'nihil',
		'molestiae',
		'et',
		'iusto',
		'odio',
		'dignissimos',
		'ducimus',
		'qui',
		'blanditiis',
		'praesentium',
		'laudantium',
		'totam',
		'rem',
		'voluptatum',
		'deleniti',
		'atque',
		'corrupti',
		'quos',
		'dolores',
		'et',
		'quas',
		'molestias',
		'excepturi',
		'sint',
		'occaecati',
		'cupiditate',
		'non',
		'provident',
		'sed',
		'ut',
		'perspiciatis',
		'unde',
		'omnis',
		'iste',
		'natus',
		'error',
		'similique',
		'sunt',
		'in',
		'culpa',
		'qui',
		'officia',
		'deserunt',
		'mollitia',
		'animi',
		'id',
		'est',
		'laborum',
		'et',
		'dolorum',
		'fuga',
		'et',
		'harum',
		'quidem',
		'rerum',
		'facilis',
		'est',
		'et',
		'expedita',
		'distinctio',
		'nam',
		'libero',
		'tempore',
		'cum',
		'soluta',
		'nobis',
		'est',
		'eligendi',
		'optio',
		'cumque',
		'nihil',
		'impedit',
		'quo',
		'porro',
		'quisquam',
		'est',
		'qui',
		'minus',
		'id',
		'quod',
		'maxime',
		'placeat',
		'facere',
		'possimus',
		'omnis',
		'voluptas',
		'assumenda',
		'est',
		'omnis',
		'dolor',
		'repellendus',
		'temporibus',
		'autem',
		'quibusdam',
		'et',
		'aut',
		'consequatur',
		'vel',
		'illum',
		'qui',
		'dolorem',
		'eum',
		'fugiat',
		'quo',
		'voluptas',
		'nulla',
		'pariatur',
		'at',
		'vero',
		'eos',
		'et',
		'accusamus',
		'officiis',
		'debitis',
		'aut',
		'rerum',
		'necessitatibus',
		'saepe',
		'eveniet',
		'ut',
		'et',
		'voluptates',
		'repudiandae',
		'sint',
		'et',
		'molestiae',
		'non',
		'recusandae',
		'itaque',
		'earum',
		'rerum',
		'hic',
		'tenetur',
		'a',
		'sapiente',
		'delectus',
		'ut',
		'aut',
		'reiciendis',
		'voluptatibus',
		'maiores',
		'doloribus',
		'asperiores',
		'repellat',
	];

	/**
	 * Generate a word.
	 *
	 * @example 'Lorem'
	 * @return string
	 */
	public static function word() {
		return static::$word_list[ array_rand( static::$word_list ) ];
	}

	/**
	 * Generate an array of random words.
	 *
	 * @param integer $nb      How many words to return.
	 * @param bool    $as_text If true the sentences are returned as one string.
	 *
	 * @return array|string
	 *
	 * @example array('Lorem', 'ipsum', 'dolor')
	 */
	public static function words( $nb = 3, $as_text = false ) {
		static $keys = null;

		static $index = 0;

		if ( null === $keys ) {
			$keys = static::prepare_random_keys();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$index = mt_rand( 0, self::RANDOM_KEYS_COUNT - 1 );
		}

		if ( ( $index + $nb ) > self::RANDOM_KEYS_COUNT ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$index = mt_rand( 0, self::RANDOM_KEYS_COUNT - $nb );
		}

		$words     = [];
		$max_index = $index + $nb;

		while ( $index < $max_index ) {
			$words[] = static::$word_list[ $keys[ $index ] ];
			$index ++;
		}

		return $as_text ? implode( ' ', $words ) : $words;
	}

	/**
	 * Generate a random sentence.
	 *
	 * @param integer $nb_words          Around how many words the sentence should contain.
	 * @param boolean $variable_nb_words Set to false if you want exactly $nbWords returned,
	 *                                   otherwise, $nb_words may vary by +/-40% with a minimum of 1.
	 *
	 * @return string
	 *
	 * @example 'Lorem ipsum dolor sit amet.'
	 */
	public static function sentence( $nb_words = 6, $variable_nb_words = true ) {
		if ( $nb_words <= 0 ) {
			return '';
		}

		if ( $variable_nb_words ) {
			$nb_words = self::randomize_nb_elements( $nb_words );
		}

		return ucfirst( static::words( $nb_words, true ) ) . '.';
	}

	/**
	 * Generate an array of sentences.
	 *
	 * @param integer $nb      How many sentences to return.
	 * @param bool    $as_text If true, the sentences are returned as one string.
	 *
	 * @return array|string
	 *
	 * @example array('Lorem ipsum dolor sit amet.', 'Consectetur adipisicing eli.')
	 */
	public static function sentences( $nb = 3, $as_text = false ) {
		static $keys = null;

		static $index = 0;

		static $prepared_sentences = null;

		if ( null === $keys ) {
			$keys = static::prepare_random_keys();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$index = mt_rand( 0, self::RANDOM_KEYS_COUNT - 1 );

			for ( $i = 0; $i < self::RANDOM_KEYS_COUNT; $i++ ) {
				$prepared_sentences[] = static::sentence();
			}
		}

		if ( ( $index + $nb ) > self::RANDOM_KEYS_COUNT ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$index = mt_rand( 0, self::RANDOM_KEYS_COUNT - $nb );
		}

		$sentences = [];
		$max_index = $index + $nb;

		while ( $index < $max_index ) {
			$sentences[] = $prepared_sentences[ $keys[ $index ] ];
			$index ++;
		}

		return $as_text ? implode( ' ', $sentences ) : $sentences;
	}

	/**
	 * Generate a single paragraph.
	 *
	 * @param integer $nb_sentences          Around how many sentences the paragraph should contain.
	 * @param boolean $variable_nb_sentences Set to false if you want exactly $nbSentences returned,
	 *                                       otherwise, $nb_sentences may vary by +/-40% with a minimum of 1.
	 *
	 * @return string
	 *
	 * @example 'Sapiente sunt omnis. Ut pariatur ad autem ducimus et. Voluptas rem voluptas sint modi dolorem amet.'
	 */
	public static function paragraph( $nb_sentences = 3, $variable_nb_sentences = true ) {
		if ( $nb_sentences <= 0 ) {
			return '';
		}

		if ( $variable_nb_sentences ) {
			$nb_sentences = self::randomize_nb_elements( $nb_sentences );
		}

		return implode( ' ', static::sentences( $nb_sentences ) );
	}

	/**
	 * Generate an array of paragraphs
	 *
	 * @param integer $nb      How many paragraphs to return.
	 * @param bool    $as_text If true, the paragraphs are returned as one string, separated by two newlines.
	 *
	 * @return array|string
	 *
	 * @example array($paragraph1, $paragraph2, $paragraph3)
	 */
	public static function paragraphs( $nb = 3, $as_text = false ) {
		$paragraphs = array();

		for ( $i = 0; $i < $nb; $i ++ ) {
			$paragraphs [] = static::paragraph();
		}

		return $as_text ? implode( "\n\n", $paragraphs ) : $paragraphs;
	}

	/**
	 * Generate a text string.
	 * Depending on the $max_num_chars, returns a string made of words, sentences, or paragraphs.
	 *
	 * @param int $max_num_chars Maximum number of characters the text should contain (minimum 5).
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException InvalidArgumentException.
	 * @example 'Sapiente sunt omnis. Ut pariatur ad autem ducimus et. Voluptas rem voluptas sint modi dolorem amet.'
	 */
	public static function text( $max_num_chars = 200 ) {
		if ( $max_num_chars < 5 ) {
			throw new InvalidArgumentException( 'Method text() can only generate text of at least 5 characters.' );
		}

		$type = ( $max_num_chars < 100 ) ? 'sentence' : 'paragraph';
		$type = ( $max_num_chars < 25 ) ? 'word' : $type;

		$text = [];
		while ( empty( $text ) ) {
			$size = 0;

			// Until $max_num_chars is reached.
			while ( $size < $max_num_chars ) {
				$word   = ( $size ? ' ' : '' ) . static::$type();
				$text[] = $word;

				$size += strlen( $word );
			}

			array_pop( $text );
		}

		if ( 'word' === $type ) {
			// Capitalize first letter.
			$text[0] = ucwords( $text[0] );

			// End the sentence with full stop.
			$text[ count( $text ) - 1 ] .= '.';
		}

		return implode( '', $text );
	}

	/**
	 * Randomize number of elements.
	 *
	 * @param integer $nb_elements Number of elements.
	 *
	 * @return int
	 */
	protected static function randomize_nb_elements( $nb_elements ) {

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		return (int) ( $nb_elements * mt_rand( 60, 140 ) / 100 ) + 1;
	}

	/**
	 * Prepare random keys.
	 *
	 * @return array
	 */
	private static function prepare_random_keys() {
		$keys      = [];
		$max_index = count( static::$word_list ) - 1;

		for ( $i = 0; $i < self::RANDOM_KEYS_COUNT; $i ++ ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$keys[] = mt_rand( 0, $max_index );
		}

		return $keys;
	}
}
