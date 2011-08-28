<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['VanillaPress'] = array(
   'Name' => 'VanillaPress',
   'Description' => 'Use WordPress as an addon to Vanilla Forums. WARNING: DELETES ALL CURRENT WP USERS. INSTALL ONLY ON EMPTY BLOG.',
   'Version' => '1.0a',
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincolnwebs@gmail.com',
   'AuthorUrl' => 'http://lincolnwebs.com',
   'RegisterPermissions' => array(
      'WordPress.Blog.Contributor',
      'WordPress.Blog.Author',
      'WordPress.Blog.Editor',
      'WordPress.Blog.Administrator'),
   'RequiredApplications' => array('Vanilla' => '2.0.17'),
   'SettingsUrl' => '/dashboard/settings/wordpress'
);

/**
 * Plugin to use WordPress as a blog addon for Vanilla Forums.
 *
 * @package VanillaPress
 * @todo Dashboard settings page
 * @todo Overwrite discussion URL with WordPress URL (DiscussionsController)
 * @todo Forward attempts to visit discussion to WordPress (DiscussionController)
 */
class VanillaPressPlugin extends Gdn_Plugin {
	/**
	 * Add menu items to Dashboard.
	 */
   //public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      //$Menu = &$Sender->EventArguments['SideMenu'];
      //$Menu->AddItem('Forum', T('WordPress'));
      //$Menu->AddLink('Forum', T('WordPress'), 'settings/vanillapress', 'VanillaPress.Settings.Manage');
   //}

	/**
	 * Add JS & CSS to the page.
	 */
   public function AddJsCss($Sender) {
      $Sender->AddCSSFile('vanillapress.css', 'plugins/VanillaPress');
		$Sender->AddJSFile('plugins/VanillaPress/vanillapress.js');
   }
	public function DiscussionsController_Render_Before($Sender) {
		$this->AddJsCss($Sender);
	}
   public function CategoriesController_Render_Before($Sender) {
      $this->AddJsCss($Sender);
   }
   
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
         
         // @todo Integrate with Gravatar plugin?
      }
   }
   
   /**
    * Because UserBuiler has a whitelist of properties that doesn't include InsertUrl. :(
    */
   public function DiscussionController_BeforeCommentDisplay_Handler($Sender) {
      $Sender->EventArguments['Author']->Url = $Sender->EventArguments['Object']->InsertUrl;
   }
   
   /**
	 * Use user data object to insert WordPress user records.
	 *
	 * @param object $User DataObject from Garden.
	 * @param mixed $Capability Array or serialized array ex: array('author' => 1).
	 */
   public function InsertWordPressUser($User, $Capability = FALSE) {
      // Prep DB
      $Database = Gdn::Database();
      $SQL = $Database->SQL();
      
      // Get capability
      $Capability = ($Capability) ?: array('subscriber' => 1); // Default to subscriber
      if (is_array($Capability))
         $Capability = serialize($Capability);

      // Blog user record
	   $SQL->Query("INSERT INTO wp_users SET
   		ID = '".$User->UserID."',
   		user_login = '".mysql_real_escape_string($User->Name)."',
   		user_pass = '".mysql_real_escape_string($User->Password)."',
   		user_nicename = '".mysql_real_escape_string(strtolower($User->Name))."',
   		user_email = '".mysql_real_escape_string($User->Email)."',
   		user_registered = '".mysql_real_escape_string(date( 'Y-m-d H:i:s', $User->DateInserted))."',
   		display_name = '".mysql_real_escape_string($User->Name)."'");
   	   	
   	// Blog nickname
   	$SQL->Query("INSERT INTO wp_usermeta SET
   		user_id = '".$User->UserID."',
   		meta_key = 'nickname',
   		meta_value = '".mysql_real_escape_string($User->Name)."'");
   		
      // Blog permission
      $SQL->Query("INSERT INTO wp_usermeta SET
   		user_id = '".$User->UserID."',
   		meta_key = 'wp_capabilities',
		   meta_value = '".mysql_real_escape_string($Capability)."'");
   }
   
   /**
	 * Update WordPress posts comment_count.
	 */
   public function PostController_Something_Handler($Sender) {
      
   }
   
   /**
    * Setting page.
    */
   public function SettingsController_WordPress_Create($Sender, $Args) {
      $Sender->Permission('Garden.Settings.Manage');
      if ($Sender->Form->IsPostBack()) {
         $Settings = array(
             'Plugins.WordPress.Category' => $Sender->Form->GetFormValue('CategoryID')
         );
         SaveToConfig($Settings);
         $Sender->InformMessage(T("Your settings have been saved."));
      } else {
         $Sender->Form->SetFormValue('CategoryID', C('Plugins.WordPress.Category'));
      }
      
      $Sender->AddSideMenu();
      $Sender->SetData('Title', T('WordPress Settings'));
      $Sender->Render('Settings', '', 'plugins/wordpress');
   }
      
   /**
	 * Port user to WordPress if it doesn't exist yet.
	 */
   public function UserModel_AfterSave_Handler($Sender) {
      // Prep DB
      $Database = Gdn::Database();
      $SQL = $Database->SQL();
      $UserID = $Sender->EventArguments['UserID'];
      
      // Get Vanilla permission to assign WP capabilities
      $FakeSession = new Gdn_Session();
      $FakeSession->Start($UserID);
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
            
      // Check if user already exists in WP
      if ($SQL->Query("select * from wp_users where ID = '$UserID'")->FirstRow()) {
         // Update permission
         $SQL->Query("UPDATE wp_usermeta 
            SET meta_value = '".mysql_real_escape_string(serialize($Capability))."'
      		WHERE user_id = '$UserID'
               AND meta_key = 'wp_capabilities'");
      } else { 
         // User not in WP - Get Vanilla user data
         $User = $SQL->Select('*')->From('User')->Where('UserID', $UserID)->Get()->FirstRow();
         InsertWordPressUser($User, $Capability);
      }
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
         ->Set();
      
      // Delete all current WordPress users
      $SQL->Query("truncate table wp_users");
      $SQL->Query("truncate table wp_usermeta");
      
      // Port existing Garden users
      $UserModel = new UserModel();
      $Users = $UserModel->Get();
      foreach ($Users->Results() as $User) {
         InsertWordPressUser($User);
      }
      
      // Set Admin
      $SQL->Query("update wp_usermeta 
         set meta_value = '".mysql_real_escape_string(serialize(array('administrator' => 1)))."' 
         where meta_key = 'wp_capabilities' 
            and (user_id IN (select UserID from GDN_User where Admin = '1')");
            
      // Disable blog registration
      $SQL->Query("update wp_options 
         set option_value = '0' 
         where option_name = 'users_can_register'");
         
      // Set default category
      SaveToConfig('Plugins.WordPress.Category', 1);
   }
}

if (!function_exists('UserAnchor')) {
   /**
    * Override base UserAnchor to account for $UserUrl.
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