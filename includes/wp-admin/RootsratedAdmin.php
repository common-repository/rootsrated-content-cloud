<?php
class RootsRatedAdmin  {

    /* ADMIN FUNCTIONS
    ----------------------------------------------------------------------------------*/

    public function addAdminStylesAndCSS()
    {
        wp_enqueue_script( 'rootsrated-main', ROOTSRATED_PLUGIN_URI . '/js/main-rr.js', array( 'jquery' ), 'v1.3', true );
        wp_enqueue_style( 'style_css', ROOTSRATED_PLUGIN_URI . '/css/main-rr.css', array(), '' );
    }

    /* ROOTSRATED OPTIONS PAGE
    ----------------------------------------------------------------------------------*/

    public function generateRootsRatedLandingPage($init_sdk, $connected)
    {
      $sdk = $init_sdk;
      $rootsRatedOptions = get_option('rootsrated');
      $sdk->setConfig( $rootsRatedOptions);

      $queryString = str_replace("amp;", "", $_SERVER['QUERY_STRING']);
      parse_str($queryString, $arr);
      $setupParam = $arr['setup'];
      $errorMessage = $arr['error'];

      if ($setupParam == "true") {
          return $this->generateRootsRatedSetupPage($sdk, $rootsRatedOptions);
      }

      $httpReferer = $_SERVER['HTTP_REFERER'];
      parse_str(explode("?", $httpReferer)[1], $httpRefererArr);
      $secretKey = html_entity_decode(preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($httpRefererArr['secret_key'])), null, 'UTF-8');

      if (!empty($httpRefererArr['key']) && !empty($secretKey) && !empty($httpRefererArr['token'])) {
          $rootsRatedOptions['rootsrated']['rootsrated_key'] = $httpRefererArr['key'];
          $rootsRatedOptions['rootsrated']['rootsrated_secret'] = $secretKey;
          $rootsRatedOptions['rootsrated']['rootsrated_auth_key'] = hash_hmac('sha256', base64_encode($httpRefererArr['key'] . ':' . $secretKey), '');
          $rootsRatedOptions['rootsrated']['rootsrated_token'] = $httpRefererArr['token'];
          $sdk->setKeyAndSecret($httpRefererArr['key'], $secretKey);
          $sdk->setToken($rootsRatedOptions['rootsrated']['rootsrated_token']);

          $posts = new RootsRatedWPPosts();
          $sdk->setImageUploadPath($rootsRatedOptions['rootsrated']['image_upload_path']);
          $webHook = new RootsRatedWebhook();
          $webHook->servicePhoneHome($posts, $sdk);

          $response = $sdk->getData('content');
          if ($response) {
              update_option('rootsrated', $rootsRatedOptions, false);

              if (count($response['response']['distributions']) > 0) {
                  $this->createPosts($response['response']['distributions'], $sdk);
              }

              $connected = true;
          } else {
              error_log("Matcha: content response invalid.\n");
              $errorMessage = 'Something went wrong! Plugin setup failed.';
              $connected = false;
          }
      }
      elseif (!empty($rootsRatedOptions['rootsrated']['rootsrated_secret'])
          && !empty($rootsRatedOptions['rootsrated']['rootsrated_auth_key'])
          && !empty($rootsRatedOptions['rootsrated']['rootsrated_token']))
      {
          $connected = true;
          $sdk->setToken($rootsRatedOptions['rootsrated']['rootsrated_token']);
          do_action('wp_print_scripts');
      }

      $pluginPath = str_replace(str_replace("https://", "http://", home_url()), "", plugins_url());
      $pluginPath = str_replace(str_replace("http://", "https://", home_url()), "", $pluginPath);
      $http_scheme = is_ssl() ? 'https' : 'http';
      $redirectUrl = $http_scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?page=matcha-landing-page';
      $permalinkStructure = get_option('permalink_structure');
      $matchaLoginUrl = 'https://blog-app.springbot.com/sso/login?wordpress_url=' . home_url() . '&wordpress_plugin_path=' . $pluginPath . '&wordpress_admin_url=' . $redirectUrl . '&wordpress_permalink_structure=' . urlencode($permalinkStructure);

      ?>
        <div id="rr-main">
          <div class="rr-brand-section">
            <img class="rr-matcha-logo"
                src="https://blog-app.springbot.com/assets/logos/logo-whitebg-vert-c836ff5f39cf89cf5688069b0b54675e34c438c78ac700af3fc71d45654bdc74.png"
                height: 40px
                width: 40px
            />
          </div>
          <div class="rr-form-section">
            <div class="rr-horizontal-line"></div>
              <?php if ($connected) { ?>
                <div class="rr-instruction-title">
                    Your WordPress site is connected!
                </div>
                <div class="rr-instruction-subtitle">
                    Your site is now set up to publish and track content from Matcha.
                    <div>Happy publishing!</div>
                </div>
                <button class="rr-button-matcha" onclick="location.href='https://blog-app.springbot.com'" type="button">
                  <span class="rr-button-matcha-text">
                    Go to Matcha
                  </span>
                </button>
              <?php } else { ?>
                <div class="rr-instruction-title">
                    Publish and track content from Matcha
                </div>
                <div class="rr-instruction-subtitle">
                    Finish setting up the Matcha plugin by connecting your
                    WordPress site with your Matcha account.
                </div>
              <?php if ($errorMessage && $errorMessage != '') { ?>
                <div class="rr-setup-failure-result">
                  <?php echo $errorMessage ?>
                </div>
              <?php } ?>
              <button class="rr-button-connect" onclick="location.replace('<?php echo $matchaLoginUrl ?>')" type="button">
                <span class="rr-button-connect-text">
                  Connect your website
                </span>
              </button>
            <?php } ?>
          </div>
        </div>
      <?php
    }

    public function generateRootsRatedSetupPage($sdk, $rootsRatedOptions)
    {
        $setupResultNotification = "";
        $setupResultClass = "display-none";

        if (!empty($_POST['rootsrated-key']) && !empty($_POST['rootsrated-secret']) && !empty($_POST['rootsrated-token'])) {
            $rootsRatedOptions['rootsrated']['rootsrated_key'] = sanitize_text_field($_POST['rootsrated-key']);
            $rootsRatedOptions['rootsrated']['rootsrated_secret'] = sanitize_text_field($_POST['rootsrated-secret']);
            $rootsRatedOptions['rootsrated']['rootsrated_auth_key'] = hash_hmac(
              'sha256',
              base64_encode($rootsRatedOptions['rootsrated']['rootsrated_key'] . ':' . $rootsRatedOptions['rootsrated']['rootsrated_secret']),
              ''
            );
            $rootsRatedOptions['rootsrated']['rootsrated_token'] = sanitize_text_field($_POST['rootsrated-token']);
            $sdk->setKeyAndSecret(
              $rootsRatedOptions['rootsrated']['rootsrated_key'],
              $rootsRatedOptions['rootsrated']['rootsrated_secret']
            );
            $sdk->setToken($rootsRatedOptions['rootsrated']['rootsrated_token']);

            $posts = new RootsRatedWPPosts();
            $sdk->setImageUploadPath($rootsRatedOptions['rootsrated']['image_upload_path']);
            $webHook = new RootsRatedWebhook();
            $webHook->servicePhoneHome($posts, $sdk);

            $response = $sdk->getData('content');
            if ($response) {
                update_option('rootsrated', $rootsRatedOptions, false);

                if (count($response['response']['distributions']) > 0) {
                    $this->createPosts($response['response']['distributions'], $sdk);
                }

                $setupResultNotification = "Success! Plugin is set up correctly.";
                $setupResultClass = "display-block";
            } else {
                error_log("Matcha: content response invalid.\n");
                $setupResultNotification = "Something went wrong! Plugin set up failed.";
                $setupResultClass = "display-block";
            }
        }
        elseif (!empty($rootsRatedOptions['rootsrated']['rootsrated_secret'])
            && !empty($rootsRatedOptions['rootsrated']['rootsrated_auth_key'])
            && !empty($rootsRatedOptions['rootsrated']['rootsrated_token']))
        {
            $setupResultNotification = "Success! Plugin is set up correctly.";
            $setupResultClass = "display-block";
            $sdk->setToken($rootsRatedOptions['rootsrated']['rootsrated_token']);
            do_action('wp_print_scripts');
        }
        elseif (!empty($_POST['rr-button-activate']))
        {
            error_log("Matcha: Activation failed due to missing POST data.\n");
            $setupResultNotification = "Something went wrong! Plugin set up failed.";
            $setupResultClass = "display-block";
        }
        elseif (!empty($_POST['rr-button-deactivate']))
        {

            delete_option();

            $setupResultNotification = "Success! Plugin is set up correctly.";
            $setupResultClass = "display-block";
        }

        $setupPageUrl = get_admin_url() . '?page=matcha-landing-page&setup=true';
        ?>

        <div id="rr-main">
          <div class="rr-brand-section">
            <img class="rr-matcha-logo"
              src="https://blog-app.springbot.com/assets/logos/logo-whitebg-vert-c836ff5f39cf89cf5688069b0b54675e34c438c78ac700af3fc71d45654bdc74.png"
              height: 40px
              width: 40px
            />
          </div>
          <div class="rr-form-section">
            <div class="rr-horizontal-line"></div>
            <div class="rr-instruction-title">
              Your WordPress plugin set-up
            </div>
            <div class="rr-instruction-subtitle">
              Your token, key, and secret key are used to connect
              your WordPress site to Matcha and should match the values
              found in your WordPress integration settings.
            </div>
          </div>
          <div class="rr-plugin-setup-notification">
            <div id="rr-setup-result" class="<?php echo $setupResultClass ?>">
              <?php echo $setupResultNotification ?>
            </div>
          </div>
          <form id="rr-activation-form" action="<?php echo $setupPageUrl ?>" method="post">
            <div class="rr-activation-form-field">
                <label class="rr-activation-form-label">Token</label>
                <input class="rr-activation-form-input" type="text" name="rootsrated-token"/>
            </div>
            <div class="rr-activation-form-field">
                <label class="rr-activation-form-label">Key</label>
                <input class="rr-activation-form-input" type="text" name="rootsrated-key"/>
            </div>
            <div class="rr-activation-form-field">
                <label class="rr-activation-form-label">Secret</label>
                <input class="rr-activation-form-input" type="text" name="rootsrated-secret"/>
            </div>
            <button type="submit" value="1" name="rr-button-activate" id="rr-button-activate">
                <span class="rr-button-text">
                    Save changes
                </span>
            </button>
          </form>
        </div>
      <?php
    }

    public function createPosts($postObjects, $sdk)
    {
        $count = 0;
        $posts = new RootsRatedWPPosts();

        $postType = $sdk->getPostType();

        foreach ($postObjects as $postObject)
        {

            $rrId = trim($$postObject['distribution']['id']);
            $launchAt = $$postObject['distribution']['launch_at'];


            $postId = $posts->getPostIdFromHook($rrId, $postType);

            if (empty($postId))
            {
                if ($posts->postGoLive($postObject, $launchAt,$rrId,  $postType))
                {
                    $count++;
                }
            }
        }

        return $count == count($postObjects);
    }
}
