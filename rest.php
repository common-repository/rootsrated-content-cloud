<?php

require_once __DIR__ . '/includes/wp-posts/RootsratedWPPosts.php';
require_once __DIR__ . '/includes/SDK/RootsratedSDK.php';
require_once __DIR__ . '/includes/SDK/RootsratedWebhook.php';

$matcha_sdk = new RootsRatedSDK();

$matcha_abspath = dirname(dirname(dirname(dirname(__FILE__)))) . '/';
include_once($matcha_abspath . 'wp-load.php');

$matcha_plugin_options = get_option('rootsrated');
$matcha_posts = new RootsRatedWPPosts();
$matcha_sdk->setConfig($matcha_plugin_options);
$matcha_webhook = new RootsRatedWebhook();
$result = $matcha_webhook->executeHook(
  $matcha_webhook->getAllHeaders(),
  file_get_contents('php://input'),
  $matcha_posts,
  $matcha_sdk
);

?>
