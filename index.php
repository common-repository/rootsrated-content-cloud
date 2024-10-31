<?php
/*
Plugin Name:  Matcha
Plugin URI:   https://wordpress.org/plugins/rootsrated-content-cloud/
Description:  Seamlessly connect your Wordpress site with Matcha
Version:      1.7
Author:       matchacontent
Author URI:   https://getmatcha.com/
License:      GPL2
*/

define('ROOTSRATED_PLUGIN_URI', plugin_dir_url( __FILE__ ));
define('ROOTSRATED_PLUGIN_DIR', plugin_dir_path( __FILE__ ));

require_once ROOTSRATED_PLUGIN_DIR . '/includes/RootsratedInit.php';
require_once ROOTSRATED_PLUGIN_DIR . '/includes/wp-admin/RootsratedAdmin.php';
require_once ROOTSRATED_PLUGIN_DIR . '/includes/wp-user/RootsratedUsers.php';
require_once ROOTSRATED_PLUGIN_DIR . '/includes/wp-posts/RootsratedWPPosts.php';
require_once ROOTSRATED_PLUGIN_DIR . '/includes/SDK/RootsratedSDK.php';
require_once ROOTSRATED_PLUGIN_DIR . '/includes/SDK/RootsratedWebhook.php';
require_once ROOTSRATED_PLUGIN_DIR . '/includes/SDK/RootsratedError.php';

$rootsRated= new RootsratedInit();
register_activation_hook(__FILE__, array('RootsratedInit','activationPlugin'));
register_uninstall_hook(__FILE__,  array('RootsratedInit', 'uninstallPlugin'));
register_deactivation_hook(__FILE__,  array('RootsratedInit', 'deactivationPlugin'));
?>
