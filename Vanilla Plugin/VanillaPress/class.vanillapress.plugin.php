<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['VanillaPress'] = array(
   'Name' => 'VanillaPress',
   'Description' => 'Use Wordpress as an addon to Vanilla Forums.',
   'Version' => '1.0a',
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincolnwebs@gmail.com',
   'AuthorUrl' => 'http://lincolnwebs.com',
   'RequiredApplications' => array('Vanilla' => '2.0.17')
);

class VanillaPressPlugin extends Gdn_Plugin {
	/**
	 * Add menu items to Dashboard.
	 */
   public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      //$Menu->AddItem('Forum', T('WordPress'));
      //$Menu->AddLink('Forum', T('WordPress'), 'settings/vanillapress', 'VanillaPress.Settings.Manage');
   }

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
	 * Overwrite discussion URL with WordPress URL.
	 */
   public function DiscussionsController_Something_Handler($Sender) {
      
   }
   
   /**
	 * Forward attempts to visit discussion to WordPress.
	 */
   public function DiscussionController_Something_Handler($Sender) {
      
   }
   
   /**
	 * Use guest data on comments if UserID is zero.
	 */
   public function DiscussionController_Something2_Handler($Sender) {
      
   }
   
   /**
	 * Port user to WordPress if it doesn't exist yet.
	 */
   public function UserModel_AfterSave_Handler($Sender) {
      // Prep DB
      $Database = Gdn::Database();
      $SQL = $Database->SQL();
      $UserID = $Sender->EventArguments['UserID'];
      
      // Check if user already exists
      if ($SQL->Query("select * from wp_users where ID = '$UserID'")->FirstRow())
         return;
      
      // Get user data
      $User = $SQL->Select('*')->From('User')->Where('UserID', $UserID)->Get()->FirstRow();

      // Main user record
   	$SQL->Query("INSERT INTO wp_users SET
   		ID = '".$UserID."',
   		user_login = '".mysql_real_escape_string($User->Name)."',
   		user_pass = '".mysql_real_escape_string($User->Password)."',
   		user_nicename = '".mysql_real_escape_string(strtolower($User->Name))."',
   		user_email = '".mysql_real_escape_string($User->Email)."',
   		user_registered = '".mysql_real_escape_string(date( 'Y-m-d H:i:s', $User->DateInserted))."',
   		display_name = '".mysql_real_escape_string($User->Name)."'");
   	   	
   	// Nickname
   	$SQL->Query("INSERT INTO wp_usermeta SET
   		user_id = '".$UserID."',
   		meta_key = 'nickname',
   		meta_value = '".mysql_real_escape_string($User->Name)."'");
   	
   	// Permissions per-blog
      $SQL->Query("INSERT INTO wp_usermeta SET
   		user_id = '".$UserID."',
   		meta_key = 'wp_capabilities',
		   meta_value = 'a:1:{s:10:\"subscriber\";b:1;}'");
   }
   
   /**
	 * Update WordPress permissions upon edit user.
	 */
   public function UserController_Something_Handler($Sender) {
      
   }
   
   /**
	 * Update WordPress posts comment_count.
	 */
   public function PostController_Something_Handler($Sender) {
      
   }

	/**
	 * 1-Time on install.
	 */
   public function Setup() {
      $Structure = Gdn::Structure();
      
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
      
      // Create WordPress roles
      $RoleModel = Gdn::Factory('RoleModel');
      $RoleModel->Database = Gdn::Database();
      $RoleModel->SQL = $RoleModel->DatabaseDatabase->SQL();
      $RoleModel->Define(array('Name' => 'WordPress Contributor', 'Deletable' => '1', 'CanSession' => '1', 'Description' => 'Gives user "Contributor" role on WordPress blog.'));
      $RoleModel->Define(array('Name' => 'WordPress Author', 'Deletable' => '1', 'CanSession' => '1', 'Description' => 'Gives user "Author" role on WordPress blog.'));
      $RoleModel->Define(array('Name' => 'WordPress Editor', 'Deletable' => '1', 'CanSession' => '1', 'Description' => 'Gives user "Editor" role on WordPress blog.'));
      unset($RoleModel);
   }
	
}