<?php
class RootsRatedUsers {
  public function checkUserExists($slug) {
    $user = get_user_by('slug', $slug);
    return $user === false ? false : $user->ID;
  }

  public function createMatchaUser() {
    $userPass = wp_generate_password();
    $userData = array('user_pass' => $userPass
    , 'user_login' => 'Matcha'
    , 'user_nicename' => ''
    , 'user_url' => ''
    , 'user_email' => 'content@getmatcha.com'
    , 'display_name' => 'Matcha'
    , 'nickname' => 'matcha'
    , 'first_name' => 'Matcha'
    , 'last_name' => 'Content'
    , 'description' => ''
    , 'rich_editing' => true
    , 'user_registered' => date('Y-m-d H:i:s')
    , 'role' => 'administrator'
    , 'jabber' => ''
    , 'aim' => ''
    , 'yim' => ''
    );
    $userId = wp_insert_user($userData);
    if (is_wp_error($userId)) {
        error_log('Matcha: Unable to create user due to error ' . $userId->get_error_message());
        return false;
    } else {
        $data = get_bloginfo('admin_email');
        $adminEmail = $data[0];
        wp_mail($adminEmail, 'User was created', 'User was created with user login Matcha and password ' . $userPass);
    }
    return $userId;
  }

  public function getOrCreatePluginUser() {
    $userId = $this->checkUserExists('matcha');
    if ($userId) {
      return $userId;
    }

    $userId = $this->checkUserExists('rootsrated');
    if ($userId) {
      return $userId;
    }

    return $this->createMatchaUser();
  }
}
