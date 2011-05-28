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
      //$Menu->AddItem('Forum', T('Wordpress'));
      //$Menu->AddLink('Forum', T('Wordpress'), 'settings/vanillapress', 'VanillaPress.Settings.Manage');
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
	 * 1-Time on Disable.
	 */
   public function OnDisable() {
      
   }

	/**
	 * 1-Time on Install.
	 */
   public function Setup() {
      // Add some fields to the database
      $Structure = Gdn::Structure();
      
      $Structure->Table('Discussion')
         ->Column('WordPressID', 'int', TRUE)
         ->Set();
         
      $Structure->Table('Comment')
         ->Column('GuestName', 'varchar(64)', TRUE)
         ->Column('GuestEmail', 'varchar(64)', TRUE)
         ->Column('GuestUrl', 'varchar(128)', TRUE)
         ->Set();
      
      //SaveToConfig('Vanilla.Comments.AutoOffset', FALSE);
   }
	
}