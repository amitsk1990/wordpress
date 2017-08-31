<?php
global $current_user; 

if(isset($_GET['refer']) && !empty($_GET['refer']))
{ 
	$url = trim($_GET['refer']);
	$url_query = parse_url($url, PHP_URL_QUERY);
	if (parse_url($url, PHP_URL_QUERY))
	{
		if(isset($url_query) && ($url_query=="lang=fr" || $url_query=="lang=en"))
		{
			$_SESSION['refer'] = $url;
		}
		else
		{
			$_SESSION['refer'] = $url.'&lang='.ICL_LANGUAGE_CODE;
		}
	}
	else
	{
		$_SESSION['refer'] = $url.'?lang='.ICL_LANGUAGE_CODE;
	}
	
}
/*   echo '<pre>';
print_r($_SESSION['refer']); 
echo '</pre>';  die */; 
/* if(!is_user_logged_in() && (isset($_GET['id']) || isset($_GET['token'])) && isset($_GET['lang']))
{	
	$location= site_url('/login/?lang=').$_GET['lang'];
	wp_redirect($location);
	exit;
} */
/*  print_r($_SESSION['refer']); die;  */
if($current_user->ID!=0)
{
	/* include(plugin_dir_path(__FILE__) . "paid-memberships-pro/pages/levels.php"); */
}	
function my_pmpro_pages_shortcode_invoice($content)
{
	ob_start();
	include(plugin_dir_path(__FILE__) . "paid-memberships-pro/pages/invoice.php");
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}
add_shortcode("pmpro_pages_shortcode_invoice", "my_pmpro_pages_shortcode_invoice");
function my_pmpro_pages_shortcode_billing($content)
{
	ob_start();
	include(plugin_dir_path(__FILE__) . "paid-memberships-pro/pages/billing.php");
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}
add_shortcode("pmpro_pages_shortcode_billing", "my_pmpro_pages_shortcode_billing");

function my_pmpro_pages_shortcode_cancel($content)
{
	ob_start();
	include(plugin_dir_path(__FILE__) . "paid-memberships-pro/pages/cancel.php");
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}
add_shortcode("pmpro_pages_shortcode_cancel", "my_pmpro_pages_shortcode_cancel");

function my_pmpro_pages_shortcode_account($content)
{
	ob_start();
	include(plugin_dir_path(__FILE__) . "paid-memberships-pro/pages/account.php");
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}
add_shortcode("pmpro_pages_shortcode_account", "my_pmpro_pages_shortcode_account");

define('PMPRO_FAILED_PAYMENT_LIMIT', 1);

function get_id_by_slug($page_slug) {
	$page = get_page_by_path($page_slug);
	if ($page) {
		return $page->ID;
	} else {
		return null;
	}
} 

add_action('init','response_paypal');
function response_paypal()
{
	global $wpdb;
	if(isset($_POST['payment_status']))
	{						
		if($_POST['payment_status']=='Completed' && isset($_POST['recurring_payment_id']) && isset($_POST['txn_type']))
		{
			$txn_type = pmpro_getParam("txn_type", "POST");
			$subscr_id = pmpro_getParam("subscr_id", "POST");
			$txn_id = pmpro_getParam("txn_id", "POST");
			$item_name = pmpro_getParam("item_name", "POST");
			$item_number = pmpro_getParam("item_number", "POST");
			$initial_payment_status = pmpro_getParam("initial_payment_status", "POST");
			$payment_status = pmpro_getParam("payment_status", "POST");
			$payment_amount = pmpro_getParam("payment_amount", "POST");
			$payment_currency = pmpro_getParam("payment_currency", "POST");
			$receiver_email = pmpro_getParam("receiver_email", "POST");
			$business_email = pmpro_getParam("business", "POST");
			$payer_email = pmpro_getParam("payer_email", "POST");
			$recurring_payment_id = pmpro_getParam("recurring_payment_id", "POST");
			if(empty($subscr_id))
			{
				$subscr_id = $recurring_payment_id;
			}
			
			if($txn_type == "recurring_payment")
			{
				/* $message = "Results: recurring_payment" . print_r($_REQUEST, true );	
				mail("demotester8@yahoo.com","My subject",$message); */
				$last_subscr_order = new MemberOrder();
				if($last_subscr_order->getLastMemberOrderBySubscriptionTransactionID($subscr_id))
				{
					//subscription payment, completed or failure?
					if($_POST['payment_status'] == "Completed")
					{	
						$message = "Results: recurring_payment_Completed" . print_r( $_REQUEST, true );	
						mail("testertech11@gmail.com","My subject",$message);						
						pmpro_ipnSaveOrder($txn_id, $last_subscr_order);						
						exit;
					}
					else
					{	
						$message = "Results: recurring_payment_fail" . print_r( $_REQUEST, true );	
						mail("testertech11@gmail.com","My subject",$message);
						pmpro_ipnFailedPayment($last_subscr_order);
					}
				}
				else
				{
					//ipnlog("ERROR: Couldn't find last order for this recurring payment (" . $subscr_id . ").");
				}
				exit;
			}
			/* $message = "Results: custom code" . print_r( $_REQUEST, true );	
			mail("demotester8@yahoo.com","My subject",$message);  */
			require_once(PMPRO_DIR . "/services/ipnhandler.php");	 
		}  
	}
}
icl_register_string('WP',__('Date Format', 'wpml-string-translation'), get_option('date_format'));
icl_register_string('WP',__('Time Format', 'wpml-string-translation'), get_option('time_format'));
function pmpro_ipnSaveOrder($txn_id, $last_order)
{
	global $wpdb;
	//check that txn_id has not been previously processed
	$old_txn = $wpdb->get_var("SELECT payment_transaction_id FROM $wpdb->pmpro_membership_orders WHERE payment_transaction_id = '" . $txn_id . "' LIMIT 1");

	if(empty($old_txn))
	{
		$amount = $_POST['amount'];
		$recurring_payment_id = $txn_id;
		$owner_id = $last_order->user_id; 
		$datetime = date('Y-m-d H:i:s',current_time('timestamp',true)); 
		$promocode = get_user_meta($owner_id,'owner_promocode',true);
		$rdcode = get_user_meta($owner_id,'owner_rd_code',true);
		if(!empty($promocode) || !empty($rdcode))
		{
			$status = 'success';
			$partner_commision = 0;
			$rd_commision = 0;
			if(!empty($promocode) && !empty($rdcode))
			{
				$partner_commision = $amount*25/100;
				$rd_commision =  $amount*20/100;
			}
			else if(!empty($promocode) && empty($rdcode))
			{
				$partner_commision =  $amount*25/100;			
			}
			else if(!empty($rdcode) && empty($promocode))
			{			
				$rd_commision = $amount*40/100;
			}
			$insert_post = $wpdb->query("insert into sp_commisions(owner_id,owner_promocode,owner_rd_code,package,amount,partner_commision,rd_commision,datetime,recurring_payment_id,status) values($owner_id,'".$promocode."','".$rdcode."','".$last_order->membership_id."',$amount,$partner_commision,$rd_commision,'".$datetime."','".$recurring_payment_id."','".$status."')"); 
		}
		
		if(!empty($owner_id))
		{
			$active_agents = count_active_agents($owner_id);
			$level_id = detect_membership_level($active_agents);
		
			update_user_meta($owner_id,"allow_agents",$active_agents);
			/* change membership level */
			$level_custom_text = get_pmpro_save_membership_level_custom_fields($level_id);
			$get_initial_payment = $level_custom_text['price_per_agent'];
			$initial_payment = ($active_agents*$get_initial_payment);
			$initial_payment = number_format($initial_payment, 2, '.', '');	
			
			if($last_order->membership_id != $level_id)
			{
				$level = pmpro_getLevel($level_id);
				
				$sql_update = "UPDATE $wpdb->pmpro_memberships_users SET `status`='changed', `enddate`='" . current_time('mysql') . "' WHERE `user_id`=".$owner_id." AND status='active'";
				$wpdb->query($sql_update);
				
				$sql = $wpdb->prepare("
						INSERT INTO {$wpdb->pmpro_memberships_users}
						(`user_id`, `membership_id`, `code_id`, `initial_payment`, `billing_amount`, `cycle_number`, `cycle_period`, `billing_limit`, `trial_amount`, `trial_limit`, `startdate`)
						VALUES
						( %d, %d, %d, %s, %s, %d, %s, %d, %s, %d, %s)",
					$owner_id, // integer
					$level_id, // integer
					$level->code_id, // integer
					$initial_payment, // float (string)
					$initial_payment, // float (string)
					$level->cycle_number, // integer
					$level->cycle_period, // string (enum)
					$level->billing_limit, // integer
					$level->trial_amount, // float (string)
					$level->trial_limit, // integer
					current_time('mysql') // string (date)					
				);
				$wpdb->query($sql);
			}
			else
			{
				$sql_update = "UPDATE $wpdb->pmpro_memberships_users SET `billing_amount`=".$initial_payment." WHERE `user_id`=".$owner_id ." AND status='active'";
				$wpdb->query($sql_update);				
			}
		} 
		
		//save order
		$morder = new MemberOrder();
		$morder->user_id = $last_order->user_id;
		/* $morder->membership_id = $last_order->membership_id; */
		$morder->membership_id = $level_id;
		$morder->payment_transaction_id = $txn_id;
		$morder->subscription_transaction_id = $last_order->subscription_transaction_id;
		$morder->gateway = $last_order->gateway;
		$morder->gateway_environment = $last_order->gateway_environment;

		// Payment Status
		$morder->status = 'success'; // We have confirmed that and thats the reason we are here.
		// Payment Type.
		$morder->payment_type = $last_order->payment_type;

		//set amount based on which PayPal type
		if($last_order->gateway == "paypal")
		{
			$morder->InitialPayment = $_POST['amount'];	//not the initial payment, but the class is expecting that
			$morder->PaymentAmount = $_POST['amount'];
		}
		elseif($last_order->gateway == "paypalexpress")
		{
			$morder->InitialPayment = $_POST['amount'];	//not the initial payment, but the class is expecting that
			$morder->PaymentAmount = $_POST['amount'];
		}
		elseif($last_order->gateway == "paypalstandard")
		{
			$morder->InitialPayment = $_POST['mc_gross'];	//not the initial payment, but the class is expecting that
			$morder->PaymentAmount = $_POST['mc_gross'];
		}

		$morder->FirstName = $_POST['first_name'];
		$morder->LastName = $_POST['last_name'];
		$morder->Email = $_POST['payer_email'];

		//get address info if appropriate
		if($last_order->gateway == "paypal")	//website payments pro
		{
			$morder->Address1 = get_user_meta($last_order->user_id, "pmpro_baddress1", true);
			$morder->City = get_user_meta($last_order->user_id, "pmpro_bcity", true);
			$morder->State = get_user_meta($last_order->user_id, "pmpro_bstate", true);
			$morder->CountryCode = "US";
			$morder->Zip = get_user_meta($last_order->user_id, "pmpro_bzip", true);
			$morder->PhoneNumber = get_user_meta($last_order->user_id, "pmpro_bphone", true);

			$morder->billing->name = $_POST['first_name'] . " " . $_POST['last_name'];
			$morder->billing->street = get_user_meta($last_order->user_id, "pmpro_baddress1", true);
			$morder->billing->city = get_user_meta($last_order->user_id, "pmpro_bcity", true);
			$morder->billing->state = get_user_meta($last_order->user_id, "pmpro_bstate", true);
			$morder->billing->zip = get_user_meta($last_order->user_id, "pmpro_bzip", true);
			$morder->billing->country = get_user_meta($last_order->user_id, "pmpro_bcountry", true);
			$morder->billing->phone = get_user_meta($last_order->user_id, "pmpro_bphone", true);

			//get CC info that is on file
			$morder->cardtype = get_user_meta($last_order->user_id, "pmpro_CardType", true);
			$morder->accountnumber = hideCardNumber(get_user_meta($last_order->user_id, "pmpro_AccountNumber", true), false);
			$morder->expirationmonth = get_user_meta($last_order->user_id, "pmpro_ExpirationMonth", true);
			$morder->expirationyear = get_user_meta($last_order->user_id, "pmpro_ExpirationYear", true);
			$morder->ExpirationDate = $morder->expirationmonth . $morder->expirationyear;
			$morder->ExpirationDate_YdashM = $morder->expirationyear . "-" . $morder->expirationmonth;
		}

		//figure out timestamp or default to none (today)
		if(!empty($_POST['payment_date']))
			$morder->timestamp = strtotime($_POST['payment_date']);

		//save
		$morder->saveOrder();
		$morder->getMemberOrderByID($morder->id);
			
		//email the user their invoice
		$pmproemail = new PMProEmail();
		$pmproemail->sendInvoiceEmail(get_userdata($last_order->user_id), $morder);

		//ipnlog("New order (" . $morder->code . ") created.");
	echo 'success';	
		return true;
	}
	else
	{
		//ipnlog("Duplicate Transaction ID: " . $txn_id);
		return false;
	}
}
function pmpro_ipnFailedPayment($last_order)
{
	
	//hook to do other stuff when payments fail
	do_action("pmpro_subscription_payment_failed", $last_order);

	//create a blank order for the email
	$morder = new MemberOrder();
	$morder->user_id = $last_order->user_id;

	//add billing information if appropriate
	if($last_order->gateway == "paypal")		//website payments pro
	{
		$morder->billing->name = $_POST['address_name'];
		$morder->billing->street = $_POST['address_street'];
		$morder->billing->city = $_POST['address_city '];
		$morder->billing->state = $_POST['address_state'];
		$morder->billing->zip = $_POST['address_zip'];
		$morder->billing->country = $_POST['address_country_code'];
		$morder->billing->phone = get_user_meta($morder->user_id, "pmpro_bphone", true);

		//get CC info that is on file
		$morder->cardtype = get_user_meta($morder->user_id, "pmpro_CardType", true);
		$morder->accountnumber = hideCardNumber(get_user_meta($morder->user_id, "pmpro_AccountNumber", true), false);
		$morder->expirationmonth = get_user_meta($morder->user_id, "pmpro_ExpirationMonth", true);
		$morder->expirationyear = get_user_meta($morder->user_id, "pmpro_ExpirationYear", true);
	}

	// Email the user and ask them to update their credit card information
	$pmproemail = new PMProEmail();
	$pmproemail->sendBillingFailureEmail($user, $morder);

	// Email admin so they are aware of the failure
	$pmproemail = new PMProEmail();
	$pmproemail->sendBillingFailureAdminEmail(get_bloginfo("admin_email"), $morder);

	//ipnlog("Payment failed. Emails sent to " . $user->user_email . " and " . get_bloginfo("admin_email") . ".");

	return true;
}

add_action('pmpro_after_checkout', 'my_custom_checkout');
function my_custom_checkout($argment){
	global $wpdb;
	$owner_id = $argment;
	$package = $_POST['level'];
	
	$message = "Results: first payment" . print_r( $_POST, true );	
	mail("demotester8@yahoo.com","first payment",$message); 
	$amount_result = $wpdb->get_row('SELECT total,payment_transaction_id FROM sp_pmpro_membership_orders WHERE paypal_token="'.$_POST['token'].'" AND gateway="'.$_POST['gateway'].'" AND status="success"'); 
	$amount = $amount_result->total;
	$recurring_payment_id = $amount_result->payment_transaction_id;
	 
	$datetime = date('Y-m-d H:i:s',current_time('timestamp',true)); 
	$promocode = get_user_meta($owner_id,'owner_promocode',true);
	$rdcode = get_user_meta($owner_id,'owner_rd_code',true);
	if(!empty($promocode) || !empty($rdcode))
	{
		$status = 'success';
		$partner_commision = 0;
		$rd_commision = 0;
		if(!empty($promocode) && !empty($rdcode))
		{
			$partner_commision = $amount*25/100;
			$rd_commision =  $amount*20/100;
		}
		else if(!empty($promocode) && empty($rdcode))
		{
			$partner_commision =  $amount*25/100;			
		}
		else if(!empty($rdcode) && empty($promocode))
		{			
			$rd_commision = $amount*40/100;
		}
		$insert_post = $wpdb->query("insert into sp_commisions(owner_id,owner_promocode,owner_rd_code,package,amount,partner_commision,rd_commision,datetime,recurring_payment_id,status) values($owner_id,'".$promocode."','".$rdcode."','".$package."',$amount,$partner_commision,$rd_commision,'".$datetime."','".$recurring_payment_id."','".$status."')"); 
	}
}
	register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'FinancementSP' ),
		'footer' =>  __( 'Footer Menu',  'FinancementSP' ),
		'left-header' =>  __( 'Left header Menu',  'FinancementSP' ),
		'right-header' =>  __( 'Right header Menu',  'FinancementSP' ),
	));

	/* function twentyfifteen_scripts() {
		
		
		
	}
	add_action( 'wp_enqueue_scripts', 'twentyfifteen_scripts' ); */

class wp_bootstrap_navwalker extends Walker_Nav_Menu {
    /**
     * @see Walker::start_lvl()
     * @since 3.0.0
     *
     * @param string $output Passed by reference. Used to append additional content.
     * @param int $depth Depth of page. Used for padding.
     */
    public function start_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "\n$indent<ul role=\"menu\" class=\" dropdown-menu\">\n";
    }
    /**
     * @see Walker::start_el()
     * @since 3.0.0
     *
     * @param string $output Passed by reference. Used to append additional content.
     * @param object $item Menu item data object.
     * @param int $depth Depth of menu item. Used for padding.
     * @param int $current_page Menu item ID.+3
     * @param object $args
     */
    public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
        $indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';
        /**
         * Dividers, Headers or Disabled
         * =============================
         * Determine whether the item is a Divider, Header, Disabled or regular
         * menu item. To prevent errors we use the strcasecmp() function to so a
         * comparison that is not case sensitive. The strcasecmp() function returns
         * a 0 if the strings are equal.
         */
        if ( strcasecmp( $item->attr_title, 'divider' ) == 0 && $depth === 1 ) {
            $output .= $indent . '<li role="presentation" class="divider">';
        } else if ( strcasecmp( $item->title, 'divider') == 0 && $depth === 1 ) {
            $output .= $indent . '<li role="presentation" class="divider">';
        } else if ( strcasecmp( $item->attr_title, 'dropdown-header') == 0 && $depth === 1 ) {
            $output .= $indent . '<li role="presentation" class="dropdown-header">' . esc_attr( $item->title );
        } else if ( strcasecmp($item->attr_title, 'disabled' ) == 0 ) {
            $output .= $indent . '<li role="presentation" class="disabled"><a href="#">' . esc_attr( $item->title ) . '</a>';
        } else {
            $class_names = $value = '';
            $classes = empty( $item->classes ) ? array() : (array) $item->classes;
            $classes[] = 'menu-item-' . $item->ID;
            $class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
            if ( $args->has_children )
                $class_names .= ' dropdown';
            if ( in_array( 'current-menu-item', $classes ) )
                $class_names .= ' active';
            $class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';
            $id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args );
            $id = $id ? ' id="' . esc_attr( $id ) . '"' : '';
            $output .= $indent . '<li' . $id . $value . $class_names .'>';
            $atts = array();
            $atts['title'] = ! empty( $item->title ) ? $item->title	: '';
            $atts['target'] = ! empty( $item->target ) ? $item->target	: '';
            $atts['rel'] = ! empty( $item->xfn ) ? $item->xfn	: '';
			// If item has_children add atts to a.
            if ( $args->has_children && $depth === 0 ) {
                $atts['href'] = '#';
                $atts['data-toggle'] = 'dropdown';
                $atts['class'] = 'dropdown-toggle';
                $atts['aria-haspopup'] = 'true';
            } else {
                $atts['href'] = ! empty( $item->url ) ? $item->url : '';
            }
            $atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args );
            $attributes = '';
            foreach ( $atts as $attr => $value ) {
                if ( ! empty( $value ) ) {
                    $value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
                    $attributes .= ' ' . $attr . '="' . $value . '"';
                }
            }
            $item_output = $args->before;
            /*
            * Glyphicons
            * ===========
            * Since the the menu item is NOT a Divider or Header we check the see
            * if there is a value in the attr_title property. If the attr_title
            * property is NOT null we apply it as the class name for the glyphicon.
            */
            if ( ! empty( $item->attr_title ) )
            {
                $item_output .= '<a'. $attributes .'><span class="glyphicon ' . esc_attr( $item->attr_title ) . '"></span>&nbsp;';
            }
            else
            {    
                $item_output .= '<a'. $attributes .'>';
            $item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
            $item_output .= ( $args->has_children && 0 === $depth ) ? ' <span class="caret"></span></a>' : '</a>';
            $item_output .= $args->after;
            $output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
            }
            
            }
    }
     public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {
        if ( ! $element )
            return;
        $id_field = $this->db_fields['id'];
		if ( is_object( $args[0] ) )
            $args[0]->has_children = ! empty( $children_elements[ $element->$id_field ] );
        parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
    }
	public static function fallback( $args ) {
        if ( current_user_can( 'manage_options' ) ) {
            extract( $args );
            $fb_output = null;
            if ( $container ) {
                $fb_output = '<' . $container;
                if ( $container_id )
                    $fb_output .= ' id="' . $container_id . '"';
                if ( $container_class )
                    $fb_output .= ' class="' . $container_class . '"';
                $fb_output .= '>';
            }
            $fb_output .= '<ul';
            if ( $menu_id )
                $fb_output .= ' id="' . $menu_id . '"';
            if ( $menu_class )
                $fb_output .= ' class="' . $menu_class . '"';
            $fb_output .= '>';
            $fb_output .= '<li><a href="' . admin_url( 'nav-menus.php' ) . '">Add a menu</a></li>';
            $fb_output .= '</ul>';
            if ( $container )
                $fb_output .= '</' . $container . '>';
            echo $fb_output;
        }
    }
}
add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar() {
	show_admin_bar(false);
}

add_action('wp_login', 'check_user', 10, 2);

function check_user($user_login, $user) { 
session_start();

$_SESSION['flogin'] = 1; 
$lang_per = get_user_meta($user->ID,'profile_lang',true);
if(empty(get_user_meta($user->ID,'profile_lang',true)))
{
	$lang_per = 'en';
}
$profile_lang = '?lang='.trim($lang_per); 	
if ( get_user_meta( $user->ID, 'has_to_be_activated', true ) != false ) 
{  
	wp_redirect(get_option('siteurl') . '/login/?disabled=true'.CURRENT_LANG);
	wp_logout();
	$_SESSION['status_val'] = 1;
	exit;
}

else if($user->roles[0]=="owner")
{ 	
	$logged = get_user_meta($user->ID,'first_time_loggedin',true);
	if($logged == 0)
	{
		update_user_meta($user->ID,'first_time_loggedin',1);
	}
	$userMemLevel = pmpro_getMembershipLevelForUser($user->ID);
	
	/* $allow_agents = '';
	if($userMemLevel->ID != 17 && $userMemLevel->ID!=11)
	{ */
		/* if($userMemLevel->ID==11)
		{
			$package = get_user_meta($user->ID,'package',true);
		}	
		else
		{ */
			/* $packargs = array(
					'post_type'  => 'packages',
					'posts_per_page'=>-1,
					'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash')    
				); 
			$pacquery = new WP_Query($packargs);
			if($pacquery->have_posts())
			{ 	
				while($pacquery->have_posts())
				{
					$pacquery->the_post();
					$monthlyLevel = get_post_meta(get_the_ID(),'wpcf-monthly',true);						
					if($userMemLevel->ID == $monthlyLevel)
					{ 
						$package = get_the_ID(); 
						break; 
					}				
				}
				
			} */
		/* } */		
		
		/* $meta_allow_agents = get_user_meta($user->ID,"allow_agents",true);
		if(empty($meta_allow_agents))
		{
			$allow_agents = get_post_meta($package,'wpcf-agent-count',true);
		}
		else
		{
			$allow_agents = $meta_allow_agents;
		}
	}
	else
	{
		$allow_agents = 2000000000000;
	}
		
	$agent_active_query = get_active_agents($user->ID); */
	/* $agent_active_query = new WP_User_Query(
	array(
		'role' => 'agent',
		'orderby' => 'registered',
		'order' => 'DESC',		
		'meta_query' => array(
		'relation' => 'AND',
				array(
					'key'     => 'owner',
					'value'   => $user->ID									
				),
				array(
					'key'     => 'statustype',
					'value'   => 'active'
				)
			)
		) 
	); */				
	/* $activeAgents = count($agent_active_query);		
	$extraAgents = $activeAgents - $allow_agents;
	
	if($activeAgents > $allow_agents)
	{
		if(!empty($agent_active_query))
		{
			$countT = 1;	
			foreach ($agent_active_query as $activeuser ) 
			{
				$code = sha1($activeuser->ID . time());
				add_user_meta($activeuser->ID, 'has_to_be_activated', $code);
				$res = update_user_meta($activeuser->ID, 'statustype', 'deactive');
				if($countT == $extraAgents)
				{						
					break;
				}
				$countT++;
			}
		}
	} 
	wp_reset_query(); */
	
	/* if($userMemLevel->ID == 11)
	{ 
		$current_date = strtotime(date("Y-m-d H:i:s",current_time('timestamp',true)));
		if(intval($current_date) > intval($userMemLevel->enddate))
		{
			$worked = pmpro_changeMembershipLevel(false, $user->ID);								
			if($worked === true)
			{  
				if(!empty($agent_active_query))
				{ 
					foreach ($agent_active_query as $activeuser1 ) 
					{ 
						$code1 = sha1($activeuser1->ID . time());
						add_user_meta($activeuser1->ID, 'has_to_be_activated', $code1);
						$res = update_user_meta($activeuser1->ID, 'statustype', 'deactive');
					}
				}
						
				wp_redirect(site_url('/membership-account/membership-level/').$profile_lang); 
				
				exit();
			} 
		}
		else
		{
			wp_redirect(site_url('/dashboard/'.$profile_lang));
			exit();
		}	
	}
	else if(empty($userMemLevel))
	{ 
		if(!empty($agent_active_query))
		{
			foreach ($agent_active_query as $activeuser1 ) 
			{  
				$code1 = sha1($activeuser1->ID . time());
				add_user_meta($activeuser1->ID, 'has_to_be_activated', $code1);
				$res = update_user_meta($activeuser1->ID, 'statustype', 'deactive');
			}
		}
		$count_agents = count_agents($user->ID);		
		if(!empty($count_agents))
		{
			wp_redirect(site_url('/membership-account/membership-level/').$profile_lang); 
		}
		else
		{
			wp_redirect(site_url('/add-agent/').$profile_lang);
		}
		exit();
	} 
	else
	{
		wp_redirect(site_url('/dashboard/'.$profile_lang));
		exit();
	} */
	
	$count_agents = count_agents($user->ID);		
	if(empty($count_agents) && empty($userMemLevel))
	{
		wp_redirect(site_url('/agent-add/').$profile_lang);
		exit();	
	}
	if(isset($_SESSION['refer']) && !empty($_SESSION['refer']))
	{
		wp_redirect($_SESSION['refer']);			
		exit();
	}
	else
	{
		wp_redirect(site_url('/dashboard/'.$profile_lang));
		exit();
	}	
}
else if($user->roles[0]=="agent")
{
	$owner_id = get_user_meta($user->ID,'owner',true);
	if(!empty($owner_id))
	{
		// The levels to check. 
		$levels = NULL; 
		  
		// The user ID to check. 
		$user_id = $owner_id; 
		  
		// NOTICE! Understand what this does before running. 
		$result = pmpro_hasMembershipLevel($levels, $user_id); 
		if(empty($result))
		{
			wp_redirect(get_option('siteurl') . '/login/?disabled=true'.CURRENT_LANG);
			wp_logout();
			$_SESSION['status_val'] = 1;
			exit;
		}
		
	}
	if(isset($_SESSION['refer']) && !empty($_SESSION['refer']))
	{
		wp_redirect($_SESSION['refer']);			
		exit();
	}
	else
	{
		wp_redirect(site_url('/dashboard/'.$profile_lang));
		exit();
	}
	
}	
else	
{
	wp_redirect(site_url('/dashboard/'.$profile_lang));
	exit();
} 
}
function display_message_usm() {
	
			
	if (isset($_SESSION['status_val']))
	{		
		if ($_SESSION['status_val']==1) 
		{
			global $wpdb;
			$strMessageTable = $wpdb->prefix . 'usm_post_message';
			$arrMessageId = $wpdb->get_results("select id,post_message from $strMessageTable LIMIT 1");
			$message = $arrMessageId[0]->post_message;
			$_SESSION['status_val']=0;
			return $message;
		}
	}
	
}
add_filter('login_message', 'display_message_usm');

function getRandomCode()
{
	global $wpdb;
	while(empty($code))
	{

		$scramble = md5(AUTH_KEY . current_time('timestamp',true) . SECURE_AUTH_KEY);
		$code = substr($scramble, 0, 10); 		
		$code = apply_filters("pmpro_random_code", $code, $this);	//filter
		$check = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_orders WHERE code = '$code' LIMIT 1");
		if($check || is_numeric($code))
		{
			$code = NULL;
			continue;
		}	
	}
	return strtoupper($code);
}	

//add_action( 'user_register', 'myplugin_registration_save', 10, 1 );

function myplugin_registration_save( $user_id ) {
	global $wpdb;
	$user = new WP_User( $user_id );
	
	if (in_array( 'owner', (array) $user->roles ) ) 
	{	
		
		$promocode = esc_sql($_POST['promocode']);
		if(!empty($promocode))
		{
			update_user_meta($user_id, 'promocode', $promocode);	
		}	
		if ( isset( $_POST['package'] ) && !empty($_POST['package']) )
		{
			$hasposts = query_posts('post_type=packages&p='.intval($_POST['package']));
			if(!empty($hasposts))
			{
				update_user_meta($user_id, 'package', intval($_POST['package'])); 	
			}		
		}
		else
		{
			update_user_meta($user_id, 'package', 200);
		}
		
	}	
}
add_action('pmpro_after_checkout','set_owner_role');
function set_owner_role($user_id)
{	
	$user_object = new WP_User($user_id);
	$user_object->set_role('owner');
	myplugin_registration_save($user_id);	
}

add_action( 'admin_init', 'redirect_non_admin_users' );
/**
 * Redirect non-admin users to home page
 *
 * This function is attached to the 'admin_init' action hook.
 */
function redirect_non_admin_users() {
	if ( ! current_user_can( 'manage_options' ) && '/wp-admin/admin-ajax.php' != $_SERVER['PHP_SELF'] ) {
		wp_redirect( home_url() );
		exit;
	}
}

/* Category Name*/
?>
<?php
//add extra fields to category edit form hook
add_action ( 'edit_category_form_fields', 'extra_category_fields');
//add extra fields to category edit form callback function
function extra_category_fields( $tag ) {    //check for existing featured ID
    $t_id = $tag->term_id;
    $cat_meta = get_option("category_$t_id");
?>

<tr class="form-field">
<th scope="row" valign="top"><label for="extra1"><?php _e('extra field'); ?></label></th>
<td>
<input type="text" name="Cat_meta[extra1]" id="Cat_meta[extra1]" size="25" style="width:60%;" value="<?php echo $cat_meta['extra1'] ? $cat_meta['extra1'] : ''; ?>"><br />
            <span class="description"><?php _e('extra field'); ?></span>
        </td>
</tr>

<?php
}
// save extra category extra fields hook
add_action ( 'edited_category', 'save_extra_category_fileds');
   // save extra category extra fields callback function
function save_extra_category_fileds( $term_id ) {
    if ( isset( $_POST['Cat_meta'] ) ) {
        $t_id = $term_id;
        $cat_meta = get_option( "category_$t_id");
        $cat_keys = array_keys($_POST['Cat_meta']);
            foreach ($cat_keys as $key){
            if (isset($_POST['Cat_meta'][$key])){
                $cat_meta[$key] = $_POST['Cat_meta'][$key];
            }
        }
        //save the option array
        update_option( "category_$t_id", $cat_meta );
    }
}

//allow redirection, even if my theme starts to send output to the browser
add_action('init', 'do_output_buffer');
function do_output_buffer() {
        ob_start();
	if(isset($_GET['key']))
	{
		$_SESSION['key_value'] = $_GET['key'];
	}
}

add_filter( 'wp_nav_menu_items', 'my_custom_menu_item',10,2);
function my_custom_menu_item($items,$args)
{ 
	global $current_user;	  	
	  /*print_r('<pre>'); 
	  print_r($args); 
	  print_r('</pre>'); */
	if($args->menu->term_id == 7 || $args->menu->term_id == 136)
		{ 
			$items .=  campaign_lists();
		}  

	if($args->menu->term_id == 117 || $args->menu->term_id == 137)
		{ 
			$items .=  agents_lists();
		}
	return $items;  				
	
}

function campaign_lists()
{
	global $current_user, $wpdb;
	$output='';
	if ($current_user->roles[0] == "owner" || $current_user->roles[0] == "telephonist" || $current_user->roles[0] == "supervisor") {

		if($current_user->roles[0] == "owner"){
			$addedby_id = $current_user->ID;
		} else {
			$addedby_id = get_user_meta($current_user->ID,"owner",true);
		}
		$user_campaign = $wpdb->get_results('SELECT * FROM sp_campaign WHERE added_by="'.$addedby_id.'"');
		
		/*if($current_user->roles[0] == "owner"){
			$user_campaign = $wpdb->get_results('SELECT * FROM sp_campaign WHERE added_by="'.$current_user->ID.'"');	
		} else if($current_user->roles[0] == "telephonist"){
			$user_campaign = $wpdb->get_results('SELECT * FROM sp_campaign WHERE telephonists="'.$current_user->ID.'"');	
		} else if($current_user->roles[0] == "supervisor"){
			$user_campaign = $wpdb->get_results('SELECT * FROM sp_campaign WHERE supervisor="'.$current_user->ID.'"');	
		}*/
		

		$agendacustomclass ='';	
		if(count($user_query->results)>5)
		{
			$agendacustomclass = 'agenda-menu-class';
		}
		if(ICL_LANGUAGE_CODE !='fr')
		{
			$agendaText = 'Campaign';
			$add_campaign_txt = 'Add campaign';
			$add_id = 24313;
			$add_url = get_page_link($add_id);
			$campaign_listing_id = 24323;
		}else{
			$agendaText = "Campagne";
			$add_campaign_txt = 'Ajouter campagne';
			$add_id = 24320;
			$add_url = get_page_link($add_id);	
			$campaign_listing_id = 24308;		
		}		


		$output .= '<li id="nav-menu-item-618" class="'.$agendacustomclass.' main-menu-item  menu-item-even menu-item-depth-0 menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a class="menu-link main-menu-link"><i class="dashicons dashicons-groups"></i> '.$agendaText.' <span class="fa fa-chevron-down"></span></a><ul id="custom-nav" class="nav child_menu">';			
		/*print_r('<pre>');
		print_r($user_campaign);
		print_r('</pre>');*/

		/* Add campaign url */	
		if($current_user->roles[0] == "owner"){
			$output .= '<li class="menu-item" id="menu-item-'.$add_url .'"><a href="'.$add_url.'"><i class="dashicons dashicons-groups" aria-hidden="true"></i> '.ucfirst($add_campaign_txt).'</a></li>';	
		}		

		foreach ($user_campaign as $campaign ){ 	
			if(ICL_LANGUAGE_CODE == 'en'){
				$camp_url = get_page_link($campaign_listing_id).'?camp_id='.base64_encode($campaign->id).CURRENT_LANG;
			} else{
				$camp_url = get_page_link($campaign_listing_id).'&camp_id='.base64_encode($campaign->id);	
			}
			
			$output .= '<li class="menu-item" id="menu-item-'.$camp_url->id .'"><a href="'.$camp_url.'"><i class="_mi _before dashicons dashicons-editor-ul" aria-hidden="true"></i> '.ucfirst($campaign->campaign_name).'</a></li>';				
		}
		return $output .= '</ul></li>';
	}
}

function agents_lists()
{
	global $current_user;
	$output='';
	if ($current_user->roles[0] == "owner" || $current_user->roles[0] == "telephonist" || $current_user->roles[0] == "supervisor") {
		
		if ( in_array( 'supervisor', (array) $current_user->roles ) )
		{
			/* $OwnerID = get_user_meta($current_user->ID,'owner',true);
			$user_query = new WP_User_Query( array( 'role' => 'agent','meta_key' => 'owner', 'meta_value' =>intval($OwnerID)) ); */
			$user_query = get_supervisor_agents();
		}
		else if ( in_array( 'owner', (array) $current_user->roles ) )
		{
			$user_query = new WP_User_Query( array( 'role' => 'agent','meta_key' => 'owner', 'meta_value' =>$current_user->ID) );
		}
		else if(in_array( 'telephonist', (array) $current_user->roles ))
		{
			/* $owner = get_user_meta($current_user->ID,'owner',true);
			$user_query = new WP_User_Query( array( 'role' => 'agent','meta_key' => 'owner', 'meta_value' =>intval($owner)) ); */
			
			$supervisor = get_user_meta($current_user->ID,"supervisor",true);
			$supervisor_Array = array();
			if(is_serialized($supervisor))
			{
				$supervisor_Array = unserialize($supervisor);
			}
			else
			{
				$supervisor_Array = array($supervisor); 
			}
			
			$agendacustomclass ='';	
			
			if(ICL_LANGUAGE_CODE !='fr')
			{
				$agendaText = 'Agenda';
			}
			else
			{
				$agendaText = "Agenda";
			}		

			$output .= '<li id="nav-menu-item-618" class="'.$agendacustomclass.' main-menu-item  menu-item-even menu-item-depth-0 menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a class="menu-link main-menu-link"><i class="dashicons dashicons-calendar-alt"></i> '.$agendaText.' <span class="fa fa-chevron-down"></span></a><ul id="custom-nav" class="nav child_menu">';
			$supAgents = "";
			$agentArray = array();
			if(!empty($supervisor_Array))
			{
				foreach($supervisor_Array as $sps)
				{
					$user_query = get_supervisor_agents($sps);
					if(!empty($user_query->get_results()))
					{
						foreach ($user_query->results as $user ) 
						{ 
							if(!in_array($user->ID,$agentArray))
							{
								$firstname =  get_user_meta($user->ID,'first_name',true);
								$lastname =  get_user_meta($user->ID,'last_name',true);
								if ( get_user_meta( $user->ID, 'has_to_be_activated', true ) == false )
								{ 
									$agenda_url = get_page_link(722).'?userID='.base64_encode($user->ID).CURRENT_LANG;	
									$output .= '<li class="menu-item" id="menu-item-'.$user->ID .'"><a href="'.$agenda_url.'"><i class="dashicons dashicons-calendar"></i> '.$firstname.' '.$lastname.'</a></li>';	
								}
							}
							$agentArray[]=$user->ID;
							
						}
					}
				}
				return $output .= '</ul></li>';
			}
		}
		if($current_user->roles[0] != "telephonist")
		{			
			$agendacustomclass ='';	
			if(count($user_query->results)>5)
			{
				$agendacustomclass = 'agenda-menu-class';
			}
			if(ICL_LANGUAGE_CODE !='fr')
			{
				$agendaText = 'Agenda';
			}
			else
			{
				$agendaText = "Agenda";
			}		

			$output .= '<li id="nav-menu-item-618" class="'.$agendacustomclass.' main-menu-item  menu-item-even menu-item-depth-0 menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children"><a class="menu-link main-menu-link"><i class="dashicons dashicons-calendar-alt"></i> '.$agendaText.' <span class="fa fa-chevron-down"></span></a><ul id="custom-nav" class="nav child_menu">';								
			foreach ($user_query->results as $user )  		{ 
				$firstname =  get_user_meta($user->ID,'first_name',true);
				$lastname =  get_user_meta($user->ID,'last_name',true);
				if ( get_user_meta( $user->ID, 'has_to_be_activated', true ) == false )
				{ 
					$agenda_url = get_page_link(722).'?userID='.base64_encode($user->ID).CURRENT_LANG;	
					$output .= '<li class="menu-item" id="menu-item-'.$user->ID .'"><a href="'.$agenda_url.'"><i class="dashicons dashicons-calendar"></i> '.$firstname.' '.$lastname.'</a></li>';	
				}
			}
			return $output .= '</ul></li>';
		}
	}
}
include("paid-memberships-pro/shortcodes/pmpro_account.php");

add_filter( 'send_password_change_email', '__return_false');

/* Remove WPML language filter when select a terms. */
global $sitepress; 
remove_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'));
remove_filter('get_term', array($sitepress,'get_term_adjust_id'));
remove_filter('terms_clauses', array($sitepress,'terms_clauses'));
/* End of Remove WPML language filter when select a terms. */

function my_pmpro_email_filter($email)
{
/* 
print_r($_REQUEST);
echo $email->template;
  die('working'); */

	global $wpdb,$current_user;
	$replace_data_again = false;
	
	$current_user = wp_get_current_user();
	$user_id=email_exists($email->email);  
	$company_Name = '';
	$sitelogo = '';
	$sitename='';
	$company_N = strtoupper(get_user_meta($user_id,'company_name',true));
	$profile_lang = trim(get_user_meta($user_id,'profile_lang',true));
	if(empty($profile_lang))
	{
		$profile_lang = 'en';
	}	
	if(!empty($company_N))
	{
		$company_Name = strtoupper($company_N);
		$sitelogo = "&nbsp;";
		$sitename = '';
	}
	else
	{
		$company_Name = strtoupper(get_bloginfo("name"));
		$logoImg = RP_URL.'/images/sitelogo.png';
		$sitelogo = '<img style="max-width: 100%;" class="footer-img" src="'.$logoImg.'"/>';
		$sitename = get_bloginfo("name");
	}
	if($email->template == "checkout_express")
	{
		//update subject 
		if($profile_lang == "en")
		{
			$email->subject = "Thank you for your payment.";
		}
		else
		{ 
			$email->subject = "Merci pour votre paiement.";
		}
		//update body !! update this to point to a real email template file
		if(isset($_REQUEST['ap']) && !empty($_REQUEST['ap']))
		{
			$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_express_addagents.html");
		}
		else
		{
			$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_express.html");
		}
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_express_admin")
	{
		//update subject 
		//$email->subject = "Member Checkout for Byte at FollowUpByte";
		//update body !! update this to point to a real email template file
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_express_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "cancel")
	{
		//update subject 
		//$email->subject = "Your membership at FollowUpByte has been CANCELLED";
		//update body !! update this to point to a real email template file
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/cancel.html");
		$replace_data_again = true; 
	}
	else if($email->template == "cancel_admin")
	{
		//update subject 
		//$email->subject = "Your membership at FollowUpByte has been CANCELLED";
		//update body !! update this to point to a real email template file
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/cancel_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "membership_expired")
	{
		$active_agents = count_active_agents($user_id);
		$total_agents = count_agents($user_id);	
		if(!empty($active_agents))
		{
			$membership_level = detect_membership_level($active_agents);
			$level_custom_text = get_pmpro_save_membership_level_custom_fields($membership_level);
			$initial_payment = $level_custom_text['price_per_agent'];
			$bill_amount = ($initial_payment*$active_agents);
			$billing_amount = '$'.number_format($bill_amount, 2, '.', '');
			$agent_modify = site_url("/modify-agents/").'?refer='.site_url("/modify-agents/?lang=".$profile_lang);
			$payment = site_url("/membership-account/membership-checkout/?level=".$membership_level).'&refer='.site_url("/membership-account/membership-checkout/?level=".$membership_level."&lang=".$profile_lang);
			$message = '';
			if($profile_lang == "en")
			{
				$message = "You have $active_agents agents active and the monthly payment will be $billing_amount, In case of any changes click on <br/>
				<a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;margin-right: 10px;' class='button' href='".$agent_modify."'>Modify agent</a>&nbsp;&nbsp;<a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' class='button' href='".$payment."'>Payment</a>";
			}
			else
			{
				$message = "Vous avez $active_agents agents actifs et le paiement mensuel sera de $billing_amount, en cas de changement, cliquez sur <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;margin-right: 10px;' href='".$agent_modify."'>Modifier l'agent</a>&nbsp;&nbsp;<a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' class='button' href='".$payment."'>Paiement</a>";
			}
			$file_contents = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expired_custom.html");
			$file_contents = str_replace("!!message!!",$message,$file_contents);
			$email->body = $file_contents;
			
		}
		else if(empty($total_agents))
		{
			$add_agent = site_url('/agent-add/?lang='.$profile_lang);
			$message = '';
			if($profile_lang == "en")
			{
				$message = "You hasn't added any agent to continue you needs to add agent <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$add_agent."'>Add agent</a>";
			}
			else
			{
				$message = "Vous n'avez ajouté aucun agent pour continuer à ajouter un agent <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$add_agent."'>Ajouter un agent</a>";
			}			
			$file_contents = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expired_custom.html");
			$file_contents = str_replace("!!message!!",$message,$file_contents);
			$email->body = $file_contents;
		}
		else if(empty($active_agents) && !empty($total_agents))
		{
		$agent_modify = site_url("/modify-agents/").'?refer='.site_url("/modify-agents/?lang=".$profile_lang);
			$message = '';
			if($profile_lang == "en")
			{
				$message = "You hasn't activated any agent to continue you need to active agent <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$agent_modify."'>Modify agent</a>";
			}
			else
			{
				$message = "Vous n'avez activé aucun agent pour continuer votre agent actif <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$agent_modify."'>Modifier l'agent</a>";
			}			
			$file_contents = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expired_custom.html");
			$file_contents = str_replace("!!message!!",$message,$file_contents);
			$email->body = $file_contents;
		}
		else
		{
			$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expired.html");
		}		
		$replace_data_again = true; 
	}
	else if($email->template == "membership_expiring")
	{		
		$active_agents = count_active_agents($user_id);
		$total_agents = count_agents($user_id);	
		if(!empty($active_agents))
		{
			$membership_level = detect_membership_level($active_agents);
			$level_custom_text = get_pmpro_save_membership_level_custom_fields($membership_level);
			$initial_payment = $level_custom_text['price_per_agent'];
			$bill_amount = ($initial_payment*$active_agents);
			$billing_amount = '$'.number_format($bill_amount, 2, '.', '');
			$agent_modify = site_url("/modify-agents/").'?refer='.site_url("/modify-agents/?lang=".$profile_lang);
			$payment = site_url("/membership-account/membership-checkout/?level=".$membership_level).'&refer='.site_url("/membership-account/membership-checkout/?level=".$membership_level."?lang=".$profile_lang);
			$message = '';
			if($profile_lang == "en")
			{
				$message = "You have $active_agents agents active and the monthly payment will be $billing_amount, In case of any changes click on <br/>
				<a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;margin-right: 10px;' class='button' href='".$agent_modify."'>Modify agent</a>&nbsp;&nbsp;<a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' class='button' href='".$payment."'>Payment</a>";
				
			}
			else
			{
				$message = "Vous avez $active_agents agents actifs et le paiement mensuel sera de $billing_amount, en cas de changement, cliquez sur <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;margin-right: 10px;' href='".$agent_modify."'>Modifier l'agent</a>&nbsp;&nbsp;<a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' class='button' href='".$payment."'>Paiement</a>";
			}
			$file_contents = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expiring_custom.html");
			$file_contents = str_replace("!!message!!",$message,$file_contents);
			$email->body = $file_contents;
			
		}
		else if(empty($total_agents))
		{
			$add_agent = site_url('/agent-add/?lang='.$profile_lang);
			$message = '';
			if($profile_lang == "en")
			{
				$message = "You hasn't added any agent to continue you needs to add agent <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$add_agent."'>Add agent</a>";
			}
			else
			{
				$message = "Vous n'avez ajouté aucun agent pour continuer à ajouter un agent <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$add_agent."'>Ajouter un agent</a>";
			}			
			$file_contents = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expiring_custom.html");
			$file_contents = str_replace("!!message!!",$message,$file_contents);
			$email->body = $file_contents;
		}
		else if(empty($active_agents) && !empty($total_agents))
		{
		$agent_modify = site_url("/modify-agents/").'?refer='.site_url("/modify-agents/?lang=".$profile_lang);
			$message = '';
			if($profile_lang == "en")
			{
				$message = "You hasn't activated any agent to continue you needs to active agent <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$agent_modify."'>Modify agent</a>";
			}
			else
			{
				$message = "Vous n'avez activé aucun agent pour continuer votre agent actif <a style='background:#33cd99;border:medium none;border-radius: 4px;color: #ffffff;font-size: 15px;padding: 6px 12px;text-align: center;display: inline-table; margin-top: 10px;text-decoration: none;' href='".$agent_modify."'>Modifier l'agent</a>";
			}			
			$file_contents = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expiring_custom.html");
			$file_contents = str_replace("!!message!!",$message,$file_contents);
			$email->body = $file_contents;
		}
		else
		{
			$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/membership_expiring.html");
		}		
		$replace_data_again = true; 
	}
	else if($email->template == "trial_ending")
	{	
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/trial_ending.html");			
		$replace_data_again = true;  
	}
	else if($email->template == "admin_change")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/admin_change.html");
		$replace_data_again = true; 
	}
	else if($email->template == "admin_change_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/admin_change_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "billing")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/billing.html");
		$replace_data_again = true; 
	}
	else if($email->template == "billing_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/billing_admin.html");
		$replace_data_again = true; 
	}	
	else if($email->template == "billing_failure")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/billing_failure.html");
		$replace_data_again = true; 
	}
	else if($email->template == "billing_failure_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/billing_failure_admin.html");
		$replace_data_again = true; 
	}	
	else if($email->template == "checkout_check")
	{
			
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_check.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_check_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_check_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_free")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_free.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_free_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_free_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_freetrial")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_freetrial.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_freetrial_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_freetrial_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_paid")
	{
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_paid.html");		
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_paid_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_paid_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_trial")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_trial.html");
		$replace_data_again = true; 
	}
	else if($email->template == "checkout_trial_admin")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/checkout_trial_admin.html");
		$replace_data_again = true; 
	}
	else if($email->template == "invoice")
	{		
		$email->body = file_get_contents(dirname(__FILE__) . "/paid-memberships-pro/email/".$profile_lang."/invoice.html");
		$replace_data_again = true; 
	}
	if($replace_data_again)
	{
		//replace data
		if(is_string($email->data))
		{
			$email->data = array("body"=>$email->data);
		}
		$email->data['company'] = $company_Name;
		$email->data['sitelogo'] = $sitelogo;
		$email->data['sitename'] = $sitename;
		if(is_array($email->data))
		{
			foreach($email->data as $key => $value)
			{				
				$email->body = str_replace("!!". $key ."!!", $value, $email->body); 
			}
		}	 
	}
	return $email;
}
add_filter("pmpro_email_filter", "my_pmpro_email_filter");

function add_custom_meta_box()
{
    add_meta_box("demo-meta-box", "Status in top?", "custom_meta_box_markup", "followupbytes-update", "side", "high", null);
}

add_action("add_meta_boxes", "add_custom_meta_box");
function custom_meta_box_markup($object)
{
    wp_nonce_field(basename(__FILE__), "meta-box-nonce");

    ?>
        <div>
            <?php
                $checkbox_value = get_post_meta($object->ID, "meta-box-checkbox", true);

                if($checkbox_value == "" and ($checkbox_value!="true"))
                {
                    ?>
                        <input name="meta-box-checkbox" type="checkbox" value="true">
                    <?php
                }
                else if($checkbox_value == "true")
                {
                    ?>  
                        <input name="meta-box-checkbox" type="checkbox" value="true" checked>
                    <?php
                }
            ?>		
            <label for="meta-box-checkbox">Shown in Status content?</label>
			<br />
            <?php
                $formobile_app = get_post_meta($object->ID, "formobile_app", true);

                if($formobile_app == "" and ($formobile_app!="yes"))
                {
                    ?>
                        <input name="formobile_app" type="checkbox" value="yes">
                    <?php
                }
                else if($formobile_app == "yes")
                {
                    ?>  
                        <input name="formobile_app" type="checkbox" value="yes" checked>
                    <?php
                }
            ?>		
            <label for="meta-box-checkbox">For Mobile App?</label>
			<br />
			<label for="meta-box-text">How much completed? </label>
            <input name="meta-box-text" type="text" placeholder="Ex : 100%" value="<?php echo get_post_meta($object->ID, "meta-box-text", true); ?>">

        </div>
    <?php  
}

function save_custom_meta_box($post_id, $post, $update)
{
    if (!isset($_POST["meta-box-nonce"]) || !wp_verify_nonce($_POST["meta-box-nonce"], basename(__FILE__)))
        return $post_id;

    if(!current_user_can("edit_post", $post_id))
        return $post_id;

    if(defined("DOING_AUTOSAVE") && DOING_AUTOSAVE)
        return $post_id;

    $slug = "followupbytes-update";
    if($slug != $post->post_type)
        return $post_id;
    $meta_box_checkbox_value = "";
    $meta_box_text_value = "";
    $formobile_app = "";
	 if(isset($_POST["formobile_app"]))
    {
        $formobile_app = $_POST["formobile_app"];
    }   
    update_post_meta($post_id, "formobile_app", $formobile_app);
	 if(isset($_POST["meta-box-text"]))
    {
        $meta_box_text_value = $_POST["meta-box-text"];
    }   
    update_post_meta($post_id, "meta-box-text", $meta_box_text_value);
    if(isset($_POST["meta-box-checkbox"]))
    {
        $meta_box_checkbox_value = $_POST["meta-box-checkbox"];
    }   
    update_post_meta($post_id, "meta-box-checkbox", $meta_box_checkbox_value);
}

add_action("save_post", "save_custom_meta_box", 10, 3);

add_filter( 'retrieve_password_title','sb_we_lost_password_title', 10, 2 );
function sb_we_lost_password_title($title, $user_id )
{
	$user = get_user_by( 'email', $user_id );
	$_title = '';
	if(ICL_LANGUAGE_CODE == 'en')
	{
		$_title = "Reset your password";
	}
	else
	{
		$_title = "Réinitialisez votre mot de passe";
	}
	
	return empty( $_title ) ? $title : Theme_My_Login_Common::replace_vars( $_title, $user->ID);
}
add_filter( 'retrieve_password_message', 'rv_new_retrieve_password_message', 10, 3 );
function rv_new_retrieve_password_message($message, $key,$user_id)
{	
		$_message = file_get_contents(dirname(__FILE__) . "/theme-my-login/emails/".ICL_LANGUAGE_CODE ."/reset_password.html");
		$lang = ICL_LANGUAGE_CODE;
		if ( ! empty( $_message ) ) { 
			$user = get_user_by( 'email', $user_id );
			/* echo '<pre>';print_r($user);echo '</pre>';  */
			if($user->roles[0] == "owner")
			{
				$CName = get_user_meta($user->data->ID,'company_name',true);
				if(!empty($CName))
				{
					$CName = strtoupper($CName);
					$sitelogo = "&nbsp;";
					$sitename = '';
				}					
				else
				{
					$CName = strtoupper(get_bloginfo("name"));
					$logoImg = RP_URL.'/images/sitelogo.png';
					$sitelogo = '<img style="max-width: 100%;" class="footer-img" src="'.$logoImg.'"/>';
					$sitename = get_bloginfo("name");	
				}
			}
			else if($user->roles[0] == "telephonist" || $user->roles[0] == "agent")
			{
				$UserOwner = get_user_meta($user->data->ID,'owner',true);			
				$CName = get_user_meta($UserOwner,'company_name',true);
				
				if(!empty($CName))
				{
					$CName = strtoupper($CName);
					$sitelogo = "&nbsp;";
					$sitename = '';
					
				}					
				else
				{
					$CName = strtoupper(get_bloginfo("name"));
					$logoImg = RP_URL.'/images/sitelogo.png';
					$sitelogo = '<img style="max-width: 100%;" class="footer-img" src="'.$logoImg.'"/>';
					$sitename = get_bloginfo("name");	
				}
			}
			else
			{
				$CName = strtoupper(get_bloginfo("name"));
				$sitename = get_bloginfo("name");
				$logoImg = RP_URL.'/images/sitelogo.png';
				$sitelogo = '<img style="max-width: 100%;" class="footer-img" src="'.$logoImg.'"/>';			
			}
			
			$message = Theme_My_Login_Common::replace_vars( $_message, $user->ID, array(
				'%loginurl%' => site_url( 'wp-login.php', 'login' ),
				'%siteurl%' => site_url(),
				'%reseturl%' => site_url( "wp-login.php?action=rp&key=$key&lang=$lang&login=" . rawurlencode( $user->data->user_login ), 'login' ),
				'%company%' => $CName,
				'%sitelogo%' => $sitelogo, 
				'%sitename%' => $sitename,
			) );
		}		
		return $message;
}
add_action('validate_password_reset','wdm_validate_password_reset',10,5);

function wdm_validate_password_reset( $errors, $user)
{

    $exp = '/^(?=.*\d)((?=.*[a-z])|(?=.*[A-Z])).{6,32}$/';

    if(strlen($_POST['pass1'])<8){
		if(ICL_LANGUAGE_CODE=="en"){
			$errors->add( 'error',  'The password should be at least 8 characters long.','');
		}else{
			$errors->add( 'error',  "Le mot de passe doit être d'au moins 8 caractères.",'');
		}
	} 
}
function my_text_strings( $translated_text, $text, $domain ) {
	switch ( $translated_text ) {
			case 'The passwords do not match.' :
				$translated_text = _x( get_post_meta(get_the_id(), 'wpcf-password-not-match', true), 'theme-my-login' );
				break;
	}
	return $translated_text;
}
add_filter( 'gettext', 'my_text_strings', 20, 5 );

/*redirect after reset password */
/* function tml_redirect_url( $url, $action ) {
	if ( 'resetpass' == $action )
		$url = get_site_url('/login/?resetpass=complete&lang='.ICL_LANGUAGE_CODE);
	return $url;
}
add_filter( 'tml_redirect_url', 'tml_redirect_url', 10, 2 ); */

// Add specific CSS class by filter


add_filter( 'body_class', 'my_class_names' );
function my_class_names( $classes ) {
	global $current_user;
	// add 'class-name' to the $classes array
	$classes[] = $current_user->roles[0].'-role';
	$classes[] = 'lang-'.ICL_LANGUAGE_CODE;
	// return the $classes array
	return $classes;
}


add_filter( 'nav_menu_link_attributes', 'mywp_contact_menu_atts', 10, 3 );

function mywp_contact_menu_atts( $atts, $item, $args )
{
    // inspect $item, then …
	/* echo"<pre>";
	print_r($item);
	echo"</pre>"; */
	$current_page = get_the_ID();
	if($current_page==5 || $current_page==1231)
	{
		if($item->ID==24225 || $item->ID==24206)
		{
			$atts['id'] = 'home';
		}
		if($item->ID==312 || $item->ID==24387)
		{
			$atts['data-attr-scroll'] = 'video-page';
			$atts['class'] = 'scrollto';
			$atts['id'] = 'video-page';
		}
		else if($item->ID==314 || $item->ID==1216)
		{
			$atts['data-attr-scroll'] = 'hiringeffort';
			$atts['class'] = 'scrollto';
			$atts['id'] = 'hiringeffort';
		}
		else if($item->ID==313 || $item->ID==1215)
		{
			$atts['data-attr-scroll'] = 'packagedata';
			$atts['class'] = 'scrollto';
			$atts['id'] = 'packagedata';
		}
		else if($item->ID==315 || $item->ID==1217)
		{	
			$atts['data-attr-scroll'] = 'contactusdata';
			$atts['class'] = 'scrollto';
			$atts['id'] = 'contactusdata';
		}
		
	}
    return $atts;
}
function twentyfifteen_widgets_init() {
 register_sidebar( array(
  'name'          => __( 'Widget Area', 'twentyfifteen' ),
  'id'            => 'sidebar-1',
  'description'   => __( 'Add widgets here to appear in your sidebar.', 'twentyfifteen' ),
  'before_widget' => '<aside id="%1$s" class="widget %2$s">',
  'after_widget'  => '</aside>',
  'before_title'  => '<h2 class="widget-title">',
  'after_title'   => '</h2>',
 ) );
}
add_action( 'widgets_init', 'twentyfifteen_widgets_init' );



/* subscription */
function my_easymail_texts ( $translated_text, $untranslated_text, $domain ) {
	if ( $untranslated_text === 'Subscribe' && $domain == "alo-easymail" ) {
		return 'Go';
	}
	return $translated_text;
}
add_filter('gettext', 'my_easymail_texts', 20, 3);



/*
Add an attachment to confirmation emails.
*/
// remove wp version param from any enqueued scripts
function vc_remove_wp_ver_css_js( $src ) {
    if ( strpos( $src, 'ver=' ) )
        $src = remove_query_arg( 'ver', $src );
    return $src;
}
add_filter( 'style_loader_src', 'vc_remove_wp_ver_css_js', 9999 );
add_filter( 'script_loader_src', 'vc_remove_wp_ver_css_js', 9999 );
function my_pmpro_email_attachments($attachments, $email)
{
	global $current_user,$wpdb;
	/* echo '<pre>';print_r($email);echo '</pre>'; die; */
	//make sure it's a checkout email (but not the admin one)
	if((strpos($email->template, "checkout_") !== false && strpos($email->template, "admin") === false) || (strpos($email->template, "billing") !== false && strpos($email->template, "admin") === false))
	{
		//make sure attachments is an array
		if(is_array($attachments))
			$attachments = array();
		
		$amount = $email->data['invoice_total']; 
		
		$mem_name = $email->data['membership_level_name'];
		$owner_name = get_user_meta($current_user->ID,'first_name',true).' '.get_user_meta($current_user->ID,'last_name',true);
		$zipcode = get_user_meta($current_user->ID,'zipcode',true);
		$city = get_user_meta($current_user->ID,'city',true);
		$state = get_user_meta($current_user->ID,'city_state',true);
		$country = get_user_meta($current_user->ID,'country',true);
		$address = get_user_meta($current_user->ID,'ownaddress',true);
		$code =  $email->data['invoice_id'];
		$date = date("d/m/Y", strtotime($email->data['invoice_date']));
		$email = $wpdb->get_var("SELECT user_email FROM sp_users WHERE ID=$current_user->ID");
		$company = get_user_meta($current_user->ID,'company',true);
		$phone = get_user_meta($current_user->ID,'phone',true);
		
		$agent_qty = intval($_REQUEST['ap']);		
		 
		$agent_amount = str_replace('&#36;', ' ', $amount);
		$agent_amount = trim($agent_amount);
		$one_agent_amount = floatval($agent_amount)/$agent_qty;
		$one_agent_amount = number_format($one_agent_amount,2,'.','');
		 
		$owner_lange = get_user_meta($current_user->ID,'profile_lang',true);
		$invoicetxt = 'Invoice No';
		$packagetxt = 'Package';
		$quantitytxt = "Quantity";
		$pricetxt = "Price";
		$wevtxt = "Web";
		$agenttext = "Agents";
		if($owner_lange == "fr")
		{
			$invoicetxt = "NumFacture";
			$packagetxt = "Paquet";
			$quantitytxt = "Quantité";
			$pricetxt = "Prix";
			$wevtxt = "Site Internet";
			$agenttext = "Agents";
		}
		if(isset($_REQUEST['ap']) && !empty($_REQUEST['ap']))
		{
			$html = '<div width="100%" class="inner">
						<table cellpadding="0" cellspacing="0" border="0" width="100%">
						<tr>
							<td>
								<table cellpadding="0" cellspacing="0" border="0" width="600">
									<tr>
										<td>
											<!-- Logo Section Starts Here -->
											<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #32CCFE; padding: 0 15px;">
												<tr>
													<td class="spacing" width="600">
														<img width="230" height="45" src="'.site_url().'/wp-content/uploads/2016/03/owl_logo_res.png" alt="Logo Image" style="display: block;" />
													</td>
												</tr>
											</table>
											<!-- Logo Section Ends Here -->
										</td>
									</tr>
									<tr>
										<td>
											<!-- Address and Invoice Section Starts Here -->
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
													<td class="cols" valign="top" width="50%">
														<!-- Address Section Starts Here -->
														<table cellpadding="0" cellspacing="0" border="0" width="100%">
															<tr>
																<td width="50%">
																	<p>'.$company.'</p>
																	<p>'.$address.',</p>
																	<p>'.$city.', '.$state. ', '.$zipcode.', '.$country.'</p>
																	<p>P: '.$phone.'</p>
																</td>
															</tr>
														</table>
														<!-- Address Section Ends Here -->
													</td>
													<td class="cols" valign="top" width="50%">
														<!-- Invoice Section Starts Here -->
														<table cellpadding="0" cellspacing="0" border="0" width="100%">
															<tr>
																<td align="right" width="50%">
																	<p>'.$invoicetxt.': ['.$code.']</p>
																	<p>Date: '.$date.'</p>
																	<p>Email: '.$email.'</p>
																</td>
															</tr>
														</table>
														<!-- Invoice Section Ends Here -->
													</td>
												</tr>
											</table>	
											<!-- Address and Invoice Section Ends Here -->
										</td>
									</tr>
								</table>
							</td>
						</tr>
						</table>
						<table cellpadding="0" cellspacing="0" border="0" width="100%" class="border">
							<tr> 
								<th class="fifth1" align="left" style="padding: 10px;">'.$packagetxt.' </th>
								<th class="fifth1" align="center">Date</th>
								<th class="fifth1" align="center">'.$agenttext.'</th>
								<th class="fifth1" align="center">'.$pricetxt.'</th>
								<th class="fifth1" align="center">Total</th>
							</tr>
							<tr>
								<td width="100" align="left" style="padding: 5px 10px;">'.$mem_name.'</td>
								<td width="100" align="center">'.$date.'</td>
								<td width="100" align="center">'.$agent_qty .'</td>
								<td width="100" align="center">$'.$one_agent_amount.' $USD</td>
								<td width="100" align="center" class="unit">'.$amount.' $USD</td>
							</tr>
								
						</table>
						<div style="float:right;" class="border1" width="218">
						<table width="100%">
							<tr>							
								<td width="100" align="center" style="padding:5px 0px 5px 0px;">TOTAL</td>
								<td width="100" align="center" class="unit" style="padding:5px 0px 5px 0px;">'.$amount.' $USD</td>
							
							</tr>
						</table>
						</div>			
						</div>
						<table cellpadding="0" cellspacing="0" border="0" width="100%" class="outer" width="100%">		
						<tr>
							<td align="center" valign="top">
								<table cellpadding="0" cellspacing="0" border="0" width="100%" class="footer">
									<tr>
										<td align="center">
											<p>7005 Blv Taschereau, Brossard, Quebec, CA 94103</p>
											<div>
												<strong style="color: #32CCFE;">Contact : </strong>+1 (800) 836-0825
												<strong style="color: #32CCFE;">Fax : </strong>418-907-2980
											</div>
											<div>
												<strong style="color: #32CCFE;">@ : </strong><a href="">info@followupbyte.com</a>
												<strong style="color: #32CCFE;">'.$wevtxt.' : </strong><a href="'.site_url().'">www.followupbyte.com</a>
											</div>
										</td>
									</tr>
								</table>
							</td>
						</tr>	
						</table>
					';
		}
		else
		{
			$html = '<div width="100%" class="inner">
						<table cellpadding="0" cellspacing="0" border="0" width="100%">
						<tr>
							<td>
								<table cellpadding="0" cellspacing="0" border="0" width="600">
									<tr>
										<td>
											<!-- Logo Section Starts Here -->
											<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #32CCFE; padding: 0 15px;">
												<tr>
													<td class="spacing" width="600">
														<img width="230" height="45" src="'.site_url().'/wp-content/uploads/2016/03/owl_logo_res.png" alt="Logo Image" style="display: block;" />
													</td>
												</tr>
											</table>
											<!-- Logo Section Ends Here -->
										</td>
									</tr>
									<tr>
										<td>
											<!-- Address and Invoice Section Starts Here -->
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
													<td class="cols" valign="top" width="50%">
														<!-- Address Section Starts Here -->
														<table cellpadding="0" cellspacing="0" border="0" width="100%">
															<tr>
																<td width="50%">
																	<p>'.$company.'</p>
																	<p>'.$address.',</p>
																	<p>'.$city.', '.$state. ', '.$zipcode.', '.$country.'</p>
																	<p>P: '.$phone.'</p>
																</td>
															</tr>
														</table>
														<!-- Address Section Ends Here -->
													</td>
													<td class="cols" valign="top" width="50%">
														<!-- Invoice Section Starts Here -->
														<table cellpadding="0" cellspacing="0" border="0" width="100%">
															<tr>
																<td align="right" width="50%">
																	<p>'.$invoicetxt.': ['.$code.']</p>
																	<p>Date: '.$date.'</p>
																	<p>Email: '.$email.'</p>
																</td>
															</tr>
														</table>
														<!-- Invoice Section Ends Here -->
													</td>
												</tr>
											</table>	
											<!-- Address and Invoice Section Ends Here -->
										</td>
									</tr>
								</table>
							</td>
						</tr>
						</table>
						<table cellpadding="0" cellspacing="0" border="0" width="100%" class="border">
							<tr> 
								<th class="fifth1" align="left" style="padding: 10px;">'.$packagetxt.' </th>
								<th class="fifth1" align="center">Date</th>
								<th class="fifth1" align="center">'.$quantitytxt.'</th>
								<th class="fifth1" align="center">'.$pricetxt.'</th>
								<th class="fifth1" align="center">Total</th>
							</tr>
							<tr>
								<td width="100" align="left" style="padding: 5px 10px;">'.$mem_name.'</td>
								<td width="100" align="center">'.$date.'</td>
								<td width="100" align="center">1</td>
								<td width="100" align="center">'.$amount.' $USD</td>
								<td width="100" align="center" class="unit">'.$amount.' $USD</td>
							</tr>
								
						</table>
						<div style="float:right;" class="border1" width="218">
						<table width="100%">
							<tr>							
								<td width="100" align="center" style="padding:5px 0px 5px 0px;">TOTAL</td>
								<td width="100" align="center" class="unit" style="padding:5px 0px 5px 0px;">'.$amount.' $USD</td>
							
							</tr>
						</table>
						</div>			
						</div>
						<table cellpadding="0" cellspacing="0" border="0" width="100%" class="outer" width="100%">		
						<tr>
							<td align="center" valign="top">
								<table cellpadding="0" cellspacing="0" border="0" width="100%" class="footer">
									<tr>
										<td align="center">
											<p>7005 Blv Taschereau, Brossard, Quebec, CA 94103</p>
											<div>
												<strong style="color: #32CCFE;">Contact : </strong>+1 (800) 836-0825
												<strong style="color: #32CCFE;">Fax : </strong>418-907-2980
											</div>
											<div>
												<strong style="color: #32CCFE;">@ : </strong><a href="">info@followupbyte.com</a>
												<strong style="color: #32CCFE;">'.$wevtxt.' : </strong><a href="'.site_url().'">www.followupbyte.com</a>
											</div>
										</td>
									</tr>
								</table>
							</td>
						</tr>	
						</table>
					';
		}
			//==============================================================
			//==============================================================
			//==============================================================
			
			require(ABSPATH . "/MPDF57/mpdf.php"); 

			$mpdf=new mPDF('c','A4','','',32,25,20,25,16,13); 

			$mpdf->SetDisplayMode('fullpage');

			$mpdf->list_indent_first_level = 0;	// 1 or 0 - whether to indent the first level of a list

			// LOAD a stylesheet
			$stylesheet = file_get_contents('MPDF57/invoice.css');
			$mpdf->WriteHTML($stylesheet,1);	// The parameter 1 tells that this is css/style only and no body/html/text

			$mpdf->WriteHTML($html,2);

			/* I D F S

			I - send the file inline to browser
			D - download
			F - save
			S - return as a string

			*/
			$file_name = 'invoice_'.$code.'.pdf';
			$mpdf->Output('invoice/'.$file_name,'F');
			
		
		//add our attachment
		$attachments[] = ABSPATH . "/invoice/".$file_name;
	}
	
	return $attachments;
}
function site_time()
{
 return current_time('timestamp',false);
}
add_filter('pmpro_email_attachments', 'my_pmpro_email_attachments', 10, 2);

/*
	Tweak the checkout page when ap is passed in.
*/
//update the level cost
function pmproap_pmpro_checkout_level($level)
{
	global $current_user;

    if (!isset($level->id)) {
        return $level;
    }
	
	/* $package_id = get_user_meta($current_user->ID,'package',true);
	$initial_payment = get_post_meta($package_id,'wpcf-price-for-membership',true);
	$package_allow_agents = intval(get_post_meta($package_id,'wpcf-agent-count',true)); */
	

	$level_custom_text = get_pmpro_save_membership_level_custom_fields($level->id);
	$initial_payment = $level_custom_text['price_per_agent'];
	$allow_agents = $level_custom_text['allow_agents']; 
	$total_agents = count_active_agents();	
	$userMemLevel = pmpro_getMembershipLevelForUser($current_user->ID);
	/* echo '<pre>';
	print_r($userMemLevel);
	echo '</pre>'; */
    //are we purchasing a post?
    if (isset($_REQUEST['ap']) && !empty($_REQUEST['ap']) && !empty($userMemLevel)) 
	{
        $ap = intval($_REQUEST['ap']);
               		
		/* calucaltion */				
			
			$additional_agents = $ap;
			$initial_payment = floatval($initial_payment);
			if($userMemLevel->cycle_period == "Month")
			{
				$curent_month_days = cal_days_in_month(CAL_GREGORIAN,date("m"),date("Y"));
			}
			else 
			{
				$curent_month_days = $userMemLevel->cycle_number;
			}
			$one_day_price = $initial_payment/$curent_month_days;
			/* $round_one_day_price = number_format($one_day_price, 2, '.', ''); */			
			
			$current_date = date("Y-m-d",site_time());
			/* $next_payment_date = explode("T",my_getNextPaymentDate()); */
			
			$next_payment_date = date("Y-m-d",pmpro_next_payment($current_user->ID));
			
			$date1=date_create($current_date);
			$date2=date_create($next_payment_date);
			$diff=date_diff($date1,$date2);
			$remaining_days = $diff->format("%a");
			
		 	$one_agent_price = $remaining_days*$one_day_price;
			/* if($current_user->membership_level->cycle_period == "Day")
			{
				$one_agent_price = $initial_payment;
			} */
			$round_one_agent_price = number_format($one_agent_price, 2, '.', ''); 							
			
			$total_additional_agents_amount = ($additional_agents * $one_agent_price);
			 
			$next_payment_amount = ($total_agents+$additional_agents)*$initial_payment;
			$pmproap_price = number_format($total_additional_agents_amount, 2, '.', '');
			$round_next_payment_amount = number_format($next_payment_amount, 2, '.', ''); 
			
		/* calucaltion */

        if (!empty($pmproap_price)) {
            if ($level->id) {
                //already have the membership, so price is just the ap price
                $level->initial_payment = $pmproap_price;

                //zero the rest out
                $level->billing_amount = $round_next_payment_amount; 
                /* $level->billing_amount = 0; */
				
				 //don't unsubscribe to the old level after checkout
                /* if (!function_exists("pmproap_pmpro_cancel_previous_subscriptions")) {
                    function pmproap_pmpro_cancel_previous_subscriptions($cancel)
                    {
                        return false;
                    }
                }
                add_filter("pmpro_cancel_previous_subscriptions", "pmproap_pmpro_cancel_previous_subscriptions"); */
				
               
	            //keep current enddate
	           /*  if (!function_exists("pmproap_pmpro_checkout_end_date")) {
		            function pmproap_pmpro_checkout_end_date($enddate, $user_id, $pmpro_level, $startdate)
		            {						
			            $user_level = pmpro_getMembershipLevelForUser($user_id);
						if(!empty($user_level) && !empty($user_level->enddate) && $user->enddate != '0000-00-00 00:00:00')
							return date_i18n('Y-m-d H:i:s', $user_level->enddate);
						else							
							return $enddate;
		            }
	            }
	            add_filter("pmpro_checkout_end_date", "pmproap_pmpro_checkout_end_date", 10, 4);  */
				
				//give the owner access to the add more agent after checkout
				if (!function_exists("pmproap_pmpro_after_checkout")) {
					function pmproap_pmpro_after_checkout($user_id)
					{
						 if (!empty($_SESSION['ap'])) {
                        $pmproap_ap = intval($_SESSION['ap']);
                        unsset($_SESSION['ap']);
						} elseif (!empty($_REQUEST['ap'])) {
							$pmproap_ap = intval($_REQUEST['ap']);
						}
						if (!empty($pmproap_ap)) {							
							$meta_allow_agents = get_user_meta($user_id,"allow_agents",true);
							$total_agents = count_active_agents();
							$total_allow_agents = $total_agents + $pmproap_ap;
							update_user_meta($user_id,"allow_agents",$total_allow_agents);
						}
					}
				}
				add_action("pmpro_after_checkout", "pmproap_pmpro_after_checkout");

            } 

            //add hidden input to carry ap value
             if (!function_exists("pmproap_pmpro_checkout_boxes")) {
                function pmproap_pmpro_checkout_boxes()
                {
                    if (!empty($_REQUEST['ap'])) {
                        ?>
                        <input type="hidden" name="ap" value="<?php echo esc_attr($_REQUEST['ap']); ?>"/>
                        <?php
                    }
                }
            }
            add_action("pmpro_checkout_boxes", "pmproap_pmpro_checkout_boxes");
			add_filter("pmpro_profile_start_date", "my_pmpro_profile_start_date", 10, 2); 
			add_filter("pmpro_paypal_express_return_url_parameters", "pmproap_pmpro_paypal_express_return_url_parameters");
            
        } else {
            //woah, they passed a post id that isn't locked down
        }
    }
	else if (isset($_REQUEST['ap']) && !empty($_REQUEST['ap']) && empty($userMemLevel)) 
	{
        $ap = intval($_REQUEST['ap']);
               		
		/* calucaltion */				
			
			$additional_agents = $ap;
			$initial_payment = floatval($initial_payment);
						 
			$next_payment_amount = ($additional_agents)*$initial_payment;			
			$round_next_payment_amount = number_format($next_payment_amount, 2, '.', ''); 
			
		/* calucaltion */

        if (!empty($round_next_payment_amount)) {
            if ($level->id) {
                //already have the membership, so price is just the ap price
                $level->initial_payment = $round_next_payment_amount;

                //zero the rest out
                $level->billing_amount = $round_next_payment_amount;
				
				//give the owner access to the add more agent after checkout
				if (!function_exists("pmproap_pmpro_after_checkout")) {
					function pmproap_pmpro_after_checkout($user_id)
					{
						 if (!empty($_SESSION['ap'])) {
                        $pmproap_ap = intval($_SESSION['ap']);
                        unsset($_SESSION['ap']);
						} elseif (!empty($_REQUEST['ap'])) {
							$pmproap_ap = intval($_REQUEST['ap']);
						}
						if (!empty($pmproap_ap)) {							
							$meta_allow_agents = get_user_meta($user_id,"allow_agents",true);
							$total_agents = count_active_agents();
							$total_allow_agents = $total_agents + $pmproap_ap;							
							update_user_meta($user_id,"allow_agents",$total_allow_agents);							
						}
					}
				}
				add_action("pmpro_after_checkout", "pmproap_pmpro_after_checkout");

            } 

            //add hidden input to carry ap value
             if (!function_exists("pmproap_pmpro_checkout_boxes")) {
                function pmproap_pmpro_checkout_boxes()
                {
                    if (!empty($_REQUEST['ap'])) {
                        ?>
                        <input type="hidden" name="ap" value="<?php echo esc_attr($_REQUEST['ap']); ?>"/>
                        <?php
                    }
                }
            }
            add_action("pmpro_checkout_boxes", "pmproap_pmpro_checkout_boxes");
			/* add_filter("pmpro_profile_start_date", "my_pmpro_profile_start_date", 10, 2); */ 
			add_filter("pmpro_paypal_express_return_url_parameters", "pmproap_pmpro_paypal_express_return_url_parameters");
            
        } 
    }
	else
	{
		$agents = $total_agents;
		if(!empty($agents))
		{
			$membership_level = detect_membership_level($agents);
			
			$level_custom_text = get_pmpro_save_membership_level_custom_fields($membership_level);
			$initial_payment = $level_custom_text['price_per_agent'];	
			
			$total_agents_amount = ($agents * $initial_payment);		
			$billing_amount = number_format($total_agents_amount, 2, '.', '');
			$level->initial_payment = $billing_amount;
			$level->billing_amount = $billing_amount;
		}
		/* if(empty($current_user->data->membership_levels))
		{
			if(empty($agents))
			{
				$all_agents = count_agents();
				if(!empty($all_agents))
				{
					$agents = $all_agents;
					$membership_level = detect_membership_level($agents);
					$level_custom_text = get_pmpro_save_membership_level_custom_fields($membership_level);
					$initial_payment = $level_custom_text['price_per_agent'];	
					
					$total_agents_amount = ($agents * $initial_payment);		
					$billing_amount = number_format($total_agents_amount, 2, '.', '');
					$level->initial_payment = $billing_amount;
					$level->billing_amount = $billing_amount;
				}	
			}
		} */
		
		 //add hidden input to carry l value
             if (!function_exists("pmproap_pmpro_checkout_boxes1")) {
                function pmproap_pmpro_checkout_boxes1()
                {
                    if (!empty($_REQUEST['l'])) {
                        ?>
                        <input type="hidden" name="l" value="<?php echo esc_attr($_REQUEST['l']); ?>"/>
                        <?php
                    }
                }
            }
            add_action("pmpro_checkout_boxes", "pmproap_pmpro_checkout_boxes1");
		
		
		add_filter("pmpro_paypal_express_return_url_parameters", "pmproap_paypal_express_return_url_parameters");
		if (!function_exists("pmproap_pmpro_after_checkout1")) 
		{
			function pmproap_pmpro_after_checkout1($user_id)
			{
				 if (!empty($_SESSION['l'])) {
				$pmproap_ap = intval($_SESSION['l']);
				unsset($_SESSION['l']);
				} elseif (!empty($_REQUEST['l'])) {
					$pmproap_ap = intval($_REQUEST['l']);
				}	
				if(isset($pmproap_ap))
				{
					$meta_allow_agents = get_user_meta($user_id,"allow_agents",true);
					$total_agents = count_active_agents();
					$total_allow_agents = $total_agents;
					update_user_meta($user_id,"allow_agents",$total_allow_agents);	
				}							
			}
		}		
		add_action("pmpro_after_checkout", "pmproap_pmpro_after_checkout1");
		
		/* $level_id = intval($_REQUEST['level']);
		if($level_id > 0)
		{
			$level_custom_fields = get_pmpro_save_membership_level_custom_fields($level_id);
			$package_allow_agents = $level_custom_fields['allow_agents'];	
			$per_agent_price = $level_custom_fields['price_per_agent']; 	
			$added_agents = count_active_agents();		
			
			$payall = @$_REQUEST['payall'];		
			if(($added_agents > $package_allow_agents) && $current_user->membership_level->ID == $level_id)
			{
				//already have the membership, so price is just the ap price
				$billing_amount = ($per_agent_price*$added_agents);
				$level->initial_payment = $billing_amount;
				$level->billing_amount = $billing_amount;
			}
			else if(($added_agents > $package_allow_agents) && $current_user->membership_level->ID != $level_id && $payall == 1)
			{
				//already have the membership, so price is just the ap price
				$billing_amount = ($per_agent_price*$added_agents); 
				$level->initial_payment = $billing_amount;
				$level->billing_amount = $billing_amount;
				add_filter("pmpro_paypal_express_return_url_parameters", "pmproap_paypal_express_return_url_parameters");
			}
			
			if(isset($payall) && $payall == 1)
			{  echo 'working'; 
				if (!function_exists("pmproap_my_after_checkout")) 
				{  echo 'working'; die;
					function pmproap_my_after_checkout($user_id)
					{
						 if (!empty($_SESSION['payall'])) {
						$pmproap_ap = intval($_SESSION['payall']);
						unsset($_SESSION['payall']);
						} elseif (!empty($_REQUEST['payall'])) { 
							$pmproap_ap = intval($_REQUEST['payall']);
						}
						 echo $_REQUEST['payall']; 
						if (!empty($pmproap_ap)) {						
							
							if($added_agents > $package_allow_agents)
							{ 
								update_user_meta($user_id,"allow_agents",$added_agents);
							}							
						}
					}
				}
				add_action("pmpro_after_checkout", "pmproap_my_after_checkout");
			}
		} */		
	} 
    return $level;
}

add_filter("pmpro_checkout_level", "pmproap_pmpro_checkout_level");


/*
	Add ap to PayPal Express return url parameters
*/
function pmproap_pmpro_paypal_express_return_url_parameters($params)
{
	if (!empty($_REQUEST['ap'])) {
		$params["ap"] = isset($_REQUEST['ap']) ? intval($_REQUEST['ap']) : null;
	}
	return $params;
}

/* Add payall to PayPal Express return url parameters*/
function pmproap_paypal_express_return_url_parameters($params)
{
	$activeAgents = count_active_agents();	
	$params["l"] = isset($activeAgents) ? intval($activeAgents) : null;	
	return $params;
}

//if checking out for the same level, set the subscription date to the next payment date
function my_pmpro_profile_start_date($date, $order)
{		
	//if the user has an existing membership level
	global $current_user, $wpdb;
	if(pmpro_hasMembershipLevel($order->membership_id))
	{			
		//get the date of their last order
		$subscription_start_date = $wpdb->get_var("SELECT startdate FROM $wpdb->pmpro_memberships_users WHERE user_id = '" . $current_user->ID . "' LIMIT 1");
		if(!empty($subscription_start_date))
		{			
			/* $date = my_getNextPaymentDate();  */
			$date = date("Y-m-d",pmpro_next_payment($current_user->ID))."T0:0:0"; 
		}			
	}	

	return $date;
}

function my_getNextPaymentDate($format = "Y-m-d")
{
	 global $wpdb, $current_user;

	//get what day are their payments on?
	$subscription_start_date = $wpdb->get_var("SELECT startdate FROM $wpdb->pmpro_memberships_users WHERE user_id = '" . $current_user->ID . "' AND status='active' LIMIT 1");
	$subscription_day = date("d", strtotime($subscription_start_date));
	
	//next month or next year
	if($current_user->membership_level->cycle_period == "Month")
	{
		//next month
		$next_month = strtotime(" + 1 Month");
		$days_in_next_month = date("t", $next_month);
		if($days_in_next_month > $subscription_day)
			$date = date("Y-m", $next_month) . "-" . $subscription_day  . "T0:0:0";
		else
			$date = date("Y-m", $next_month) . "-" . $days_in_next_month  . "T0:0:0";
	}	
	else
	{
		//next year
		$next_year = strtotime(" + 1 Year");
		$days_in_that_month = date("t", $next_year);
		if($days_in_that_month > $subscription_day)
			$date = date("Y-m", $next_year) . "-" . $subscription_day  . "T0:0:0";
		else
			$date = date("Y-m", $next_year) . "-" . $days_in_that_month  . "T0:0:0";
	}	 
	
	if($format == "Y-m-d")		
		return $date;
	elseif($format == "timestamp")
		return strtotime($date);
	else 
		return date($format, strtotime($date)); 
}



/* add custom field in pmpro membership level edit page */

function pclct_pmpro_membership_level_after_other_settings()
{
	$level_id = intval($_REQUEST['edit']);
	
if($level_id > 0)
		$level_custom_text = get_pmpro_save_membership_level_custom_fields($level_id);	
	else
		$level_custom_text = "";
?>
<h3 class="topborder">Custom field</h3>
<table>
<tbody class="form-table">
	<tr>
		<td>
			<tr>
				<th scope="row" valign="top"><label for="level_cost_text">Allow agents:</label></th>
				<td>
					<input type="text" name="allow_agents" value="<?php echo $level_custom_text['allow_agents']; ?>"/>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="level_cost_text">Price per agent:</label></th>
				<td>
					<input type="text" name="price_per_agent" value="<?php echo $level_custom_text['price_per_agent']; ?>"/>
				</td>
			</tr>			
		</td>
	</tr> 
</tbody>
</table>
<?php
}
add_action("pmpro_membership_level_after_other_settings", "pclct_pmpro_membership_level_after_other_settings");

function pclct_pmpro_save_membership_level_custom_fields($level_id)
{
	$message_custom = get_option("pmpro_message_custom", array());
		
	$message_custom[$level_id] = array('allow_agents'=>$_REQUEST['allow_agents'],'price_per_agent'=>$_REQUEST['price_per_agent']);
	
	update_option("pmpro_message_custom", $message_custom);
	
	//add level cost text for this level			
}
add_action("pmpro_save_membership_level", "pclct_pmpro_save_membership_level_custom_fields");
function get_pmpro_save_membership_level_custom_fields($level_id)
{
	$message_custom = get_option("pmpro_message_custom", array());
	if(!empty($message_custom[$level_id]))
	{				
		$message = $message_custom[$level_id];
	}
	return $message;
}

function getNextPaymentDate($start_date,$cycle_period)
{
	 global $wpdb; 

	//get what day are their payments on?
	$subscription_start_date = $start_date;
	$subscription_day = date("d", strtotime($subscription_start_date));
	
	//next month or next year
	if($cycle_period == "Month")
	{
		//next month
		$next_month = strtotime(" + 1 Month");
		$days_in_next_month = date("t", $next_month);
		if($days_in_next_month > $subscription_day)
			$date = date("Y-m", $next_month) . "-" . $subscription_day  . "T0:0:0";
		else
			$date = date("Y-m", $next_month) . "-" . $days_in_next_month  . "T0:0:0";
	}
	else if($cycle_period == "Week")
	{
		//next month		
		$next_month = strtotime(" + 1 Week");
		$days_in_next_month = date("t", $next_month);
		$subscription_day = date("d", $next_month);
		
		if($days_in_next_month > $subscription_day)
			$date = date("Y-m", $next_month) . "-" . $subscription_day  . "T0:0:0";
		else
			$date = date("Y-m", $next_month) . "-" . $days_in_next_month  . "T0:0:0";
	}	
	else
	{
		//next year
		$next_year = strtotime(" + 1 Year");
		$days_in_that_month = date("t", $next_year);
		if($days_in_that_month > $subscription_day)
			$date = date("Y-m", $next_year) . "-" . $subscription_day  . "T0:0:0";
		else
			$date = date("Y-m", $next_year) . "-" . $days_in_that_month  . "T0:0:0";
	}	 
	return date('Y-m-d', strtotime($date)); 
}

/* function tml_resetpass_action() {
   
}
add_action( 'tml_request_resetpass', 'tml_resetpass_action' );  */

/*
    Filter pmpro_next_payment to get actual value
    via the PayPal API. This is disabled by default
    for performance reasons, but you can enable it
    by copying this line into a custom plugin or
    your active theme's functions.php and uncommenting
    it there.
   */
add_filter('pmpro_next_payment', array('PMProGateway_paypalexpress', 'pmpro_next_payment'), 10, 3);	
