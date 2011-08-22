<?php 
/**
 * Spoofing framework's existence for Gdn_CookieIdentity.
 */
 
define('VANILLA_PATH', '../../../'); // @todo Undo this hack
define('APPLICATION', TRUE);

class Gdn {
   // Spoof Gdn::Config()
   public function Config($Name, $DefaultValue = FALSE) {
      // Get $Configuration
      require(VANILLA_PATH.'conf/config-defaults.php');
      require(VANILLA_PATH.'conf/config.php');
      
      $Path = explode('.', $Name);
      
      $Count = count($Path);
      for($i = 0; $i < $Count; ++$i) {
         if(is_array($Configuration) && array_key_exists($Path[$i], $Configuration)) {
            $Configuration = $Configuration[$Path[$i]]; 
         } else {
            return $DefaultValue;
         }
      }
      
      if(is_string($Configuration))
         $Result = Gdn_Format::Unserialize($Configuration);
      else
         $Result = $Configuration;

      return $Result;
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

// Spoof Gdn_Format::Unserialize()
class Gdn_Format {
   public static function Unserialize($SerializedString) {
		$Result = $SerializedString;
		
      if(is_string($SerializedString)) {
			if(substr_compare('a:', $SerializedString, 0, 2) === 0 || substr_compare('O:', $SerializedString, 0, 2) === 0)
				$Result = unserialize($SerializedString);
			elseif(substr_compare('obj:', $SerializedString, 0, 4) === 0)
            $Result = json_decode(substr($SerializedString, 4), FALSE);
         elseif(substr_compare('arr:', $SerializedString, 0, 4) === 0)
            $Result = json_decode(substr($SerializedString, 4), TRUE);
      }
      return $Result;
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

if (!function_exists('GetValue')) {
	/**
	 * Return the value from an associative array or an object.
	 *
	 * @param string $Key The key or property name of the value.
	 * @param mixed $Collection The array or object to search.
	 * @param mixed $Default The value to return if the key does not exist.
    * @param bool $Remove Whether or not to remove the item from the collection.
	 * @return mixed The value from the array or object.
	 */
	function GetValue($Key, &$Collection, $Default = FALSE, $Remove = FALSE) {
		$Result = $Default;
		if(is_array($Collection) && array_key_exists($Key, $Collection)) {
			$Result = $Collection[$Key];
         if($Remove)
            unset($Collection[$Key]);
		} elseif(is_object($Collection) && property_exists($Collection, $Key)) {
			$Result = $Collection->$Key;
         if($Remove)
            unset($Collection->$Key);
      }
			
      return $Result;
	}
}

if (!function_exists('StringEndsWith')) {
   /** Checks whether or not string A ends with string B.
    *
    * @param string $Haystack The main string to check.
    * @param string $Needle The substring to check against.
    * @param bool $CaseInsensitive Whether or not the comparison should be case insensitive.
    * @param bool Whether or not to trim $B off of $A if it is found.
    * @return bool|string Returns true/false unless $Trim is true.
    */
   function StringEndsWith($Haystack, $Needle, $CaseInsensitive = FALSE, $Trim = FALSE) {
      if (strlen($Haystack) < strlen($Needle))
         return FALSE;
      elseif (strlen($Needle) == 0) {
         if ($Trim)
            return $Haystack;
         return TRUE;
      } else {
         $Result = substr_compare($Haystack, $Needle, -strlen($Needle), strlen($Needle), $CaseInsensitive) == 0;
         if ($Trim)
            $Result = $Result ? substr($Haystack, 0, -strlen($Needle)) : $Haystack;
         return $Result;
      }
   }
}

if (!function_exists('CompareHashDigest')) {
    /**
     * Returns True if the two strings are equal, False otherwise.
     * The time taken is independent of the number of characters that match.
     *
     * This snippet prevents HMAC Timing attacks ( http://codahale.com/a-lesson-in-timing-attacks/ )
     * Thanks to Eric Karulf (ekarulf @ github) for this fix.
     */
   function CompareHashDigest($Digest1, $Digest2) {
        if (strlen($Digest1) !== strlen($Digest2)) {
            return false;
        }

        $Result = 0;
        for ($i = strlen($Digest1) - 1; $i >= 0; $i--) {
            $Result |= ord($Digest1[$i]) ^ ord($Digest2[$i]);
        }

        return 0 === $Result;
    }
}

$Prefix = Gdn::Config('Database.DatabasePrefix');
define('VANILLA_PREFIX', $Prefix);