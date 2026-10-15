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

	/** Shown when a guest has no email of their own. */
	const OWN_EMAIL = 'Each guest needs their own email for their confirmation and reminders. If your guest doesn\'t have one, call 1-866-LEADCMA and we\'ll register them.';

	/** Shown when an email is already someone else's. */
	const EMAIL_TAKEN = 'That email belongs to someone else.';
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
			'id name column_values(ids: ["' . Config\column( 'table_leaders', 'status' ) . '", "' . Config\column( 'table_leaders', 'church' ) . '"]) { id text ... on MirrorValue { display_value } }'
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
	 * A seat is anyone connected to the Table Leader who isn't cancelled,
	 * the Table Leader and their spouse/+1 included.
	 *
	 * @return array Table Leader item ID => count.
	 */
	private static function seat_counts() {
		$status = Config\column( 'guests', 'status' );
		$role   = Config\column( 'guests', 'role' );
		$link   = Config\column( 'guests', 'table_leader' );

		$items = Monday::all_items(
			Config\board( 'guests' ),
			'id column_values(ids: ["' . $status . '", "' . $role . '", "' . $link . '"]) { id text ... on BoardRelationValue { linked_item_ids } ... on MirrorValue { display_value } }'
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
		return 'Cancelled' !== Monday::text( $item, Config\column( 'guests', 'status' ) );
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
		// A live-call registration opens from a recruiting-board item's private
		// link, so it's always a Table Leader and the contact is already known.
		$prospect = isset( $in['r'] ) && '' !== (string) $in['r'] ? self::prospect_by_token( (string) $in['r'] ) : null;
		$role     = $prospect || ( isset( $in['role'] ) && 'leader' === $in['role'] ) ? 'leader' : 'guest';
		$errors   = array();
		$person = self::clean_person( $in, '', true, $errors );
		$plus   = ! empty( $in['plus_one'] ) ? self::clean_person( $in, 'plus_', false, $errors ) : null;
		// Every person has their own email: it's how they're matched to All
		// Contacts and where their confirmation and reminders go.
		if ( $plus && ! isset( $errors['plus_email'] ) && ( '' === $plus['email'] || 0 === strcasecmp( $plus['email'], $person['email'] ) ) ) {
			$errors['plus_email'] = self::OWN_EMAIL;
		}

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

		if ( ! $errors ) {
			if ( self::email_used_by_other( $person['email'], $person['first'] ) ) {
				$errors['email'] = self::EMAIL_TAKEN;
			}
			if ( $plus && self::email_used_by_other( $plus['email'], $plus['first'] ) ) {
				$errors['plus_email'] = self::EMAIL_TAKEN;
			}
		}

		if ( $errors ) {
			throw new Validation_Exception( $errors );
		}

		if ( 'guest' === $role ) {
			return self::register_guest( $person, $plus, $address, $leader, ! empty( $in['interest'] ) );
		}

		$result = self::register_leader( $person, $plus, $address, $prospect );
		if ( $prospect ) {
			self::mark_prospect_yes( $prospect, $result );
		}
		return $result;
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
		$existing  = self::find_guest_for( $person['email'], $person['first'] );
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
		self::confirm( $guest_id, $person, $address );

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
	 * @param array      $person   Clean person.
	 * @param array|null $plus     Clean spouse.
	 * @param string     $address  Mailing address.
	 * @param array|null $prospect Recruiting-board item for a live-call registration.
	 * @return array
	 */
	private static function register_leader( array $person, $plus, $address, $prospect = null ) {
		$source = $prospect ? 'Staff' : 'Self';
		$tl       = Config\board( 'table_leaders' );
		$existing = self::find_leader_by_email( $person['email'] );
		$name     = self::household_name( $person, $plus );

		// Already a Table Leader: change nothing (anyone can type an email) and
		// email the private link to the address on file, as "Lost your link?" does.
		if ( $existing ) {
			self::send_link( $existing, $person );
			Monday::add_update( $existing['id'], 'Someone registered again on leadcma.org/dinner with this email. Nothing was changed; the private link was emailed to the address on file.' );
			return array(
				'role'     => 'leader',
				'table'    => 'Your table · ' . self::TABLE_SIZE . ' seats',
				'link'     => null,
				'returned' => true,
			);
		}

		$token     = self::token();
		$leader_id = Monday::create_item(
			$tl,
			$name,
			array(
				Config\column( 'table_leaders', 'status' )     => Monday::status( 'Registered' ),
				Config\column( 'table_leaders', 'first_name' ) => $person['first'],
				Config\column( 'table_leaders', 'email' )      => Monday::email( $person['email'] ),
				Config\column( 'table_leaders', 'seats_filled' ) => '0',
				Config\column( 'table_leaders', 'guest_link_token' ) => $token,
				Config\column( 'table_leaders', 'guest_link' ) => Monday::link( self::table_url( $token ), 'Register my guests' ),
			)
		);

		$guest    = self::find_guest_for( $person['email'], $person['first'] );
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
				Config\column( 'guests', 'registered_by' ) => Monday::status( $source ),
				Config\column( 'guests', 'guest_link' )    => Monday::link( self::table_url( $token ), 'Register my guests' ),
			)
		);
		$contact_id = self::confirm( $guest_id, $person, $address, true, $prospect ? $prospect['contact_id'] : null );
		if ( $contact_id && '' !== Config\column( 'table_leaders', 'contact' ) ) {
			Monday::update_item( $tl, $leader_id, array( Config\column( 'table_leaders', 'contact' ) => Monday::relation( $contact_id ) ) );
		}
		if ( $prospect && '' !== $prospect['email'] && 0 !== strcasecmp( $prospect['email'], $person['email'] ) ) {
			Monday::add_update( $guest_id, 'Registered on a call with the email ' . $person['email'] . '. All Contacts has ' . $prospect['email'] . ' and was not changed; update it there if the new one is right.' );
		}

		if ( $plus ) {
			self::save_plus_one( $plus, $party, $address, $leader_id, 'Assigned', $source );
		}

		self::recount( array( $leader_id ) );

		return array(
			'role'     => 'leader',
			'table'    => 'Your table · ' . self::TABLE_SIZE . ' seats',
			'link'     => self::table_url( $token ),
			'returned' => false,
		);
	}

	/**
	 * Save a spouse/+1: update their own Guests item (matched by their own
	 * email, or the +1 already in this party), or create one.
	 *
	 * @param array       $plus      Clean person, with their own email.
	 * @param string      $party     Party ID.
	 * @param string      $address   Mailing address (the registrant's).
	 * @param string|null $leader_id Table Leader item ID.
	 * @param string      $seating   Seating label.
	 * @param string      $source    Registered by label.
	 */
	private static function save_plus_one( array $plus, $party, $address, $leader_id, $seating, $source ) {
		$existing = self::find_guest_for( $plus['email'], $plus['first'] );
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
		self::confirm( $id, $plus, $address );
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
				'leader'  => 'Table Leader' === $g['role'],
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
		self::require_email( $person, $errors );
		if ( ! $errors && self::email_used_by_other( $person['email'], $person['first'] ) ) {
			$errors['email'] = self::EMAIL_TAKEN;
		}
		if ( $errors ) {
			throw new Validation_Exception( $errors );
		}

		$existing = self::find_guest_for( $person['email'], $person['first'] );
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
		self::confirm( $id, $person, null );
		if ( $existing ) {
			Monday::add_update( $id, 'Added to ' . $leader['name'] . "'s table from their private guest link." );
		}

		self::recount( array_unique( array_merge( $before, array( $leader['id'] ) ) ) );
		return self::table( $token );
	}

	/**
	 * Save a Table Leader's changes to a guest at their table and re-send the
	 * confirmation. The changes go to the guest's All Contacts record (the
	 * page warns before saving); the email can change, but never to one that
	 * belongs to someone else.
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
		self::require_email( $person, $errors );

		$contact_id = $guest['contact_ids'] ? $guest['contact_ids'][0] : null;
		if ( ! $errors && 0 !== strcasecmp( $person['email'], $guest['email'] ) ) {
			$other = self::find_contact( $person['email'] );
			if ( self::email_used_by_other( $person['email'], $person['first'], $guest['id'] ) || ( $other && (string) $other['id'] !== (string) $contact_id ) ) {
				$errors['email'] = self::EMAIL_TAKEN;
			}
		}
		if ( $errors ) {
			throw new Validation_Exception( $errors );
		}

		Monday::update_item(
			Config\board( 'guests' ),
			$guest['id'],
			array(
				'name'                                   => $person['first'] . ' ' . $person['last'],
				Config\column( 'guests', 'first_name' )    => $person['first'],
				Config\column( 'guests', 'email' )         => Monday::email( $person['email'] ),
				Config\column( 'guests', 'dietary' )       => $person['dietary'],
				Config\column( 'guests', 'accessibility' ) => $person['access'],
			)
		);

		if ( $contact_id ) {
			try {
				self::save_contact( $person, null, self::contact_by_id( $contact_id ), false, true );
			} catch ( Monday_Exception $e ) {
				self::note_failure( $guest['id'], 'The website could not update this guest\'s All Contacts record. Please update it by hand.', $e );
			}
		} else {
			self::link_contact( $guest['id'], $person, null );
		}
		self::send_confirmation( $guest['id'] );

		self::recount( array( $leader['id'] ) );
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
			self::without_dinner_date( array( Config\column( 'guests', 'status' ) => Monday::status( 'Cancelled' ) ) )
		);
		Monday::add_update( $guest['id'], 'Seat released by ' . $leader['name'] . ' from their private guest link.' );
		self::drop_campaign( $guest );

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
		if ( $leader ) {
			self::send_link( $leader, null );
		}
	}

	/**
	 * Email a Table Leader their private link (issuing one if they have
	 * none) by setting Link email to Send on their Table Leaders item.
	 *
	 * @param array      $leader id, email, token.
	 * @param array|null $person Unused; kept so both callers read the same.
	 */
	private static function send_link( array $leader, $person ) {
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
	 * Live-call registration (leadcma.org/dinner?r=…)
	 *
	 * Each item on the recruiting board gets a private Register link. Opened
	 * during a call, it prefills the form from the person's All Contacts
	 * record; submitting it registers them as a Table Leader, updates that
	 * same contact (never matched by email, so a new email can't create a
	 * duplicate) and sets the item's Stage to Yes.
	 * ------------------------------------------------------------------ */

	/**
	 * What the form needs to prefill a live-call registration.
	 *
	 * @param string $token Register token.
	 * @return array
	 * @throws Token_Exception When the token matches nobody.
	 */
	public static function prospect( $token ) {
		$p = self::prospect_by_token( $token );
		return array(
			'name'   => $p['name'],
			'first'  => $p['first'],
			'last'   => $p['last'],
			'email'  => $p['email'],
			'phone'  => self::format_phone( $p['phone'] ),
			'church' => $p['church'],
			'stage'  => $p['stage'],
		);
	}

	/**
	 * @param string $token Register token.
	 * @return array id, name, contact_id, first, last, email, phone, church, stage.
	 * @throws Token_Exception When the token matches nobody.
	 */
	private static function prospect_by_token( $token ) {
		$token = (string) $token;
		$board = Config\board( 'recruiting' );
		$col   = function ( $key ) {
			return Config\column( 'recruiting', $key );
		};
		if ( ! $board || '' === $col( 'register_token' ) || ! preg_match( '/\A[a-f0-9]{40}\z/', $token ) ) {
			throw new Token_Exception( 'Malformed register token.' );
		}

		$fields = 'id name column_values(ids: ["' . implode( '", "', array_filter( array( $col( 'register_token' ), $col( 'stage' ), $col( 'contact' ), $col( 'email' ), $col( 'mobile' ) ) ) ) . '"]) { id text ... on BoardRelationValue { linked_item_ids } ... on MirrorValue { display_value } }';
		foreach ( Monday::find_by( $board, $col( 'register_token' ), $token, $fields ) as $item ) {
			if ( ! hash_equals( Monday::text( $item, $col( 'register_token' ) ), $token ) ) {
				continue;
			}

			$parts    = preg_split( '/\s+/', trim( (string) $item['name'] ), 2 );
			$contacts = Monday::linked_ids( $item, $col( 'contact' ) );
			$p        = array(
				'id'         => (string) $item['id'],
				'name'       => (string) $item['name'],
				'contact_id' => $contacts ? $contacts[0] : null,
				'first'      => isset( $parts[0] ) ? $parts[0] : '',
				'last'       => isset( $parts[1] ) ? $parts[1] : '',
				'email'      => Monday::text( $item, $col( 'email' ) ),
				'phone'      => self::phone_digits( Monday::text( $item, $col( 'mobile' ) ) ),
				'church'     => '',
				'stage'      => Monday::text( $item, $col( 'stage' ) ),
			);

			// The contact is the master record: prefer its details.
			$contact = $p['contact_id'] ? self::contact_by_id( $p['contact_id'] ) : null;
			if ( $contact ) {
				foreach ( array( 'first' => 'first_name', 'last' => 'last_name', 'email' => 'email', 'church' => 'church' ) as $key => $column ) {
					$value = Monday::text( $contact, Config\column( 'all_contacts', $column ) );
					if ( '' !== $value ) {
						$p[ $key ] = $value;
					}
				}
				$mobile = self::phone_digits( Monday::text( $contact, Config\column( 'all_contacts', 'mobile' ) ) );
				if ( 10 === strlen( $mobile ) ) {
					$p['phone'] = $mobile;
				}
			} else {
				$p['contact_id'] = null;
			}
			return $p;
		}

		throw new Token_Exception( 'No recruiting item has this register token.' );
	}

	/**
	 * After a live-call registration: Stage = Yes and a note on the item.
	 * A failure here never fails the registration.
	 *
	 * @param array $prospect From prospect_by_token().
	 * @param array $result   What register_leader() returned.
	 */
	private static function mark_prospect_yes( array $prospect, array $result ) {
		$board = Config\board( 'recruiting' );
		$yes   = isset( $board['stage_yes'] ) ? (string) $board['stage_yes'] : 'Yes';
		try {
			if ( '' !== Config\column( 'recruiting', 'stage' ) ) {
				Monday::update_item( $board, $prospect['id'], array( Config\column( 'recruiting', 'stage' ) => Monday::status( $yes ) ) );
			}
			Monday::add_update(
				$prospect['id'],
				! empty( $result['returned'] )
					? 'Registered on a call from this item, but they were already a Table Leader, so nothing was changed. Their private guest link was emailed to the address on file.'
					: 'Registered as a Table Leader on a call from this item. Their confirmation and private guest link are on the way by email.'
			);
		} catch ( Monday_Exception $e ) {
			error_log( '[lead-vision-dinner] Could not mark recruiting item ' . $prospect['id'] . ' Yes: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/**
	 * Give every recruiting-board item without a Register link a new one.
	 *
	 * @return int How many links were made.
	 * @throws Monday_Exception On failure.
	 */
	public static function issue_register_links() {
		$board = Config\board( 'recruiting' );
		$token = Config\column( 'recruiting', 'register_token' );
		$link  = Config\column( 'recruiting', 'register_link' );
		if ( ! $board || '' === $token || '' === $link ) {
			return 0;
		}

		$made = 0;
		foreach ( Monday::all_items( $board, 'id column_values(ids: ["' . $token . '"]) { id text ... on MirrorValue { display_value } }' ) as $item ) {
			if ( preg_match( '/\A[a-f0-9]{40}\z/', Monday::text( $item, $token ) ) ) {
				continue;
			}
			$new = self::token();
			Monday::update_item(
				$board,
				(string) $item['id'],
				array(
					$token => $new,
					$link  => Monday::link( self::register_url( $new ), 'Register on a call' ),
				)
			);
			++$made;
		}
		return $made;
	}

	/**
	 * @param string $token Register token.
	 * @return string
	 */
	public static function register_url( $token ) {
		return add_query_arg( 'r', $token, Settings\get( 'register_page_url' ) );
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
			'id name column_values(ids: ["' . $column . '", "' . $status . '"]) { id text ... on MirrorValue { display_value } }'
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
			'id column_values(ids: ["' . $email_col . '", "' . $token_col . '"]) { id text ... on MirrorValue { display_value } }'
		);

		foreach ( $items as $item ) {
			if ( 0 === strcasecmp( Monday::text( $item, $email_col ), $email ) ) {
				return array(
					'id'    => (string) $item['id'],
					'email' => Monday::text( $item, $email_col ),
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
		foreach ( array( 'status', 'role', 'table_leader', 'party_id', 'email', 'mobile', 'dietary', 'accessibility', 'contact' ) as $key ) {
			$ids[] = '"' . Config\column( 'guests', $key ) . '"';
		}
		return 'id name column_values(ids: [' . implode( ', ', $ids ) . ']) { id text ... on BoardRelationValue { linked_item_ids } ... on MirrorValue { display_value } }';
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
			'leader_ids'  => Monday::linked_ids( $item, Config\column( 'guests', 'table_leader' ) ),
			'contact_ids' => Monday::linked_ids( $item, Config\column( 'guests', 'contact' ) ),
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
	 * Everyone connected to a Table Leader, the Table Leader first.
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
			if ( in_array( $leader['id'], $guest['leader_ids'], true ) ) {
				$guests[] = $guest;
			}
		}

		// The Table Leader, then registered guests, then cancelled; each group
		// in the order they were added.
		$rank = function ( $g ) {
			if ( 'Table Leader' === $g['role'] ) {
				return 0;
			}
			return 'Cancelled' === $g['status'] ? 2 : 1;
		};
		usort(
			$guests,
			function ( $a, $b ) use ( $rank ) {
				$ra = $rank( $a );
				$rb = $rank( $b );
				return $ra === $rb ? strcmp( str_pad( $a['id'], 20, '0', STR_PAD_LEFT ), str_pad( $b['id'], 20, '0', STR_PAD_LEFT ) ) : $ra - $rb;
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
			// The Table Leader's own record isn't editable from the link.
			if ( $guest['id'] === (string) $guest_id && 'Table Leader' !== $guest['role'] ) {
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
	 * Connect a Guests item to the person's All Contacts record (the master
	 * record), matched by email; create the contact when there is none.
	 *
	 * Registration data is trusted as typed and written to the contact (the
	 * dinner boards mirror it). The email is the match key and is never
	 * changed here. When the email's contact has a different first name, the
	 * guest is still linked, but that contact's details are left alone and a
	 * note asks for a check.
	 *
	 * A failure never fails the registration; it leaves a note on the
	 * Guests item, with Monday's message, so nothing fails silently.
	 *
	 * @param string      $guest_id Guests item ID.
	 * @param array       $person   Clean person.
	 * @param string|null $address  Mailing address, or null when not collected.
	 * @param string|null $known    All Contacts item ID already known (a live
	 *                              call from the recruiting board): used as is,
	 *                              even when the email typed differs from it.
	 * @return string|null All Contacts item ID.
	 */
	private static function link_contact( $guest_id, array $person, $address, $known = null ) {
		if ( ! Config\board( 'all_contacts' ) || '' === Config\column( 'guests', 'contact' ) || ( '' === $person['email'] && ! $known ) ) {
			return null;
		}

		try {
			$contact  = $known ? self::contact_by_id( $known ) : self::find_contact( $person['email'] );
			$mismatch = $contact && ! $known && ! self::same_person( Monday::text( $contact, Config\column( 'all_contacts', 'first_name' ) ), $person['first'] );

			$contact_id = self::save_contact( $person, $address, $contact, $mismatch );
			Monday::update_item( Config\board( 'guests' ), $guest_id, array( Config\column( 'guests', 'contact' ) => Monday::relation( $contact_id ) ) );

			if ( $mismatch ) {
				Monday::add_update(
					$guest_id,
					'Registered as ' . trim( $person['first'] . ' ' . $person['last'] ) . ' with ' . $person['email'] . ', which belongs to ' . $contact['name'] . ' in All Contacts. Linked to that contact without changing its name or details. Please check it.'
				);
			}
			return $contact_id;
		} catch ( Monday_Exception $e ) {
			self::note_failure( $guest_id, 'The website could not link this guest to All Contacts. Please link them by hand.', $e );
			return null;
		}
	}

	/**
	 * Log a Monday failure and leave a note on the item. Never throws.
	 *
	 * @param string           $item_id Item ID.
	 * @param string           $what    What went wrong, for the team.
	 * @param Monday_Exception $e       The failure.
	 */
	private static function note_failure( $item_id, $what, Monday_Exception $e ) {
		error_log( '[lead-vision-dinner] ' . $what . ' Item ' . $item_id . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		try {
			Monday::add_update( $item_id, $what . ' Monday said: ' . mb_substr( $e->getMessage(), 0, 400 ) );
		} catch ( Monday_Exception $ignored ) {
			// Already logged.
		}
	}

	/**
	 * Write a registration to All Contacts: update the contact given, or
	 * create one. Every registrant gets the dinner's Fundraising Campaign
	 * (added to any they have) and Marketing Status = Marketing contact
	 * when it's blank. Unsubscribed and Not marketing contact are never
	 * changed; Clint gets a Monday notification instead.
	 *
	 * @param array       $person       Clean person.
	 * @param string|null $address      Mailing address, or null.
	 * @param array|null  $contact      Existing contact (contact_fields()), or null to create.
	 * @param bool        $keep_details Don't change its name or details (someone else's email).
	 * @param bool        $write_email  Change the contact's email (a Table Leader's edit).
	 * @return string Item ID.
	 * @throws Monday_Exception On failure.
	 */
	private static function save_contact( array $person, $address, $contact, $keep_details = false, $write_email = false ) {
		$board = Config\board( 'all_contacts' );
		$col   = function ( $key ) {
			return Config\column( 'all_contacts', $key );
		};
		$name  = trim( $person['first'] . ' ' . $person['last'] );

		// What the registration says about this person. Blank values are
		// never written, so an empty form field can't wipe a contact's data.
		$details = array(
			$col( 'first_name' ) => $person['first'],
			$col( 'last_name' )  => $person['last'],
			$col( 'mobile' )     => '' !== $person['phone'] ? self::format_phone( $person['phone'] ) : '',
			$col( 'church' )     => $person['church'],
		);
		if ( '' !== $col( 'location' ) && null !== $address ) {
			$details[ $col( 'location' ) ] = trim( preg_replace( '/\s*\n\s*/', ', ', (string) $address ), ', ' );
		}
		$details = array_filter(
			$details,
			function ( $v ) {
				return '' !== $v;
			}
		);
		unset( $details[''] );

		$campaign = isset( $board['campaign_id'] ) ? (int) $board['campaign_id'] : 0;
		$mkt_col  = $col( 'marketing_status' );
		$mkt      = '' !== $mkt_col && isset( $board['marketing_status_index'] ) ? array( 'index' => (int) $board['marketing_status_index'] ) : null;

		if ( $contact ) {
			$values = $keep_details ? array() : $details;
			if ( ! $keep_details && '' !== $name ) {
				$values['name'] = $name;
			}
			if ( $write_email && '' !== $person['email'] ) {
				$values[ $col( 'email' ) ] = Monday::email( $person['email'] );
			}
			if ( $campaign && '' !== $col( 'campaigns' ) ) {
				$ids = Monday::dropdown_ids( $contact, $col( 'campaigns' ) );
				if ( ! in_array( $campaign, $ids, true ) ) {
					// Writing a dropdown replaces it, so send every current
					// value back with ours added (retired labels drop off).
					$keep                          = array_values( array_intersect( $ids, self::active_label_ids( $col( 'campaigns' ) ) ) );
					$keep[]                        = $campaign;
					$values[ $col( 'campaigns' ) ] = array( 'ids' => $keep );
				}
			}
			if ( $mkt ) {
				$current = Monday::text( $contact, $mkt_col );
				if ( '' === $current ) {
					$values[ $mkt_col ] = $mkt;
				} elseif ( 'Marketing contact' !== $current ) {
					self::notify_marketing( $contact, $current );
				}
			}
			Monday::update_item( $board, $contact['id'], $values );
			return (string) $contact['id'];
		}

		$values                    = $details;
		$values[ $col( 'email' ) ] = Monday::email( $person['email'] );
		if ( $campaign && '' !== $col( 'campaigns' ) ) {
			$values[ $col( 'campaigns' ) ] = array( 'ids' => array( $campaign ) );
		}
		if ( $mkt ) {
			$values[ $mkt_col ] = $mkt;
		}
		return Monday::create_item( $board, '' !== $name ? $name : $person['email'], $values );
	}

	/**
	 * A released seat: take the dinner's Fundraising Campaign off the guest's
	 * contact, so the dinner campaign stops reaching them. Their other
	 * campaigns stay. Kept when the same email still holds a seat at this
	 * dinner. Registering again adds it back. Never throws; a failure leaves
	 * a note.
	 *
	 * @param array $guest Shaped guest (shape_guest()).
	 */
	private static function drop_campaign( array $guest ) {
		$board    = Config\board( 'all_contacts' );
		$col      = Config\column( 'all_contacts', 'campaigns' );
		$campaign = $board && isset( $board['campaign_id'] ) ? (int) $board['campaign_id'] : 0;
		if ( ! $campaign || '' === $col || empty( $guest['contact_ids'] ) ) {
			return;
		}

		try {
			foreach ( self::find_guests( 'email', $guest['email'] ) as $other ) {
				if ( $other['id'] !== $guest['id'] && 'Cancelled' !== $other['status'] ) {
					return;
				}
			}

			foreach ( Monday::items( $guest['contact_ids'], self::contact_fields() ) as $contact ) {
				$ids = Monday::dropdown_ids( $contact, $col );
				if ( ! in_array( $campaign, $ids, true ) ) {
					continue;
				}
				// Writing a dropdown replaces it, so send the others back
				// (retired labels drop off); null clears it.
				$keep = array_values( array_diff( array_intersect( $ids, self::active_label_ids( $col ) ), array( $campaign ) ) );
				Monday::update_item( $board, $contact['id'], array( $col => $keep ? array( 'ids' => $keep ) : null ) );
			}
		} catch ( Monday_Exception $e ) {
			self::note_failure( $guest['id'], 'The website could not take this dinner off their Fundraising Campaigns in All Contacts. Please remove it by hand.', $e );
		}
	}

	/**
	 * A registrant's contact is Unsubscribed or Not marketing contact: leave
	 * it, and tell Clint (Monday bell, which Monday can also email).
	 *
	 * @param array  $contact All Contacts item.
	 * @param string $status  Current Marketing Status.
	 */
	private static function notify_marketing( array $contact, $status ) {
		$board = Config\board( 'all_contacts' );
		if ( empty( $board['notify_user_id'] ) ) {
			return;
		}
		try {
			Monday::notify(
				(string) $board['notify_user_id'],
				(string) $contact['id'],
				'Vision Dinner: ' . $contact['name'] . ' just registered, but their Marketing Status is "' . $status . '". It was not changed. Please decide whether to update it.'
			);
		} catch ( Monday_Exception $e ) {
			error_log( '[lead-vision-dinner] Marketing notice failed for contact ' . $contact['id'] . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/**
	 * Label ids of an All Contacts dropdown that are not deactivated
	 * (cached per request).
	 *
	 * @param string $column Dropdown column ID.
	 * @return int[]
	 * @throws Monday_Exception On failure.
	 */
	private static function active_label_ids( $column ) {
		static $cache = array();
		if ( ! isset( $cache[ $column ] ) ) {
			$data     = Monday::call(
				'query ($board: [ID!], $col: [String!]) { boards(ids: $board) { columns(ids: $col) { settings_str } } }',
				array(
					'board' => array( (string) Config\board( 'all_contacts' )['id'] ),
					'col'   => array( $column ),
				)
			);
			$settings = isset( $data['boards'][0]['columns'][0]['settings_str'] ) ? json_decode( (string) $data['boards'][0]['columns'][0]['settings_str'], true ) : array();
			$retired  = isset( $settings['deactivated_labels'] ) ? array_map( 'intval', (array) $settings['deactivated_labels'] ) : array();
			$ids      = array();
			foreach ( isset( $settings['labels'] ) ? $settings['labels'] : array() as $label ) {
				if ( isset( $label['id'] ) && empty( $label['is_deactivated'] ) && ! in_array( (int) $label['id'], $retired, true ) ) {
					$ids[] = (int) $label['id'];
				}
			}
			$cache[ $column ] = $ids;
		}
		return $cache[ $column ];
	}

	/**
	 * The All Contacts item with this email, or null.
	 *
	 * @param string $email Email.
	 * @return array|null Item with the identity column values.
	 */
	private static function find_contact( $email ) {
		if ( '' === $email || ! Config\board( 'all_contacts' ) ) {
			return null;
		}
		$email_col = Config\column( 'all_contacts', 'email' );
		foreach ( Monday::find_by( Config\board( 'all_contacts' ), $email_col, $email, self::contact_fields() ) as $item ) {
			if ( 0 === strcasecmp( Monday::text( $item, $email_col ), $email ) ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * The All Contacts item with this ID, or null.
	 *
	 * @param string $id Item ID.
	 * @return array|null
	 */
	private static function contact_by_id( $id ) {
		$items = Monday::items( array( (string) $id ), self::contact_fields() );
		return $items ? $items[0] : null;
	}

	/**
	 * GraphQL selection for an All Contacts item.
	 *
	 * @return string
	 */
	private static function contact_fields() {
		$ids = array();
		foreach ( array( 'email', 'campaigns', 'first_name', 'last_name', 'mobile', 'church', 'marketing_status' ) as $key ) {
			if ( '' !== Config\column( 'all_contacts', $key ) ) {
				$ids[] = Config\column( 'all_contacts', $key );
			}
		}
		return 'id name column_values(ids: ["' . implode( '", "', $ids ) . '"]) { id text value ... on MirrorValue { display_value } }';
	}

	/**
	 * Link a Guests item to its contact and set Confirmation to Send so the
	 * Monday automation emails the confirmation.
	 *
	 * @param string      $guest_id Guests item ID.
	 * @param array       $person   Clean person.
	 * @param string|null $address  Mailing address, or null.
	 * @param bool        $send     Send the confirmation.
	 * @param string|null $known    All Contacts item ID already known (live call).
	 * @return string|null All Contacts item ID.
	 */
	private static function confirm( $guest_id, array $person, $address, $send = true, $known = null ) {
		$contact_id = self::link_contact( $guest_id, $person, $address, $known );
		if ( $send && '' !== $person['email'] ) {
			self::send_confirmation( $guest_id );
		}
		return $contact_id;
	}

	/**
	 * Confirmation → Send on a Guests item (the Monday automation emails it).
	 * Never throws; a failure leaves a note.
	 *
	 * @param string $guest_id Guests item ID.
	 */
	private static function send_confirmation( $guest_id ) {
		try {
			self::trigger( Config\board( 'guests' ), $guest_id, Config\column( 'guests', 'confirmation' ) );
		} catch ( Monday_Exception $e ) {
			self::note_failure( $guest_id, 'The website could not set Confirmation to Send. Please send the confirmation by hand.', $e );
		}
	}

	/**
	 * Whether two first names are the same person's. A blank name can't tell
	 * them apart, so it counts as the same.
	 *
	 * @param string $a First name.
	 * @param string $b First name.
	 * @return bool
	 */
	private static function same_person( $a, $b ) {
		$first = function ( $name ) {
			$parts = preg_split( '/\s+/', strtolower( trim( (string) $name ) ) );
			return $parts ? $parts[0] : '';
		};
		$a = $first( $a );
		$b = $first( $b );
		return '' === $a || '' === $b || $a === $b;
	}

	/**
	 * The Guests item with this email, when it's the same person (same first
	 * name). Someone else using it is caught by email_used_by_other().
	 *
	 * @param string $email Email.
	 * @param string $first First name.
	 * @return array|null
	 */
	private static function find_guest_for( $email, $first ) {
		foreach ( self::find_guests( 'email', $email ) as $guest ) {
			if ( self::same_person( $guest['first'], $first ) ) {
				return $guest;
			}
		}
		return null;
	}

	/**
	 * Whether someone else (a different first name) is already registered
	 * for this dinner with this email. One email is one person.
	 *
	 * @param string      $email  Email.
	 * @param string      $first  First name of the person using it.
	 * @param string|null $except Guests item ID to ignore (the one being edited).
	 * @return bool
	 */
	private static function email_used_by_other( $email, $first, $except = null ) {
		foreach ( self::find_guests( 'email', $email ) as $guest ) {
			if ( $guest['id'] !== (string) $except && ! self::same_person( $guest['first'], $first ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Every guest needs an email (it can be shared with a spouse).
	 *
	 * @param array $person Clean person.
	 * @param array $errors Errors (by reference).
	 */
	private static function require_email( array $person, array &$errors ) {
		if ( '' === $person['email'] && ! isset( $errors['email'] ) ) {
			$errors['email'] = self::OWN_EMAIL;
		}
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
		if ( '' === $column ) {
			return;
		}
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
		// Mobile, church and address go to All Contacts; the Guests board
		// mirrors them. Email and first name stay here for the email automations.
		$values = array(
			Config\column( 'guests', 'email' )         => Monday::email( $person['email'] ),
			Config\column( 'guests', 'dietary' )       => $person['dietary'],
			Config\column( 'guests', 'accessibility' ) => $person['access'],
		);
		if ( '' !== Config\column( 'guests', 'first_name' ) ) {
			$values[ Config\column( 'guests', 'first_name' ) ] = $person['first'];
		}
		// Drives the dinner-week reminder automations; cleared on Cancelled.
		if ( '' !== Config\column( 'guests', 'dinner_date' ) && '' !== Config\dinner_date() ) {
			$values[ Config\column( 'guests', 'dinner_date' ) ] = array( 'date' => Config\dinner_date() );
		}
		return $values;
	}

	/**
	 * Add a cleared Dinner date to a write, so no reminders go out.
	 *
	 * @param array $values Column values.
	 * @return array
	 */
	private static function without_dinner_date( array $values ) {
		if ( '' !== Config\column( 'guests', 'dinner_date' ) ) {
			$values[ Config\column( 'guests', 'dinner_date' ) ] = null;
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
