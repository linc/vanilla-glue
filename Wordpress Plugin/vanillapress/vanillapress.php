<?php
/*
Plugin Name: VanillaPress
Plugin URI: 
Description: Use Wordpress as an addon for Vanilla Forums.
Author: Matt Lincoln Russell
Version: 1.0
Author URI: http://lincolnwebs.com
*/

/**
 * Required WP template additions:
 * Add to comments.php template: global $vanilla_comments; do_action('vanilla_comments', $post_id);
 * Add to single.php template: global $vanilla_postinfo; do_action('vanilla_postinfo', $post_id);
 */

// Vanilla setup
require_once(__DIR__.'/vanillaspoof.php'); // Requires 5.3 :(
require_once(__DIR__.'/vanillacookieidentity.php');

// Hooks
#register_activation_hook(__FILE__, 'vanillapress_activate');
add_action('init', 'vanillapress_authenticate');
add_action('publish_post', 'vanillapress_add_discussion');
add_action('comment_post', 'vanillapress_add_comment');
add_action('vanilla_comments', 'vanillapress_get_comments');
add_action('vanilla_postinfo', 'vanillapress_get_postinfo');

/**
 * Authenticate users from Vanilla cookie.
 */
function vanillapress_authenticate() {
   // Get & authenticate Vanilla cookie
   $auth_object = new Gdn_CookieIdentity();
   $userid = $auth_object->GetIdentity();
   
   // Set WordPress cookie
	if ($userid > 0) {
		wp_set_auth_cookie($userid, true);
		setup_userdata($userid);
	}
}

/**
 * Create Vanilla discussion for each new WordPress post.
 */
function vanillapress_add_discussion($postid) {
	global $wpdb;
   
	// Verify discussion has not been created
	$discussionid = get_post_meta($postid, 'discussionid', true);
	if ($discussionid > 0)
	  return;
   
	// Get post info
	$the_post = get_post($postid);
	
	// CategoryID
	$categoryid = Gdn::Config('Plugins.WordPress.Category', 3);
   
	// UserID
	$userid = intval($the_post->post_author);
   
	// Data
	$title = $the_post->post_title;
	$link = str_replace('%postname%', $the_post->post_name, get_permalink($the_post->ID, true));
	$body = '<a href="'.$link.'">'.$the_post->post_title.'</a>';
   
	// Create Discussion
   $discussionid = $wpdb->insert(VANILLA_PREFIX.'Discussion', array(
      'CategoryID' => $categoryid, 
      'InsertUserID' => $userid, 
      'Name' => $title, 
      'Body' => $body, 
      'Format' => 'Html', 
      'DateInserted' => date('Y-m-d H:i:s'))
   );

   // Update Post
   update_post_meta($postid, 'discussionid', $discussionid);
   
   // Update counters
   // @todo
}

/**
 * Copy new WordPress comment to Vanilla Comment.
 */
function vanillapress_add_comment($commentid) {
	global $wpdb;
   
	// Get comment info
	$comment = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."comments WHERE comment_ID = '$commentid'");
   
	// Get DiscussionID
	$discussionid = get_post_meta($comment->comment_post_ID, 'discussionid', true);
   
	// Create Comment
	$wpdb->insert(VANILLA_PREFIX.'Comment', array(
	  'DiscussionID' => $discussionid, 
	  'InsertUserID' => $comment->user_id, 
	  'Body' => $comment->comment_content, 
	  'Format' => 'Html', 
	  'DateInserted' => $comment->comment_date, 
	  'InsertIPAddress' => $comment->comment_author_IP, 
	  'GuestName' => $comment->comment_author, 
	  'GuestEmail' => $comment->comment_author_email, 
	  'GuestUrl' => $comment->comment_author_url
   )); // $comment->comment_approved;
    
   // Update discussion meta
   $wpdb->update(VANILLA_PREFIX.'Discussion', 
      array('DateLastComment' => $comment->comment_date, 'LastCommentUserID' => $comment->user_id), 
      array('ID' => $discussionid)
   );
	
	// Update counters
	// @todo
}

/**
 * Get comments to display in WordPress.
 *
 * @todo Add a limit or pagination
 */
function vanillapress_get_comments($postid) {
	global $wpdb, $vanilla_comments, $discussionid;
	$discussionid = 0;
	
	// Get DiscussionID
	$discussionid = get_post_meta($postid, 'discussionid', true);
   
	// Get all comments from discussion
   $vanilla_comments = $wpdb->get_results("SELECT CommentID, c.InsertUserID, Body, c.DateInserted, c.InsertIPAddress, u.Name, u.Photo
      FROM ".VANILLA_PREFIX."Comment c
      LEFT JOIN ".VANILLA_PREFIX."User u ON u.UserID = c.InsertUserID
      WHERE DiscussionID = $discussionid 
      ORDER BY DateInserted ASC");
}

/**
 * Get extra post meta from Vanilla.
 */
function vanillapress_get_postinfo($postid) {
	global $wpdb, $vanilla_postinfo;

	// Get DiscussionID, author, replycount
	$vanilla_postinfo = $wpdb->get_row("SELECT p.post_author as user_id, p.vb_threadid as thread_id 
		FROM $wpdb->posts p WHERE p.ID = '$postid'");
}