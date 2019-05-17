<?php
/*******************************************************************************
 *
 *  filename    : CartToFamily.php
 *  last change : 2003-10-09
 *  description : Add cart records to a family
 *
 *  http://www.ecclesiacrm.com/
 *  Copyright 2003 Chris Gebhardt
 *            2018 Philippe Logel
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\Utils\InputUtils;
use EcclesiaCRM\Utils\OutputUtils;
use EcclesiaCRM\Utils\RedirectUtils;
use EcclesiaCRM\Utils\MiscUtils;
use EcclesiaCRM\ListOptionQuery;
use EcclesiaCRM\FamilyQuery;
use EcclesiaCRM\Map\FamilyTableMap;
use EcclesiaCRM\Family;
use EcclesiaCRM\PersonQuery;
use EcclesiaCRM\dto\Cart;
use EcclesiaCRM\dto\StateDropDown;
use EcclesiaCRM\dto\CountryDropDown;
use EcclesiaCRM\SessionUser;


// Security: User must have add records permission
if (!SessionUser::getUser()->isAddRecordsEnabled()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Was the form submitted?
if (isset($_POST['Submit']) && count($_SESSION['aPeopleCart']) > 0) {

    // Get the FamilyID
    $iFamilyID = InputUtils::LegacyFilterInput($_POST['FamilyID'], 'int');
    
    // Are we creating a new family
    if ($iFamilyID == 0) {
        $sFamilyName = InputUtils::LegacyFilterInput($_POST['FamilyName']);

        $dWeddingDate = InputUtils::LegacyFilterInput($_POST['WeddingDate']);
        if (strlen($dWeddingDate) > 0) {
            $dWeddingDate = '"'.$dWeddingDate.'"';
        } else {
            $dWeddingDate = null;
        }

        $iPersonAddress = InputUtils::LegacyFilterInput($_POST['PersonAddress']);

        $per_Address1 = null;
        $per_Address2 = null;
        $per_City = null;
        $per_Zip = null;
        $per_Country = null;
        $per_State = null;
        $per_HomePhone = null;
        $per_WorkPhone = null;
        $per_CellPhone = null;
        $per_Email = null;
            
        if ($iPersonAddress != 0) {
            $person=PersonQuery::Create()->findOneById($iPersonAddress);

            if (!is_null($person)) {
              $per_Address1  = $person->getAddress1();
              $per_Address2  = $person->getAddress2();
              $per_City      = $person->getCity();
              $per_Zip       = $person->getZip();
              $per_Country   = $person->getCountry();
              $per_State     = $person->getState();
              $per_HomePhone = $person->getHomePhone();
              $per_WorkPhone = $person->getWorkPhone();
              $per_CellPhone = $person->getCellPhone();
              $per_Email     = $person->getEmail();
            }
        }

        MiscUtils::SelectWhichAddress($sAddress1, $sAddress2, InputUtils::LegacyFilterInput($_POST['Address1']), InputUtils::LegacyFilterInput($_POST['Address2']), $per_Address1, $per_Address2, false);
        $sCity = MiscUtils::SelectWhichInfo(InputUtils::LegacyFilterInput($_POST['City']), $per_City);
        $sZip = MiscUtils::SelectWhichInfo(InputUtils::LegacyFilterInput($_POST['Zip']), $per_Zip);
        $sCountry = MiscUtils::SelectWhichInfo(InputUtils::LegacyFilterInput($_POST['Country']), $per_Country);

        if ($sCountry == 'United States' || $sCountry == 'Canada') {
            $sState = InputUtils::LegacyFilterInput($_POST['State']);
        } else {
            $sState = InputUtils::LegacyFilterInput($_POST['StateTextbox']);
        }
        $sState = MiscUtils::SelectWhichInfo($sState, $per_State);

        // Get and format any phone data from the form.
        $sHomePhone = InputUtils::LegacyFilterInput($_POST['HomePhone']);
        $sWorkPhone = InputUtils::LegacyFilterInput($_POST['WorkPhone']);
        $sCellPhone = InputUtils::LegacyFilterInput($_POST['CellPhone']);
        if (!isset($_POST['NoFormat_HomePhone'])) {
            $sHomePhone = MiscUtils::CollapsePhoneNumber($sHomePhone, $sCountry);
        }
        if (!isset($_POST['NoFormat_WorkPhone'])) {
            $sWorkPhone = MiscUtils::CollapsePhoneNumber($sWorkPhone, $sCountry);
        }
        if (!isset($_POST['NoFormat_CellPhone'])) {
            $sCellPhone = MiscUtils::CollapsePhoneNumber($sCellPhone, $sCountry);
        }

        $sHomePhone = MiscUtils::SelectWhichInfo($sHomePhone, $per_HomePhone);
        $sWorkPhone = MiscUtils::SelectWhichInfo($sWorkPhone, $per_WorkPhone);
        $sCellPhone = MiscUtils::SelectWhichInfo($sCellPhone, $per_CellPhone);
        $sEmail = MiscUtils::SelectWhichInfo(InputUtils::LegacyFilterInput($_POST['Email']), $per_Email);

        if (strlen($sFamilyName) == 0) {
            $sError = '<p class="callout callout-warning" align="center" style="color:red;">'._('No family name entered!').'</p>';
            $bError = true;
        } else {
            $fam = new Family();
            
            $fam->setName($sFamilyName);
            $fam->setAddress1($sAddress1);
            $fam->setAddress1($sAddress2);
            $fam->setCity($sCity);
            $fam->setState($sState);
            $fam->setZip($sZip);
            $fam->setCountry($sCountry);
            $fam->setHomePhone($sHomePhone);
            $fam->setWorkPhone($sWorkPhone);
            $fam->setCellPhone($sCellPhone);
            $fam->setEmail($sEmail);
            $fam->setWeddingdate($dWeddingDate);
            $fam->setDateEntered(date('YmdHis'));
            $fam->setEnteredBy(SessionUser::getUser()->getPersonId());
            
            $fam->save();
            
            //Get the key back
            $last = FamilyQuery::create() 
              ->addAsColumn('maxId', 'MAX('.FamilyTableMap::COL_FAM_ID.')')
              ->findOne();
              
            $iFamilyID = $last->getMaxId();
            
        }
    }

    if (!$bError) {
        // Loop through the cart array
        $iCount = 0;
        foreach ($_SESSION['aPeopleCart'] as $element) {
            $iPersonID = $_SESSION['aPeopleCart'][$element[key]];
            $ormPerson = PersonQuery::Create()
                         ->findOneById($iPersonID);

            // Make sure they are not already in a family
            if ($ormPerson->getFamId() == 0) {
                $iFamilyRoleID = 0;

                if (isset($_POST['role'.$iPersonID])) {
                    $iFamilyRoleID = InputUtils::LegacyFilterInput($_POST['role'.$iPersonID], 'int');
                }
                
                $ormPerson->setFamId($iFamilyID);
                $ormPerson->setFmrId($iFamilyRoleID);
                $ormPerson->save();

                $iCount++;
            }
        }

        $sGlobalMessage = $iCount.' records(s) successfully added to selected Family.';
        
        // empty the cart
        if(sizeof($_SESSION['aPeopleCart'])>0) {
          $_SESSION['aPeopleCart'] = [];
        }

        RedirectUtils::Redirect('FamilyView.php?FamilyID='.$iFamilyID.'&Action=EmptyCart');
    }
}

// Set the page title and include HTML header
$sPageTitle = _('Add Cart to Family');
require 'Include/Header.php';

echo $sError;
?>
<form method="post">
<div class="box">
<?php
if (count($_SESSION['aPeopleCart']) > 0) {

    // Get all the families
    $ormFamilies = FamilyQuery::Create()
                    ->orderByName()
                    ->find();
                    
    // Get the family roles
    $ormFamilyRoles = ListOptionQuery::Create()
          ->filterById(2)
          ->orderByOptionSequence()
          ->find();


    $sRoleOptionsHTML = '';
    foreach ($ormFamilyRoles as $ormFamilyRole) {
        $sRoleOptionsHTML .= '<option value="'.$ormFamilyRole->getOptionId().'">'.$ormFamilyRole->getOptionName().'</option>';
    }

    $ormCartItems = PersonQuery::Create()
                ->Where('per_ID IN ('.Cart::ConvertCartToString($_SESSION['aPeopleCart']).')')
                ->orderByLastName()
                ->find();
    ?>
  <table class='table table-hover dt-responsive'>
    <tr>
    <td>&nbsp;</td>
    <td><b><?= _('Name') ?></b></td>
    <td align="center"><b><?= _('Assign Role') ?></b></td>

    <?php
    $count = 1;    
    foreach ($ormCartItems as $ormCartItem) {
        $sRowClass = MiscUtils::AlternateRowStyle($sRowClass);
        ?>
        <tr class="<?= $sRowClass ?>">
          <td align="center"><?= $count++ ?></td>
          <td><img src="<?= SystemURLs::getRootPath()?>/api/persons/<?= $ormCartItem->getId() ?>/thumbnail" class="direct-chat-img"> &nbsp <a href="PersonView.php?PersonID=<?= $ormCartItem->getId() ?>"><?= OutputUtils::FormatFullName($ormCartItem->getTitle(), $ormCartItem->getFirstName(), $ormCartItem->getMiddleName(), $ormCartItem->getLastName(), $ormCartItem->getSuffix(), 1) ?></a></td>
          <td align="center">
          <?php
            if ($ormCartItem->getFamId() == 0) {
              ?>
                  <select name="role<?= $ormCartItem->getId() ?>" class="form-control"><?= $sRoleOptionsHTML ?></select>
              <?php
            } else {
              ?>
                  <?= _('Already in a family') ?>
              <?php
            }
          ?>
          </td>
        </tr>
    <?php
    }
    ?>

  </table>
</div>
<div class="box">
<div class="table-responsive">
<table align="center" class="table table-hover" id="cart-family-table" width="100%">
  <thead>
    <tr>
        <th></th>
        <th></th>
    </tr>
  </thead>
  <tbody>
    <tr>
    <td class="LabelColumn"><?= _('Add to Family') ?>:</td>
    <td class="TextColumn">
        <select name="FamilyID"  class="form-control">
              <option value="0"><?= _('Create new family') ?></option>
      <?php            
        // Create the family select drop-down
        foreach ($ormFamilies as $ormFamily) {
        ?>
          <option value="<?= $ormFamily->getId()?>"><?= $ormFamily->getName() ?></option>
        <?php
        }
        ?>
       </select>
    </td>
  </tr>

  <tr>
    <td></td>
    <td><p class="MediumLargeText"><?= _('If adding a new family, enter data below.') ?></p></td>
  </tr>


  <tr>
    <td class="LabelColumn"><?= _('Family Name') ?>:</td>
    <td class="TextColumnWithBottomBorder form-control"><input type="text" Name="FamilyName" value="<?= $sName ?>" maxlength="48" class="form-control"><font color="red"><?= $sNameError ?></font></td>
  </tr>

  <tr>
        <td class="LabelColumn"><?= _('Wedding Date') ?>:</td>
    <td class="TextColumnWithBottomBorder"><input type="text" Name="WeddingDate" value="<?= $dWeddingDate ?>" maxlength="10" id="sel1" size="15"  class="form-control active date-picker"><font color="red"><?php echo '<BR>'.$sWeddingDateError ?></font></td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Use address/contact data from') ?>:</td>
    <td class="TextColumn">
      <select name="PersonAddress"  class="form-control">
         <option value="0"><?= _('Only the new data below') ?></option>

      <?php 
      foreach ($ormCartItems as $ormCartItem) {
        if ($ormCartItem->getFamId() == 0) {
        ?>
           <option value="<?= $ormCartItem->getId() ?>"><?= $ormCartItem->getFirstName()?> <?= $ormCartItem->getLastName() ?></option>
        <?php      
        }
      }
      ?>
      </select>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Address') ?> 1:</td>
    <td class="TextColumn"><input type="text" Name="Address1" value="<?= $sAddress1 ?>" size="50" maxlength="250" class="form-control"></td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Address') ?> 2:</td>
    <td class="TextColumn"><input type="text" Name="Address2" value="<?= $sAddress2 ?>" size="50" maxlength="250" class="form-control"></td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('City') ?>:</td>
    <td class="TextColumn"><input type="text" Name="City" value="<?= $sCity ?>" maxlength="50" class="form-control"></td>
  </tr>

  <tr <?= (SystemConfig::getValue('bStateUnusefull'))?'style="display: none;"':""?>>
    <td class="LabelColumn"><?= _('State') ?>:</td>
    <td class="TextColumn">
      <?php                          
          $statesDD = new StateDropDown();     
          echo $statesDD->getDropDown($sState);
      ?>
      OR
      <input class="form-control" type="text" name="StateTextbox" value="<?php if ($sCountry != 'United States' && $sCountry != 'Canada') {
            echo $sState;
        } ?>" size="20" maxlength="30">
      <BR><?= _('(Use the textbox for countries other than US and Canada)') ?>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Zip')?>:</td>
    <td class="TextColumn">
      <input class="form-control" type="text" Name="Zip" value="<?= $sZip ?>" maxlength="10" size="8">
    </td>

  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Country') ?>:</td>
    <td class="TextColumnWithBottomBorder">
      <?= CountryDropDown::getDropDown($sCountry); ?>
    </td>
  </tr>

  <tr>
    <td>&nbsp;</td><td></td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Home Phone') ?>:</td>
    <td class="TextColumn">
      <input class="form-control" type="text" Name="HomePhone" value="<?= $sHomePhone ?>" size="30" maxlength="30" data-inputmask="'mask': '<?= SystemConfig::getValue('sPhoneFormat') ?>'" data-mask>
      <input type="checkbox" name="NoFormat_HomePhone" value="1" <?php if ($bNoFormat_HomePhone) {
            echo ' checked';
        } ?>><?= _('Do not auto-format') ?>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Work Phone') ?>:</td>
    <td class="TextColumn">
      <input class="form-control" type="text" name="WorkPhone" value="<?php echo $sWorkPhone ?>" size="30" maxlength="30" data-inputmask="'mask': '<?= SystemConfig::getValue('sPhoneFormat') ?>'" data-mask>
      <input type="checkbox" name="NoFormat_WorkPhone" value="1" <?php if ($bNoFormat_WorkPhone) {
            echo ' checked';
        } ?>><?= _('Do not auto-format') ?>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Mobile Phone') ?>:</td>
    <td class="TextColumn">
      <input class="form-control" type="text" name="CellPhone" value="<?php echo $sCellPhone ?>" size="30" maxlength="30" data-inputmask="'mask': '<?= SystemConfig::getValue('sPhoneFormat') ?>'" data-mask>
      <input type="checkbox" name="NoFormat_CellPhone" value="1" <?php if ($bNoFormat_CellPhone) {
            echo ' checked';
        } ?>><?= _('Do not auto-format') ?>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= _('Email') ?>:</td>
    <td class="TextColumnWithBottomBorder"><input class="form-control" type="text" Name="Email" value="<?= $sEmail ?>" size="30" maxlength="50"></td>
  </tr>
</tbody>
</table>
</div>
<p align="center">
<BR>
<input type="submit" class="btn btn-primary" name="Submit" value="<?= _('Add to Family') ?>">
<BR><BR>
</p>
<?php
} else {
            echo "<p align=\"center\" class='callout callout-warning'>"._('Your cart is empty!').'</p>';
        }
?>
</div>
</form>


<script  nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {
        $("#country-input").select2();
        $("#state-input").select2();
        
        $(function() {
          $("[data-mask]").inputmask();
        });

        
        $("#cart-family-table").DataTable({
            responsive:true,
            paging: false,
            searching: false,
            ordering: false,
            info:     false,
            //dom: window.CRM.plugin.dataTable.dom,
            fnDrawCallback: function( settings ) {
              $("#selector thead").remove(); 
            }
        });
    });
</script>
<?php require 'Include/Footer.php'; ?>
