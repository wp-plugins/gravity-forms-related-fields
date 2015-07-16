<?php
/*
Plugin Name: Gravity Forms Related Fields Add-On
Description: Related Fields Add-on for Gravity Forms
Version: 1.0
Author: mikemanger
Author URI:
Text Domain: gravityformsrelatedfields
Domain Path: /languages
*/

/**
 * Replace select options with related fields on front end
 *
 * @param object $form Gravity Form form object
 *
 * @return object Filtered Gravity Form object.
 */
function gfrf_populate_dropdown( $form ) {

	$related_fields = gfrf_get_related_fields( $form['id'] );

	// Check it is enabled for this form
	if ( empty( $related_fields ) ) {
		return $form;
	}

	foreach ( $form['fields'] as &$field ) {

		if ( $field->type != 'select' && $field->type != 'multiselect' ) {
			continue;
		}

		$related_field = gfrf_get_related_field( $field->id, $related_fields );

		$is_active  = isset( $related_field['is_active'] ) ? $related_field['is_active'] : true;

		if ( ! $is_active ) {
			continue;
		}

		$target_form_id       = rgar( $related_field, 'target_form_id' );
		$target_form_field_id = rgar( $related_field, 'target_form_field_id' );

		$entries = gfrf_get_entries( $target_form_id, $target_form_field_id );
		$choices = array();

		foreach ( $entries as $entry ) {
			$choices[] = array( 'text' => $entry[ $target_form_field_id ], 'value' => $entry[ $target_form_field_id ] );
		}

		$field->choices = $choices;

	}

	return $form;
}
add_filter( 'gform_pre_render', 'gfrf_populate_dropdown' );
add_filter( 'gform_admin_pre_render', 'gfrf_populate_dropdown' );
add_filter( 'gform_pre_validation', 'gfrf_populate_dropdown' );
add_filter( 'gform_pre_submission_filter', 'gfrf_populate_dropdown' );

/**
 * Check if related field array contains a field mapping
 *
 * @param int $field_id Related field mapping to check
 * @param array $related_fields Array of related field mappings
 *
 * @return array Related field array if matched or empty string if not found.
 */
function gfrf_get_related_field( $field_id, $related_fields ) {
	foreach ( $related_fields as $related_field ) {
		if ( rgar( $related_field, 'target_field_id' ) == $field_id ) {
			return $related_field;
		}
	}
	return '';
}

function gfrf_get_entries( $form_id, $field_id ) {

	$search_criteria = array(
		'status' => 'active',
	);

	$sorting = array(
		'key' => $field_id,
		'direction' => 'ASC',
	);

	return GFAPI::get_entries( $form_id, $search_criteria, $sorting );
}

function gfrf_settings_menu( $setting_tabs, $form_id ) {
	$setting_tabs[] = array(
		'name' => 'gfrf-settings',
		'label' => __( 'Related Fields', 'gravityformsrelatedfields' ),
		'query' => array(
			'rfid' => null,
		),
	);
	return $setting_tabs;
}
add_filter( 'gform_form_settings_menu', 'gfrf_settings_menu', 10, 2 );

function gfrf_settings_page() {
	$form_id    = rgget( 'id' );
	$related_id = rgget( 'rfid' );
	if ( ! rgblank( $related_id ) ) {
		gfrf_edit_page( $form_id, $related_id );
	} else {
		gfrf_list_page( $form_id );
	}

}
add_action( 'gform_form_settings_page_gfrf-settings', 'gfrf_settings_page' );

function gfrf_get_related_fields( $form_id ) {
	return get_option( "gfrf_related_fields_{$form_id}", array() );
}

function gfrf_delete_related_field( $related_field_id, $form_id ) {
	if ( ! $form_id ) {
		return false;
	}

	$related_fields = gfrf_get_related_fields( $form_id );

	unset( $related_fields[ $related_field_id ] );

	return update_option( "gfrf_related_fields_{$form_id}", $related_fields );

}

function gfrf_list_page( $form_id ) {
	gfrf_maybe_process_list_action();

	GFFormSettings::page_header( __( 'Related Fields', 'gravityformsrelatedfields' ) );
	$add_new_url = add_query_arg( array( 'rfid' => 0 ) );
	?>

	<h3><span><?php _e( 'Related Fields', 'gravityformsrelatedfields' ) ?>
			<a id="add-new-related-field" class="add-new-h2" href="<?php echo esc_url( $add_new_url ) ?>"><?php _e( 'Add New', 'gravityformsrelatedfields' ) ?></a></span>
	</h3>

	<?php
		require_once 'class-related-fields-table.php';
		$form = GFFormsModel::get_form_meta( $form_id );
		$related_fields_table = new GFRF_Related_Fields_Table( $form );
		$related_fields_table->prepare_items();
	?>

	<form id="related_fields_list_form" method="post">
		<?php $related_fields_table->display(); ?>

		<input id="action_argument" name="action_argument" type="hidden" />
		<input id="action" name="action" type="hidden" />

		<?php wp_nonce_field( 'gfrf_list_action', 'gfrf_list_action' ) ?>
	</form>

	<script type="text/javascript">
		function gfrf_delete_confirmation( related_id ) {
			jQuery( '#action_argument' ).val( related_id );
			jQuery( '#action' ).val( 'delete' );
			jQuery( '#related_fields_list_form' )[0].submit();
		}

		function gfrf_toggle_active( img, related_field_id ) {
			var is_active = img.src.indexOf( 'active1.png' ) >= 0;

			if ( is_active ) {
				img.src = img.src.replace( 'active1.png', 'active0.png' );
				jQuery( img ).attr( 'title', '<?php _e( 'Inactive', 'gravityformsrelatedfields' ); ?>').attr( 'alt', '<?php _e( 'Inactive', 'gravityformsrelatedfields' ); ?>');
			} else {
				img.src = img.src.replace( 'active0.png', 'active1.png' );
				jQuery( img ).attr( 'title', '<?php _e( 'Active', 'gravityformsrelatedfields' ); ?>').attr( 'alt', '<?php _e( 'Active', 'gravityformsrelatedfields' ); ?>');
			}
			jQuery.post( ajaxurl, {
				form_id: <?php echo intval( $form_id ); ?>,
				related_field_id: related_field_id,
				is_active: is_active ? 0 : 1,
				action: 'gfrf_toggle_related_field_active'
			},
			function( response ) {

				if ( ! response ) {
					alert('<?php echo esc_js( __( 'Ajax error while updating the related field', 'gravityformsrelatedfields' ) ) ?>')
				}

			});
		}
	</script>
	<?php
	GFFormSettings::page_footer();
}

function gfrf_maybe_process_list_action() {
	if ( empty( $_POST ) || ! check_admin_referer( 'gfrf_list_action', 'gfrf_list_action' ) ) {
		return;
	}

	$action    = rgpost( 'action' );
	$object_id = rgpost( 'action_argument' );

	switch ( $action ) {
		case 'delete':
			$related_field_deleted = gfrf_delete_related_field( $object_id, rgget( 'id' ) );
			if ( $related_field_deleted ) {
				GFCommon::add_message( __( 'Related field connection deleted.', 'gravityformsrelatedfields' ) );
			} else {
				GFCommon::add_error_message( __( 'There was an issue deleting this related field connection.', 'gravityformsrelatedfields' ) );
			}
			break;
	}
}

function gfrf_edit_page( $form_id, $related_id ) {
	$form = apply_filters( "gform_admin_pre_render_{$form_id}", apply_filters( 'gform_admin_pre_render', GFFormsModel::get_form_meta( $form_id ) ) );

	$related_fields = gfrf_get_related_fields( $form_id );
	$related_field = gfrf_handle_edit_submission( rgar( $related_fields, $related_id ), $related_fields, $form_id );

	GFFormSettings::page_header( __( 'Related Fields', 'gravityformsrelatedfields' ) );

	$current_form_fields = array();

	foreach ( $form['fields'] as $field ) {

		if ( $field->type != 'select' && $field->type != 'multiselect' ) {
			continue;
		}

		$label = empty( $field->adminLabel ) ? $field->label : $field->adminLabel;

		$current_form_fields[ $field->id ] = $label;

	}

	$forms = RGFormsModel::get_forms();

	$target_field_id      = rgar( $related_field, 'target_field_id' );
	$target_form_id       = rgar( $related_field, 'target_form_id' );
	$target_form_field_id = rgar( $related_field, 'target_form_field_id' );
	?>
	<div id="related-field-editor">

		<form id="related-field_edit_form" method="post">

			<table class="form-table gforms_form_settings">
				<tr>
					<th><?php _e( 'Field', 'gravityformsrelatedfields' ); ?></th>
					<td>
						<?php if ( empty( $current_form_fields ) ) : ?>
							<?php _e( 'There are no mapable fields in this form', 'gravityformsrelatedfields' ); ?>
						<?php else: ?>
							<select name="target_field">
							<?php foreach ( $current_form_fields as $id => $label ) : ?>
								<option value="<?php echo $id; ?>" <?php selected( $target_field_id, $id ); ?>><?php echo $label; ?></option>
							<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'Target form', 'gravityformsrelatedfields' ); ?></th>
					<td>
						<select name="target_form" onchange="set_target_form_fields(this)">
							<option value=""><?php _e( 'Select a form', 'gravityformsrelatedfields' ); ?></option>
							<?php foreach ( $forms as $form ) : ?>
								<option value="<?php echo $form->id; ?>" <?php selected( $target_form_id, $form->id ); ?>><?php echo esc_html( $form->title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr id="target_form_field_row" <?php echo empty( $target_form_id ) ? 'style="display:none;"' : '' ?>>
					<th><?php _e( 'Target form field', 'gravityformsrelatedfields' ); ?> <?php gform_tooltip( 'form_redirect_to_webpage' ) ?></th>
					<td>
						<select id="target_form_field" name="target_form_field" style="max-width: 400px;">
							<?php echo gfrf_get_available_form_fields( $target_form_id, $target_form_field_id ); ?>
						</select>
					</td>
				</tr>
			</table>

			<input type="hidden" id="related_id" name="related_id" value="<?php echo $related_id; ?>" />
			<input type="hidden" id="form_id" name="form_id" value="<?php echo $form_id; ?>" />

			<p class="submit">
				<input type="submit" name="save" value="<?php _e( 'Save Related Field', 'gravityformsrelatedfields' ); ?>" class="button-primary">
			</p>

			<?php wp_nonce_field( 'gfrf_edit', 'gfrf_edit' ); ?>
		</form>

		<script type="text/javascript">
			function set_target_form_fields( elem ) {
				var form_id = elem.value;

				jQuery( '#target_form_field_row' ).hide();

				if ( ! form_id ) {
					return false;
				}

				jQuery.post( ajaxurl, {
					form_id: form_id,
					action: 'gfrf_get_available_form_fields'
				},
				function( response ) {

					if ( response ) {
						jQuery( 'select#target_form_field' ).html( response );
						jQuery( '#target_form_field_row' ).slideDown();
					} else {
					}

				});
			}
		</script>
	</div><!-- / related-field-editor -->
	<?php
	GFFormSettings::page_footer();
}

function gfrf_get_available_form_fields( $form_id, $selected_field_id = 0 ) {
	$form = GFFormsModel::get_form_meta( $form_id );

	$form_fields = array();

	$str = '<option value="">' . __( 'Select a field', 'gravityformsrelatedfields' ) . ' </option>';
	foreach ( $form['fields'] as $field ) {
		if ( $field->displayOnly ) {
			continue;
		}
		$label = empty( $field->adminLabel ) ? $field->label : $field->adminLabel;
		$str .= '<option value="' . $field->id . '"' . selected( $field->id, $selected_field_id, false ) . '>' . esc_html( $label ) . '</option>' . "\n";
	}

	return $str;
}

function gfrf_get_available_form_fields_callback() {
	// TODO Nonce validation

	$form_id = rgpost( 'form_id' );

	echo gfrf_get_available_form_fields( $form_id );
	die();
}
add_action( 'wp_ajax_gfrf_get_available_form_fields', 'gfrf_get_available_form_fields_callback' );

function gfrf_toggle_related_field_active_callback() {
	// TODO Nonce validation
	$form_id = rgpost( 'form_id' );
	$related_field_id = rgpost( 'related_field_id' );
	$is_active = rgpost( 'is_active' );

	$related_fields = gfrf_get_related_fields( $form_id );

	$related_field = rgar( $related_fields, $related_field_id );

	$related_field['is_active'] = $is_active;

	$related_fields[ $related_field['id'] ] = $related_field;

	update_option( "gfrf_related_fields_{$form_id}", $related_fields );

	echo true;
	die();
}
add_action( 'wp_ajax_gfrf_toggle_related_field_active', 'gfrf_toggle_related_field_active_callback' );


function gfrf_handle_edit_submission( $related_field, $related_fields, $form_id ) {
	if ( empty( $_POST ) || ! check_admin_referer( 'gfrf_edit', 'gfrf_edit' ) ) {
		return $related_field;
	}

	$is_new_related_field = ! $related_field;

	if ( $is_new_related_field ) {
		$related_field['id'] = uniqid();
	}

	// TODO: check that these are valid IDS
	$related_field['target_field_id']      = absint( rgpost( 'target_field' ) );
	$related_field['target_form_id']       = absint( rgpost( 'target_form' ) );
	$related_field['target_form_field_id'] = absint( rgpost( 'target_form_field' ) );

	$failed_validation = false;

	if ( empty( $related_field['target_field_id'] ) ) {
		$failed_validation = true;
		GFCommon::add_error_message( __( 'You must select a field.', 'gravityformsrelatedfields' ) );
	}

	if ( empty( $related_field['target_form_id'] ) ) {
		$failed_validation = true;
		GFCommon::add_error_message( __( 'You must select a Gravity Form.', 'gravityformsrelatedfields' ) );
	}

	if ( empty( $related_field['target_form_field_id'] ) ) {
		$failed_validation = true;
		GFCommon::add_error_message( __( 'You must select a Gravity Form field to relate to.', 'gravityformsrelatedfields' ) );
	}

	if ( $failed_validation ) {
		return $related_field;
	}

	// add current related field to related fields array
	$related_fields[ $related_field['id'] ] = $related_field;

	// save updated related fields array
	update_option( "gfrf_related_fields_{$form_id}", $related_fields );

	$url = remove_query_arg( array( 'rfid' ) );
	GFCommon::add_message( sprintf( __( 'Related field saved successfully. %sBack to related fields.%s', 'gravityformsrelatedfields' ), '<a href="' . esc_url( $url ) . '">', '</a>' ) );

	return $related_field;
}
