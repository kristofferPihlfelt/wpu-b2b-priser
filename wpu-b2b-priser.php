<?php
/**
 * Plugin Name:       WPU B2B-priser
 * Plugin URI:        https://wordpressutveckling.se/b2b-prisplugin-woocommerce/
 * Description:       Kundgrupp, B2B-pris per produkt, procentrabatt och priser exkl. moms för inloggade B2B-kunder.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Author:            Pihlfelt & Vänner
 * Author URI:        https://pihlfelt.se
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpu-b2b
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1. The customer group: a role with the same capabilities as "customer".
 */
register_activation_hook( __FILE__, 'wpu_b2b_activate' );
function wpu_b2b_activate() {
	$customer = get_role( 'customer' );
	add_role( 'b2b_kund', 'B2B-kund', $customer ? $customer->capabilities : array( 'read' => true ) );
	// WordPress stores the uninstall callback in an option, so it belongs here and not at file load.
	register_uninstall_hook( __FILE__, 'wpu_b2b_uninstall' );
}

function wpu_b2b_uninstall() {
	// A removed role would leave its users without any role, so they become plain customers first.
	foreach ( get_users( array( 'role' => 'b2b_kund', 'fields' => 'ID' ) ) as $user_id ) {
		$user = new WP_User( $user_id );
		$user->remove_role( 'b2b_kund' );
		if ( empty( $user->roles ) ) {
			$user->add_role( 'customer' );
		}
	}
	remove_role( 'b2b_kund' );
	delete_option( 'wpu_b2b_discount_percent' );
	delete_option( 'wpu_b2b_show_excl_tax' );
	delete_post_meta_by_key( '_wpu_b2b_price' );
}

// The plugin never touches orders, but the declaration keeps it off the "uncertain" list under Features.
add_action( 'before_woocommerce_init', 'wpu_b2b_declare_hpos' );
function wpu_b2b_declare_hpos() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

/*
 * 2. Who gets B2B prices, and the two settings under WooCommerce > Settings > Products.
 */
function wpu_b2b_is_customer() {
	$user = wp_get_current_user();
	return $user->exists() && in_array( 'b2b_kund', (array) $user->roles, true );
}

add_filter( 'woocommerce_product_settings', 'wpu_b2b_settings' );
function wpu_b2b_settings( $settings ) {
	$settings[] = array(
		'title' => 'B2B-priser',
		'type'  => 'title',
		'id'    => 'wpu_b2b_options',
		'desc'  => 'Gäller inloggade kunder med rollen B2B-kund.',
	);
	$settings[] = array(
		'title'             => 'Rabatt i procent',
		'id'                => 'wpu_b2b_discount_percent',
		'type'              => 'number',
		'default'           => '0',
		'desc_tip'          => 'Används på produkter som saknar eget B2B-pris.',
		'custom_attributes' => array(
			'min'  => '0',
			'max'  => '100',
			'step' => '0.01',
		),
	);
	$settings[] = array(
		'title'   => 'Visa priser exkl. moms',
		'id'      => 'wpu_b2b_show_excl_tax',
		'type'    => 'checkbox',
		'default' => 'no',
		'desc'    => 'B2B-kunder ser priser exklusive moms i butik, varukorg och kassa.',
	);
	$settings[] = array(
		'type' => 'sectionend',
		'id'   => 'wpu_b2b_options',
	);
	return $settings;
}

/*
 * 3. The B2B price field on simple products and on every variation.
 */
add_action( 'woocommerce_product_options_pricing', 'wpu_b2b_product_field' );
function wpu_b2b_product_field() {
	woocommerce_wp_text_input(
		array(
			'id'          => '_wpu_b2b_price',
			'label'       => 'B2B-pris (' . get_woocommerce_currency_symbol() . ')',
			'data_type'   => 'price',
			'desc_tip'    => true,
			'description' => 'Pris för inloggade B2B-kunder. Tomt betyder procentrabatten i inställningarna.',
		)
	);
}

add_action( 'woocommerce_admin_process_product_object', 'wpu_b2b_save_product_field' );
function wpu_b2b_save_product_field( $product ) {
	// WooCommerce has verified the product form nonce before this hook runs.
	if ( ! isset( $_POST['_wpu_b2b_price'] ) || is_array( $_POST['_wpu_b2b_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	wpu_b2b_store_price( $product, wc_clean( wp_unslash( $_POST['_wpu_b2b_price'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

add_action( 'woocommerce_variation_options_pricing', 'wpu_b2b_variation_field', 10, 3 );
function wpu_b2b_variation_field( $loop, $variation_data, $variation ) {
	// $variation is a WP_Post here; variations are posts even with HPOS, which only moves orders.
	woocommerce_wp_text_input(
		array(
			'id'            => "_wpu_b2b_variation_price_{$loop}",
			'name'          => "_wpu_b2b_variation_price[{$loop}]",
			'value'         => get_post_meta( $variation->ID, '_wpu_b2b_price', true ),
			'label'         => 'B2B-pris (' . get_woocommerce_currency_symbol() . ')',
			'data_type'     => 'price',
			'wrapper_class' => 'form-row form-row-full',
		)
	);
}

add_action( 'woocommerce_admin_process_variation_object', 'wpu_b2b_save_variation_field', 10, 2 );
function wpu_b2b_save_variation_field( $variation, $i ) {
	if ( ! isset( $_POST['_wpu_b2b_variation_price'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	wpu_b2b_store_price( $variation, wc_clean( wp_unslash( $_POST['_wpu_b2b_variation_price'][ $i ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

function wpu_b2b_store_price( $product, $raw ) {
	// The product saves itself right after these hooks, so only the meta needs setting.
	$value = wc_format_decimal( $raw );
	if ( '' === $value ) {
		$product->delete_meta_data( '_wpu_b2b_price' );
	} else {
		$product->update_meta_data( '_wpu_b2b_price', $value );
	}
}

/*
 * 4. The price. One function, hooked into every place WooCommerce reads an active price.
 */
function wpu_b2b_price( $price, $product ) {
	if ( '' === $price || null === $price || ! wpu_b2b_is_customer() ) {
		return $price;
	}
	$fixed = $product->get_meta( '_wpu_b2b_price' );
	if ( '' !== $fixed && null !== $fixed ) {
		return wc_format_decimal( $fixed, wc_get_price_decimals() );
	}
	$percent = (float) get_option( 'wpu_b2b_discount_percent', 0 );
	if ( $percent <= 0 || $percent > 100 ) {
		return $price;
	}
	// Work on $price as given. Calling $product->get_price() in here would run this filter again.
	return wc_format_decimal( (float) $price * ( 1 - $percent / 100 ), wc_get_price_decimals() );
}
add_filter( 'woocommerce_product_get_price', 'wpu_b2b_price', 10, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'wpu_b2b_price', 10, 2 );
// The price range of a variable product reads its variations in "edit" context, which skips the two filters above.
add_filter( 'woocommerce_variation_prices_price', 'wpu_b2b_price', 10, 2 );

add_filter( 'woocommerce_get_variation_prices_hash', 'wpu_b2b_prices_hash' );
function wpu_b2b_prices_hash( $hash ) {
	// The range is cached in a transient. Without this key one group's range would be served to the other.
	$hash['wpu_b2b'] = wpu_b2b_is_customer() ? (string) get_option( 'wpu_b2b_discount_percent', 0 ) : 'no';
	return $hash;
}

/*
 * 5. Prices without VAT for the group. WooCommerce reads the display mode with get_option(),
 *    in the shop, in the cart and when the Cart and Checkout blocks render, so the option filter covers them all.
 */
add_filter( 'option_woocommerce_tax_display_shop', 'wpu_b2b_tax_display' );
add_filter( 'option_woocommerce_tax_display_cart', 'wpu_b2b_tax_display' );
function wpu_b2b_tax_display( $value ) {
	if ( ! did_action( 'init' ) || 'yes' !== get_option( 'wpu_b2b_show_excl_tax', 'no' ) || ! wpu_b2b_is_customer() ) {
		return $value;
	}
	return 'excl';
}
