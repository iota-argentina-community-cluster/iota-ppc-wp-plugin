<?php
/**
* Plugin Name: IOTA Pay per Content
* Plugin URI: https://github.com/iota-argentina-community-cluster/iota-ppc-wp-plugin/
* Description: Allow IOTA Payments to buy access to posts. 
* Version: 1.0
* Author: IOTA Argentina Community Cluster
* Author URI: http://www.iotaargentina.org/
**/

// Exit if accessed directly
if (false === defined('ABSPATH')) exit;

// Config
define("payUsingIOTA_address",get_option( 'iota_pay_per_content_wallet_address' ));
define("payUsingIOTA_cookie_name","ih_passport");

// Get the Node provided on Plugin configurations
define("payUsingIOTA_NODE",get_option( 'iota_pay_per_content_node_host' ));

// IOTA PHP Library https://github.com/tuupola/trytes
require_once(WP_PLUGIN_DIR.'/iota-ppc-wp-plugin/lib/trytes/Trytes.php');


// Localize and register the Javscript script with new data
wp_register_script( 'payUsingIOTA', plugin_dir_url( __FILE__ ) . '/assets/js/script.js', array("jquery") );
wp_localize_script( 'payUsingIOTA', 'WPURLS', array( 'siteurl' => get_site_url() ));
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script( 'QRCode', plugin_dir_url( __FILE__ ) . '/assets/js/qrcode.web.min.js');
    wp_enqueue_script( 'payUsingIOTA' );
});

// CSS
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'iota-ppc-style', plugin_dir_url( __FILE__ ) . '/assets/css/style.css' );
});

// WP Data : Force the session start
if (!session_id()) session_start();

// IOTA Data
define("payUsingIOTA_units",serialize(Array(
    "1000000" => "Mi",
    "1000" => "Ki",
    "1" =>"i" 
)));

// Posts page Metabox 
add_action('admin_menu', function() {
	add_meta_box(
		'payUsingIOTA_Metabox',
		'Pay content with IOTA',
		'payUsingIOTA_Metabox',
		'post', 'side', 'high'
	);
});

function payUsingIOTA_Metabox() {
    global $post;
    $enabled = get_post_meta( $post->ID, 'payUsingIOTA_enabled', true );
    $amount = (int) get_post_meta( $post->ID, 'payUsingIOTA_amount', true );
    $unit = get_post_meta( $post->ID, 'payUsingIOTA_unit', true );
    ?>
    <form>
        <p>
            <input type="checkbox" class="checkbox" name="payUsingIOTA_enabled" <?php checked( $enabled, 1 ); ?> /><label for="payUsingIOTA_enabled">Request IOTA Payment to access</label>
        </p>
        <p>
            <label for="payUsingIOTA_amount">Amount to pay</label>
            <input type="text" name="payUsingIOTA_amount" value="<?php echo $unit ? number_format($amount / $unit,strlen((string)($unit-1))) : ''; ?>" />
            <select name="payUsingIOTA_unit">
                <option value="1" <?php selected($unit,1); ?>>i</option>
                <option value="1000" <?php selected($unit,1000); ?>>Ki</option>
                <option value="1000000" <?php selected($unit,1000000); ?>>Mi</option>
            </select>
        </p>
    </form>
    <?php
}


// Validate and save Metabox data
add_action('save_post', 'payUsingIOTA_Saving');
function payUsingIOTA_Saving($post_id)
{
    if (array_key_exists('payUsingIOTA_enabled', $_POST)
    && array_key_exists('excerpt', $_POST)
    && empty($_POST["excerpt"])) {
        $_SESSION['my_admin_notices'] .= '<div class="error"><p>You need to provide an Excerpt to request an IOTA Payment on the post.</p></div>';
        return;
    }

    if (array_key_exists('payUsingIOTA_enabled', $_POST)
    && array_key_exists('payUsingIOTA_amount', $_POST)
    && (float)$_POST["payUsingIOTA_amount"] * (int)$_POST["payUsingIOTA_unit"] < 1) {
        $_SESSION['my_admin_notices'] .= '<div class="error"><p>Invalid amount entered.</p></div>';
        return;
    }

    if(!array_key_exists('payUsingIOTA_amount', $_POST))
        return;
    
    update_post_meta(
        $post_id,
        'payUsingIOTA_amount',
        (float)$_POST["payUsingIOTA_amount"] * (int)$_POST["payUsingIOTA_unit"]
    );

    update_post_meta(
        $post_id,
        'payUsingIOTA_unit',
        (int)$_POST["payUsingIOTA_unit"]
    );

    update_post_meta(
        $post_id,
        'payUsingIOTA_enabled',
        array_key_exists('payUsingIOTA_enabled', $_POST)
    );
}


// Errors handle
function my_admin_notices(){
    if(!empty($_SESSION['my_admin_notices'])) print  $_SESSION['my_admin_notices'];
    unset ($_SESSION['my_admin_notices']);
}
add_action( 'admin_notices', 'my_admin_notices' );


function payUsingIOTA_formatPrice($amount,$unit) {
    return number_format($amount / $unit,$unit != 1 ? strlen((string)($unit-1)) : 0)." ".unserialize(payUsingIOTA_units)[$unit];
}


// Display QR and payment data after the Excerpt
add_filter( 'the_content', function ($content) {
    
    global $post;
    $enabled = get_post_meta( $post->ID, 'payUsingIOTA_enabled', true );
    if(!$enabled) return $content;
    $amount = (int) get_post_meta( $post->ID, 'payUsingIOTA_amount', true );
    $unit = get_post_meta( $post->ID, 'payUsingIOTA_unit', true );
    $sha256 = hash("sha256",payUsingIOTA_address.$amount.$post->ID.session_id());
    $amount_formated = payUsingIOTA_formatPrice($amount,$unit);
    if(isset($_COOKIE[payUsingIOTA_cookie_name.$post->ID])) {
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM wp_iotappc WHERE post_id = '$post->ID'");
        foreach ( $rows as $row ) {
            if($row->cookie_hash == $_COOKIE[payUsingIOTA_cookie_name.$post->ID])
                return $content;
        }
    }
    $has_excerpt = has_excerpt();
    if(!$has_excerpt && !is_single()) return excerpt_filtered();

    if(!is_single()) return the_excerpt();
    
    ob_start();
    ?>
    <div id="payUsingIOTA_loading" class="clearfix" style="text-align:center;">
        <div class="spinner"></div>
    </div>
    <div id="payUsingIOTA_error" class="clearfix" style="text-align:center;">
        <strong>Sorry! Our systems aren't working, QR Payments are not available.<br>Please try later.</strong>
    </div>
    <div id="payUsingIOTA_restrictedArea" class="clearfix">
        <div id="payUsingIOTA_QRDATA" style="with:100%;">
            <div class="payUsingIOTA_QRDATA_SECTOR">
                <label for="address">Address</label><input type="text" id="address" name="address" value="<?php echo payUsingIOTA_address; ?>" /> <button class="payUsingIOTA_QRDATA_COPY">Copy</button>
            </div>
            <div class="payUsingIOTA_QRDATA_SECTOR">
                <label for="amount">Amount (i)</label><input type="text" id="amount" name="amount" value="<?php echo $amount; ?>" /> <button class="payUsingIOTA_QRDATA_COPY">Copy</button>
            </div>
            <div class="payUsingIOTA_QRDATA_SECTOR">
                <label for="message">Message</label><input type="text" id="message" name="message" value='{"postId":<?php echo $post->ID; ?>,"code":"<?php echo $sha256; ?>"}' /> <button class="payUsingIOTA_QRDATA_COPY">Copy</button>
            </div>
        </div>
        <div id="payUsingIOTA_QR">
            <canvas style="height:auto !important;max-width:100%;" id="payUsingIOTA_QRCanvas" data-address="<?php echo payUsingIOTA_address; ?>" data-price="<?php echo $amount; ?>" data-postId="<?php echo $post->ID; ?>" data-code="<?php echo $sha256; ?>"></canvas>
            <br>
            <button class="payUsingIOTA_QR_showdata">Show QR Data</button>
        </div>
        <div id="payUsingIOTA_INFO" class="clearfix">
            <strong>Get full access to this note by paying <?php echo $amount_formated; ?></strong><br>
            <span>The QR contents sensitive information, don't alter it.</span><br>
            <a class="button" href="iota://<?php echo payUsingIOTA_address; ?>/?amount=<?php echo $amount; ?>&message={"postId":<?php echo $post->ID; ?>,"code":"<?php echo $sha256; ?>"}" id="iota-deep-link">Open with Trinity Wallet</a>
            <br>
            <small>(Deep links must be enabled)</small>
        </div>
        <div id="payUsingIOTA_VerificationArea" class="clearfix">
            <button class="button button-large">
                VERIFY PAYMENT
            </button>
            <div id="payUsingIOTA_loading" class="clearfix" style="text-align:center;">
                <div class="spinner"></div>
            </div>
            <div id="payUsingIOTA_feedback"></div>
        </div>
        <div class="clear"></div>
    </div>
    <?php
    return ($has_excerpt ? get_the_excerpt() : '').ob_get_clean();

});


// Query node
function query_node($body) {
    return wp_remote_post( payUsingIOTA_NODE, array(
        'method' => 'POST',
        'timeout' => 1200,
        'redirection' => 1,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array('Content-type' => 'application/x-www-form-urlencoded','X-IOTA-API-Version' => '1'),
        'body' => $body,
        'cookies' => array()
        )
    );
}

// WP API endpoint creation enabling the payment status return
add_action( 'rest_api_init', function () {
    register_rest_route( 'payUsingIOTA/v1', '/estado/', array(
        'methods' => 'POST',
        'callback' => function ($data) {
            ini_set('default_socket_timeout', 900); // 900 Seconds = 15 Minutes
            $address = payUsingIOTA_address;
            $post_id = $data["postId"];
            $amount = (int) get_post_meta( $post_id, 'payUsingIOTA_amount', true );
            $sha256 = hash("sha256",$address.$amount.$post_id.session_id());

            // Verify if the request hash match with the hash required by the post
            if($data["code"] != $sha256) return json_decode('{"result":false,"reason":"invalid hash"}');

            global $wpdb;
            
            // Get all post's records and cookie_hash's records
            $rows = $wpdb->get_results( "SELECT * FROM wp_iotappc WHERE post_id = '$post_id' OR cookie_hash = '$post_id' ORDER BY id DESC");
            $txs_ids_indexadas = Array();

            // Iterate records
            foreach ( $rows as $row )
                
                // If the id of the post_id's records match with the post_id requested and the tx value is enough
                if($row->post_id == $post_id && (int) $row->tx_value >= $amount) {
                    // If the cookie isn't set
                    if(!isset($_COOKIE[payUsingIOTA_cookie_name.$post_id])) {
                        // If the cookie_hash's record is valid
                        if($row->cookie_hash == $sha256 && time() - strtotime($row->timestamp) < 2678400) {
                            setcookie(payUsingIOTA_cookie_name.$row->post_id, $sha256, strtotime($row->timestamp)+2678400,"/");
                            return json_decode('{"result":true,"reason":""}');
                        }
                        // No else because maybe the person paid again and is trying to verify their new payment
                    } else {
                    // If cookie exists
                        if($row->cookie_hash == $_COOKIE[payUsingIOTA_cookie_name.$post_id])
                            return json_decode('{"result":true,"reason":""}');
                    }
                } else
                    array_push($txs_ids_indexadas,$row->tx_id);

            // Stored records do not match: Querying for new transactions
            $response = query_node('{"command": "findTransactions", "addresses": ["'.substr($address,0,81).'"]}');
                
            // Error
            if ( is_wp_error( $response ) )
               return (object) [
                'result' => false,
                'reason' => $response->get_error_message()
                ];
                
            $response = json_decode($response['body']);

            // Error: Empty query's result
            if (!$response) return json_decode('{"result":false,"reason":"Empty result on node-query #1"}');

            $txs_ids = $response->hashes;

            // 0 transaction to analyze
            if(!count($txs_ids)) return json_decode('{"result":false,"reason":"0 transactions to analyze"}');

            // Found transaction's ID - Stored Record's TXID
            $txs_ids_noConsultadas = (array) array_diff( $txs_ids,$txs_ids_indexadas  );

            // Confirmation verification
            $iota_pay_per_content_confirmed_payments = get_option( 'iota_pay_per_content_confirmed_payments' ) == "on";

            if(!count($txs_ids_noConsultadas)) {
                return json_decode('{"result":false,"reason":"0 transactions to analyze"}');
            }

            // Confirmed verifications
            if ( $iota_pay_per_content_confirmed_payments ) {
                
                // Getting latest milestone
                $response = query_node('{"command": "getNodeInfo"}');
                
                // Error
                if ( is_wp_error( $response ) )
                   return (object) [
                    'result' => false,
                    'reason' => $response->get_error_message()
                    ];
                    
                $response = json_decode($response['body']);

                // Error: No results
                if (!$response) return (object) [
                    'result' => false,
                    'reason' => 'Empty result on node-query #2'
                ];
                
                // Checking for latest inclution state
                $response = query_node('{"command": "getInclusionStates","transactions": ["'.implode('","',$txs_ids_noConsultadas).'"],"tips": ["'.json_decode($response)->latestMilestone.'"]}');

                // Error
                if ( is_wp_error( $response ) )
                   return (object) [
                    'result' => false,
                    'reason' => $response->get_error_message()
                    ];
                
                $response = json_decode($response['body']);

                // Error: No results
                if (!$response) return (object) [
                    'result' => false,
                    'reason' => 'Empty result on node-query #2'
                ];

                $confirmeds = [];
                foreach(json_decode($response)->states as $index => $state) if($state) $confirmeds[] = $txs_ids_noConsultadas[$index];
            }

            // Getting new records
            $response = query_node('{"command": "getTrytes", "hashes": ["'.implode('","',$txs_ids_noConsultadas).'"]}');
                
            // Error
            if ( is_wp_error( $response ) )
               return (object) [
                'result' => false,
                'reason' => $response->get_error_message()
                ];
                
            $response = json_decode($response['body']);

            // Error: No results
            if (!$response) return (object) [
                'result' => false,
                'reason' => 'Empty result on node-query #2'
            ];

            $txs_trytes = $response->trytes; //array of transactions on trytes format
            $txs_data = [];
            foreach ($txs_trytes as $tx_trytes)
                $txs_data[] = getDataFromTrytes(($tx_trytes));

            $results = [];
            $trytes = new Tuupola\Trytes;
            $i = 0;
            foreach( $txs_data as $tx_data ) {
                $data = json_decode( trim( $trytes->decode(
                    substr($tx_data["signatureMessageFragment"],0,-1 )
                    ) ) );
                if (is_null($data)) continue;
                $data->tx_id = $txs_ids_noConsultadas[$i];
                $data->post_id = $data->postId;
                $data->cookie_hash = $data->code;
                $data->value = $tx_data["value"];
                $results[] = $data;
                ++$i;
            }

            $found = false; $confirmed = false;
            foreach($results as $result) {
                if( $sha256 != $result->cookie_hash || $result->value < $amount ) continue;
                $found = true;
                if( $iota_pay_per_content_confirmed_payments && !in_array($result->tx_id,$confirmeds) ) continue;
                $confirmed = true;
                $r = $wpdb->insert("wp_iotappc",array(
                    "address" => $address,
                    "tx_id" => $result->tx_id,
                    "tx_value" => $result->value,
                    "cookie_hash" => $result->cookie_hash,
                    "post_id" => $result->post_id
                ));
                if(!isset($r) || !$r) return json_decode('{"result":false,"reason":"Error inserting to db"}');
            }
            $right = false;
            if($found)
                if(!$iota_pay_per_content_confirmed_payments)
                    $right = true;
                else if($confirmed)
                    $right = true;
                else
                    return json_decode('{"result":false,"reason":"unconfirmed","txid":""}');
            if($right)
                setcookie(payUsingIOTA_cookie_name.$post_id, $sha256, time()+2678400,"/");
            return json_decode('{"result":'.($right ? 'true' : 'false').',"reason":"found"}');
        },
    ));
});

add_action( 'rest_api_init', function () {
    register_rest_route( 'payUsingIOTA/v1', '/getNodeInfo/', array(
        'methods' => 'GET',
        'callback' => function ($data) {
            ini_set('default_socket_timeout', 900); // 900 Seconds = 15 Minutes
            $response = query_node('{"command": "getNodeInfo"}');
                
            // Error
            if ( is_wp_error( $response ) )
               return false;
            $response = json_decode($response['body']);
            if(!$response) return false;
            else return $response;
        },
    ));
});


function excerpt_filtered( $excerpt = "", $post = null ) {
    if(is_single()) return $excerpt;
    $ID = $post ? $post->ID : get_the_ID();
	$enabled = get_post_meta( $ID, 'payUsingIOTA_enabled', true );
	$amount = (int) get_post_meta( $ID, 'payUsingIOTA_amount', true );
    $unit = get_post_meta( $ID, 'payUsingIOTA_unit', true );
    ob_start();
    ?>
<button onclick="location.href='<?php echo esc_url( get_permalink() ); ?>';" class="button button-large" style="display:block;height:48px;"><?php echo payUsingIOTA_formatPrice($amount,$unit); ?></button>
    <?php if($enabled) return $excerpt.ob_get_clean(); else ob_clean();
}
add_filter('get_the_excerpt', 'excerpt_filtered');



/* Dashboard PPC Configuration */
add_action('admin_menu', 'iota_pay_per_content_admin');
 
function iota_pay_per_content_admin(){
        add_menu_page( 'IOTA Pay Per Content', 'IOTA PPC', 'manage_options', 'iota_pay_per_content', 'iota_pay_per_content_admin_content' );
}


if( !function_exists("iota_pay_per_content_data") ) {
    function iota_pay_per_content_data() {
        register_setting( 'iota_pay_per_content-settings', 'iota_pay_per_content_node_host' );
        register_setting( 'iota_pay_per_content-settings', 'iota_pay_per_content_wallet_address' );
        register_setting( 'iota_pay_per_content-settings', 'iota_pay_per_content_confirmed_payments' );
    }
}
add_action( 'admin_init', 'iota_pay_per_content_data' );

 
function iota_pay_per_content_admin_content(){
    if( isset($_GET['settings-updated']) ) { ?>
    <div id='message' class='updated'>
        <p><strong><?php _e('Settings saved.'); ?></strong></p>
    </div>
    <?php } ?>
    <h1>IOTA Pay Per Content Settings</h1>
    
    <p>The following Plugin allows content creators to require an IOTA Payment in order to access certain Posts. Enter what you want people to read on the Excerpt box, Check the "Require IOTA Payment to access" box at the Pay Content with IOTA box and input the amount of IOTA you want to charge.  </p>

    <p>You can define a Node, Address in which you will recieve the payments and whether the payment transaction should be confirmed or not in order to grant access at the IOTA PPC Config page in your Wordpress Dashboard.</p>


    <form method="post" action="options.php">
        <?php settings_fields( 'iota_pay_per_content-settings' ); ?>
        <?php do_settings_sections( 'iota_pay_per_content-settings' ); ?>
        <table class="form-table">
        <tr valign="top">
            <th scope="row"><label for='iota_pay_per_content_node_host'>Hosting Node</label></th>
            <td><input type="text" required placeholder='protocol://host:port' id="iota_pay_per_content_node_host" name="iota_pay_per_content_node_host" value="<?php echo payUsingIOTA_NODE; ?>"/></td>
        </tr>
        <tr>
            <th scope="row"><label for='iota_pay_per_content_wallet_address'>Wallet Address (+ checksum)</label></th>
            <td><input type="text" size='90' pattern="[A-Z9]{90,}" required placeholder='WEPGVGVH9EHOZHBXKL9YFLIPCMPRBTIPPKQNLNMYZOKPXRHZSIHWGSKTZWKFHDVEWNXLGCUFQGUAZILMWEXOHANSFW' id="iota_pay_per_content_wallet_address" name="iota_pay_per_content_wallet_address" value="<?php echo payUsingIOTA_address; ?>"/></td>
        </tr>
        <tr>
            <th scope="row"><label for='iota_pay_per_content_confirmed_payments'>Require confirmed transaction to grant access</label></th>
            <td><input type="checkbox" class="checkbox" name="iota_pay_per_content_confirmed_payments" <?php checked( get_option( 'iota_pay_per_content_confirmed_payments' ) , "on" ); ?> /></td>
        </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <?php
}

// DB Installation
function iotappc_install() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'iotappc';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        address varchar(90) NOT NULL,
        id int(11) NOT NULL AUTO_INCREMENT,
        post_id int(11) NOT NULL,
        cookie_hash varchar(64) NOT NULL,
        tx_id varchar(81) NOT NULL,
        timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        tx_value int(16) NOT NULL,
        PRIMARY KEY (id)
      ) ENGINE=MyISAM DEFAULT CHARSET=$charset_collate";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

    // Plugin DB Version
	add_option( 'iotappc_db_version', '1.0' );
}
register_activation_hook( __FILE__, 'iotappc_install' );

/* =============================================================================================================================
== Functions extracted from https://github.com/crypto5000/iota.lib.php/blob/master/library.php =================================
==============================================================================================================================*/

/* Function to transform trytes to transaction data (address, value)
*  Takes a single tryte as input
*  Returns either "ERROR" or an array with address, value, etc.
*/
function getDataFromTrytes($trytes) {
    
    // validate trytes
    for ($i = 2279; $i < 2295; $i++) {
        
        if ($trytes[$i] !== "9") {
            return "ERROR";
        }
    }

    $txAddress = substr($trytes,2187,81);
    $signatureMessageFragment = substr($trytes,0, 2187);    
    $tag = substr($trytes,2592,27);

    // validate items exist
    if ((!isset($txAddress)) || (!isset($signatureMessageFragment)) || (!isset($tag))) {
        return "ERROR";
    }            

    // get the spend for that particular address - could be pending
    $transactionTrits = trits($trytes);
    $spend = value(array_slice($transactionTrits,6804,33));

    // validate spend value
    if (!isset($spend)) {
        return "ERROR";
    }

    // set the return items as an array
    $outputArray['txAddress'] = $txAddress;
    $outputArray['signatureMessageFragment'] = $signatureMessageFragment;
    $outputArray['tag'] = $tag;
    $outputArray['value'] = $spend;

    return $outputArray;

}


// helper function for getDataFromTrytes
function value($trits) {

    $returnValue = 0;
    for ( $i = count($trits); $i-- > 0; ) {
        $returnValue = $returnValue * 3 + $trits[ $i ];
    }

    return $returnValue;
}

// helper function for getDataFromTrytes
function trits($input) {
    
    $trits = [];

    // All possible tryte values
    $trytesAlphabet = "9ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    // map of all trits representations
    $trytesTrits = [
    [ 0,  0,  0],
    [ 1,  0,  0],
    [-1,  1,  0],
    [ 0,  1,  0],
    [ 1,  1,  0],
    [-1, -1,  1],
    [ 0, -1,  1],
    [ 1, -1,  1],
    [-1,  0,  1],
    [ 0,  0,  1],
    [ 1,  0,  1],
    [-1,  1,  1],
    [ 0,  1,  1],
    [ 1,  1,  1],
    [-1, -1, -1],
    [ 0, -1, -1],
    [ 1, -1, -1],
    [-1,  0, -1],
    [ 0,  0, -1],
    [ 1,  0, -1],
    [-1,  1, -1],
    [ 0,  1, -1],
    [ 1,  1, -1],
    [-1, -1,  0],
    [ 0, -1,  0],
    [ 1, -1,  0],
    [-1,  0,  0]
    ];

    // check if tryte is number or string
    if (is_numeric($input)) {

        $absoluteValue = $input;

        if ($input < 0) {
                $absoluteValue = -$input;
        }

        while ($absoluteValue > 0) {

            $remainder = $absoluteValue % 3;
            $absoluteValue = floor($absoluteValue / 3);

            if ($remainder > 1) {
                $remainder = -1;
                $absoluteValue++;
            }

            $tritLength = count($trits);
            $trits[$tritLength] = $remainder;
        }
        if ($input < 0) {

            for ($i = 0; $i < count($trits); $i++) {

                $trits[$i] = -$trits[$i];
            }
        }
    } else {

        for ($i = 0; $i < strlen($input); $i++) {

            $inputVal = $input[$i];
            $index = strpos($trytesAlphabet,$inputVal);

            $trits[$i * 3] = $trytesTrits[$index][0];
            $trits[$i * 3 + 1] = $trytesTrits[$index][1];
            $trits[$i * 3 + 2] = $trytesTrits[$index][2];
        }
    }

    return $trits;
}