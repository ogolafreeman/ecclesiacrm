<?php


/*******************************************************************************
 *
 *  filename    : /Include/Functions.php
 *  website     : http://www.ecclesiacrm.com
 *  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-1012 Michael Wilt
 *                Copyright 2017 Philippe Logel
 *
 ******************************************************************************/

use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\Service\PersonService;
use EcclesiaCRM\Service\SystemService;
use EcclesiaCRM\Utils\InputUtils;
use EcclesiaCRM\Utils\OutputUtils;

$personService = new PersonService();
$systemService = new SystemService();
$_SESSION['sSoftwareInstalledVersion'] = SystemService::getInstalledVersion();

//
// Basic security checks:
//

if (empty($bSuppressSessionTests)) {  // This is used for the login page only.
    // Basic security: If the UserID isn't set (no session), redirect to the login page
    if (!isset($_SESSION['user'])) {
        Redirect('Login.php');
        exit;
    }

    // Check for login timeout.  If login has expired, redirect to login page
    if (SystemConfig::getValue('iSessionTimeout') > 0) {
        if ((time() - $_SESSION['tLastOperation']) > SystemConfig::getValue('iSessionTimeout')) {
            Redirect('Login.php');
            exit;
        } else {
            if ($_SESSION['lastPage'] != $_SERVER['PHP_SELF']) {
                $_SESSION['lastPage'] = $_SERVER['PHP_SELF'];            
                $_SESSION['tLastOperation'] = time();
            }
        }
    }

    // If this user needs to change password, send to that page
    if ($_SESSION['bNeedPasswordChange'] && !isset($bNoPasswordRedirect)) {
        Redirect('UserPasswordChange.php?PersonID='.$_SESSION['user']->getPersonId());
        exit;
    }

    // Check if https is required

  // Note: PHP has limited ability to access the address bar
  // url.  PHP depends on Apache or other web server
  // to provide this information.  The web server
  // may or may not be configured to pass the address bar url
  // to PHP.  As a workaround this security check is now performed
  // by the browser using javascript.  The browser always has
  // access to the address bar url.  Search for basic security checks
  // in Include/Header-functions.php
}
// End of basic security checks


// if magic_quotes off and array
function addslashes_deep($value)
{
    $value = is_array($value) ?
    array_map('addslashes_deep', $value) :
    addslashes($value);

    return $value;
}

// If Magic Quotes is turned off, do the same thing manually..
if (!isset($_SESSION['bHasMagicQuotes'])) {
    foreach ($_REQUEST as $key => $value) {
        $value = addslashes_deep($value);
    }
}

// Constants
$aPropTypes = [
  1  => gettext('True / False'),
  2  => gettext('Date'),
  3  => gettext('Text Field (50 char)'),
  4  => gettext('Text Field (100 char)'),
  5  => gettext('Text Field (long)'),
  6  => gettext('Year'),
  7  => gettext('Season'),
  8  => gettext('Number'),
  9  => gettext('Person from Group'),
  10 => gettext('Money'),
  11 => gettext('Phone Number'),
  12 => gettext('Custom Drop-Down List'),
];

$sGlobalMessageClass = 'success';

if (isset($_GET['Registered'])) {
    $sGlobalMessage = gettext('Thank you for registering your EcclesiaCRM installation.');
}

if (isset($_GET['AllPDFsEmailed'])) {
    $sGlobalMessage = gettext('PDFs successfully emailed ').$_GET['AllPDFsEmailed'].' '.gettext('families').".";
}

if (isset($_GET['PDFEmailed'])) {
    if ($_GET['PDFEmailed'] == 1) {
        $sGlobalMessage = gettext('PDF successfully emailed to family members.');
    } else {
        $sGlobalMessage = gettext('Failed to email PDF to family members.');
    }
}

// Are they adding an entire group to the cart?
if (isset($_GET['AddGroupToPeopleCart'])) {
    AddGroupToPeopleCart(InputUtils::LegacyFilterInput($_GET['AddGroupToPeopleCart'], 'int'));
    $sGlobalMessage = gettext('Group successfully added to the Cart.');
}

// Are they removing an entire group from the Cart?
if (isset($_GET['RemoveGroupFromPeopleCart'])) {
    RemoveGroupFromPeopleCart(InputUtils::LegacyFilterInput($_GET['RemoveGroupFromPeopleCart'], 'int'));
    $sGlobalMessage = gettext('Group successfully removed from the Cart.');
}

if (isset($_GET['ProfileImageDeleted'])) {
    $sGlobalMessage = gettext('Profile Image successfully removed.');
}

if (isset($_GET['ProfileImageUploaded'])) {
    $sGlobalMessage = gettext('Profile Image successfully updated.');
}

if (isset($_GET['ProfileImageUploadedError'])) {
    $sGlobalMessage = gettext('Profile Image upload Error.');
    $sGlobalMessageClass = 'danger';
}

// Are they removing a person from the Cart?
if (isset($_GET['RemoveFromPeopleCart'])) {
    RemoveFromPeopleCart(InputUtils::LegacyFilterInput($_GET['RemoveFromPeopleCart'], 'int'));
    $sGlobalMessage = gettext('Selected record successfully removed from the Cart.');
}

if (isset($_POST['BulkAddToCart'])) {
    $aItemsToProcess = explode(',', $_POST['BulkAddToCart']);

    if (isset($_POST['AndToCartSubmit'])) {
        if (isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'], $aItemsToProcess);
        }
    } elseif (isset($_POST['NotToCartSubmit'])) {
        if (isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'], $aItemsToProcess);
        }
    } else {
        for ($iCount = 0; $iCount < count($aItemsToProcess); $iCount++) {
            AddToPeopleCart(str_replace(',', '', $aItemsToProcess[$iCount]));
        }
        $sGlobalMessage = $iCount.' '.gettext('item(s) added to the Cart.');
    }
}


//
// Some very basic functions that all scripts use
//

function AddToPeopleCart($sID)
{
    // make sure the cart array exists
    if (isset($_SESSION['aPeopleCart'])) {
        if (!in_array($sID, $_SESSION['aPeopleCart'], false)) {
            $_SESSION['aPeopleCart'][] = $sID;
        }
    } else {
        $_SESSION['aPeopleCart'][] = $sID;
    }
}

function RemoveFromPeopleCart($sID)
{
   // make sure the cart array exists
   // we can't remove anybody if there is no cart
   if (isset($_SESSION['aPeopleCart'])) {
       unset($aTempArray); // may not need this line, but make sure $aTempArray is empty
   $aTempArray[] = $sID; // the only element in this array is the ID to be removed
   $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'], $aTempArray);
   }
}

// Remove group from cart
function RemoveGroupFromPeopleCart($iGroupID)
{
    //Get all the members of this group
    $sSQL = 'SELECT p2g2r_per_ID FROM person2group2role_p2g2r '.
    'WHERE p2g2r_grp_ID = '.$iGroupID;
    $rsGroupMembers = RunQuery($sSQL);

    //Loop through the recordset
    while ($aRow = mysqli_fetch_array($rsGroupMembers)) {
        extract($aRow);

        //remove each person from the cart
        RemoveFromPeopleCart($p2g2r_per_ID);
    }
}

// Add group to cart
function AddGroupToPeopleCart($iGroupID)
{
    //Get all the members of this group
    $sSQL = 'SELECT p2g2r_per_ID FROM person2group2role_p2g2r '.
    'WHERE p2g2r_grp_ID = '.$iGroupID;
    $rsGroupMembers = RunQuery($sSQL);

    //Loop through the recordset
    while ($aRow = mysqli_fetch_array($rsGroupMembers)) {
        extract($aRow);

        //Add each person to the cart
        AddToPeopleCart($p2g2r_per_ID);
    }
}

// Convert a relative URL into an absolute URL and return absolute URL.
function RedirectURL($sRelativeURL)
{
    // Test if file exists before redirecting.  May need to remove
    // query string first.
    $iQueryString = strpos($sRelativeURL, '?');
    if ($iQueryString) {
        $sPathExtension = mb_substr($sRelativeURL, 0, $iQueryString);
    } else {
        $sPathExtension = $sRelativeURL;
    }

    // The idea here is to get the file path into this form:
    //     $sFullPath = $sDocumentRoot . $sRootPath . $sPathExtension
    // The Redirect URL is then in this form:
    //     $sRedirectURL = $sRootPath . $sPathExtension
    $sFullPath = str_replace('\\', '/', SystemURLs::getDocumentRoot().'/'.$sPathExtension);

    // With the query string removed we can test if file exists
    if (file_exists($sFullPath) && is_readable($sFullPath)) {
        return SystemURLs::getRootPath().'/'.$sRelativeURL;
    } else {
        $sErrorMessage = 'Fatal Error: Cannot access file: '.$sFullPath."<br>\n"
      ."\$sPathExtension = $sPathExtension<br>\n"
      ."\$sDocumentRoot = ".SystemURLs::getDocumentRoot()."<br>\n"
      .'$sRootPath = ' .SystemURLs::getRootPath()."<br>\n";

        die($sErrorMessage);
    }
}

// Convert a relative URL into an absolute URL and redirect the browser there.
function Redirect($sRelativeURL)
{
    $sRedirectURL = RedirectURL($sRelativeURL);
    header('Location: '.$sRedirectURL);
    exit;
}

// Returns the current fiscal year
function CurrentFY()
{
    $yearNow = date('Y');
    $monthNow = date('m');
    $FYID = $yearNow - 1996;
    if ($monthNow >= SystemConfig::getValue('iFYMonth') && SystemConfig::getValue('iFYMonth') > 1) {
        $FYID += 1;
    }

    return $FYID;
}

// PrintFYIDSelect: make a fiscal year selection menu.
function PrintFYIDSelect($iFYID, $selectName)
{
    echo '<select class="form-control" name="'.$selectName.'">';
    echo '<option value="0">'.gettext('Select Fiscal Year').'</option>';

    for ($fy = 1; $fy < CurrentFY() + 2; $fy++) {
        echo '<option value="'.$fy.'"';
        if ($iFYID == $fy) {
            echo ' selected';
        }
        echo '>';
        echo MakeFYString($fy);
    }
    echo '</select>';
}

// Formats a fiscal year string
function MakeFYString($iFYID)
{
    $monthNow = date('m');

    if (SystemConfig::getValue('iFYMonth') == 1) {
        return 1996 + $iFYID;
    } else {
        return 1995 + $iFYID.'/'.mb_substr(1996 + $iFYID, 2, 2);
    }
}

// Runs an SQL query.  Returns the result resource.
// By default stop on error, unless a second (optional) argument is passed as false.
function RunQuery($sSQL, $bStopOnError = true)
{
    global $cnInfoCentral;
    mysqli_query($cnInfoCentral, "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    if ($result = mysqli_query($cnInfoCentral, $sSQL)) {
        return $result;
    } elseif ($bStopOnError) {
        if (SystemConfig::getValue('sLogLevel') == "100") { // debug level
            die(gettext('Cannot execute query.')."<p>$sSQL<p>".mysqli_error());
        } else {
            die('Database error or invalid data');
        }
    } else {
        return false;
    }
}

function ConvertCartToString($aCartArray)
{
    // Implode the array
    $sCartString = implode(',', $aCartArray);

    // Make sure the comma is chopped off the end
    if (mb_substr($sCartString, strlen($sCartString) - 1, 1) == ',') {
        $sCartString = mb_substr($sCartString, 0, strlen($sCartString) - 1);
    }

    // Make sure there are no duplicate commas
    $sCartString = str_replace(',,', '', $sCartString);

    return $sCartString;
}

/******************************************************************************
 * Returns the proper information to use for a field.
 * Person info overrides Family info if they are different.
 * If using family info and bFormat set, generate HTML tags for text color red.
 * If neither family nor person info is available, return an empty string.
 *****************************************************************************/

function SelectWhichInfo($sPersonInfo, $sFamilyInfo, $bFormat = false)
{
    $finalData = '';
    $isFamily = false;

    if (SystemConfig::getValue('bShowFamilyData')) {
        if ($sPersonInfo != '') {
            $finalData = $sPersonInfo;
        } elseif ($sFamilyInfo != '') {
            $isFamily = true;
            $finalData = $sFamilyInfo;
        }
    } elseif ($sPersonInfo != '') {
        $finalData = $sPersonInfo;
    }

    if ($bFormat && $isFamily) {
        $finalData = $finalData."<i class='fa fa-fw fa-tree'></i>";
    }

    return $finalData;
}

//
// Returns the correct address to use via the sReturnAddress arguments.
// Function value returns 0 if no info was given, 1 if person info was used, and 2 if family info was used.
// We do address lines 1 and 2 in together because seperately we might end up with half family address and half person address!
//
function SelectWhichAddress(&$sReturnAddress1, &$sReturnAddress2, $sPersonAddress1, $sPersonAddress2, $sFamilyAddress1, $sFamilyAddress2, $bFormat = false)
{
    if (SystemConfig::getValue('bShowFamilyData')) {
        if ($bFormat) {
            $sFamilyInfoBegin = "<span style='color: red;'>";
            $sFamilyInfoEnd = '</span>';
        }

        if ($sPersonAddress1 || $sPersonAddress2) {
            $sReturnAddress1 = $sPersonAddress1;
            $sReturnAddress2 = $sPersonAddress2;

            return 1;
        } elseif ($sFamilyAddress1 || $sFamilyAddress2) {
            if ($bFormat) {
                if ($sFamilyAddress1) {
                    $sReturnAddress1 = $sFamilyInfoBegin.$sFamilyAddress1.$sFamilyInfoEnd;
                } else {
                    $sReturnAddress1 = '';
                }
                if ($sFamilyAddress2) {
                    $sReturnAddress2 = $sFamilyInfoBegin.$sFamilyAddress2.$sFamilyInfoEnd;
                } else {
                    $sReturnAddress2 = '';
                }

                return 2;
            } else {
                $sReturnAddress1 = $sFamilyAddress1;
                $sReturnAddress2 = $sFamilyAddress2;

                return 2;
            }
        } else {
            $sReturnAddress1 = '';
            $sReturnAddress2 = '';

            return 0;
        }
    } else {
        if ($sPersonAddress1 || $sPersonAddress2) {
            $sReturnAddress1 = $sPersonAddress1;
            $sReturnAddress2 = $sPersonAddress2;

            return 1;
        } else {
            $sReturnAddress1 = '';
            $sReturnAddress2 = '';

            return 0;
        }
    }
}

function ChopLastCharacter($sText)
{
    return mb_substr($sText, 0, strlen($sText) - 1);
}

function AlternateRowStyle($sCurrentStyle)
{
    if ($sCurrentStyle == 'RowColorA') {
        return 'RowColorB';
    } else {
        return 'RowColorA';
    }
}

function ConvertToBoolean($sInput)
{
    if (empty($sInput)) {
        return false;
    } else {
        if (is_numeric($sInput)) {
            if ($sInput == 1) {
                return true;
            } else {
                return false;
            }
        } else {
            $sInput = strtolower($sInput);
            if (in_array($sInput, ['true', 'yes', 'si'])) {
                return true;
            } else {
                return false;
            }
        }
    }
}

function ConvertFromBoolean($sInput)
{
    if ($sInput) {
        return 1;
    } else {
        return 0;
    }
}

//
// Collapses a formatted phone number as long as the Country is known
// Eg. for United States:  555-555-1212 Ext. 123 ==> 5555551212e123
//
// Need to add other countries besides the US...
//
function CollapsePhoneNumber($sPhoneNumber, $sPhoneCountry)
{
    switch ($sPhoneCountry) {
    case 'United States':
      $sCollapsedPhoneNumber = '';
      $bHasExtension = false;

      // Loop through the input string
      for ($iCount = 0; $iCount <= strlen($sPhoneNumber); $iCount++) {

        // Take one character...
          $sThisCharacter = mb_substr($sPhoneNumber, $iCount, 1);

          // Is it a number?
          if (ord($sThisCharacter) >= 48 && ord($sThisCharacter) <= 57) {
              // Yes, add it to the returned value.
              $sCollapsedPhoneNumber .= $sThisCharacter;
          } // Is the user trying to add an extension?
          elseif (!$bHasExtension && ($sThisCharacter == 'e' || $sThisCharacter == 'E')) {
              // Yes, add the extension identifier 'e' to the stored string.
              $sCollapsedPhoneNumber .= 'e';
              // From now on, ignore other non-digits and process normally
              $bHasExtension = true;
          }
      }
      break;

    default:
      $sCollapsedPhoneNumber = $sPhoneNumber;
      break;
  }

    return $sCollapsedPhoneNumber;
}

//
// Expands a collapsed phone number into the proper format for a known country.
//
// If, during expansion, an unknown format is found, the original will be returned
// and the a boolean flag $bWeird will be set.  Unfortunately, because PHP does not
// allow for pass-by-reference in conjunction with a variable-length argument list,
// a dummy variable will have to be passed even if this functionality is unneeded.
//
// Need to add other countries besides the US...
//
function ExpandPhoneNumber($sPhoneNumber, $sPhoneCountry, &$bWeird)
{
  // this is normally unusefull
  
  /*$bWeird = false;
  $length = strlen($sPhoneNumber);

  switch ($sPhoneCountry) {
    case 'United States':
      if ($length == 0) {
          return '';
      } // 7 digit phone # with extension
      elseif (mb_substr($sPhoneNumber, 7, 1) == 'e') {
          return mb_substr($sPhoneNumber, 0, 3).'-'.mb_substr($sPhoneNumber, 3, 4).' Ext.'.mb_substr($sPhoneNumber, 8, 6);
      } // 10 digit phone # with extension
      elseif (mb_substr($sPhoneNumber, 10, 1) == 'e') {
          return mb_substr($sPhoneNumber, 0, 3).'-'.mb_substr($sPhoneNumber, 3, 3).'-'.mb_substr($sPhoneNumber, 6, 4).' Ext.'.mb_substr($sPhoneNumber, 11, 6);
      } elseif ($length == 7) {
          return mb_substr($sPhoneNumber, 0, 3).'-'.mb_substr($sPhoneNumber, 3, 4);
      } elseif ($length == 10) {
          return mb_substr($sPhoneNumber, 0, 3).'-'.mb_substr($sPhoneNumber, 3, 3).'-'.mb_substr($sPhoneNumber, 6, 4);
      } // Otherwise, there is something weird stored, so just leave it untouched and set the flag
      else {
          $bWeird = true;

          return $sPhoneNumber;
      }
      break;

    // If the country is unknown, we don't know how to format it, so leave it untouched
    default:
      return $sPhoneNumber;
  }*/
  
  return $sPhoneNumber;
}

function FormatAge($Month, $Day, $Year, $Flags)
{
    if (($Flags & 1)) { //||!$_SESSION['user']->isSeePrivacyDataEnabled()
        return;
    }

    if ($Year > 0) {
        if ($Year == date('Y')) {
            $monthCount = date('m') - $Month;
            if ($Day > date('d')) {
                $monthCount--;
            }
            if ($monthCount == 1) {
                return gettext('1 m old');
            } else {
                return $monthCount.' '.gettext('m old');
            }
        } elseif ($Year == date('Y') - 1) {
            $monthCount = 12 - $Month + date('m');
            if ($Day > date('d')) {
                $monthCount--;
            }
            if ($monthCount >= 12) {
                return gettext('1 yr old');
            } elseif ($monthCount == 1) {
                return gettext('1 m old');
            } else {
                return $monthCount.' '.gettext('m old');
            }
        } elseif ($Month > date('m') || ($Month == date('m') && $Day > date('d'))) {
            return date('Y') - 1 - $Year.' '.gettext('yrs old');
        } else {
            return date('Y') - $Year.' '.gettext('yrs old');
        }
    } else {
        return gettext('Unknown');
    }
}

//
// Formats an age suffix: age in years, or in months if less than one year old
//
function FormatAgeSuffix($birthDate, $Flags)
{
    if ($Flags == 1) {
        return '';
    }

    $ageSuffix = gettext('Unknown');

    $now = new DateTime();
    $age = $now->diff($birthDate);

    if ($age->y < 1) {
        if ($age->m > 1) {
            $ageSuffix = gettext('mos old');
        } else {
            $ageSuffix = gettext('mo old');
        }
    } else {
        if ($age->y > 1) {
            $ageSuffix = gettext('yrs old');
        } else {
            $ageSuffix = gettext('yr old');
        }
    }

    return $ageSuffix;
}

// Returns a string of a person's full name, formatted as specified by $Style
// $Style = 0  :  "Title FirstName MiddleName LastName, Suffix"
// $Style = 1  :  "Title FirstName MiddleInitial. LastName, Suffix"
// $Style = 2  :  "LastName, Title FirstName MiddleName, Suffix"
// $Style = 3  :  "LastName, Title FirstName MiddleInitial., Suffix"
//
function FormatFullName($Title, $FirstName, $MiddleName, $LastName, $Suffix, $Style)
{
    $nameString = '';

    switch ($Style) {

    case 0:
      if ($Title) {
          $nameString .= $Title.' ';
      }
      $nameString .= $FirstName;
      if ($MiddleName) {
          $nameString .= ' '.$MiddleName;
      }
      if ($LastName) {
          $nameString .= ' '.$LastName;
      }
      if ($Suffix) {
          $nameString .= ', '.$Suffix;
      }
      break;

    case 1:
      if ($Title) {
          $nameString .= $Title.' ';
      }
      $nameString .= $FirstName;
      if ($MiddleName) {
          $nameString .= ' '.strtoupper(mb_substr($MiddleName, 0, 1, 'UTF-8')).'.';
      }
      if ($LastName) {
          $nameString .= ' '.$LastName;
      }
      if ($Suffix) {
          $nameString .= ', '.$Suffix;
      }
      break;

    case 2:
      if ($LastName) {
          $nameString .= $LastName.', ';
      }
      if ($Title) {
          $nameString .= $Title.' ';
      }
      $nameString .= $FirstName;
      if ($MiddleName) {
          $nameString .= ' '.$MiddleName;
      }
      if ($Suffix) {
          $nameString .= ', '.$Suffix;
      }
      break;

    case 3:
      if ($LastName) {
          $nameString .= $LastName.', ';
      }
      if ($Title) {
          $nameString .= $Title.' ';
      }
      $nameString .= $FirstName;
      if ($MiddleName) {
          $nameString .= ' '.strtoupper(mb_substr($MiddleName, 0, 1, 'UTF-8')).'.';
      }
      if ($Suffix) {
          $nameString .= ', '.$Suffix;
      }
      break;
  }

    return $nameString;
}

// Generate a nicely formatted string for "FamilyName - Address / City, State" with available data
function FormatAddressLine($Address, $City, $State)
{
    $sText = '';

    if ($Address != '' || $City != '' || $State != '') {
        $sText = ' - ';
    }
    $sText .= $Address;
    if ($Address != '' && ($City != '' || $State != '')) {
        $sText .= ' / ';
    }
    $sText .= $City;
    if ($City != '' && $State != '') {
        $sText .= ', ';
    }
    $sText .= $State;

    return $sText;
}

function assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut = 'future')
{
    // This function takes a year, month and day from parseAndValidateDate.  On success this
    // function returns a string in the form "YYYY-MM-DD".  It returns FALSE on failure.
    // The year can be either 2 digit or 4 digit.  If a 2 digit year is passed the $passfut
    // indicates whether to return a 4 digit year in the past or the future.  The parameter
    // $passfut is not needed for the current year.  If unspecified it assumes the two digit year
    // is either this year or one of the next 99 years.

    // Parse the year
    // Take a 2 or 4 digit year and return a 4 digit year.  Use $pasfut to determine if
    // two digit year maps to past or future 4 digit year.
    if (strlen($sYear) == 2) {
        $thisYear = date('Y');
        $twoDigit = mb_substr($thisYear, 2, 2);
        if ($sYear == $twoDigit) {
            // Assume 2 digit year is this year
            $sYear = mb_substr($thisYear, 0, 4);
        } elseif ($pasfut == 'future') {
            // Assume 2 digit year is in next 99 years
            if ($sYear > $twoDigit) {
                $sYear = mb_substr($thisYear, 0, 2).$sYear;
            } else {
                $sNextCentury = $thisYear + 100;
                $sYear = mb_substr($sNextCentury, 0, 2).$sYear;
            }
        } else {
            // Assume 2 digit year was is last 99 years
            if ($sYear < $twoDigit) {
                $sYear = mb_substr($thisYear, 0, 2).$sYear;
            } else {
                $sLastCentury = $thisYear - 100;
                $sYear = mb_substr($sLastCentury, 0, 2).$sYear;
            }
        }
    } elseif (strlen($sYear) == 4) {
        $sYear = $sYear;
    } else {
        return false;
    }

    // Parse the Month
    // Take a one or two character month and return a two character month
    if (strlen($sMonth) == 1) {
        $sMonth = '0'.$sMonth;
    } elseif (strlen($sMonth) == 2) {
        $sMonth = $sMonth;
    } else {
        return false;
    }

    // Parse the Day
    // Take a one or two character day and return a two character day
    if (strlen($sDay) == 1) {
        $sDay = '0'.$sDay;
    } elseif (strlen($sDay) == 2) {
        $sDay = $sDay;
    } else {
        return false;
    }

    $sScanString = $sYear.'-'.$sMonth.'-'.$sDay;
    list($iYear, $iMonth, $iDay) = sscanf($sScanString, '%04d-%02d-%02d');

    if (checkdate($iMonth, $iDay, $iYear)) {
        return $sScanString;
    } else {
        return false;
    }
}

function parseAndValidateDate($data, $locale = 'US', $pasfut = 'future')
{
    // This function was written because I had no luck finding a PHP
    // function that would reliably parse a human entered date string for
    // dates before 1/1/1970 or after 1/19/2038 on any Operating System.
    //
    // This function has hooks for US English M/D/Y format as well as D/M/Y.  The
    // default is M/D/Y for date.  To change to D/M/Y use anything but "US" for
    // $locale.
    //
    // Y-M-D is allowed if the delimiter is "-" instead of "/"
    //
    // In order to help this function guess a two digit year a "past" or "future" flag is
    // passed to this function.  If no flag is passed the function assumes that two digit
    // years are in the future (or the current year).
    //
    // Month and day may be either 1 character or two characters (leading zeroes are not
    // necessary)

    // Determine if the delimiter is "-" or "/".  The delimiter must appear
    // twice or a FALSE will be returned.

    if (mb_substr_count($data, '-') == 2) {
        // Assume format is Y-M-D
        $iFirstDelimiter = strpos($data, '-');
        $iSecondDelimiter = strpos($data, '-', $iFirstDelimiter + 1);

        // Parse the year.
        $sYear = mb_substr($data, 0, $iFirstDelimiter);

        // Parse the month
        $sMonth = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);

        // Parse the day
        $sDay = mb_substr($data, $iSecondDelimiter + 1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
    } elseif ((mb_substr_count($data, '/') == 2) && ($locale == 'US')) {
        // Assume format is M/D/Y
        $iFirstDelimiter = strpos($data, '/');
        $iSecondDelimiter = strpos($data, '/', $iFirstDelimiter + 1);

        // Parse the month
        $sMonth = mb_substr($data, 0, $iFirstDelimiter);

        // Parse the day
        $sDay = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);

        // Parse the year
        $sYear = mb_substr($data, $iSecondDelimiter + 1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
    } elseif (mb_substr_count($data, '/') == 2) {
        // Assume format is D/M/Y
        $iFirstDelimiter = strpos($data, '/');
        $iSecondDelimiter = strpos($data, '/', $iFirstDelimiter + 1);

        // Parse the day
        $sDay = mb_substr($data, 0, $iFirstDelimiter);

        // Parse the month
        $sMonth = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);

        // Parse the year
        $sYear = mb_substr($data, $iSecondDelimiter + 1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
    }

    // If we made it this far it means the above logic was unable to parse the date.
    // Now try to parse using the function strtotime().  The strtotime() function does
    // not gracefully handle dates outside the range 1/1/1970 to 1/19/2038.  For this
    // reason consider strtotime() as a function of last resort.
    $timeStamp = strtotime($data);
    if ($timeStamp == false || $timeStamp <= 0) {
        // Some Operating Sytems and older versions of PHP do not gracefully handle
        // negative timestamps.  Bail if the timestamp is negative.
        return false;
    }

    // Now use the date() function to convert timestamp into YYYY-MM-DD
    $dateString = date('Y-m-d', $timeStamp);

    if (strlen($dateString) != 10) {
        // Common sense says we have a 10 charater string.  If not, something is wrong
        // and it's time to bail.
        return false;
    }

    if ($dateString > '1970-01-01' && $dateString < '2038-01-19') {
        // Success!
        return $dateString;
    }

    // Should not have made it this far.  Something is wrong so bail.
    return false;
}

// Processes and Validates custom field data based on its type.
//
// Returns false if the data is not valid, true otherwise.
//
function validateCustomField($type, &$data, $col_Name, &$aErrors)
{
    global $aLocaleInfo;
    $bErrorFlag = false;
    $aErrors[$col_Name] = '';

    switch ($type) {
    // Validate a date field
    case 2:
        // this part will work with each date format
        // Philippe logel
        $data = InputUtils::FilterDate($data);
        
      if (strlen($data) > 0) {
          $dateString = parseAndValidateDate($data);
          if ($dateString === false) {
              $aErrors[$col_Name] = gettext('Not a valid date');
              $bErrorFlag = true;
          } else {
              $data = $dateString;
          }
      }
      break;

    // Handler for 4-digit year
    case 6:
      if (strlen($data) != 0) {
          if (!is_numeric($data) || strlen($data) != 4) {
              $aErrors[$col_Name] = gettext('Invalid Year');
              $bErrorFlag = true;
          } elseif ($data > 2155 || $data < 1901) {
              $aErrors[$col_Name] = gettext('Out of range: Allowable values are 1901 to 2155');
              $bErrorFlag = true;
          }
      }
      break;

    // Handler for integer numbers
    case 8:
      if (strlen($data) != 0) {
          if ($aLocalInfo['thousands_sep']) {
              $data = preg_replace('/'.$aLocaleInfo['thousands_sep'].'/i', '', $data);  // remove any thousands separators
          }
          if (!is_numeric($data)) {
              $aErrors[$col_Name] = gettext('Invalid Number');
              $bErrorFlag = true;
          } elseif ($data < -2147483648 || $data > 2147483647) {
              $aErrors[$col_Name] = gettext('Number too large. Must be between -2147483648 and 2147483647');
              $bErrorFlag = true;
          }
      }
      break;

    // Handler for money amounts
    case 10:
      if (strlen($data) != 0) {
          if ($aLocaleInfo['mon_thousands_sep']) {
              $data = preg_replace('/'.$aLocaleInfo['mon_thousands_sep'].'/i', '', $data);
          }
          if (!is_numeric($data)) {
              $aErrors[$col_Name] = gettext('Invalid Number');
              $bErrorFlag = true;
          } elseif ($data > 999999999.99) {
              $aErrors[$col_Name] = gettext('Money amount too large. Maximum is $999999999.99');
              $bErrorFlag = true;
          }
      }
      break;

    // Otherwise ignore.. some types do not need validation or filtering
    default:
      break;
  }

    return !$bErrorFlag;
}

// Generates SQL for custom field update
//
// $special is currently only used for the phone country and the list ID for custom drop-down choices.
//
function sqlCustomField(&$sSQL, $type, $data, $col_Name, $special)
{
    switch ($type) {
    // boolean
    case 1:
      switch ($data) {
        case 'false':
          $data = "'false'";
          break;
        case 'true':
          $data = "'true'";
          break;
        default:
          $data = 'NULL';
          break;
      }

      $sSQL .= $col_Name.' = '.$data.', ';
      break;

    // date
    case 2:
      if (strlen($data) > 0) {
          $sSQL .= $col_Name.' = "'.$data.'", ';
      } else {
          $sSQL .= $col_Name.' = NULL, ';
      }
      break;

    // year
    case 6:
      if (strlen($data) > 0) {
          $sSQL .= $col_Name." = '".$data."', ";
      } else {
          $sSQL .= $col_Name.' = NULL, ';
      }
      break;

    // season
    case 7:
      if ($data != 'none') {
          $sSQL .= $col_Name." = '".$data."', ";
      } else {
          $sSQL .= $col_Name.' = NULL, ';
      }
      break;

    // integer, money
    case 8:
    case 10:
      if (strlen($data) > 0) {
          $sSQL .= $col_Name." = '".$data."', ";
      } else {
          $sSQL .= $col_Name.' = NULL, ';
      }
      break;

    // list selects
    case 9:
    case 12:
      if ($data != 0) {
          $sSQL .= $col_Name." = '".$data."', ";
      } else {
          $sSQL .= $col_Name.' = NULL, ';
      }
      break;

    // strings
    case 3:
    case 4:
    case 5:
      if (strlen($data) > 0) {
          $sSQL .= $col_Name." = '".$data."', ";
      } else {
          $sSQL .= $col_Name.' = NULL, ';
      }
      break;

    // phone
    case 11:
      if (strlen($data) > 0) {
          if (!isset($_POST[$col_Name.'noformat'])) {
              $sSQL .= $col_Name." = '".CollapsePhoneNumber($data, $special)."', ";
          } else {
              $sSQL .= $col_Name." = '".$data."', ";
          }
      } else {
          $sSQL .= $col_Name.' = NULL, ';
      }
      break;

    default:
      $sSQL .= $col_Name." = '".$data."', ";
      break;
  }
}

function FilenameToFontname($filename, $family)
{
    if ($filename == $family) {
        return ucfirst($family);
    } else {
        if (strlen($filename) - strlen($family) == 2) {
            return ucfirst($family).gettext(' Bold Italic');
        } else {
            if (mb_substr($filename, strlen($filename) - 1) == 'i') {
                return ucfirst($family).gettext(' Italic');
            } else {
                return ucfirst($family).gettext(' Bold');
            }
        }
    }
}

function FontFromName($fontname)
{
    $fontinfo = explode(' ', $fontname);
    switch (count($fontinfo)) {
    case 1:
      return [$fontinfo[0], ''];
    case 2:
      return [$fontinfo[0], mb_substr($fontinfo[1], 0, 1)];
    case 3:
      return [$fontinfo[0], mb_substr($fontinfo[1], 0, 1).mb_substr($fontinfo[2], 0, 1)];
  }
}

// Figure out the class ID for "Member", should be one (1) unless they have been playing with the
// classification manager.
function FindMemberClassID()
{
    //Get Classifications
    $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
    $rsClassifications = RunQuery($sSQL);

    while ($aRow = mysqli_fetch_array($rsClassifications)) {
        extract($aRow);
        if ($lst_OptionName == gettext('Member')) {
            return $lst_OptionID;
        }
    }

    return 1; // Should not get here, but if we do get here use the default value.
}

// Prepare data for entry into MySQL database.
// This function solves the problem of inserting a NULL value into MySQL since
// MySQL will not accept 'NULL'.  One drawback is that it is not possible
// to insert the character string "NULL" because it will be inserted as a MySQL NULL!
// This will produce a database error if NULL's are not allowed!  Do not use this
// function if you intend to insert the character string "NULL" into a field.
function MySQLquote($sfield)
{
    $sfield = trim($sfield);

    if ($sfield == 'NULL') {
        return 'NULL';
    } elseif ($sfield == "'NULL'") {
        return 'NULL';
    } elseif ($sfield == '') {
        return 'NULL';
    } elseif ($sfield == "''") {
        return 'NULL';
    } else {
        if ((mb_substr($sfield, 0, 1) == "'") && (mb_substr($sfield, strlen($sfield) - 1, 1)) == "'") {
            return $sfield;
        } else {
            return "'".$sfield."'";
        }
    }
}

//Function to check email
//From http://www.tienhuis.nl/php-email-address-validation-with-verify-probe
//Functions checkndsrr and getmxrr are not enabled on windows platforms & therefore are disabled
//Future use may be to enable a Admin option to enable these options
//domainCheck verifies domain is valid using dns, verify uses SMTP to verify actual account exists on server

function checkEmail($email, $domainCheck = false, $verify = false, $return_errors = false)
{
    global $checkEmailDebug;
    if ($checkEmailDebug) {
        echo '<pre>';
    }
    // Check syntax with regex
    if (preg_match('/^([a-zA-Z0-9\._\+-]+)\@((\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,7}|[0-9]{1,3})(\]?))$/', $email, $matches)) {
        $user = $matches[1];
        $domain = $matches[2];
        // Check availability of DNS MX records
        if ($domainCheck && function_exists('checkdnsrr')) {
            // Construct array of available mailservers
            if (getmxrr($domain, $mxhosts, $mxweight)) {
                for ($i = 0; $i < count($mxhosts); $i++) {
                    $mxs[$mxhosts[$i]] = $mxweight[$i];
                }
                asort($mxs);
                $mailers = array_keys($mxs);
            } elseif (checkdnsrr($domain, 'A')) {
                $mailers[0] = gethostbyname($domain);
            } else {
                $mailers = [];
            }
            $total = count($mailers);
            // Query each mailserver
            if ($total > 0 && $verify) {
                // Check if mailers accept mail
                for ($n = 0; $n < $total; $n++) {
                    // Check if socket can be opened
                    if ($checkEmailDebug) {
                        echo "Checking server $mailers[$n]...\n";
                    }
                    $connect_timeout = 2;
                    $errno = 0;
                    $errstr = 0;
                    $probe_address = SystemConfig::getValue('sToEmailAddress');
                    // Try to open up socket
                    if ($sock = @fsockopen($mailers[$n], 25, $errno, $errstr, $connect_timeout)) {
                        $response = fgets($sock);
                        if ($checkEmailDebug) {
                            echo "Opening up socket to $mailers[$n]... Succes!\n";
                        }
                        stream_set_timeout($sock, 5);
                        $meta = stream_get_meta_data($sock);
                        if ($checkEmailDebug) {
                            echo "$mailers[$n] replied: $response\n";
                        }
                        $cmds = [
              'HELO '.SystemConfig::getValue('sSMTPHost'), // Be sure to set this correctly!
              "MAIL FROM: <$probe_address>",
              "RCPT TO: <$email>",
              'QUIT',
            ];
                        // Hard error on connect -> break out
                        if (!$meta['timed_out'] && !preg_match('/^2\d\d[ -]/', $response)) {
                            $error = "Error: $mailers[$n] said: $response\n";
                            break;
                        }
                        foreach ($cmds as $cmd) {
                            $before = microtime(true);
                            fwrite($sock, "$cmd\r\n");
                            $response = fgets($sock, 4096);
                            $t = 1000 * (microtime(true) - $before);
                            if ($checkEmailDebug) {
                                echo htmlentities("$cmd\n$response").'('.sprintf('%.2f', $t)." ms)\n";
                            }
                            if (!$meta['timed_out'] && preg_match('/^5\d\d[ -]/', $response)) {
                                $error = "Unverified address: $mailers[$n] said: $response";
                                break 2;
                            }
                        }
                        fclose($sock);
                        if ($checkEmailDebug) {
                            echo "Succesful communication with $mailers[$n], no hard errors, assuming OK";
                        }
                        break;
                    } elseif ($n == $total - 1) {
                        $error = "None of the mailservers listed for $domain could be contacted";
                    }
                }
            } elseif ($total <= 0) {
                $error = "No usable DNS records found for domain '$domain'";
            }
        }
    } else {
        $error = 'Address syntax not correct';
    }
    if ($checkEmailDebug) {
        echo '</pre>';
    }
    //echo "</pre>";
    if ($return_errors) {
        // Give back details about the error(s).
        // Return FALSE if there are no errors.
        // Keep this in mind when using it like:
        // if(checkEmail($addr)) {
        // Because of this strange behaviour this
        // is not default ;-)
        if (isset($error)) {
            return htmlentities($error);
        } else {
            return false;
        }
    } else {
        // 'Old' behaviour, simple to understand
        if (isset($error)) {
            return false;
        } else {
            return true;
        }
    }
}

function getFamilyList($sDirRoleHead, $sDirRoleSpouse, $classification = 0, $sSearchTerm = 0)
{
    if ($classification) {
        if ($sSearchTerm) {
            $whereClause = " WHERE per_cls_ID='".$classification."' AND fam_Name LIKE '%".$sSearchTerm."%' ";
        } else {
            $whereClause = " WHERE per_cls_ID='".$classification."' ";
        }
        $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam LEFT JOIN person_per ON fam_ID = per_fam_ID $whereClause ORDER BY fam_Name";
    } else {
        if ($sSearchTerm) {
            $whereClause = " WHERE fam_Name LIKE '%".$sSearchTerm."%' ";
        } else {
            $whereClause = '';
        }
        $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam $whereClause ORDER BY fam_Name";
    }

    $rsFamilies = RunQuery($sSQL);

    // Build Criteria for Head of Household
    if (!$sDirRoleHead) {
        $sDirRoleHead = '1';
    }
    $head_criteria = ' per_fmr_ID = '.$sDirRoleHead;
    // If more than one role assigned to Head of Household, add OR
    $head_criteria = str_replace(',', ' OR per_fmr_ID = ', $head_criteria);
    // Add Spouse to criteria
    if (intval($sDirRoleSpouse) > 0) {
        $head_criteria .= " OR per_fmr_ID = $sDirRoleSpouse";
    }
    // Build array of Head of Households and Spouses with fam_ID as the key
    $sSQL = 'SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND ('.$head_criteria.') ORDER BY per_fam_ID';
    $rs_head = RunQuery($sSQL);
    $aHead = [];
    while (list($head_firstname, $head_famid) = mysqli_fetch_row($rs_head)) {
        if ($head_firstname && isset($aHead[$head_famid])) {
            $aHead[$head_famid] .= ' & '.$head_firstname;
        } elseif ($head_firstname) {
            $aHead[$head_famid] = $head_firstname;
        }
    }
    $familyArray = [];
    while ($aRow = mysqli_fetch_array($rsFamilies)) {
        extract($aRow);
        $name = $fam_Name;
        if (isset($aHead[$fam_ID])) {
            $name .= ', '.$aHead[$fam_ID];
        }
        $name .= ' '.FormatAddressLine($fam_Address1, $fam_City, $fam_State);

        $familyArray[$fam_ID] = $name;
    }

    return $familyArray;
}

function buildFamilySelect($iFamily, $sDirRoleHead, $sDirRoleSpouse)
{
    //Get Families for the drop-down
    $familyArray = getFamilyList($sDirRoleHead, $sDirRoleSpouse);
    foreach ($familyArray as $fam_ID => $fam_Data) {
        $html .= '<option value="'.$fam_ID.'"';
        if ($iFamily == $fam_ID) {
            $html .= ' selected';
        }
        $html .= '>'.$fam_Data;
    }

    return $html;
}

function genGroupKey($methodSpecificID, $famID, $fundIDs, $date)
{
    $uniqueNum = 0;
    while (1) {
        $GroupKey = $methodSpecificID.'|'.$uniqueNum.'|'.$famID.'|'.$fundIDs.'|'.$date;
        $sSQL = "SELECT COUNT(plg_GroupKey) FROM pledge_plg WHERE plg_PledgeOrPayment='Payment' AND plg_GroupKey='".$GroupKey."'";
        $rsResults = RunQuery($sSQL);
        list($numGroupKeys) = mysqli_fetch_row($rsResults);
        if ($numGroupKeys) {
            ++$uniqueNum;
        } else {
            return $GroupKey;
        }
    }
}

function requireUserGroupMembership($allowedRoles = null)
{
    if ( isset($_SESSION['updateDataBase']) && $_SESSION['updateDataBase'] == true ) {// we don't have to interfer with this test
      return true;
    }
    
    if (!$allowedRoles) {
        throw new Exception('Role(s) must be defined for the function which you are trying to access.  End users should never see this error unless something went horribly wrong.');
    }
    if ($_SESSION[$allowedRoles] || $_SESSION['user']->isAdmin() || $_SESSION['user']->isAddRecordsEnabled()) {  //most of the time the API endpoint will specify a single permitted role, or the user is an admin
        // new $_SESSION['user']->isAddRecordsEnabled() : Philippe Logel
        return true;
    } elseif (is_array($allowedRoles)) {  //sometimes we might have an array of allowed roles.
        foreach ($allowedRoles as $role) {
            if ($_SESSION[$role]) {
                // The current allowed role is in the user's session variable
                return true;
            }
        }
    }

    //if we get to this point in the code, then the user is not authorized.
    throw new Exception('User is not authorized to access '.debug_backtrace()[1]['function'], 401);
}

function generateGroupRoleEmailDropdown($roleEmails, $href)
{
    foreach ($roleEmails as $role => $Email) {
        if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($Email, SystemConfig::getValue('sToEmailAddress'))) {
            $Email .= $sMailtoDelimiter.SystemConfig::getValue('sToEmailAddress');
        }
        $Email = urlencode($Email);  // Mailto should comply with RFC 2368
    ?>
      <li> <a href="<?= $href.mb_substr($Email, 0, -3) ?>"><?= gettext($role) ?></a></li>
    <?php
    }
}

?>
