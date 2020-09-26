<?php
// Make sure we don't expose any info if called directly
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/**
 * Plugin Name: WFCM Whatsapp Checkout
 * Description: Whatsapp checkout plugin for WFCM.
 * Version: 1.1
 * Author: aditbaco, Plonknimbuzz
 * Author URI: https://github.com/aditbaco/WA-Checkout-WCFM-Woocommerce
 * Requires at least Woocommerce : 4.1
 * Requires at least WCFM Front End Manager : 6.4
 * Requires at least WCFM Marketplace Multi Vendor : 3.4
 * Tested up to Wordpress : 5.5
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

 // Check if WooCommerce is active
 function WCFMWC_check_woocommece_active(){
	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		echo "<div class='error'><p><strong>WCFM Whatsapp Checkout</strong> requires <strong>WooCommerce plugin.</strong>&nbsp; Please <a href='https://wordpress.org/plugins/woocommerce' target=_blank>install</a> and activate it.</p></div>";
		}
	}
	add_action('admin_notices', 'WCFMWC_check_woocommece_active');

// Check if WCFM is active
 function WCFMWC_check_wcmv_active(){
	if ( ! is_plugin_active( 'wc-multivendor-marketplace/wc-multivendor-marketplace.php' ) ) {
		echo "<div class='error'><p><strong>WCFM Whatsapp Checkout</strong> requires <strong>WC Multivendor Marketplace plugin.</strong>&nbsp; Please <a href='https://wordpress.org/plugins/wc-multivendor-marketplace' target=_blank>install</a> and activate it.</p></div>";
		}
	}
	add_action('admin_notices', 'WCFMWC_check_wcmv_active');

// Check if WCFM is active
 function WCFMWC_check_wcfm_active(){
	if ( ! is_plugin_active( 'wc-frontend-manager/wc_frontend_manager.php' ) ) {
		echo "<div class='error'><p><strong>WCFM Whatsapp Checkout</strong> requires <strong>WC Multivendor Marketplace - Frontend Manager plugin.</strong>&nbsp; Please <a href='https://wordpress.org/plugins/wc-frontend-manager' target=_blank>install</a> and activate it.</p></div>";
		}
	}
	add_action('admin_notices', 'WCFMWC_check_wcfm_active');

//register Whatsapp number to vendors store setting
add_filter( 'wcfm_marketplace_settings_fields_general', 'vendor_store_custom_fields' );
function vendor_store_custom_fields($settings_fields_general) {
	global $WCFM, $WCFMmp, $wp;
	if(isset($settings_fields_general['banner'])){
		return $settings_fields_general; //fix bug on admin store manage page
	}
	if( current_user_can('manage_woocommerce') ) {
		$van_cur_url = add_query_arg( array(), $wp->request );
		$van_vendorid = substr( $van_cur_url, strrpos( $van_cur_url, '/' ) + 1 );
		$user_id = intval( $van_vendorid );
	}
	else {
		$user_id = apply_filters( 'wcfm_current_vendor_id', get_current_user_id() );
	}
	//Register Whatsapp Number button and option in vendor's profile
	$store_whatsapp_opt = array( 'yes' => __( 'Yes', 'wc-frontend-manager' ), 'no' => __( 'No', 'wc-frontend-manager' ) );
	$vendor_data = get_user_meta( $user_id, 'wcfmmp_profile_settings', true );
	$store_whatsapp_show = isset( $vendor_data['store_whatsapp_show'] ) ? $vendor_data['store_whatsapp_show'] : 'no';
	$store_whatsapp = isset( $vendor_data['store_whatsapp_number'] ) ? $vendor_data['store_whatsapp_number'] : null;
	$settings_fields_general["store_whatsapp_number"] = array('label' => __('Whatsapp Number', 'wc-frontend-manager') , 'type' => 'text',  'class' => 'wcfm-text wcfm_ele ', 'label_class' => 'wcfm_title', 'value' => $store_whatsapp );
	$settings_fields_general["store_whatsapp_show"] = array('label' => __('Show Whatsapp button on Checkout', 'wc-frontend-manager') , 'type' => 'select', 'options' => $store_whatsapp_opt, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $store_whatsapp_show );
	return $settings_fields_general;
}

//Register Show whatsapp number on vendor's page
add_action( 'after_wcfmmp_sold_by_info_product_page', 'cus_after_wcfmmp_sold_by_info_product_page' );
function cus_after_wcfmmp_sold_by_info_product_page( $vendor_id ) {
	$vendor_data = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
	$whatsapp = isset($vendor_data['store_whatsapp_number'])?$vendor_data['store_whatsapp_number']:null;
	if( isset($vendor_data['store_whatsapp_show']) && $vendor_data['store_whatsapp_show'] == 'yes' && !empty($whatsapp)) {
		echo '<div class="wcfmmp_store_tab_info wcfmmp_store_info_address"><i class="wcfmfa fa-phone" aria-hidden="true"></i><span>' . $whatsapp . '</div>';
	}
}

//Register different WA number checkout based on WA number on vendor stores setting
add_action( 'woocommerce_before_thankyou', 'wfcm_add_assets_wa_checkout' );
add_filter( 'woocommerce_thankyou_order_received_text', 'wfcm_wa_thankyou', 10, 2 );

function wfcm_wa_thankyou($title, $order) {
	$data =[];
	$shipping_data =[];
	$judul = 'Thank you for your order.';
    $subtitle = 'Complete your checkout by pressing the Order by WA button below so that the order can be processed by the Seller.';
	
	$mode = ($order->get_billing_address_1() != $order->get_shipping_address_1() || $order->get_billing_first_name() != $order->get_shipping_first_name())?'shipping':'billing';
	//$mode = 'shipping'; //force shipping mode
	$country =  WC()->countries->countries[ $order->{"get_".$mode."_country"}() ];
	$states = WC()->countries->get_states( $order->{"get_".$mode."_country"}() );
	$province =  $states[ $order->{"get_".$mode."_state"}() ];
	$shipping_method_title = $order->get_shipping_method();
	foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
		$found=false;
		foreach($shipping_item_obj->get_meta_data() as $i=>$val){
			$d = $val->get_data();
			if($d['key']=='vendor_id'){
				$shipping_data[$d['value']] = [
					'title'=>$shipping_item_obj->get_method_title(),
					'total'=>$shipping_item_obj->get_total(),
				];
				$found = true;
				break;
			}
			if(!$found){
				$shipping_data[0] = [
					'title'=>$shipping_item_obj->get_method_title(),
					'total'=>$shipping_item_obj->get_total(),
				];
			}
		}
	}

	foreach($order->get_items() as $item){
		$vendor_id = $item->get_meta('_vendor_id');
		if(!isset($data[$vendor_id])){
			$vendor_data = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
			$whatsapp_show = isset( $vendor_data['store_whatsapp_show'] ) ? $vendor_data['store_whatsapp_show'] : 'no';
			$whatsapp = isset( $vendor_data['store_whatsapp_number'] ) ? $vendor_data['store_whatsapp_number'] : null;
			$vendor_name =  get_user_meta( $vendor_id, 'store_name', true );
			if($whatsapp_show!='yes' || empty($whatsapp) ){
				continue;
			}
			$items = $item->get_quantity()."x - *".$item->get_name()."*\n";
	    	$items .= "URL: ".get_permalink( $item->get_product_id() ) ."\n";
			$data[$vendor_id]=[
				'whatsapp'=>$whatsapp,
				'vendor_name'=>$vendor_name,
				'items'=>$items,
				'total'=>$item->get_total(),
			];
		}else{
			$items = $item->get_quantity()."x - *".$item->get_name()."*\n";
	    	$items .= "Tautan: ".get_permalink( $item->get_product_id() ) ."\n";
			$data[$vendor_id]['items'] .= $items;
			$data[$vendor_id]['total'] += $item->get_total();
		}
	}
	
	if(empty($data)){
		return $title;
	}
	//Loop each checkout vendors whatsapp button
	$html ='';
	foreach($data as $vendor_id=>$d){
		$msg = "*Hello, here's my order details:*\n";
    	$msg .= $d['items']."\n";
    	$msg .="*Order Id*: ".$order->get_id()."\n";
    	$msg .="*Total Price*: ".strip_tags(wc_price($d['total']))."\n";
    	$msg .="*Payment Method*: ".$order->get_payment_method_title()."\n";
    	if(isset($shipping_data[$vendor_id])){
    		$msg .="*Shipping Method*: ".$shipping_data[$vendor_id]['title']." ".strip_tags(wc_price($shipping_data[$vendor_id]['total']))."\n\n";
    	}elseif(isset($shipping_data[0])){
    		$msg .="*Shipping Method*: ".$shipping_data[0]['title']." ". strip_tags(wc_price($shipping_data[0]['total']))."\n\n";
    	}
    	
    	$msg .="*Shipping Info*: \n";
    	$msg .="Name: ".$order->{"get_".$mode."_first_name"}()." ".$order->{"get_".$mode."_last_name"}()."\n";
    	$msg .="Address: ".implode(', ',[$order->{"get_".$mode."_address_1"}(),$order->{"get_".$mode."_address_2"}()])."\n";
    	$msg .="City: ".$order->{"get_".$mode."_city"}().", ".$province.", ".$country."\n";
    	$msg .="Zip Code: ".$order->{"get_".$mode."_postcode"}()."\n";
    	if($mode=='shipping'){
    		$email = (isset($order->shipping['email']))?$order->shipping['email']:$order->get_billing_email();
    		$phone = (isset($order->shipping['phone']))?$order->shipping['phone']:$order->get_billing_phone();
    	}else{
    		$email = $order->get_billing_email();
    		$phone = $order->get_billing_phone();
    	}
    	$msg .="Email: ".$email."\n";
    	$msg .="Phone Number: ".$phone."\n";
    	$msg .= "Notes: ".$order->get_customer_note()."\n";
    	$msg .="\n";
    	$msg .="Thank you!\n\n";
    	$msg .= "Server Time: ".get_post_time( 'j-F-Y - H:i', false, $order->get_id(), true );
    	$btn_text ='Send Order by WA to: '.$d['vendor_name'];
    	$html .=  '<a id="sendbtn" href="https://api.whatsapp.com/send?phone='.$d['whatsapp'].'&text='.rawurlencode($msg).'" target="_blank" class="wa-order-thankyou">'.$btn_text.'</a><br>';
	}

	return '<div class="thankyoucustom_wrapper">
                <h1 class="thankyoutitle">'.$judul.'</h1>
                <p class="subtitle">'.$subtitle.'</p>'.
                $html.
            '</div>';
}

function wfcm_add_assets_wa_checkout(){
	wp_register_style( 'wa_checkout_style',  plugin_dir_url( __FILE__ ) . 'style.css' );
	wp_enqueue_style( 'wa_checkout_style' );
}
