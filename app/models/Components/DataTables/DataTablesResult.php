<?php

namespace Slack\Models\Components\DataTables;

use Nette\Object;

/**
 * Description of DataTablesSource
 *
 * @author Vejvis
 */
class DataTablesResult extends Object
{

    private $draw;
    private $iTotalRecords;
    private $iTotalDisplayRecords;
    private $data;

    function getDraw()
    {
        return $this->draw;
    }

    function setDraw($draw)
    {
        $this->draw = $draw;
    }

    public function getITotalRecords()
    {
        return $this->iTotalRecords;
    }

    public function setITotalRecords($iTotalRecords)
    {
        $this->iTotalRecords = $iTotalRecords;
    }

    public function getITotalDisplayRecords()
    {
        return $this->iTotalDisplayRecords;
    }

    public function setITotalDisplayRecords($iTotalDisplayRecords)
    {
        $this->iTotalDisplayRecords = $iTotalDisplayRecords;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

}
