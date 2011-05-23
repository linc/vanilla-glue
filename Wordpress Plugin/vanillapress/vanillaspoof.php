<?php 
/**
 * Spoofing framework's existence for Gdn_CookieIdentity.
 */
 
// Vanilla Path (temporary hack)
define('VANILLA_PATH', '../../../forum/');
 
class Gdn {
   // Spoof Gdn::Config()
   public static function Config($Value, $DefaultValue) {
      // Get $Configuration
      include(VANILLA_PATH.'conf/config-defaults.php');
      include(VANILLA_PATH.'conf/config.php');
      
      // From Configuration::Get
      $Path = explode('.', $Name);
      
      $Value = $Configuration;
      $Count = count($Path);
      for($i = 0; $i < $Count; ++$i) {
         if(is_array($Value) && array_key_exists($Path[$i], $Value)) {
            $Value = $Value[$Path[$i]];
         } else {
            return $DefaultValue;
         }
      }
   }
   
   // Spoof Gdn::Request()->Host()
   public static function Request() {
      return new Gdn_Request();
   }  
}

// Spoof Gdn::Request()->Host()
class Gdn_Request {
   public function Host() {
      return isset($_SERVER['HTTP_HOST']) ? ArrayValue('HTTP_HOST',$_SERVER) : ArrayValue('SERVER_NAME',$_SERVER);
   }
}

if (!function_exists('ArrayValue')) {
   /**
    * Returns the value associated with the $Needle key in the $Haystack
    * associative array or FALSE if not found. This is a CASE-SENSITIVE search.
    *
    * @param string The key to look for in the $Haystack associative array.
    * @param array The associative array in which to search for the $Needle key.
    * @param string The default value to return if the requested value is not found. Default is FALSE.
    */
   function ArrayValue($Needle, $Haystack, $Default = FALSE) {
      $Result = GetValue($Needle, $Haystack, $Default);
		return $Result;
   }
}