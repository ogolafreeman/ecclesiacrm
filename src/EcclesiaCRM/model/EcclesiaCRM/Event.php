<?php

namespace EcclesiaCRM;

use EcclesiaCRM\Base\Event as BaseEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\MyPDO\VObjectExtract;


/**
 * Skeleton subclass for representing a row from the 'events_event' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Event extends BaseEvent
{

    private $_alarm             = -1; // to avoid to use to many times VObjectExtract ...
    private $_freqlastOccurence = -1; // for each parts of getCalendardata
    private $_freq              = -1; // for frequence type DAILY ...

    public function checkInPerson($PersonId)
    {
        $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->findOneOrCreate();

        $AttendanceRecord->setEvent($this)
            ->setPersonId($PersonId)
            ->setCheckinDate(date('Y-m-d H:i:s'))
            ->setCheckoutDate(null)
            ->save();

        return array("status" => "success");

    }

    public function unCheckInPerson($PersonId)
    {
        $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->findOneOrCreate();

        $AttendanceRecord->setEvent($this)
            ->setPersonId($PersonId)
            ->setCheckinDate(NULL)
            ->setCheckoutDate(null)
            ->save();

        return array("status" => "success");

    }

    public function unCheckOutPerson($PersonId)
    {
        $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->filterByCheckinDate(NULL, Criteria::NOT_EQUAL)
            ->findOne();

        $AttendanceRecord->setEvent($this)
            ->setPersonId($PersonId)
            ->setCheckoutDate(NULL)
            ->save();

        return array("status" => "success");

    }

    public function checkOutPerson($PersonId)
    {
        $AttendanceRecord = EventAttendQuery::create()
            ->filterByEvent($this)
            ->filterByPersonId($PersonId)
            ->filterByCheckinDate(NULL, Criteria::NOT_EQUAL)
            ->findOne();

        $AttendanceRecord->setEvent($this)
            ->setPersonId($PersonId)
            ->setCheckoutDate(date('Y-m-d H:i:s'))
            ->save();

        return array("status" => "success");

    }

    public function getLatitude()
    {
        $LatLong = explode(' commaGMAP ', $this->getCoordinates());

        return $LatLong[0];
    }

    public function getLongitude()
    {
        $LatLong = explode(' commaGMAP ', $this->getCoordinates());

        return $LatLong[1];
    }

    public function getAlarm()
    {
        // we get the PDO for the Sabre connection from the Propel connection
        if ($this->_alarm == -1) {
            $data = VObjectExtract::calendarData($this->getCalendardata(), $this->getStart()->format('Y-m-d'), $this->getEnd()->format('Y-m-d'));
            $this->_alarm = $data['alarm'];
            $this->_freqlastOccurence = $data['freqlastOccurence'];
            $this->_freq = $data['freq'];
        }

        return $this->_alarm;
    }

    public function getFreqLastOccurence()
    {
        // we get the PDO for the Sabre connection from the Propel connection
        if ($this->_alarm == -1) {
            $data = VObjectExtract::calendarData($this->getCalendardata());
            $this->_alarm = $data['alarm'];
            $this->_freqlastOccurence = $data['freqlastOccurence'];
            $this->_freq = $data['freq'];
        }

        return $this->_freqlastOccurence;
    }

    public function getFreq()
    {
        // we get the PDO for the Sabre connection from the Propel connection
        if ($this->_alarm == -1) {
            $data = VObjectExtract::calendarData($this->getCalendardata());
            $this->_alarm = $data['alarm'];
            $this->_freqlastOccurence = $data['freqlastOccurence'];
            $this->_freq = $data['freq'];
        }

        return explode (";",$this->_freq)[0];
    }

    public function getEventURI()
    {
        if (SessionUser::getUser()->isAdmin())
            return SystemURLs::getRootPath() . "/EventEditor.php?calendarAction=" . $this->getID();
        else
            return '';
    }
}
