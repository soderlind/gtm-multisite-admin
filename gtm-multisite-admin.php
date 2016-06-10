<?php
/**
 * Plugin Name: Google Tag Manager Multisite Admin
 * Plugin URI: https://github.com/soderlind/gtm-multisite-admin
 * Description: Google Tag Manager Multisite Admin is an add-on to <a href="https://wordpress.org/plugins/duracelltomi-google-tag-manager/">DuracellTomi's Google Tag Manager for WordPress</a> plugin. With the add-on, you can add Google Tag Manager (GTM) IDs on the Network Sites page.
 * Author: Per Soderlind
 * Version: 0.0.3
 * Author URI: http://soderlind.no
 * GitHub Plugin URI: soderlind/gtm-multisite-admin
 */


if ( defined( 'ABSPATH' ) ) {
	define( 'GTM_PLUGIN_NAME', 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' );
	GTM_Multisite_Admin::instance();
}

class GTM_Multisite_Admin {

	private static $instance;
	private $pages_not_in_menu = array();

	public static function instance() {
		if ( self::$instance ) {
			return self::$instance;
		}
		self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		if ( is_network_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'gtm_multisite_admin_scripts' ) );
			add_filter( 'wpmu_blogs_columns', array( $this, 'add_new_columns' ) );
			add_action( 'manage_sites_custom_column', array( $this, 'manage_columns' ), 10, 2 );
			add_filter( 'manage_sites-network_sortable_columns', array( $this, 'gtm_multisite_admin_sortable' ) ); //nonworkable, yet
		}
		if ( is_admin() ) {
			add_action( 'wp_ajax_gtm_multisite_admin_change_tag', array( $this, 'gtm_multisite_admin_change_tag' ) );
		}
	}

	/**
	 * Load scripts
	 */
	function gtm_multisite_admin_scripts() {
		// Multisite fix, use home_url() if domain mapped to avoid cross-domain issues.
		$http_scheme = ( is_ssl() ) ? 'https' : 'http';
		if ( home_url() != site_url() ) {
			$ajaxurl = home_url( '/wp-admin/admin-ajax.php', $http_scheme );
		} else {
			$ajaxurl = site_url( '/wp-admin/admin-ajax.php', $http_scheme );
		}
		$url = plugins_url( '', __FILE__ );
		wp_enqueue_script( 'change_gtm_id', $url . '/js/gtm-multisite-admin.js', array( 'jquery', 'jquery-effects-core' ), MSPORTFOLIO_VERSION );
		wp_localize_script( 'change_gtm_id', 'gtm_multisite_options', array(
				'nonce' => wp_create_nonce( 'gtm_multisite_admin_security' ),
				'ajaxurl' => $ajaxurl,
			)
		);
	}

	/**
	 * Add new column function
	 *
	 * @param [type]  $columns [description]
	 */
	public function add_new_columns( $columns ) {
		$columns['gtm-tag'] = __( 'GTM ID', 'dss-web' );
		return $columns;
	}

	/**
	 * Render columns function
	 *
	 * @param [type]  $column_name [description]
	 * @param [type]  $site_id     [description]
	 * @return [type]              [description]
	 */
	public function manage_columns( $column_name, $site_id ) {

		if ( 'gtm-tag' == $column_name ) {
			echo '<div class="gtm-multisite">';
			//delete_blog_option( $site_id, 'gtm4wp-options');
			$active_plugins = get_blog_option( $site_id, 'active_plugins' );
			$gtm_options = (array) get_blog_option( $site_id, 'gtm4wp-options' );
			if ( count( $gtm_options ) && isset( $gtm_options['gtm-code'] ) ) {
				printf( '<a href="#" class="gtm-multisite-tag" title="Click to change GTM Id" data-siteid="%s" >%s</a>
					<input type="text"/ style="display:none">&nbsp;
					<a href="%s/wp-admin/options-general.php?page=gtm4wp-settings">
					<span class="dashicons dashicons-admin-settings"></span></a>',
					$site_id,
					$gtm_options['gtm-code'],
					get_blog_option( $site_id, 'siteurl' )
				);
			} elseif ( is_plugin_active_for_network( GTM_PLUGIN_NAME ) || array_search( GTM_PLUGIN_NAME, $active_plugins ) ) {
				printf( '<a href="#" class="gtm-multisite-tag" title="Click to add GTM Id" data-siteid="%s" >Add ID</a>
					<input type="text" value="" style="display:none">&nbsp;
					<a href="%s/wp-admin/options-general.php?page=gtm4wp-settings">
					<span class="dashicons dashicons-admin-settings"></a></span>',
					$site_id,
					get_blog_option( $site_id, 'siteurl' )
				);
			} else {
				// gtm not added for site
				echo 'NO GTM';
			}
			echo '</div>';
		}
	}

	/**
	 * Register the column as sortable
	 *
	 * @param array   $columns [description]
	 * @return array          [description]
	 */
	function gtm_multisite_admin_sortable( $columns ) {
		$columns['gtm-tag'] = 'gtm-tag';
		return $columns;
	}

	/**
	 * Ajax callback function
	 *
	 * @return json encoded string
	 */
	public function gtm_multisite_admin_change_tag() {
		header( 'Content-type: application/json' );
		if ( check_ajax_referer( 'gtm_multisite_admin_security', 'security', false ) ) {
			$site_id   = filter_var( $_POST['site_id'],    FILTER_VALIDATE_INT, array( 'default' => 0 ) );
			$gtm_tag = filter_var( $_POST['gtm_tag'],  FILTER_SANITIZE_STRING, array( 'default' => 'Add ID' ) );
			if ( ! $site_id ) {
				$response['data'] = 'something went wrong ...';
				echo json_encode( $response );
				die();
			}
			if ( isset( $gtm_tag ) || '' == $gtm_tag ) {
				if ( 'Add ID' != $gtm_tag ) {
					if ( '' == $gtm_tag ) {
						delete_blog_option( $site_id, 'gtm4wp-options' );
						$response['text']     = 'Add ID';
						$response['response'] = 'success';
					} else {
						$gtm_tag_options = get_blog_option( $site_id, 'gtm4wp-options' );
						$gtm_tag_options['gtm-code'] = $gtm_tag;
						if ( true !== update_blog_option( $site_id, 'gtm4wp-options', $gtm_tag_options ) ) {
							$response['response'] = 'failed';
							$response['message']  = 'update_blog_option failed';
						} else {
							$response['text']     = $gtm_tag;
							$response['response'] = 'success';
						}
					}
				}
			} else {
				$response['response'] = 'failed';
				$response['message']     = 'something went wrong ...';
			}
		} else {
			$response['response'] = 'failed';
			$response['message']  = 'invalid nonse';
		}
		echo json_encode( $response );
		die();
	}
}
