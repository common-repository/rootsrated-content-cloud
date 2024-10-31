<?php
class RootsratedInit
{
    private static $rootsRated;
    private static $admin;
    private static $users;
    private static $posts;
    private static $sdk;
    private static $rootsRatedXmlRpc;

    public function __construct()
    {
        self::$admin = new RootsRatedAdmin();
        self::$posts = new RootsRatedWPPosts();
        self::$sdk = new RootsRatedSDK();
        self::$users = new RootsRatedUsers();

        $rootsRatedOptions = get_option('rootsrated');
        self::$sdk->setConfig( $rootsRatedOptions);

        /* THESE ARE WORDPRESS BASED ACTIONS. */
        add_action('admin_menu', array($this,'addPluginMenuToAdminPanel'));
        add_action('admin_enqueue_scripts', array($this, 'addAdminStylesAndCSS'));
        add_action('wp_footer', array($this, 'wpFooterHookCallback'));
        add_action('wp_head', array($this, 'wpHeadHookCallback' ));
        add_action('before_delete_post', array($this, 'delete_associated_media') );
        add_action('init', array($this, 'init') );
        /* THESE ARE WORDPRESS BASED ACTIONS. */
    }

    public static function init() {
        /* THESE ARE WORDPRESS BASED ACTIONS / FILTERS. */
        add_filter('the_content', array(self::$posts, 'addSnippetToContent'));
        add_action('post_updated', 'wp_save_post_revision');
        /* THESE ARE WORDPRESS BASED ACTIONS / FILTERS. */

        // create RootsRated user if it does not exist
        $userId = self::$users->getOrCreatePluginUser();
    }

    public static function activationPlugin() {
        $userId = self::$users->getOrCreatePluginUser();

        if (empty($userId)) {
          deactivate_plugins(plugin_basename( __FILE__ ));
          wp_die('Unable to create Matcha user. If an existing Matcha user exists, please delete and try again.');
        }
    }

    public static function deactivationPlugin() {
        delete_option('rootsrated');
    }

    public static function delete_associated_media($postid)
    {
        self::$posts->delete_associated_media($postid);
    }

    public static function uninstallPlugin()
    {
        self::deactivationPlugin();
    }

    public static function addPluginMenuToAdminPanel() {
        /* THESE ARE WORDPRESS BASED FUNCTIONS. */
        $rootsRatedOptions = get_option('rootsrated');
        if ((!empty($rootsRatedOptions['rootsrated']['rootsrated_secret'])
                && !empty($rootsRatedOptions['rootsrated']['rootsrated_auth_key'])
                && !empty($rootsRatedOptions['rootsrated']['rootsrated_token']))
            || (!empty($_POST['rootsrated-key']) && !empty($_POST['rootsrated-secret']) && !empty($_POST['rootsrated-token'])))
        {
            add_menu_page('Matcha', 'Matcha', 8, 'matcha-landing-page', array('RootsRatedInit', 'generateRootsRatedConnectedLandingPage'));
        } else {
            add_menu_page('Matcha', 'Matcha', 8, 'matcha-landing-page', array('RootsRatedInit', 'generateRootsRatedDisconnectedLandingPage'));
        }
        /* THESE ARE WORDPRESS BASED FUNCTIONS. */
    }

    public static function generateRootsRatedConnectedLandingPage()
    {
        self::$admin->generateRootsRatedLandingPage(self::$sdk, true);
    }

    public static function generateRootsRatedDisconnectedLandingPage()
    {
        self::$admin->generateRootsRatedLandingPage(self::$sdk, false);
    }

    public static function addAdminStylesAndCSS()
    {
        self::$admin->addAdminStylesAndCSS();
    }

    public static function wpFooterHookCallback()
    {

    }

    public static function wpHeadHookCallback()
    {
        $result = self::$sdk->siteJavascript();
        echo $result;
    }

    public static function initHooks()
    {
        $methods['rootsrated.getHooks'] = array('RootsRatedInit', 'getHooks');

    }

    public function getHook()
    {
        self::$rootsRatedXmlRpc->getHooks();
    }
}
