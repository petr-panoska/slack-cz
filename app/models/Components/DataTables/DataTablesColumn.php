<?php

namespace Slack\Models\Components\DataTables;

/**
 * Description of DataTablesSource
 *
 * @author kubapet
 */
class DataTablesColumn
{

    private $name;
    private $caption;
    private $filter;
    private $searchable;
    private $visible;
    private $sortable;
    private $width;
    private $render;
    private $dateFormat;

    function __construct($name = null, $caption = null)
    {
        $this->name = $name;
        $this->caption = $caption;
        $this->searchable = false;
        $this->visible = true;
        $this->sortable = true;
        $this->dateFormat = null;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function setCaption($caption)
    {
        $this->caption = $caption;
    }

    public function getFilter()
    {
        return $this->filter;
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function setSearchable($searchable)
    {
        $this->searchable = $searchable;
    }

    public function getVisible()
    {
        return $this->visible;
    }

    public function setVisible($visible)
    {
        $this->visible = $visible;
    }

    public function getSortable()
    {
        return $this->sortable;
    }

    public function setSortable($sortable)
    {
        $this->sortable = $sortable;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth($width)
    {
        $this->width = $width;
    }

    public function getRender()
    {
        return $this->render;
    }

    public function setRender($render)
    {
        $this->render = $render;
    }

    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;
    }

}
