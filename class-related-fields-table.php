<?php

class GFRF_Related_Fields_Table extends WP_List_Table {

	public $form;

	function __construct( $form ) {

		$this->form = $form;

		$this->_column_headers = array(
			array(
				'cb'                => '',
				'target_field'      => __( 'Field', 'gravityformsrelatedfields' ),
				'source_form'       => __( 'Source form', 'gravityformsrelatedfields' ),
				'source_form_field' => __( 'Source form field', 'gravityformsrelatedfields' )
			),
			array(),
			array(),
			'field',
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

	function column_default( $item, $column ) {
		echo rgar( $item, $column );
	}

	function column_cb( $item ) {

		$is_active = isset( $item['is_active'] ) ? $item['is_active'] : true;
		?>
		<img src="<?php echo GFCommon::get_base_url() ?>/images/active<?php echo intval( $is_active ) ?>.png" style="cursor: pointer;margin:-5px 0 0 8px;" alt="<?php $is_active ? __( 'Active', 'gravityformsrelatedfields' ) : __( 'Inactive', 'gravityformsrelatedfields' ); ?>" title="<?php echo $is_active ? __( 'Active', 'gravityformsrelatedfields' ) : __( 'Inactive', 'gravityformsrelatedfields' ); ?>" onclick="gfrf_toggle_active(this, '<?php echo $item['id'] ?>'); " />
	<?php
	}

	function column_target_field( $item ) {
		$edit_url      = add_query_arg( array( 'rfid' => $item['id'] ) );
		$actions       = apply_filters(
			'gfrf_related_field_actions', array(
				'edit'   => '<a title="' . __( 'Edit this item', 'gravityformsrelatedfields' ) . '" href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'gravityformsrelatedfields' ) . '</a>',
				'delete' => '<a title="' . __( 'Delete this item', 'gravityformsrelatedfields' ) . '" class="submitdelete" onclick="javascript: if(confirm(\'' . __( 'WARNING: You are about to delete this related field connection.', 'gravityformsrelatedfields' ) . __( "\'Cancel\' to stop, \'OK\' to delete.", 'gravityformsrelatedfields' ) . '\')){ gfrf_delete_confirmation(\'' . esc_js( $item['id'] ) . '\'); }" style="cursor:pointer;">' . __( 'Delete', 'gravityformsrelatedfields' ) . '</a>'
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