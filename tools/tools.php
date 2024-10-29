<?php
/**
 * Author: Alin Marcu
 * Author URI: https://deconf.com
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();
if ( ! class_exists( 'AIWP_Tools' ) ) {

	class AIWP_Tools {

		public static function get_countrycodes() {
			include 'iso3166.php';
			return $country_codes;
		}

		public static function guess_default_domain( $profiles, $index = 3 ) {
			$domain = get_option( 'siteurl' );
			$domain = str_ireplace( array( 'http://', 'https://' ), '', $domain );
			if ( ! empty( $profiles ) ) {
				foreach ( $profiles as $items ) {
					if ( strpos( $items[$index], $domain ) ) {
						return $items[1];
					}
				}
				return $profiles[0][1];
			} else {
				return '';
			}
		}

		public static function get_selected_profile( $profiles, $profile ) {
			if ( ! empty( $profiles ) ) {
				foreach ( $profiles as $item ) {
					if ( isset( $item[1] ) && $item[1] == $profile ) {
						return $item;
					}
				}
			}
		}

		public static function get_root_domain() {
			$url = site_url();
			$root = explode( '/', $url );
			preg_match( '/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', str_ireplace( 'www', '', isset( $root[2] ) ? $root[2] : $url ), $root );
			if ( isset( $root['domain'] ) ) {
				return $root['domain'];
			} else {
				return '';
			}
		}

		public static function strip_protocol( $domain ) {
			return str_replace( array( "https://", "http://", " " ), "", $domain );
		}

		public static function colourVariator( $colour, $per ) {
			$colour = substr( $colour, 1 );
			$rgb = '';
			$per = $per / 100 * 255;
			if ( $per < 0 ) {
				// Darker
				$per = abs( $per );
				for ( $x = 0; $x < 3; $x++ ) {
					$c = hexdec( substr( $colour, ( 2 * $x ), 2 ) ) - $per;
					$c = ( $c < 0 ) ? 0 : dechex( (int) $c );
					$rgb .= ( strlen( $c ) < 2 ) ? '0' . $c : $c;
				}
			} else {
				// Lighter
				for ( $x = 0; $x < 3; $x++ ) {
					$c = hexdec( substr( $colour, ( 2 * $x ), 2 ) ) + $per;
					$c = ( $c > 255 ) ? 'ff' : dechex( (int) $c );
					$rgb .= ( strlen( $c ) < 2 ) ? '0' . $c : $c;
				}
			}
			return '#' . $rgb;
		}

		public static function variations( $base ) {
			$variations[] = $base;
			$variations[] = self::colourVariator( $base, - 10 );
			$variations[] = self::colourVariator( $base, + 10 );
			$variations[] = self::colourVariator( $base, + 20 );
			$variations[] = self::colourVariator( $base, - 20 );
			$variations[] = self::colourVariator( $base, + 30 );
			$variations[] = self::colourVariator( $base, - 30 );
			return $variations;
		}

		public static function check_roles( $access_level, $tracking = false ) {
			if ( is_user_logged_in() && isset( $access_level ) ) {
				$current_user = wp_get_current_user();
				$roles = (array) $current_user->roles;
				if ( ( current_user_can( 'manage_options' ) ) && ! $tracking ) {
					return true;
				}
				if ( count( array_intersect( $roles, $access_level ) ) > 0 ) {
					return true;
				} else {
					return false;
				}
			}
		}

		public static function unset_cookie( $name ) {
			$name = 'aiwp_wg_' . $name;
			setcookie( $name, '', time() - 3600, '/' );
			$name = 'aiwp_ir_' . $name;
			setcookie( $name, '', time() - 3600, '/' );
		}

		/**
		 * Cache Helper function. I don't use transients because cleanup plugins can break their functionality
		 * @param string $name
		 * @param mixed $value
		 * @param number $expiration
		 */
		public static function set_cache( $name, $value, $expiration = 0 ) {
			update_option( '_aiwp_cache_' . $name, $value, 'no' );
			if ( $expiration ) {
				update_option( '_aiwp_cache_timeout_' . $name, time() + (int) $expiration, 'no' );
			} else {
				update_option( '_aiwp_cache_timeout_' . $name, time() + 7 * 24 * 3600, 'no' );
			}
		}

		/**
		 * Cache Helper function. I don't use transients because cleanup plugins can break their functionality
		 * @param string $name
		 * @param mixed $value
		 * @param number $expiration
		 */
		public static function delete_cache( $name ) {
			delete_option( '_aiwp_cache_' . $name );
			delete_option( '_aiwp_cache_timeout_' . $name );
		}

		/**
		 * Cache Helper function. I don't use transients because cleanup plugins can break their functionality
		 * @param string $name
		 * @param mixed $value
		 * @param number $expiration
		 */
		public static function get_cache( $name ) {
			$value = get_option( '_aiwp_cache_' . $name );
			$expires = get_option( '_aiwp_cache_timeout_' . $name );
			if ( false === $value || ! isset( $value ) || ! isset( $expires ) ) {
				return false;
			}
			if ( $expires < time() ) {
				delete_option( '_aiwp_cache_' . $name );
				delete_option( '_aiwp_cache_timeout_' . $name );
				return false;
			} else {
				return $value;
			}
		}

		/**
		 * Cache Helper function. I don't use transients because cleanup plugins can break their functionality
		 */
		public static function clear_cache() {
			global $wpdb;
			$sqlquery = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%%aiwp_cache_%%'" );
		}

		public static function delete_expired_cache() {
			global $wpdb, $wp_version;
			if ( wp_using_ext_object_cache() ) {
				return;
			}
			if ( version_compare( $wp_version, '4.0.0', '>=' ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
				WHERE a.option_name LIKE %s
				AND a.option_name NOT LIKE %s
				AND b.option_name = CONCAT( '_aiwp_cache_timeout_', SUBSTRING( a.option_name, 13 ) )
				AND b.option_value < %d", $wpdb->esc_like( '_aiwp_cache_' ) . '%', $wpdb->esc_like( '_aiwp_cache_timeout_' ) . '%', time() ) );
				if ( ! is_multisite() ) {
					// Single site stores site transients in the options table.
					$wpdb->query( $wpdb->prepare( "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
					WHERE a.option_name LIKE %s
					AND a.option_name NOT LIKE %s
					AND b.option_name = CONCAT( '_site_aiwp_cache_timeout_', SUBSTRING( a.option_name, 18 ) )
					AND b.option_value < %d", $wpdb->esc_like( '_site_aiwp_cache_' ) . '%', $wpdb->esc_like( '_site_aiwp_cache_timeout_' ) . '%', time() ) );
				} elseif ( is_multisite() && is_main_site() && is_main_network() ) {
					// Multisite stores site transients in the sitemeta table.
					$wpdb->query( $wpdb->prepare( "DELETE a, b FROM {$wpdb->sitemeta} a, {$wpdb->sitemeta} b
					WHERE a.meta_key LIKE %s
					AND a.meta_key NOT LIKE %s
					AND b.meta_key = CONCAT( '_site_aiwp_cache_timeout_', SUBSTRING( a.meta_key, 18 ) )
					AND b.meta_value < %d", $wpdb->esc_like( '_site_aiwp_cache_' ) . '%', $wpdb->esc_like( '_site_aiwp_cache_timeout_' ) . '%', time() ) );
				}
			} else {
				$wpdb->query( $wpdb->prepare( "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
				WHERE a.option_name LIKE %s
				AND a.option_name NOT LIKE %s
				AND b.option_name = CONCAT( '_aiwp_cache_timeout_', SUBSTRING( a.option_name, 13 ) )
				AND b.option_value < %d", like_escape( '_aiwp_cache_' ) . '%', like_escape( '_aiwp_cache_timeout_' ) . '%', time() ) );
				if ( ! is_multisite() ) {
					// Single site stores site transients in the options table.
					$wpdb->query( $wpdb->prepare( "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
					WHERE a.option_name LIKE %s
					AND a.option_name NOT LIKE %s
					AND b.option_name = CONCAT( '_site_aiwp_cache_timeout_', SUBSTRING( a.option_name, 18 ) )
					AND b.option_value < %d", like_escape( '_site_aiwp_cache_' ) . '%', like_escape( '_site_aiwp_cache_timeout_' ) . '%', time() ) );
				} elseif ( is_multisite() && is_main_site() && is_main_network() ) {
					// Multisite stores site transients in the sitemeta table.
					$wpdb->query( $wpdb->prepare( "DELETE a, b FROM {$wpdb->sitemeta} a, {$wpdb->sitemeta} b
					WHERE a.meta_key LIKE %s
					AND a.meta_key NOT LIKE %s
					AND b.meta_key = CONCAT( '_site_aiwp_cache_timeout_', SUBSTRING( a.meta_key, 18 ) )
					AND b.meta_value < %d", like_escape( '_site_aiwp_cache_' ) . '%', like_escape( '_site_aiwp_cache_timeout_' ) . '%', time() ) );
				}
			}
		}

		public static function get_sites( $args ) { // Use wp_get_sites() if WP version is lower than 4.6.0
			global $wp_version;
			if ( version_compare( $wp_version, '4.6.0', '<' ) ) {
				return wp_get_sites( $args );
			} else {
				foreach ( get_sites( $args ) as $blog ) {
					$blogs[] = (array) $blog; // Convert WP_Site object to array
				}
				return $blogs;
			}
		}

		/**
		 * Loads a view file
		 *
		 * $data parameter will be available in the template file as $data['value']
		 *
		 * @param string $template - Template file to load
		 * @param array $data - data to pass along to the template
		 * @return boolean - If template file was found
		 **/
		public static function load_view( $path, $data = array(), $globalsitetag = 0 ) {
			if ( file_exists( AIWP_DIR . $path ) ) {
				require ( AIWP_DIR . $path );
				return true;
			}
			return false;
		}

		public static function doing_it_wrong( $function, $message, $version ) {
			if ( WP_DEBUG && apply_filters( 'doing_it_wrong_trigger_error', true ) ) {
				if ( is_null( $version ) ) {
					$version = '';
				} else {
					/* translators: %s: version number */
					$version = sprintf( __( 'This message was added in version %s.', 'analytics-insights' ), $version );
				}
				/* translators: Developer debugging message. 1: PHP function name, 2: Explanatory message, 3: Version information message */
				trigger_error( sprintf( __( '%1$s was called <strong>incorrectly</strong>. %2$s %3$s', 'analytics-insights' ), $function, $message, $version ) );
			}
		}

		public static function get_dom_from_content( $content ) {
			$libxml_previous_state = libxml_use_internal_errors( true );
			if ( class_exists( 'DOMDocument' ) ) {
				$dom = new DOMDocument();
				$result = $dom->loadHTML( '<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8"></head><body>' . $content . '</body></html>' );
				libxml_clear_errors();
				libxml_use_internal_errors( $libxml_previous_state );
				if ( ! $result ) {
					return false;
				}
				return $dom;
			} else {
				self::set_error( __( 'DOM is disabled or libxml PHP extension is missing. Contact your hosting provider. Automatic tracking of events for AMP pages is not possible.', 'analytics-insights' ), 24 * 60 * 60 );
				return false;
			}
		}

		public static function get_content_from_dom( $dom ) {
			$out = '';
			$body = $dom->getElementsByTagName( 'body' )->item( 0 );
			foreach ( $body->childNodes as $node ) {
				$out .= $dom->saveXML( $node );
			}
			return $out;
		}

		public static function array_keys_rename( $options, $keys ) {
			foreach ( $keys as $key => $newkey ) {
				if ( isset( $options[$key] ) ) {
					$options[$newkey] = $options[$key];
					unset( $options[$key] );
				}
			}
			return $options;
		}

		public static function set_error( $e, $timeout, $ajax = false ) {
			if ( $ajax ) {
				self::set_cache( 'ajax_errors', esc_html( print_r( $e, true ) ), $timeout );
			} else {
				if ( is_object( $e ) ) {
					if ( method_exists( $e, 'get_error_code' ) && method_exists( $e, 'get_error_message' ) ) {
						$error_code = $e->get_error_code();
						if ( 500 == $error_code || 503 == $error_code ) {
							$timeout = 60;
						}
						self::set_cache( 'api_errors', array( $e->get_error_code(), $e->get_error_message(), $e->get_error_data() ), $timeout );
					} else {
						self::set_cache( 'api_errors', array( 600, array(), esc_html( print_r( $e, true ) ) ), $timeout );
					}
				} else if ( is_array( $e ) ) {
					self::set_cache( 'api_errors', array( 601, array(), esc_html( print_r( $e, true ) ) ), $timeout );
				} else {
					self::set_cache( 'api_errors', array( 602, array(), esc_html( print_r( $e, true ) ) ), $timeout );
				}
				// Count Errors until midnight
				$midnight = strtotime( "tomorrow 00:00:00" ); // UTC midnight
				$midnight = $midnight + 8 * 3600; // UTC 8 AM
				$tomidnight = $midnight - time();
				$errors_count = self::get_cache( 'errors_count' );
				$errors_count = (int) $errors_count + 1;
				self::set_cache( 'errors_count', $errors_count, $tomidnight );
			}
		}

		public static function anonymize_options( $options ) {
			global $wp_version;
			$options['wp_version'] = $wp_version;
			$options['aiwp_version'] = AIWP_CURRENT_VERSION;
			if ( $options['token'] && ( ! WP_DEBUG || ( is_multisite() && ! current_user_can( 'manage_network_options' ) ) ) ) {
				$options['token'] = 'HIDDEN';
			} else {
				$options['token'] = (array) $options['token'];
				unset( $options['token']['challenge'] );
			}
			if ( $options['client_secret'] ) {
				$options['client_secret'] = 'HIDDEN';
			}
			return $options;
		}

		public static function system_info() {
			$info = '';
			// Server Software
			$server_soft = "-";
			if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
				$server_soft = $_SERVER['SERVER_SOFTWARE'];
			}
			$info .= 'Server Info: ' . sanitize_text_field( $server_soft ) . "\n";
			// PHP version
			if ( defined( 'PHP_VERSION' ) ) {
				$info .= 'PHP Version: ' . PHP_VERSION . "\n";
			} else if ( defined( 'HHVM_VERSION' ) ) {
				$info .= 'HHVM Version: ' . HHVM_VERSION . "\n";
			} else {
				$info .= 'Other Version: ' . '-' . "\n";
			}
			// cURL Info
			if ( function_exists( 'curl_version' ) && function_exists( 'curl_exec' ) ) {
				$curl_version = curl_version();
				if ( ! empty( $curl_version ) ) {
					$curl_ver = $curl_version['version'] . " " . $curl_version['ssl_version'];
				} else {
					$curl_ver = '-';
				}
			} else {
				$curl_ver = '-';
			}
			$info .= 'cURL Info: ' . $curl_ver . "\n";
			// Gzip
			if ( is_callable( 'gzopen' ) ) {
				$gzip = true;
			} else {
				$gzip = false;
			}
			$gzip_status = ( $gzip ) ? 'Yes' : 'No';
			$info .= 'Gzip: ' . $gzip_status . "\n";
			return $info;
		}

		/**
		 * Follows the SCRIPT_DEBUG settings
		 * @param string $script
		 * @return string
		 */
		public static function script_debug_suffix() {
			if ( defined( 'SCRIPT_DEBUG' ) and SCRIPT_DEBUG ) {
				return '';
			} else {
				return '.min';
			}
		}

		/**
		 * Dimensions and metric mapping from GA3 to GA4
		 * @param string $value
		 * @return string
		 */
		public static function ga3_ga4_mapping( $value ) {
			$value = str_replace( 'ga:', '', $value );
			$list = [ 'users' => 'totalUsers', 'sessionDuration' => 'userEngagementDuration', 'fullReferrer' => 'pageReferrer', 'source' => 'sessionSource', 'medium' => 'sessionMedium', 'dataSource' => 'platform',
				// 'pagePath' => 'pagePathPlusQueryString',
				'pageviews' => 'screenPageViews', 'pageviewsPerSession' => 'screenPageViewsPerSession', 'timeOnPage' => 'userEngagementDuration', 'channelGrouping' => 'sessionDefaultChannelGrouping', 'dayOfWeekName' => 'dayOfWeek', 'visitBounceRate' => 'bounceRate', 'organicSearches' => 'engagedSessions', 'socialNetwork' => 'language', 'visitorType' => 'newVsReturning', 'uniquePageviews' => 'sessions' ];
			if ( isset( $list[$value] ) ) {
				return $list[$value];
			} else {
				return $value;
			}
		}

		public static function secondstohms( $value ) {
			$value = (float) $value;
			$hours = floor( $value / 3600 );
			$hours = $hours < 10 ? '0' . $hours : (string) $hours;
			$minutes = floor( (int) ( $value / 60 ) % 60 );
			$minutes = $minutes < 10 ? '0' . $minutes : (string) $minutes;
			$seconds = floor( (int)$value % 60 );
			$seconds = $seconds < 10 ? '0' . $seconds : (string) $seconds;
			return $hours . ':' . $minutes . ':' . $seconds;
		}

		public static function is_amp() {
			if ( is_singular( 'web-story' ) ) {
				return true;
			}
			return function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
		}

		public function generate_random( $length = 10 ) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen( $characters );
			$randomString = '';
			for ( $i = 0; $i < $length; $i++ ) {
				$randomString .= $characters[random_int( 0, $charactersLength - 1 )];
			}
			return $randomString;
		}

		public static function report_errors() {
			if ( AIWP_Tools::get_cache( 'api_errors' ) ) {
				$info = AIWP_Tools::system_info();
				$info .= 'AIWP Version: ' . AIWP_CURRENT_VERSION;
				$sep = "\n---------------------------\n";
				$error_report = $sep . print_r( AIWP_Tools::get_cache( 'api_errors' ), true );
				$error_report .= $sep . AIWP_Tools::get_cache( 'errors_count' );
				$error_report .= $sep . $info;
				$error_report = urldecode( $error_report );
				$url = AIWP_ENDPOINT_URL . 'aiwp-report.php';
				/* @formatter:off */
		$response = wp_remote_post( $url, array(
		 'method' => 'POST',
		 'timeout' => 45,
		 'redirection' => 5,
		 'httpversion' => '1.0',
		 'blocking' => true,
		 'headers' => array(),
		 'body' => array( 'error_report' => esc_html( $error_report ) ),
		 'cookies' => array()
		 )
		 );
		/* @formatter:off */
		 }
		}

		/** Keeps compatibility with WP < 5.3.0
		 *
		 * @return string
		 */
		public static function timezone_string() {
			$timezone_string = get_option( 'timezone_string' );

			if ( $timezone_string ) {
				return $timezone_string;
			}

			$offset  = (float) get_option( 'gmt_offset' );
			$hours   = (int) $offset;
			$minutes = ( $offset - $hours );

			$sign      = ( $offset < 0 ) ? '-' : '+';
			$abs_hour  = abs( $hours );
			$abs_mins  = abs( $minutes * 60 );
			$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

			return $tz_offset;
		}

	}
}
