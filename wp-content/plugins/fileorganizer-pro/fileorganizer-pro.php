<?php
/*
Plugin Name: FileOrganizer Pro
Plugin URI: https://wordpress.org/plugins/fileorganizer/
Description: FileOrganizer is a plugin that helps you to manage all files in your WordPress Site.
Version: 1.1.9
Author: Softaculous Team
Author URI: https://fileorganizer.net
Text Domain: fileorganizer
*/

// We need the ABSPATH
if(!defined('ABSPATH')) exit;

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

// The plugins has already been loaded
if(defined('FILEORGANIZER_PRO')){
	return;
}

define('FILEORGANIZER_PRO_VERSION', '1.1.9');
define('FILEORGANIZER_PRO_FILE', __FILE__);
define('FILEORGANIZER_PRO_DIR', plugin_dir_path(__FILE__));
define('FILEORGANIZER_PRO_BASE', plugin_basename(FILEORGANIZER_PRO_FILE));
define('FILEORGANIZER_API', 'https://api.fileorganizer.net/');

include_once(FILEORGANIZER_PRO_DIR.'/main/functions.php');

$_tmp_plugins = get_option('active_plugins');
$_fo_version = get_option('fileorganizer_version');

$fo_req_free_update = !empty($_fo_version) && version_compare($_fo_version, '1.0.9', '<');

if(
	!defined('SITEPAD') && (
	!(in_array('fileorganizer/fileorganizer.php', $_tmp_plugins) || 
	fileorganizer_pro_is_network_active('fileorganizer')) || 
	!file_exists(WP_PLUGIN_DIR . '/fileorganizer/fileorganizer.php') || 
	!empty($fo_req_free_update) )
){
	include_once(FILEORGANIZER_PRO_DIR .'/main/fileorganizer-init.php');
	return;
}

define('FILEORGANIZER_PRO', plugin_basename(__FILE__));

include_once(dirname(__FILE__).'/init.php');
