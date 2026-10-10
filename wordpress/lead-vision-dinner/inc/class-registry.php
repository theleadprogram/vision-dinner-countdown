<?php
/**
 * Registration rules: what each form does to the Guests and Table Leaders
 * boards.
 *
 * Emails are sent by Monday.com automations, not by WordPress. The site only
 * flips a status column to "Send" (Confirmation on Guests, Link email on Table
 * Leaders) and the automation does the rest. See README "Monday automations".
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field-level validation failure. getErrors() maps field name => message.
 */
class Validation_Exception extends \RuntimeException {

	/** @var array */
	private $errors;

	/**
	 * @param array $errors Field => message.
	 */
	public function __construct( array $errors ) {
		parent::__construct( 'Validation failed' );
		$this->errors = $errors;
	}

	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}
}

/**
 * The private link is missing, wrong or revoked.
 */
class Token_Exception extends \RuntimeException {}

class Registry {

	const TABLE_SIZE     = 10;
	const LEADERS_CACHE  = 'lead_vd_leaders';
	const LISTED_STATUS  = array( 'Registered', 'Active', 'Packet delivered' );

	/* ---------------------------------------------------------------------
	 * Table Leader search
	 * ------------------------------------------------------------------ */

	/**
	 * Table Leaders for the search: Registered, Active or Packet delivered,
	 * sorted by name, with seats left. Cached for about a minute.
	 *
	 * @return array[] Each: id, name, church, seats_left.
	 */
	public static function leaders() {
		$cached = get_transient( self::LEADERS_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$tl     = Config\board( 'table_leaders' );
		$counts = self::seat_counts();
		$items  = Monday::all_items(
			$tl,
			'id name column_values(ids: ["' . Config\column( 'table_leaders', 'status' ) . '", "' . Config\column( 'table_leaders', 'church' ) . '"]) { id text }'
		);

		$leaders = array();
		foreach ( $items as $item ) {
			if ( ! in_array( Monday::text( $item, Config\column( 'table_leaders', 'status' ) ), self::LISTED_STATUS, true ) ) {
				continue;
			}
			$filled    = isset( $counts[ $item['id'] ] ) ? $counts[ $item['id'] ] : 0;
			$leaders[] = array(
				'id'         => (string) $item['id'],
				'name'       => (string) $item['name'],
				'church'     => Monday::text( $item, Config\column( 'table_leaders', 'church' ) ),
				'seats_left' => max( 0, self::TABLE_SIZE - $filled ),
			);
		}

		usort(
			$leaders,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		set_transient( self::LEADERS_CACHE, $leaders, 60 );
		return $leaders;
	}

	/**
	 * Seats filled per Table Leader item ID, read from the Guests board.
	 *
	 * A seat is any non-cancelled guest connected to the Table Leader, other
	 * than the Table Leader themselves (their spouse/+1 does take a seat).
	 *
	 * @return array Table Leader item ID => count.
	 */
	private static function seat_counts() {
		$status = Config\column( 'guests', 'status' );
		$role   = Config\column( 'guests', 'role' );
		$link   = Config\column( 'guests', 'table_leader' );

		$items = Monday::all_items(
			Config\board( 'guests' ),
			'id column_values(ids: ["' . $status . '", "' . $role . '", "' . $link . '"]) { id text ... on BoardRelationValue { linked_item_ids } }'
		);

		$counts = array();
		foreach ( $items as $item ) {
			if ( ! self::takes_a_seat( $item ) ) {
				continue;
			}
			foreach ( Monday::linked_ids( $item, $link ) as $leader_id ) {
				$counts[ $leader_id ] = ( isset( $counts[ $leader_id ] ) ? $counts[ $leader_id ] : 0 ) + 1;
			}
		}

		return $counts;
	}

	/**
	 * @param array $item Guests item with status and role column values.
	 * @return bool
	 */
	private static function takes_a_seat( array $item ) {
		return 'Cancelled' !== Monday::text( $item, Config\column( 'guests', 'status' ) )
			&& 'Table Leader' !== Monday::text( $item, Config\column( 'guests', 'role' ) );
	}

	/* ---------------------------------------------------------------------
	 * Public registration form
	 * ------------------------------------------------------------------ */

	/**
	 * Register a guest or a Table Leader (and their spouse or guest).
	 *
	 * @param array $in Request fields.
	 * @return array What the thank-you screen needs.
	 * @throws Validation_Exception When a field is invalid.
	 */
	public static function register( array $in ) {
		$role   = isset( $in['role'] ) && 'leader' === $in['role'] ? 'leader' : 'guest';
		$errors = array();
		$person = self::clean_person( $in, '', true, $errors );
		$plus   = ! empty( $in['plus_one'] ) ? self::clean_person( $in, 'plus_', false, $errors ) : null;

		$address = self::clean_address( $in );
		$leader  = null;
		$seat_me = false;

		if ( 'guest' === $role ) {
			$seat_me = ! empty( $in['seat_me'] );
			if ( ! $seat_me ) {
				$leader = self::find_leader( isset( $in['leader_id'] ) ? (string) $in['leader_id'] : '' );
				if ( ! $leader ) {
					$errors['leader'] = 'Choose your Table Leader, or "Please seat me".';
				}
			}
		}

		if ( $errors ) {
			throw new Validation_Exception( $errors );
		}

		return 'leader' === $role
			? self::register_leader( $person, $plus, $address )
			: self::register_guest( $person, $plus, $address, $leader, ! empty( $in['interest'] ) );
	}

	/**
	 * @param array      $person  Clean person.
	 * @param array|null $plus    Clean spouse/+1.
	 * @param string     $address Mailing address.
	 * @param array|null $leader  Table Leader (id, name) or null for "seat me".
	 * @param bool       $interest Wants to hear about being a Table Leader.
	 * @return array
	 */
	private static function register_guest( array $person, $plus, $address, $leader, $interest ) {
		$g         = Config\board( 'guests' );
		$leader_id = $leader ? $leader['id'] : null;
		$existing  = self::find_guest( 'email', $person['email'] );
		$party     = $existing && '' !== $existing['party'] ? $existing['party'] : self::party_id();
		$before    = $existing ? $existing['leader_ids'] : array();

		$values = self::person_values( $person, $address ) + array(
			Config\column( 'guests', 'status' )                => Monday::status( 'Registered' ),
			Config\column( 'guests', 'table_leader' )          => Monday::relation( $leader_id ),
			Config\column( 'guests', 'seating' )               => Monday::status( $leader_id ? 'Assigned' : 'Needs a seat' ),
			Config\column( 'guests', 'party_id' )              => $party,
			Config\column( 'guests', 'table_leader_interest' ) => $interest ? array( 'checked' => 'true' ) : null,
			Config\column( 'guests', 'registered_by' )         => Monday::status( 'Self' ),
		);

		// A Table Leader registering again as a guest keeps their role.
		if ( ! $existing || 'Table Leader' !== $existing['role'] ) {
			$values[ Config\column( 'guests', 'role' ) ] = Monday::status( 'Guest' );
		}

		$guest_id = self::save_guest( $existing, $person, $values );
		self::send_confirmation( $guest_id );

		if ( $plus ) {
			self::save_plus_one( $plus, $party, $address, $leader_id, $leader_id ? 'Assigned' : 'Needs a seat', 'Self' );
		}

		self::recount( array_unique( array_filter( array_merge( $before, array( $leader_id ) ) ) ) );

		return array(
			'role'  => 'guest',
			'table' => $leader ? $leader['name'] . "'s table" : "We'll seat you and let you know",
		);
	}

	/**
	 * @param array      $person  Clean person.
	 * @param array|null $plus    Clean spouse.
	 * @param string     $address Mailing address.
	 * @return array
	 */
	private static function register_leader( array $person, $plus, $address ) {
		$tl       = Config\board( 'table_leaders' );
		$existing = self::find_leader_by_email( $person['email'] );
		$name     = self::household_name( $person, $plus );

		if ( $existing ) {
			$leader_id = $existing['id'];
			$token     = '' !== $existing['token'] ? $existing['token'] : self::token();
			Monday::update_item(
				$tl,
				$leader_id,
				array(
					'name'                                         => $name,
					Config\column( 'table_leaders', 'church' )     => $person['church'],
					Config\column( 'table_leaders', 'mobile' )     => Monday::phone( $person['phone'] ),
					Config\column( 'table_leaders', 'guest_link_token' ) => $token,
					Config\column( 'table_leaders', 'guest_link' ) => Monday::link( self::table_url( $token ), 'Register my guests' ),
				)
			);
			Monday::add_update( $leader_id, 'Registered again on leadcma.org/dinner. Details updated from the form.' );
		} else {
			$token     = self::token();
			$leader_id = Monday::create_item(
				$tl,
				$name,
				array(
					Config\column( 'table_leaders', 'status' )     => Monday::status( 'Registered' ),
					Config\column( 'table_leaders', 'church' )     => $person['church'],
					Config\column( 'table_leaders', 'email' )      => Monday::email( $person['email'] ),
					Config\column( 'table_leaders', 'mobile' )     => Monday::phone( $person['phone'] ),
					Config\column( 'table_leaders', 'seats_filled' ) => '0',
					Config\column( 'table_leaders', 'guest_link_token' ) => $token,
					Config\column( 'table_leaders', 'guest_link' ) => Monday::link( self::table_url( $token ), 'Register my guests' ),
				)
			);
		}

		$guest    = self::find_guest( 'email', $person['email'] );
		$party    = $guest && '' !== $guest['party'] ? $guest['party'] : self::party_id();
		$guest_id = self::save_guest(
			$guest,
			$person,
			self::person_values( $person, $address ) + array(
				Config\column( 'guests', 'status' )        => Monday::status( 'Registered' ),
				Config\column( 'guests', 'role' )          => Monday::status( 'Table Leader' ),
				Config\column( 'guests', 'table_leader' )  => Monday::relation( $leader_id ),
				Config\column( 'guests', 'seating' )       => Monday::status( 'Assigned' ),
				Config\column( 'guests', 'party_id' )      => $party,
				Config\column( 'guests', 'registered_by' ) => Monday::status( 'Self' ),
				Config\column( 'guests', 'guest_link' )    => Monday::link( self::table_url( $token ), 'Register my guests' ),
			)
		);
		self::send_confirmation( $guest_id );

		if ( $plus ) {
			self::save_plus_one( $plus, $party, $address, $leader_id, 'Assigned', 'Self' );
		}

		self::recount( array( $leader_id ) );

		// Never hand a private link to whoever typed an existing Table
		// Leader's email. The confirmation email (sent to that address) has it.
		return array(
			'role'     => 'leader',
			'table'    => 'Your table · ' . self::TABLE_SIZE . ' seats',
			'link'     => $existing ? null : self::table_url( $token ),
			'returned' => (bool) $existing,
		);
	}

	/**
	 * Save a spouse/+1: update the one already in this party, or create one.
	 *
	 * @param array       $plus      Clean person.
	 * @param string      $party     Party ID.
	 * @param string      $address   Mailing address.
	 * @param string|null $leader_id Table Leader item ID.
	 * @param string      $seating   Seating label.
	 * @param string      $source    Registered by label.
	 */
	private static function save_plus_one( array $plus, $party, $address, $leader_id, $seating, $source ) {
		$existing = null;
		if ( '' !== $plus['email'] ) {
			$existing = self::find_guest( 'email', $plus['email'] );
		}
		if ( ! $existing ) {
			foreach ( self::find_guests( 'party_id', $party ) as $member ) {
				if ( 'Spouse/+1' === $member['role'] ) {
					$existing = $member;
					break;
				}
			}
		}

		$values = self::person_values( $plus, $address ) + array(
			Config\column( 'guests', 'status' )        => Monday::status( 'Registered' ),
			Config\column( 'guests', 'role' )          => Monday::status( 'Spouse/+1' ),
			Config\column( 'guests', 'table_leader' )  => Monday::relation( $leader_id ),
			Config\column( 'guests', 'seating' )       => Monday::status( $seating ),
			Config\column( 'guests', 'party_id' )      => $party,
			Config\column( 'guests', 'registered_by' ) => Monday::status( $source ),
		);

		$id = self::save_guest( $existing, $plus, $values );
		if ( '' !== $plus['email'] ) {
			self::send_confirmation( $id );
		}
	}

	/* ---------------------------------------------------------------------
	 * Private guest page (leadcma.org/dinner/table?t=…)
	 * ------------------------------------------------------------------ */

	/**
	 * The Table Leader and everyone registered at their table.
	 *
	 * @param string $token Private link token.
	 * @return array
	 * @throws Token_Exception When the token matches nobody.
	 */
	public static function table( $token ) {
		$leader = self::leader_by_token( $token );
		$guests = array();

		foreach ( self::guests_at( $leader ) as $g ) {
			$guests[] = array(
				'id'      => $g['id'],
				'first'   => $g['first'],
				'last'    => $g['last'],
				'email'   => $g['email'],
				'phone'   => $g['phone'],
				'dietary' => $g['dietary'],
				'access'  => $g['access'],
				'status'  => 'Cancelled' === $g['status'] ? 'Cancelled' : 'Registered',
			);
		}

		return array(
			'leader' => array(
				'name'  => $leader['name'],
				'first' => self::first_word( $leader['name'] ),
			),
			'size'   => self::TABLE_SIZE,
			'guests' => $guests,
		);
	}

	/**
	 * Add a guest from the private page.
	 *
	 * @param string $token Private link token.
	 * @param array  $in    Fields.
	 * @return array The refreshed table.
	 */
	public static function add_guest( $token, array $in ) {
		$leader = self::leader_by_token( $token );
		$errors = array();
		$person = self::clean_person( $in, '', false, $errors );
		if ( $errors ) {
			throw new Validation_Exception( $errors );
		}

		$existing = '' !== $person['email'] ? self::find_guest( 'email', $person['email'] ) : null;
		$before   = $existing ? $existing['leader_ids'] : array();
		$values   = self::person_values( $person, null ) + array(
			Config\column( 'guests', 'status' )        => Monday::status( 'Registered' ),
			Config\column( 'guests', 'table_leader' )  => Monday::relation( $leader['id'] ),
			Config\column( 'guests', 'seating' )       => Monday::status( 'Assigned' ),
			Config\column( 'guests', 'registered_by' ) => Monday::status( 'Table Leader link' ),
		);
		if ( ! $existing ) {
			$values[ Config\column( 'guests', 'role' ) ]     = Monday::status( 'Guest' );
			$values[ Config\column( 'guests', 'party_id' ) ] = self::party_id();
		}

		$id = self::save_guest( $existing, $person, $values );
		if ( '' !== $person['email'] ) {
			self::send_confirmation( $id );
		}
		if ( $existing ) {
			Monday::add_update( $id, 'Added to ' . $leader['name'] . "'s table from their private guest link." );
		}

		self::recount( array_unique( array_merge( $before, array( $leader['id'] ) ) ) );
		return self::table( $token );
	}

	/**
	 * Save changes to a guest at this table and re-send their confirmation.
	 *
	 * @param string $token    Private link token.
	 * @param string $guest_id Guests item ID.
	 * @param array  $in       Fields.
	 * @return array The refreshed table.
	 */
	public static function update_guest( $token, $guest_id, array $in ) {
		$leader = self::leader_by_token( $token );
		$guest  = self::guest_at( $leader, $guest_id );
		$errors = array();
		$person = self::clean_person( $in, '', false, $errors );
		if ( $errors ) {
			throw new Validation_Exception( $errors );
		}

		Monday::update_item(
			Config\board( 'guests' ),
			$guest['id'],
			array(
				'name'                                   => $person['first'] . ' ' . $person['last'],
				Config\column( 'guests', 'email' )         => Monday::email( $person['email'] ),
				Config\column( 'guests', 'mobile' )        => Monday::phone( $person['phone'] ),
				Config\column( 'guests', 'dietary' )       => $person['dietary'],
				Config\column( 'guests', 'accessibility' ) => $person['access'],
			)
		);
		if ( '' !== $person['email'] ) {
			self::send_confirmation( $guest['id'] );
		}

		return self::table( $token );
	}

	/**
	 * "Can't come · release their seat": Status = Cancelled, and a note the
	 * coordinator sees (a Monday automation notifies them on Cancelled).
	 *
	 * @param string $token    Private link token.
	 * @param string $guest_id Guests item ID.
	 * @return array The refreshed table.
	 */
	public static function release_guest( $token, $guest_id ) {
		$leader = self::leader_by_token( $token );
		$guest  = self::guest_at( $leader, $guest_id );

		Monday::update_item(
			Config\board( 'guests' ),
			$guest['id'],
			array( Config\column( 'guests', 'status' ) => Monday::status( 'Cancelled' ) )
		);
		Monday::add_update( $guest['id'], 'Seat released by ' . $leader['name'] . ' from their private guest link.' );

		self::recount( array( $leader['id'] ) );
		return self::table( $token );
	}

	/**
	 * "Lost your link?": if the email belongs to a Table Leader, flip Link
	 * email to Send so Monday emails the link to that address. Says nothing
	 * about whether the email matched.
	 *
	 * @param string $email Email typed.
	 */
	public static function resend_link( $email ) {
		$email = strtolower( trim( $email ) );
		if ( ! is_email( $email ) ) {
			throw new Validation_Exception( array( 'email' => 'Enter a valid email address.' ) );
		}

		$leader = self::find_leader_by_email( $email );
		if ( ! $leader ) {
			return;
		}

		if ( '' === $leader['token'] ) {
			$token = self::token();
			Monday::update_item(
				Config\board( 'table_leaders' ),
				$leader['id'],
				array(
					Config\column( 'table_leaders', 'guest_link_token' ) => $token,
					Config\column( 'table_leaders', 'guest_link' )       => Monday::link( self::table_url( $token ), 'Register my guests' ),
				)
			);
		}

		self::trigger( Config\board( 'table_leaders' ), $leader['id'], Config\column( 'table_leaders', 'link_email' ) );
	}

	/* ---------------------------------------------------------------------
	 * Lookups
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $token Private link token.
	 * @return array id, name.
	 * @throws Token_Exception When nothing matches.
	 */
	private static function leader_by_token( $token ) {
		$token = (string) $token;
		if ( ! preg_match( '/\A[a-f0-9]{40}\z/', $token ) ) {
			throw new Token_Exception( 'Malformed token.' );
		}

		$column = Config\column( 'table_leaders', 'guest_link_token' );
		$status = Config\column( 'table_leaders', 'status' );
		$items  = Monday::find_by(
			Config\board( 'table_leaders' ),
			$column,
			$token,
			'id name column_values(ids: ["' . $column . '", "' . $status . '"]) { id text }'
		);

		foreach ( $items as $item ) {
			if ( hash_equals( Monday::text( $item, $column ), $token ) && 'Stepped back' !== Monday::text( $item, $status ) ) {
				return array(
					'id'   => (string) $item['id'],
					'name' => (string) $item['name'],
				);
			}
		}

		throw new Token_Exception( 'No Table Leader has this token.' );
	}

	/**
	 * A listed Table Leader by item ID (full tables included).
	 *
	 * @param string $id Item ID.
	 * @return array|null
	 */
	private static function find_leader( $id ) {
		if ( '' === $id ) {
			return null;
		}
		foreach ( self::leaders() as $leader ) {
			if ( $leader['id'] === $id ) {
				return $leader;
			}
		}
		// The cache may predate a brand-new Table Leader.
		delete_transient( self::LEADERS_CACHE );
		foreach ( self::leaders() as $leader ) {
			if ( $leader['id'] === $id ) {
				return $leader;
			}
		}
		return null;
	}

	/**
	 * @param string $email Email.
	 * @return array|null id, token.
	 */
	private static function find_leader_by_email( $email ) {
		$email_col = Config\column( 'table_leaders', 'email' );
		$token_col = Config\column( 'table_leaders', 'guest_link_token' );
		$items     = Monday::find_by(
			Config\board( 'table_leaders' ),
			$email_col,
			$email,
			'id column_values(ids: ["' . $email_col . '", "' . $token_col . '"]) { id text }'
		);

		foreach ( $items as $item ) {
			if ( 0 === strcasecmp( Monday::text( $item, $email_col ), $email ) ) {
				return array(
					'id'    => (string) $item['id'],
					'token' => Monday::text( $item, $token_col ),
				);
			}
		}
		return null;
	}

	/**
	 * GraphQL selection for a Guests item.
	 *
	 * @return string
	 */
	private static function guest_fields() {
		$ids = array();
		foreach ( array( 'status', 'role', 'table_leader', 'party_id', 'email', 'mobile', 'dietary', 'accessibility' ) as $key ) {
			$ids[] = '"' . Config\column( 'guests', $key ) . '"';
		}
		return 'id name column_values(ids: [' . implode( ', ', $ids ) . ']) { id text ... on BoardRelationValue { linked_item_ids } }';
	}

	/**
	 * @param array $item Raw Guests item.
	 * @return array
	 */
	private static function shape_guest( array $item ) {
		$parts = preg_split( '/\s+/', trim( (string) $item['name'] ), 2 );
		return array(
			'id'         => (string) $item['id'],
			'first'      => isset( $parts[0] ) ? $parts[0] : '',
			'last'       => isset( $parts[1] ) ? $parts[1] : '',
			'status'     => Monday::text( $item, Config\column( 'guests', 'status' ) ),
			'role'       => Monday::text( $item, Config\column( 'guests', 'role' ) ),
			'party'      => Monday::text( $item, Config\column( 'guests', 'party_id' ) ),
			'email'      => Monday::text( $item, Config\column( 'guests', 'email' ) ),
			'phone'      => self::format_phone( Monday::text( $item, Config\column( 'guests', 'mobile' ) ) ),
			'dietary'    => Monday::text( $item, Config\column( 'guests', 'dietary' ) ),
			'access'     => Monday::text( $item, Config\column( 'guests', 'accessibility' ) ),
			'leader_ids' => Monday::linked_ids( $item, Config\column( 'guests', 'table_leader' ) ),
		);
	}

	/**
	 * @param string $key   Logical column, e.g. 'email'.
	 * @param string $value Value.
	 * @return array[]
	 */
	private static function find_guests( $key, $value ) {
		if ( '' === $value ) {
			return array();
		}
		$column = Config\column( 'guests', $key );
		$found  = array();
		foreach ( Monday::find_by( Config\board( 'guests' ), $column, $value, self::guest_fields() ) as $item ) {
			$guest = self::shape_guest( $item );
			if ( 0 === strcasecmp( Monday::text( $item, $column ), $value ) ) {
				$found[] = $guest;
			}
		}
		return $found;
	}

	/**
	 * One record per person: the existing Guests item with this value.
	 *
	 * @param string $key   Logical column.
	 * @param string $value Value.
	 * @return array|null
	 */
	private static function find_guest( $key, $value ) {
		$found = self::find_guests( $key, $value );
		return $found ? $found[0] : null;
	}

	/**
	 * Everyone connected to a Table Leader except the Table Leader.
	 *
	 * @param array $leader id, name.
	 * @return array[]
	 */
	private static function guests_at( array $leader ) {
		$link  = Config\column( 'table_leaders', 'guests' );
		$items = Monday::items( array( $leader['id'] ), 'id column_values(ids: ["' . $link . '"]) { id ... on BoardRelationValue { linked_item_ids } }' );
		$ids   = $items ? Monday::linked_ids( $items[0], $link ) : array();

		$guests = array();
		foreach ( Monday::items( $ids, self::guest_fields() ) as $item ) {
			$guest = self::shape_guest( $item );
			if ( 'Table Leader' !== $guest['role'] && in_array( $leader['id'], $guest['leader_ids'], true ) ) {
				$guests[] = $guest;
			}
		}

		// Registered first, then cancelled; each in the order they were added.
		usort(
			$guests,
			function ( $a, $b ) {
				$ca = 'Cancelled' === $a['status'];
				$cb = 'Cancelled' === $b['status'];
				return $ca === $cb ? strcmp( str_pad( $a['id'], 20, '0', STR_PAD_LEFT ), str_pad( $b['id'], 20, '0', STR_PAD_LEFT ) ) : ( $ca ? 1 : -1 );
			}
		);

		return $guests;
	}

	/**
	 * A guest at this leader's table, or a Token_Exception.
	 *
	 * @param array  $leader   id, name.
	 * @param string $guest_id Item ID.
	 * @return array
	 */
	private static function guest_at( array $leader, $guest_id ) {
		foreach ( self::guests_at( $leader ) as $guest ) {
			if ( $guest['id'] === (string) $guest_id ) {
				return $guest;
			}
		}
		throw new Token_Exception( 'That guest is not at this table.' );
	}

	/* ---------------------------------------------------------------------
	 * Writes
	 * ------------------------------------------------------------------ */

	/**
	 * Create a Guests item, or update the existing one.
	 *
	 * @param array|null $existing Shaped guest or null.
	 * @param array      $person   Clean person.
	 * @param array      $values   Column values.
	 * @return string Item ID.
	 */
	private static function save_guest( $existing, array $person, array $values ) {
		$name = $person['first'] . ' ' . $person['last'];
		if ( $existing ) {
			Monday::update_item( Config\board( 'guests' ), $existing['id'], array( 'name' => $name ) + $values );
			return $existing['id'];
		}

		// Monday rejects null (clear) values on create.
		$values = array_filter(
			$values,
			function ( $v ) {
				return null !== $v && '' !== $v && array( 'item_ids' => array() ) !== $v;
			}
		);
		return Monday::create_item( Config\board( 'guests' ), $name, $values );
	}

	/**
	 * Flip Confirmation to Send so the Monday automation emails it.
	 *
	 * @param string $guest_id Item ID.
	 */
	private static function send_confirmation( $guest_id ) {
		self::trigger( Config\board( 'guests' ), $guest_id, Config\column( 'guests', 'confirmation' ) );
	}

	/**
	 * Clear a status column, then set it to Send. Clearing first means the
	 * "changes to Send" automation fires even if the last one never reset it.
	 *
	 * @param array  $board   Board config.
	 * @param string $item_id Item ID.
	 * @param string $column  Status column ID.
	 */
	private static function trigger( array $board, $item_id, $column ) {
		Monday::update_item( $board, $item_id, array( $column => null ) );
		Monday::update_item( $board, $item_id, array( $column => Monday::status( 'Send' ) ) );
	}

	/**
	 * Write Seats filled on each Table Leader and refresh the search cache.
	 *
	 * @param string[] $leader_ids Table Leader item IDs.
	 */
	private static function recount( array $leader_ids ) {
		delete_transient( self::LEADERS_CACHE );
		$leader_ids = array_filter( array_map( 'strval', $leader_ids ) );
		if ( ! $leader_ids ) {
			return;
		}

		$counts = self::seat_counts();
		foreach ( $leader_ids as $id ) {
			Monday::update_item(
				Config\board( 'table_leaders' ),
				$id,
				array( Config\column( 'table_leaders', 'seats_filled' ) => (string) ( isset( $counts[ $id ] ) ? $counts[ $id ] : 0 ) )
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Input cleaning
	 * ------------------------------------------------------------------ */

	/**
	 * Clean and validate one person's fields.
	 *
	 * @param array  $in       Request.
	 * @param string $prefix   '' or 'plus_'.
	 * @param bool   $primary  Primary registrant: all of name, email, phone required.
	 * @param array  $errors   Collected errors (by reference).
	 * @return array
	 */
	private static function clean_person( array $in, $prefix, $primary, array &$errors ) {
		$get = function ( $key ) use ( $in, $prefix ) {
			return isset( $in[ $prefix . $key ] ) ? sanitize_text_field( wp_unslash( (string) $in[ $prefix . $key ] ) ) : '';
		};

		$person = array(
			'first'   => $get( 'first' ),
			'last'    => $get( 'last' ),
			'email'   => strtolower( $get( 'email' ) ),
			'phone'   => self::phone_digits( $get( 'phone' ) ),
			'church'  => $get( 'church' ),
			'dietary' => $get( 'dietary' ),
			'access'  => $get( 'access' ),
		);

		if ( '' === $person['first'] ) {
			$errors[ $prefix . 'first' ] = 'Enter a first name.';
		}
		if ( $primary || 'plus_' === $prefix ) {
			if ( '' === $person['last'] ) {
				$errors[ $prefix . 'last' ] = 'Enter a last name.';
			}
		}
		if ( $primary && '' === $person['email'] ) {
			$errors[ $prefix . 'email' ] = 'Enter your email.';
		} elseif ( '' !== $person['email'] && ! is_email( $person['email'] ) ) {
			$errors[ $prefix . 'email' ] = 'Enter a valid email address.';
		}
		$raw_phone = $get( 'phone' );
		if ( $primary && 10 !== strlen( $person['phone'] ) ) {
			$errors[ $prefix . 'phone' ] = 'Enter a 10-digit mobile number.';
		} elseif ( ! $primary && '' !== $raw_phone && 10 !== strlen( $person['phone'] ) ) {
			$errors[ $prefix . 'phone' ] = 'Enter a 10-digit mobile number.';
		}

		foreach ( $person as $key => $value ) {
			$person[ $key ] = mb_substr( $value, 0, 200 );
		}

		return $person;
	}

	/**
	 * @param array $in Request.
	 * @return string Multi-line address, or ''.
	 */
	private static function clean_address( array $in ) {
		$get    = function ( $key ) use ( $in ) {
			return isset( $in[ $key ] ) ? mb_substr( sanitize_text_field( wp_unslash( (string) $in[ $key ] ) ), 0, 200 ) : '';
		};
		$street = $get( 'street' );
		$line2  = trim( $get( 'city' ) . ( '' !== $get( 'state' ) ? ', ' . strtoupper( $get( 'state' ) ) : '' ) . ' ' . $get( 'zip' ) );
		$line2  = trim( $line2, ', ' );
		return trim( $street . "\n" . $line2 );
	}

	/**
	 * Common Guests columns for a person.
	 *
	 * @param array       $person  Clean person.
	 * @param string|null $address Mailing address, or null to leave it alone.
	 * @return array
	 */
	private static function person_values( array $person, $address ) {
		$values = array(
			Config\column( 'guests', 'email' )         => Monday::email( $person['email'] ),
			Config\column( 'guests', 'mobile' )        => Monday::phone( $person['phone'] ),
			Config\column( 'guests', 'dietary' )       => $person['dietary'],
			Config\column( 'guests', 'accessibility' ) => $person['access'],
		);
		if ( '' !== $person['church'] ) {
			$values[ Config\column( 'guests', 'church' ) ] = $person['church'];
		}
		if ( null !== $address && '' !== $address ) {
			$values[ Config\column( 'guests', 'mailing_address' ) ] = array( 'text' => $address );
		}
		return $values;
	}

	/* ---------------------------------------------------------------------
	 * Small helpers
	 * ------------------------------------------------------------------ */

	/**
	 * US 10 digits; a leading 1 is dropped.
	 *
	 * @param string $raw Typed phone.
	 * @return string
	 */
	public static function phone_digits( $raw ) {
		$digits = preg_replace( '/\D/', '', (string) $raw );
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		return substr( $digits, 0, 10 );
	}

	/**
	 * (330) 555-0100 for display.
	 *
	 * @param string $raw Stored phone.
	 * @return string
	 */
	public static function format_phone( $raw ) {
		$d = self::phone_digits( $raw );
		return 10 === strlen( $d ) ? '(' . substr( $d, 0, 3 ) . ') ' . substr( $d, 3, 3 ) . '-' . substr( $d, 6 ) : (string) $raw;
	}

	/**
	 * "David & Ruth Mwangi", "David Mwangi & Ruth Otieno", or "David Mwangi".
	 *
	 * @param array      $person Primary.
	 * @param array|null $plus   Spouse/guest.
	 * @return string
	 */
	public static function household_name( array $person, $plus ) {
		if ( ! $plus ) {
			return $person['first'] . ' ' . $person['last'];
		}
		if ( 0 === strcasecmp( $person['last'], $plus['last'] ) ) {
			return $person['first'] . ' & ' . $plus['first'] . ' ' . $person['last'];
		}
		return $person['first'] . ' ' . $person['last'] . ' & ' . $plus['first'] . ' ' . $plus['last'];
	}

	/**
	 * @param string $name "David & Ruth Mwangi".
	 * @return string "David".
	 */
	private static function first_word( $name ) {
		$parts = preg_split( '/[\s&]+/', trim( $name ) );
		return $parts ? $parts[0] : $name;
	}

	/**
	 * 160-bit unguessable token, hex.
	 *
	 * @return string
	 */
	private static function token() {
		return bin2hex( random_bytes( 20 ) );
	}

	/**
	 * @return string e.g. "P-7K3Q9X2M".
	 */
	private static function party_id() {
		return 'P-' . strtoupper( wp_generate_password( 8, false, false ) );
	}

	/**
	 * @param string $token Token.
	 * @return string
	 */
	public static function table_url( $token ) {
		return add_query_arg( 't', $token, Settings\get( 'table_page_url' ) );
	}
}
