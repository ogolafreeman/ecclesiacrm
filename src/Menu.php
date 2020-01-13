<?php
/*******************************************************************************
 *
 *  filename    : Menu.php
 *  description : menu that appears after login, shows login attempts
 *
 *  http://www.ecclesiacrm.com/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker, Michael Wilt
 *
 *  Additional Contributors:
 *  2006 Ed Davis
 *  2020 Philippe Logel
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use EcclesiaCRM\DepositQuery;
use EcclesiaCRM\Service\FinancialService;
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\dto\ChurchMetaData;
use EcclesiaCRM\dto\MenuEventsCount;
use EcclesiaCRM\FamilyQuery;
use EcclesiaCRM\PersonQuery;
use EcclesiaCRM\PastoralCareQuery;
use EcclesiaCRM\Map\PastoralCareTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use EcclesiaCRM\utils\RedirectUtils;
use EcclesiaCRM\SessionUser;


// we place this part to avoid a problem during the upgrade process
// Set the page title
$sPageTitle = _('Welcome to') . ' ' . ChurchMetaData::getChurchName();

require 'Include/Header.php';

$financialService = new FinancialService();

if (!(SessionUser::getUser()->isFinanceEnabled() || SessionUser::getUser()->isMainDashboardEnabled() || SessionUser::getUser()->isPastoralCareEnabled())) {
    RedirectUtils::Redirect('PersonView.php?PersonID=' . SessionUser::getUser()->getPersonId());
    exit;
}

$depositData = false;  //Determine whether or not we should display the deposit line graph
if (SessionUser::getUser()->isFinanceEnabled()) {
    $deposits = DepositQuery::create()->filterByDate(['min' => date('Y-m-d', strtotime('-90 days'))])->find();
    if (count($deposits) > 0) {
        $depositData = $deposits->toJSON();
    }
}

$showBanner = SystemConfig::getBooleanValue("bEventsOnDashboardPresence");

$peopleWithBirthDays = MenuEventsCount::getBirthDates();
$Anniversaries = MenuEventsCount::getAnniversaries();
$peopleWithBirthDaysCount = MenuEventsCount::getNumberBirthDates();
$AnniversariesCount = MenuEventsCount::getNumberAnniversaries();


if (SessionUser::getUser()->isGdrpDpoEnabled() && SystemConfig::getBooleanValue('bGDPR')) {
    $time = new DateTime('now');
    $newtime = $time->modify('-' . SystemConfig::getValue('iGdprExpirationDate') . ' year')->format('Y-m-d');

    // when a family is completely deactivated : we seek the families with more than one member. A one person family = a fmaily with an address
    $subQuery = FamilyQuery::create()
        ->withColumn('Family.Id', 'FamId')
        ->leftJoinPerson()
        ->withColumn('COUNT(Person.Id)', 'cnt')
        ->filterByDateDeactivated($newtime, Criteria::LESS_THAN)
        ->groupById();//groupBy('Family.Id');

    $families = FamilyQuery::create()
        ->addSelectQuery($subQuery, 'res')
        ->where('res.cnt>1 AND Family.Id=res.FamId')
        ->find();

    $numFamilies = $families->count();

    // for the persons
    $persons = PersonQuery::create()
        ->filterByDateDeactivated($newtime, Criteria::LESS_THAN)// GDRP
        ->_or() // or : this part is unusefull, it's only for debugging
        ->useFamilyQuery()
        ->filterByDateDeactivated($newtime, Criteria::LESS_THAN)// GDRP, when a Family is completely deactivated
        ->endUse()
        ->orderByLastName()
        ->find();

    $numPersons = $persons->count();

    if ($numPersons + $numFamilies > 0) {
        ?>
        <div class="alert alert-gpdr alert-dismissible " id="Menu_GDRP">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h4 class="alert-heading"><?= _("GDPR") ?> (<?= _("message for the DPO") ?>)</h4>
            <div class="row">
                <div class="col-sm-1">
                </div>
                <div class="col-sm-5">
                    <?php
                    if ($numPersons) {
                        ?>
                        <?php
                        if ($numPersons == 1) {
                            ?>
                            <?= $numPersons . " " . _("person must be deleted from the CRM.") ?>
                        <?php } else { ?>
                            <?= $numPersons . " " . _("persons must be deleted from the CRM.") ?>
                            <?php
                        }
                        ?>
                        <br>
                        <b><?= _("Click the") ?> <a
                                href="<?= SystemURLs::getRootPath() ?>/v2/personlist/GDRP"><?= _("link") ?></a> <?= _("to solve the problem.") ?>
                        </b>
                        <?php
                    } else {
                        ?>
                        <?= _("No Person to remove in the CRM.") ?>
                        <?php
                    }
                    ?>
                </div>
                <div class="col-sm-5">
                    <?php
                    if ($numFamilies) {
                        ?>
                        <?php
                        if ($numFamilies == 1) {
                            ?>
                            <?= $numFamilies . " " . _("family must be deleted from the CRM.") ?>
                        <?php } else { ?>
                            <?= $numFamilies . " " . _("families must be deleted from the CRM.") ?>
                            <?php
                        }
                        ?>
                        <br>
                        <b><?= _("Click the") ?> <a
                                href="<?= SystemURLs::getRootPath() ?>/v2/familylist/GDRP"><?= _("link") ?></a> <?= _("to solve the problem.") ?>
                        </b>
                        <?php
                    } else {
                        ?>
                        <?= _("No Family to remove in the CRM.") ?>
                        <?php
                    }
                    ?>
                </div>
                <div class="col-sm-1">
                </div>
            </div>
        </div>
        <?php
    }
}

if ($showBanner && ($peopleWithBirthDaysCount > 0 || $AnniversariesCount > 0) && SessionUser::getUser()->isSeePrivacyDataEnabled()) {
    ?>
    <div class="alert alert-birthday alert-dismissible " id="Menu_Banner">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>

    <?php
    $new_unclassified_row = false;
    $cout_unclassified_people = 0;
    $unclassified = "";

    $new_row = false;
    $count_people = 0;
    $classified = "";

    $new_row = false;
    $count_people = 0;

    foreach ($peopleWithBirthDays as $peopleWithBirthDay) {
        if ($peopleWithBirthDay->getOnlyVisiblePersonView()) {
            if ($new_unclassified_row == false) {
                $unclassified .= '<div class="row">';
                $new_unclassified_row = true;
                $unclassified .= '<div class="col-sm-3">';
                $unclassified .= '<label class="checkbox-inline">';

                if ($peopleWithBirthDay->getUrlIcon() != '') {
                    $unclassified .= '<img src="' . SystemURLs::getRootPath() . "/skin/icons/markers/" . $peopleWithBirthDay->getUrlIcon() . '">';
                }

                $unclassified .= '<a href="' . $peopleWithBirthDay->getViewURI() . '" class="btn btn-link-menu" style="text-decoration: none">' . $peopleWithBirthDay->getFullNameWithAge() . '</a>';

                $unclassified .= '</label>';
                $unclassified .= '</div>';

                $cout_unclassified_people += 1;
                $cout_unclassified_people %= 4;
                if ($cout_unclassified_people == 0) {
                    $unclassified .= '</div>';
                    $new_unclassified_row = false;
                }
            }

            if ($new_unclassified_row == true) {
                $unclassified .= '</div>';
            }
            continue;
        }

        // we now work with the classified date
        if ($new_row == false) {
            $classified .= '<div class="row">';
            $new_row = true;
        }

        $classified .= '<div class="col-sm-3">';
        $classified .= '<label class="checkbox-inline">';

        if ($peopleWithBirthDay->getUrlIcon() != '') {
            $classified .= '<img src="' . SystemURLs::getRootPath() . '/skin/icons/markers/' . $peopleWithBirthDay->getUrlIcon() . '">';
        }
        $classified .= '<a href="' . $peopleWithBirthDay->getViewURI() . '" class="btn btn-link-menu" style="text-decoration: none">' . $peopleWithBirthDay->getFullNameWithAge() . '</a>';
        $classified .= '</label>';
        $classified .= '</div>';

        $count_people += 1;
        $count_people %= 4;
        if ($count_people == 0) {
            $classified .= '</div>';
            $new_row = false;
        }
    }

    if ($new_row == true) {
        $classified .= '</div>';
    }

    if (!empty($classified)) {
        ?>
        <h4 class="alert-heading"><?= _("Birthdates of the day") ?></h4>
        <div class="row">
            <?php
            echo $classified;
            ?>
        </div>
        <?php
    } ?>

    <?php if ($AnniversariesCount > 0) {
        if ($peopleWithBirthDaysCount > 0) {
            ?>
            <hr style="background-color: green; height: 1px; border: 0;">
            <?php
        } ?>

        <h4 class="alert-heading"><?= _("Anniversaries of the day") ?></h4>
        <div class="row">

        <?php
        $new_row = false;
        $count_people = 0;

        foreach ($Anniversaries as $Anniversary) {
            if ($new_row == false) {
                ?>
                <div class="row">

                <?php $new_row = true;
            } ?>
            <div class="col-sm-3">
                <label class="checkbox-inline">
                    <a href="<?= $Anniversary->getViewURI() ?>" class="btn btn-link-menu"
                       style="text-decoration: none"><?= $Anniversary->getFamilyString() ?></a>
                </label>
            </div>

            <?php
            $count_people += 1;
            $count_people %= 4;
            if ($count_people == 0) {
                ?>
                </div>
                <?php
                $new_row = false;
            }
        }

        if ($new_row == true) {
            ?>
            </div>
            <?php
        } ?>

        </div>
        <?php
    } ?>

    <?php if ($unclassified) {
        if ($peopleWithBirthDaysCount > 0) {
            ?>
            <hr style="background-color: green; height: 1px; border: 0;">
            <?php
        } ?>

        <h4 class="alert-heading"><?= _("Unclassified birthdates") ?></h4>
        <div class="row">

            <?php
            echo $unclassified;
            ?>

        </div>
        <?php
    } ?>
    </div>

    <?php
}

// The person can see the pastoral care
if (SessionUser::getUser()->isPastoralCareEnabled()) {
    $caresPersons = PastoralCareQuery::Create()
        ->filterByPersonId(null, Criteria::NOT_EQUAL)
        ->leftJoinPastoralCareType()
        ->joinPersonRelatedByPersonId()
        ->groupBy(PastoralCareTableMap::COL_PST_CR_PERSON_ID)
        ->orderByDate(Criteria::DESC)
        ->limit(SystemConfig::getValue("iSearchIncludePastoralCareMax"))
        ->findByPastorId(SessionUser::getUser()->getPerson()->getId());

    $caresFamilies = PastoralCareQuery::Create()
        ->filterByFamilyId(null, Criteria::NOT_EQUAL)
        ->leftJoinPastoralCareType()
        ->joinWithFamily()
        ->groupBy(PastoralCareTableMap::COL_PST_CR_PERSON_ID)
        ->orderByDate(Criteria::DESC)
        ->limit(SystemConfig::getValue("iSearchIncludePastoralCareMax"))
        ->findByPastorId(SessionUser::getUser()->getPerson()->getId());

    if ($caresPersons->count() > 0 || $caresFamilies->count() > 0) {
        ?>
        <div class="alert alert-pastoral-care alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <?php
        if ($caresPersons->count() > 0) {
            ?>
            <h4 class="alert-heading"><?= _("Individual Pastoral Care") ?></h4>
            <?php

            $count_care = 0;
            $new_row = false;

            foreach ($caresPersons as $care) {
                if (is_null($care->getPersonRelatedByPersonId())) {
                    continue;
                }
                if ($new_row == false) {
                    ?>
                    <div class="row">

                    <?php $new_row = true;
                } ?>

                <div class="col-sm-3">
                    <label class="checkbox-inline">
                        <a href="<?= SystemURLs::getRootPath() . "/v2/pastoralcare/person/" . $care->getPersonId() ?>"
                           class="btn btn-link-menu"
                           style="text-decoration: none;"><?= $care->getPersonRelatedByPersonId()->getFullName() ?>
                            (<?= $care->getDate()->format(SystemConfig::getValue('sDateFormatLong')) ?>)</a>
                    </label>
                </div>

                <?php
                $count_care += 1;
                $count_care %= 4;
                if ($count_care == 0) {
                    ?>
                    </div>
                    <?php
                    $new_row = false;
                }
                ?>
                <?php
            }

            if ($new_row == true) {
                ?>
                </div>
                <?php
            }
            ?>

            <?php
        }


        if ($caresFamilies->count() > 0) {
            if ($caresPersons->count() > 0) {
                ?>
                <hr style="background-color: #7c633e; height: 1px; border: 0;">
                <?php
            }
            ?>
            <h4 class="alert-heading"><?= _("Family Pastoral Care") ?></h4>
            <?php
            $count_care = 0;
            $new_row = false;

            foreach ($caresFamilies as $care) {
                if (is_null($care->getFamily())) {
                    continue;
                }
                if ($new_row == false) {
                    ?>
                    <div class="row">

                    <?php $new_row = true;
                } ?>

                <div class="col-sm-3">
                    <label class="checkbox-inline">
                        <a href="<?= SystemURLs::getRootPath() . "/v2/pastoralcare/family/" . $care->getFamilyId() ?>"
                           class="btn btn-link-menu" style="text-decoration: none;"><?= $care->getFamily()->getName() ?>
                            (<?= $care->getDate()->format(SystemConfig::getValue('sDateFormatLong')) ?>)</a>
                    </label>
                </div>

                <?php
                $count_care += 1;
                $count_care %= 4;
                if ($count_care == 0) {
                    ?>
                    </div>
                    <?php
                    $new_row = false;
                }
                ?>
                <?php
            }

            if ($new_row == true) {
                ?>
                </div>
                <?php
            }
            ?>

            <?php
        }

        ?>
        </div>

        <?php
    }
}
?>


<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3 id="familyCountDashboard">
                    0
                </h3>
                <p>
                    <?= _('Families') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-users"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/familylist" class="small-box-footer">
                <?= _('See all Families') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-purple">
            <div class="inner">
                <h3 id="peopleStatsDashboard">
                    0
                </h3>
                <p>
                    <?= _('People') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-user"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/SelectList.php?mode=person" class="small-box-footer">
                <?= _('See All People') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <?php
    if (SystemConfig::getBooleanValue("bEnabledSundaySchool")) {
        ?>
        <div class="col-lg-3 col-xs-6">
            <!-- small box -->
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3 id="groupStatsSundaySchool">
                        0
                    </h3>
                    <p>
                        <?= _('Sunday School Classes') ?>
                    </p>
                </div>
                <div class="icon">
                    <i class="fa fa-child"></i>
                </div>
                <a href="<?= SystemURLs::getRootPath() ?>/v2/sundayschool/dashboard" class="small-box-footer">
                    <?= _('More info') ?> <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div><!-- ./col -->
        <?php
    }
    ?>
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-maroon">
            <div class="inner">
                <h3 id="groupsCountDashboard">
                    0
                </h3>
                <p>
                    <?= _('Groups') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-gg"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/group/list" class="small-box-footer">
                <?= _('More info') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <?php
    $countAttend = EcclesiaCRM\Base\EventAttendQuery::create()
        ->filterByCheckoutId(null, \Propel\Runtime\ActiveQuery\Criteria::EQUAL)
        ->find()
        ->count();

    if ($countAttend > 0) {
        ?>
        <div class="col-lg-3 col-xs-6">
            <!-- small box -->
            <div class="small-box bg-light-blue">
                <div class="inner">
                    <h3>
                        <?= $countAttend ?>
                    </h3>
                    <p>
                        <?= _('Attendees Checked In') ?>
                    </p>
                </div>
                <div class="icon">
                    <i class="fa fa-gg"></i>
                </div>
                <a href="<?= SystemURLs::getRootPath() ?>/ListEvents.php" class="small-box-footer">
                    <?= _('More info') ?> <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div><!-- ./col -->
        <?php
    }
    ?>
</div><!-- /.row -->

<?php
if ($depositData && SystemConfig::getBooleanValue('bEnabledFinance')) { // If the user has Finance permissions, then let's display the deposit line chart
    ?>
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="box box-info">
                <div class="box-header">
                    <i class="fa fa-money fa-5x" style="font-size:26px"></i>
                    <h3 class="box-title"><?= _('Deposit Tracking') ?></h3>
                    <div class="box-tools pull-right">
                        <div id="deposit-graph" class="chart-legend"></div>
                    </div>
                </div><!-- /.box-header -->
                <div class="box-body">
                    <canvas id="deposit-lineGraph" style="height:225px; width:100%"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php
}  //END IF block for Finance permissions to include HTML for Deposit Chart
?>

<div class="row">
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box-header with-border">
                <i class="fa fa-group"></i><i class="fa fa-plus"></i>
                <h3 class="box-title"><?= _('Latest Families') ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                    </button>
                </div>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <table class="dataTable table table-striped table-condensed" id="latestFamiliesDashboardItem">
                    <thead>
                    <tr>
                        <th data-field="name"><?= _('Family Name') ?></th>
                        <th data-field="address"><?= _('Address') ?></th>
                        <th data-field="city"><?= _('Created') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box-header with-border">
                <i class="fa fa-check"></i>
                <h3 class="box-title"><?= _('Updated Families') ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                    </button>
                </div>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <table class=" dataTable table table-striped table-condensed" id="updatedFamiliesDashboardItem">
                    <thead>
                    <tr>
                        <th data-field="name"><?= _('Family Name') ?></th>
                        <th data-field="address"><?= _('Address') ?></th>
                        <th data-field="city"><?= _('Updated') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <i class="fa fa-user-plus"></i></i><h3 class="box-title"><?= _('Latest Members') ?></h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                                class="fa fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <!-- /.box-header -->
                <div class="box-body no-padding">
                    <table class=" dataTable table table-striped table-condensed" id="latestPersonsDashboardItem">
                        <thead>
                        <tr>
                            <th data-field="lastname"><?= _('Name') ?></th>
                            <th data-field="address"><?= _('Address') ?></th>
                            <th data-field="city"><?= _('Updated') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <!-- /.users-list -->
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <i class="fa fa-check"></i>
                    <h3 class="box-title"><?= _('Updated Members') ?></h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                                class="fa fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <!-- /.box-header -->
                <div class="box-body no-padding">
                    <table class=" dataTable table table-striped table-condensed" id="updatedPersonsDashboardItem">
                        <thead>
                        <tr>
                            <th data-field="lastname"><?= _('Name') ?></th>
                            <th data-field="address"><?= _('Address') ?></th>
                            <th data-field="city"><?= _('Updated') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <!-- /.users-list -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- this page specific inline scripts -->
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.bEnabledFinance = <?= (SystemConfig::getBooleanValue('bEnabledFinance'))?'true':'false' ?>;
    window.CRM.depositData = <?= ($depositData)?$depositData:"false" ?>;
    window.CRM.timeOut = <?= SystemConfig::getValue("iEventsOnDashboardPresenceTimeOut") * 1000 ?>;
</script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/menu.js"></script>

<?php
require 'Include/Footer.php';
?>
