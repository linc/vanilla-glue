<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['Glue'] = array(
   'Name' => 'Glue',
   'Description' => 'Glues WordPress to your Vanilla Forum. WARNING: DELETES ALL CURRENT WP USERS. INSTALL ONLY ON EMPTY BLOG.',
   'Version' => '1.0a',
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincolnwebs@gmail.com',
   'AuthorUrl' => 'http://lincolnwebs.com',
   'RegisterPermissions' => array(
      'WordPress.Blog.Contributor',
      'WordPress.Blog.Author',
      'WordPress.Blog.Editor',
      'WordPress.Blog.Administrator'),
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
 */
class GluePlugin extends Gdn_Plugin {
   /**
	 * Use guest data on comments if UserID is zero.
	 *
	 * Fields that need to be set for most themes: InsertName, InsertEmail, InsertPhoto.
	 * We'll also set InsertUrl to replicate WordPress functionality of linking name.
	 */
   public function CommentModel_AfterGet_Handler($Sender) {
      foreach ($Sender->EventArguments['Comments'] as &$Comment) {
         $Comment->InsertName = $Comment->GuestName;
         $Comment->InsertEmail = $Comment->GuestEmail;
         $Comment->InsertUrl = $Comment->GuestUrl;
      }
   }
   
   /**
    * Do CommentModel::Save2() on new comments.
    *
    * WordPress can't call Vanilla's framework to do all the updates,
    * activity, and mentions. It would cause tremendous duplication to do
    * all that in the WP plugin, so we quietly cURL to this "sekrit" URL.
    */
   public function Controller_SaveComment($Sender) {
      $CommentID = GetValue(1, $Sender->RequestArgs);
            
      // Update metadata on the comment & trigger activity
      $CommentModel = new CommentModel();
      $CommentData = $CommentModel->GetID($CommentID);
      if (!$CommentData->Bridged) {
         $CommentModel->UpdateCommentCount($CommentData->DiscussionID);
         $CommentModel->Save2($CommentID, TRUE);
         $CommentModel->SetProperty($CommentID, 'Bridged', 1);
      }
   }
   
   /**
    * Because UserBuiler has a whitelist of properties that doesn't include InsertUrl. :(
    */
   public function DiscussionController_BeforeCommentDisplay_Handler($Sender) {
      $Sender->EventArguments['Author']->Url = GetValue('InsertUrl', $Sender->EventArguments['Object'], '');
   }
   
   /**
    * If the discussion is based on a WordPress post, go to WordPress instead.
    */
   public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
      $WordPressID = GetValue('WordPressID', $Sender->Discussion, FALSE);
      if ($WordPressID) {
         $Link = $this->GetWordPressLink($WordPressID);
         Redirect($Link, 301);
         exit();
      }
   }
   
   /**
	 * Get WordPress role name.
	 *
	 * @param int $UserID Unique ID.
	 * @return array WordPress-format capability array.
	 */
   public function GetWordPressCapability($UserID) {
      // Get Vanilla permission to assign WP capabilities
      $FakeSession = new Gdn_Session();
      $FakeSession->Start($UserID, FALSE);
      switch (TRUE) {
         case ($FakeSession->CheckPermission('WordPress.Blog.Administrator')) :
            $Capability = array('administrator' => 1);
            break;
         case ($FakeSession->CheckPermission('WordPress.Blog.Editor')) :
            $Capability = array('editor' => 1);
            break;
         case ($FakeSession->CheckPermission('WordPress.Blog.Author')) :
            $Capability = array('author' => 1);
            break;
         case ($FakeSession->CheckPermission('WordPress.Blog.Contributor')) :
            $Capability = array('contributor' => 1);
            break;
         default :
            $Capability = array('subscriber' => 1);
      }
      unset($FakeSession);
      return $Capability;
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
	 * Grab existing WordPress discussions/comments and import into Vanilla for continuity.
	 */
   public function ImportWordPressComments() {      
      // Start discussions for existing WordPress posts
      $SQL->Query("insert into :_Discussion 
         (WordPressID, UserID, DateInserted, Name) 
         select ID, post_author, post_date, post_title 
         from ".WP_PREFIX."posts
            where post_status = 'publish' and comment_count > 0");
         
      // Port all comments from WordPress to new Vanilla discussions
      $SQL->Query("insert into :_Comment
         (DiscussionID, DateInserted, Body, UserID, GuestName, GuestUrl, GuestEmail, Glued) 
         select comment_post_id, commet_date, comment_content, user_id, 
            comment_author, comment_author_url, comment_author_email, '1' 
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
      $Capability = ($Capability) ?: array('subscriber' => 1); // Default to subscriber
      if (is_array($Capability))
         $Capability = serialize($Capability);

      // Blog user record
	   $SQL->Query("INSERT INTO ".WP_PREFIX."users SET
   		ID = '".$User->UserID."',
   		user_login = '".mysql_real_escape_string($User->Name)."',
   		user_pass = '".mysql_real_escape_string($User->Password)."',
   		user_nicename = '".mysql_real_escape_string(strtolower($User->Name))."',
   		user_email = '".mysql_real_escape_string($User->Email)."',
   		user_registered = '".mysql_real_escape_string($User->DateInserted)."',
   		display_name = '".mysql_real_escape_string($User->Name)."'");
   	   	
   	// Blog nickname
   	$SQL->Query("INSERT INTO ".WP_PREFIX."usermeta SET
   		user_id = '".$User->UserID."',
   		meta_key = 'nickname',
   		meta_value = '".mysql_real_escape_string($User->Name)."'");
   		
      // Blog permission
      $SQL->Query("INSERT INTO ".WP_PREFIX."usermeta SET
   		user_id = '".$User->UserID."',
   		meta_key = 'wp_capabilities',
		   meta_value = '".mysql_real_escape_string($Capability)."'");
   }
   
   /**
    * Create virtual controller.
    */
   public function PluginController_Glue_Create($Sender) {
      $this->Dispatch($Sender, $Sender->RequestArgs);
   }
   
   /**
    * Setting page.
    */
   public function SettingsController_WordPress_Create($Sender, $Args = array()) {
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
	 * Port user to WordPress if it doesn't exist yet. Otherwise, update permission.
	 */
   public function UserModel_AfterSave_Handler($Sender) {
      // Prep DB
      $Database = Gdn::Database();
      $SQL = $Database->SQL();
      $UserID = $Sender->EventArguments['UserID'];
      
      $Capability = $this->GetWordPressCapability($UserID);
      
      // Check if user already exists in WP
      $User = $SQL->Query("select * from ".WP_PREFIX."users where ID = '$UserID'")->FirstRow();
      if ($User->ID) {
         // Update permission
         $SQL->Query("update ".WP_PREFIX."usermeta 
            set meta_value = '".mysql_real_escape_string(serialize($Capability))."'
      		where user_id = '$UserID'
               and meta_key = 'wp_capabilities'");
      } else { 
         // User not in WP
         $this->InsertWordPressUser($UserID, $Capability);
      }
   }
   
   /**
	 * Port new user to WordPress.
	 */
   public function UserModel_AfterInsertUser_Handler($Sender) {
      $this->InsertWordPressUser($Sender->EventArguments['InsertUserID']);
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
            select UserID, Name, Password, LOWER(Name), Email, DateInserted, Name from :_User");
         
         // Nicknames
   	   $SQL->Query("insert into ".WP_PREFIX."usermeta (user_id, meta_key, meta_value)
            select UserID, 'nickname', Name from :_User");         
         
         // Starting permission (subscriber)
         $Capability = mysql_real_escape_string(serialize(array('subscriber' => 1)));
         $SQL->Query("insert into ".WP_PREFIX."usermeta (user_id, meta_key, meta_value)
            select UserID, 'wp_capabilities', '$Capability' from :_User");            
         
         // Set Admin
         $SQL->Query("update ".WP_PREFIX."usermeta 
            set meta_value = '".mysql_real_escape_string(serialize(array('administrator' => 1)))."' 
            where meta_key = 'wp_capabilities' 
               and (user_id IN (select UserID from :_User where Admin = '1'))");
               
         // Import existing comments
         $this->ImportWordPressComments();
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