<?php
/**
 * Tools → Site Health → Info → LEAD Vision Dinner.
 *
 * Some LEAD sites hide admin notices, so status is reported here, where
 * notice suppression does not reach.
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'debug_information',
	function ( $info ) {
		$dinner = Config\dinner();
		$rule   = Config\read_only_rule();

		$info['lead-vision-dinner'] = array(
			'label'  => 'LEAD Vision Dinner',
			'fields' => array(
				'version' => array(
					'label' => 'Plugin version',
					'value' => VERSION,
				),
				'dinner'  => array(
					'label' => 'Dinner file',
					'value' => $dinner ? ( $dinner['label'] . ' · ' . Config\dinner_date() ) : 'dinner.json missing',
				),
				'token'   => array(
					'label' => 'Monday.com token',
					'value' => '' !== Config\token() ? 'Found' : 'Missing (define LEAD_MONDAY_TOKEN in wp-config.php)',
				),
				'boards'  => array(
					'label' => 'Boards',
					'value' => ( Config\board( 'guests' ) && Config\board( 'table_leaders' ) ) ? 'Guests and Table Leaders found' : 'Missing from dinner.json',
				),
				'writes'  => array(
					'label' => 'Writes to Monday.com',
					'value' => null === $rule ? 'Allowed (treated as production)' : 'Blocked: ' . $rule,
				),
			),
		);

		return $info;
	}
);
