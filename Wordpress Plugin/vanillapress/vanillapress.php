<?php
/*
Plugin Name: VanillaPress
Plugin URI: 
Description: Use Wordpress as an addon for Vanilla Forums.
Author: Matt Lincoln Russell
Version: 1.0
Author URI: http://lincolnwebs.com
*/

// Hooks
register_activation_hook(__FILE__, 'vanillapress_activate');
add_action('init', 'vanillapress_authenticate');
add_action('publish_post', 'vanillapress_add_discussion');
add_action('comment_post', 'vanillapress_add_comment');
add_action('vanilla_comments', 'vanillapress_get_comments');
add_action('vanilla_postinfo', 'vanillapress_get_postinfo');

// Add to comments.php template: do_action('vanilla_comments', $post_id);
// Add to single.php template: do_action('vanilla_postinfo', $post_id);

/**
 * Do plugin setup.
 */
function vanillapress_activate() {
	// Add 'discussionid' column to wp_posts
	
}

/**
 * Authenticate users from Vanilla cookie.
 */
function vanillapress_authenticate() {
   // Get & authenticate Vanilla cookie
   require_once('vanillaspoof.php');
   require_once('class.cookieidentity.php');
   $Auth = new Gdn_CookieIdentity();
   $UserID = $Auth->GetIdentity();
   
   // Set WordPress cookie
	if ($UserID > 0) {
		wp_set_auth_cookie($UserID, true);
		setup_userdata($UserID);
	}
}

/**
 * Create Vanilla discussion for each new WordPress post.
 */
function vanillapress_add_discussion($post_ID) {
	global $wpdb;
   
   // Get post info
	$the_post = get_post($post_ID);
	
	// Verify discussion has not been created
	if ($the_post->vb_threadid > 0) 
	  return;
	
	// CategoryID
   
	// UserID
	$postuserid = $userid = intval($the_post->post_author);
	
	// Name
	$r = $wpdb->get_row("SELECT user_login FROM ".$wpdb->prefix."users WHERE ID = '$userid'");
	$username = $r->user_login;
	unset($r);

	// Data
	$title = $the_post->post_title;
	$link = str_replace('%postname%', $the_post->post_name, get_permalink($the_post->ID, true));
	$body = '';

	// Create Discussion
		
   // Update Counters
   
}

/**
 * Copy new WordPress comment to Vanilla Comment.
 */
function vanillapress_add_comment($comment_id) {
	global $wpdb;
   
	// Get comment info
	$r = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."comments WHERE comment_ID = '$comment_id'");
	$pagetext = $r->comment_content;
	$approved = $r->comment_approved;
	$date = strtotime($r->comment_date);
	$userid = $r->user_id;
	$ip = $r->comment_author_IP;
	$useremail = $r->comment_author_email;
	$userurl = $r->comment_author_url;
	$wp_postid = $r->comment_post_ID;
	$username = $r->comment_author; # Potentially guest name
	unset($r);
   
	// Name
	if($userid > 0) {
		$r = $wpdb->get_row("SELECT username FROM ".TABLE_PREFIX."user WHERE userid = '$userid'");
		$username = $r->username;
		unset($r);
	}

	// Get DiscussionID
	$r = $wpdb->get_row("SELECT discussionid FROM ".$wpdb->prefix."posts WHERE ID = '$wp_postid'");
	$threadid = $r->vb_threadid;
	$title = '';
	unset($r);

	// Create Comment
	
	// Update counters
	
}

/**
 * Get comments to display in WordPress.
 */
function vanillapress_get_comments($post_id) {
	global $wpdb;	
   
	// Get all comments from discussion
   $comments = $wpdb->get_results("");
   
	return $comments;
}

/**
 * Get extra post meta from Vanilla.
 */
function vanillapress_get_postinfo($post_id) {
	global $wpdb;

	// Get DiscussionID, author, replycount
	$post = $wpdb->get_row("SELECT p.post_author as user_id, p.vb_threadid as thread_id 
		FROM $wpdb->posts p WHERE p.ID = '$post_id'");
		
   return $post;
}
