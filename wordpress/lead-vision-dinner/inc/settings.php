<?php
/**
 * Settings → Vision Dinner: the details still open when the page was built
 * (venue, coordinator, photos) so they can be filled in without a code change.
 *
 * Until a value is set the page shows plain wording ("Venue to be announced")
 * and hides what it can't show (the "What to expect" photo band).
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner\Settings;

use LEAD\VisionDinner\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'lead_vd_settings';

/**
 * Every setting, with its label, help text and default.
 *
 * @return array
 */
function fields() {
	return array(
		'venue'       => array(
			'title'  => 'Venue',
			'fields' => array(
				'venue_name'    => array( 'Venue name', 'Shown as "Where" on the page and thank-you screen. Leave blank to show "Venue to be announced".' ),
				'venue_address' => array( 'Venue address', 'One line, e.g. "1 Main St, Akron, OH 44308".' ),
				'map_url'       => array( 'Directions link', 'Optional. A Google Maps link. Builds "Get directions" when set.', 'url' ),
			),
		),
		'time'        => array(
			'title'  => 'Times',
			'fields' => array(
				'reception_time' => array( 'Reception', 'e.g. 5:45 pm', 'text', '5:45 pm' ),
				'end_time'       => array( 'Done by', 'e.g. 8:15 pm', 'text', '8:15 pm' ),
			),
		),
		'coordinator' => array(
			'title'  => 'Table Leader Coordinator',
			'fields' => array(
				'coordinator_name'  => array( 'Name', 'Shown in the "Need help?" card on the private guest page.' ),
				'coordinator_phone' => array( 'Phone', 'e.g. (330) 555-0100' ),
				'coordinator_email' => array( 'Email', '', 'email' ),
			),
		),
		'photos'      => array(
			'title'  => 'What to expect photos (April 18, 2026 dinner)',
			'fields' => array(
				'photo_reception' => array( 'Reception and welcome', 'The band stays hidden until all four photos are set. Photos display in black and white.', 'image' ),
				'photo_tables'    => array( 'Tables set for the evening', '', 'image' ),
				'photo_guests'    => array( 'Guests at a table', '', 'image' ),
				'photo_giving'    => array( 'An invitation to partner', '', 'image' ),
			),
		),
		'links'       => array(
			'title'  => 'Pages',
			'fields' => array(
				'table_page_url' => array( 'Private guest page', 'The page holding [lead_vision_dinner_table]. Table Leaders\' private links point here.', 'url', '/dinner/table/' ),
			),
		),
	);
}

/**
 * Read one setting, falling back to its default.
 *
 * @param string $key Setting key.
 * @return string
 */
function get( $key ) {
	$saved = get_option( OPTION, array() );
	if ( is_array( $saved ) && isset( $saved[ $key ] ) && '' !== $saved[ $key ] ) {
		return (string) $saved[ $key ];
	}

	foreach ( fields() as $section ) {
		if ( isset( $section['fields'][ $key ][3] ) ) {
			$default = $section['fields'][ $key ][3];
			return 0 === strpos( $default, '/' ) ? home_url( $default ) : $default;
		}
	}

	return '';
}

/**
 * The four "What to expect" photos, or an empty array unless all are set.
 *
 * @return array
 */
function photos() {
	$photos = array(
		array( get( 'photo_reception' ), 'Reception and welcome', get( 'reception_time' ) . '. Guests arrive, find their Table Leader and settle in.' ),
		array( get( 'photo_tables' ), 'Tables set for the evening', 'A plated dinner, served at your table.' ),
		array( get( 'photo_guests' ), 'Guests at a table', 'Ten friends, stories and conversation.' ),
		array( get( 'photo_giving' ), 'An invitation to partner', 'Near the end, everyone is invited to give. No pressure.' ),
	);

	foreach ( $photos as $photo ) {
		if ( '' === $photo[0] ) {
			return array();
		}
	}

	return $photos;
}

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Vision Dinner', 'Vision Dinner', 'manage_options', 'lead-vision-dinner', __NAMESPACE__ . '\\render_page' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'lead_vd',
			OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => __NAMESPACE__ . '\\sanitize',
			)
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_lead-vision-dinner' === $hook ) {
			wp_enqueue_media();
		}
	}
);

/**
 * @param mixed $input Submitted values.
 * @return array
 */
function sanitize( $input ) {
	$clean = array();
	$input = is_array( $input ) ? $input : array();

	foreach ( fields() as $section ) {
		foreach ( $section['fields'] as $key => $field ) {
			$value = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
			$type  = isset( $field[2] ) ? $field[2] : 'text';
			if ( 'url' === $type || 'image' === $type ) {
				$value = esc_url_raw( $value );
			} elseif ( 'email' === $type ) {
				$value = sanitize_email( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			$clean[ $key ] = $value;
		}
	}

	delete_transient( 'lead_vd_leaders' );
	return $clean;
}

/**
 * Settings → Vision Dinner.
 */
function render_page() {
	$saved   = get_option( OPTION, array() );
	$dinner  = Config\dinner();
	$guests  = Config\board( 'guests' );
	$leaders = Config\board( 'table_leaders' );
	?>
	<div class="wrap">
		<h1>Vision Dinner</h1>
		<p>
			<?php echo esc_html( isset( $dinner['dinner'] ) ? $dinner['dinner'] : 'Dinner file missing' ); ?>
			· <?php echo esc_html( Config\dinner_date() ); ?>.
			The date and Monday.com boards come from <code>dinner.json</code> in the plugin folder.
		</p>
		<?php if ( ! Config\is_configured() ) : ?>
			<div class="notice notice-error inline"><p>Registration can't reach Monday.com yet. Add <code>define( 'LEAD_MONDAY_TOKEN', '…' );</code> to wp-config.php and make sure dinner.json lists the Guests and Table Leaders boards.</p></div>
		<?php elseif ( Config\is_read_only() ) : ?>
			<div class="notice notice-warning inline"><p>Read-only: registrations will be refused on this site because <?php echo esc_html( Config\read_only_rule() ); ?>.</p></div>
		<?php endif; ?>
		<p>
			<?php if ( $guests ) : ?><a href="<?php echo esc_url( $guests['url'] ); ?>" target="_blank" rel="noopener">Guests board</a><?php endif; ?>
			<?php if ( $leaders ) : ?> · <a href="<?php echo esc_url( $leaders['url'] ); ?>" target="_blank" rel="noopener">Table Leaders board</a><?php endif; ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'lead_vd' ); ?>
			<?php foreach ( fields() as $section ) : ?>
				<h2><?php echo esc_html( $section['title'] ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					foreach ( $section['fields'] as $key => $field ) :
						$type  = isset( $field[2] ) ? $field[2] : 'text';
						$value = isset( $saved[ $key ] ) ? $saved[ $key ] : '';
						$name  = OPTION . '[' . $key . ']';
						$id    = 'lead-vd-' . $key;
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
							<td>
								<input type="<?php echo 'image' === $type ? 'url' : esc_attr( $type ); ?>" class="regular-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( isset( $field[3] ) ? $field[3] : '' ); ?>">
								<?php if ( 'image' === $type ) : ?>
									<button type="button" class="button lead-vd-media" data-target="<?php echo esc_attr( $id ); ?>">Choose photo</button>
								<?php endif; ?>
								<?php if ( '' !== $field[1] ) : ?>
									<p class="description"><?php echo esc_html( $field[1] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<script>
	document.querySelectorAll('.lead-vd-media').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var frame = wp.media({ title: 'Choose a photo', library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				var size = (a.sizes && (a.sizes.large || a.sizes.full)) || a;
				document.getElementById(btn.dataset.target).value = size.url;
			});
			frame.open();
		});
	});
	</script>
	<?php
}
