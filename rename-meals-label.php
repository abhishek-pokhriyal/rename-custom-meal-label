<?php
/**
 * Plugin Name:       Rename Custom Meal Labels
 * Plugin URI:        https://github.com/abhishek-pokhriyal/rename-custom-meal-label
 * Description:       Rename Custom Meal Labels.
 * Version:           1.0.0
 * Author:            ColoredCow
 * Author URI:        https://coloredcow.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rename-custom-meal-label
 */

// https://xyz.abc/?rename_label=1&old=Grass Fed Beef&new=Grass Fed Beef (93%)
// https://xyz.abc/?rename_label=1&old=Cod&new=Wild Caught Cod
add_action( 'wp_loaded', 'rename_custom_meal_label' );
function rename_custom_meal_label() {
	if ( isset( $_GET['rename_label'] ) && $_GET['rename_label'] ) {
		if ( isset( $_GET['old'], $_GET['new'] ) ) {
			$old = $_GET['old'];
			$new = $_GET['new'];

			// $gf_rows_updated  = rcml_rename_gf_meta( $old, $new );
			$mcf_rows_updated = rcml_rename_mcf_meta( $old, $new );
			$labels_updated = rcml_rename_custom_meal_label( $old, $new );

			// $message  = sprintf( 'Renamed %1$s to %2$s at %3$s places in gravity form data.<br>', $old, $new, $gf_rows_updated );
			$message .= sprintf( 'Renamed %1$s to %2$s at %3$s places in mcf data.<br>', $old, $new, $mcf_rows_updated );
			$message .= sprintf( 'Renamed %1$s to %2$s at %3$s places in labels.<br>', $old, $new, $labels_updated );

			wp_die( $message );
		} else {
			wp_die( 'Invalid GET parameters!' );
		}
	}
}

function rcml_rename_gf_meta( $old, $new ) {
	global $wpdb;

	$table        = $wpdb->prefix . 'woocommerce_order_itemmeta';
	$query        = "SELECT * FROM $table WHERE meta_key = '_gravity_forms_history' AND meta_value LIKE '%\"{$old}|%'";
	$updated_rows = 0;

	$order_item_meta = $wpdb->get_results( $query, ARRAY_A );
	foreach ( $order_item_meta as $meta ) {
		$meta_id                             = $meta['meta_id'];
		$gf_history                          = maybe_unserialize( $meta['meta_value'] );

		// The '1' in the array index represent position of protein label in the gravity form lead.
		// The hardcoded value '1', restricts this plugin to only 'protein'. It can be made dynamic.
		$exploded                            = explode( '|', $gf_history['_gravity_form_lead'][1] );

		if ( $exploded[0] !== $old ) {
			continue;
		}

		$exploded[0]                         = $new;
		$gf_history['_gravity_form_lead'][1] = implode( '|', $exploded );
		$updated                             = $wpdb->update( $table, array( 'meta_value' => serialize( $gf_history ) ), array( 'meta_id' => $meta_id ) );

		if ( false !== $updated ) {
			$updated_rows += $updated;
		}
	}

	if ( $updated_rows > 0 ) {
		rcml_update_nutritional_value_table( $old, $new );
	}

	return $updated_rows;
}

function rcml_update_nutritional_value_table( $old, $new ) {
	global $wpdb;

	$table   = $wpdb->prefix . 'cc_custom_meal_nutritional_value';
	$updated = $wpdb->update( $table, array( 'meal_options' => $new ), array( 'meal_options' => $old ) );

	return false !== $updated;
}

function rcml_rename_mcf_meta( $old, $new ) {
	global $wpdb;

	$table        = $wpdb->prefix . 'woocommerce_order_itemmeta';
	$query        = "SELECT * FROM $table WHERE meta_key = '_mcf_data' AND meta_value LIKE '%\"{$old}\"%'";
	$updated_rows = 0;

	$order_item_meta = $wpdb->get_results( $query, ARRAY_A );
	foreach ( $order_item_meta as $meta ) {
		$meta_id  = $meta['meta_id'];
		$mcf_data = maybe_unserialize( $meta['meta_value'] );

		$mcf_data[0]['option']['title'] = $new;
		$mcf_data[0]['option']['slug']  = sanitize_title_with_dashes( $new );

		$updated = $wpdb->update( $table, array( 'meta_value' => serialize( $mcf_data ) ), array( 'meta_id' => $meta_id ) );

		if ( false !== $updated ) {
			$updated_rows += $updated;
		}
	}

	return $updated_rows;
}

function rcml_rename_custom_meal_label( $old, $new ) {
	global $wpdb;

	$table           = $wpdb->prefix . 'woocommerce_order_itemmeta';
	$query           = "SELECT * FROM $table WHERE meta_key = 'Protein' AND meta_value LIKE '%$old%'";
	$updated_rows    = 0;
	$order_item_meta = $wpdb->get_results( $query, ARRAY_A );

	foreach ( $order_item_meta as $meta ) {
		$meta_id  = $meta['meta_id'];

		// Rename only if the new label is not already present in the meta value or
		// new label is a substring of the old one.
		if ( false === strpos( $meta['meta_value'], $new ) || false !== strpos( $old, $new ) ) {
			$new_meta = str_replace( $old, $new, $meta['meta_value'] );
			$updated  = $wpdb->update( $table, array( 'meta_value' => $new_meta ), array( 'meta_id' => $meta_id ) );

			if ( false !== $updated ) {
				$updated_rows += $updated;
			}
		}
	}

	return $updated_rows;
}
