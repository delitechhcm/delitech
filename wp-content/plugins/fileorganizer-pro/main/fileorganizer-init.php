<?php
/*
* fileorganizer
* https://fileorganizer.net
* (c) Softaculous Team
*/

// Are we being accessed directly ?
if(!defined('FILEORGANIZER_PRO_VERSION')) {
	exit('Hacking Attempt !');
}

if(!class_exists('FileOrganizer')){
	#[\AllowDynamicProperties]
	class FileOrganizer{
		public $options = array();
	}
}

// Prevent update of fileorganizer free
// This also work for auto update
add_filter('site_transient_update_plugins', 'fileorganizer_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'fileorganizer_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'fileorganizer_pro_update_free_after_pro', 10, 2);

add_action('plugins_loaded', 'fileorganizer_pro_load_plugin');
function fileorganizer_pro_load_plugin(){
	global $fileorganizer;
	
	if(empty($fileorganizer)){
		$fileorganizer = new FileOrganizer();
		
		$options = get_option('fileorganizer_options');
		$fileorganizer->options = empty($options) ? array() : $options;
	}
	
	fileorganizer_pro_update_checker();

	// Load license
	fileorganizer_pro_load_license();
	
	// Check for updates
	include_once(FILEORGANIZER_PRO_DIR.'/main/plugin-update-checker.php');
	$fileorganizer_updater = Fileorganizer_PucFactory::buildUpdateChecker(fileorganizer_pro_api_url().'/updates.php?version='.FILEORGANIZER_PRO_VERSION, FILEORGANIZER_PRO_FILE);
	
	// Add the license key to query arguments
	$fileorganizer_updater->addQueryArgFilter('fileorganizer_pro_updater_filter_args');
	
	// Show the text to install the license key
	add_filter('puc_manual_final_check_link-fileorganizer-pro', 'fileorganizer_pro_updater_check_link', 10, 1);
	
	// Nag informing the user to install the free version.
	if(current_user_can('activate_plugins')){
		add_action('admin_notices', 'fileorganizer_pro_free_version_nag', 9);
		add_action('admin_menu', 'fileorganizer_pro_add_menu', 9);
	}
	
	$is_network_wide = fileorganizer_pro_is_network_active('fileorganizer-pro');
	$_fo_version = get_option('fileorganizer_version');
	$req_free_update = !empty($_fo_version) && version_compare($_fo_version, '1.0.9', '<');
	
	if($is_network_wide){
		$free_installed = get_site_option('fileorganizer_free_installed');
	}else{
		$free_installed = get_option('fileorganizer_free_installed');
	}
	
	if(!empty($free_installed)){
		return;
	}
	
	// Include the necessary stuff
	include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	include_once(ABSPATH . 'wp-admin/includes/file.php');
	
	if(file_exists(WP_PLUGIN_DIR . '/fileorganizer/fileorganizer.php') && is_plugin_inactive('/fileorganizer/fileorganizer.php') && empty($req_free_update)) {
		
		if($is_network_wide){
			update_site_option('fileorganizer_free_installed', time());
		}else{
			update_option('fileorganizer_free_installed', time());
		}
		
		activate_plugin('/fileorganizer/fileorganizer.php', '', $is_network_wide);
		remove_action('admin_notices', 'fileorganizer_pro_free_version_nag', 9);
		remove_action('admin_menu', 'fileorganizer_pro_add_menu', 9);
		return;
	}
	
	// Includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
	include_once(ABSPATH . 'wp-admin/includes/misc.php');
	include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

	// Filter to prevent the activate text
	add_filter('install_plugin_complete_actions', 'fileorganizer_pro_prevent_activation_text', 10, 3);
	 
	$upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
	
	// Upgrade the plugin to the latest version of free already installed.
	if(!empty($req_free_update)){
		$installed = $upgrader->upgrade('fileorganizer/fileorganizer.php');
	}else{
		$installed = $upgrader->install('https://downloads.wordpress.org/plugin/fileorganizer.zip');
	}
	
	if(!is_wp_error($installed) && $installed){
		
		if($is_network_wide){
			update_site_option('fileorganizer_free_installed', time());
		}else{
			update_option('fileorganizer_free_installed', time());
		}
		
		activate_plugin('fileorganizer/fileorganizer.php', '', $is_network_wide);
		remove_action('admin_notices', 'fileorganizer_pro_free_version_nag', 9);
		remove_action('admin_menu', 'fileorganizer_pro_add_menu', 9);
	}
}

// Do not shows the activation text if 
function fileorganizer_pro_prevent_activation_text($install_actions, $api, $plugin_file){
	if($plugin_file == 'fileorganizer/fileorganizer.php'){
		return array();
	}

	return $install_actions;
}

function fileorganizer_pro_free_version_nag(){
	
	$fo_version = get_option('fileorganizer_version');
	
	$lower_version = __('You have not installed the free version of FileOrganizer. FileOrganizer Pro depends on the free version, so you must install it first in order to use FileOrganizer Pro.');
	
	if(!empty($fo_version) && version_compare($fo_version, '1.0.9', '<')){
		$lower_version = __('You are using an older version of the free version of FileOrganizer, please update FileOrganizer to work without any issues');
	}

	echo '<div class="notice notice-error">
		<p style="font-size:16px;">'.esc_html($lower_version).' <a href="'.admin_url('plugin-install.php?s=fileorganizer&tab=search').'" class="button button-primary">Install/Update Now</a></p>
	</div>';
}

function fileorganizer_pro_add_menu(){
	add_menu_page('FileOrganizer Settings', 'FileOrganizer', 'activate_plugins', 'fileorganizer', 'fileorganizer_pro_menu_page');
}

function fileorganizer_pro_menu_page(){
	echo '<div style="color: #333;padding: 50px;text-align: center;">
		<h1 style="font-size: 2em;margin-bottom: 10px;">FileOrganizer Free version is not installed / outdated!</h>
		<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">FileOrganizer Pro depends on the free version of FileOrganizer, so you need to install / update the free version first.</p>
		<a href="'.admin_url('plugin-install.php?s=fileorganizer&tab=search').'" style="text-decoration: none;font-size:16px;">Install/Update Now</a>
	</div>';
}