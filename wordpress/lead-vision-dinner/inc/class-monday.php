<?php
/**
 * A minimal Monday.com GraphQL client.
 *
 * Every request goes through wp_remote_post (so the staging killswitch from
 * lead-monday-integration can catch it) and through the write guard.
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown for any failed Monday.com call. The message is for logs, never shown
 * to visitors.
 */
class Monday_Exception extends \RuntimeException {}

class Monday {

	/**
	 * Run a GraphQL document and return its `data`.
	 *
	 * @param string $graphql   Query or mutation.
	 * @param array  $variables Variables.
	 * @return array
	 * @throws Monday_Exception On any failure.
	 */
	public static function call( $graphql, array $variables = array() ) {
		if ( ! Config\is_configured() ) {
			throw new Monday_Exception( 'Monday.com is not configured (token or dinner.json missing).' );
		}

		if ( Config\is_mutation( $graphql ) && Config\is_read_only() ) {
			throw new Monday_Exception( 'Write refused: ' . Config\read_only_rule() );
		}

		$response = wp_remote_post(
			Config\api_url(),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => Config\token(),
					'Content-Type'  => 'application/json',
					'API-Version'   => Config\api_version(),
				),
				'body'    => wp_json_encode(
					array(
						'query'     => $graphql,
						'variables' => empty( $variables ) ? new \stdClass() : $variables,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Monday_Exception( 'HTTP error: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['errors'] ) || ! empty( $body['error_message'] ) ) {
			$message = ! empty( $body['errors'] )
				? wp_json_encode( $body['errors'] )
				: (string) $body['error_message'];
			throw new Monday_Exception( "Monday.com error ({$code}): {$message}" );
		}

		if ( $code < 200 || $code >= 300 || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			throw new Monday_Exception( "Unexpected Monday.com response ({$code})." );
		}

		return $body['data'];
	}

	/**
	 * Create an item and return its ID.
	 *
	 * @param array  $board  Board config from dinner.json.
	 * @param string $name   Item name.
	 * @param array  $values Column ID => value.
	 * @return string
	 */
	public static function create_item( array $board, $name, array $values ) {
		$data = self::call(
			'mutation ($board: ID!, $group: String, $name: String!, $values: JSON) {
				create_item(board_id: $board, group_id: $group, item_name: $name, column_values: $values, create_labels_if_missing: false) { id }
			}',
			array(
				'board'  => (string) $board['id'],
				'group'  => isset( $board['group'] ) ? (string) $board['group'] : null,
				'name'   => $name,
				'values' => wp_json_encode( (object) $values ),
			)
		);

		return (string) $data['create_item']['id'];
	}

	/**
	 * Update several columns (and optionally the name) on an item.
	 *
	 * @param array  $board   Board config.
	 * @param string $item_id Item ID.
	 * @param array  $values  Column ID => value. Use 'name' to rename.
	 */
	public static function update_item( array $board, $item_id, array $values ) {
		if ( empty( $values ) ) {
			return;
		}

		self::call(
			'mutation ($board: ID!, $item: ID!, $values: JSON!) {
				change_multiple_column_values(board_id: $board, item_id: $item, column_values: $values, create_labels_if_missing: false) { id }
			}',
			array(
				'board'  => (string) $board['id'],
				'item'   => (string) $item_id,
				'values' => wp_json_encode( (object) $values ),
			)
		);
	}

	/**
	 * Post an update (comment) on an item, for the coordinator's benefit.
	 *
	 * @param string $item_id Item ID.
	 * @param string $body    Plain text.
	 */
	public static function add_update( $item_id, $body ) {
		self::call(
			'mutation ($item: ID!, $body: String!) { create_update(item_id: $item, body: $body) { id } }',
			array(
				'item' => (string) $item_id,
				'body' => esc_html( $body ),
			)
		);
	}

	/**
	 * Find items whose column equals a value.
	 *
	 * @param array  $board  Board config.
	 * @param string $column Column ID.
	 * @param string $value  Value to match.
	 * @param string $fields GraphQL selection for each item.
	 * @return array
	 */
	public static function find_by( array $board, $column, $value, $fields ) {
		$data = self::call(
			'query ($board: ID!, $column: String!, $value: String!) {
				items_page_by_column_values(board_id: $board, limit: 25, columns: [{column_id: $column, column_values: [$value]}]) {
					items { ' . $fields . ' }
				}
			}',
			array(
				'board'  => (string) $board['id'],
				'column' => $column,
				'value'  => $value,
			)
		);

		return isset( $data['items_page_by_column_values']['items'] ) ? $data['items_page_by_column_values']['items'] : array();
	}

	/**
	 * Read every item on a board (follows the cursor).
	 *
	 * @param array  $board  Board config.
	 * @param string $fields GraphQL selection for each item.
	 * @return array
	 */
	public static function all_items( array $board, $fields ) {
		$items  = array();
		$data   = self::call(
			'query ($board: [ID!]) { boards(ids: $board) { items_page(limit: 500) { cursor items { ' . $fields . ' } } } }',
			array( 'board' => array( (string) $board['id'] ) )
		);
		$page   = isset( $data['boards'][0]['items_page'] ) ? $data['boards'][0]['items_page'] : array();
		$items  = isset( $page['items'] ) ? $page['items'] : array();
		$cursor = isset( $page['cursor'] ) ? $page['cursor'] : null;

		while ( $cursor ) {
			$data   = self::call(
				'query ($cursor: String!) { next_items_page(limit: 500, cursor: $cursor) { cursor items { ' . $fields . ' } } }',
				array( 'cursor' => $cursor )
			);
			$page   = $data['next_items_page'];
			$items  = array_merge( $items, $page['items'] );
			$cursor = $page['cursor'];
		}

		return $items;
	}

	/**
	 * Read specific items.
	 *
	 * @param array  $ids    Item IDs.
	 * @param string $fields GraphQL selection.
	 * @return array
	 */
	public static function items( array $ids, $fields ) {
		if ( empty( $ids ) ) {
			return array();
		}

		$items = array();
		foreach ( array_chunk( array_values( $ids ), 100 ) as $chunk ) {
			$data  = self::call(
				'query ($ids: [ID!]) { items(ids: $ids, limit: 100) { ' . $fields . ' } }',
				array( 'ids' => array_map( 'strval', $chunk ) )
			);
			$items = array_merge( $items, isset( $data['items'] ) ? $data['items'] : array() );
		}

		return $items;
	}

	/* ---------------------------------------------------------------------
	 * Column value helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Pull one column's display text out of an item's column_values.
	 *
	 * @param array  $item   Item with column_values { id text value }.
	 * @param string $column Column ID.
	 * @return string
	 */
	public static function text( array $item, $column ) {
		foreach ( isset( $item['column_values'] ) ? $item['column_values'] : array() as $cv ) {
			if ( $cv['id'] === $column ) {
				return isset( $cv['text'] ) ? (string) $cv['text'] : '';
			}
		}
		return '';
	}

	/**
	 * Linked item IDs from a connect-boards column.
	 *
	 * @param array  $item   Item with column_values including `... on BoardRelationValue { linked_item_ids }`.
	 * @param string $column Column ID.
	 * @return string[]
	 */
	public static function linked_ids( array $item, $column ) {
		foreach ( isset( $item['column_values'] ) ? $item['column_values'] : array() as $cv ) {
			if ( $cv['id'] === $column ) {
				return isset( $cv['linked_item_ids'] ) ? array_map( 'strval', (array) $cv['linked_item_ids'] ) : array();
			}
		}
		return array();
	}

	/**
	 * @param string $label Status label.
	 * @return array
	 */
	public static function status( $label ) {
		return array( 'label' => $label );
	}

	/**
	 * @param string $email Email address, or '' to clear.
	 * @return array|string
	 */
	public static function email( $email ) {
		return '' === $email ? '' : array(
			'email' => $email,
			'text'  => $email,
		);
	}

	/**
	 * @param string $digits 10 US digits, or '' to clear.
	 * @return array|string
	 */
	public static function phone( $digits ) {
		return '' === $digits ? '' : array(
			'phone'            => $digits,
			'countryShortName' => 'US',
		);
	}

	/**
	 * @param string $url  URL.
	 * @param string $text Link text.
	 * @return array
	 */
	public static function link( $url, $text ) {
		return array(
			'url'  => $url,
			'text' => $text,
		);
	}

	/**
	 * @param string|null $item_id Item to connect, or null to clear.
	 * @return array
	 */
	public static function relation( $item_id ) {
		return array( 'item_ids' => $item_id ? array( (int) $item_id ) : array() );
	}
}
