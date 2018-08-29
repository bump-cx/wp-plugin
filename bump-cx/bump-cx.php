<?php
/**
* Plugin Name: Bump CX
* Plugin URI: https://bump.cx/
* Version: 1.0.0
* Author: BumpCX
* Author URI: https://bump.cx/
* Description: Allows you to insert the Bump code into your WordPress WooCommerce store.
* License: GPL2
*/

/*  Copyright 2018 BumpCX

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Must include plugin.php to use is_plugin_active()
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
* Insert Headers and Footers Class
*/
class BumpCX {
	/**
	* Constructor
	*/
	public function __construct() {

		// Plugin Details
    $this->plugin               = new stdClass;
    $this->plugin->name         = 'bump-cx'; // Plugin Folder
    $this->plugin->displayName  = 'Bump CX'; // Plugin Name
    $this->plugin->version      = '1.0.0';
    $this->plugin->folder       = plugin_dir_path( __FILE__ );
    $this->plugin->url          = plugin_dir_url( __FILE__ );
    $this->plugin->db_welcome_dismissed_key = $this->plugin->name . '_welcome_dismissed_key';

		// Hooks
		add_action( 'admin_init', array( &$this, 'registerSettings' ) );
    add_action( 'admin_menu', array( &$this, 'adminPanelsAndMetaBoxes' ) );
    add_action( 'admin_notices', array( &$this, 'dashboardNotices' ) );
    add_action( 'wp_ajax_' . $this->plugin->name . '_dismiss_dashboard_notices', array( &$this, 'dismissDashboardNotices' ) );

    // Frontend Hooks
		add_action( 'wp_head', array( &$this, 'hook_metatag' ) );
    add_action( 'wp_head', array( &$this, 'frontendHeader' ) );
	}

    /**
     * Show relevant notices for the plugin
     */
    function dashboardNotices() {
        global $pagenow;

        if ( !get_option( $this->plugin->db_welcome_dismissed_key ) ) {
        	if ( ! ( $pagenow == 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] == 'bump-cx' ) ) {
	            $setting_page = admin_url( 'options-general.php?page=' . $this->plugin->name );
	            // load the notices view
                include_once( $this->plugin->folder . '/views/dashboard-notices.php' );
        	}
        }
    }

    /**
     * Dismiss the welcome notice for the plugin
     */
    function dismissDashboardNotices() {
    	check_ajax_referer( $this->plugin->name . '-nonce', 'nonce' );
        // user has dismissed the welcome notice
        update_option( $this->plugin->db_welcome_dismissed_key, 1 );
        exit;
    }

	/**
	* Register Settings
	*/
	function registerSettings() {
		register_setting( $this->plugin->name, 'bumpcx_insert_header', 'trim' );
	}

	/**
    * Register the plugin settings panel
    */
    function adminPanelsAndMetaBoxes() {
    	add_submenu_page( 'options-general.php', $this->plugin->displayName, $this->plugin->displayName, 'manage_options', $this->plugin->name, array( &$this, 'adminPanel' ) );
	}

    /**
    * Output the Administration Panel
    * Save POSTed data from the Administration Panel into a WordPress option
    */
    function adminPanel() {
			// only admin user can access this page
			if ( !current_user_can( 'administrator' ) ) {
				echo '<p>' . __( 'Sorry, you are not allowed to access this page.', $this->plugin->name ) . '</p>';
				return;
			}
			// Save Settings
			if ( isset( $_REQUEST['submit'] ) ) {
				// Check nonce
				if ( !isset( $_REQUEST[$this->plugin->name.'_nonce'] ) ) {
					// Missing nonce
					$this->errorMessage = __( 'nonce field is missing. Settings NOT saved.', $this->plugin->name );
				} elseif ( !wp_verify_nonce( $_REQUEST[$this->plugin->name.'_nonce'], $this->plugin->name ) ) {
						// Invalid nonce
						$this->errorMessage = __( 'Invalid nonce specified. Settings NOT saved.', $this->plugin->name );
				} else {
					// Save
					// $_REQUEST has already been slashed by wp_magic_quotes in wp-settings
					// so do nothing before saving
					update_option( 'bumpcx_api_key', $_REQUEST['bumpcx_api_key'] );
					update_option( $this->plugin->db_welcome_dismissed_key, 1 );
					$this->message = __( 'Settings Saved.', $this->plugin->name );
				}
			}

			// Get latest settings
			$this->settings = array(
				'bumpcx_api_key' => esc_html( wp_unslash( get_option( 'bumpcx_api_key' ) ) ),
			);

			// Load Settings Form
			include_once( WP_PLUGIN_DIR . '/' . $this->plugin->name . '/views/settings.php' );

    }

    /**
	* Loads plugin textdomain
	*/
	function loadLanguageFiles() {
		load_plugin_textdomain( $this->plugin->name, false, $this->plugin->name . '/languages/' );
	}

	/**
	* Outputs metatag to the frontend header
	*/
	function hook_metatag() {
		global $wp;
		$verification = get_option( 'bumpcx_api_key' );
		$output = '<meta name="bump-site-verification" content="'.$verification.'" />' . PHP_EOL;
		echo $output;
	}

	/**
	* Outputs script to the frontend header
	*/
	function frontendHeader() {
		global $wp;
		if ( is_order_received_page() ) {
			$location = "success";
			// Get the order details
			$order_id  = absint( $wp->query_vars['order-received'] );
			$orderemail = get_post_meta( $order_id, '_billing_email', true );
			$orderfname = get_post_meta( $order_id, '_billing_first_name', true );
			$orderlname = get_post_meta( $order_id, '_billing_last_name', true );
			$ordercity = get_post_meta( $order_id, '_billing_city', true );
			$orderstate = get_post_meta( $order_id, '_billing_state', true );
			$orderpostcode = get_post_meta( $order_id, '_billing_postcode', true );
			$ordercountry = get_post_meta( $order_id, '_billing_country', true );
			$ordertotal = get_post_meta( $order_id, '_order_total', true );
			$ordercurrency = get_post_meta( $order_id, '_order_currency', true );
		} elseif ( is_checkout() ) {
			$location = "checkout";
		} elseif ( is_cart() ) {
			$location = "cart";
		}	elseif ( is_product() ) {
			$location = "product";
		}	elseif ( is_shop() ) {
			$location = "category";
		}	elseif ( is_product_tag() ) {
			$location = "category";
		} elseif ( is_product_category() ) {
			$location = "category";
		} else {
			$location = "index";
		}
		$script = '<script type="text/javascript">' . PHP_EOL;
    $script .= 'var sp=sp||[];(function(){var e=["init","identify","track","trackLink","pageview"],t=function(e){return function(){sp.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var n=0;n<e.length;n++)sp[e[n]]=t(e[n])})(),sp.load=function(e,o){sp._endpoint=e;if(o){sp.init(o)};var t=document.createElement("script");t.type="text/javascript",t.async=!0,t.src=("https:"===document.location.protocol?"https://":"http://")+"d21ey8j28ejz92.cloudfront.net/analytics/v1/sp.min.js";var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(t,n)};' . PHP_EOL;
    if ($location == "success") {
			$script .= 'sp.track("Browse", { location: "'.$location.'", orderemail: "'.$orderemail.'", orderfname: "'.$orderfname.'", orderlname: "'.$orderlname.'", ordercity: "'.$ordercity.'", orderstate: "'.$orderstate.'", orderpostcode: "'.$orderpostcode.'", ordercountry: "'.$ordercountry.'", ordertotal: "'.$ordertotal.'", ordercurrency: "'.$ordercurrency.'" });' . PHP_EOL;
		} else {
			$script .= 'sp.track("Browse", { location: "'.$location.'" });' . PHP_EOL;
		}
    $script .= 'sp.load("https://sp.bump.cx:4443");' . PHP_EOL;
    $script .= '</script>' . PHP_EOL;
		echo $script;
	}

	/**
	* Outputs the given setting, if conditions are met
	*
	* @param string $setting Setting Name
	* @return output
	*/
	function output( $setting ) {
		// Ignore admin, feed, robots or trackbacks
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		// provide the opportunity to Ignore BumpCX via filters
		if ( apply_filters( 'disable_bumpcx', false ) ) {
			return;
		}

		// provide the opportunity to Ignore IHAF - header only via filters
		if ( 'bumpcx_insert_header' == $setting && apply_filters( 'disable_bumpcx_header', false ) ) {
			return;
		}

		// Get meta
		$meta = get_option( $setting );
		if ( empty( $meta ) ) {
			return;
		}
		if ( trim( $meta ) == '' ) {
			return;
		}

		// Output
		echo wp_unslash( $meta );
	}
}

if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

	$bumpcx = new BumpCX();

} else {

	/* Deactivate the plugin, and display our error notification */
	deactivate_plugins( '/bump-cx/bump-cx.php' );
	add_action( 'admin_notices' , 'bump_cx_display_admin_notice_error' );

}

/**
* Display our error admin notice if WooCommerce is not installed + active
*/
function bump_cx_display_admin_notice_error() {
	?>
		<!-- hide the 'Plugin Activated' default message -->
		<style>
		#message.updated {
			display: none;
		}
		</style>
		<!-- display our error message -->
		<div class="error">
			<p><?php _e( 'Bump CX for WooCommerce could not be activated because WooCommerce is not installed and active.', 'Bump CX' ); ?></p>
			<p><?php _e( 'Please install and activate ', 'Bump CX' ); ?><a href="<?php echo admin_url( 'plugin-install.php?tab=search&type=term&s=WooCommerce' ); ?>" title="WooCommerce">WooCommerce</a><?php _e( ' before activating the plugin.', 'Bump CX' ); ?></p>
		</div>
	<?php
}
