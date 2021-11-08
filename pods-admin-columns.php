<?php
/*
Plugin Name:        Pods Admin Columns
Plugin URI:         https://github.com/joker-x/pods-admin-columns
Description:        Add option for showing custom fields managed by Pods in WordPress admin dashboard.
Version:            1.0.2
Author:             joker-x
Author URI:         https://github.com/joker-x
Text Domain:        pods-admin-columns
Domain Path:        /languages/
Requires at least:  5.5
Requires PHP:       5.6
GitHub Plugin URI:  https://github.com/joker-x/pods-admin-columns
Primary Branch:     main
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'PODS_ADMIN_COLUMNS_VERSION', '1.0.2' );
define( 'PODS_ADMIN_COLUMNS_URL', plugin_dir_url( __FILE__ ) );
define( 'PODS_ADMIN_COLUMNS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Initialize plugin
 */
function pods_admin_columns_init() {
  global $pagenow;
  
  if ( ! function_exists( 'pods' ) ||  ! defined( 'PODS_DIR' ) ) {
    // Pods plugin has not been installed or active
    add_action('admin_notices', 'pods_admin_columns_install_pods');
    return;
  }

  if ( ! is_admin() ) {
    return; // This plugin only apply in wordpress admin dashboard 
  }

  // Load plugin textdomain
  //load_plugin_textdomain( 'pods-admin-columns', false, PODS_ADMIN_COLUMNS_DIR . 'languages/' );
  
  // Add configuration options
  add_filter( 'pods_admin_setup_edit_field_options', 'pods_admin_columns_add_options', 10, 2 );

  if ($pagenow == 'edit.php') {  
    // Add custom columns to WP dashboard
    add_filter('manage_posts_columns', 'pods_admin_columns_add');
  
    // Populate custom columns to WP dashboard
    add_action('manage_posts_custom_column', 'pods_admin_columns_populate');
  
    // Search by custom field
    add_filter('posts_join', 'pods_admin_columns_search_join', 10, 2 );
    add_filter('posts_where', 'pods_admin_columns_search_where', 10, 2 );
    add_filter('posts_distinct', 'pods_admin_columns_search_distinct' );
  }
}

add_action( 'plugins_loaded', 'pods_admin_columns_init', 20 );

// Add options to Pods Admin UI
function pods_admin_columns_add_options($options,$pod) {
  $options['advanced']['dashboard'] = [
    'name' => 'dashboard',
    'label' => __('WP Dasboard', 'pods-admin-columns'),
    'type' => 'heading'
  ];
  $options['advanced']['show_admin_column'] = [
    'name' => 'show_admin_column',
    'boolean_yes_label' => __('Show as column in dashboard', 'pods-admin-columns'),
    'type' => 'boolean',
    'default' => 0
  ];
  return $options;
}

// Return html with install or activate Pods required notice
function pods_admin_columns_install_pods() {
  $html = '<div class="notice notice-error is-dismissible"><p>';
  if (array_key_exists( "pods/init.php", get_plugins() )) {
    $html .= __('You need to activate Pods plugin.', 'pods-admin-columns');
  } else {
    $html .= __('You need to install Pods plugin.', 'pods-admin-columns');
  }
  $html .= '</p></div>';
  echo "$html";
}

// Add custom fields to WP Dashboard
function pods_admin_columns_add($columns) {
  $post_type = get_post_type();
  $fields_names = [];
  $fields_config = pods_config_get_all_fields( pods($post_type) );

  foreach ($fields_config as $field_name => $field_config) {
    if ($field_config['show_admin_column'] == "1") {
      $fields_names[$field_name] = $field_config['label'];
    }
  }

  $date = $columns['date'];
  unset($columns['date']);
  foreach ($fields_names as $field_name => $field_label) {
    $columns[$field_name] = $field_label;
  }
  $columns['date']=$date;
  return $columns;
}

// Show custom fields to WP Dashboard
function pods_admin_columns_populate($column) {
  echo pods_field_display (get_post_type(), get_the_ID(), $column);
}

// Functions for searching by custom fields

// SQL join
function pods_admin_columns_search_join( $join, $query ) {
  global $wpdb;
  $post_type = $query->get('post_type');

  if ( is_search() && $query->is_main_query() && !empty ($post_type) ) {

    if (pods_api()->pod_exists($post_type)) {
      $mypod = pods($post_type);
      if ($mypod->pod_data['storage'] == 'table') {
        $table_name = $wpdb->prefix.'pods_'.$mypod->pod_data['name'];
        $fields_names = [];
        $fields_types_allowed = ['text', 'website', 'email', 'link', 'phone'];
        $fields_config = pods_config_get_all_fields( $mypod );

        foreach ($fields_config as $field_name => $field_config) {
          if (($field_config['show_admin_column'] == "1") && in_array($field_config['type'], $fields_types_allowed)) {
            $fields_names[] = $field_name;
          }
        }

        if (count($fields_names) > 0) {
          $join .=' LEFT JOIN '.$table_name. ' ON '. $wpdb->posts . '.ID = ' . $table_name . '.id ';
        }
      } else {
        $join .=' LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
      }
    }
  }

  return $join;
}

// SQL where
function pods_admin_columns_search_where( $where, $query ) {
  global $wpdb;
  $post_type = $query->get('post_type');
  $search = $query->get('s');
    
  if ( is_search() && $query->is_main_query() && !empty ($post_type) ) {

    if (pods_api()->pod_exists($post_type)) {
      $mypod = pods($post_type);
      if ($mypod->pod_data['storage'] == 'table') {
        $table_name = $wpdb->prefix.'pods_'.$mypod->pod_data['name'];
        $fields_names = [];
        $fields_types_allowed = ['text', 'website', 'email', 'link', 'phone'];
        $fields_config = pods_config_get_all_fields( $mypod );

        foreach ($fields_config as $field_name => $field_config) {
          if (($field_config['show_admin_column'] == "1") && in_array($field_config['type'], $fields_types_allowed)) {
            $fields_names[] = $field_name;
          }
        }

        if (count($fields_names) > 0) {
          $where = preg_replace(
            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(".$wpdb->posts.".post_title LIKE $1)", $where );
          foreach ($fields_names as $field_name) {
            $where .= " OR (".$table_name.".".$field_name." LIKE '%".$search."%')";
          }
        }

      } else {
        $where = preg_replace(
          "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
          "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
      }
    }
  }

  return $where;
}

// SQL DISTINCT
function pods_admin_columns_search_distinct( $where ) {
  global $wpdb;

  if ( is_search() ) {
    return "DISTINCT";
  }
  
  return $where;
}

