<?php

/*
* FILEORGANIZER
* https://fileorganizer.net/
* (c) FileOrganizer Team
*/
global $fileorganizer;

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

function fileorganizer_pro_user_restriction_header(){
	wp_enqueue_style('forg-admin');

	$options = get_option('fileorganizer_options');
	$options = empty($options) || !is_array($options) ? array() : $options;

	// print_r($_POST);
	if(!empty($_POST['delete_user_restrictions']) || !empty($_POST['delete_role_restrictions'])){

		// Check nonce
		check_admin_referer('fileorganizer_settings');

		$option = 'delete_user_restrictions';
		$key = 'user_restrictions';

		if(isset($_POST['delete_role_restrictions'])){
			$key = 'user_roles_restrictions';
			$option = 'delete_role_restrictions';
		}
		
		// Find array index to be removed
		$req_id = (!empty($_POST[$option]) ? (int)fileorganizer_optpost($option) : '');

		if(empty($req_id)){
			return;
		}

		// load restriction array.
		$restrict_data = !empty($options[$key]) ? $options[$key] : array();
		
		if(empty($restrict_data) || !is_array($restrict_data) || !isset($restrict_data[$req_id - 1])){
			return;
		}
		
		// remove user role from array
		unset($restrict_data[$req_id - 1]);

		// reset array index
		$restrict_data = array_values($restrict_data);

		// Update roles
		$options[$key] = $restrict_data;
		update_option('fileorganizer_options', $options);
	}

	// Settings for User and Roles Restrictions  
	if((isset($_POST['save_roles_restr']) && !empty($_POST['user_role'])) || (isset($_POST['save_user_restr']) && !empty($_POST['user'])) ){

		// Check nonce
		check_admin_referer('fileorganizer_settings');

		$restr_key = 'user_role';
		$restr_option = 'user_roles_restrictions';
		
		if(isset($_POST['save_user_restr'])){
			$restr_key = 'user';
			$restr_option = 'user_restrictions';
		}

		$path = fileorganizer_optpost('private_dir');
		if(empty($options['disable_path_restriction'])){
			$verify = fileorganizer_validate_path($path);
			$path =  $verify ? $path : ABSPATH;
		}

		$roles_ = array(
			$restr_key => fileorganizer_optpost($restr_key),
			'restrict_operations' => fileorganizer_optpost('restrict_operations'),
			'private_dir' => fileorganizer_cleanpath($path),
			'restrict_dirs' => fileorganizer_cleanpath(fileorganizer_optpost('restrict_dirs')),
			'restrict_files' => fileorganizer_cleanpath(fileorganizer_optpost('restrict_files')),
			'disable_toolbar' => fileorganizer_optpost('disable_toolbar'),
			'disable_context_menu' => fileorganizer_optpost('disable_context_menu'),
		);

		$roles = !empty($options[$restr_option]) && is_array($options[$restr_option]) ? $options[$restr_option] : '';
		
		// Array already exists?
		if(!empty($roles) ){
			$options[$restr_option] = $roles;
		}
		
		// Update or add user role.
		if(!empty($_POST['rule_id']) && isset($options[$restr_option][ (int)$_POST['rule_id'] - 1])){
			$options[$restr_option][(int)fileorganizer_optpost('rule_id') - 1] = $roles_;
		}else{
			$options[$restr_option][] = $roles_;
		}
		
		if(update_option( 'fileorganizer_options', $options )){
			fileorganizer_notify(__('Settings saved successfully.'));
		}
	}
}

function fileorganizer_user_restriction_render(){
	
	// Load header
	fileorganizer_pro_user_restriction_header();
	
	$settings = get_option('fileorganizer_options', array());
	
	if(empty($settings) || !is_array($settings)){
		$settings = array();
	}
	
	$file_operations = ['mkdir', 'mkfile', 'rename', 'duplicate', 'paste', 'archive', 'extract', 'copy', 'cut', 'edit', 'rm', 'download', 'upload', 'search', 'empty']; 

?>
<div class="fileorganizer-restrictions-wrapper wrap">
	<h2 class="fileorganizer-notices"></h2>
	<table class="fileorganizer-settings-header wrap" cellpadding="2" cellspacing="1" width="100%" class="fixed" border="0">
		<tr>
			<td class="fileorganizer-td" valign="top">
				<span class="dashicons dashicons-shield"></span>
				<h2><?php _e('User Restrictions'); ?></h2>
			</td>
			<td align="right" width="440">
				<button id="fileorganizer-add-user-restriction" type="button">
					<i class="dashicons dashicons-plus-alt"></i>
					<span><?php _e('Add Restriction'); ?></span>
				</button>
			</td>
		</tr>
	</table>
	<div class="fileorganizer-restrictions-content">
		<form class="fileorganizer-settings" name="fileorganizer_settings" method="post" >
			<?php wp_nonce_field('fileorganizer_settings'); ?>			
			<!-- User restrictions settings start -->
			<table class="wp-list-table widefat striped fileorganizer-table">
				<tr>
					<th><?php _e('User') ?></th>
					<th><?php _e('Disabled Operations') ?></th>
					<th><?php _e('Private Directory') ?></th>
					<th><?php _e('Hidden Directories') ?></th>
					<th><?php _e('Locked Extension') ?></th>
					<th><?php _e('Disable Toolbar') ?></th>
					<th><?php _e('Disable Context Menu') ?></th>
					<th><?php _e('Actions') ?></th>
				</tr>
				<?php
				if(!empty($settings['user_restrictions']) && is_array($settings['user_restrictions'])){
					$user_options = $settings['user_restrictions'];   
					foreach($user_options as $key => $restriction){
						$operations_ = !empty($restriction['restrict_operations']) ? $restriction['restrict_operations'] :  array();
						$rm_pos = array_search('rm', $operations_);
						if(!empty($rm_pos)){
							$operations_[$rm_pos] = 'Remove';
						}
						$operations = !empty($operations_) && is_array($operations_) ? implode(', ',$operations_) : __('NA');
					
						$user_restrict = !empty($restriction['user']) ? $restriction ['user'] : __('NA.');
						
						$private_path = !empty($restriction['private_dir']) ? $restriction['private_dir'] : '';
						$hasInvalidPath = false;
						if(!empty($private_path) && empty($settings['disable_path_restriction']) && !fileorganizer_validate_path($private_path)){
							$hasInvalidPath = true;
						}

					echo '<tr>
						<td>
							<div class="fileorganizer-restrictions-wrap">
								<span class="dashicons dashicons-admin-users"></span>'. $user_restrict.'
							</div>
						</td>
						<td>
							<span>'.ucwords($operations).'</span>
						</td>
						<td>
							<span>'.( empty($restriction['private_dir']) ? __('NA') : $restriction['private_dir']).( $hasInvalidPath ? '&nbsp;<strong class="fileorganizer_invalid_path dashicons dashicons-info" title="'.__('Invalid Path Detected!').'"></strong>&nbsp;' : '').'</span>
						</td>
						<td>
							<span>'.(empty($restriction['restrict_dirs']) ? __('NA') : $restriction['restrict_dirs']).'</span>
						</td>
						<td>
							<span>'.(empty($restriction['restrict_files']) ? __('NA') : $restriction['restrict_files']).'</span>
						</td>
						<td>
							<span>'.(empty($restriction['disable_toolbar']) ? __('No') : __('Yes') ).'</span>
						</td>
						<td>
							<span>'.(empty($restriction['disable_context_menu']) ? __('No') : __('Yes')).'</span>
						</td>
						<td class="fileorganizer-table-actions">
							<button type="button" data-valid="'.($hasInvalidPath ? 'false' : 'true').'" data-path="'.fileorganizer_cleanpath($restriction['private_dir']).'" value="'.($key + 1).'" class="fileorganizer-edit edit-user-restriction">
								<i class="dashicons dashicons-edit-large"></i>
							</button>
							<button type="submit" name="delete_user_restrictions" value="'.($key + 1).'" class="fileorganizer-delete">
								<i class="dashicons dashicons-trash"></i>
							</button>
						</td>
					</tr>';
					}
				}else{
					echo '<tr class="fileorganizer-text-center">
						<td  colspan="9">User Restrictions not found!</td>
					</tr>';
				}
				?>
			</table>
			<!-- User restrictions settings end -->
		</form>
	</div>
</div>

<!-- User restrictions dialog start -->
<div class="fileorganizer-dialog" id="fileorganizer-user-dialog">
	<div class="fileorganizer-dialog-wrap">
		<div class="fileorganizer-dialog-container">
			<div class="fileorganizer-dialog-header">
				<div class="fileorganizer-dialog-header-content">
				<div class="fileorganizer-dialog-title"><div class="fileorganizer-status-icon"></div><span><?php _e('User Restriction') ?></span></div>
				<button type="button" class="fileorganizer-dialog-close"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
			</div>
			
			<div class="fileorganizer-dialog-content">
				<form method="post" class="form-user-form" name="form-user-form">
					<?php wp_nonce_field('fileorganizer_settings'); ?>
					<div class="fileorganizer-dialog-form">
						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-3">
								<label><?php _e('Select User'); ?>:</label>
							</div>
							<div class="fileorganizer-col fileorganizer-col-9">
								<div class="fileorganizer-dialog-desc">
									<select name="user" class="fileorganizer-select role-restriction" required>
										<option value=""><?php _e('Select User'); ?></option>
										<?php
											$users = get_users();
											foreach($users as $key => $user){
												echo '<option value="'.$user->data->user_login.'">'.$user->data->user_login.'</option>';
											}
										?>
									</select>
								</div>
								<p class="description">
									<?php _e( 'Select the User you want to set restrictions for.'); ?>
								</p>
							</div>
						</div>

						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-3">
								<label><?php _e('Disable Operations'); ?>:</label></span>
							</div>
							<div class="fileorganizer-col fileorganizer-col-9">
								<div class="fileorganizer-dialog-desc fileorganizer-chkbox-group">
								<?php 
									foreach($file_operations as $operation){
										echo '<div class="fileorganizer-chkbox-wrap">
											<input class="fileorganizer-dialog-user_role-opration" name="restrict_operations[]" value="'.$operation.'" type="checkbox" />
											<span class="description">'.__( ucwords(($operation == 'rm' ? 'remove' : $operation))).'</span>
										</div>';
									}
								?>
								</div>
								<p class="description">
									<?php  _e( 'Choose the operations that you want to disable for the User.'); ?>
								</p>
							</div>
						</div>

						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-3">
								<label><?php _e('Separate/Private Folder'); ?>:</label></span>
							</div>
							<div class="fileorganizer-col fileorganizer-col-9">
								<div class="fileorganizer-dialog-desc">
									<textarea class="regular-text always_active" name="private_dir"></textarea>
								</div>
								<p class="description">
									<?php echo 'Eg.&nbsp;',fileorganizer_cleanpath(ABSPATH); ?><br>
									<strong>Note:</strong> It will override "File Manager Path" settings.
									<?php
									if(empty($settings['disable_path_restriction'])){
										echo '<br><strong class="fileorganizer-path-error">'. __("File manager path restriction is enabled, access outside of your WordPress installation path is not permitted.").'</strong>';
									}
									?>
								</p>
							</div>
						</div>

						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-3">
								<label><?php _e('Hide Folder or File Paths'); ?>:</label></span>
							</div>
							<div class="fileorganizer-col fileorganizer-col-9">
								<div class="fileorganizer-dialog-desc">
									<textarea type="text" class="regular-text always_active" name="restrict_dirs"></textarea>
								</div>
								<p class="description"><?php
								echo !defined('SITEPAD') ? __( 'Eg. wp-content/themes|wp-content/plugins' ) : __( 'Eg. sitepad-data/themes|sitepad-data/plugins' );
								?></p>
							</div>
						</div>

						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-3">
								<label><?php _e('Locked File Extensions'); ?>:</label></span>
							</div>
							<div class="fileorganizer-col fileorganizer-col-9">
							<div class="fileorganizer-dialog-desc">
									<textarea type="text" class="regular-text always_active" name="restrict_files"></textarea>
								</div>
								<p class="description"><?php _e( 'Eg. .php|.png|.css etc<br><b>Note:</b>&nbsp;The extensions are case-sensitive and must be separated by vertical bar (|) without any spaces. Example: .jpg, JPG' ); ?></p>
							</div>
						</div>

						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-3">
								<label><?php _e('Disable Toolbar & Context Menu'); ?>:</label>
							</div>
							<div class="fileorganizer-col fileorganizer-col-9">
								<div class="fileorganizer-dialog-desc fileorganizer-chkbox-group">
									<div class="fileorganizer-chkbox-wrap">
										<input name="disable_toolbar" type="checkbox" value="yes">
										<span><?php _e('Disable Toolbar') ?></span>
									</div>
									<div class="fileorganizer-chkbox-wrap">
										<input  name="disable_context_menu" type="checkbox" value="yes">
										<span><?php _e('Disable Context Menu') ?></span>
									</div>
								</div>
							</div>
						</div>

						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-12 fileorganizer-text-right fileorganizer-borderless">
								<button type="submit" name="save_user_restr" class="button fileorganizer-button-primary"><?php _e('Save Changes'); ?></button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<!-- User restrictions dialog end -->

<?php

// Load footer
fileorganizer_pro_user_restriction_footer();

}

function fileorganizer_pro_user_restriction_footer(){
	
	$settings = get_option('fileorganizer_options', array());
	
	if(empty($settings) || !is_array($settings)){
		$settings = array();
	}
?>
	<?php if(!defined('SITEPAD')) : ?>
	<div style="width:45%;background:#FFF;padding:15px; margin:40px auto">
		<b><?php _e('Let your followers know that you use FileOrganizer to manage your wordpress files :'); ?></b>
		<form method="get" action="https://twitter.com/intent/tweet" id="tweet" onsubmit="return dotweet(this);">
			<textarea name="text" cols="45" row="3" style="resize:none;"><?php _e('I easily manage my #WordPress #files using @fileorganizer'); ?></textarea>
			&nbsp; &nbsp; <input type="submit" value="Tweet!" class="button button-primary" onsubmit="return false;" id="twitter-btn" style="margin-top:20px;">
		</form>	
	</div>
	<?php endif; ?>

	<script>

		var fileorganizer_usr_role_restr = fileorganizer_isJSON('<?php echo !empty($settings['user_roles_restrictions']) ? wp_json_encode($settings['user_roles_restrictions']) : ''; ?>');
		var fileorganizer_usr_restr = fileorganizer_isJSON('<?php echo !empty($settings['user_restrictions']) ? wp_json_encode($settings['user_restrictions']) : ''; ?>');

		jQuery(document).ready(function(){
			
			// Close dialog
			jQuery('.fileorganizer-dialog, .fileorganizer-dialog-close').on('click',function(e){
				
				if(e.currentTarget.classList[0] == 'fileorganizer-dialog-close' || e.target.classList[0] == 'fileorganizer-dialog'){
					jQuery('.fileorganizer-dialog').fadeOut();
					jQuery('body').css('overflow','auto');
				}
			});
			
			// Edit role
			jQuery('.edit-user_role-restriction, .edit-user-restriction').on('click',function(e){
				
				var jEle = jQuery(this);
				var dialogId = 'fileorganizer-user_role-dialog';
				var config = fileorganizer_usr_role_restr;
				var is_valid_path = jEle.attr('data-valid');
				
				if(jEle.hasClass('edit-user-restriction')){
					var dialogId = 'fileorganizer-user-dialog';
					var config = fileorganizer_usr_restr;
				}
				
				var id = jEle.val();
				var dialog = jQuery('#'+dialogId);
				
				id = id > 0 ? parseInt(id) - 1 : 0;

				if( Object.keys(config).length == 0 || config[id] == undefined){
					alert('Error Occured!');
					return;
				}

				var data = config[id];
				var operation = data.user_role;
				var save_name = 'save_roles_restr';
				var select = dialog.find('[name=user_role]');
				var form = jEle.hasClass('edit-user-restriction') ? 'form-user-form' : 'form-user_role-form';
				
				jQuery('.'+form).get(0).reset();
				
				if(jEle.hasClass('edit-user-restriction')){
					select = dialog.find('[name=user]');
					operation = data.user;
					save_name = 'save_user_restr';
				}
				
				if(operation == undefined || Object.keys(operation).length == 0){
					alert('Error Occured!');
					return;
				}

				var error_wrap = dialog.find('.fileorganizer-path-error');
				error_wrap.text('File manager path restriction is enabled, access outside of your WordPress installation path is not permitted.');

				if(is_valid_path == 'false'){
					var old_path = jEle.attr('data-path');
					error_wrap.html('<strong>Invalid Path Detected: Saving changes will replace your current path  <code>'+old_path+'</code> with <code><?php echo fileorganizer_cleanpath(ABSPATH); ?></code> due to enabled file manager root path restriction.');
				}
				
				select.find('[value="'+operation+'"]').prop('selected', 'true');

				if(Object.keys(data.restrict_operations).length > 0){
					for(var i=0; i < data.restrict_operations.length; i++){
						dialog.find('[value="'+data.restrict_operations[i]+'"]').prop('checked',true);
					}
				}

				if(Object.keys(data.private_dir).length > 0){
					dialog.find('[name="private_dir"]').val(data.private_dir);
				}

				if(Object.keys(data.restrict_dirs).length > 0){
					dialog.find('[name="restrict_dirs"]').val(data.restrict_dirs);
				}

				if(Object.keys(data.restrict_files).length > 0){
					dialog.find('[name="restrict_files"]').val(data.restrict_files);
				}
				
				if(Object.keys(data.disable_toolbar).length > 0 && data.disable_toolbar == 'yes'){
					dialog.find('[name="disable_toolbar"]').prop('checked', true);
				}

				if(Object.keys(data.disable_context_menu).length > 0 && data.disable_context_menu == 'yes'){
					dialog.find('[name="disable_context_menu"]').prop('checked', true);
				}

				dialog.find('[name=rule_id]').remove();

				var html = '<input type="hidden" name="rule_id" value="'+(id + 1)+'">';
				dialog.find('[name='+save_name+']').before(html);

				dialog.fadeIn();

			});
			
			// Add User role
			jQuery('.fileorganizer-delete').on('click', function(e){
				var ct = confirm('Do you want to remove the restriction?');
				if(!ct){
					e.preventDefault();
				}
			});

			jQuery('#fileorganizer-add-userrole-restriction, #fileorganizer-add-user-restriction').on('click',function(e){
				
				var jEle = jQuery(this);
				var dialogId = 'fileorganizer-user-dialog';
				var form = 'form-user-form';
				
				if(jEle.attr('id') == 'fileorganizer-add-userrole-restriction'){
					dialogId = 'fileorganizer-user_role-dialog';
					form = 'form-user_role-form';
				}
												
				var dialog = jQuery('#'+dialogId);
				
				var error_wrap = dialog.find('.fileorganizer-path-error');
				error_wrap.text('File manager path restriction is enabled, access outside of your WordPress installation path is not permitted.');
				
				// Reset form
				jQuery('.'+form).get(0).reset();

				dialog.find('[name="rule_id"]').remove();
				dialog.fadeIn();
			});

		});

		function fileorganizer_isJSON(str){
			try {
				var obj = JSON.parse(str);
				return obj;
			} catch (e) {
				return false;
			}
		}
	</script>

<?php
}
