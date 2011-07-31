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
 * Add to comments.php template: do_action('vanilla_comments', $post_id);
 * Add to single.php template: do_action('vanilla_postinfo', $post_id);
 */

// Vanilla table prefix
// @todo
define('VANILLA_PREFIX', 'GDN_');
define('APPLICATION', TRUE);

// Hooks
register_activation_hook(__FILE__, 'vanillapress_activate');
add_action('init', 'vanillapress_authenticate');
add_action('publish_post', 'vanillapress_add_discussion');
add_action('comment_post', 'vanillapress_add_comment');
add_action('vanilla_comments', 'vanillapress_get_comments');
add_action('vanilla_postinfo', 'vanillapress_get_postinfo');

/**
 * Wipe current WordPress users and replace with Vanilla users.
 */
function vanillapress_activate() {
   global $wpdb;
   
   # Structure - empty everything
   $wpdb->query("TRUNCATE TABLE wp_users");
   $wpdb->query("TRUNCATE TABLE wp_usermeta");
   
   # Get all users
   $vanilla_users = $wpdb->get_results("SELECT u.*, 
      (SELECT Value FROM GDN_UserMeta m WHERE m.UserID = u.UserID AND Name = 'display_name') as display_name,
      (SELECT Value FROM GDN_UserMeta m WHERE m.UserID = u.UserID AND Name = 'user_url') as user_url
      FROM GDN_User u");
   
   # Import users
   foreach ($vanilla_users as $a) {
   	# Get display_name, user_url from UserMeta
      $display_name = ($a['display_name']) ? $a['display_name'] : $a['Name'];
      $user_url = ($a['user_url']) ? $a['user_url'] : '';
      
      # Main user record
   	$wpdb->query("INSERT INTO wp_users SET
   		ID = '".$a['UserID']."',
   		user_login = ".e($a['Name']).",
   		user_pass = ".e($a['Password']).",
   		user_nicename = ".e(strtolower($a['Name'])).",
   		user_email = ".e($a['Email']).",
   		user_url = ".e($user_url).",
   		user_registered = ".e(date( 'Y-m-d H:i:s', $a['DateInserted'])).",
   		display_name = ".e($display_name)."
   	");
   	
   	# Nickname
   	$wpdb->query("INSERT INTO wp_usermeta SET
   		user_id = '".$a['UserID']."',
   		meta_key = 'nickname',
   		meta_value = '".mysql_real_escape_string($a['Name'])."'");
   	
   	# Permissions per-blog
      $wpdb->query("INSERT INTO wp_usermeta SET
   		user_id = '".$a['UserID']."',
   		meta_key = 'wp_capabilities',
   		meta_value = 'a:1:{s:10:\"subscriber\";b:1;}'"); # Theoretically it will fix this at first login where appropriate
   }

   // Set admins
   $wpdb->query("UPDATE wp_usermeta SET meta_value = 'a:1:{s:13:\"administrator\";b:1;}' WHERE meta_key = 'wp_capabilities' AND 
      (user_id IN (SELECT ID from GDN_User WHERE Admin = '1')");
}

/**
 * Authenticate users from Vanilla cookie.
 */
function vanillapress_authenticate() {
   // Get & authenticate Vanilla cookie
   require_once(__DIR__.'/vanillaspoof.php');
   require_once(__DIR__.'/vanillacookieidentity.php');
   $auth_object = new Gdn_CookieIdentity();
   $userid = $auth_object->GetIdentity();
   
   // Set WordPress cookie
	if ($userid > 0) {
	  die('close!')
		wp_set_auth_cookie($userid, true);
		setup_userdata($userid);
	}
	die('no cigar');
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
	// @todo
	// This needs to somehow figure out the WordPress category and map it to a Vanilla category.
	// This is probably going to require a WP dashboard page.
	$categoryid = 1;
   
	// UserID
	$userid = intval($the_post->post_author);
   
	// Data
	$title = $the_post->post_title;
	$link = str_replace('%postname%', $the_post->post_name, get_permalink($the_post->ID, true));
	$body = '<a href="'.$link.'">'.$the_post->post_title.'</a>';
   
	// Create Discussion
   $discussionid = $wpdb->query("INSERT INTO {VANILLA_PREFIX}Discussion (CategoryID, InsertUserID, Name, Body, Format, DateInserted) VALUES (
      $categoryid,
      $userid,
      ".$wpdb->escape($title).",
      ".$wpdb->escape($body).",
      'Html',
      NOW()
   )");
   
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
	// unused:$comment->comment_approved;
	$wpdb->query("INSERT INTO {VANILLA_PREFIX}Comment (DiscussionID, InsertUserID, Body, Format, DateInserted, InsertIPAddress, GuestName, GuestEmail, GuestUrl) 
      VALUES (
         $discussionid, 
         {$comment->user_id}, 
         ".$wpdb->escape($comment->comment_content).",
         'Html',
         {$comment->comment_date},
         {$comment->comment_author_IP},
         ".$wpdb->escape($comment->comment_author).",
         ".$wpdb->escape($comment->comment_author_email).",
         ".$wpdb->escape($comment->comment_author_url)."
      )");
	
	// Update counters
	// @todo
}

/**
 * Get comments to display in WordPress.
 */
function vanillapress_get_comments($postid) {
	global $wpdb;
	
	// Get DiscussionID
	$discussionid = get_post_meta($comment->comment_post_ID, 'discussionid', true);
   
	// Get all comments from discussion
	// @todo Add a limit or pagination
   $comments = $wpdb->get_results("SELECT CommentID, c.InsertUserID, Body, c.DateInserted, c.InsertIPAddress, u.Name, u.Photo
      FROM {VANILLA_PREFIX}Comment c
      LEFT JOIN {VANILLA_PREFIX}User u ON u.UserID = c.InsertUserID
      WHERE DiscussionID = $discussionid 
      ORDER BY DateInserted ASC");
   
	return $comments;
}

/**
 * Get extra post meta from Vanilla.
 */
function vanillapress_get_postinfo($postid) {
	global $wpdb;

	// Get DiscussionID, author, replycount
	$post = $wpdb->get_row("SELECT p.post_author as user_id, p.vb_threadid as thread_id 
		FROM $wpdb->posts p WHERE p.ID = '$postid'");
		
   return $post;
}
