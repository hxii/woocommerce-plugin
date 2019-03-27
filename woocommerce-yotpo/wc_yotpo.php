    <?php
    /*
        Plugin Name: Yotpo Social Reviews for Woocommerce
        Description: Yotpo Social Reviews helps Woocommerce store owners generate a ton of reviews for their products. Yotpo is the only solution which makes it easy to share your reviews automatically to your social networks to gain a boost in traffic and an increase in sales.
        Author: Yotpo
        Version: 1.2.0
        Author URI: http://www.yotpo.com?utm_source=yotpo_plugin_woocommerce&utm_medium=plugin_page_link&utm_campaign=woocommerce_plugin_page_link
        Plugin URI: http://www.yotpo.com?utm_source=yotpo_plugin_woocommerce&utm_medium=plugin_page_link&utm_campaign=woocommerce_plugin_page_link
        WC requires at least: 3.1.0
        WC tested up to: 3.6.0
    */

    // Exit if accessed directly
    if ( !defined( 'ABSPATH' ) ) { exit; }

    // Hooks
    register_activation_hook( __FILE__, 'wc_yotpo_activation' );
    //register_uninstall_hook( __FILE__, 'wc_yotpo_uninstall' );
    register_deactivation_hook( __FILE__, 'wc_yotpo_deactivate' );
    add_action( 'init', 'wc_yotpo_redirect' );
    add_action( 'woocommerce_loaded', 'ver_check' );
    add_action( 'upgrader_process_complete', 'wc_yotpo_post_update', 10, 2 );
    add_action( 'admin_notices', 'wc_yotpo_check_settings' );
    add_action( 'yotpo_scheduled_submission', 'wc_yotpo_send_scheduled_orders', 10, 1 );
    add_filter( 'woocommerce_tab_manager_integration_tab_allowed', 'wc_yotpo_disable_tab_manager_managment' );
    $yotpo_settings = get_option( 'yotpo_settings', wc_yotpo_get_default_settings() );
    if ( $yotpo_settings['order_submission_method'] == 'hook' || !isset( $yotpo_settings['order_submission_method'] ) ) {
        add_action( 'woocommerce_order_status_changed', 'wc_yotpo_map', 99, 1 );
    }
    $product_image_map = array();
    $product_map = array();
    $currency = "";

    // Make sure the plugin does not run in older versions of WooCommerce
    function ver_check() {
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        global $currency;
        $currency = get_woocommerce_currency();
        global $woocommerce;
        global $woo_ver;
        global $woo_req_ver;
        $woo_ver = $woocommerce->version;
        $woo_req_ver = '3.1.0';
        if ( version_compare( $woo_ver, $woo_req_ver, ">=" ) && version_compare( phpversion() , '5.6.0', '>=' ) ) {
            add_action( 'plugins_loaded', 'wc_yotpo_init' );
        } elseif ( version_compare( phpversion() , '5.6.0', '<' ) ) {
            function yotpo_php_warn() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e( '<span class="dashicons dashicons-warning" style="color: red"></span>
                    <strong>Warning</strong> - Yotpo is disabled due to incompatible PHP version: ' . phpversion() . '. PHP 5.6.0 and above is required.' ); ?></p>
                </div>
                <?php
            }
            add_action( 'admin_notices', 'yotpo_php_warn' );
        } else {
            function yotpo_woocom_warn() {
                global $woo_ver;
                global $woo_req_ver;
                ?>
                <div class="notice notice-error">
                    <p><?php _e( '<span class="dashicons dashicons-warning" style="color: red"></span>
                    <strong>Warning</strong> - Yotpo is disabled due to incompatible WooCommerce version: ' . $woo_ver . ', please update to at least ' . $woo_req_ver . '. Alternatively, please downgrade to Yotpo v1.1.7 from <a href="https://github.com/YotpoLtd/woocommerce-plugin/releases/tag/1.1.7" target="_blank">here</a>.' ); ?></p>
                </div>
                <?php
            }
            add_action( 'admin_notices', 'yotpo_woocom_warn' );
        }
    }
    }
    // Init of everything
    function wc_yotpo_init() {
        global $yotpo_settings;
        if ( is_admin() ) {
            if ( isset( $_GET['download_exported_reviews'] ) ) {
                if ( current_user_can( 'manage_options' ) ) {
                    require_once ( 'classes/class-wc-yotpo-export-reviews.php' );
                    $export = new Yotpo_Review_Export();
                    list( $file, $errors ) = $export->exportReviews();
                    if ( is_null($errors) ) {
                        ytdbg( $file, 'Reviews Export Success: ' );
                        $export->downloadReviewToBrowser( $file );
                    } else {
                        ytdbg( $errors, 'Reviews Export Fail: ' );
                    }
                }
                exit;
            }
            require_once ( plugin_dir_path( __FILE__ ) . 'templates/wc-yotpo-settings.php' );
            require_once ( plugin_dir_path( __FILE__ ) . 'templates/wc-yotpo-debug.php' );
            require_once ( plugin_dir_path( __FILE__ ) . 'lib/yotpo-api/Yotpo.php' );
            add_action( 'admin_menu', 'wc_yotpo_admin_settings' );
            add_action( 'admin_menu', 'wc_yotpo_admin_debug' );
        } elseif ( !empty( $yotpo_settings['app_key'] ) && wc_yotpo_compatible() ) {
            add_action( 'wp_enqueue_scripts', 'wc_yotpo_load_js' );
            add_action( 'template_redirect', 'wc_yotpo_front_end_init' );
        }
    }
    // Redirect to Yotpo settings page if plugin was just installed
    function wc_yotpo_redirect() {
        if ( get_option( 'wc_yotpo_just_installed', false ) ) {
            delete_option( 'wc_yotpo_just_installed' );
            wp_redirect( ( ( is_ssl() || force_ssl_admin() || force_ssl_login() ) ? str_replace( 'http:', 'https:', admin_url( 'admin.php?page=woocommerce-yotpo-settings-page' ) ) : str_replace( 'https:', 'http:', admin_url( 'admin.php?page=woocommerce-yotpo-settings-page' ) ) ) );
            exit;
        }
    }
    // Yotpo admin settings page
    function wc_yotpo_admin_settings() {
        add_action( 'admin_enqueue_scripts', 'wc_yotpo_admin_styles' );
        add_menu_page( 'Yotpo', 'Yotpo', 'manage_options', 'woocommerce-yotpo-settings-page', 'wc_display_yotpo_admin_page', 'none', null );
    }
    // Yotpo debug settings page
    function wc_yotpo_admin_debug() {
        global $yotpo_settings;
        if ( $yotpo_settings['debug_mode'] ) {
            add_submenu_page( 'woocommerce-yotpo-settings-page', 'Debug', 'Debug', 'manage_options', 'yotpo-debug', 'wc_yotpo_admin_debug_page' );
        }
    }
    // All frontend hooks
    function wc_yotpo_front_end_init() {
        global $yotpo_settings;
        add_action( 'woocommerce_thankyou', 'wc_yotpo_conversion_track' );
        if ( is_product() ) {
            if ( $yotpo_settings['disable_native_review_system'] ) {
                add_filter( 'comments_open', 'wc_yotpo_remove_native_review_system', null, 2 );
            }
            $widget_location = $yotpo_settings['widget_location'];
            if ( $widget_location == 'footer' ) {
                add_action( $yotpo_settings['main_widget_hook'], 'wc_yotpo_show_widget', $yotpo_settings['main_widget_priority'] );
            } elseif ( $widget_location == 'tab' ) {
                add_action( 'woocommerce_product_tabs', 'wc_yotpo_show_widget_in_tab' );
            }
            if ( $yotpo_settings['bottom_line_enabled_product'] ) {
                wp_enqueue_style( 'yotpoSideBootomLineStylesheet', plugins_url( 'assets/css/bottom-line.css', __FILE__ ) );
                add_action( $yotpo_settings['product_bottomline_hook'], 'wc_yotpo_show_bottomline', $yotpo_settings['product_bottomline_priority'] );
            }
            if ( $yotpo_settings['qna_enabled_product'] ) {
                add_action( $yotpo_settings['product_qna_hook'], 'wc_yotpo_show_qa_bottomline', $yotpo_settings['product_qna_priority'] );
            }
        } elseif ( $yotpo_settings['bottom_line_enabled_category'] ) {
            wp_enqueue_style( 'yotpoSideBootomLineStylesheet', plugins_url( 'assets/css/bottom-line.css', __FILE__ ) );
            add_action( $yotpo_settings['category_bottomline_hook'], 'wc_yotpo_show_bottomline', $yotpo_settings['category_bottomline_priority'] );
        }
    }
    // Yotpo plugin activation actions
    function wc_yotpo_activation() {
        if ( current_user_can( 'activate_plugins' ) ) {
            update_option( 'wc_yotpo_just_installed', true );
            $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
            check_admin_referer( "activate-plugin_{$plugin}" );
            $default_settings = get_option( 'yotpo_settings', false );
            if ( !is_array( $default_settings ) ) {
                add_option( 'yotpo_settings', wc_yotpo_get_default_settings() );
            }
            update_option( 'native_star_ratings_enabled', get_option( 'woocommerce_enable_review_rating' ) );
            update_option( 'woocommerce_enable_review_rating', 'no' );
        }
    }
    // Yotpo plugin uninstall actions
    // function wc_yotpo_uninstall() {
    //     if ( current_user_can( 'activate_plugins' ) && __FILE__ == WP_UNINSTALL_PLUGIN ) {
    //         check_admin_referer( 'bulk-plugins' );
    //         delete_option( 'yotpo_settings' );
    //     }
    // }
    // Load main JS file and append app key
    function wc_yotpo_load_js() {
        global $yotpo_settings;
        wp_enqueue_script( 'yquery', plugins_url( 'assets/js/headerScript.js', __FILE__ ) , null, null );
        wp_localize_script( 'yquery', 'yotpo_settings', array(
            'app_key' => $yotpo_settings['app_key']
        ) );
    }
    // Show main widget function
    function wc_yotpo_show_widget( $check = true ) {
        global $product;
        $show_widget = is_product() ? $product->get_reviews_allowed() == true : true;
        if ( $show_widget || !$check ) {
            $product_data = wc_yotpo_get_product_data( $product );
            echo '<div class="yotpo yotpo-main-widget"
                data-product-id="' . $product_data["id"] . '"
                data-name="' .       $product_data["title"] . '"
                data-url="' .        $product_data["url"] . '"
                data-image-url="' .  $product_data["image-url"] . '"
                data-description="'. $product_data["description"] . '"
                data-lang="' .       $product_data["lang"] . '"
                data-price="' .      $product->get_price() . '"
                data-currency="' .   get_woocommerce_currency() . '">
                </div>';
        }
    }
    // Show widget in tab function
    function wc_yotpo_show_widget_in_tab( $tabs ) {
        global $product;
        if ( $product->get_reviews_allowed() ) {
            global $yotpo_settings;
            $tabs['yotpo_widget'] = array(
                'title'    => $yotpo_settings['widget_tab_name'],
                'priority' => 50,
                'callback' => 'wc_yotpo_show_widget'
            );
        }
        return $tabs;
    }
    // Show Q&A bottomline function
    function wc_yotpo_show_qa_bottomline( $check = true ) {
        global $product;
        $show_bottom_line = is_product() ? $product->get_reviews_allowed() == true : true;
        if ( $show_bottom_line || !$check ) {
            $product_data = wc_yotpo_get_product_data( wc_get_product() );
            echo '<div class="yotpo QABottomLine"
                data-appkey="' .     $product_data["app_key"] . '"
                data-product-id="' . $product_data["id"] . '">
                </div>';
        }
    }
    // Show bottomline function
    function wc_yotpo_show_buttomline( $check = true ) {
        return wc_yotpo_show_bottomline( $check );
    }
    function wc_yotpo_show_bottomline( $check = true ) {
        global $product;
        $show_bottom_line = is_product() ? $product->get_reviews_allowed() == true : true;
        if ( $show_bottom_line || !$check ) {
            $product_data = wc_yotpo_get_product_data( $product );
            echo '<div class="yotpo bottomLine"
                data-product-id="' . $product_data["id"] . '"
                data-url="' .        $product_data["url"] . '"
                data-lang="' .       $product_data["lang"] . '"
                ></div>';
        }
    }
    // Get product data
    function wc_yotpo_get_product_data( $product ) {
        global $yotpo_settings;
        return array(
            'app_key'     => $yotpo_settings['app_key'],
            'id'          => ( $id = $product->get_id() ),
            'url'         => get_permalink( $id ),
            'lang'        => $yotpo_settings['yotpo_language_as_site'] ? explode( '-', get_bloginfo( 'language' ) )[ 0 ] : $yotpo_settings['language_code'],
            'description' => wp_strip_all_tags( substr( $product->get_short_description() , 0, 255) ),
            'title'       => $product->get_title(),
            'image-url'   => wc_yotpo_get_product_image_url( $id ),
            'specs'       => array_filter( array(
                'external_sku' => $product->get_sku(),
                'upc'          => $product->get_attribute( 'upc' ) ?: null,
                'isbn'         => $product->get_attribute( 'isbn' ) ?: null,
                'brand'        => $product->get_attribute( 'brand' ) ?: null,
                'mpn'          => $product->get_attribute( 'mpn' ) ?: null,
            ) ),
        );
    }
    // Disable WooCommerce review system
    function wc_yotpo_remove_native_review_system( $open, $post_id ) {
        if ( get_post_type( $post_id ) == 'product' ) {
            return false;
        }
        return $open;
    }
    // Order submission
    function wc_yotpo_map( $order_id ) {
        $order = wc_get_order( $order_id );
        $orderStatus = 'wc-' . $order->get_status();
        unset( $order );
        global $yotpo_settings;
        ytdbg( ($orderStatus . ' should be ' . $yotpo_settings['yotpo_order_status'] ) , "Order #" . $order_id . " status changed to" );
        if ( $orderStatus == $yotpo_settings['yotpo_order_status'] ) {
            ytdbg( '', "Order #" . $order_id . " submission starting..." );
            $secret = $yotpo_settings['secret'];
            $app_key = $yotpo_settings['app_key'];
            if ( !empty( $app_key ) && !empty( $secret ) && wc_yotpo_compatible() ) {
                try {
                    $purchase_data = wc_yotpo_get_single_map_data( $order_id, false );
                    if ( !is_null( $purchase_data ) && is_array( $purchase_data ) ) {
                        require_once ( plugin_dir_path( __FILE__ ) . 'lib/yotpo-api/Yotpo.php' );
                        $yotpo_api = new Yotpo( $app_key, $secret );
                        $get_oauth_token_response = $yotpo_api->get_oauth_token();
                        if ( !empty( $get_oauth_token_response ) && !empty( $get_oauth_token_response['access_token'] ) ) {
                            $purchase_data['utoken'] = $get_oauth_token_response['access_token'];
                            $purchase_data['platform'] = 'woocommerce';
                            $response = $yotpo_api->create_purchase( $purchase_data );
                            ytdbg( $response['code'] . ' ' . $response['message'], "Order #" . $order_id . " Submitted with response");
                        }
                    }
                }
                catch( Exception $e ) {
                    error_log( $e->getMessage() );
                }
            }
        }
    }
    // Get info for single order
    function wc_yotpo_get_single_map_data( $order_id, $enable_map = true ) {
        if ( $enable_map ) { global $product_map; }
        $order = wc_get_order( $order_id );
        $data = null;
        if ( $order ) {
            $data = array();
            $products_arr = array();
            $data['order_date'] = date( 'Y-m-d H:i:s', strtotime( $order->get_date_created() ) );
            $email = $order->get_billing_email();
            if (
                !empty( $email )
                && !preg_match( '/\d$/', $email )
                && filter_var( $email, FILTER_VALIDATE_EMAIL )
                && strlen( substr( $email, strrpos( $email, "." ) ) ) >= 3
            )
            {
                $data['email'] = $email;
            } else {
                ytdbg( "($order_id - $email)", 'Order Dropped - Invalid Email' );
                return;
            }
            $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            if ( !empty( trim ( $name ) ) ) {
                $data['customer_name'] = $name;
            } else {
                ytdbg( "($order_id - $name)", 'Order Dropped - No Customer Name' );
                return;
            }
            $data['order_id'] = $order_id;
            $data['currency_iso'] = wc_yotpo_get_order_currency( $order );
            ytdbg( "Date: " . $data['order_date'] . " Email: " . $data['email'], "Order #" . $data['order_id'] );
            if ( empty( $order->get_items() ) ) {
                ytdbg( "($order_id)", 'Order Dropped - No Products' );
                return;
            }
            foreach ( $order->get_items() as $item ) {
                if ( $item['product_id'] == "0" ) {
                    ytdbg( "($order_id)", 'Order Dropped - Invalid product (ID of 0)' );
                    return;
                }
                $parent_id = $item->get_product()->get_parent_id();
                $product_id = ( $parent_id != 0 ) ? $parent_id : $item['product_id'];
                $quantity = $item['qty'];
                if ( ( $enable_map ) && array_key_exists($product_id, $product_map) ) {
                    $product_data = $product_map[ $product_id ];
                    $product_data['price'] = $product_data['price'] * $quantity;
                    $products_arr[ $product_id ] = $product_data;
                } else {
                    $_product = wc_get_product( $product_id );
                    if ( !is_object( $_product ) ) { return; }
                    $product_map[ $product_id ] = array(
                        'url'         => get_permalink( $product_id ),
                        'name'        => $_product->get_name(),
                        'image'       => wc_yotpo_get_product_image_url( $product_id ),
                        'description' => '',
                        'price'       => $item->get_product()->get_price(),
                        'specs'       => array_filter( array(
                            'external_sku' => $_product->get_sku(),
                            'upc'          => $_product->get_attribute( 'upc' ) ?: null,
                            'isbn'         => $_product->get_attribute( 'isbn' ) ?: null,
                            'brand'        => $_product->get_attribute( 'brand' ) ?: null,
                            'mpn'          => $_product->get_attribute( 'mpn' ) ?: null,
                        ) ),
                    );
                    $product_data = $product_map[ $product_id ];
                    $product_data['price'] = $product_data['price'] * $quantity; // WIP - To be fixed
                    $products_arr[ $item['product_id'] ] = $product_data;
                }
                ytdbg( $product_data['name'] . ", Descr. length: " . strlen( $product_data['description'] ) . ", ID: " . $product_id . ", Specs: " . implode( ' / ', $product_data['specs'] ) . ", Price: " . $product_data['price'] . " " . $data['currency_iso'] . ", Quantity: " . $item['qty'], "\tProduct:", false );
            }
            $data['products'] = $products_arr;
        }
        return $data;
    }
    // Get product image
    function wc_yotpo_get_product_image_url( $product_id ) {
        global $product_image_map;
        if ( is_array( $product_image_map ) && !array_key_exists( $product_id, $product_image_map ) ) {
            $image_url = wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) ?: null;
            $product_image_map[$product_id] = $image_url;
            return $product_image_map[$product_id];
        } else {
            return $product_image_map[$product_id];
        }
    }
    // Query for past orders
    function wc_yotpo_get_past_orders( $schedule = false ) {
        global $yotpo_settings;
        global $product_image_map;
        global $product_map;
        if ( $schedule ) {
            $timeframe_from = date( 'Y-m-d', strtotime( '-1 days' ) );
            $timeframe_to = date( 'Y-m-d' );
        } else {
            $timeframe_from = date( 'Y-m-d', strtotime( '-' . $yotpo_settings['timeframe_from'] . ' days' ) );
            $timeframe_to = $yotpo_settings['timeframe_to'] != 0 ? date( 'Y-m-d', strtotime( '-' . $yotpo_settings['timeframe_to'] . ' days' ) ) : date( 'Y-m-d' );
        }
        ytdbg('', 'Time frame is from ' . $timeframe_from . ' to ' . $timeframe_to );
        $result = null;
        $args = array(
            'limit'          => -1,
            'status'         => 'completed',
            'type'           => 'shop_order',
            'date_created'   => "$timeframe_from...$timeframe_to",
            'return'         => 'ids',
        );
        $query = new WC_Order_Query( $args );
        $orders = $query->get_orders();
        ytdbg('', 'Got ' . count( $orders ) . ' orders');
        $past_orders = array();
        foreach ( $orders as $order_id ) {
            $order_data = wc_yotpo_get_single_map_data( $order_id );
            if ( !is_null( $order_data ) ) {
                $past_orders[] = $order_data;
            }
        }
        $orders = array();
        if ( count( $past_orders ) > 0 ) {
            $chunks = array_chunk( $past_orders, 200 );
            $result = array();
            foreach ( $chunks as $index => $chunk ) {
                $result[ $index ] = array(
                    'orders'   => $chunk,
                    'platform' => 'woocommerce',
                );
            }
        }
        $product_image_map = array();
        return $result;
    }
    function wc_yotpo_send_scheduled_orders() {
        global $yotpo_settings;
        require_once ( plugin_dir_path( __FILE__ ) . 'lib/yotpo-api/Yotpo.php' );
        ytdbg('', 'Starting scheduled order submission ----------------------------------------------------------');
        $past_orders = wc_yotpo_get_past_orders( true );
        if ( !is_null( $past_orders ) && is_array( $past_orders ) ) {
            ytdbg('', $yotpo_settings['app_key'] . " - " . $yotpo_settings['secret']);
            $yotpoapi = new Yotpo( $yotpo_settings['app_key'], $yotpo_settings['secret'] );
            $get_oauth_token_response = $yotpoapi->get_oauth_token();
            ytdbg('', "TOKEN " . $get_oauth_token_response['access_token']);
            if ( !empty( $get_oauth_token_response ) && !empty( $get_oauth_token_response['access_token'] ) ) {
                foreach ( $past_orders as $index => $post_bulk ) if ( !is_null( $post_bulk ) ) {
                    $post_bulk['utoken'] = $get_oauth_token_response['access_token'];
                    $response = $yotpoapi->create_purchases( $post_bulk );
                    ytdbg( $response['code'] . " " . $response['message'], "\tBatch " . ($index + 1) . " sent with response" );
                    $post_bulk = null;
                }
            }
        }
        ytdbg('', 'Finishing scheduled order submission ----------------------------------------------------------');
    }
    // Past order submission
    function wc_yotpo_send_past_orders() {
        // Script start
        set_time_limit( 120 );
        $rustart = getrusage();
        ytdbg( '', 'Submit Past Orders Start -------------------------------------------------------------------' );
        $startMemory = (memory_get_usage() / 1024);
        ytdbg('', "Memory usage at start is $startMemory KB");
        global $yotpo_settings;
        if ( !empty( $yotpo_settings['app_key'] ) && !empty( $yotpo_settings['secret'] ) ) {
            $past_orders = wc_yotpo_get_past_orders();
            ytdbg( "", "\tGot " . count( $past_orders ) . " batches, sending..." );
            $is_success = true;
            if ( !is_null( $past_orders ) && is_array( $past_orders ) ) {
                $yotpo_api = new Yotpo( $yotpo_settings['app_key'], $yotpo_settings['secret'] );
                $get_oauth_token_response = $yotpo_api->get_oauth_token();
                if ( !empty( $get_oauth_token_response ) && !empty( $get_oauth_token_response['access_token'] ) ) {
                    foreach ( $past_orders as $index => $post_bulk ) if ( !is_null( $post_bulk ) ) {
                        $post_bulk['utoken'] = $get_oauth_token_response['access_token'];
                        $response = $yotpo_api->create_purchases( $post_bulk );
                        if ( $response['code'] != 200 && $is_success ) {
                            ytdbg( $response, "\tSending Past Orders failed for batch" . $index . " :" );
                            $is_success = false;
                            $message = !empty( $response['status'] ) && !empty( $response['status']['message'] ) ? $response['status']['message'] : 'Error occurred';
                            wc_yotpo_display_message( $message, true );
                        } else {
                            ytdbg( $response['code'] . " " . $response['message'], "\tBatch " . ($index + 1) . " sent successfully with response" );
                        }
                        $post_bulk = null;
                    }
                    if ( $is_success ) {
                        wc_yotpo_display_message( 'Past orders sent successfully', false );
                        ytdbg( '', 'Memory usage at end is ' . ( ( memory_get_usage() / 1024 ) - $startMemory) . "KB");
                        ytdbg( '', 'Submit Past Orders End -------------------------------------------------------------------' );
                        $yotpo_settings['show_submit_past_orders'] = false;
                        update_option( 'yotpo_settings', $yotpo_settings );
                    }
                }
            } else {
                wc_yotpo_display_message( 'Could not retrieve past orders', true );
            }
        } else {
            wc_yotpo_display_message('You need to set your app key and secret token to post past orders', false);
        }
        // Script end
        function rutime($ru, $rus, $index) {
            return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
             -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
        }
        $ru = getrusage();
        ytdbg('',"This process used " . rutime($ru, $rustart, "utime") .
            " ms for its computations");
        ytdbg('',"It spent " . rutime($ru, $rustart, "stime") .
            " ms in system calls");
    }
    // Conversion tracking code
    function wc_yotpo_conversion_track( $order_id ) {
        global $yotpo_settings;
        $order = wc_get_order( $order_id );
        $currency = wc_yotpo_get_order_currency( $order );
        $total = $order->get_total();
        $conversion_params = "app_key=" . $yotpo_settings['app_key'] . "&order_id=" . $order_id . "&order_amount=" . $total . "&order_currency=" . $currency;
        echo "<script>yotpoTrackConversionData = {orderId: " . $order_id . ", orderAmount: " . $total . ", orderCurrency: '" . $currency . "'}</script>";
        echo "<noscript><img
            src='https://api.yotpo.com/conversion_tracking.gif?$conversion_params'
            width='1'
            height='1'></img></noscript>";
    }
    // Default settings
    function wc_yotpo_get_default_settings() {
        return array(
            'app_key'                      => '',
            'secret'                       => '',
            'widget_location'              => 'footer',
            'language_code'                => 'en',
            'widget_tab_name'              => 'Reviews',
            'bottom_line_enabled_product'  => true,
            'qna_enabled_product'          => false,
            'bottom_line_enabled_category' => false,
            'yotpo_language_as_site'       => true,
            'show_submit_past_orders'      => true,
            'yotpo_order_status'           => 'wc-completed',
            'disable_native_review_system' => true,
            'native_star_ratings_enabled'  => 'no',
            'debug_mode'                   => false,
            'main_widget_hook'             => 'woocommerce_after_single_product',
            'main_widget_priority'         => 10,
            'product_bottomline_hook'      => 'woocommerce_single_product_summary',
            'product_bottomline_priority'  => 7,
            'product_qna_hook'             => 'woocommerce_single_product_summary',
            'product_qna_priority'         => 8,
            'category_bottomline_hook'     => 'woocommerce_after_shop_loop_item',
            'category_bottomline_priority' => 7,
            'timeframe_from'               => 90,
            'timeframe_to'                 => 0,
            'order_submission_method'      => 'hook',
        );
    }
    function wc_yotpo_get_degault_settings() {
        return wc_yotpo_get_default_settings();
    }
    // WIP - Post plugin update actions
    function wc_yotpo_post_update( $upgrader_object, $options ) {
        $yotpo = plugin_basename( __FILE__ );
        if ( $options['action'] == 'update' && $options['type'] == 'plugin' ){
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == $yotpo ) {
                    set_transient( 'yotpo_plugin_updated', 1 );
                }
            }
        }
    }
    // WIP - Checking plugin settings
    function wc_yotpo_check_settings( $force_check = false ) {
        if ( get_transient( 'yotpo_plugin_updated' || $force_check ) ) {
            $current_settings = get_option( 'yotpo_settings', false );
            $default_settings = wc_yotpo_get_default_settings();
            $s = array_diff_key( $default_settings, $current_settings ) ?: false;
            if ( $s ) {
                $updated_settings = array_merge( $current_settings, $s );
                update_option( 'yotpo_settings', $updated_settings );
                wc_yotpo_display_message( 'Yotpo settings updated!', false );
            }
            echo ($s ? 'new settings found' : 'no new settings');
            delete_transient( 'yotpo_plugin_updated' );
        }
    }
    // Enqueue all styles
    function wc_yotpo_admin_styles( $hook ) {
        if ( $hook == 'toplevel_page_woocommerce-yotpo-settings-page' ) {
            wp_enqueue_script( 'yotpoSettingsJs', plugins_url( 'assets/js/settings.js', __FILE__ ) , array(
                'jquery-effects-core'
            ) );
            wp_enqueue_style( 'yotpoSettingsStylesheet', plugins_url( 'assets/css/yotpo.css', __FILE__ ) );
        }
        wp_enqueue_style( 'yotpoSideLogoStylesheet' , plugins_url( 'assets/css/side-menu-logo.css', __FILE__ ) );
        wp_enqueue_style( 'yotpoDebugStylesheet', plugins_url( 'assets/css/debug.css', __FILE__ ) );
    }
    // WIP - Compatibility check (merge/remove?)
    function wc_yotpo_compatible() {
        return version_compare( phpversion() , '5.6.0' ) >= 0 && function_exists( 'curl_init' );
    }
    // WIP - Yotpo deactivation actions
    function wc_yotpo_deactivate() {
        update_option( 'woocommerce_enable_review_rating', get_option( 'native_star_ratings_enabled' ) );
    }
    function wc_yotpo_disable_tab_manager_managment( $allowed, $tab = null ) {
        if ( $tab == 'yotpo_widget' ) {
            $allowed = false;
            return false;
        }
    }
    function wc_yotpo_get_order_currency( $order ) {
        global $currency;
        // $currency = get_woocommerce_currency();
        // if ( is_null( $order ) || !is_object( $order ) ) {
        //     return $currency;
        // } elseif ( method_exists( $order, 'get_currency' ) ) {
        //     $currency = $order->get_currency();
        // }
        return empty( $currency ) ? get_woocommerce_currency() : $currency;
    }
    function ytdbg( $msg, $name = '', $date = true ) {
        global $yotpo_settings;
        if ( !$yotpo_settings['debug_mode'] ) { return; }
        //$trace = debug_backtrace();
        //$name = ( '' == $name ) ? $trace[1]['function'] : $name;
        $error_dir = plugin_dir_path( __FILE__ ) . "yotpo_debug.log";
        $msg = print_r( $msg, true );
        if ( $date ) {
            $log = "[" . date( "m/d/Y @ g:i:sA", time() ) . "] " . $name . ' ' . $msg . "\n";
        } else {
            $log = $name . ' ' . $msg . "\n";
        }
        $fh = fopen( $error_dir, 'a+' );
        fwrite( $fh, $log );
        fclose( $fh );
    }
    ob_start( 'fatal_error_handler' );
    function fatal_error_handler( $buffer ) {
        $error = error_get_last();
        if ( $error['type'] == 1 ) {
            $newBuffer = '<html><header><title>Fatal Error </title></header>
                        <style>
                        .error_content{
                            background: ghostwhite;
                            vertical-align: middle;
                            margin:0 auto;
                            padding:10px;
                            width:50%;
                         }
                         .error_content label{color: red;font-family: "Ubuntu Mono", Monaco, Consolas, monospace;font-size: 16pt;font-style: italic;}
                         .error_content ul li{ background: none repeat scroll 0 0 FloralWhite;
                                    border: 1px solid AliceBlue;
                                    display: block;
                                    font-family: "Ubuntu Mono" , Monaco, Consolas, monospace;
                                    padding: 2%;
                                    text-align: left;
                          }
                        </style>
                        <body style="text-align: center;">
                          <div class="error_content">
                              <label >Fatal Error </label>
                              <ul>
                                <li><b>Line</b> ' . $error['line'] . '</li>
                                <li><b>Message</b> ' . $error['message'] . '</li>
                                <li><b>File</b> ' . $error['file'] . '</li>
                              </ul>
                              <a href="javascript:history.back()"> Back </a>
                          </div>
                        </body></html>';
            return $newBuffer;
        }
        return $buffer;
    }