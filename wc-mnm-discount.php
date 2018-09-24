<?php
/*
* Plugin Name: WooCommerce Mix and Match: Per-Item Discount
* Plugin URI: https://woocommerce.com/products/woocommerce-mix-and-match-products/
* Description: Discounts for WooCommerce Mix and Match Products.
* Version: 1.0.0-beta-1
* Author: Kathy Darling
* Author URI: http://kathyisawesome.com/
*
* Text Domain: woocommerce-mix-and-match-products-discount
* Domain Path: /languages/
*
* Requires at least: 4.9
* Tested up to: 4.9
*
* WC requires at least: 3.3
* WC tested up to: 3.4
*
* Copyright: © 2018 Kathy Darling
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_MNM_Discount {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '1.0.0-beta-1';

	/**
	 * Min required PB version.
	 *
	 * @var string
	 */
	public static $req_mnm_version = '1.3.0';

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename(__FILE__) );
	}

	/**
	 * Plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Fire in the hole!
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin' ) );
	}

	/**
	 * Hooks.
	 */
	public static function load_plugin() {

		// Check dependencies.
		if ( ! function_exists( 'WC_Mix_and_Match' ) || version_compare( WC_Mix_and_Match()->version, self::$req_mnm_version ) < 0 ) {
			add_action( 'admin_notices', array( __CLASS__, 'version_notice' ) );
			return false;
		}

		/*
		 * Admin.
		 */

		// Display discount option.
		add_action( 'woocommerce_mnm_product_options', array( __CLASS__, 'discount_options' ), 35, 2 );

		// Save discount data.
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_meta' ) );

		/*
		 * Cart.
		 */
		add_filter( 'woocommerce_mnm_cart_item', array( __CLASS__, 'mnm_cart_item_discount' ), 10, 2 );

		/*
		 * Products / Catalog.
		 */
		add_action( 'woocommerce_mnm_child_item_details', array( __CLASS__, 'add_discount_price_of_items_filter' ), -10, 2 );
		add_action( 'woocommerce_mnm_child_item_details', array( __CLASS__, 'remove_discount_price_of_items_filter' ), 999, 2 );

		// Modify the catalog price to include discounts for the default min quantities.
		add_filter( 'woocommerce_mnm_price_html', array( __CLASS__, 'bundle_get_discounted_price_html' ), 10 , 2 );

	}

	/*
	|--------------------------------------------------------------------------
	| Application layer.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Calculates a discounted price based on discount.
	 *
	 * @param  mixed    $price
	 * @param  mixed  	$discount
	 * @return mixed    $price
	 */
	public static function calculate_discount( $price, $discount ) {
		return (double) ( ( 100 - $discount ) / 100 ) * $price;
	}


	/**
	 * Whether to apply discounts to the base price.
	 *
	 * @param  array    $bundle
	 * @return boolean
	 */
	public static function apply_discount_to_base_price( $bundle ) {
		/**
		 * 'wc_mnm_bulk_discount_apply_to_base_price' filter.
		 *
		 * @param  bool               $apply
		 * @param  WC_Product_Bundle  $bundle
		 */
		return apply_filters( 'wc_mnm_bulk_discount_apply_to_base_price', false, $bundle );
	}

	/*
	|--------------------------------------------------------------------------
	| Admin and Metaboxes.
	|--------------------------------------------------------------------------
	*/

	/**
	 * PB version check notice.
	 */
	public static function version_notice() {
		echo '<div class="error"><p>' . sprintf( __( '<strong>WooCommerce Mix and Match Products &ndash; Discount</strong> requires Mix and Match Products <strong>%s</strong> or higher.', 'woocommerce-mix-and-match-products-discount' ), self::$req_mnm_version ) . '</p></div>';
	}

	/**
	 * Add bundle quantity discount option.
	 *
	 * @param  WC_Product  $product_bundle_object
	 * @return void
	 */
	public static function discount_options( $post_id, $mnm_product_object ) {

		// Per-Item Discount.
		woocommerce_wp_text_input( array(
			'id'          => '_mnm_per_product_discount',
			'wrapper_class' => 'show_if_per_item_pricing',
			'label'       => __( 'Per-Item Discount (%s)', 'woocommerce-mix-and-match-products' ),
			'value'       => $mnm_product_object->get_meta( '_mnm_per_product_discount', true ),
			'description' => __( 'Discount applied to each item when in per-item pricing mode. This discount applies whenever the quantity restrictions are satisfied.', 'woocommerce-mix-and-match-products' ),
			'desc_tip'    => true,
			'data_type'   => 'decimal',
		) );

		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'discount_options_scripts' ) );
	}

	/**
	 * Add bundle quantity discount option.
	 *
	 * @param  WC_Product  $product_bundle_object
	 * @return void
	 */
	public static function discount_options_scripts() {?>

		<script type="text/javascript">

			jQuery(document).ready(function ($) {;

				// Hide/Show Per-Item related fields.
				$('#_mnm_per_product_pricing').change(function() {;
					$dependents = $(this).closest('#mnm_product_data').find('.show_if_per_item_pricing');
					
					if( $( this ).prop( "checked" ) ) {
						$dependents.slideDown();
					} else {
						$dependents.slideUp();
					}	
				}).change();

			});

		</script>

		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function save_meta( $product ) {

		$input_data           = $_POST[ '_mnm_per_product_discount' ];
		$clean_data = wc_clean( wp_unslash( $_POST[ '_mnm_per_product_discount' ] ) );

		if ( ! empty( $_POST[ '_mnm_per_product_discount' ] ) ) {
			$product->add_meta_data( '_mnm_per_product_discount', $clean_data, true );
		} else {
			$product->delete_meta_data( '_mnm_per_product_discount' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Cart.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Applies discount on bundled cart items based on overall cart quantity.
	 *
	 * @param  array  $cart_item
	 * @param  obj    WC_Product_Mix_and_Match $container_product
	 * @return array  $cart_item
	 */
	public static function mnm_cart_item_discount( $cart_item, $container_product ) {

		if ( wc_mnm_is_mnm_cart_item( $cart_item ) ) {

			$discount = self::get_discount( $container_product );
			
			$price = $cart_item[ 'data' ]->get_price();

			if ( $price && ! empty( $discount ) ) {

				$discounted_price = self::calculate_discount( $price, $discount );
				$cart_item[ 'data' ]->set_price( $discounted_price );

			}
		}

		return $cart_item;
	}

	/*
	|--------------------------------------------------------------------------
	| Products / Catalog.
	|--------------------------------------------------------------------------
	*/


	/**
	 * Add filter.
	 *
	 * @param  object WC_Product $child_product
	 * @param  object WC_Product_Mix_and_Match $container_product
	 */
	public static function add_discount_price_of_items_filter( $child_product, $container_product ) {

		$discount = self::get_discount( $container_product );
		
		$child_product->add_meta_data( '_mnm_discount', $discount, true );

		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
	}

	/**
	 * Clear filter.
	 *
	 * @param  object WC_Product $child_product
	 * @param  object WC_Product_Mix_and_Match $container_product
	 */
	public static function remove_discount_price_of_items_filter( $child_product, $container_product ) {	
		remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
		remove_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
		remove_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
		remove_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'discount_price_of_items' ), 10, 2 );
	}


	/**
	 * Filter the price of MNM items *only* while they are being displayed in product page.
	 *
	 * @param  string  $price
	 * @param  object  $product
	 * @return string
	 */
	public static function discount_price_of_items( $price, $product ) { 
		$discount = $product->get_meta( '_mnm_discount' );
		
		if( $price == '' ) {
			$price = $product->get_regular_price( 'edit' );
		}
		
		if ( $price && ! empty( $discount ) ) {
			$price = self::calculate_discount( $price, $discount );
		}

		return $price;

	}

	/**
	 * Returns discounted price based on default min quantities.
	 *
	 * @param  string  $price
	 * @param  object  $product
	 * @return string  $price
	 */
	public static function bundle_get_discounted_price_html( $price, $product ) {

		$discount = self::get_discount( $product );

		if ( $price && ! empty( $discount ) ) {
			
			$regular_price = $product->get_mnm_price( 'min' );
			$discount_price = self::calculate_discount( $regular_price, $discount );

			if ( $product->get_mnm_price( 'min' ) !== $product->get_mnm_price( 'max' ) ) {
				$price = sprintf( _x( '%1$s%2$s', 'Price range: from', 'woocommerce-mix-and-match-products' ), wc_get_price_html_from_text(), wc_format_sale_price( $regular_price, $discount_price ) . $product->get_price_suffix() );
			} else {
				$price = wc_format_sale_price( $regular_price, $discount_price ) . $product->get_price_suffix();
			}

		}
		return $price;
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the discount.
	 *
	 * @param  object  $product
	 * @return 
	 */
	public static function get_discount( $product ) {
		return apply_filters( 'wc_mnm_discount_amount', $product->get_meta( '_mnm_per_product_discount', true ), $product );
	}

}

WC_MNM_Discount::init();
