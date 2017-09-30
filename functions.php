<?php
/*
Plugin Name: Asset Performance for WP Engine
Description: Find out how often your static assets are served on WP Engine.
Version:     0.0.1
Author:      Nate Gay
Author URI:  https://nategay.me/
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
*/

function create_db_tables() {
  global $wpdb;
  $table_name = $wpdb->prefix . "asset_performance";
  $charset_collate = $wpdb->get_charset_collate();
  $sql = "CREATE TABLE $table_name (
    `id` mediumint(9) NOT NULL AUTO_INCREMENT,
    `date` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    `asset` text DEFAULT '' NOT NULL,
    `ip` text DEFAULT '' NOT NULL,
    `status` text DEFAULT '' NOT NULL,
    `referrer` text DEFAULT '' NOT NULL,
    PRIMARY KEY  (id)
  ) $charset_collate;";
  
  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  dbDelta( $sql );
}

function get_log() {
  $log_url = site_url() . WpeCommon::get_access_log_url( 'previous' );
  $log = wp_remote_get($log_url);
  if ( is_wp_error($log) ) {
    echo "Could not retrieve log file";
    return null;
  }
  return $log['body'];
}

function parse_log( $log ) {
  $log_array = explode("\n", $log);
  $static_asset_regex = '/^(.*?)\s.*?\[(.*?)\s.*?GET\s(.*?\.(jpe?g|gif|png|css|js|ico|zip|7z|tgz|gz|rar|bz2|do[ct][mx]?|xl[ast][bmx]?|exe|pdf|p[op][ast][mx]?|sld[xm]?|thmx?|txt|tar|midi?|wav|bmp|rtf|avi|mp\d|mpg|iso|mov|djvu|dmg|flac|r70|mdf|chm|sisx|sis|flv|thm|bin|swf|cert|otf|ttf|eot|svgx?|woff2?|jar|class|log|web[ma]|ogv).*?)\s.*?"\s(\d+).*?".*?"\s"(.*?)"/';
  return array_reduce( $log_array, function($asset_array, $log_line) use ($static_asset_regex) {
    $is_static_asset = preg_match( $static_asset_regex, $log_line, $match );
    if ( $is_static_asset ) {
      $date = DateTime::createFromFormat('d/M/Y:H:i:s', $match[2]);
      $asset_array[] = array(
        'ip' => $match[1],
        'date' => $date->format('Y-m-d H:i:s'),
        'asset' => $match[3],
        'status' => $match[5],
        'referrer' => $match[6],
      );
    }
    return $asset_array;
  }, [] );
}

function add_to_database( $asset_array ) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'asset_performance';
  foreach ($asset_array as $asset) {
    $wpdb->insert( $table_name, $asset );
  }
}

function run() {
  $log = get_log();
  $asset_array = parse_log($log);
  add_to_database($asset_array);
}

function register_cron() {
    if (! wp_next_scheduled ( 'asset_performance' )) {
	     wp_schedule_event(time(), 'daily', 'asset_performance_cron');
    }
}

function unregister_cron() {
	wp_clear_scheduled_hook('cron_reqeust_emotion_data');
}

// On activation, register cron and create db tables
register_activation_hook(__FILE__, 'register_cron');
register_activation_hook( __FILE__, 'create_db_tables' );

add_action('asset_performance_cron', 'run');

// On deactivation, deregister cron
register_deactivation_hook(__FILE__, 'unregister_cron');
