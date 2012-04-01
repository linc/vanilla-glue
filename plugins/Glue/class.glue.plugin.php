<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['Glue'] = array(
   'Name' => 'Glue',
   'Description' => 'Glues WordPress to your Vanilla Forum permanently. See warnings in README.',
   'Version' => '1.0a',
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincolnwebs@gmail.com',
   'AuthorUrl' => 'http://lincolnwebs.com',
   'RegisterPermissions' => array(),
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'SettingsUrl' => '/dashboard/settings/glue'
);

// Establish overridable WP table prefix
$Prefix = C('Plugins.Glue.WordPressPrefix', 'wp_');
define('WP_PREFIX', $Prefix);

/**
 * Plugin to use WordPress as a blog addon for Vanilla Forums.
 *
 * @package Glue
 * @todo Dashboard settings page
 * @todo Overwrite discussion URL with WordPress URL (DiscussionsController)
 * @todo Forward attempts to visit discussion to WordPress (DiscussionController)
 * @todo Quotes plugin needs this at end of FormatQuote function:
      if (!GetValue('authorname', $QuoteData))
         SetValue('authorname', $QuoteData, GetValue('GuestName', $Data));
 */
class GluePlugin extends Gdn_Plugin {
   /**
    * Inject the email under the username on guest comments.
    */
   public function DiscussionController_CommentInfo_Handler($Sender, $Args) {
      $this->AttachEmail($Sender, $Args);
   }
   public function PostController_CommentInfo_Handler($Sender, $Args) {
      $this->AttachEmail($Sender, $Args);
   }
   private function AttachEmail($Sender, $Args) {
      $Object = GetValue('Object', $Args);
      $GuestEmail = GetValue('GuestEmail', $Object);
      if (!$GuestEmail || GetValue('InsertUserID', $Object) || !CheckPermission('Garden.Moderation.Manage'))
         return;
      echo '<span class="MItem GuestEmail">'.$GuestEmail.'</span> ';
   }
   
   /**
    * Use guest data on comments if UserID is zero.
    *
    * Fields that need to be set for most themes: InsertName, InsertEmail, InsertPhoto.
    * We'll also set InsertUrl to replicate WordPress functionality of linking name.
    */
   public function CommentModel_AfterGet_Handler($Sender, $Args) {
      foreach ($Args['Comments'] as &$Comment) {
         if ($Comment->GuestName) {
            $Comment->InsertName = $Comment->GuestName;
            $Comment->InsertEmail = $Comment->GuestEmail;
            $Comment->InsertUrl = $Comment->GuestUrl;
         }
      }
   }
   
   /**
    * Because UserBuiler has a whitelist of properties that doesn't include InsertUrl. :(
    */
   public function DiscussionController_BeforeCommentDisplay_Handler($Sender, $Args) {
      $Args['Author']->Url = GetValue('InsertUrl', $Args['Object'], '');
   }
   
   /**
    * Get Guest user data for discussions view.
    */
   public function DiscussionsController_BeforeDiscussionName_Handler($Sender, $Args) {
      $this->LastGuestUser($Args);
   }
   
   /**
    * Get Guest user data for categories view.
    */
   public function CategoriesController_BeforeDiscussionName_Handler($Sender, $Args) {
      $this->LastGuestUser($Args);
   }
   
   /**
    * Derive guest user data.
    */
   public function LastGuestUser(&$Args) {
      if (!GetValue('Name', $Args['LastUser']) && Gdn::Session()->UserID == 5) {
         $CommentID = GetValue('LastCommentID', $Args['Discussion']);
         if ($CommentID) {
            $CommentModel = new CommentModel();
            $CommentData = $CommentModel->GetID($CommentID);
            $Args['LastUser'] = UserBuilder($CommentData, 'Guest');
            SetValue('Url', $Args['LastUser'], GetValue('GuestUrl', $CommentData, '#'));
         }
      }
   }
   
   /**
    * If the discussion is based on a WordPress post, go to WordPress instead.
    */
   /*public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
      $WordPressID = GetValue('WordPressID', $Sender->Discussion, FALSE);
      if ($WordPressID) {
         $Link = $this->GetWordPressLink($WordPressID);
         Redirect($Link, 301);
         exit();
      }
   }*/
   
   /**
    * Remove WordPress cookies during signout.
    */
   public function EntryController_SignOut_Handler($Sender) {
      foreach ($_COOKIE as $Name => $Value) {
         if (strstr($Name, 'wordpress') !== FALSE)
            setcookie($Name, ' ', time() - 31536000);
      }
   }
   
   /**
    * Get URL to a WordPress post by ID.
    *
    * @param int $PostID Unique ID.
    * @return string $Link URL to the post.
    */
   public function GetWordPressLink($PostID) {
      $Link = '?p='.$PostID; // @todo Cheating to test. Get pretty URL.
      
      return $Link;
   }
   
   /**
    * Convert guest fields into normal users fields.
    */
   public static function HandleGuest($CommentData) {
      if ($CommentData->GuestName) {
         $CommentData->Name = $CommentData->GuestName;
         $CommentData->Email = $CommentData->GuestEmail;
      }
      
      return $CommentData;
   }
   
   /**
    * Grab existing WordPress discussions/comments and import into Vanilla for continuity.
    */
   public function ImportWordPressComments() { 
      // Prep DB
      $Database = Gdn::Database();
      $SQL = $Database->SQL();
      
      // Start discussions for existing WordPress posts
      $SQL->Query("insert into ".$Database->DatabasePrefix."Discussion 
         (WordPressID, InsertUserID, DateInserted, Name, Body, Format) 
         select ID, post_author, post_date, post_title, CONCAT('<a href=\"/article/', post_name, '\">', post_title, '</a>'), 'HTML'
         from ".WP_PREFIX."posts
            where post_status = 'publish' and comment_count > 0");
         
      // Port all comments from WordPress to new Vanilla discussions
      $SQL->Query("insert into ".$Database->DatabasePrefix."Comment
         (DiscussionID, DateInserted, Body, InsertUserID, GuestName, GuestUrl, GuestEmail, Glued) 
         select (select DiscussionID from ".$Database->DatabasePrefix."Discussion where WordPressID = comment_post_id), 
            comment_date, comment_content, user_id, comment_author, comment_author_url, comment_author_email, '1' 
         from ".WP_PREFIX."comments
            where comment_approved = 1");
   }
   
   /**
    * Use user data object to insert WordPress user records.
    *
    * @param object $User DataObject from Garden.
    * @param mixed $Capability Array or serialized array ex: array('author' => 1).
    */
   public function InsertWordPressUser($UserID, $Capability = FALSE) {
      // Prep DB
      $Database = Gdn::Database();
      $SQL = $Database->SQL();
      
      // Get user
      $UserModel = new UserModel();
      $User = $UserModel->GetID($UserID);
            
      // Get capability
      $Capability = ($Capability) ? $Capability : array('subscriber' => 1); // Default to subscriber
      if (is_array($Capability))
         $Capability = serialize($Capability);

      // Blog user record
      $SQL->NamedParameters(array(
         ':ID' => GetValue('UserID', $User),
         ':user_login' => GetValue('Name', $User),
         ':user_pass' => GetValue('Password', $User),
         ':user_nicename' => strtolower(GetValue('Name', $User)),
         ':user_email' => GetValue('Email', $User),
         ':user_registered' => GetValue('DateInserted', $User),
         ':display_name' => GetValue('Name', $User)
      ));
      $SQL->Query("INSERT INTO ".WP_PREFIX."users SET ID = :ID, user_login = :user_login, user_pass = :user_pass, 
         user_nicename = :user_nicename, user_email = :user_email, user_registered = :user_registered, display_name = :display_name", 'insert');
                        
      // Blog nickname
      $SQL->NamedParameters(array(
         ':user_id' => GetValue('UserID', $User),
         ':meta_key' => 'nickname',
         ':meta_value' => GetValue('Name', $User)
      ));
      $SQL->Query("INSERT INTO ".WP_PREFIX."usermeta SET user_id = :user_id, meta_key = :meta_key, meta_value = :meta_value", 'insert');
         
      // Blog permission
      $SQL->NamedParameters(array(
         ':user_id' => GetValue('UserID', $User),
         ':meta_key' => 'wp_capabilities',
         ':meta_value' => $Capability
      ));
      $SQL->Query("INSERT INTO ".WP_PREFIX."usermeta SET user_id = :user_id, meta_key = :meta_key, meta_value = :meta_value", 'insert');
   }
   
   /**
    * Setting page.
    */
   public function SettingsController_Glue_Create($Sender, $Args = array()) {
      $Sender->Permission('Garden.Settings.Manage');
      if ($Sender->Form->IsPostBack()) {
         $Settings = array(
             'Plugins.Glue.Category' => $Sender->Form->GetFormValue('CategoryID')
         );
         SaveToConfig($Settings);
         $Sender->InformMessage(T("Your settings have been saved."));
      } else {
         $Sender->Form->SetFormValue('CategoryID', C('Plugins.Glue.Category'));
      }
      
      $Sender->AddSideMenu();
      $Sender->SetData('Title', T('WordPress Settings'));
      $Sender->Render('Settings', '', 'plugins/wordpress');
   }
   
   /**
    * Update user's name if it changes.
    */
   /*public function UserModel_AfterSave_Handler($Sender, $Args) {
      $UserID = $Args['UserID'];
   }*/
   
   /**
    * Port new user to WordPress.
    */
   public function UserModel_AfterInsertUser_Handler($Sender, $Args) {
      $this->InsertWordPressUser($Args['InsertUserID']);
   }

   /**
    * 1-Time on install.
    */
   public function Setup() {
      $Structure = Gdn::Structure();
      $Database = Gdn::Database();
      $SQL = $Database->SQL();
      
      // Associate discussions with posts
      $Structure->Table('Discussion')
         ->Column('WordPressID', 'int', TRUE)
         ->Set();
      
      // Enable guest data
      $Structure->Table('Comment')
         ->Column('GuestName', 'varchar(64)', TRUE)
         ->Column('GuestEmail', 'varchar(64)', TRUE)
         ->Column('GuestUrl', 'varchar(128)', TRUE)
         ->Column('Glued', 'tinyint(1)', '0')
         ->Set();
      
      // Only do user modifications during first setup
      if (!C('Plugins.Glue.Setup', FALSE)) {
         // Delete all current WordPress users
         // @todo Match to Vanilla users by email instead
         $SQL->Query("truncate table ".WP_PREFIX."users");
         $SQL->Query("truncate table ".WP_PREFIX."usermeta");
            
         // Transfer existing Vanilla users 
         $SQL->Query("insert into ".WP_PREFIX."users 
            (ID, user_login, user_pass, user_nicename, user_email, user_registered, display_name) 
            select UserID, Name, Password, LOWER(Name), Email, DateInserted, Name from ".$Database->DatabasePrefix."User");
         
         // Nicknames
         $SQL->Query("insert into ".WP_PREFIX."usermeta (user_id, meta_key, meta_value)
            select UserID, 'nickname', Name from ".$Database->DatabasePrefix."User");         
         
         // Starting permission (subscriber)
         $Capability = mysql_real_escape_string(serialize(array('subscriber' => 1)));
         $SQL->Query("insert into ".WP_PREFIX."usermeta (user_id, meta_key, meta_value)
            select UserID, 'wp_capabilities', '$Capability' from ".$Database->DatabasePrefix."User");            
         
         // Set Admin
         $SQL->Query("update ".WP_PREFIX."usermeta 
            set meta_value = '".mysql_real_escape_string(serialize(array('administrator' => 1)))."' 
            where meta_key = 'wp_capabilities' 
               and (user_id IN (select UserID from ".$Database->DatabasePrefix."User where Admin = '1'))");
               
         // Import existing comments
         //$this->ImportWordPressComments();
      }
            
      // Disable blog registration
      $SQL->Query("update ".WP_PREFIX."options 
         set option_value = '0' 
         where option_name = 'users_can_register'");
         
      // Set default category
      if (!C('Plugins.Glue.Category', FALSE))
         SaveToConfig('Plugins.Glue.Category', 1);
         
      SaveToConfig('Plugins.Glue.Setup', TRUE);
   }
}

if (!function_exists('UserAnchor')) {
   /**
    * Override UserAnchor to account for $UserUrl.
    */
   function UserAnchor($User, $CssClass = '', $Options = NULL) {
      static $NameUnique = NULL;
      if ($NameUnique === NULL)
         $NameUnique = C('Garden.Registration.NameUnique');
      
      $Px = $Options;
      $Name = GetValue($Px.'Name', $User, T('Unknown'));
      $UserID = GetValue($Px.'UserID', $User, 0);
   
      if ($CssClass != '')
         $CssClass = ' class="'.$CssClass.'"';
      
      // Use Guest's provided URL with nofollow attribute
      $UserUrl = GetValue($Px.'Url', $User, FALSE);
      if ($UserUrl) {
         $Link = $UserUrl;
         $NoFollow = ' rel="nofollow"';
      }
      else {
         $Link = Url('/profile/'.($NameUnique ? '' : "$UserID/").rawurlencode($Name));
         $NoFollow = '';
      }
         
      return '<a href="'.htmlspecialchars($Link).'"'.$CssClass.$NoFollow.'>'.htmlspecialchars($Name).'</a>';
   }
}