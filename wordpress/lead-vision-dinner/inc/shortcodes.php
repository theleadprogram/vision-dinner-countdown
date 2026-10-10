<?php
/**
 * [lead_vision_dinner]        The public registration page (mockups 1a, 1b, 1d, 1g).
 * [lead_vision_dinner_table]  The Table Leader's private guest page (mockup 1c).
 *
 * Both render server-side HTML; assets/js adds the behavior and talks to the
 * REST endpoints in rest.php.
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner\Shortcodes;

use LEAD\VisionDinner\Config;
use LEAD\VisionDinner\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lead_vision_dinner', __NAMESPACE__ . '\\register_page' );
add_shortcode( 'lead_vision_dinner_table', __NAMESPACE__ . '\\table_page' );

/**
 * Dinner facts used by the page, the thank-you screen and the .ics file.
 *
 * @return array
 */
function facts() {
	$date  = Config\dinner_date();
	$tz    = new \DateTimeZone( 'America/New_York' );
	$day   = $date ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $tz ) : false;
	$start = $day ? date_create_immutable( $date . ' ' . Settings\get( 'reception_time' ), $tz ) : false;
	$end   = $day ? date_create_immutable( $date . ' ' . Settings\get( 'end_time' ), $tz ) : false;

	$venue   = Settings\get( 'venue_name' );
	$address = Settings\get( 'venue_address' );

	return array(
		'dateLong'  => $day ? $day->format( 'l, F j, Y' ) : '',
		'dateShort' => $day ? $day->format( 'F j' ) : '',
		'reception' => Settings\get( 'reception_time' ),
		'end'       => Settings\get( 'end_time' ),
		'venue'     => '' !== $venue ? $venue : 'Venue to be announced',
		'venueSet'  => '' !== $venue,
		'address'   => $address,
		'mapUrl'    => Settings\get( 'map_url' ),
		'icsStart'  => $start ? $start->format( 'Ymd\THis' ) : '',
		'icsEnd'    => $end ? $end->format( 'Ymd\THis' ) : '',
		'title'     => 'LEAD Africa Vision Dinner',
	);
}

/**
 * CSS, JS and the config object both pages share.
 *
 * @param string $script 'register' or 'table'.
 */
function enqueue( $script ) {
	$ver = \LEAD\VisionDinner\VERSION;
	wp_enqueue_style( 'lead-vd', LEAD_VD_URL . 'assets/css/dinner.css', array(), $ver );
	wp_enqueue_script( 'lead-vd-common', LEAD_VD_URL . 'assets/js/common.js', array(), $ver, true );
	wp_enqueue_script( 'lead-vd-' . $script, LEAD_VD_URL . 'assets/js/' . $script . '.js', array( 'lead-vd-common' ), $ver, true );
	wp_localize_script(
		'lead-vd-common',
		'LEAD_VD',
		array(
			'api'       => esc_url_raw( rest_url( 'lead-dinner/v1/' ) ),
			'tablePage' => esc_url_raw( Settings\get( 'table_page_url' ) ),
			'dinner'    => facts(),
		)
	);
}

/**
 * Text input markup, matching the mockup's field.
 *
 * @param string $name        Field name.
 * @param string $label       Label.
 * @param array  $opts        placeholder, type, optional (bool), span (bool), autocomplete.
 * @return string
 */
function field( $name, $label, array $opts = array() ) {
	$opts = wp_parse_args(
		$opts,
		array(
			'placeholder'  => '',
			'type'         => 'text',
			'optional'     => false,
			'span'         => false,
			'autocomplete' => 'off',
			'inputmode'    => '',
		)
	);
	$id   = 'lvd-' . str_replace( '_', '-', $name ) . '-' . wp_unique_id();

	ob_start();
	?>
	<div class="lvd-field<?php echo $opts['span'] ? ' lvd-field--span2' : ''; ?>">
		<label class="lvd-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?><?php if ( $opts['optional'] ) : ?> <span class="lvd-opt">(optional)</span><?php endif; ?></label>
		<input class="lvd-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $opts['type'] ); ?>" placeholder="<?php echo esc_attr( $opts['placeholder'] ); ?>" autocomplete="<?php echo esc_attr( $opts['autocomplete'] ); ?>"<?php echo $opts['inputmode'] ? ' inputmode="' . esc_attr( $opts['inputmode'] ) . '"' : ''; ?>>
		<div class="lvd-error" data-error-for="<?php echo esc_attr( $name ); ?>" hidden></div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * [lead_vision_dinner]
 *
 * @return string
 */
function register_page() {
	enqueue( 'register' );
	$f      = facts();
	$img    = LEAD_VD_URL . 'assets/img/';
	$photos = Settings\photos();

	ob_start();
	?>
	<div class="lvd lvd-page" data-lvd-register>
		<div class="lvd-reg">
			<aside class="lvd-hero">
				<div class="lvd-hero__inner">
					<div class="lvd-hero__photo">
						<img class="lvd-hero__img" src="<?php echo esc_url( $img . 'giraffe-1600.jpg' ); ?>" alt="">
						<div class="lvd-hero__shade"></div>
						<img class="lvd-hero__logo" src="<?php echo esc_url( $img . 'lead-africa-white-red.png' ); ?>" alt="LEAD Africa">
						<div class="lvd-hero__mobile">
							<div class="lvd-eyebrow lvd-eyebrow--sand">LEAD Africa Vision Dinner</div>
							<div class="lvd-hero__h1">An evening of vision.</div>
							<div class="lvd-rule"></div>
							<div class="lvd-hero__lines"><?php echo esc_html( $f['dateLong'] . ' · ' . $f['reception'] ); ?><br><?php echo esc_html( $f['venue'] ); ?> · Cleveland–Akron area</div>
						</div>
						<div class="lvd-hero__thanks" data-thanks-headline hidden></div>
					</div>
					<div class="lvd-hero__details" data-hero-details>
						<div class="lvd-eyebrow lvd-eyebrow--sand lvd-eyebrow--lg">LEAD Africa Vision Dinner</div>
						<h1 class="lvd-hero__h1">An evening of vision.</h1>
						<div class="lvd-rule"></div>
						<dl class="lvd-facts">
							<dt>When</dt><dd><?php echo esc_html( $f['dateLong'] ); ?><br>Reception <?php echo esc_html( $f['reception'] ); ?> · Done by <?php echo esc_html( $f['end'] ); ?></dd>
							<dt>Where</dt><dd><?php echo esc_html( $f['venue'] ); ?><?php if ( '' !== $f['address'] ) : ?><br><?php echo esc_html( $f['address'] ); ?><?php endif; ?><br>Cleveland–Akron area</dd>
							<dt>Cost</dt><dd>None. Dinner is our gift to you.</dd>
							<dt>Attire</dt><dd>Business attire strongly suggested. Men: suit or jacket and tie. Women: dress or pants outfit.</dd>
						</dl>
						<p class="lvd-hero__p">Hear what God is doing through LEAD to equip ministry leaders in East Africa and here at home. Near the end, everyone will be invited to partner with LEAD in prayer and giving.</p>
					</div>
				</div>
			</aside>

			<main class="lvd-main">
				<div class="lvd-main__inner">
					<?php echo register_form(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
					<?php echo thank_you(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
				</div>
			</main>

			<?php if ( $photos ) : ?>
				<section class="lvd-expect" data-expect>
					<div class="lvd-expect__head">
						<div class="lvd-eyebrow lvd-eyebrow--red">What to expect<span class="lvd-hide-mobile"> · from our first Vision Dinner, April 18, 2026</span></div>
						<h2 class="lvd-h2">A warm evening, start to finish.</h2>
					</div>
					<div class="lvd-expect__grid">
						<?php foreach ( $photos as $photo ) : ?>
							<figure class="lvd-expect__card">
								<img src="<?php echo esc_url( $photo[0] ); ?>" alt="<?php echo esc_attr( $photo[1] ); ?>" loading="lazy">
								<figcaption><strong><?php echo esc_html( $photo[1] ); ?></strong><span><?php echo esc_html( $photo[2] ); ?></span></figcaption>
							</figure>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * The registration form.
 *
 * @return string
 */
function register_form() {
	ob_start();
	?>
	<form class="lvd-form" data-form novalidate>
		<div class="lvd-banner" data-banner role="alert" hidden>Something went wrong. Please try again, or call 1-866-LEADCMA.</div>

		<header class="lvd-stack lvd-stack--10">
			<div class="lvd-eyebrow lvd-eyebrow--red">Register · leadcma.org/dinner</div>
			<h2 class="lvd-h2">Reserve your seat.</h2>
			<p class="lvd-sub">Dinner is our gift to you. It takes about two minutes.</p>
		</header>

		<fieldset class="lvd-stack lvd-stack--12">
			<legend class="lvd-h3">I'm registering as</legend>
			<div class="lvd-grid lvd-grid--200 lvd-grid--12" role="radiogroup">
				<label class="lvd-choice">
					<input type="radio" name="role" value="guest" checked>
					<span class="lvd-choice__title">A guest</span>
					<span class="lvd-choice__sub">Someone invited me to their table</span>
				</label>
				<label class="lvd-choice">
					<input type="radio" name="role" value="leader">
					<span class="lvd-choice__title">A Table Leader</span>
					<span class="lvd-choice__sub">I'm filling a table of ten</span>
				</label>
			</div>
		</fieldset>

		<div class="lvd-stack lvd-stack--10" data-guest-only>
			<label class="lvd-h3" for="lvd-tl-search">Your Table Leader</label>
			<div class="lvd-combo" data-combo>
				<input class="lvd-input lvd-input--combo" id="lvd-tl-search" type="text" placeholder="Start typing a name" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="lvd-tl-list" aria-autocomplete="list" data-combo-input>
				<span class="lvd-combo__caret" aria-hidden="true">▾</span>
				<div class="lvd-combo__list" id="lvd-tl-list" role="listbox" data-combo-list hidden></div>
			</div>
			<div class="lvd-error" data-error-for="leader" hidden></div>
			<div class="lvd-note lvd-note--sand" data-full-note hidden>This table is full. You can still choose it, and our coordinator will seat you as close as possible.</div>
			<div class="lvd-note" data-seatme-note hidden>Wonderful. We'll seat you with a Table Leader and let you know before the dinner.</div>
		</div>

		<div class="lvd-stack lvd-stack--14">
			<div class="lvd-h3">Your details</div>
			<div class="lvd-grid lvd-grid--200 lvd-grid--14">
				<?php
				echo field( 'first', 'First name', array( 'placeholder' => 'First name', 'autocomplete' => 'given-name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo field( 'last', 'Last name', array( 'placeholder' => 'Last name', 'autocomplete' => 'family-name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo field( 'email', 'Email', array( 'placeholder' => 'you@example.com', 'type' => 'email', 'autocomplete' => 'email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo field( 'phone', 'Mobile phone', array( 'placeholder' => '(330) 555-0100', 'type' => 'tel', 'autocomplete' => 'tel-national', 'inputmode' => 'tel' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</div>
			<?php echo field( 'church', 'Church', array( 'placeholder' => 'Where you worship', 'optional' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

		<div class="lvd-stack lvd-stack--14">
			<div class="lvd-h3">Mailing address</div>
			<?php echo field( 'street', 'Street', array( 'placeholder' => 'Street address', 'autocomplete' => 'street-address' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="lvd-grid lvd-grid--110 lvd-grid--14 lvd-grid--address">
				<?php
				echo field( 'city', 'City', array( 'placeholder' => 'City', 'span' => true, 'autocomplete' => 'address-level2' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo field( 'state', 'State', array( 'placeholder' => 'OH', 'autocomplete' => 'address-level1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo field( 'zip', 'ZIP', array( 'placeholder' => '44308', 'autocomplete' => 'postal-code', 'inputmode' => 'numeric' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</div>
		</div>

		<div class="lvd-stack lvd-stack--14">
			<label class="lvd-toggle">
				<span class="lvd-stack">
					<span class="lvd-h3">My spouse or a guest is coming with me</span>
					<span class="lvd-small">They'll be seated with you and get their own name tag.</span>
				</span>
				<input type="checkbox" name="plus_one" class="lvd-switch" role="switch">
			</label>
			<div class="lvd-plus" data-plus hidden>
				<div class="lvd-grid lvd-grid--200 lvd-grid--14">
					<?php
					echo field( 'plus_first', 'Their first name', array( 'placeholder' => 'First name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo field( 'plus_last', 'Their last name', array( 'placeholder' => 'Last name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo field( 'plus_email', 'Their email', array( 'placeholder' => 'So they get reminders too', 'type' => 'email', 'optional' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo field( 'plus_dietary', 'Their dietary needs', array( 'placeholder' => 'e.g. vegetarian', 'optional' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>
			</div>
		</div>

		<div class="lvd-stack lvd-stack--14">
			<div class="lvd-h3">So we can care for you well</div>
			<?php
			echo field( 'dietary', 'Dietary needs', array( 'placeholder' => 'Vegetarian, gluten-free, allergies…', 'optional' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo field( 'access', 'Accessibility needs', array( 'placeholder' => 'Wheelchair access, seating near the front, hearing assistance…', 'optional' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
			<label class="lvd-check" data-guest-only>
				<input type="checkbox" name="interest">
				<span class="lvd-stack"><strong>I'd like to hear about being a Table Leader</strong><span class="lvd-small">Fill a table of your own next year, or even this year.</span></span>
			</label>
		</div>

		<div class="lvd-card lvd-card--redtop" data-leader-only hidden>
			<strong>Next, your guests.</strong> After you register, we'll email you a private link to register your guests and see who's at your table.
		</div>

		<div class="lvd-hp" aria-hidden="true">
			<label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		</div>

		<div class="lvd-stack lvd-stack--12">
			<button type="submit" class="lvd-btn lvd-btn--primary lvd-btn--lg lvd-btn--block" data-submit>
				<span data-submit-label>Reserve my seat</span>
			</button>
			<div class="lvd-fine">We'll send a confirmation and a few reminders. We never share your information.</div>
			<div class="lvd-fine"><a href="<?php echo esc_url( Settings\get( 'table_page_url' ) ); ?>">Table Leader? Find your guest link.</a></div>
		</div>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * The thank-you screen (filled in by JS after a successful registration).
 *
 * @return string
 */
function thank_you() {
	$f = facts();
	ob_start();
	?>
	<div class="lvd-done" data-done tabindex="-1" hidden>
		<div class="lvd-check-tile" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="28" height="28"><path d="M5 12.5l4.2 4.2L19 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</div>
		<div class="lvd-stack lvd-stack--10">
			<div class="lvd-eyebrow lvd-eyebrow--red">You're registered</div>
			<h2 class="lvd-h2" data-done-title></h2>
			<p class="lvd-sub" data-done-body></p>
		</div>
		<dl class="lvd-summary">
			<dt>When</dt><dd><?php echo esc_html( $f['dateLong'] . ' · Reception ' . $f['reception'] ); ?></dd>
			<dt>Where</dt><dd><?php echo esc_html( $f['venue'] . ( '' !== $f['address'] ? ', ' . $f['address'] : '' ) ); ?></dd>
			<dt>Table</dt><dd data-done-table></dd>
			<dt>Attire</dt><dd>Business attire strongly suggested</dd>
		</dl>
		<div class="lvd-card lvd-card--redtop lvd-stack lvd-stack--12" data-done-leader hidden>
			<div class="lvd-h3 lvd-h3--20">Now, fill your table.</div>
			<div class="lvd-small lvd-small--14" data-done-leader-body>Register guests as they say yes. We've also emailed you this private link so you can come back anytime.</div>
			<a class="lvd-btn lvd-btn--primary lvd-btn--lg lvd-self-start" data-done-link href="#">Register my guests →</a>
		</div>
		<div class="lvd-row">
			<button type="button" class="lvd-btn lvd-btn--secondary" data-ics>Add to calendar</button>
			<?php if ( '' !== $f['mapUrl'] ) : ?>
				<a class="lvd-btn lvd-btn--secondary" href="<?php echo esc_url( $f['mapUrl'] ); ?>" target="_blank" rel="noopener">Get directions</a>
			<?php endif; ?>
			<button type="button" class="lvd-btn lvd-btn--ghost" data-reset>Register someone else</button>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * [lead_vision_dinner_table]
 *
 * @return string
 */
function table_page() {
	enqueue( 'table' );
	$f     = facts();
	$img   = LEAD_VD_URL . 'assets/img/';
	$coord = Settings\get( 'coordinator_name' );
	$phone = Settings\get( 'coordinator_phone' );
	$email = Settings\get( 'coordinator_email' );

	ob_start();
	?>
	<div class="lvd lvd-page lvd-tablepage" data-lvd-table>
		<div class="lvd-topbar">
			<img src="<?php echo esc_url( $img . 'lead-africa-black-red.png' ); ?>" alt="LEAD Africa" class="lvd-topbar__logo">
			<div class="lvd-topbar__meta">Vision Dinner · <?php echo esc_html( $f['dateLong'] ); ?></div>
		</div>

		<div class="lvd-table-body">
			<div class="lvd-loading" data-loading>Loading your table…</div>

			<?php /* Shown when the link is missing or wrong: "Lost your link?" */ ?>
			<div class="lvd-lost" data-lost hidden>
				<div class="lvd-stack lvd-stack--8">
					<div class="lvd-eyebrow lvd-eyebrow--red">Your table</div>
					<h2 class="lvd-h2 lvd-h2--44" data-lost-title>Lost your link?</h2>
					<p class="lvd-sub" data-lost-body>Enter the email you registered with. If you're a Table Leader, we'll email your private link to register guests.</p>
				</div>
				<form class="lvd-card lvd-card--white lvd-stack lvd-stack--14" data-lost-form novalidate>
					<?php echo field( 'email', 'Email', array( 'placeholder' => 'you@example.com', 'type' => 'email', 'autocomplete' => 'email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<button type="submit" class="lvd-btn lvd-btn--primary lvd-btn--lg lvd-self-start" data-submit>Email my link</button>
					<div class="lvd-note" data-lost-sent hidden>If that email belongs to a Table Leader, your link is on its way. Check your inbox in a few minutes.</div>
				</form>
			</div>

			<div class="lvd-tablegrid" data-table hidden>
				<div class="lvd-stack lvd-stack--28">
					<div class="lvd-stack lvd-stack--8">
						<div class="lvd-eyebrow lvd-eyebrow--red">Your table</div>
						<h2 class="lvd-h2 lvd-h2--44" data-welcome></h2>
						<p class="lvd-sub lvd-sub--16">Register each guest the day they say yes. Each one gets their own confirmation and reminders.</p>
					</div>

					<div class="lvd-banner" data-banner role="alert" hidden>Something went wrong. Please try again, or call 1-866-LEADCMA.</div>

					<form class="lvd-card lvd-card--white lvd-stack lvd-stack--18" data-add novalidate>
						<div class="lvd-h3 lvd-h3--20">Add a guest</div>
						<div class="lvd-grid lvd-grid--200 lvd-grid--14">
							<?php
							echo field( 'first', 'First name', array( 'placeholder' => 'First name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo field( 'last', 'Last name', array( 'placeholder' => 'Last name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo field( 'email', 'Email', array( 'placeholder' => 'guest@example.com', 'type' => 'email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo field( 'phone', 'Mobile phone', array( 'placeholder' => '(330) 555-0100', 'type' => 'tel', 'inputmode' => 'tel' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo field( 'dietary', 'Dietary needs', array( 'placeholder' => 'e.g. gluten-free', 'optional' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo field( 'access', 'Accessibility needs', array( 'placeholder' => 'e.g. wheelchair access', 'optional' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</div>
						<div class="lvd-small">No email? Leave it blank and add a phone number. Your coordinator will make sure they get reminders.</div>
						<div class="lvd-row">
							<button type="submit" class="lvd-btn lvd-btn--primary lvd-btn--lg" data-submit>Add guest to my table</button>
							<span class="lvd-note" data-added role="status" hidden></span>
						</div>
					</form>

					<div class="lvd-card lvd-card--white lvd-card--flush">
						<div class="lvd-list-head">
							<div class="lvd-h3 lvd-h3--20">Registered at your table</div>
							<div class="lvd-small lvd-small--14" data-count></div>
						</div>
						<div data-list></div>
						<div class="lvd-empty" data-empty hidden>No guests yet. Someone is waiting to be asked.</div>
					</div>
				</div>

				<aside class="lvd-side">
					<div class="lvd-meter">
						<div class="lvd-eyebrow lvd-eyebrow--sand">Your table</div>
						<div class="lvd-meter__count"><span data-filled>0</span><span class="lvd-meter__of">of 10 seats filled</span></div>
						<div class="lvd-meter__bar" data-bar aria-hidden="true"></div>
						<div class="lvd-meter__msg" data-seats-msg></div>
					</div>
					<div class="lvd-card lvd-card--white lvd-stack lvd-stack--8 lvd-help">
						<div class="lvd-eyebrow lvd-eyebrow--red">Need help?</div>
						<?php if ( '' !== $coord ) : ?>
							<div class="lvd-help__name"><?php echo esc_html( $coord ); ?></div>
						<?php endif; ?>
						<?php if ( '' !== $phone || '' !== $email ) : ?>
							<div class="lvd-muted">
								<?php if ( '' !== $phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>
								<?php if ( '' !== $phone && '' !== $email ) : ?> · <?php endif; ?>
								<?php if ( '' !== $email ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?>
							</div>
						<?php else : ?>
							<div class="lvd-muted">Call 1-866-LEADCMA.</div>
						<?php endif; ?>
						<div class="lvd-muted lvd-help__note">Changes or cancellations? Tell your coordinator right away so seats can be shared.</div>
					</div>
				</aside>
			</div>
		</div>
	</div>

	<template data-row-template>
		<div class="lvd-guest">
			<div class="lvd-guest__row">
				<span class="lvd-guest__name" data-g="name"></span>
				<span class="lvd-guest__email" data-g="email"></span>
				<span class="lvd-guest__phone" data-g="phone"></span>
				<span class="lvd-guest__badge"><span class="lvd-badge" data-g="status"></span></span>
				<button type="button" class="lvd-link" data-g="toggle">Edit</button>
			</div>
			<form class="lvd-guest__edit" data-g="form" novalidate hidden>
				<div class="lvd-grid lvd-grid--180 lvd-grid--12">
					<?php
					foreach ( array(
						'first'   => 'First name',
						'last'    => 'Last name',
						'email'   => 'Email',
						'phone'   => 'Mobile phone',
						'dietary' => 'Dietary needs',
						'access'  => 'Accessibility needs',
					) as $name => $label ) {
						echo field( $name, $label, array( 'type' => 'email' === $name ? 'email' : ( 'phone' === $name ? 'tel' : 'text' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>
				<div class="lvd-guest__actions">
					<div class="lvd-row">
						<button type="submit" class="lvd-btn lvd-btn--primary" data-submit>Save changes</button>
						<button type="button" class="lvd-btn lvd-btn--ghost" data-g="cancel">Cancel</button>
					</div>
					<button type="button" class="lvd-release" data-g="release">Can't come · release their seat</button>
				</div>
				<div class="lvd-fine lvd-fine--left">Saving sends the guest an updated confirmation. Releasing a seat lets your coordinator know right away.</div>
			</form>
		</div>
	</template>
	<?php
	return (string) ob_get_clean();
}
