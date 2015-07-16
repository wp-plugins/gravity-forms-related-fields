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

		if ( ! gfrf_is_valid_field_type( $field['type'] ) ) {
			continue;
		}

		$related_field = gfrf_get_related_field( $field['id'], $related_fields );

		$is_active  = isset( $related_field['is_active'] ) ? $related_field['is_active'] : true;

		if ( ! $is_active ) {
			continue;
		}

		$source_form_id       = rgar( $related_field, 'source_form_id' );
		$source_form_field_id = rgar( $related_field, 'source_form_field_id' );

		$entries = gfrf_get_entries( $source_form_id, $source_form_field_id );
		$choices = array();

		foreach ( $entries as $entry ) {
			if ( isset( $entry[ $source_form_field_id ] ) && ! empty ( $entry[ $source_form_field_id ] ) ) {
				$choices[] = array( 'text' => $entry[ $source_form_field_id ], 'value' => $entry[ $source_form_field_id ] );
			}
		}

		$field['choices'] = $choices;

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
 * @param int $target_field_id Related field mapping to check
 * @param array $related_fields Array of related field mappings
 *
 * @return array Related field array if matched or empty string if not found.
 */
function gfrf_get_related_field( $target_field_id, $related_fields ) {
	foreach ( $related_fields as $related_field ) {
		if ( rgar( $related_field, 'target_field_id' ) == $target_field_id ) {
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

function gfrf_get_available_form_fields( $form_id, $selected_field_id = 0 ) {
	$form = GFFormsModel::get_form_meta( $form_id );

	$form_fields = array();

	$str = '<option value="">' . __( 'Select a field', 'gravityformsrelatedfields' ) . ' </option>';
	foreach ( $form['fields'] as $field ) {
		if ( $field['displayOnly'] ) {
			continue;
		}
		$label = empty( $field['adminLabel'] ) ? $field['label'] : $field['adminLabel'];
		$str .= '<option value="' . $field['id'] . '"' . selected( $field['id'], $selected_field_id, false ) . '>' . esc_html( $label ) . '</option>' . "\n";
	}

	return $str;
}

/**
 * Helper function to check if field is populateable
 *
 * @since 1.0.0
 *
 * @param string $field_type Field type to check.
 *
 * @return bool If field is valid or not.
 */
function gfrf_is_valid_field_type( $field_type ) {
	$is_valid = false;

	switch ( $field_type ) {
		case 'select':
		case 'multiselect':
		case 'radio':
		case 'checkbox':
			$is_valid = true;
			break;
	}

	/**
	 * Filter to check if a Gravity Forms field type is able to support related fields.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $is_valid Whether $field_type is valid or not.
	 * @param string $field_type Gravity Forms field type to check.
	 */
	$is_valid = apply_filters( 'gfrf_is_valid_field_type', $is_valid, $field_type );

	return $is_valid;
}

function gfrf_get_available_form_fields_callback() {
	check_ajax_referer( 'gfrf_get_available_form_fields', 'security' );

	$form_id = rgpost( 'form_id' );

	echo gfrf_get_available_form_fields( $form_id );
	die();
}
add_action( 'wp_ajax_gfrf_get_available_form_fields', 'gfrf_get_available_form_fields_callback' );

function gfrf_toggle_related_field_active_callback() {
	check_ajax_referer( 'gfrf_toggle_related_field_active', 'security' );

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

if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
	require_once 'class-admin.php';
	add_action( 'plugins_loaded', array( 'GFRF_Admin', 'get_instance' ) );
}
