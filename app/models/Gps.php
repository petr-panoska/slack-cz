<?php

namespace Slack\Models;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Nette\Object;

/**
 * @Entity
 * @Table(name="gps")
 */
class Gps extends Object
{

    const PARKING = "PARKING";
    const LINE_POINT = "LINE_POINT";

    /**
     * @Id
     * @GeneratedValue
     * @Column
     *      (type="integer")
     */
    private $id;

    /**
     * @Column(type="decimal", precision=10, scale=6)
     */
    private $lat;

    /**
     * @Column(type="decimal", precision=10, scale=6)
     */
    private $lng;

    /**
     * @Column(type="string")
     */
    private $type;

    function __construct($lat = null, $lng = null, $type = null)
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->type = $type;
    }

    function getId()
    {
        return $this->id;
    }

    function getLat()
    {
        return $this->lat;
    }

    function getLng()
    {
        return $this->lng;
    }

    function getType()
    {
        return $this->type;
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function setLat($lat)
    {
        $this->lat = $lat;
    }

    function setLng($lng)
    {
        $this->lng = $lng;
    }

    function setType($type)
    {
        $this->type = $type;
    }


    /**
     *
     * @return LatLng
     */
    function getPosition()
    {
        return new LatLng($this->lat, $this->lng);
    }

}
