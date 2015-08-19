<?php
/**
 * GFRF_Related_Fields_Table class. This class extends WP_List_Table to handle displaying the
 * list of related field connections.
 *
 * @since 1.0.0
 *
 * @see WP_List_Table
 */
class GFRF_Related_Fields_Table extends WP_List_Table {

	/**
	 * Stores the Gravity Form the connections belong to.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	public $form;

	function __construct( $form ) {

		$this->form = $form;

		$this->_column_headers = array(
			array(
				'cb'                => '',
				'target_field'      => __( 'Field', 'gravity-forms-related-fields' ),
				'source_form'       => __( 'Source form', 'gravity-forms-related-fields' ),
				'source_form_field' => __( 'Source form field', 'gravity-forms-related-fields' )
			),
			array(),
			array(),
			'target_field',
		);

		parent::__construct();
	}

	function prepare_items() {
		$this->items = gfrf_get_related_fields( $this->form['id'] );
	}

	function display() {
		$singular = rgar( $this->_args, 'singular' );
		?>
		<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>" cellspacing="0">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

			<tbody id="the-list"<?php if ( $singular )
				echo " class='list:$singular'"; ?>>

			<?php $this->display_rows_or_placeholder(); ?>

			</tbody>
		</table>

	<?php
	}

	function single_row( $item ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		echo '<tr id="confirmation-' . $item['id'] . '" ' . $row_class . '>';
		echo $this->single_row_columns( $item );
		echo '</tr>';
	}

	function get_columns() {
		return $this->_column_headers[0];
	}

	function column_default( $item, $column ) {
		echo rgar( $item, $column );
	}

	/**
	 * Display a toggle button to deactivate related field connections.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Related Field being displayed.
	 */
	function column_cb( $item ) {

		$is_active = isset( $item['is_active'] ) ? $item['is_active'] : true;
		?>
		<img src="<?php echo GFCommon::get_base_url() ?>/images/active<?php echo intval( $is_active ) ?>.png" style="cursor: pointer;margin:-5px 0 0 8px;" alt="<?php $is_active ? __( 'Active', 'gravity-forms-related-fields' ) : __( 'Inactive', 'gravity-forms-related-fields' ); ?>" title="<?php echo $is_active ? __( 'Active', 'gravity-forms-related-fields' ) : __( 'Inactive', 'gravity-forms-related-fields' ); ?>" onclick="gfrf_toggle_active(this, '<?php echo $item['id'] ?>'); " />
	<?php
	}

	function column_target_field( $item ) {
		$edit_url      = add_query_arg( array( 'rfid' => $item['id'] ) );
		$actions       = apply_filters(
			'gfrf_related_field_actions', array(
				'edit'   => '<a title="' . __( 'Edit this item', 'gravity-forms-related-fields' ) . '" href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'gravity-forms-related-fields' ) . '</a>',
				'delete' => '<a title="' . __( 'Delete this item', 'gravity-forms-related-fields' ) . '" class="submitdelete" onclick="javascript: if(confirm(\'' . __( 'WARNING: You are about to delete this related field connection.', 'gravity-forms-related-fields' ) . __( "\'Cancel\' to stop, \'OK\' to delete.", 'gravity-forms-related-fields' ) . '\')){ gfrf_delete_confirmation(\'' . esc_js( $item['id'] ) . '\'); }" style="cursor:pointer;">' . __( 'Delete', 'gravity-forms-related-fields' ) . '</a>'
			)
		);
		foreach ( $this->form['fields'] as $field ) {
			if ( $field['id'] == $item['target_field_id'] ) {
				$field_label = $field['label'];
				break;
			}
		}

		?>

		<a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $field_label ); ?></strong></a>
		<div class="row-actions">

			<?php
			if ( is_array( $actions ) && ! empty( $actions ) ) {
				$keys     = array_keys( $actions );
				$last_key = array_pop( $keys );
				foreach ( $actions as $key => $html ) {
					$divider = $key == $last_key ? '' : ' | ';
					?>
					<span class="<?php echo $key; ?>">
						<?php echo $html . $divider; ?>
					</span>
				<?php
				}
			}
			?>

		</div>

	<?php
	}

	function column_source_form( $item ) {
		$form = GFFormsModel::get_form_meta( $item['source_form_id'] );

		if ( isset( $form['title'] ) ) {
			echo $form['title'];
		}
	}

	function column_source_form_field( $item ) {
		$form = GFFormsModel::get_form_meta( $item['source_form_id'] );

		foreach ( $form['fields'] as $field ) {
			if ( $field['id'] == $item['source_form_field_id'] ) {
				echo empty( $field['adminLabel'] ) ? $field['label'] : $field['adminLabel'];
				break;
			}
		}
	}

}