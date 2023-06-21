<?php
/**
 * Handles support for Deposits extension.
 *
 * @author  Sébastien Dumont
 * @package CoCart\Compatibility\Modules
 * @since   X.X.X
 * @license GPL-2.0+
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Deposits' ) ) {
	return;
}

if ( ! class_exists( 'CoCart_Deposits_Compatibility' ) ) {

	/**
	 * Free Gift Coupons Support.
	 */
	class CoCart_Deposits_Compatibility {

		/**
		 * Constructor.
		 *
		 * @access public
		 */
		public function __construct() {
			add_filter( 'cocart_add_to_cart_validation', array( $this, 'validate_add_cart_item' ), 10, 8 );
			add_filter( 'cocart_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 6 );

			//add_filter( 'woocommerce_cart_item_price', array( $this, 'display_item_price' ), 10, 3 );
			//add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'display_item_subtotal' ), 10, 3 );
		}

		/**
		 * When an item is added to the cart, validate it.
		 *
		 * @throws CoCart_Data_Exception Exception if invalid data is detected.
		 *
		 * @access public
		 *
		 * @static
		 *
		 * @param bool            true          Default is true to allow the product to pass validation.
		 * @param int             $product_id   ID of the product.
		 * @param int|float       $quantity     Quantity of the item.
		 * @param int             $variation_id Variation ID of the product.
		 * @param array           $variation    Contains the selected attributes.
		 * @param object          $item_data    Extra cart item data we want to pass into the item.
		 * @param string          $product_type Product type.
		 * @param WP_REST_Request $request      Full details about the request.
		 *
		 * @return bool
		 */
		public static function validate_add_cart_item( $passed, $product_id, $qty, $variation_id = 0, $variations = array(), $cart_item_data = array(), $product_type, $request ) {
			try {
				if ( ! \WC_Deposits_Product_Manager::deposits_enabled( $product_id ) ) {
					return $passed;
				}

				// Get parameters.
				$params               = $request->get_params();
				$is_deposit           = ! empty( $params[ 'is_deposit' ] ) ? sanitize_text_field( wp_unslash( $params[ 'is_deposit' ] ) ) : 'no';                        // Shadows $_POST['wc_deposit_option']
				$deposit_payment_plan = ! empty( $params[ 'deposit_payment_plan' ] ) ? (int) sanitize_text_field( wp_unslash( $params[ 'deposit_payment_plan' ] ) ) : 0; // Shadows $_POST['wc_deposit_payment_plan']

				// Validate chosen plan.
				if ( ( 'yes' === $is_deposit || \WC_Deposits_Product_Manager::deposits_forced( $product_id ) ) && 'plan' === \WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
					if ( ! in_array( $deposit_payment_plan, \WC_Deposits_Plans_Manager::get_plan_ids_for_product( $product_id ), true ) ) {
						throw new CoCart_Data_Exception( 'cocart_deposits_payment_plan', __( 'Please select a valid payment plan.', 'woocommerce-deposits' ), 408 );
					}
				}

				return $passed;
			} catch ( CoCart_Data_Exception $e ) {
				return CoCart_Response::get_error_response( $e->getErrorCode(), $e->getMessage(), $e->getCode(), $e->getAdditionalData() );
			}
		} // END validate_add_cart_item()

		/**
		 * Add posted data to the cart item.
		 *
		 * @access public
		 *
		 * @static
		 *
		 * @param array           $item_data    Cart item data.
		 * @param int             $product_id   ID of the Product.
		 * @param int             $variation_id Variation ID of the product.
		 * @param int|float       $quantity     Quantity of the item.
		 * @param string          $product_type Product type.
		 * @param WP_REST_Request $request      Full details about the request.
		 *
		 * @return array
		 */
		public static function add_cart_item_data( $item_data, $product_id, $variation_id, $quantity, $product_type, $request ) {
			if ( ! empty( $variation_id ) ) {
				$product_id = $variation_id;
			}

			if ( ! \WC_Deposits_Product_Manager::deposits_enabled( $product_id ) ) {
				return $item_data;
			}

			$params               = $request->get_params();
			$is_deposit           = ! empty( $params[ 'is_deposit' ] ) ? sanitize_text_field( wp_unslash( $params[ 'is_deposit' ] ) ) : 'no';                        // Shadows $_POST['wc_deposit_option']
			$deposit_payment_plan = ! empty( $params[ 'deposit_payment_plan' ] ) ? (int) sanitize_text_field( wp_unslash( $params[ 'deposit_payment_plan' ] ) ) : 0; // Shadows $_POST['wc_deposit_payment_plan']

			if ( 'yes' === $is_deposit || \WC_Deposits_Product_Manager::deposits_forced( $product_id ) ) {
				$item_data['is_deposit'] = true;

				if ( 'plan' === \WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
					$item_data['payment_plan'] = $deposit_payment_plan;
				} else {
					$item_data['payment_plan'] = 0;
				}
			}

			return $item_data;
		} // END add_cart_item_data()

		/**
		 * Show the correct item price.
		 *
		 * @param string $output Price HTML.
		 * @param array  $cart_item Cart item.
		 * @param string $cart_item_key Cart item key.
		 *
		 * @return string
		 */
		public function display_item_price( $output, $cart_item, $cart_item_key ) {
			if ( ! isset( $cart_item['full_amount'] ) ) {
				return $output;
			}
			if ( ! empty( $cart_item['is_deposit'] ) ) {
				$_product = $cart_item['data'];
				if ( 'excl' === WC()->cart->get_tax_price_display_mode() ) {
					$amount = $this->get_price_excluding_tax(
						$_product,
						array(
							'qty'   => 1,
							'price' => $cart_item['full_amount'],
						)
					);
				} else {
					$amount = $this->get_price_including_tax(
						$_product,
						array(
							'qty'   => 1,
							'price' => $cart_item['full_amount'],
						)
					);
				}
				$output = wc_price( $amount );
			}
			return $output;
		}

		/**
		 * Adjust the subtotal display in the cart.
		 *
		 * @param string $output Subtotal HTML.
		 * @param array  $cart_item Cart item.
		 * @param string $cart_item_key Cart item key.
		 *
		 * @return string
		 */
		public function display_item_subtotal( $output, $cart_item, $cart_item_key ) {
			if ( ! isset( $cart_item['full_amount'] ) ) {
				return $output;
			}

			if ( ! empty( $cart_item['is_deposit'] ) ) {
				$_product    = $cart_item['data'];
				$quantity    = $cart_item['quantity'];
				$full_amount = $cart_item['full_amount'];
				// We need to apply this filter to the deposit amount, as it may have been affected by Memberships.
				$deposit_amount = apply_filters( 'woocommerce_deposits_get_deposit_amount', $cart_item['deposit_amount'], $_product );

				if ( 'excl' === WC()->cart->get_tax_price_display_mode() ) {
					$full_amount    = $this->get_price_excluding_tax(
						$_product,
						array(
							'qty'   => $quantity,
							'price' => $full_amount,
						)
					);
					$deposit_amount = $this->get_price_excluding_tax(
						$_product,
						array(
							'qty'   => $quantity,
							'price' => $deposit_amount,
						)
					);
					$output         = wc_price( $deposit_amount );

					/**
					 * Optionally add (ex. tax) suffix.
					 *
					 * @see WC_Cart::get_product_subtotal
					 */
					if ( wc_prices_include_tax() && WC()->cart->get_subtotal_tax() > 0 ) {
						$output .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
					}
				} else {
					$full_amount    = $this->get_price_including_tax(
						$_product,
						array(
							'qty'   => $quantity,
							'price' => $full_amount,
						)
					);
					$deposit_amount = $this->get_price_including_tax(
						$_product,
						array(
							'qty'   => $quantity,
							'price' => $deposit_amount,
						)
					);
					$output         = wc_price( $deposit_amount );

					/**
					 * Optionally add (incl. tax) suffix.
					 *
					 * @see WC_Cart::get_product_subtotal
					 */
					if ( ! wc_prices_include_tax() && WC()->cart->get_subtotal_tax() > 0 ) {
						$output .= ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
					}
				}

				// Adding this to be compatible with WC3.2 changes. Allow further modification by other plugins.
				$output = apply_filters( 'woocommerce_cart_product_price', $output, $_product );

				if ( ! empty( $cart_item['payment_plan'] ) ) {
					$plan    = new WC_Deposits_Plan( $cart_item['payment_plan'] );
					$output .= '<br/><small>' . $plan->get_formatted_schedule( $full_amount ) . '</small>';
				} else {
					/* translators: item subtotal */
					$output .= '<br/><small>' . sprintf( __( '%s payable in total', 'woocommerce-deposits' ), wc_price( $full_amount ) ) . '</small>';
				}
			}

			return $output;
		}

	} // END class.

} // END if class exists.

return new CoCart_Deposits_Compatibility();
