<?php
/**
 * Lorem class file.
 *
 * @package kagg/generator
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection SpellCheckingInspection */

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
	 * Name list.
	 *
	 * @var string[]
	 */
	protected static $name_list = [
		'Aaron',
		'Abel',
		'Abigail',
		'Abijah',
		'Abner',
		'Abraham',
		'Absalom',
		'Adaline',
		'Adam',
		'Adela',
		'Adelaide',
		'Adelbert',
		'Adele',
		'Adelia',
		'Adolphus',
		'Adrian',
		'Adrienne',
		'Agatha',
		'Agnes',
		'Aileen',
		'Alanson',
		'Alastair',
		'Alazama',
		'Albert',
		'Alberta',
		'Aleva',
		'Alexander',
		'Alexandra',
		'Alexandria',
		'Alexis',
		'Alfred',
		'Alfreda',
		'Algernon',
		'Alice',
		'Alicia',
		'Aline',
		'Alison',
		'Almena',
		'Almina',
		'Almira',
		'Alonzo',
		'Alphinias',
		'Alverta',
		'Alyssa',
		'Alzada',
		'Amanda',
		'Ambrose',
		'Amelia',
		'America',
		'Amos',
		'Anabelle',
		'Anastasia',
		'Andrea',
		'Andrew',
		'Angela',
		'Angelina',
		'Ann',
		'Anna',
		'Anne',
		'Annette',
		'Annie',
		'Anselm',
		'Anthony',
		'Antoinette',
		'Antonia',
		'Appoline',
		'Aquilla',
		'Arabella',
		'Arabelle',
		'Araminta',
		'Archibald',
		'Archilles',
		'Ariadne',
		'Arielle',
		'Aristotle',
		'Arizona',
		'Arlene',
		'Armanda',
		'Armida',
		'Armilda',
		'Arminda',
		'Arminta',
		'Arnold',
		'Artelepsa',
		'Artemus',
		'Arthur',
		'Arthusa',
		'Arzada',
		'Asenath',
		'Aubrey',
		'Audrey',
		'Augusta',
		'Augustina',
		'Augustine',
		'Augustus',
		'Aurelia',
		'Aurilla',
		'Avarilla',
		'Barbara',
		'Barbery',
		'Barnabas',
		'Barnard',
		'Bartholomew',
		'Barticus',
		'Bazaleel',
		'Beatrice',
		'Bedelia',
		'Belinda',
		'Benedict',
		'Bengta',
		'Bengtha',
		'Benjamin',
		'Bernard',
		'Bertha',
		'Bertram',
		'Bethena',
		'Beverly',
		'Blanche',
		'Boetius',
		'Brian',
		'Bridget',
		'Caldonia',
		'Caleb',
		'California',
		'Calista',
		'Calpurnia',
		'Calvin',
		'Cameron',
		'Camille',
		'Campbell',
		'Candace',
		'Carl',
		'Carlotta',
		'Carmellia',
		'Carmon',
		'Caroline',
		'Carolyn',
		'Carthaette',
		'Casper',
		'Cassandra',
		'Caswell',
		'Catherine',
		'Cathleen',
		'Cecilia',
		'Celeste',
		'Celinda',
		'Charity',
		'Charles',
		'Charlotte',
		'Chauncey',
		'Chesley',
		'Chester',
		'Chloe',
		'Christian',
		'Christiana',
		'Christina',
		'Christine',
		'Christopher',
		'Cicely',
		'Cinderella',
		'Cinthia',
		'Clara',
		'Clarence',
		'Clarinda',
		'Clarissa',
		'Claudia',
		'Cleatus',
		'Clementine',
		'Clifford',
		'Clifton',
		'Cole',
		'Columbus',
		'Constance',
		'Cordelia',
		'Corey',
		'Corinne',
		'Cornelia',
		'Cornelius',
		'Cory',
		'Courtney',
		'Crystal',
		'Cynthia',
		'Cyrus',
		'Daisy',
		'Daniel',
		'Danielle',
		'Daphne',
		'David',
		'Deanne',
		'Deborah',
		'Deidre',
		'Delbert',
		'Delia',
		'Delilah',
		'Dell',
		'Della',
		'Delores',
		'Delpha',
		'Delphine',
		'Demaris',
		'Demerias ',
		'Democrates',
		'Denise',
		'Deuteronomy',
		'Diana',
		'Diane',
		'Dickson',
		'Doctor',
		'Dominic',
		'Dorinda',
		'Doris',
		'Dorothea',
		'Dorothy',
		'Douglas',
		'Drusilla',
		'Duncan',
		'Earnest',
		'Ebenezer',
		'Edgar',
		'Edith',
		'Edmund',
		'Edna',
		'Edward',
		'Edwin',
		'Edwina',
		'Egbert',
		'Eighta',
		'Eileen',
		'Elaine',
		'Elbert',
		'Elbertson',
		'Eleanor',
		'Elena',
		'Elenora',
		'Elenore',
		'Elias',
		'Elijah',
		'Eliphalel',
		'Elisa',
		'Elisabeth',
		'Elisha',
		'Eliza',
		'Elizabeth',
		'Ella',
		'Ellen',
		'Ellender',
		'Ellie',
		'Elminie',
		'Elmira',
		'Elnora',
		'Eloise',
		'Elsie',
		'Elvira',
		'Elwood',
		'Elysia',
		'Emanuel',
		'Emeline',
		'Emil',
		'Emily',
		'Ephraim',
		'Erasmus',
		'Eric',
		'Ernest',
		'Ernestine',
		'Erwin',
		'Eseneth',
		'Estella',
		'Esther',
		'Eudicy',
		'Eudora',
		'Eudoris',
		'Eugene',
		'Eugenia',
		'Eunice',
		'Euphemia',
		'Eurydice',
		'Eustacia',
		'Evangeline',
		'Evelyn',
		'Experience',
		'Ezekiel',
		'Faith',
		'Felicia',
		'Felicity',
		'Ferdinand',
		'Fidelia',
		'Florence',
		'Floyd',
		'Frances',
		'Francis',
		'Franklin',
		'Frederica',
		'Frederick',
		'Gabriel',
		'Gabrielle',
		'Genevieve',
		'Geoffrey',
		'George',
		'Georgia',
		'Gerald',
		'Geraldine',
		'Gerhardt',
		'Gertrude',
		'Gilbert',
		'Gloria',
		'Governor',
		'Greenberry',
		'Gregory',
		'Gretchen',
		'Griselda',
		'Gustavus',
		'Gwendolyn',
		'Hamilton',
		'Hannah',
		'Harold',
		'Harriet',
		'Harriett',
		'Harry',
		'Haseltine',
		'Heather',
		'Helen',
		'Helen(a)',
		'Helena',
		'Heloise',
		'Henrietta',
		'Henry',
		'Hepsabah',
		'Hepsabel',
		'Hepsabeth',
		'Herbert',
		'Herman',
		'Hester',
		'Hezekiah',
		'Honora',
		'Honoria',
		'Horace',
		'Hortense',
		'Hosea',
		'Howard',
		'Hubert',
		'Ian',
		'Ignatius',
		'Ignatzio',
		'Immanuel',
		'India',
		'Inez',
		'Iona',
		'Irene',
		'Irvin',
		'Irwin',
		'Isaac',
		'Isabel',
		'Isabella',
		'Isabelle(a)',
		'Isadora',
		'Isaiah',
		'Isidore',
		'Iva',
		'Ivan',
		'Jackson',
		'Jacob',
		'Jacqueline',
		'James',
		'Jameson',
		'Jane',
		'Janet',
		'Jannett',
		'Jasper',
		'Jayme',
		'Jean',
		'Jeanette',
		'Jedediah',
		'Jeffrey',
		'Jehu',
		'Jemima',
		'Jennet',
		'Jennett',
		'Jennifer',
		'Jeremiah',
		'Jerita',
		'Jessica',
		'Jessie',
		'Jincy',
		'Jinsy',
		'Joanna',
		'Johannes',
		'John',
		'Jonathan',
		'Joseph',
		'Josepha',
		'Josephine',
		'Josetta',
		'Joshua',
		'Joyce',
		'Juanita',
		'Judah',
		'Judith',
		'Julia',
		'June',
		'Justin',
		'Karonhappuck',
		'Katarina',
		'Katherine',
		'Kathleen',
		'Kayla',
		'Kendra',
		'Keziah',
		'Kristel',
		'Kristine',
		'Kristopher',
		'Lafayette',
		'Laodicia',
		'Larena',
		'Lauren',
		'Laurena',
		'Laurinda',
		'Lauryn',
		'Laveda',
		'Laverne',
		'Lavinia',
		'Lavonia',
		'Lavonne',
		'Lawrence',
		'LeRoy',
		'Leanne',
		'Lecurgus',
		'Lemuel',
		'Leonard',
		'Leonidas',
		'Leonora',
		'Leonore',
		'Leslie',
		'Letitia',
		'Levicy',
		'Levone',
		'Lillian',
		'Lincoln',
		'Lionel',
		'Littleberry',
		'Lois',
		'Lorena',
		'Loretta',
		'Lorinda',
		'Lorraine',
		'Lotta',
		'Lotty',
		'Louis',
		'Louise',
		'Louvenia',
		'Louvinia',
		'Lucia',
		'Lucias',
		'Lucille',
		'Lucina',
		'Lucinda',
		'Lucretia',
		'Luella',
		'Lunetta',
		'Lurana',
		'Mabel',
		'Mac',
		'Mack',
		'Mackenzie',
		'Madeline',
		'Madison',
		'Magdalena',
		'Mahala',
		'Malachi',
		'Malcolm',
		'Malissa',
		'Manerva',
		'Manoah',
		'Manola',
		'Manuel',
		'Marcus',
		'Margaret',
		'Margaretha',
		'Margarita',
		'Mariah',
		'Marian',
		'Marilyn',
		'Marion',
		'Marissa',
		'Marjorie',
		'Marsha',
		'Martha',
		'Marvin',
		'Mary',
		'Mathilda',
		'Matilda',
		'Matthew',
		'Maureen',
		'Maurice',
		'Mavery ',
		'Mavine',
		'Maxine',
		'May',
		'Mc',
		'McKenna',
		'Medora',
		'Megan',
		'Mehitabel',
		'Melchizedek',
		'Melinda',
		'Melissa',
		'Mellony',
		'Melody',
		'Melvin',
		'Melvina',
		'Mercedes',
		'Micajah',
		'Michael',
		'Michelle',
		'Mildred',
		'Millicent',
		'Minerva',
		'Mirabel',
		'Miranda',
		'Miriam',
		'Mitchell',
		'Mitzi',
		'Monet',
		'Monica',
		'Monteleon',
		'Montesque',
		'Montgomery',
		'Mortimer',
		'Moses',
		'Muriel',
		'Myrtle',
		'Nadine',
		'Nancy',
		'Naomi',
		'Napoleon',
		'Natalie',
		'Natasha',
		'Nathaniel',
		'Nelson',
		'Nicholas',
		'Nicodemus',
		'Nicole',
		'Nora',
		'Nowell',
		'Obadiah',
		'Obedience',
		'Octavia',
		'Odell',
		'Olive',
		'Oliver',
		'Olivia',
		'Onicyphorous',
		'Orilla',
		'Orlando',
		'Orphelia',
		'Oswald',
		'Otis',
		'Pamela',
		'Pandora',
		'Parthenia',
		'Patience',
		'Patricia',
		'Patrick',
		'Paula',
		'Paulina',
		'Pauline',
		'Penelope',
		'Percival',
		'Permelia',
		'Pernetta',
		'Persephone',
		'Pharaba',
		'Pheney',
		'Pheriba',
		'Philadelphia',
		'Philander',
		'Philip',
		'Philipina',
		'Philomena',
		'Phoebe',
		'Pinckney',
		'Pleasant',
		'Pocahontas',
		'Posthuma',
		'Prescott',
		'Priscilla',
		'Providence',
		'Prudence',
		'Rachel',
		'Ramona',
		'Randolph',
		'Raphael',
		'Raymond',
		'ReFina',
		'Rebecca',
		'Regina',
		'Reginald',
		'Reuben',
		'Reynold',
		'Rhoda',
		'Rhodella',
		'Rhyna',
		'Richard',
		'Robert',
		'Roberta',
		'Roderick',
		'Roger',
		'Roland',
		'Ronald',
		'Rosabel',
		'Roscoe',
		'Rosina',
		'Roxane',
		'Rudolph',
		'Rufina',
		'Russell',
		'Ryan',
		'Sabrina',
		'Salome',
		'Salvador',
		'Samantha',
		'Sampson',
		'Samson',
		'Samuel',
		'Sandra',
		'Sanford',
		'Sarah',
		'Sarilla',
		'Sarina',
		'Savannah',
		'Scott',
		'Sebastian',
		'Selena',
		'Selma',
		'Serena',
		'Serilla',
		'Shaina',
		'Sharon',
		'Sheila',
		'Sheldon',
		'Sheridan',
		'Sibbell',
		'Sibbilla',
		'Sidney',
		'Sigfired',
		'Sigfrid',
		'Sigismund',
		'Silas',
		'Silence',
		'Silvester',
		'Simeon',
		'Socrates',
		'Solomon',
		'Sondra',
		'Sophronia',
		'Stephanie',
		'Stephen',
		'Submit',
		'Sullivan',
		'Susan',
		'Susanna',
		'Susannah',
		'Suzanne',
		'Sybil',
		'Sybill',
		'Sylvester',
		'Tabitha',
		'Tamarra',
		'Tanafra',
		'Tasha',
		'Temperance',
		'Terence',
		'Teresa',
		'Thaddeus',
		'Theodocius',
		'Theodora',
		'Theodore',
		'Theodosia',
		'Theophilus',
		'Theotha',
		'Theresa',
		'Thomas',
		'Thomasa',
		'Thomasine',
		'Tiffany',
		'Tilford',
		'Timothy',
		'Tobias',
		'Tranquilla',
		'Unice',
		'Uriah',
		'Ursula',
		'Valentina',
		'Valentine',
		'Valerie',
		'VanBuren',
		'Vandalia',
		'Vanessa',
		'Vernisee',
		'Veronica',
		'Victor',
		'Victoria',
		'Vincent',
		'Viola',
		'Violetta',
		'Virginia',
		'Vivian',
		'Waldo',
		'Wallace',
		'Walter',
		'Webster',
		'Wendy',
		'Wilber',
		'Wilda ',
		'Wilfred',
		'Wilhelmina',
		'William',
		'Willis',
		'Winefred',
		'Winfield',
		'Winifred',
		'Winton',
		'Woodrow',
		'Yeona',
		'Yulan',
		'Yvonne',
		'Zachariah',
		'Zadock',
		'Zaven',
		'Zebulon',
		'Zedediah',
		'Zelphia',
		'Zepaniah',
		'Zina',
	];

	/**
	 * Get the name list.
	 *
	 * @return array
	 */
	public static function get_name_list(): array {
		return static::$name_list;
	}

	/**
	 * Generate a word.
	 *
	 * @example 'Lorem'
	 * @return string
	 */
	public static function word(): string {
		return static::$word_list[ array_rand( static::$word_list ) ];
	}

	/**
	 * Generate an array of random words.
	 *
	 * @param integer $nb      How many words to return.
	 * @param bool    $as_text If true, the sentences are returned as one string.
	 *
	 * @return array|string
	 *
	 * @example array('Lorem', 'ipsum', 'dolor')
	 * @noinspection RandomApiMigrationInspection
	 */
	public static function words( int $nb = 3, bool $as_text = false ) {
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
			++$index;
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
	public static function sentence( int $nb_words = 6, bool $variable_nb_words = true ): string {
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
	 * @noinspection RandomApiMigrationInspection
	 */
	public static function sentences( int $nb = 3, bool $as_text = false ) {
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
			++$index;
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
	public static function paragraph( int $nb_sentences = 3, bool $variable_nb_sentences = true ): string {
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
	public static function paragraphs( int $nb = 3, bool $as_text = false ) {
		$paragraphs = [];

		for ( $i = 0; $i < $nb; $i++ ) {
			$paragraphs [] = static::paragraph();
		}

		return $as_text ? implode( "\n\n", $paragraphs ) : $paragraphs;
	}

	/**
	 * Generate a text string.
	 * Depending on the $max_num_chars, returns a string made of words, sentences, or paragraphs.
	 *
	 * @param int $max_num_chars The maximum number of characters the text should contain (minimum 5).
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException InvalidArgumentException.
	 * @example 'Sapiente sunt omnis. Ut pariatur ad autem ducimus et. Voluptas rem voluptas sint modi dolorem amet.'
	 */
	public static function text( int $max_num_chars = 200 ): string {
		if ( $max_num_chars < 5 ) {
			throw new InvalidArgumentException( 'Method text() can only generate text of at least 5 characters.' );
		}

		$type = ( $max_num_chars < 100 ) ? 'sentence' : 'paragraph';
		$type = ( $max_num_chars < 25 ) ? 'word' : $type;

		$text = [];
		while ( empty( $text ) ) {
			$text = self::get_text( $max_num_chars, $type, $text );
		}

		return self::text_array_to_string( $type, $text );
	}

	/**
	 * Randomize number of elements.
	 *
	 * @param integer $nb_elements Number of elements.
	 *
	 * @return int
	 * @noinspection RandomApiMigrationInspection
	 */
	protected static function randomize_nb_elements( int $nb_elements ): int {

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		return (int) ( $nb_elements * mt_rand( 60, 140 ) / 100 ) + 1;
	}

	/**
	 * Prepare random keys.
	 *
	 * @return array
	 * @noinspection RandomApiMigrationInspection
	 */
	private static function prepare_random_keys(): array {
		$keys      = [];
		$max_index = count( static::$word_list ) - 1;

		for ( $i = 0; $i < self::RANDOM_KEYS_COUNT; $i++ ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$keys[] = mt_rand( 0, $max_index );
		}

		return $keys;
	}

	/**
	 * Get text.
	 *
	 * @param int    $max_num_chars Max number of characters.
	 * @param string $type          Text type.
	 * @param array  $text          Text.
	 *
	 * @return array
	 */
	private static function get_text( int $max_num_chars, string $type, array $text ): array {
		$size = 0;

		// Until $max_num_chars is reached.
		while ( $size < $max_num_chars ) {
			$word   = ( $size ? ' ' : '' ) . static::$type();
			$text[] = $word;

			$size += strlen( $word );
		}

		array_pop( $text );

		return $text;
	}

	/**
	 * Convert a text array to string.
	 *
	 * @param string $type Text element type.
	 * @param array  $text Text.
	 *
	 * @return string
	 */
	private static function text_array_to_string( string $type, array $text ): string {
		if ( 'word' === $type ) {
			// Capitalize a first letter.
			$text[0] = strtoupper( $text[0] );

			// End the sentence with full stop.
			$text[ count( $text ) - 1 ] .= '.';
		}

		return implode( '', $text );
	}
}
