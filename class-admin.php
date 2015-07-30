<?php
/**
 * GFRF_Admin class. This class adds a settings page for managing related field
 * connections for all Gravity Form settings pages.
 *
 * @since 1.0.0
 */
class GFRF_Admin {

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	protected static $instance = null;

	public function __construct() {
		add_filter( 'gform_form_settings_menu', array( $this, 'settings_menu' ), 10, 2 );
		add_action( 'gform_form_settings_page_gfrf-settings', array( $this, 'settings_page' ) );
		add_filter( 'gform_tooltips', array( $this, 'add_tooltips' ) );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since  1.0.0
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Add related fields settings page to Gravity Forms form settings menu.
	 *
	 * @param array $setting_tabs Current list of tabs.
	 * @param int $form_id Gravity Form ID.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of filtered tabs.
	 */
	function settings_menu( $setting_tabs, $form_id ) {
		$setting_tabs[] = array(
			'name' => 'gfrf-settings',
			'label' => __( 'Related Fields', 'gravity-forms-related-fields' ),
			'query' => array(
				'rfid' => null,
			),
		);
		return $setting_tabs;
	}

	/**
	 * Initialise the Gravity Form settings page.
	 *
	 * @since 1.0.0
	 */
	function settings_page() {
		$form_id    = rgget( 'id' );
		$related_id = rgget( 'rfid' );
		if ( ! rgblank( $related_id ) ) {
			self::edit_page( $form_id, $related_id );
		} else {
			self::list_page( $form_id );
		}
	}

	/**
	 * Outputs overview list page displaying all related field connections for a form.
	 *
	 * @param int $form_id Form ID that connections belong to.
	 *
	 * @since 1.0.0
	 */
	function list_page( $form_id ) {
		self::maybe_process_list_action();

		GFFormSettings::page_header( __( 'Related Fields', 'gravity-forms-related-fields' ) );
		$add_new_url = add_query_arg( array( 'rfid' => 0 ) );
		?>

		<h3><span><?php _e( 'Related Field Connections', 'gravity-forms-related-fields' ) ?>
				<a id="add-new-related-field" class="add-new-h2" href="<?php echo esc_url( $add_new_url ) ?>"><?php _e( 'Add New', 'gravity-forms-related-fields' ) ?></a></span>
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
					jQuery( img ).attr( 'title', '<?php _e( 'Inactive', 'gravity-forms-related-fields' ); ?>').attr( 'alt', '<?php _e( 'Inactive', 'gravity-forms-related-fields' ); ?>');
				} else {
					img.src = img.src.replace( 'active0.png', 'active1.png' );
					jQuery( img ).attr( 'title', '<?php _e( 'Active', 'gravity-forms-related-fields' ); ?>').attr( 'alt', '<?php _e( 'Active', 'gravity-forms-related-fields' ); ?>');
				}
				jQuery.post( ajaxurl, {
					security: '<?php echo wp_create_nonce( 'gfrf_toggle_related_field_active' ); ?>',
					form_id: <?php echo intval( $form_id ); ?>,
					related_field_id: related_field_id,
					is_active: is_active ? 0 : 1,
					action: 'gfrf_toggle_related_field_active'
				},
				function( response ) {

					if ( ! response ) {
						alert('<?php echo esc_js( __( 'Ajax error while updating the related field', 'gravity-forms-related-fields' ) ) ?>')
					}

				});
			}
		</script>
		<?php
		GFFormSettings::page_footer();
	}

	/**
	 * Output page for editing and adding new related field connections.
	 *
	 * @param int $form_id Form ID that connection belongs to.
	 * @param int $related_id Current related field being edited. 0 if adding a new page.
	 *
	 * @since 1.0.0
	 */
	function edit_page( $form_id, $related_id ) {
		$form = apply_filters( "gform_admin_pre_render_{$form_id}", apply_filters( 'gform_admin_pre_render', GFFormsModel::get_form_meta( $form_id ) ) );

		$related_fields = gfrf_get_related_fields( $form_id );
		$related_field = self::handle_edit_submission( rgar( $related_fields, $related_id ), $related_fields, $form_id );

		GFFormSettings::page_header( __( 'Related Fields', 'gravity-forms-related-fields' ) );

		$current_form_fields = array();

		foreach ( $form['fields'] as $field ) {

			if ( ! gfrf_is_valid_field_type( $field['type'] ) ) {
				continue;
			}

			$label = empty( $field['adminLabel'] ) ? $field['label'] : $field['adminLabel'];

			$current_form_fields[ $field['id'] ] = $label;

		}

		$forms = RGFormsModel::get_forms();

		$target_field_id      = rgar( $related_field, 'target_field_id' );
		$source_form_id       = rgar( $related_field, 'source_form_id' );
		$source_form_field_id = rgar( $related_field, 'source_form_field_id' );
		?>
		<div id="related-field-editor">

			<form id="related-field_edit_form" method="post">

				<table class="form-table gforms_form_settings">
					<tr>
						<th><?php _e( 'Field to populate', 'gravity-forms-related-fields' ); ?></th>
						<td>
							<?php if ( empty( $current_form_fields ) ) : ?>
								<?php _e( 'There are no mapable fields in this form', 'gravity-forms-related-fields' ); ?>
							<?php else: ?>
								<select name="target_field">
									<option value=""><?php _e( 'Select a field', 'gravity-forms-related-fields' ); ?></option>
									<?php foreach ( $current_form_fields as $id => $label ) : ?>
										<option value="<?php echo $id; ?>" <?php selected( $target_field_id, $id ); ?>><?php echo $label; ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Source form', 'gravity-forms-related-fields' ); ?> <?php gform_tooltip( 'gfrf_source_form' ) ?></th>
						<td>
							<select name="source_form" onchange="set_source_form_fields(this)">
								<option value=""><?php _e( 'Select a form', 'gravity-forms-related-fields' ); ?></option>
								<?php foreach ( $forms as $form ) : ?>
									<option value="<?php echo $form->id; ?>" <?php selected( $source_form_id, $form->id ); ?>><?php echo esc_html( $form->title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="source_form_field_row" <?php echo empty( $source_form_id ) ? 'style="display:none;"' : '' ?>>
						<th><?php _e( 'Source form field', 'gravity-forms-related-fields' ); ?> <?php gform_tooltip( 'gfrf_source_form_field' ) ?></th>
						<td>
							<select id="source_form_field" name="source_form_field" style="max-width: 400px;">
								<?php echo gfrf_get_available_form_fields( $source_form_id, $source_form_field_id ); ?>
							</select>
						</td>
					</tr>
				</table>

				<input type="hidden" id="related_id" name="related_id" value="<?php echo $related_id; ?>" />
				<input type="hidden" id="form_id" name="form_id" value="<?php echo $form_id; ?>" />

				<p class="submit">
					<input type="submit" name="save" value="<?php _e( 'Save Related Field', 'gravity-forms-related-fields' ); ?>" class="button-primary">
				</p>

				<?php wp_nonce_field( 'gfrf_edit', 'gfrf_edit' ); ?>
			</form>

			<script type="text/javascript">
				function set_source_form_fields( elem ) {
					var form_id = elem.value;

					jQuery( '#source_form_field_row' ).hide();

					if ( ! form_id ) {
						return false;
					}

					jQuery.post( ajaxurl, {
						security: '<?php echo wp_create_nonce( 'gfrf_get_available_form_fields' ); ?>',
						form_id: form_id,
						action: 'gfrf_get_available_form_fields'
					},
					function( response ) {

						if ( response ) {
							jQuery( 'select#source_form_field' ).html( response );
							jQuery( '#source_form_field_row' ).slideDown();
						} else {
						}

					});
				}
			</script>
		</div><!-- / related-field-editor -->
		<?php
		GFFormSettings::page_footer();
	}

	/**
	 * Proccess related field list actions. Currently this only supports deleting fields.
	 *
	 * @since 1.0.0
	 */
	function maybe_process_list_action() {
		if ( empty( $_POST ) || ! check_admin_referer( 'gfrf_list_action', 'gfrf_list_action' ) ) {
			return;
		}

		$action    = rgpost( 'action' );
		$object_id = rgpost( 'action_argument' );

		switch ( $action ) {
			case 'delete':
				$related_field_deleted = gfrf_delete_related_field( $object_id, rgget( 'id' ) );
				if ( $related_field_deleted ) {
					GFCommon::add_message( __( 'Related field connection deleted.', 'gravity-forms-related-fields' ) );
				} else {
					GFCommon::add_error_message( __( 'There was an issue deleting this related field connection.', 'gravity-forms-related-fields' ) );
				}
				break;
		}
	}

	/**
	 * Save and validate a related field.
	 *
	 * @param array $related_field Field to save.
	 * @param array $related_fields Existing array of related fields beloning to form.
	 * @param int $form_id Form ID conection belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return array $related_field
	 */
	function handle_edit_submission( $related_field, $related_fields, $form_id ) {
		if ( empty( $_POST ) || ! check_admin_referer( 'gfrf_edit', 'gfrf_edit' ) ) {
			return $related_field;
		}

		$is_new_related_field = ! $related_field;

		if ( $is_new_related_field ) {
			$related_field['id'] = uniqid();
		}

		// TODO: check that these are valid IDS
		$related_field['target_field_id']      = absint( rgpost( 'target_field' ) );
		$related_field['source_form_id']       = absint( rgpost( 'source_form' ) );
		$related_field['source_form_field_id'] = absint( rgpost( 'source_form_field' ) );

		$failed_validation = false;

		if ( empty( $related_field['target_field_id'] ) ) {
			$failed_validation = true;
			GFCommon::add_error_message( __( 'You must select a field to populate.', 'gravity-forms-related-fields' ) );
		}

		if ( empty( $related_field['source_form_id'] ) ) {
			$failed_validation = true;
			GFCommon::add_error_message( __( 'You must select a source Gravity Form.', 'gravity-forms-related-fields' ) );
		}

		if ( empty( $related_field['source_form_field_id'] ) ) {
			$failed_validation = true;
			GFCommon::add_error_message( __( 'You must select a source field.', 'gravity-forms-related-fields' ) );
		}

		if ( $failed_validation ) {
			return $related_field;
		}

		// add current related field to related fields array
		$related_fields[ $related_field['id'] ] = $related_field;

		// save updated related fields array
		update_option( "gfrf_related_fields_{$form_id}", $related_fields );

		$url = remove_query_arg( array( 'rfid' ) );
		GFCommon::add_message( sprintf( __( 'Related field saved successfully. %sBack to related fields.%s', 'gravity-forms-related-fields' ), '<a href="' . esc_url( $url ) . '">', '</a>' ) );

		return $related_field;
	}

	/**
	 * Adds helper tooltips.
	 *
	 * @param array $tooltips Array of existing tooltips.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of filtered tooltips.
	 */
	function add_tooltips( $tooltips ) {
		$tooltips['gfrf_source_form']       = __( 'Entries from the source form will be used to populate your field', 'gravity-forms-related-fields' );
		$tooltips['gfrf_source_form_field'] = __( 'The this field will be used as the option value. Make sure to pick something unique', 'gravity-forms-related-fields' );
		return $tooltips;
	}
}