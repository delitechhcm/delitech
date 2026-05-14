<?php
/*
* fileorganizer
* https://fileorganizer.net
* (c) Softaculous Team
*/

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

if(defined('FILEORGANIZER_DIR')) {
	include_once(FILEORGANIZER_DIR.'/main/settings.php');
}

global $fileorganizer;

function fileorganizer_pro_license(){
	
	global $lic_resp;
	
	if(!wp_verify_nonce($_POST['fileorganizer_license_nonce'], 'fileorganizer_license')){
		fileorganizer_notify( __('Security Check Failed'), 'error');
		return;
	}

	$license = sanitize_key(fileorganizer_optpost('fileorganizer_license'));
	
	if(empty($license)){
		fileorganizer_notify(__('The license key was not submitted'), 'error');
		return;
	}
	
	fileorganizer_pro_load_license($license);
	
	if(!is_array($lic_resp)){
		fileorganizer_notify(__('The response was malformed<br>'.var_export($lic_resp, true)), 'error');
		return;
	}

	$json = json_decode($lic_resp['body'], true);

	if(empty($json['license'])){
		fileorganizer_notify(__('The license key is invalid'), 'error');
		return;
	}
	
	fileorganizer_notify(__('Successfully updated the license key'));
	
}
	
if(isset($_REQUEST['save_fileorganizer_license'])){
	fileorganizer_pro_license();
}
?>

<div class="wrap" >
	<?php fileorganizer_page_header('FileOrganizer License'); ?>
	<div class="fileorganizer-license-content">
		<div class="fileorganizer-tab-group fileorganizer-mr20">
			<h3><?php _e('System Information'); ?></h3>
			<table class="wp-list-table fixed striped users fileorganizer-license-table" cellspacing="1" border="0" width="100%" cellpadding="10" align="center">
				<tbody>
					<tr>				
						<th align="left" width="25%"><?php esc_html_e('FileOrganizer Version', 'fileorganizer'); ?></th>
						<td><?php
							echo FILEORGANIZER_PRO_VERSION.' (Pro Version)';
						?>
						</td>
					</tr>
					<tr>			
						<th align="left" valign="top"><?php esc_html_e('FileOrganizer License', 'fileorganizer'); ?></th>
						<td align="left">
							<form method="post" action="">
								<?php echo (defined('FILEORGANIZER_PRO') && empty($fileorganizer->license['license']) ? '<span style="color:red">Unlicensed</span> &nbsp; &nbsp;' : '')?>
								<input type="hidden" name="fileorganizer_license_nonce" value="<?php echo wp_create_nonce('fileorganizer_license');?>"/>
								<input type="text" name="fileorganizer_license" value="<?php echo (empty($fileorganizer->license['license']) ? '': $fileorganizer->license['license'])?>" size="30" placeholder="e.g. FILEO-11111-22222-33333-44444" style="width:300px;"> &nbsp; 
								<input name="save_fileorganizer_license" class="button button-primary dosmtp-sumbit-licence" value="Update License" type="submit">
							</form>
							<?php if(!empty($fileorganizer->license['license'])){
									
									$expires = $fileorganizer->license['expires'];
									$expires = substr($expires, 0, 4).'/'.substr($expires, 4, 2).'/'.substr($expires, 6);
									
									echo '<div style="margin-top:10px;">License Status : '.(empty($fileorganizer->license['status_txt']) ? 'N.A.' : wp_kses_post($fileorganizer->license['status_txt'])).' &nbsp; &nbsp; &nbsp; 
									'.($fileorganizer->license['expires'] <= date('Ymd') ? 'License Expires : <span style="color:var(--red)">'.esc_attr($expires).'</span>' : (empty($fileorganizer->license['has_plid']) ? __('License Expires : ', 'softaculous-pro').' '.esc_html($expires) : '')).'
									</div>';
									
							}?>
						</td>
					</tr>
					<tr>
						<th align="left">URL</th>
						<td><?php echo get_site_url(); ?></td>
					</tr>
					<tr>				
						<th align="left">Path</th>
						<td><?php echo ABSPATH; ?></td>
					</tr>
					<tr>
						<th align="left"><?php _e('Server\'s IP Address') ?></th>
						<td><?php echo esc_url($_SERVER['SERVER_ADDR']); ?></td>
					</tr>
					<tr>				
						<th align="left">.htaccess <?php _e('is writable') ?></th>
						<td><?php echo (is_writable(ABSPATH.'/.htaccess') ? '<span style="color:var(--fileorganizer-red)">Yes</span>' : '<span style="color:green">No</span>');?></td>
					</tr>		
				</tbody>
			</table>
		</div>

<?php fileorganizer_page_footer() ?>