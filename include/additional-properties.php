<?php

namespace NikolayS93\Exchange;

/**
 * WC Product additional properties
 */
// Show Fields
add_action( 'woocommerce_product_options_general_product_data', __NAMESPACE__ . '\woo_add_custom_general_fields' );
function woo_add_custom_general_fields() {
	$mime = explode( '/', get_post_mime_type( get_the_ID() ) );

	$XML = array(
		'type'          => 'text',
		'id'            => 'EXT_ID',
		'label'         => 'Внешний код',
		'wrapper_class' => 'show_if_simple',
		// 'desc_tip'    => 'true',
		// 'description' => 'Разрешить продажи от этого количества',
	);

	if ( $mime[0] == 'XML' && isset( $mime[1] ) ) {
		$XML['value'] = $mime[1];
	}

	woocommerce_wp_text_input( $XML );
	woocommerce_wp_text_input( array(
		'type'          => 'text',
		'id'            => '_unit',
		'label'         => 'Единица измерения',
		'wrapper_class' => 'show_if_simple',
	) );
}

// Save Fields
add_action( 'woocommerce_process_product_meta', __NAMESPACE__ . '\woo_custom_general_fields_save' );
function woo_custom_general_fields_save( $post_id ) {
	global $wpdb;

	if ( isset( $_POST['XML_ID'] ) ) {
		$wpdb->update( $wpdb->posts,
			array( 'post_mime_type' => 'XML/' . $_POST['XML_ID'] ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	if ( isset( $_POST['_unit'] ) ) {
		update_post_meta( $post_id, '_unit', sanitize_text_field( $_POST['_unit'] ) );
	}
}