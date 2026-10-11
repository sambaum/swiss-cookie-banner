<?php
/**
 * Plugin Name: CBee Cookie Banner
 * Description: Schlanker Cookie-Banner (CH): Akzeptieren / Einstellungen, Google Analytics erst nach Einwilligung.
 * Version: 0.3.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: cbee-cookie-banner
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

const SCB_VERSION = '0.3.0';
const SCB_OPTION  = 'scb_options';

function scb_defaults() {
	return array(
		'privacy_url'    => '',
		'policy_version' => 1,
		'ga_id'          => '',
		'extra_js'       => '',
		'text'           => 'Wir verwenden Cookies, um die Website zu betreiben und – mit Ihrer Einwilligung – um die Nutzung zu analysieren.',
	);
}

function scb_options() {
	return wp_parse_args( get_option( SCB_OPTION, array() ), scb_defaults() );
}

/* ---------- Frontend ---------- */

add_action( 'wp_enqueue_scripts', function () {
	$o = scb_options();
	wp_enqueue_style( 'scb', plugins_url( 'assets/banner.css', __FILE__ ), array(), SCB_VERSION );
	// WP Consent API (separate plugin) lets Site Kit and others follow our consent choice.
	$deps = wp_script_is( 'wp-consent-api', 'registered' ) ? array( 'wp-consent-api' ) : array();
	wp_enqueue_script( 'scb', plugins_url( 'assets/banner.js', __FILE__ ), $deps, SCB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_add_inline_script(
		'scb',
		'window.SCB=' . wp_json_encode( array(
			'version'    => (int) $o['policy_version'],
			'gaId'       => $o['ga_id'],
			'text'       => $o['text'],
			'privacyUrl' => $o['privacy_url'],
		) ) . ';',
		'before'
	);
} );

// Neutralise Google tag loaders in the final HTML so the browser never fetches them before consent.
// Same output for every visitor (cache-safe); banner.js re-activates them after consent.
add_action( 'template_redirect', function () {
	if ( is_feed() || is_robots() || is_trackback() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	ob_start( 'scb_block_tags' );
}, 0 );

function scb_block_tags( $html ) {
	if ( false === stripos( $html, '</head>' ) ) {
		return $html;
	}
	$re = '#<script\b[^>]*\bsrc=["\'][^"\']*(?:googletagmanager\.com/(?:gtag/js|gtm\.js)|google-analytics\.com/(?:analytics|ga)\.js)[^"\']*["\'][^>]*>#i';
	return preg_replace_callback( $re, function ( $m ) {
		$tag = preg_replace( '#\stype=(["\'])[^"\']*\1#i', '', $m[0] );
		$tag = preg_replace( '#\ssrc=#i', ' type="text/plain" data-scb-category="analytics" data-scb-src=', $tag, 1 );
		return $tag;
	}, $html );
}

// Register as a consent management plugin with the WP Consent API (opt-in model).
add_filter( 'wp_get_consent_type', function () {
	return 'optin';
} );
add_filter( 'wp_consent_api_registered_' . plugin_basename( __FILE__ ), '__return_true' );

// Extra analytics code is inert until the JS activates it after consent.
add_action( 'wp_footer', function () {
	$o = scb_options();
	if ( '' !== trim( $o['extra_js'] ) ) {
		echo '<script type="text/plain" data-scb-category="analytics">' . $o['extra_js'] . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- admin-only, unfiltered_html gated on save
	}
} );

// [cookie_einstellungen] — link to reopen the settings, e.g. for the footer.
add_shortcode( 'cookie_einstellungen', function ( $atts ) {
	$atts = shortcode_atts( array( 'text' => 'Cookie-Einstellungen' ), $atts );
	return '<a href="#cookie-einstellungen" class="scb-open">' . esc_html( $atts['text'] ) . '</a>';
} );

/* ---------- Admin ---------- */

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=scb' ) ) . '">Einstellungen</a>' );
	return $links;
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Cookie-Banner', 'Cookie-Banner', 'manage_options', 'scb', 'scb_render_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'scb', SCB_OPTION, array( 'sanitize_callback' => 'scb_sanitize' ) );
} );

function scb_sanitize( $in ) {
	$old = scb_options();
	$out = array(
		'privacy_url'    => esc_url_raw( $in['privacy_url'] ?? '' ),
		'policy_version' => max( 1, (int) ( $in['policy_version'] ?? 1 ) ),
		'ga_id'          => preg_match( '/^G-[A-Z0-9]+$/', $ga = strtoupper( trim( $in['ga_id'] ?? '' ) ) ) ? $ga : '',
		'text'           => sanitize_text_field( $in['text'] ?? '' ),
		// Raw JS is only accepted from users who may post unfiltered HTML.
		'extra_js'       => current_user_can( 'unfiltered_html' ) ? wp_unslash( $in['extra_js'] ?? '' ) : $old['extra_js'],
	);
	return $out;
}

function scb_render_page() {
	$o = scb_options();
	$n = SCB_OPTION;
	?>
	<div class="wrap">
		<h1>Cookie-Banner</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'scb' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="scb_text">Banner-Text</label></th>
					<td><textarea id="scb_text" name="<?php echo esc_attr( $n ); ?>[text]" rows="3" class="large-text"><?php echo esc_textarea( $o['text'] ); ?></textarea></td></tr>
				<tr><th><label for="scb_privacy">URL Datenschutzerklärung</label></th>
					<td><input id="scb_privacy" type="url" name="<?php echo esc_attr( $n ); ?>[privacy_url]" value="<?php echo esc_attr( $o['privacy_url'] ); ?>" class="regular-text"></td></tr>
				<tr><th><label for="scb_ga">Google Analytics ID</label></th>
					<td><input id="scb_ga" type="text" name="<?php echo esc_attr( $n ); ?>[ga_id]" value="<?php echo esc_attr( $o['ga_id'] ); ?>" placeholder="G-XXXXXXXXXX" class="regular-text">
					<p class="description">GA4-Messungs-ID. Wird erst nach Einwilligung geladen. Leer lassen, wenn Google Site Kit den Tag einbindet (zusammen mit dem Plugin «WP Consent API»).</p></td></tr>
				<tr><th><label for="scb_js">Zusätzlicher Analytics-JS-Code</label></th>
					<td><textarea id="scb_js" name="<?php echo esc_attr( $n ); ?>[extra_js]" rows="6" class="large-text code" <?php disabled( ! current_user_can( 'unfiltered_html' ) ); ?>><?php echo esc_textarea( $o['extra_js'] ); ?></textarea>
					<p class="description">Optional. Reiner JavaScript-Code ohne &lt;script&gt;-Tags; läuft nur nach Einwilligung.</p></td></tr>
				<tr><th><label for="scb_ver">Richtlinien-Version</label></th>
					<td><input id="scb_ver" type="number" min="1" name="<?php echo esc_attr( $n ); ?>[policy_version]" value="<?php echo esc_attr( $o['policy_version'] ); ?>" class="small-text">
					<p class="description">Erhöhen, damit alle Besucher erneut gefragt werden.</p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p>Link zum Wiederöffnen der Einstellungen: Shortcode <code>[cookie_einstellungen]</code> oder ein beliebiger Link auf <code>#cookie-einstellungen</code> (z. B. im Footer-Menü).</p>
	</div>
	<?php
}
