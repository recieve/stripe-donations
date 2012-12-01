<?php
/*
Plugin Name: Stripe Donations
Plugin URI: https://uproot.us/
Description: Accept donations on your site using Stripe.
Version: 1.0.2
Author: Matt Gibbs
Author URI: https://uproot.us/
License: GPL2
*/

$sd = new StripeDonations();

class StripeDonations
{
    public $dir;
    public $url;
    public $options;

    /*============================================================
        __construct
    ============================================================*/

    function __construct() {
        $this->dir = (string) dirname(__FILE__);
        $this->url = plugins_url('stripe-donations');
        $this->options = get_option('ssd_options');

        // assign defaults
        if (!isset($this->options['secret_key'])) {
            $this->options['secret_key'] = '';
        }
        if (!isset($this->options['publishable_key'])) {
            $this->options['publishable_key'] = '';
        }

        // include the stripe library
        require_once($this->dir . '/lib/Stripe.php');

        // get the stripe keys
        $stripe = array(
            'secret_key' => $this->options['secret_key'],
            'publishable_key' => $this->options['publishable_key'],
        );

        Stripe::setApiKey($stripe['secret_key']);

        add_action('init', array($this, 'init'));
        add_action('wp_ajax_ssd_save_options', array($this, 'save_options'));
        add_action('wp_ajax_ssd_submit_payment', array($this, 'submit_payment'));
        add_action('wp_ajax_nopriv_ssd_submit_payment', array($this, 'submit_payment'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_shortcode('ssd', array($this, 'shortcode'));
    }

    /*============================================================
        init
    ============================================================*/

    function init() {
        wp_enqueue_script('jquery');
    }

    /*============================================================
        admin_menu
    ============================================================*/

    function admin_menu() {
        add_options_page(
            'Stripe Donations',
            'Stripe Donations',
            'manage_options',
            'stipe-donations',
            array($this, 'options_page')
        );
    }

    /*============================================================
        options_page
    ============================================================*/

    function options_page() {
    ?>
    <script>
    (function($) {
        $(function() {
            $('#submit').click(function() {
                $('#message').hide();
                var data = {
                    'action': 'ssd_save_options',
                    'secret_key': $('#ssd_secret_key').val(),
                    'publishable_key': $('#ssd_publishable_key').val()
                };
                $.post(ajaxurl, data, function(response) {
                    $('#message p').html(response);
                    $('#message').show();
                });
            });
        });
    })(jQuery);
    </script>

    <div class="wrap">
        <h2>Stripe Donations</h2>
        <div id="message" class="updated hidden"><p></p></div>
        <p>To find your keys, log into your <a href="https://stripe.com/" target="_blank">Stripe.com</a> account and look in the "Account Settings" menu.</p>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">Secret Key</th>
                    <td><input type="text" id="ssd_secret_key" class="regular-text" value="<?php echo esc_attr($this->options['secret_key']); ?>" />
                </tr>
                <tr>
                    <th scope="row">Publishable Key</th>
                    <td><input type="text" id="ssd_publishable_key" class="regular-text" value="<?php echo esc_attr($this->options['publishable_key']); ?>" />
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>
    </div>
    <?php
    }

    /*============================================================
        shortcode
    ============================================================*/

    function shortcode($atts) {
        ob_start();

        $amount = isset($atts['amount']) ? $atts['amount'] : '1000';
    ?>
    <style>
    .donate-loading {
        width: 24px;
        height: 24px;
        background: url('<?php echo $this->url; ?>/images/loading.gif') no-repeat;
    }
    </style>
    <script>
    (function($) {
        $(function() {
            $('.stripe-button').bind('token', function(e, token) {
                var data = {
                    'action': 'ssd_submit_payment',
                    'token': token.id,
                    'amount': $(this).attr('data-amount')
                };
                $('.stripe-button-inner').hide();
                $('.donate-response').html('<div class="donate-loading"></div>');
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
                    $('.donate-response').html(response);
                });
            });
        });
    })(jQuery);
    </script>
    <script src="https://button.stripe.com/v1/button.js" class="stripe-button"
        data-key="<?php echo $this->options['publishable_key']; ?>"
        data-amount="<?php echo $amount; ?>"
        data-label="Donate"></script>
        <div class="donate-response"></div>
    <?php
        return ob_get_clean();
    }

    /*============================================================
        save_options
    ============================================================*/

    function save_options() {
        $data = array(
            'secret_key' => $_POST['secret_key'],
            'publishable_key' => $_POST['publishable_key'],
        );
        update_option('ssd_options', $data);
        echo 'Settings saved.';
        exit;
    }

    /*============================================================
        submit_payment
    ============================================================*/

    function submit_payment() {
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        $amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
        try {
            $charge = Stripe_Charge::create(array(
                'card' => $token,
                'amount' => $amount,
                'currency' => 'usd',
            ));
        }
        catch (Stripe_Error $e) {
            die($e->getMessage());
        }

        die('Your payment has been sent. Thank you for your donation!');
    }
}
