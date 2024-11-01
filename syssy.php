<?php

/**
 * Plugin Name: SYSSY - Managing Websites
 * Plugin URI: https://wordpress.org/plugins/syssy
 * Description: Plugin for connecting your website with SYSSY online platform under https://app.syssy.net
 * Version: 1.0.22
 * Requires at least: 4.7.2
 * Requires PHP: 7.0.0
 * Author: SYSSY Online GmbH
 * Author URI: https://www.syssy.net
 * License: GPL v2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: syssy
 * Domain Path: /languages
 */

use \Firebase\JWT\JWT;

require_once 'vendor/firebase/php-jwt/src/BeforeValidException.php';
require_once 'vendor/firebase/php-jwt/src/ExpiredException.php';
require_once 'vendor/firebase/php-jwt/src/SignatureInvalidException.php';
require_once 'vendor/firebase/php-jwt/src/JWT.php';

if ( is_admin() ){ // admin actions

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

}

class Syssy{

    /**
     * Static property to hold singleton instance
     *
     */
    static $instance = false;

    /**
     * constructor
     *
     * @return void
     */
    private function __construct() {

        // load translations
        add_action( 'plugins_loaded', array( $this, 'textdomain'));

        // load settings
        add_filter( 'plugin_action_links_syssy/syssy.php', array($this, 'syssy_settings_link') );

        //register API
        add_action( 'rest_api_init', array($this, 'syssy_register_api') );

        // create custom plugin settings menu
        add_action('admin_menu', array($this, 'syssy_create_option_menu'));

        //call register settings function
        add_action( 'admin_init', array($this, 'syssy_register_settings') );

    }

    /**
     * If an instance exists, this returns it.  If not, it creates one and retuns it.
     *
     * @return SYSSY
     */
    public static function getInstance() {
        if ( !self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * load textdomain
     *
     * @return void
     */
    public function textdomain() {

        load_plugin_textdomain( 'syssy', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    }

    /**
     * create option menu
     *
     * @return void
     */
    function syssy_create_option_menu() {

        add_options_page(
            'SYSSY',
            'SYSSY',
            'manage_options',
            'syssy',
            array(
                $this,
                'syssy_options_page'
            )
        );

    }

    /**
     * register settings
     *
     * @return void
     */
    function syssy_register_settings(){

        //register api key setting
        register_setting(
            'syssy-settings-group',
            'syssy_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => NULL,
            )
        );

    }

    /**
     * create settings form
     *
     * @return void
     */
    function syssy_options_page() {

        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( _e( 'You do not have sufficient permissions to access this page.' ) );
        }

        $this->syssy_settings_page();

    }

    /**
     * create settings page
     *
     * @return void
     */
    function syssy_settings_page() {

        ?>

        <div class="wrap">
            <h1>SYSSY</h1>
            <h2><?php _e('Connect your website with SYSSY', 'syssy'); ?></h2>

            <form method="post" action="options.php">

                <?php settings_fields( 'syssy-settings-group' ); ?>
                <?php do_settings_sections( 'syssy-settings-group' ); ?>

                <p><?php _e('Please enter your API key, you can find it in your project settings at SYSSY.', 'syssy'); ?></p>

                <table class="form-table">

                    <tr valign="top">
                        <td scope="row">
                            <strong><?php _e('API key', 'syssy'); ?></strong>
                        </td>
                        <td>
                            <input style="width: 500px" type="text" name="syssy_api_key" value="<?php echo esc_attr( get_option('syssy_api_key') ); ?>" />
                        </td>
                    </tr>

                </table>

                <?php submit_button(); ?>

            </form>
        </div>

        <div class="website-info">

            <?php
            global $wpdb;

            $wordpressVersion = get_bloginfo( 'version' );
            $phpVersion = phpversion();

            $serverSoftware = $_SERVER['SERVER_SOFTWARE'];
            $dbVersion = $wpdb->get_var('SELECT VERSION();');

            $activePlugins = get_option('active_plugins');
            $plugins = get_plugins();

            ?>
            <h2><?php _e('Wordpress and system information', 'syssy'); ?></h2>
            <p><strong><?php _e('Wordpress Version', 'syssy') ?>: </strong><?php echo esc_attr($wordpressVersion) ?></p>
            <p><strong><?php _e('PHP Version', 'syssy') ?>: </strong><?php echo esc_attr($phpVersion) ?></p>
            <p><strong><?php _e('Server Software', 'syssy') ?>: </strong><?php echo esc_attr($serverSoftware) ?></p>
            <p><strong><?php _e('MySQL Version', 'syssy') ?>: </strong><?php echo esc_attr($dbVersion) ?></p>

            <h2><?php _e('Active plugins', 'syssy'); ?></h2>

            <ul>
                <?php foreach($activePlugins as $plugin){
                    ?><li><?php

                    if(isset($plugins[$plugin])){

                        ?>
                        <ul>
                            <li><strong><?php echo esc_attr($plugins[$plugin]['Name']) ?></strong> - Version: <?php echo esc_attr($plugins[$plugin]['Version']) ?></li>
                        </ul>
                        <?php
                    }
                    ?>
                    </li><?php
                }
                ?>
            </ul>

            <h2><?php _e('All installed plugins', 'syssy'); ?></h2>

            <ul>
                <?php foreach($plugins as $key => $value){

                    $plugin = $value;

                    ?><li>

                    <ul>
                        <li><strong><?php echo esc_attr($plugin['Name']) ?></strong> - Version: <?php echo esc_attr($plugin['Version']) ?></li>
                    </ul>
                    </li>

                    <?php

                }
                ?>
            </ul>
        </div>

        <?php

    }


    /**
     * create settings link for plugin page
     *
     * @param $links
     * @return mixed
     */
    function syssy_settings_link( $links ) {

        $url = esc_url( add_query_arg(
            'page',
            'syssy',
            get_admin_url() . 'admin.php'
        ) );

        $settings_link = '<a href="' . $url . '">' . __( 'Settings' ) . '</a>';

        array_push(
            $links,
            $settings_link
        );

        return $links;
    }


    /**
     * create infos for SYSSY
     *
     */
    function syssy_infos( $data ) {

        global $wpdb;

        //needed for older Wordpress
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $wordpressVersion = get_bloginfo( 'version' );
        $phpVersion = phpversion();

        $serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : "";
        $dbVersion = $wpdb->get_var('SELECT VERSION();');

        $activePlugins = get_option('active_plugins');
        $plugins = get_plugins();

        $siteurl = get_site_url();
        $homeurl = get_home_url();
        $pagetitle = get_bloginfo( 'name' );
		$hostname = gethostname();

        $infos['site-url'] = $siteurl;
        $infos['home-url'] = $homeurl;
        $infos['site-title'] = $pagetitle;
        $infos['cms-version'] = $wordpressVersion;
        $infos['php-version'] = $phpVersion;
        $infos['server-software'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : "";
        $infos['db-version'] = $wpdb->get_var('SELECT VERSION();');
        $infos['http-version'] = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : "";
        $infos['server-ip'] = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : "";
        $infos['server-port'] = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : "";
		$infos['hostname'] = $hostname;

        $installedPlugins = get_plugins();
        $activePlugins = get_option('active_plugins');
        $activePluginNames = array();

        $pluginList = array();

        $update_plugins_data = get_site_transient('update_plugins')->response;

        foreach($activePlugins as $plugin){
            $pluginName = explode("/", $plugin, 2);
            $activePluginNames[] = $pluginName[0];
        }

        if($installedPlugins) {
            foreach ($installedPlugins as $plugin => $plugininfo) {

                $pluginName = $plugininfo['TextDomain'];
                $pluginUri = $plugininfo['PluginURI'];
                $pluginVersion = $plugininfo['Version'];

                if($pluginName && ($pluginUri || $pluginVersion)) {

                    $pluginList[$plugin]['name'] = $pluginName;
                    $pluginList[$plugin]['title'] = $plugininfo['Title'];
                    $pluginList[$plugin]['version'] = $plugininfo['Version'];

                    if (in_array($plugin, $activePlugins)) {
                        $pluginList[$plugin]['active'] = 1;
                    } else {
                        $pluginList[$plugin]['active'] = 0;
                    }
                }

            }
        }

        if($update_plugins_data){
            foreach($update_plugins_data as $plugin){

                if(isset($plugin->plugin)) {

                    if(isset($pluginList[$plugin->plugin])) {

                        $pluginList[$plugin->plugin]['new_version'] = $plugin->new_version;

                        if ($plugin->new_version) {
                            $pluginList[$plugin->plugin]['needs_update'] = 1;
                        } else {
                            $pluginList[$plugin->plugin]['needs_update'] = 0;
                        }
                    }
                }

            }
        }

        $infos['plugininfo'] = $pluginList;

        $json = json_encode($infos);

        $apikey = get_option('syssy_api_key');

        $jwt = JWT::encode($json, JWT::urlsafeB64Encode($apikey), 'HS256');

        return $jwt;

    }

    /**
     * create endpoint for REST API
     *
     * @return void
     */
    function syssy_register_api(){

        /**
         * call https://www.domain.com/wp-json/syssy/v1/info/
         */
        register_rest_route( 'syssy/v1', '/info', array(
            'methods' => 'GET',
            'callback' => array($this, 'syssy_infos'),
            'permission_callback' => array($this, 'syssy_check_access')
        ));

    }

    /**
     * check if access is granted
     *
     * @return void
     */
    function syssy_check_access(WP_REST_Request $request){

        $apiToken = $request->get_header('Syssy-Api-Token');

        if($apiToken){
            try{
                $ApiTokenDecoded = JWT::decode($apiToken, JWT::urlsafeB64Encode(get_option('syssy_api_key')), array('HS256'));

                $wpApiToken = get_option('syssy_api_key');

                if($ApiTokenDecoded == $wpApiToken){
                    return true;
                }
            } catch(Exception $e){

                //show 404
                $this->syssy_redirect_404();
            }

        }
        else{

            //show 404
            $this->syssy_redirect_404();

        }

    }

    /**
     * redirect to 404 page
     *
     * @return void
     */
    function syssy_redirect_404(){

        header("Content-Type: text/html");

        global $wp_query;

        $wp_query->set_404();
        status_header( 404 );
        get_template_part( 404 );

        exit();

    }
}

$syssy = Syssy::getInstance();
