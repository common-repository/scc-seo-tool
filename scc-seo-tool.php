<?php
/**
 * @package scc-seo-tool
 * @version 1.0
 */
/*
Plugin Name: SCC Seo Tool
Plugin URI: http://wordpress.org/plugins/tool-auto-post/
Description: Nhận bài viết tự động từ sieucongcu.com
Author: sieucongcu.com
Version: 1.0
Author URI: https://sieucongcu.com
License: GPL v3
Text Domain: scc-seo-tool
*/
global $panel_link_folder;
$panel_link_folder = plugins_url('',__FILE__);
if ( ! defined( 'SCCST_BASENAME' ) ) {
    define( 'SCCST_BASENAME', plugin_basename(  __FILE__ ) );
}
if ( ! defined( 'SCCST_OPT_NAME' ) ) {
    define( 'SCCST_OPT_NAME', "tapostslug_sections" );
}
if ( ! defined( 'SCCST_ADMIN_MENU_SLUG' ) ) {
    define( 'SCCST_ADMIN_MENU_SLUG', "sccst-setting" );
}

require_once( dirname(__FILE__). '/functions.php');
require_once( dirname(__FILE__). '/admin/panel.php');
require_once( dirname(__FILE__). '/admin/admin.php');
require_once( dirname(__FILE__). '/admin/forms.php');


add_action( 'plugins_loaded', 'sccstslug_process_auto_post_content' );
?>