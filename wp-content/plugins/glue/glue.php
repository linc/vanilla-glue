<?php
/*
Plugin Name: Glue
Plugin URI: 
Description: Glues WordPress to your Vanilla Forum.
Author: Matt Lincoln Russell
Version: 1.0
Author URI: http://lincolnwebs.com
*/

/**
 * WordPress Glue plugin.
 *
 * @package Glue
 * @copyright 2011 Matt Lincoln Russell <lincolnwebs@gmail.com>
 * @todo Redirect to Vanilla log out.
 */

// Pass GET params around Vanilla
if (isset($_GET)) 
   $GetHolder = $_GET;

// Include Garden framework
require_once(dirname(__FILE__).'/config.php');
require_once(dirname(__FILE__).'/vanilla.php');

// Pass GET params around Vanilla
if (isset($GetHolder)) {
   $_GET = $GetHolder;
   unset($GetHolder);
}

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
 *
 * @param int $postid
 */
function glue_add_discussion($postid) {
   // Verify discussion has not been created
   $discussionid = get_post_meta($postid, 'discussionid', true);
   if ($discussionid > 0)
     return;
   
   // Get post info
   $the_post = get_post($postid);
   list($category) = get_the_category($postid);
   
   // CategoryID
   $default_cat = Gdn::Config('Glue.Category.Default', 1);
   $categoryid = Gdn::Config('Glue.Category.'.$category->name, $default_cat);

   // Build discussion data
   $userid = intval($the_post->post_author);
   $title = $the_post->post_title;
   $link = str_replace('%postname%', $the_post->post_name, get_permalink($the_post->ID, true));
   $body = '<a href="'.$link.'">'.$title.'</a>';
   $DiscussionData = array(
      'CategoryID' => $categoryid, 
      'InsertUserID' => $userid, 
      'Name' => $title, 
      'Body' => $body, 
      'Format' => 'Html', 
      'DateInserted' => $the_post->post_date,
      'DateUpdated' => $the_post->post_date,
      'DateLastComment' => $the_post->post_date )
   );
   
   // Create discussion
   $DiscussionModel = new DiscussionModel();
   $DiscussionID = $DiscussionModel->Save($DiscussionData);

   // Update Post
   update_post_meta($postid, 'discussionid', $DiscussionID);
}

/**
 * Copy new WordPress comment to Vanilla Comment.
 *
 * @param int $commentid
 */
function glue_add_comment($commentid) {
   global $wpdb;
   
   // Get comment info
   $comment = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."comments WHERE comment_ID = '$commentid'");
   
   // Ignore spam
   if ($comment->comment_approved == 'spam')
      return;
   
   // Get DiscussionID
   $discussionid = get_post_meta($comment->comment_post_ID, 'discussionid', true);
   
   // Create Comment
   $CommentModel = new CommentModel();
   $CommentData = array(
     'DiscussionID' => $discussionid, 
     'InsertUserID' => $comment->user_id, 
     'Body' => $comment->comment_content, 
     'Format' => 'Html', 
     'DateInserted' => $comment->comment_date, 
     'InsertIPAddress' => $comment->comment_author_IP, 
     'GuestName' => $comment->comment_author, 
     'GuestEmail' => $comment->comment_author_email, 
     'GuestUrl' => $comment->comment_author_url
   ));
   $CommentID = $CommentModel->Save($CommentData);
   if ($CommentID) 
      $CommentModel->Save2($CommentID, TRUE, TRUE, TRUE);
}

/**
 * Get comments to display in WordPress.
 *
 * @todo Add a limit or pagination
 * @param int $postid
 */
function glue_get_comments($postid) {
   global $wpdb, $vanilla_comments, $discussionid;
   $discussionid = 0;
      
   // Get DiscussionID
   $discussionid = get_post_meta($postid, 'discussionid', true);
   
   // Get all comments from discussion
   $vanilla_comments = $wpdb->get_results("
      SELECT c.CommentID, c.InsertUserID, c.Body, c.DateInserted, c.InsertIPAddress, u.UserID, u.Name, u.Photo, u.Email, 
         c.GuestName, c.GuestEmail, c.GuestUrl, c.Format
      FROM ".VANILLA_PREFIX."Comment c
      LEFT JOIN ".VANILLA_PREFIX."User u ON u.UserID = c.InsertUserID
      WHERE DiscussionID = $discussionid 
      ORDER BY DateInserted ASC");
}

/**
 * Get avatar/photo URL for a comment user.
 *
 * @param mixed $data UserID (int) or object containing user data.
 * @return string Url of photo.
 */
function glue_get_photo($data) {
   if (is_numeric($data)) {
      global $wpdb;
      $data = $wpdb->get_row("SELECT Name, Photo, Email, DateFirstVisit FROM ".VANILLA_PREFIX."User WHERE UserID = $data");
   }

   // Get photo URL
   $PhotoUrl = '/uploads/'.ChangeBasename($data->Photo, 'n%s'); // @todo Get PATH_UPLOADS / prefix
   $Email = ($data->Email) ? $data->Email : $data->GuestEmail;
   if (!$data->Photo) {
      // Use Gravatar + Vanillicon        
      $PhotoUrl = 'http://www.gravatar.com/avatar.php?'
         .'gravatar_id='.md5(strtolower($Email))
         .'&amp;size=50'
         .'&amp;default='.urlencode('http://vanillicon.com/'.md5(strtolower($Email)).'.png');
   }
   
   return $PhotoUrl;
}

/**
 * Get avatar/photo URL for a comment user.
 *
 * @param object $comment
 * @return string Url path to user profile.
 */
function glue_get_url($comment) {
   if ($comment->UserID)
      $Url = '/profile/'.$comment->UserID.'/'.rawurlencode($comment->Name); 
   else
      $Url = $comment->GuestUrl;
      
   return $Url;
}
