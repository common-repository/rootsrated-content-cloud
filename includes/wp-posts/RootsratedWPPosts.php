<?php
require_once(__DIR__ . '/../SDK/RootsratedPosts.php');

class RootsRatedWPPosts implements RootsRatedPosts {

    // Public Functions
    public function postScheduling($distribution, $rrId, $postType)
    {
        $postId = $this->getPostIdFromHook($rrId, $postType);
        $this->createOrUpdatePost($distribution, $postId, false, 'draft');

        return $this->getPostIdFromHook($rrId, $postType);
    }

    public function postGoLive($distribution, $launchAt, $rrId, $postType)
    {

        $postId = $this->getPostIdFromHook($rrId, $postType);

        if (empty($postId))
        {
            $this->createOrUpdatePost($distribution);

            return $this->getPostIdFromHook($rrId, $postType);

        }
        else
        {
            $currentTime = new DateTime($launchAt);
            $utcTime = new DateTime($launchAt, new DateTimeZone("UTC")); // TO FIX - ADD TIMEZONE SELECTOR
            $arg = array(
                'ID' => $postId,
                'post_status' => 'publish',
                'post_date' => $currentTime->format('Y-m-d H:i:s'),
                'post_date_gmt' => $utcTime->format('Y-m-d H:i:s')
            );

            return wp_update_post($arg);
        }

    }

    public function postRevision($distribution,  $rrId, $postType, $scheduledAt)
    {
        $postId = $this->getPostIdFromHook($rrId, $postType);

        $currentTime = new DateTime($scheduledAt);
        if(get_option('timezone_string'))
        {
            $newTZ = new DateTimeZone(get_option('timezone_string')); //GET TIMEZONE SETTING FROM WP //
            $currentTime->setTimezone($newTZ); // ENSURE POST IS SCHEDULED IN PROPER TIMEZONE //
        }
        else
        {
            $newTZ = new DateTimeZone("UTC");
            $currentTime->setTimezone($newTZ);
        }
        $utcTime = new DateTime($scheduledAt, new DateTimeZone("UTC"));

        $content = $distribution;
        $expFlag = false;
        if ($content['meta']['template'] == 'Experience')
        {
            $expFlag = true;
        }

        $post_content = $expFlag ? $content['content']['review']['full_content'] : $content['content']['copy']['full_content'];

        $creditName = $this->checkFieldExistOnPostObject($content['images']['feature']['credit'], 'credit_name');
        if (!empty($creditName))
        {
            $creditUrl = $this->checkFieldExistOnPostObject($content['images']['feature']['credit'], 'credit_url');
            if (!empty($creditUrl))
            {
                $creditLine = '<p>Featured image provided by <a target="_blank" href="' . $creditUrl . '">' . $creditName . "</a></p>";
            }
            else
            {
                $creditLine = '<p>Featured image provided by ' . $creditName . "</p>";
            }

            $post_content .= $creditLine;
        }

        $arg = array(
            'post_title' => $content['title'],
            'post_date' => $currentTime->format('Y-m-d H:i:s'),
            'post_date_gmt' => $utcTime->format('Y-m-d H:i:s'),
            'post_type' => 'revision',
            'post_content' => $post_content,
            'post_parent' => $postId,
            'post_status' => 'inherit',
            'ping_status' => 'closed',
            'comment_status' => 'closed'
        );
        $postId = wp_insert_post($arg, $wp_error=true);
        if (is_wp_error($postId)) {
            error_log('Matcha: Failed to insert post in postRevision due to error ' . $postId->get_error_message());
            return false;
        }
        return $postId;
    }

    public function postUpdate($distribution, $rrId, $postType, $scheduledAt)
    {
        $postId = $this->getPostIdFromHook($rrId, $postType);
        $this->createOrUpdatePost($distribution, $postId, true);
        return $postId;
    }

    public function postRevoke($rrId, $postType)
    {

        $postId = $this->getPostIdFromHook($rrId, $postType);

        $this->delete_associated_media($postId);
        $this->deletePost($postId);

        if (get_current_user_id() == 0)
        {
            $email = get_bloginfo('admin_email ');
        }
        else
        {
            $current_user = wp_get_current_user();
            $email = $current_user->user_email;
        }

        $subject = 'Post revoked';
        $post = get_post($postId);
        if (!$post)
        {
            return false;
        }
        $message = 'Dear Account Owner:<br><br>Matcha has removed the following piece of content for use by partners.<br><br>' . $post->post_title .
            '<br><br>Please contact <a href="support@getmatcha.com">support@getmatcha.com</a> if you have any questions -- we\'re happy to explain in more detail.<br><br>
            Thank You!';
        $headers[] = 'From: Matcha Support <support@getmatcha.com>';

        return wp_mail($email,$subject, $message);
    }

    public function getInfo()
    {
        $info = array();

        global $wp_version;

        $info['db_version'] = $wp_version;
        $info['siteurl'] = get_option('siteurl');
        $info['home'] = get_option('home');
        $user_id = username_exists('rootsrated');
        if ($user_id) {
            $info['username_exists'] = true;
            $info['publish_posts'] = user_can($user_id, 'publish_posts');
            $info['delete_published_posts'] = user_can($user_id, 'delete_published_posts');
        } else {
            $info['username_exists'] = false;
            $info['publish_posts'] = false;
            $info['delete_published_posts'] = false;
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $info['plugins'] = get_plugins();

        if ( !function_exists('plugins_url') ) {
            require_once ABSPATH . 'wp-includes/link-template.php';
        }
        $info['plugins_url'] = plugins_url();

        $wp_upload_dir = wp_upload_dir();
        if ($wp_upload_dir && is_array($wp_upload_dir) && $wp_upload_dir['error']) {
            $info['upload_path'] = $wp_upload_dir['error'];
        } else if ($wp_upload_dir && is_array($wp_upload_dir) && $wp_upload_dir['path']) {
            $info['upload_path'] = $wp_upload_dir['path'];
        } else {
            $info['upload_path'] = 'missing!';
        }

        return $info;
    }



    public function deletePost($postId)
    {
        wp_delete_post($postId, true);
        delete_post_meta($postId, '_is_rootsrated');
        delete_post_meta($postId, '_rootsrated_id');
        delete_post_meta($postId, '_rootsrated_content_id');
        delete_post_meta($postId, '_rootsrated_url');
        delete_post_meta($postId, '_rootsrated_author');
        delete_post_meta($postId, '_rootsrated_descriptors');
        delete_post_meta($postId, '_rootsrated_gallery');
        delete_post_meta($postId, '_rootsrated_seo');
        delete_post_meta($postId, '_rootsrated_campaigns');
        delete_post_meta($postId, '_rootsrated_tags');
        delete_post_meta($postId, '_rootsrated_geo');
        delete_post_meta($postId, '_rootsrated_activities');
        delete_post_meta($postId, '_rootsrated_personas');
        delete_post_meta($postId, '_rootsrated_experience');
        delete_post_meta($postId, '_rootsrated_shedTime');
        delete_post_meta($postId, '_wp_attached_file');
        delete_post_meta($postId, '_wp_attachment_image_alt');
        delete_post_meta($postId, '_wp_attachment_metadata');
        delete_post_meta($postId, '_thumbnail_id');
        delete_post_meta($postId, '_rootsrated_snippet');

    }

    public function delete_associated_media($parentPostId)
    {
        global $post;

        if(empty($parentPostId) && !empty($post->ID))
        {
            global $post;
            $parentPostId = $post->ID;
        }

        if(empty($parentPostId))
        {
            return false;
        }

        $media = get_children( array(
            'post_parent' => $parentPostId,
            'post_type'   => 'attachment'
        ) );

        if(empty($media))
        {
            return false;
        }

        foreach($media as $file)
        {
            wp_delete_attachment( $file->ID, true );
        }

        return true;
    }

    public function addSnippetToContent($content)
    {
        global $post;

        $data = get_post_meta($post->ID, '_rootsrated_snippet');
        $snippet = "";
        if ($data != false)
        {
            $snippet = $data[0];
        }

        return $content . $snippet;
    }

    public function createOrUpdatePost($postObject, $postId = 0, $update = false, $status = 'publish')
    {

        error_log('Matcha: Attempting to create or update post');
        $posts = $this;
        if (is_array($postObject) && array_key_exists('title', $postObject) == false)
        {
            error_log('Matcha: postObject is invalid when trying to create or update post');
            return 0;
        }
        $expFlag = false;
        if ($postObject['meta']['template'] == 'Experience')
        {
            $expFlag = true;
        }

        $users = new RootsRatedUsers;
        $userId = $users->getOrCreatePluginUser();

        $postArgs['post_title'] = $postObject['title'];
        $currentTime = new DateTime($postObject['distribution']['scheduled_at']);
        if(get_option('timezone_string'))
        {
            $newTZ = new DateTimeZone(get_option('timezone_string')); /* GET TIMEZONE SETTING FROM WP */
            $currentTime->setTimezone($newTZ); /* ENSURE POST IS SCHEDULED IN PROPER TIMEZONE */
        }
        else
        {
            $newTZ = new DateTimeZone("UTC");
            $currentTime->setTimezone($newTZ);
        }
        $utcTime = new DateTime($postObject['distribution']['scheduled_at'], new DateTimeZone("UTC"));

        $postArgs['post_date'] = $currentTime->format('Y-m-d H:i:s');
        $postArgs['post_date_gmt'] = $utcTime->format('Y-m-d H:i:s');
        $postArgs['post_content'] = $expFlag ? $postObject['content']['review']['full_content'] : $postObject['content']['copy']['full_content'];
        $postArgs['post_status'] = $status;
        $postArgs['post_type'] = 'post';
        $postArgs['post_author'] =  $userId;
        $creditName = $posts->checkFieldExistOnPostObject($postObject['images']['feature']['credit'], 'credit_name');
        if (!empty($creditName))
        {
            $creditUrl = $posts->checkFieldExistOnPostObject($postObject['images']['feature']['credit'], 'credit_url');
            if (!empty($creditUrl))
            {
                $creditLine = '<p>Featured image provided by <a target="_blank" href="' . $creditUrl . '">' . $creditName . "</a></p>";
            }
            else
            {
                $creditLine = '<p>Featured image provided by ' . $creditName . "</p>";
            }

            $postArgs['post_content'] .= $creditLine;
        }

        $snippet = $posts->checkFieldExistOnPostObject($postObject, 'snippet');

        if ($update && !empty($postId))
        {
            $postArgs['ID'] = $postId;
        }

        $postId = wp_insert_post($postArgs, $wp_error=true);
        if (is_wp_error($postID)) {
            error_log('Matcha: Unable to get or create post with error ' . $postId->get_error_message());
            return false;
        } else {
            update_post_meta($postId, '_is_rootsrated', 1);
            update_post_meta($postId, '_rootsrated_id', $posts->checkFieldExistOnPostObject($postObject, 'id'));
            update_post_meta($postId, '_rootsrated_content_id', $posts->checkFieldExistOnPostObject($postObject, 'content_id'));
            update_post_meta($postId, '_rootsrated_url', $posts->checkFieldExistOnPostObject($postObject, 'rootsrated_url'));
            update_post_meta($postId, '_rootsrated_author', $posts->checkFieldExistOnPostObject($postObject, 'author'));
            update_post_meta($postId, '_rootsrated_descriptors', $posts->checkFieldExistOnPostObject($postObject, 'descriptors'));
            update_post_meta($postId, '_rootsrated_gallery', $posts->checkFieldExistOnPostObject($postObject['images'], 'gallery'));
            update_post_meta($postId, '_rootsrated_seo', $posts->checkFieldExistOnPostObject($postObject, 'seo'));
            update_post_meta($postId, '_rootsrated_campaigns', $posts->checkFieldExistOnPostObject($postObject, 'campaigns'));
            update_post_meta($postId, '_rootsrated_tags', $posts->checkFieldExistOnPostObject($postObject, 'tags'));
            update_post_meta($postId, '_rootsrated_geo', $posts->checkFieldExistOnPostObject($postObject['segments'], 'geo'));
            update_post_meta($postId, '_rootsrated_activities', $posts->checkFieldExistOnPostObject($postObject['segments'], 'activities'));
            update_post_meta($postId, '_rootsrated_personas', $posts->checkFieldExistOnPostObject($postObject['segments'], 'personas'));
            update_post_meta($postId, '_rootsrated_experience', $expFlag );
            update_post_meta($postId, '_rootsrated_shedTime', $posts->checkFieldExistOnPostObject($postObject, 'shedTime'));
            update_post_meta($postId, '_rootsrated_snippet', $snippet);
            if ($update) {
                $this->delete_associated_media($postId);
            }
            if (!empty($postObject['images']['feature'])) {
                $posts->addFeaturedImageToPost($postId, $postObject['images']['feature'], $this->imageUploadPath);
            }
            wp_save_post_revision( $postId );
            error_log('Matcha: Created or updated post successfully');
        }
        return true;
    }

    public function getPostIdFromHook($rrPostId, $postType)
    {
        $query = new WP_Query(array('post_status' => 'any','meta_key'=>'_rootsrated_id', 'meta_value' => $rrPostId, 'ignore_sticky_posts' => 1,  'post_type' => $postType ));
        $postId = 0;
        while ($query->have_posts())
        {
            $query->the_post();
            $postId = get_the_ID();
        }

        wp_reset_postdata();

        return $postId;
    }

    private function checkFieldExistOnPostObject($postObject, $field)
    {
        if (array_key_exists($field, $postObject) && count($postObject[$field]) > 0)
        {
            return $postObject[$field];
        }
        else
        {
            return '';
        }
    }

    public function distributionUrls()
    {
        $matcha_posts_query_args = array(
            'meta_query' => array(
                array(
                    'key' => '_is_rootsrated',
                    'value' => '1'
                )
            )
        );

        $matcha_posts_query = new WP_Query($matcha_posts_query_args);

        $dist_urls = array();
        foreach ($matcha_posts_query->posts as $post) {
            $dist_urls[] = array(
                'distribution_token' => $post->_rootsrated_id,
                'distribution_url' => get_permalink($post)
            );
        }

        return $dist_urls;
    }

    private function addFeaturedImageToPost($postId, $imageObject, $uploadImagePath) {
        // Get feature image URL from object and confirm a feature image is present
        $image_url = $imageObject['sizes']['large_natural'];
        if (empty($image_url)) {
          return false;
        }

        // Get local filepath for feature image
        $upload_dir = wp_upload_dir();
        $data = explode('?', $image_url);
        if (!is_array($data) || count($data) == 0) {
            error_log('Matcha: Failed to add feature image ' . $image_url);
            return false;
        }
        $filename = basename($data[0]); // Create image file name
        $file = $upload_dir['path'] . '/' . $filename;

        // Download image from Matcha
        $response = wp_remote_get($image_url);
        if (is_wp_error($response)) {
            error_log('Unable to download feature image');
            return false;
        } else {
            $image_body = wp_remote_retrieve_body($response);
            error_log('We got the image body!');
        }

        // Write file to WP upload directory
        file_put_contents($file, $image_body);

        $wpFiletype = wp_check_filetype( $filename, null );

        $attachment = array(
            'post_mime_type' => $wpFiletype['type'],
            'post_title'     => $imageObject['title'],
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        if (!empty($imageObject['caption']))
        {
            $attachment['post_excerpt'] = $imageObject['caption'];
        }

        $attachId = wp_insert_attachment( $attachment, $file, $postId);

        update_post_meta($attachId, '_wp_attached_file', $file);
        update_post_meta($attachId, '_wp_attachment_image_alt', $imageObject['alt']);

        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachmentData = wp_generate_attachment_metadata( $attachId, $file );

        if (!$attachmentData) {
          error_log('Matcha: Failed to create image attachment metadata');
          return false;
        }

        $attachmentData['image_meta']['credit'] = !empty($imageObject['credit']['credit_name']) ? $imageObject['credit']['credit_name'] : '';
        $attachmentData['image_meta']['credit_url'] = !empty($imageObject['credit']['credit_url']) ? $imageObject['credit']['credit_url'] : '';

        wp_update_attachment_metadata( $attachId, $attachmentData );

        set_post_thumbnail( $postId, $attachId );

        return true;
    }
}
