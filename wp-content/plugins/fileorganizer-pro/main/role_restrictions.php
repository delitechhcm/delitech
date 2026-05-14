
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

include_once FILEORGANIZER_PRO_DIR .'/main/user_restrictions.php';

// Load header
fileorganizer_pro_user_restriction_header();

$settings = get_option('fileorganizer_options', array());
$file_operations = ['mkdir', 'mkfile', 'rename', 'duplicate', 'paste', 'archive', 'extract', 'copy', 'cut', 'edit', 'rm', 'download', 'upload', 'search', 'empty']; 

?>
<div class="fileorganizer-restrictions-wrapper wrap">
	<h2 class="fileorganizer-notices"></h2>
	<table class="fileorganizer-settings-header wrap" cellpadding="2" cellspacing="1" width="100%" class="fixed" border="0">
		<tr>
			<td class="fileorganizer-td" valign="top">
				<span class="dashicons dashicons-shield"></span>
				<h2><?php _e('User Role Restrictions'); ?></h2>
			</td>
			<td align="right" width="440">
				<button id="fileorganizer-add-userrole-restriction" type="button"><i class="dashicons dashicons-plus-alt"></i><span><?php _e('Add Restriction') ?></span></button>
			</td>
		</tr>
	</table>

	<div class="fileorganizer-restrictions-content">
		<form class="fileorganizer-settings" name="fileorganizer_settings" method="post" >
			<?php wp_nonce_field('fileorganizer_settings'); ?>	
			<!-- User role restrictions settings start -->
			
			<table class="wp-list-table widefat striped fileorganizer-table">
				<tr>
					<th><?php _e('User Role') ?></th>
					<th><?php _e('Disabled Operations') ?></th>
					<th><?php _e('Private Directory') ?></th>
					<th><?php _e('Hidden Directories') ?></th>
					<th><?php _e('Locked Extension') ?></th>
					<th><?php _e('Disable Toolbar') ?></th>
					<th><?php _e('Disable Context Menu') ?></th>
					<th><?php _e('Actions') ?></th>
				</tr>
				<?php
				if(!empty($settings['user_roles_restrictions']) && is_array($settings['user_roles_restrictions'])){
					$user_role_options = $settings['user_roles_restrictions'];   
					foreach($user_role_options as $key => $restriction){
						
						$operations_ = !empty($restriction['restrict_operations']) ? $restriction['restrict_operations'] : array();
						$rm_pos = array_search('rm', $operations_);
						if(!empty($rm_pos)){
							$operations_[$rm_pos] = 'Remove';
						}
						$operations = !empty($operations_) && is_array($operations_) ? implode(', ',$operations_) : __('NA');

						$icons = ['administrator'=>'dashicons-shield',
							'editor'=>'dashicons-edit-page',
							'author'=>'dashicons-edit-large',
							'contributor'=>'dashicons-welcome-learn-more',
							'customer'=>'dashicons-admin-users',
							'subscriber'=>'dashicons-rss',
							'shop_manager'=>'dashicons-store'
						];
						
						$usr_role = !empty($restriction['user_role']) ? $restriction ['user_role'] : __('NA.');
						$private_path = !empty($restriction['private_dir']) ? $restriction['private_dir'] : '';
						$hasInvalidPath = false;
						if(!empty($private_path) && empty($settings['disable_path_restriction']) && !fileorganizer_validate_path($private_path)){
							$hasInvalidPath = true;
						}

						echo '<tr>
						<td>
							<div class="fileorganizer-restrictions-wrap"><span class="dashicons '.$icons[ $usr_role ].'"></span>'.ucwords($usr_role).'</div>
						</td>
						<td>
							<span>'.ucwords($operations).'</span>
						</td>
						<td>
							<span>'.( empty($restriction['private_dir']) ? __('NA') : $restriction['private_dir']).( $hasInvalidPath ? '&nbsp;<strong class="fileorganizer_invalid_path dashicons dashicons-info" title="'.__('Invalid Path Detected!').'"></strong>' : '').'</span>
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
							<button type="button" data-valid="'.($hasInvalidPath ? 'false' : 'true').'" data-path="'.fileorganizer_cleanpath($restriction['private_dir']).'" value="'.($key + 1).'" class="fileorganizer-edit edit-user_role-restriction">
								<i class="dashicons dashicons-edit-large"></i>
							</button>
							<button type="submit" name="delete_role_restrictions" value="'.($key + 1).'" class="fileorganizer-delete">
								<i class="dashicons dashicons-trash"></i>
							</button>
						</td>
					</tr>';
					}
					
				}else{
					echo '<tr class="fileorganizer-text-center">
						<td  colspan="9">User Role Restrictions not found!</td>
					</tr>';
				}
			?>
			</table>
			<!-- User role restrictions settings end -->
		</form>
	</div>
</div>

 <!-- User role restrictions dialog box start -->
<div class="fileorganizer-dialog" id="fileorganizer-user_role-dialog">
	<div class="fileorganizer-dialog-wrap">
		<div class="fileorganizer-dialog-container">
			<div class="fileorganizer-dialog-header">
				<div class="fileorganizer-dialog-header-content">
				<div class="fileorganizer-dialog-title"><div class="fileorganizer-status-icon"></div><span><?php _e('User Role Restriction') ?></span></div>
				<button type="button" class="fileorganizer-dialog-close"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
			</div>
			
			<div class="fileorganizer-dialog-content">
				<form method="post" class="form-user_role-form" name="form-user_role-form">
					<?php wp_nonce_field('fileorganizer_settings'); ?>
					<div class="fileorganizer-dialog-form">
						<div class="fileorganizer-row">
							<div class="fileorganizer-col fileorganizer-col-3">
								<label><?php _e('Select User Role'); ?>:</label>
							</div>
							<div class="fileorganizer-col fileorganizer-col-9">
								<div class="fileorganizer-dialog-desc">
									<select name="user_role" class="fileorganizer-select role-restriction" required>
										<option value=""><?php _e('Select User Role'); ?></option>
										<option value="administrator"><?php _e('Administrator'); ?></option>
										<option value="editor"><?php _e('Editor'); ?></option>
										<option value="author"><?php _e('Author'); ?></option>
										<option value="contributor"><?php _e('Contributor'); ?></option>
										<option value="subscriber"><?php _e('Subscriber'); ?></option>
										<option value="customer"><?php _e('Customer'); ?></option>
										<option value="shop_manager"><?php _e('Shop manager'); ?></option>
									</select>
								</div>
								<p class="description">
									<?php _e( 'Select the User Role you want to set restrictions for.'); ?>
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
										echo '<div class="fileorganizer-chkbox-wrap"><input class="fileorganizer-dialog-user_role-opration" name="restrict_operations[]" value="'.$operation.'" type="checkbox" />
										<span class="description">'.__( ucwords(($operation == 'rm' ? 'remove' : $operation)) ).'</span></div>';
									}
								?>
								</div>
								<p class="description">
									<?php  _e( 'Choose the operations that you want to disable for the User Role.'); ?>
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
								<button type="submit" name="save_roles_restr" class="button fileorganizer-button-primary"><?php _e('Save Changes'); ?></button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<!-- User role restrictions dialog box end -->

<?php

// Load Footer
fileorganizer_pro_user_restriction_footer();