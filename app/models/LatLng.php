<?php

namespace Slack\Models;

/**
 * Description of LatLng
 *
 * @author Vejvis
 */
class LatLng
{

    private $lat;
    private $lng;

    function __construct($lat = null, $lng = null)
    {
        $this->lat = $lat;
        $this->lng = $lng;
    }

    function getLat()
    {
        return $this->lat;
    }

    function getLng()
    {
        return $this->lng;
    }

    function setLat($lat)
    {
        $this->lat = $lat;
    }

    function setLng($lng)
    {
        $this->lng = $lng;
    }

    public function isEmpty()
    {
        return $this->lat == null && $this->lng == null;
    }

}
