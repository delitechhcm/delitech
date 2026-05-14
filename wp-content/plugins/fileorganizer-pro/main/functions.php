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

function fileorganizer_pro_activation(){
	update_option('fileorganizer_pro_version', FILEORGANIZER_PRO_VERSION);
}

function fileorganizer_pro_is_network_active($plugin_name){
	$is_network_wide = false;
	
	// Handling network site
	if(!is_multisite()){
		return $is_network_wide;
	}
	
	$_tmp_plugins = get_site_option('active_sitewide_plugins');

	if(!empty($_tmp_plugins) && preg_grep('/.*\/'.$plugin_name.'\.php$/', array_keys($_tmp_plugins))){
		$is_network_wide = true;
	}
	
	return $is_network_wide;
}

function fileorganizer_pro_update_checker(){
	$current_version = get_option('fileorganizer_pro_version', '0.0');
	$version = (int) str_replace('.', '', $current_version);

	// No update required
	if($current_version == FILEORGANIZER_PRO_VERSION){
		return true;
	}
	
	$is_network_wide = fileorganizer_pro_is_network_active('fileorganizer-pro');
	
	if($is_network_wide){
		$free_ins = get_site_option('fileorganizer_free_installed');
	}else{
		$free_ins = get_option('fileorganizer_free_installed');
	}
	
	// If plugin runing reached here it means FileOrganizer free installed 
	if(empty($free_ins)){
		if($is_network_wide){
			update_site_option('fileorganizer_free_installed', time());
		}else{
			update_option('fileorganizer_free_installed', time());
		}
	}
	
	update_option('fileorganizer_version_pro_nag', time());
	update_option('fileorganizer_version_free_nag', time());
	update_option('fileorganizer_pro_version', FILEORGANIZER_PRO_VERSION);
}

// Load license data
function fileorganizer_pro_load_license($parent = 0){
	
	global $fileorganizer, $lic_resp;
	
	$license_field = 'fileorganizer_license';
	$license_api_url = FILEORGANIZER_API;
	
	// Save license
	if(!empty($parent) && is_string($parent) && strlen($parent) > 5){		
		$lic['license'] = $parent;
	
	// Load license of Soft Pro
	}elseif(!empty($parent)){
		$license_field = 'softaculous_pro_license';
		$lic = get_option('softaculous_pro_license', []);
	
	// My license
	}else{
		$lic = get_option($license_field, []);
	}
	
	// Loaded license is a Soft Pro
	if(!empty($lic['license']) && preg_match('/^softwp/is', $lic['license'])){
		$license_field = 'softaculous_pro_license';
		$license_api_url = 'https://a.softaculous.com/softwp/';
		$prods = apply_filters('softaculous_pro_products', []);
	}else{
		$prods = [];
	}

	if(empty($lic['last_update'])){
		$lic['last_update'] = time() - 86600;
	}
	
	// Update license details as well
	if(!empty($lic) && !empty($lic['license']) && (time() - @$lic['last_update']) >= 86400){
		
		$url = $license_api_url.'/license.php?license='.$lic['license'].'&prods='.implode(',', $prods).'&url='.rawurlencode(site_url());
		$resp = wp_remote_get($url);
		$lic_resp = $resp;

		//Did we get a response ?
		if(is_array($resp)){
			
			$tosave = json_decode($resp['body'], true);
			
			//Is it the license ?
			if(!empty($tosave['license'])){
				$tosave['last_update'] = time();
				update_option($license_field, $tosave);
				$lic = $tosave;
			}
		}
	}
	
	// If the license is Free or Expired check for Softaculous Pro license
	if(empty($lic) || empty($lic['active'])){
		
		if(function_exists('softaculous_pro_load_license')){
			$softaculous_license = softaculous_pro_load_license();
			if(!empty($softaculous_license['license']) && 
				(!empty($softaculous_license['active']) || empty($lic['license']))
			){
				$lic = $softaculous_license;
			}
		}elseif(empty($parent)){
			$soft_lic = get_option('softaculous_pro_license', []);
			
			if(!empty($soft_lic)){
				return fileorganizer_pro_load_license(1);
			}
		}
	}
	
	if(!empty($lic['license'])){
		$fileorganizer->license = $lic;
	}
	
}

add_filter('softaculous_pro_products', 'fileorganizer_softaculous_pro_products', 10, 1);
function fileorganizer_softaculous_pro_products($r = []){
	$r['fileorganizer'] = 'fileorganizer';
	return $r;
}

// Add our license key if ANY
function fileorganizer_pro_updater_filter_args($queryArgs){
	
	global $fileorganizer;
	
	if (!empty($fileorganizer->license['license'])){
		$queryArgs['license'] = $fileorganizer->license['license'];
	}
	
	$queryArgs['url'] = rawurlencode(site_url());
	
	return $queryArgs;
}

// Handle the Check for update link and ask to install license key
function fileorganizer_pro_updater_check_link($final_link){
	
	global $fileorganizer;
	
	if(empty($fileorganizer->license['license'])){
		return '<a href="'.admin_url('admin.php?page=fileorganizer-license').'">Install FileOrganizer Pro License Key</a>';
	}
	
	return $final_link;
}

// Prevent update of fileorganizer free
function fileorganizer_pro_get_free_version_num(){
		
	if(defined('FILEORGANIZER_VERSION')){
		return FILEORGANIZER_VERSION;
	}
	
	// In case of fileorganizer deactive
	return fileorganizer_pro_file_get_version_num('fileorganizer/fileorganizer.php');
}

// Prevent update of fileorganizer free
function fileorganizer_pro_file_get_version_num($plugin){
	
	// In case of fileorganizer deactive
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/'.$plugin);
	
	if(empty($plugin_data)){
		return false;
	}
	
	return $plugin_data['Version'];
	
}

// Prevent update of fileorganizer free
function fileorganizer_pro_disable_manual_update_for_plugin($transient){
	$plugin = 'fileorganizer/fileorganizer.php';
	
	// Is update available?
	if(!isset($transient->response) || !isset($transient->response[$plugin])){
		return $transient;
	}
	
	$free_version = fileorganizer_pro_get_free_version_num();
	$pro_version = FILEORGANIZER_PRO_VERSION;
	
	if(!empty($GLOBALS['fileorganizer_pro_is_upgraded'])){
		$pro_version = fileorganizer_pro_file_get_version_num('fileorganizer-pro/fileorganizer-pro.php');
	}
	
	// Update the fileorganizer version to the equivalent of Pro version
	if(!empty($pro_version) && version_compare($free_version, $pro_version, '<')){
		$transient->response[$plugin]->new_version = $pro_version;
		$transient->response[$plugin]->package = 'https://downloads.wordpress.org/plugin/fileorganizer.'.$pro_version.'.zip';
	}else{
		unset($transient->response[$plugin]);
	}

	return $transient;
}

// Auto update free version after update pro version
function fileorganizer_pro_update_free_after_pro($upgrader_object, $options){
	
	// Check if the action is an update for the plugins
	if($options['action'] != 'update' || $options['type'] != 'plugin'){
		return;
	}
		
	// Define the slugs for the free and pro plugins
	$free_slug = 'fileorganizer/fileorganizer.php'; 
	$pro_slug = 'fileorganizer-pro/fileorganizer-pro.php';

	// Check if the pro plugin is in the list of updated plugins
	if( 
		(isset($options['plugins']) && in_array($pro_slug, $options['plugins']) && !in_array($free_slug, $options['plugins'])) ||
		(isset($options['plugin']) && $pro_slug == $options['plugin'])
	){
	
		// Trigger the update for the free plugin
		$current_version = fileorganizer_pro_get_free_version_num();
		
		if(empty($current_version)){
			return;
		}
		
		$GLOBALS['fileorganizer_pro_is_upgraded'] = true;
		
		// This will set the 'update_plugins' transient again
		wp_update_plugins();

		// Check for updates for the free plugin
		$update_plugins = get_site_transient('update_plugins');
		
		if(empty($update_plugins) || !isset($update_plugins->response[$free_slug]) || version_compare($update_plugins->response[$free_slug]->new_version, $current_version, '<=')){
			return;
		}
		
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
		
		$skin = wp_doing_ajax()? new WP_Ajax_Upgrader_Skin() : null;
		
		$upgrader = new Plugin_Upgrader($skin);
		$upgraded = $upgrader->upgrade($free_slug);
		
		if(!is_wp_error($upgraded) && $upgraded){
			// Re-active free plugins
			if( file_exists( WP_PLUGIN_DIR . '/'.  $free_slug ) && is_plugin_inactive($free_slug) ){
				activate_plugin($free_slug); // TODO for network
			}
			
			// Re-active pro plugins
			if( file_exists( WP_PLUGIN_DIR . '/'.  $pro_slug ) && is_plugin_inactive($pro_slug) ){
				activate_plugin($pro_slug); // TODO for network
			}
		}
	}
}

function fileorganizer_pro_api_url($main_server = 0, $suffix = 'fileorganizer'){
	global $fileorganizer;
	
	$r = array(
		'https://s0.softaculous.com/a/softwp/',
		'https://s1.softaculous.com/a/softwp/',
		'https://s2.softaculous.com/a/softwp/',
		'https://s3.softaculous.com/a/softwp/',
		'https://s4.softaculous.com/a/softwp/',
		'https://s5.softaculous.com/a/softwp/',
		'https://s7.softaculous.com/a/softwp/',
		'https://s8.softaculous.com/a/softwp/'
	);
	
	$mirror = $r[array_rand($r)];
	
	// If the license is newly issued, we need to fetch from API only
	if(!empty($main_server) || empty($fileorganizer->license['last_edit']) || 
		(!empty($fileorganizer->license['last_edit']) && (time() - 3600) < $fileorganizer->license['last_edit'])
	){
		$mirror = FILEORGANIZER_API;
	}
	
	if(!empty($suffix)){
		$mirror = str_replace('/softwp', '/'.$suffix, $mirror);
	}

	return $mirror;
	
}

function fileorganizer_pro_plugins_expired($plugins){
	$plugins[] = 'FileOrganizer';

	return $plugins;
}

function fileorganizer_pro_expiry_notice(){
	global $fileorganizer;
	
	// The combined notice for all Softaculous plugin to show that the license has expired
	$dismissed_at = get_option('softaculous_expired_licenses', 0);
	$expired_plugins = apply_filters('softaculous_expired_licenses', []);
	$soft_wp_buy = 'https://www.softaculous.com/clients?ca=softwp_buy';
	
	if(
		!empty($expired_plugins) && 
		is_array($expired_plugins) && 
		!defined('SOFTACULOUS_EXPIRY_LICENSES') && 
		(empty($dismissed_at) || ($dismissed_at + WEEK_IN_SECONDS) < time())
	){

		define('SOFTACULOUS_EXPIRY_LICENSES', true); // To make sure other plugins don't return a Notice
		$soft_rebranding = get_option('softaculous_pro_rebranding', []);

		if(!empty($fileorganizer->license['has_plid'])){
			if(!empty($soft_rebranding['sn']) && $soft_rebranding['sn'] != 'Softaculous'){
				
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact %3$s to continue receiving uninterrupted updates and support for %4$s.', 'fileorganizer-pro'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html($soft_rebranding['sn']),
					esc_html(implode(', ', $expired_plugins))
				);

			}else{
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact your hosting provider to continue receiving uninterrupted updates and support for %3$s.', 'fileorganizer-pro'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html(implode(', ', $expired_plugins))
				);
			}
		}else{
			$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please %3$srenew%4$s it to continue receiving uninterrupted updates and support for %5$s.', 'fileorganizer-pro'),
				'<font style="color:red;"><b>',
				'</b></font>',
				'<a href="'.esc_url($soft_wp_buy.'&license='.$fileorganizer->license['license'].'&plan='.$fileorganizer->license['plan']).'" target="_blank">',
				'</a>',
				esc_html(implode(', ', $expired_plugins))
			);
		}
		
		echo '<div class="notice notice-error is-dismissible" id="fileorganizer-pro-expiry-notice">
				<p>'.$msg. '</p>
			</div>';

		wp_register_script('fileorganizer-pro-expiry-notice', '', array('jquery'), FILEORGANIZER_PRO_VERSION, true );
		wp_enqueue_script('fileorganizer-pro-expiry-notice');
		wp_add_inline_script('fileorganizer-pro-expiry-notice', '
		jQuery(document).ready(function(){
			jQuery("#fileorganizer-pro-expiry-notice").on("click", ".notice-dismiss", function(e){
				e.preventDefault();
				let target = jQuery(e.target);

				let jEle = target.closest("#fileorganizer-pro-expiry-notice");
				jEle.slideUp();

				jQuery.post("'.admin_url('admin-ajax.php').'", {
					security : "'.wp_create_nonce('fileorganizer_expiry_notice').'",
					action: "fileorganizer_pro_dismiss_expired_licenses",
				}, function(res){
					if(!res["success"]){
						alert(res["data"]);
					}
				}).fail(function(data){
					alert("There seems to be some issue dismissing this alert");
				});
			});
		})');
	}
}

function fileorganizer_pro_dismiss_expired_licenses(){
	check_admin_referer('fileorganizer_expiry_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'fileorganizer-pro'));
	}

	update_option('softaculous_expired_licenses', time());
	wp_send_json_success();
}