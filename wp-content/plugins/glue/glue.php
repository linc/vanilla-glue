<?php
/*
Plugin Name: Glue
Plugin URI: 
Description: Glues WordPress to your Vanilla Forum.
Author: Matt Lincoln Russell
Version: 1.0
Author URI: http://lincolnwebs.com
*/

// @todo Redirect from WordPress sign in page.
// @todo Redirect to Vanilla log out.

// Vanilla setup
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/vanillaspoof.php'); // Requires 5.3 :(
require_once(__DIR__.'/vanillacookieidentity.php');

// Hooks
add_action('init', 'glue_authenticate');
add_action('publish_post', 'glue_add_discussion');
add_action('comment_post', 'glue_add_comment');
add_action('vanilla_comments', 'glue_get_comments');
add_action('vanilla_postinfo', 'glue_get_postinfo');

/**
 * Authenticate users from Vanilla cookie.
 *
 * @todo Fix authentication lag - requires refresh after initial signin
 */
function glue_authenticate() {
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
function glue_add_discussion($postid) {
	global $wpdb;
   
	// Verify discussion has not been created
	$discussionid = get_post_meta($postid, 'discussionid', true);
	if ($discussionid > 0)
	  return;
   
	// Get post info
	$the_post = get_post($postid);
	
	// CategoryID
	$categoryid = Gdn::Config('Plugins.WordPress.Category', 0);
   
	// UserID
	$userid = intval($the_post->post_author);
   
	// Data
	$title = $the_post->post_title;
	$link = str_replace('%postname%', $the_post->post_name, get_permalink($the_post->ID, true));
	$body = '<a href="'.$link.'">'.$the_post->post_title.'</a>';
   
	// Create Discussion
   $wpdb->insert(VANILLA_PREFIX.'Discussion', array(
      'CategoryID' => $categoryid, 
      'InsertUserID' => $userid, 
      'Name' => $title, 
      'Body' => $body, 
      'Format' => 'Html', 
      'DateInserted' => date('Y-m-d H:i:s'))
   );

   // Update Post
   update_post_meta($postid, 'discussionid', $wpdb->insert_id);
}

/**
 * Copy new WordPress comment to Vanilla Comment.
 */
function glue_add_comment($commentid) {
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
   
   $commentid = $wpdb->insert_id;
   
   // Update discussion meta
   $wpdb->update(VANILLA_PREFIX.'Discussion', 
      array('DateLastComment' => $comment->comment_date, 'LastCommentUserID' => $comment->user_id), 
      array('ID' => $discussionid)
   );
   	
	// Call CommentModel::Save2() with cURL magic
	$URL = get_bloginfo('home');
	$c = curl_init($URL.'/plugin/vanillapress/savecomment/'.$commentid);
	curl_exec($c);
	curl_close($c);
}

/**
 * Get comments to display in WordPress.
 *
 * @todo Add a limit or pagination
 */
function glue_get_comments($postid) {
	global $wpdb, $vanilla_comments, $discussionid;
	$discussionid = 0;
	
	// Get DiscussionID
	$discussionid = get_post_meta($postid, 'discussionid', true);
   
	// Get all comments from discussion
   $vanilla_comments = $wpdb->get_results("
      SELECT CommentID, c.InsertUserID, Body, c.DateInserted, c.InsertIPAddress, u.Name, u.Photo, u.Email, c.GuestName, c.GuestEmail, c.GuestUrl
      FROM ".VANILLA_PREFIX."Comment c
      LEFT JOIN ".VANILLA_PREFIX."User u ON u.UserID = c.InsertUserID
      WHERE DiscussionID = $discussionid 
      ORDER BY DateInserted ASC");
}

/**
 * Get extra post meta from Vanilla.
 */
function glue_get_postinfo($postid) {
	global $wpdb, $vanilla_postinfo;

	// Get DiscussionID, author, replycount
	$vanilla_postinfo = $wpdb->get_row("SELECT p.post_author as user_id, p.vb_threadid as thread_id 
		FROM $wpdb->posts p WHERE p.ID = '$postid'");
}