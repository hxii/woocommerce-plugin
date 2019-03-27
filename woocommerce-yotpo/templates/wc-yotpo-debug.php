<?php

if ( ! defined( 'ABSPATH' ) ) {
 exit;
}

define("LOG_FILE", plugin_dir_path( __FILE__ ) . '../yotpo_debug.log');

function wc_yotpo_admin_debug_page() {
	global $yotpo_settings;
    if ( !current_user_can( 'manage_options' ) ) {
        return;
    }
	if ( isset( $_POST[ 'yotdbg-clear' ] ) ) {
		check_admin_referer( 'yotdbg-clear' );
		$filename = LOG_FILE;
		file_put_contents( $filename, "" );
		display_debug();
	} elseif ( isset ( $_POST[ 'extra_button' ] ) ) {
		check_admin_referer( 'extra_button' );
		// set_transient( 'yotpo_plugin_updated', 1 );
		// wc_yotpo_check_settings();
		display_debug();
	} elseif ( isset( $_POST[ 'update_settings' ] ) ) {
		if ( isset( $_POST[ 'extra_settings'] ) ) {
			$settings = json_decode( stripslashes( urldecode( $_POST[ 'extra_settings' ] ) ), true );
			update_option( 'yotpo_settings', $settings );
			$yotpo_settings = $settings;
			display_debug();
		}
	} elseif ( isset( $_POST[ 'reset_settings' ] ) ) {
		update_option( 'yotpo_settings', wc_yotpo_get_default_settings() );
	} else {
		display_debug();
	}
}

function display_debug() {
	global $yotpo_settings;
	global $woocommerce;
	$debug_log = ( file_exists( LOG_FILE ) ) ? file_get_contents( LOG_FILE ) : false;
	$wpver = get_bloginfo( 'version' );
	$phpver = phpversion();
	$phpmem = ini_get( 'memory_limit' );
	$phpexec = ini_get( 'max_execution_time' );
	$swell = json_encode( get_option( 'swell_options' ) ) ?: 'swell not installed';
	?>
    <div class="wrap">
		<script>
			jQuery(document).ready(function(){
				var $textarea = jQuery('#yotpo-log');
				$textarea.scrollTop( $textarea[0].scrollHeight );
			});
		</script>
        <h1><?= esc_html( get_admin_page_title() ); ?></h1>
		<pre><?= "WooCommerce: $woocommerce->version, Wordpress: $wpver, PHP: $phpver, PHP MEM: $phpmem, PHP EXEC: $phpexec ms"; ?></pre>
		<pre><?= "Swell: $swell"; ?></pre>
		<div class="right settings">
			<h2>Yotpo settings</h2>
			<div class="box warning">
				<p><strong>WARNING</strong></p>
				<p>This may be destructive. Excercise care when changing settings.</p>
			</div>
			<form method="post" id="extra-settings">
				<textarea name="extra_settings" id="extra_settings" rows="30"><?= json_encode( $yotpo_settings, JSON_PRETTY_PRINT ); ?></textarea>
				<input type="submit" value="Update Settings" name="update_settings" class="button button-primary">
				<input type="submit" value="RESET Settings" name="reset_settings" class="button button-red" disabled>
				<?= wp_nonce_field( 'update_settings' ); ?>
			</form>
		</div>
		<div class="left log">
			<h2>Debug Log</h2>
			<?php
				if ( !$debug_log ) {
					echo '<textarea id="yotpo-log" rows=35>Problem opening yotpo_debug.log and/or file is empty</textarea>';
				} else {
					echo '<textarea id="yotpo-log" rows=35>'.$debug_log.'</textarea>
					<form method="post" id="yotdbg-clear">' .wp_nonce_field( 'yotdbg-clear' ) .'<input type="submit" value="Clear" class="button button-link-delete" name="yotdbg-clear" id="yotdbg-clear-submit"/></form>';
				}
			?>
		</div>
		<hr>
		<div class="clear extra-settings hidden">
			<form method="post">
				<input type="submit" value="Do things" name="extra_button" class="button">
				<?= wp_nonce_field( 'extra_button' ); ?>
			</form>
			<hr>
		</div>
    </div>
    <?php
}

function wc_yotpo_update_setting( $setting, $value ) {
	$settings = get_option( 'yotpo_settings', wc_yotpo_get_default_settings() );
	if ( array_key_exists( $setting, $settings ) ) {
		$settings[ $setting ] = $value;
		update_option( 'yotpo_settings', $settings );
	}
}

function wc_yotpo_get_setting( $setting ) {
	$settings = get_option( 'yotpo_settings', false );
	if ( array_key_exists( $setting, $settings ) ) {
		return $settings[ $setting ];
	}
}