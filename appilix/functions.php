<?php

$host = "https://www.appilix.com";

/* Register activation hook */
function appilix_init()
{
    add_option( 'appilix_do_activation_redirect', true);
}
/* redirect to plugin page after activation */
function appilix_activation_redirect() {
    if (get_option('appilix_do_activation_redirect', false)) {
        delete_option('appilix_do_activation_redirect');
        exit( wp_redirect( admin_url('options-general.php?page=appilix-index')));
    }
}
/* add settings submenu for plugin */
function appilix_add_menu()
{
    add_options_page('Appilix App Builder', 'Appilix App Builder', 'manage_options', 'appilix-index', 'appilix_index');
}
/* Register stylesheets and scripts */
function appilix_styles_and_scripts() {
    $appilix_version = "1.0";
    /* stylesheets */
    wp_enqueue_style('appilix_custom', plugins_url('styles/css/style.css', __FILE__), array(), $appilix_version);
}
/* action links on plugin page */
function appilix_action_links($links) {
    $links = array_merge( array(
        '<a href="' . esc_url(admin_url( '/options-general.php?page=appilix-index')) . '">Instruction</a>'
    ), $links );
    return $links;
}


/* For Select2 from Builder */
function appilix_check_connection(WP_REST_Request $request)
{
    $request->sanitize_params();
    $key= $request->get_param( 'key' );
    $arguments = isset($key) ? array("key" => $key) : array();
    $url = sprintf("%s?%s", $GLOBALS['host']."/app/check_connection.php", http_build_query($arguments));
    $response = wp_remote_get($url);
    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
        $jsonResponse = json_decode($response['body'], true);
        if($jsonResponse != Null){
            if($jsonResponse['status'] == "true"){
                $result = array("status" => 'true');
            }else{
                $result = array("status" => 'false', "msg" => $jsonResponse['msg']);
            }
        }else{
            $result = array("status" => 'false', "msg" => "Bad response from Appilix Server");
        }
    }else{
        $result = array("status" => 'false', "msg" => "Could not connect to Appilix Server");
    }
    return $result;
}

/* For Select2 from Builder */
function appilix_get_categories(WP_REST_Request $request)
{
    $request->sanitize_params();
    $catArr = array();
    $cat_ids = $request->get_param( 'ids' );
    $limit = $request->get_param( 'limit' );
    $search = $request->get_param( 'search' );
    $listCategories = get_categories( array(
        "hide_empty" => false,
        'orderby' => 'name',
        'order'   => 'ASC',
        'include'   => isset($cat_ids) ? $cat_ids : "all",
        'name__like' => isset($search) ? $search : "",
    ));
    if(isset($limit)){
        if(is_numeric($limit)){
            $listCategories = array_slice($listCategories, 0, $limit, true);
        }
    }
    if (sizeof($listCategories) > 0) {
        foreach( $listCategories as $singleCategory ) {
            $catArr[] = array("cat_id" => $singleCategory->term_id,
                "cat_name" => $singleCategory->name);
        }
    }
    $result = array("status" => 'true', "categories"=>$catArr);
    return $result;
}


/* For Load More Comments from App */
function appilix_get_comments(WP_REST_Request $request){
    $request->sanitize_params();
    $post_id = $request->get_param( 'post_id' );
    $page = $request->get_param( 'page' );
    $comment_display_photo = $request->get_param( 'comment_display_photo' );
    $comment_display_datetime = $request->get_param( 'comment_display_datetime' );
    $comment_per_load = $request->get_param( 'comment_per_load' );

    if(isset($post_id) && isset($page) && isset($comment_display_photo) && isset($comment_display_datetime) && isset($comment_per_load)){
        $result = array("status" => 'true', "comments"=> appilix_generate_comments($post_id, $comment_display_photo, $comment_display_datetime, $page, $comment_per_load));
    }else{
        $result = array("status" => 'false', "msg"=>"All parameters required.");
    }
    return $result;
}

/* For Load More Category Posts from App */
function appilix_get_category_posts(WP_REST_Request $request){
    $request->sanitize_params();
    $cat_id = $request->get_param( 'cat_id' );
    $page = $request->get_param( 'page' );
    $display_date = $request->get_param( 'display_date' );
    $display_category = $request->get_param( 'display_category' );
    $post_per_load = $request->get_param( 'post_per_load' );

    if(isset($cat_id) && isset($page) && isset($display_date) && isset($display_category) && isset($post_per_load)){
        $result = array("status" => 'true', "posts"=> appilix_generate_category_posts($cat_id, $display_date, $display_category, $page, $post_per_load));
    }else{
        $result = array("status" => 'false', "msg"=>"All parameters required.");
    }
    return $result;
}


/* For Load More Bookmarks Posts from App */
function appilix_get_bookmarks_posts(WP_REST_Request $request){
    $request->sanitize_params();
    $post_ids = $request->get_param( 'post_ids' );
    $allowedPostIds = explode(",", $post_ids);
    if($allowedPostIds != Null) {
        if (sizeof($allowedPostIds) > 0) {
            $page = $request->get_param( 'page' );
            $display_date = $request->get_param( 'display_date' );
            $display_category = $request->get_param( 'display_category' );
            $post_per_load = $request->get_param( 'post_per_load' );

            if(isset($page) && isset($display_date) && isset($display_category) && isset($post_per_load)){
                $result = array("status" => 'true', "posts"=> appilix_generate_bookmarks_posts($allowedPostIds, $display_date, $display_category, $page, $post_per_load));
            }else{
                $result = array("status" => 'false', "msg"=>"All parameters required.");
            }
        }else{
            $result = array("status" => 'true', "posts"=> array());
        }
    }else{
        $result = array("status" => 'true', "posts"=> array());
    }

    return $result;
}


/* For Posting Comments from App */
function appilix_post_comment(WP_REST_Request $request){
    $request->sanitize_params();
    $post_id = $request->get_param( 'post_id' );
    $user_name = $request->get_param( 'user_name' );
    $user_email = $request->get_param( 'user_email' );
    $comment = $request->get_param( 'comment' );

    if(isset($post_id) && isset($user_name) && isset($user_email) && isset($comment)){
        $comment = htmlentities($comment);
        $userIp = $_SERVER['REMOTE_ADDR'];
        $listPrevComments = get_comments( array( 'date_query' => array(array('after' => '-5 minutes')), 'post_id' =>  $post_id, 'author_email' =>  $user_email) );
        if(sizeof($listPrevComments) < 4){
            wp_insert_comment( array('comment_approved' => 0, 'comment_author' => $user_name, 'comment_author_email' => $user_email,
                'comment_author_IP' => $userIp, 'comment_content' => $comment, 'comment_post_ID' => $post_id) );
        }
        $result = array("status" => 'true');
    }else{
        $result = array("status" => 'false');
    }
    return $result;
}

/* For Select2 from Builder */
function appilix_get_pages(WP_REST_Request $request)
{
    $request->sanitize_params();
    $pageArr = array();
    $page_ids = $request->get_param( 'ids' );
    $limit = $request->get_param( 'limit' );
    $search = $request->get_param( 'search' );
    $listPages = get_posts( array(
        'order'   => 'ASC',
        'orderby' => 'post_title',
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_password'   => '',
        'numberposts'   => isset($limit) ? $limit : "10",
        'include'   => isset($page_ids) ? $page_ids : array(),
        's' => isset($search) ? $search : "",
    ));
    if (sizeof($listPages) > 0) {
        foreach( $listPages as $singlePage ) {
            $pageArr[] = array("page_id" => $singlePage->ID,
                "page_title" => $singlePage->post_title);
        }
    }
    $result = array("status" => 'true', "pages"=>$pageArr);
    return $result;
}


/* For Select2 from Builder */
function appilix_get_articles(WP_REST_Request $request)
{
    $request->sanitize_params();
    $postsArr = array();
    $post_ids = $request->get_param( 'ids' );
    $limit = $request->get_param( 'limit' );
    $search = $request->get_param( 'search' );

    $listPosts = get_posts( array(
        'order'   => 'ASC',
        'orderby' => 'post_title',
        'post_type'    => 'post',
        'post_status'  => 'publish',
        'post_password'   => '',
        'numberposts'   => isset($limit) ? $limit : "10",
        'include'   => isset($post_ids) ? $post_ids : array(),
        's' => isset($search) ? $search : "",
    ));
    if (sizeof($listPosts) > 0) {
        foreach( $listPosts as $singlePost ) {
            $postsArr[] = array("post_id" => $singlePost->ID,
                "post_title" => $singlePost->post_title);
        }
    }
    $result = array("status" => 'true', "posts"=>$postsArr);
    return $result;
}

function appilix_register_firebase_token(WP_REST_Request $request)
{
    $request->sanitize_params();
    $key= $request->get_param( 'key' );
    $token = $request->get_param( 'token' );
    $arguments = array("key" => $key, "token" => $token);
    $url = sprintf("%s?%s", $GLOBALS['host']."/app/register_token.php", http_build_query($arguments));
    $response = wp_remote_get($url);
    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
        $jsonResponse = json_decode($response['body'], true);
        if($jsonResponse != Null){
            if($jsonResponse['status'] == "true"){
                $result = array("status" => 'true');
            }else{
                $result = array("status" => 'false', "msg" => $jsonResponse['msg']);
            }
        }else{
            $result = array("status" => 'false', "msg" => "Bad response from Appilix Server");
        }
    }else{
        $result = array("status" => 'false', "msg" => "Could not connect to Appilix Server");
    }
    return $result;
}


function appilix_get_layout(WP_REST_Request $request)
{
    $request->sanitize_params();
    $key= $request->get_param( 'key' );
    $layout_id = $request->get_param( 'layout_id' );
    $layout_type = $request->get_param( 'layout_type' );
    $arguments = isset($layout_id) ? array("key" => $key, "layout_id" => $layout_id) : array("key" => $key, "layout_type" => $layout_type );
    $url = sprintf("%s?%s", $GLOBALS['host']."/app", http_build_query($arguments));
    $response = wp_remote_get($url);
    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
        $jsonResponse = json_decode($response['body'], true);
        if($jsonResponse != Null){
            if($jsonResponse['status'] == "true"){
                /* Recreate Layout Blocks */
                $blocksList = array();
                $list_blocks = $jsonResponse['blocks'];
                if ($list_blocks != Null) {
                    if (sizeof($list_blocks) > 0) {
                        foreach ($list_blocks as $single_block) {
                            $blocksList[] = array("id" => $single_block['id'],
                                "type" => $single_block['type'],
                                "position" => $single_block['position'],
                                "settings" => $single_block['settings'],
                                "data" => appilix_process_block($single_block, $request));
                        }
                    }
                }
                /* Recreate Layout Blocks */

                $result = array("status" => 'true', "blocks" => $blocksList);
            }else{
                $result = array("status" => 'false', "msg" => $jsonResponse['msg']);
            }
        }else{
            $result = array("status" => 'false', "msg" => "Bad response from Appilix Server");
        }
    }else{
        $result = array("status" => 'false', "msg" => "Could not connect to Appilix Server");
    }
    return $result;
}


function appilix_process_block($single_block, WP_REST_Request $request)
{
    $request->sanitize_params();
    $category_block_types = array("cat_list", "cat_carousel");
    $post_block_types = array("post_list", "post_grid", "post_carousel");
    $related_post_block_types = array("related_post_list", "related_post_grid", "related_post_carousel");
    $full_article_block_types = array("article_view");
    $custom_html_block_types = array("custom_html");
    $comment_block_types = array("comments_list");
    $category_posts_types = array("cat_post_list", "cat_post_grid");
    $bookmarks_posts_types = array("bookmarks_post_list", "bookmarks_post_grid");
    $search_posts_types = array("search_post_list", "search_post_grid");
    $page_block_types = array("page_view");

    if(in_array($single_block['type'], $category_block_types)){

        $jsonCategories = Null;
        $limit = Null;
        foreach ($single_block['settings'] as $single_setting) {
            if($single_setting['type'] == "categories"){
                $jsonCategories = json_decode($single_setting['value'], true);
            }
            if($single_setting['type'] == "limit"){
                $limit = $single_setting['value'];
            }
        }
        return appilix_generate_categories($jsonCategories, $limit);

    }else if(in_array($single_block['type'], $post_block_types)){

        $jsonCategories = Null;
        $limit = Null;
        $display_date = Null;
        $display_category = Null;
        foreach ($single_block['settings'] as $single_setting) {
            if($single_setting['type'] == "categories"){
                $jsonCategories = json_decode($single_setting['value'], true);
            }
            if($single_setting['type'] == "limit"){
                $limit = $single_setting['value'];
            }
            if($single_setting['type'] == "display_date"){
                $display_date = $single_setting['value'];
            }
            if($single_setting['type'] == "display_category"){
                $display_category = $single_setting['value'];
            }
        }
        return appilix_generate_posts($jsonCategories, $limit, $display_date, $display_category);

    }else if(in_array($single_block['type'], $related_post_block_types)){

        $post_id = $request->get_param( 'post_id' );
        $post_id = isset($post_id) ? $post_id : 0;
        $limit = Null;
        $display_date = Null;
        $display_category = Null;
        foreach ($single_block['settings'] as $single_setting) {
            if($single_setting['type'] == "limit"){
                $limit = $single_setting['value'];
            }
            if($single_setting['type'] == "display_date"){
                $display_date = $single_setting['value'];
            }
            if($single_setting['type'] == "display_category"){
                $display_category = $single_setting['value'];
            }
        }
        return appilix_generate_related_posts($post_id, $limit, $display_date, $display_category);

    }else if(in_array($single_block['type'], $full_article_block_types)){

        $post_id = $request->get_param( 'post_id' );
        $post_id = isset($post_id) ? $post_id : 0;
        $display_date = Null;
        $display_category = Null;
        $display_author = Null;
        $display_comment_count = Null;
        foreach ($single_block['settings'] as $single_setting) {
            if($single_setting['type'] == "display_author"){
                $display_author = $single_setting['value'];
            }
            if($single_setting['type'] == "display_date"){
                $display_date = $single_setting['value'];
            }
            if($single_setting['type'] == "display_category"){
                $display_category = $single_setting['value'];
            }
            if($single_setting['type'] == "display_comment_count"){
                $display_comment_count = $single_setting['value'];
            }
        }
        return appilix_generate_article($post_id, $display_date, $display_category, $display_author, $display_comment_count);

    }else if(in_array($single_block['type'], $custom_html_block_types)){

        $custom_html = Null;
        foreach ($single_block['settings'] as $single_setting) {
            if($single_setting['type'] == "custom_html"){
                $custom_html = $single_setting['value'];
            }
        }
        return appilix_generate_custom_html_view($custom_html);

    }else if(in_array($single_block['type'], $comment_block_types)){

        $post_id = $request->get_param( 'post_id' );
        $post_id = isset($post_id) ? $post_id : 0;
        $page = $request->get_param( 'page' );
        $page = isset($page) ? $page : 1;
        $comment_display_photo = Null;
        $comment_display_datetime = Null;
        $comment_per_load = Null;
        foreach ($single_block['settings'] as $single_setting) {
            if($single_setting['type'] == "comment_display_photo"){
                $comment_display_photo = $single_setting['value'];
            }
            if($single_setting['type'] == "comment_display_datetime"){
                $comment_display_datetime = $single_setting['value'];
            }
            if($single_setting['type'] == "comment_per_load"){
                $comment_per_load = $single_setting['value'];
            }
        }
        return appilix_generate_comments($post_id, $comment_display_photo, $comment_display_datetime, $page, $comment_per_load);

    }else if(in_array($single_block['type'], $category_posts_types)){

        $cat_id = $request->get_param( 'cat_id' );
        $cat_id = isset($cat_id) ? $cat_id : 0;
        $page = $request->get_param( 'page' );
        $page = isset($page) ? $page : 1;
        $display_date = Null;
        $display_category = Null;
        $post_per_load = Null;
        foreach ($single_block['settings'] as $single_setting) {
            if($single_setting['type'] == "display_date"){
                $display_date = $single_setting['value'];
            }
            if($single_setting['type'] == "display_category"){
                $display_category = $single_setting['value'];
            }
            if($single_setting['type'] == "post_per_load"){
                $post_per_load = $single_setting['value'];
            }
        }
        return appilix_generate_category_posts($cat_id, $display_date, $display_category, $page, $post_per_load);

    }else if(in_array($single_block['type'], $bookmarks_posts_types)){

        $post_ids = $request->get_param( 'post_ids' );
        $allowedPostIds = explode(",", $post_ids);
        if($allowedPostIds != Null){
            if(sizeof($allowedPostIds) > 0){

                $page = $request->get_param( 'page' );
                $page = isset($page) ? $page : 1;
                $display_date = Null;
                $display_category = Null;
                $post_per_load = Null;
                foreach ($single_block['settings'] as $single_setting) {
                    if($single_setting['type'] == "display_date"){
                        $display_date = $single_setting['value'];
                    }
                    if($single_setting['type'] == "display_category"){
                        $display_category = $single_setting['value'];
                    }
                    if($single_setting['type'] == "post_per_load"){
                        $post_per_load = $single_setting['value'];
                    }
                }
                return appilix_generate_bookmarks_posts($allowedPostIds, $display_date, $display_category, $page, $post_per_load);

            }
        }
    }else if(in_array($single_block['type'], $search_posts_types)){

        $search = $request->get_param( 'search' );
        $search = isset($search) ? $search : "";
        if(strlen(trim($search)) > 0){
            $display_date = Null;
            $display_category = Null;
            foreach ($single_block['settings'] as $single_setting) {
                if($single_setting['type'] == "display_date"){
                    $display_date = $single_setting['value'];
                }
                if($single_setting['type'] == "display_category"){
                    $display_category = $single_setting['value'];
                }
            }
            return appilix_generate_search_posts($search, $display_date, $display_category);
        }else{
            return array();
        }
    }else if(in_array($single_block['type'], $page_block_types)){

        $page_id = $request->get_param( 'page_id' );
        $page_id = isset($page_id) ? $page_id : "";
        if(strlen(trim($page_id)) > 0){
            $display_page_title = Null;
            foreach ($single_block['settings'] as $single_setting) {
                if($single_setting['type'] == "display_page_title"){
                    $display_page_title = $single_setting['value'];
                }
            }
            return appilix_generate_page($page_id, $display_page_title);
        }else{
            return array();
        }


    }

    return Null;
}

function appilix_generate_categories($jsonCategories = array(), $limit = Null){
    $catArr = array();
    $include_ids_arr = array_column($jsonCategories, 'id');
    $include_ids = implode(',', $include_ids_arr);
    $listCategories = get_categories( array(
        "hide_empty" => false,
        'orderby' => 'name',
        'order'   => 'ASC',
        'include'   => (!in_array(0, $include_ids_arr)) ? $include_ids : "all",
    ));

    if($limit != Null && (in_array(0, $include_ids_arr))){
        if(is_numeric($limit)){
            $listCategories = array_slice($listCategories, 0, $limit, true);
        }
    }

    if (sizeof($listCategories) > 0) {
        foreach( $listCategories as $singleCategory ) {
            $catArr[] = array("cat_id" => $singleCategory->term_id,
                "cat_name" => $singleCategory->name);
        }
    }
    return $catArr;
}


function appilix_generate_posts($jsonCategories = array(), $limit = Null, $display_date = Null, $display_category = Null){
    $postArr = array();
    $include_ids_arr = array_column($jsonCategories, 'id');
    $include_ids = implode(',', $include_ids_arr);

    $listPosts = get_posts( array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_password' => '',
        'numberposts' => ($limit != Null) ? $limit : "5",
        'category' => (!in_array(0, $include_ids_arr)) ? $include_ids : "0",
        'orderby' => 'date',
        'order'   => 'DESC'));

    foreach( $listPosts as $singlePost ) {
        $catInfo = get_the_category( $singlePost->ID );
        $postCover =  get_the_post_thumbnail_url( $singlePost, 'post-thumbnail' );
        //$authorName = ($wpdb->get_var( "SELECT value FROM $table_app_settings WHERE type = 'display_author_name'" ) == "1") ? get_the_author_meta( "display_name", $singlePost->post_author) : "";
        $catName = ($display_category != Null) ? (($display_category == "1") ? (($catInfo != Null) ? $catInfo[0]->name : "") : "") : "";
        $postDate = ($display_date != Null) ? (($display_date == "1") ? date_format(date_create($singlePost->post_date),"d M, Y") : "") : "";
        $postArr[] = array("post_id" => $singlePost->ID,
            "cat_name" => $catName,
            "post_cat" => ($catInfo != Null) ? $catInfo[0]->cat_ID : 0,
            "post_url" => get_permalink($singlePost),
            "post_title" => $singlePost->post_title,
            "post_cover" => $postCover == false ? "" : $postCover,
            "post_date" => $postDate,
            "comment_status" => ($singlePost->comment_status == "open"));
    }
    return $postArr;
}

function appilix_generate_article($post_id, $display_date = Null, $display_category = Null, $display_author = Null, $display_comment_count = Null){
    $postData = array();
    $singlePost = get_post($post_id);
    if($singlePost != Null){
        if($singlePost->post_type == "post" && $singlePost->post_status == "publish" && $singlePost->post_password == ""){
            $catInfo = get_the_category( $singlePost->ID );
            $postCover =  get_the_post_thumbnail_url( $singlePost, 'post-thumbnail' );
            $authorName = ($display_author != Null) ? (($display_author == "1") ? get_the_author_meta( "display_name", $singlePost->post_author) : "" ) : "";
            $catName = ($display_category != Null) ? (($display_category == "1") ? (($catInfo != Null) ? $catInfo[0]->name : "") : "") : "";
            $postDate = ($display_date != Null) ? (($display_date == "1") ? date_format(date_create($singlePost->post_date),"d M, Y") : "") : "";
            $totalComments = ($display_comment_count != Null) ? (($display_comment_count == "1") ? $singlePost->comment_count : "") : "";


            $cssCheat = "";

            $cssCheat .= "<link rel=\"stylesheet\" href='".esc_url($GLOBALS['host']."/app/bootstrap.min.css")."' />";
            $cssCheat .= "<link rel=\"stylesheet\" href='".esc_url($GLOBALS['host']."/app/wp-block.css")."' />";
            $cssCheat .= "<style>".wp_remote_get(sprintf($GLOBALS['host']."/app/responsive_article.css"))['body']."</style>";
            $postContent = apply_filters('the_content', get_post_field('post_content', $singlePost->ID));
            $postContentFinal = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\">$cssCheat</head><body>$postContent</body></html>";

            $postData = array("post_id" => $singlePost->ID,
                "cat_name" => $catName,
                "post_cat" => ($catInfo != Null) ? $catInfo[0]->cat_ID : 0,
                "post_url" => get_permalink($singlePost),
                "post_title" => $singlePost->post_title,
                "post_cover" => $postCover == false ? "" : $postCover,
                "post_author" => $authorName,
                "post_date" => $postDate,
                "comment_status" => ($singlePost->comment_status == "open"),
                "total_comments" => $totalComments,
                "post_content" => $postContentFinal);
        }
    }
    return $postData;
}

function appilix_generate_custom_html_view($custom_html){
    $cssCheat = "";

    $cssCheat .= "<link rel=\"stylesheet\" href='".esc_url($GLOBALS['host']."/app/bootstrap.min.css")."' />";
    $cssCheat .= "<link rel=\"stylesheet\" href='".esc_url($GLOBALS['host']."/app/wp-block.css")."' />";
    $cssCheat .= "<style>".wp_remote_get(sprintf($GLOBALS['host']."/app/responsive_article.css"))['body']."</style>";
    $customHtmlFinal = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\">$cssCheat</head><body>$custom_html</body></html>";
    return $customHtmlFinal;
}

function appilix_generate_related_posts($post_id = 0, $limit = Null, $display_date = Null, $display_category = Null){
    $postArr = array();

    $catInfo = get_the_category( $post_id );

    $listPosts = get_posts( array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_password' => '',
        'numberposts' => ($limit != Null) ? $limit : "5",
        'category' => ($catInfo != Null) ? $catInfo[0]->cat_ID : 0,
        'exclude' => array($post_id),
        'orderby' => 'date',
        'order'   => 'DESC'));

    foreach( $listPosts as $singlePost ) {
        $catInfo = get_the_category( $singlePost->ID );
        $postCover =  get_the_post_thumbnail_url( $singlePost, 'post-thumbnail' );
        //$authorName = ($wpdb->get_var( "SELECT value FROM $table_app_settings WHERE type = 'display_author_name'" ) == "1") ? get_the_author_meta( "display_name", $singlePost->post_author) : "";
        $catName = ($display_category != Null) ? (($display_category == "1") ? (($catInfo != Null) ? $catInfo[0]->name : "") : "") : "";
        $postDate = ($display_date != Null) ? (($display_date == "1") ? date_format(date_create($singlePost->post_date),"d M, Y") : "") : "";
        $postArr[] = array("post_id" => $singlePost->ID,
            "cat_name" => $catName,
            "post_cat" => ($catInfo != Null) ? $catInfo[0]->cat_ID : 0,
            "post_url" => get_permalink($singlePost),
            "post_title" => $singlePost->post_title,
            "post_cover" => $postCover == false ? "" : $postCover,
            "post_date" => $postDate,
            "comment_status" => ($singlePost->comment_status == "open"));
    }
    return $postArr;
}

function appilix_generate_comments($post_id = 0, $comment_display_photo = Null, $comment_display_datetime = Null, $page = 1, $comment_per_load = Null){
    $commentsArr = array();
    $singlePost = get_post($post_id);
    if($singlePost != Null) {
        if ($singlePost->post_type == "post" && $singlePost->post_status == "publish" && $singlePost->post_password == "" && $singlePost->comment_status == "open") {

            $comment_per_load = ($comment_per_load != Null) ? $comment_per_load : "5";
            $listComments = get_comments( array(
                'post_id' =>  $post_id,
                'status' => 'approve',
                'number' => $comment_per_load,
                'offset' => ($page-1) * $comment_per_load,
                'orderby' => 'date',
                'order'   => 'DESC',
            ));
            foreach( $listComments as $singleComment ) {
                $commentDate = ($comment_display_datetime != Null) ? (($comment_display_datetime == "1") ? date_format(date_create($singleComment->comment_date),"d M, Y  h:m a") : "") : "";
                $commenterPhoto = ($comment_display_photo != Null) ? (($comment_display_photo == "1") ? $GLOBALS['host']."/app/commenter.png" : "") : "";
                $commentsArr[] = array("comment_id" => $singleComment->comment_ID,
                    "user_name" => $singleComment->comment_author,
                    "user_photo" => $commenterPhoto,
                    "comment" => $singleComment->comment_content,
                    "commented_at" => $commentDate);
            }

        }
    }
    return $commentsArr;
}


function appilix_generate_category_posts($cat_id = 0, $display_date = Null, $display_category = Null, $page = 1, $post_per_load = Null){
    $postArr = array();
    $post_per_load = ($post_per_load != Null) ? $post_per_load : "14";

    $listPosts = get_posts( array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_password' => '',
        'posts_per_page' => $post_per_load,
        'offset' => ($page-1) * $post_per_load,
        'category' => $cat_id,
        'orderby' => 'date',
        'order'   => 'DESC'));

    foreach( $listPosts as $singlePost ) {
        $catInfo = get_the_category( $singlePost->ID );
        $postCover =  get_the_post_thumbnail_url( $singlePost, 'post-thumbnail' );
        //$authorName = ($wpdb->get_var( "SELECT value FROM $table_app_settings WHERE type = 'display_author_name'" ) == "1") ? get_the_author_meta( "display_name", $singlePost->post_author) : "";
        $catName = ($display_category != Null) ? (($display_category == "1") ? (($catInfo != Null) ? $catInfo[0]->name : "") : "") : "";
        $postDate = ($display_date != Null) ? (($display_date == "1") ? date_format(date_create($singlePost->post_date),"d M, Y") : "") : "";
        $postArr[] = array("post_id" => $singlePost->ID,
            "cat_name" => $catName,
            "post_cat" => ($catInfo != Null) ? $catInfo[0]->cat_ID : 0,
            "post_url" => get_permalink($singlePost),
            "post_title" => $singlePost->post_title,
            "post_cover" => $postCover == false ? "" : $postCover,
            "post_date" => $postDate,
            "comment_status" => ($singlePost->comment_status == "open"));
    }
    return $postArr;
}

function appilix_generate_bookmarks_posts($allowedPostIds, $display_date = Null, $display_category = Null, $page = 1, $post_per_load = Null){
    $postArr = array();
    $post_per_load = ($post_per_load != Null) ? $post_per_load : "14";

    $listPosts = get_posts( array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_password' => '',
        'posts_per_page' => $post_per_load,
        'offset' => ($page-1) * $post_per_load,
        'post__in' => $allowedPostIds,
        'orderby' => 'date',
        'order'   => 'DESC'));

    foreach( $listPosts as $singlePost ) {
        $catInfo = get_the_category( $singlePost->ID );
        $postCover =  get_the_post_thumbnail_url( $singlePost, 'post-thumbnail' );
        //$authorName = ($wpdb->get_var( "SELECT value FROM $table_app_settings WHERE type = 'display_author_name'" ) == "1") ? get_the_author_meta( "display_name", $singlePost->post_author) : "";
        $catName = ($display_category != Null) ? (($display_category == "1") ? (($catInfo != Null) ? $catInfo[0]->name : "") : "") : "";
        $postDate = ($display_date != Null) ? (($display_date == "1") ? date_format(date_create($singlePost->post_date),"d M, Y") : "") : "";
        $postArr[] = array("post_id" => $singlePost->ID,
            "cat_name" => $catName,
            "post_cat" => ($catInfo != Null) ? $catInfo[0]->cat_ID : 0,
            "post_url" => get_permalink($singlePost),
            "post_title" => $singlePost->post_title,
            "post_cover" => $postCover == false ? "" : $postCover,
            "post_date" => $postDate,
            "comment_status" => ($singlePost->comment_status == "open"));
    }
    return $postArr;
}

function appilix_generate_search_posts($search, $display_date = Null, $display_category = Null){
    $postArr = array();

    $listPosts = get_posts( array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_password' => '',
        'numberposts' => "20",
        's' => $search,
        'orderby' => 'date',
        'order'   => 'DESC'));

    foreach( $listPosts as $singlePost ) {
        $catInfo = get_the_category( $singlePost->ID );
        $postCover =  get_the_post_thumbnail_url( $singlePost, 'post-thumbnail' );
        //$authorName = ($wpdb->get_var( "SELECT value FROM $table_app_settings WHERE type = 'display_author_name'" ) == "1") ? get_the_author_meta( "display_name", $singlePost->post_author) : "";
        $catName = ($display_category != Null) ? (($display_category == "1") ? (($catInfo != Null) ? $catInfo[0]->name : "") : "") : "";
        $postDate = ($display_date != Null) ? (($display_date == "1") ? date_format(date_create($singlePost->post_date),"d M, Y") : "") : "";
        $postArr[] = array("post_id" => $singlePost->ID,
            "cat_name" => $catName,
            "post_cat" => ($catInfo != Null) ? $catInfo[0]->cat_ID : 0,
            "post_url" => get_permalink($singlePost),
            "post_title" => $singlePost->post_title,
            "post_cover" => $postCover == false ? "" : $postCover,
            "post_date" => $postDate,
            "comment_status" => ($singlePost->comment_status == "open"));
    }
    return $postArr;
}

function appilix_generate_page($page_id, $display_page_title = Null){
    $postData = array();
    $singlePage = get_post($page_id);
    if($singlePage != Null){
        if($singlePage->post_type == "page" && $singlePage->post_status == "publish" && $singlePage->post_password == ""){

            $pageTitle = ($display_page_title != Null) ? (($display_page_title == "1") ? $singlePage->post_title : "") : "";

            $cssCheat = "";
            $cssCheat .= "<link rel=\"stylesheet\" href='".esc_url($GLOBALS['host']."/app/bootstrap.min.css")."' />";
            $cssCheat .= "<link rel=\"stylesheet\" href='".esc_url($GLOBALS['host']."/app/wp-block.css")."' />";
            $cssCheat .= "<style>".wp_remote_get(sprintf($GLOBALS['host']."/app/responsive_article.css"))['body']."</style>";
            $postContent = apply_filters('the_content', get_post_field('post_content', $singlePage->ID));
            $pageContentFinal = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\">$cssCheat</head><body>$postContent</body></html>";

            $postData = array("page_id" => $singlePage->ID,
                "page_title" => $pageTitle,
                "page_content" => $pageContentFinal);
        }
    }
    return $postData;
}