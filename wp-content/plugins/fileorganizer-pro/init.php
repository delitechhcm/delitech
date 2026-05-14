<?php
/*
* FILEORGANIZER
* https://fileorganizer.net/
* (c) FileOrganizer Team
*/

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

if(!class_exists('FileOrganizer')){
	#[\AllowDynamicProperties]
	class FileOrganizer{
		public $options = array();
	}
}

register_activation_hook(__FILE__, 'fileorganizer_pro_activation');
register_deactivation_hook(__FILE__, 'fileorganizer_pro_deactivate');

// Prevent update of fileorganizer free
// This also work for auto update
add_filter('site_transient_update_plugins', 'fileorganizer_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'fileorganizer_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'fileorganizer_pro_update_free_after_pro', 10, 2);

// Add action to load FileOrganizer
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
	
	if(wp_doing_ajax()){
		add_action('wp_ajax_fileorganizer_pro_version_notice', 'fileorganizer_pro_version_notice');
		add_action('wp_ajax_fileorganizer_pro_dismiss_expired_licenses', 'fileorganizer_pro_dismiss_expired_licenses');
	}
	
	if(!defined('SITEPAD')){
		if(current_user_can('activate_plugins')){
			add_action('admin_notices', 'fileorganizer_pro_free_version_nag');
			
			if(!empty($fileorganizer->license) && empty($fileorganizer->license['active']) && strpos($fileorganizer->license['license'], 'SOFTWP') !== FALSE){
				add_action('admin_notices', 'fileorganizer_pro_expiry_notice');
				add_filter('softaculous_expired_licenses', 'fileorganizer_pro_plugins_expired');
			}
		}
	
		// Check for updates
		include_once(FILEORGANIZER_PRO_DIR.'/main/plugin-update-checker.php');
		$fileorganizer_updater = Fileorganizer_PucFactory::buildUpdateChecker(fileorganizer_pro_api_url().'/updates.php?version='.FILEORGANIZER_PRO_VERSION, FILEORGANIZER_PRO_FILE);
		
		// Add the license key to query arguments
		$fileorganizer_updater->addQueryArgFilter('fileorganizer_pro_updater_filter_args');
		
		// Show the text to install the license key
		add_filter('puc_manual_final_check_link-fileorganizer-pro', 'fileorganizer_pro_updater_check_link', 10, 1);
	}

}

// Ajax function
function fileorganizer_pro_version_notice(){
	check_admin_referer('fileorganizer_version_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'fileorganizer'));
	}
	
	$type = '';
	if(!empty($_REQUEST['type'])){
		$type = sanitize_text_field(wp_unslash($_REQUEST['type']));
	}

	if(empty($type)){
		wp_send_json_error(__('Unknow version difference type', 'fileorganizer'));
	}
	
	update_option('fileorganizer_version_'. $type .'_nag', time() + WEEK_IN_SECONDS);
	wp_send_json_success();
}

function fileorganizer_pro_free_version_nag(){
	
	if(!defined('FILEORGANIZER_VERSION')){
		return;
	}

	$dismissed_free = (int) get_option('fileorganizer_version_free_nag');
	$dismissed_pro = (int) get_option('fileorganizer_version_pro_nag');

	// Checking if time has passed since the dismiss.
	if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
		return;
	}

	$showing_error = false;
	if(version_compare(FILEORGANIZER_VERSION, FILEORGANIZER_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="fileorganizer-pro-version-notice" onclick="fileorganizer_pro_dismiss_notice(event)" data-type="pro">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of FileOrganizer Pro. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'fileorganizer').'</p>
	</div>';
	}elseif(version_compare(FILEORGANIZER_VERSION, FILEORGANIZER_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="fileorganizer-pro-version-notice" onclick="fileorganizer_pro_dismiss_notice(event)" data-type="free">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of FileOrganizer. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'fileorganizer').'</p>
	</div>';
	}
	
	if(!empty($showing_error)){
		wp_register_script('fileorganizer-pro-version-notice', '', array('jquery'), FILEORGANIZER_PRO_VERSION, true );
		wp_enqueue_script('fileorganizer-pro-version-notice');
		wp_add_inline_script('fileorganizer-pro-version-notice', '
	function fileorganizer_pro_dismiss_notice(e){
		e.preventDefault();
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		let jEle = target.closest("#fileorganizer-pro-version-notice"),
		type = jEle.data("type");

		jEle.slideUp();
		
		jQuery.post("'.admin_url('admin-ajax.php').'", {
			security : "'.wp_create_nonce('fileorganizer_version_notice').'",
			action: "fileorganizer_pro_version_notice",
			type: type
		}, function(res){
			if(!res["success"]){
				alert(res["data"]);
			}
		}).fail(function(data){
			alert("There seems to be some issue dismissing this alert");
		});
	}');
	}
}

// Add hook for capabilites
add_filter('fileorganizer_get_capability', 'fileorganizer_pro_get_capability');
function fileorganizer_pro_get_capability($capability){
	global $fileorganizer;
	
	if(empty($fileorganizer->options['user_roles']) || !is_array($fileorganizer->options['user_roles'])){
		return $capability;
	}
	
	$current_user = wp_get_current_user();
	$roles = $current_user->roles;
	
	$is_allowed = array_intersect($roles, $fileorganizer->options['user_roles']);
	
	if(count($is_allowed) > 0){
		$capability = 'read';
	}
    
	return $capability;
}

add_filter('fileorganizer_manager_config', 'fileorganizer_pro_manager_config', 10 , 2);
function fileorganizer_pro_manager_config($config){	

	global $fileorganizer;
	
	$data = [];
	
	$current_user = wp_get_current_user();
	
	// Do restrict user?
	if(!empty($fileorganizer->options['user_restrictions']) && is_array($fileorganizer->options['user_restrictions'])){
		
		foreach($fileorganizer->options['user_restrictions'] as $restrictions){
			if(!empty($restrictions['user']) && $restrictions['user'] == $current_user->user_login){
				$data = $restrictions;
				break;
			}
				
		}
		
	}
	
	// Do restrict user roles?
	if(!empty($fileorganizer->options['user_roles_restrictions']) && is_array($fileorganizer->options['user_roles_restrictions']) && empty($data)){

		foreach($current_user->roles as $role){

			foreach($fileorganizer->options['user_roles_restrictions'] as $restrictions){
				if(!empty($restrictions['user_role']) && $restrictions['user_role'] == $role){
					$data = $restrictions;
					break 2;
				}
				
			}
		}
	}

	$restrictions = [];

	// Hide directories?
	if(!empty($data['restrict_dirs'])) {
		
		$dirs = explode('|',$data['restrict_dirs']);
		
		foreach($dirs as $dir){
			
			if(empty($dir)){
				continue;
			}
			
			$restrictions[] = array(
				'pattern' => '!^\/'.$dir.'$!',
				'read' => false,
				'write' => false,
				'hidden' => true,
				'locked' => false
			);
		}
		
	}

	// Hide files?
	if(!empty($data['restrict_files'])) {

		$files = explode('|',$data['restrict_files']);

		foreach($files as $file){
			
			if(empty($file)){
				continue;
			}
			
			$restrictions[] = array(
				'pattern' => '/'.$file.'$/',
				'read' => false,
				'write' => false,
				'hidden' => false,
				'locked' => true
			);
		}
	}
	
	// Is set private dir?
	if(!empty($data['private_dir'])){
		$path = $data['private_dir'];
	
		if(empty($fileorganizer->options['disable_path_restriction'])){
			$path = fileorganizer_validate_path($path) ? $path : ABSPATH;
		}

		$config[0]['path'] = $path;
	}

	// Disable operations
	$disable_commands = array(
		'help',
		'preference',
		'hide'
	);
	
	$config[0]['disabled'] = $disable_commands;

	// restrict commands
	if(!empty($data['restrict_operations']) && is_array($data['restrict_operations'])){
		$config[0]['disabled'] = array_merge($disable_commands, $data['restrict_operations']);
	}

	// Set max upload size
	if(!empty($fileorganizer->options['max_upload_size'])){
		$config[0]['uploadMaxSize'] = $fileorganizer->options['max_upload_size'].'M';
	}
	
	// Set restrictions
	$config[0]['attributes'] = array_merge($config[0]['attributes'], $restrictions);
	
	// Trash enabled?
	if(isset($config[1])){
		$config[1]['attributes'] = $config[0]['attributes'];
		$config[1]['disabled'] = $config[0]['disabled'];
	}

	return $config;
}

add_filter('fileorganizer_elfinder_script', 'fileorganizer_pro_elfinder_script');
function fileorganizer_pro_elfinder_script($config){
	global $fileorganizer;

	$data = array();
	
	$current_user = wp_get_current_user();
	
	// Do restrict user?
	if(!empty($fileorganizer->options['user_restrictions']) && is_array($fileorganizer->options['user_restrictions'])){
		
		foreach($fileorganizer->options['user_restrictions'] as $restrictions){
			if(!empty($restrictions['user']) && $restrictions['user'] == $current_user->user_login){
				$data = $restrictions;
				break;
			}
				
		}
		
	}
	
	// Do restrict user roles?
	if(!empty($fileorganizer->options['user_roles_restrictions']) && is_array($fileorganizer->options['user_roles_restrictions']) && empty($data)){

		foreach($current_user->roles as $role){

			foreach($fileorganizer->options['user_roles_restrictions'] as $restrictions){
				if(!empty($restrictions['user_role']) && $restrictions['user_role'] == $role){
					$data = $restrictions;
					break 2;
				}
				
			}
		}
	}
	
	// To remove preference form contextmenu
	$context_menu_content = 'cwd: ["undo", "redo", "|", "back", "up", "reload", "|", "upload", "mkdir", "mkfile", "paste", "|", "empty", "hide", "|", "view", "sort", "selectall", "colwidth", "|", "places", "info", "chmod", "netunmount", "|", "fullscreen"]';
	
	if(!empty($data['disable_context_menu'])){
		$context_menu_content = 'files: [], navbar: [], cwd: []';
	}

	$config = '
	uiOptions : {
		toolbar: '.(!empty($data['disable_toolbar']) && $data['disable_toolbar'] == 'yes' ? '[]' :'{}').',
		toolbarExtra : {
			autoHideUA: [],
			displayTextLabel: false,
			preferenceInContextmenu: false,
		},
	},
	contextmenu: {
		'.$context_menu_content.'
	},';

	return $config;
}

function fileorganizer_pro_deactivate(){
	delete_option('fileorganizer_pro_version');
	delete_option('fileorganizer_free_installed');
	delete_option('fileorganizer_version_pro_nag');
	delete_option('fileorganizer_version_free_nag');
}