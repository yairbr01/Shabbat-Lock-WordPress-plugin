<?php
/**
 * Plugin Name: Shabbat Lock
 * Description: Locking the site on Shabbat and holidays by showing an Elementor popup. Times and holidays data is taken from the Hebcal.com API.
 * Version: 1.0
 * Author: Yair Broyer
 * Author URI: https://github.com/yairbr01
 */

add_action( 'admin_menu', 'shabbat_lock_menu' );
function shabbat_lock_menu() {
    add_options_page( 'Shabbat Lock Settings', 'Shabbat Lock', 'manage_options', 'shabbat-lock-settings', 'shabbat_lock_options' );
}

function shabbat_lock_options() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    echo '<div class="wrap">';
    echo '<h1 class="wrap_h1">Shabbat Lock Settings</h1>';
    echo '<form action="options.php" method="post">';
    settings_fields( 'shabbat_lock_settings_group' );
    do_settings_sections( 'shabbat-lock-settings' );
    submit_button();
    echo '</form>';
    echo '</div>';

	echo '<style>
    .wrap {
		background-color: #FFFFFF;
		border-radius: 20px;
		border: 1px solid #CCCCCC;
		padding: 50px;
		width: 50%;
		margin: 50px 0px 50px 0px;
	}
	.wp-core-ui select {
		width: 100%;
    	border: 1px solid #CCCCCC;
	    border-radius: 7.5px;
    	padding: 5px 10px 5px 10px;
	}
	.wp-core-ui .button-primary {
		border-radius: 7.5px;
		padding: 5px 20px 5px 20px;
		font-size: 15px;
	}
	.form-table th {
		vertical-align: middle;
		font-size: 15px;
	}
	.wrap_h1 {
		color: #FFFFFF;
		background-color: black;
		padding: 5px 10px 5px 10px !important;
		border-radius: 7.5px;
		margin-bottom: 25px !important;
	}
    </style>';
}

add_action( 'admin_init', 'shabbat_lock_settings' );
function shabbat_lock_settings() {
    register_setting( 'shabbat_lock_settings_group', 'shabbat_lock_setting' );
    add_settings_section( 'shabbat_lock_settings_section', '', '', 'shabbat-lock-settings' );
    add_settings_field( 'shabbat_lock_select_popup', 'Please select a popup', 'shabbat_lock_select_popup_render', 'shabbat-lock-settings', 'shabbat_lock_settings_section' );
    add_settings_field( 'shabbat_lock_tzeit_time', 'Please select tzeit time', 'shabbat_lock_select_tzeit_time_render', 'shabbat-lock-settings', 'shabbat_lock_settings_section' );
}

function shabbat_lock_select_popup_render() {
    $options = get_option( 'shabbat_lock_setting' );
    $selected = isset( $options['shabbat_lock_select_popup'] ) ? $options['shabbat_lock_select_popup'] : '';
    echo '<select id="shabbat_lock_select_popup" name="shabbat_lock_setting[shabbat_lock_select_popup]" class="select_field">';

    $args = [
		'posts_per_page' => -1,
        'post_type' => 'elementor_library',
        'meta_key'     => '_elementor_template_type',
        'meta_value'   => 'popup',
	];
    $elementor_library_posts = new WP_Query($args);

    foreach ( $elementor_library_posts->posts as $post ) {
        echo '<option value="' . $post->ID . '" '.selected( $selected, "$post->ID", false ).'>' . $post->post_title . '</option>';
	}    
    echo '</select>';
}

function shabbat_lock_select_tzeit_time_render() {
    $options = get_option( 'shabbat_lock_setting' );
    $selected = isset( $options['shabbat_lock_tzeit_time'] ) ? $options['shabbat_lock_tzeit_time'] : '';
    echo '<select id="shabbat_lock_tzeit_time" name="shabbat_lock_setting[shabbat_lock_tzeit_time]" class="select_field">';
    echo '<option value="tzeit42min" '.selected( $selected, 'tzeit42min', false ).'>tzeit 42 minutes</option>';
    echo '<option value="tzeit50min" '.selected( $selected, 'tzeit50min', false ).'>tzeit 50 minutes</option>';
    echo '<option value="tzeit72min" '.selected( $selected, 'tzeit72min', false ).'>tzeit 72 minutes</option>';
    echo '</select>';
}

function shabbat_lock_display_popup() {
    $options = get_option( 'shabbat_lock_setting' );
    $popup_id = isset( $options['shabbat_lock_select_popup'] ) ? $options['shabbat_lock_select_popup'] : '';
    $show_popup = false;
	
	$user_ip = $_SERVER['REMOTE_ADDR'];
	$location = json_decode(file_get_contents("https://ipinfo.io/{$user_ip}/json"));
	$timezone = $location->timezone;

	$tzeit_var = isset( $options['shabbat_lock_tzeit_time'] ) ? $options['shabbat_lock_tzeit_time'] : '';

	$tz = new DateTimeZone($timezone);

    $location = $tz->getLocation();
    $latitude = $location['latitude'];
    $longitude = $location['longitude'];

	date_default_timezone_set( $timezone );
	$date = date( 'Y-m-d' );    
	$time = date( 'H:i:s' );
	$day = date('w', strtotime($date));
		
	$hebcal_date = file_get_contents( "https://www.hebcal.com/converter?cfg=json&date=$date&g2h=1&strict=1" );
	$hebcal_time = file_get_contents( "https://www.hebcal.com/zmanim?cfg=json&tzid=$timezone&latitude=$latitude&longitude=$longitude&date=$date" );
	
	$hebcal_date_obj = json_decode($hebcal_date);
	$events = $hebcal_date_obj->events;
	$hebrew_year =  $hebcal_date_obj->hy;
	
	$hebcal_time_obj = json_decode($hebcal_time);
	$times = $hebcal_time_obj->times;
	$times_array = json_decode(json_encode ( $times ) , true);
	
	$sunset = date('H:i:s', strtotime( $times_array['sunset'] ));
	$tzeit = date('H:i:s', strtotime( $times_array["$tzeit_var"] ));
		
	$holidays = array( "Rosh Hashana $hebrew_year", 'Rosh Hashana II', 'Yom Kippur', 'Sukkot I', 'Shmini Atzeret', 'Pesach I', 'Pesach VII', 'Shavuot I' );
	$erev_holidays = array( 'Erev Rosh Hashana', 'Erev Yom Kippur', 'Erev Sukkot', 'Sukkot VII (Hoshana Raba)', 'Erev Pesach', "Pesach VI (CH''M)", 'Erev Shavuot' );
	
	foreach ( $events as $event ) {
		if ( in_array($event, $holidays ) ) {
			if ( $time < $tzeit ) {
				$show_popup = true;
			}
		} elseif ( in_array($event, $erev_holidays ) ) {
			if ( $time > $sunset ) {
				$show_popup = true;
			}
		}	
	}

	if ( $day == '5' ) {
       	if ( $time > $sunset ) {
       		$show_popup = true;
       	}
	} elseif ( $day == '6' ) {
       	if ( $time < $tzeit ) {
       		$show_popup = true;
       	}	
    }
	
	if ( $show_popup ) {
		echo '<script type="text/javascript"> function popup_shabat_func() { elementorProFrontend.modules.popup.showPopup( { id: ' . $popup_id . ' } ); } window.onload = popup_shabat_func;</script>';
	}

}
add_action( 'wp_footer', 'shabbat_lock_display_popup' );
