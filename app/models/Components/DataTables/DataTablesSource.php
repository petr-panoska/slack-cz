<?php

namespace Slack\Models\Components\DataTables;

use Nette\Object;
use Slack\EnvironmentManager;
use Slack\Models\Components\Collection\Collection;

/**
 * Description of DataTablesSource
 *
 * @author Vejvis
 */
class DataTablesSource extends Object
{

    private $name;

    /** @var Collection Description */
    private $columns;
    private $baseQuery;
    private $idColumn;
    private $detailColumn;
    private $detailOpenIcon;
    private $detailCloseIcon;
    private $detailUrl;
    private $extendedCondition;
    private $defaultSortColumn;
    private $defaultSort;
    private $dataUrl;
    private $rowCallback;

    /** @var DataTablesResult Description */
    private $result;

    function __construct()
    {
        $this->columns = new Collection();
    }

    public function hasAnyColumnFilter()
    {
        foreach ($this->columns as $column)
        {
            if ($column->getFilter() != null)
            {
                return true;
            }
        }
        return false;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    public function getBaseQuery()
    {
        return $this->baseQuery;
    }

    public function setBaseQuery($baseQuery)
    {
        $this->baseQuery = $baseQuery;
    }

    public function getDetailColumn()
    {
        return $this->detailColumn;
    }

    public function setDetailColumn($detailColumn)
    {
        $this->detailColumn = $detailColumn;
    }

    public function getIdColumn()
    {
        return $this->idColumn;
    }

    public function setIdColumn($idColumn)
    {
        $this->idColumn = $idColumn;
    }

    public function getDefaultSortColumn()
    {
        return $this->defaultSortColumn;
    }

    public function setDefaultSortColumn($defaultSortColumn)
    {
        $this->defaultSortColumn = $defaultSortColumn;
    }

    public function getDefaultSort()
    {
        return $this->defaultSort;
    }

    public function setDefaultSort($defaultSort)
    {
        $this->defaultSort = $defaultSort;
    }

    public function getDetailOpenIcon()
    {
        return $this->detailOpenIcon;
    }

    public function setDetailOpenIcon($detailOpenIcon)
    {
        $this->detailOpenIcon = $detailOpenIcon;
    }

    public function getDetailCloseIcon()
    {
        return $this->detailCloseIcon;
    }

    public function setDetailCloseIcon($detailCloseIcon)
    {
        $this->detailCloseIcon = $detailCloseIcon;
    }

    public function getDetailUrl()
    {
        return $this->detailUrl;
    }

    public function setDetailUrl($detailUrl)
    {
        $this->detailUrl = $detailUrl;
    }

    public function getRowCallback()
    {
        return $this->rowCallback;
    }

    public function setRowCallback($rowCallback)
    {
        $this->rowCallback = $rowCallback;
    }

    public function getExtendedCondition()
    {
        return $this->extendedCondition;
    }

    public function setExtendedCondition($extendedCondition)
    {
        $this->extendedCondition = $extendedCondition;
    }

    public function getDataUrl()
    {
        return $this->dataUrl;
    }

    public function setDataUrl($dataUrl)
    {
        $this->dataUrl = $dataUrl;
    }

    public function getColumn($order)
    {
        $count = 0;
        foreach ($this->columns as $key => $value)
        {
            if ($count == $order)
            {
                return $this->columns->get($key);
            }
            $count++;
        }
        return null;
    }

    public function getDefaultSortColumnIndex()
    {
        $i = 0;
        foreach ($this->columns as $col)
        {
            if ($col->getName() == $this->defaultSortColumn)
            {
                return $i;
            }
            $i++;
        }
        return 0;
    }

    public function getColumnOrder($name)
    {
        $i = 0;
        foreach ($this->columns as $column)
        {
            if ($column->getName() == $name)
                return $i;
            else
                $i++;
        }
        return 0;
    }

    public function getResult()
    {
        if ($this->result != null)
        {
            return $this->result;
        }


        $this->result = new DataTablesResult();
        $em = EnvironmentManager::getEntityManager();

        $aColumns = $this->columns->getSubCollection('name');

        /*
         * Paging
         */
        $iDisplayStart = EnvironmentManager::getRequestVar('start');
        $iDisplayLength = EnvironmentManager::getRequestVar('length');
        $draw = EnvironmentManager::getRequestVar('draw');

        /*
         * Ordering
         */
        $ordering = EnvironmentManager::getRequestVar('order');
        $sOrder = " ORDER BY  ";
        foreach ($ordering as $orderItem)
        {
            $columnOrder = $orderItem["column"];
            $columnName = $this->getColumn($columnOrder)->getName();
            $columnDir = $orderItem["dir"];

            $sOrder.= $columnName . " ";
            switch ($columnDir)
            {
                case 'asc':
                case 'ASC':
                    $sOrder.="ASC, ";
                    break;
                case 'desc':
                case 'DESC':
                    $sOrder.="DESC, ";
                    break;
            }
        }


        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == " ORDER BY")
        {
            $sOrder = "";
        }



        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";

        if ($this->extendedCondition != null)
        {
            $sWhere.= " WHERE (" . $this->extendedCondition . ")";
        }

        if (EnvironmentManager::getRequestVar('search')->get("value") != "")
        {
            $search = "";
            $term = EnvironmentManager::getRequestVar('search')->get("value");
            foreach ($this->columns as $col)
            {

                if ($col->getSearchable())
                {
                    if ($search != "")
                    {
                        $search.= " OR ";
                    }
                    $search.= $col->getName() . " LIKE '%" . $term . "%'";
                }
            }
            if ($search != "")
            {
                if ($sWhere == "")
                {
                    $sWhere.= " WHERE (" . $search . ")";
                }
                else
                {
                    $sWhere.= " AND (" . $search . ")";
                }
            }
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($this->columns); $i++)
        {
            if (EnvironmentManager::getRequestVar('bSearchable_' . $i) == "true" && EnvironmentManager::getRequestVar('sSearch_' . $i) != '')
            {
                if ($sWhere == "")
                {
                    $sWhere = " WHERE ";
                }
                else
                {
                    $sWhere .= " AND ";
                }
                $sWhere .= $aColumns->get($i) . " = '" . EnvironmentManager::getRequestVar('sSearch_' . $i) . "'";
            }
        }
        /*
         * SQL queries
         * Get data to display
         */

        $sql = $this->baseQuery . $sWhere . $sOrder;

        $query = $em->createQuery($sql);

        $totalRecords = 150;
        $totalFilteredRecords = count($query->getResult());

        $query->setFirstResult($iDisplayStart)
                ->setMaxResults($iDisplayLength);

        $this->result->setData($query->getResult());


        $em->clear();
        $this->result->setITotalDisplayRecords($totalFilteredRecords);
        $this->result->setITotalRecords($totalRecords);
        $this->result->setDraw($draw);
        return $this->result;
    }

    public function setResult(DataTablesResult $result)
    {
        $this->result = $result;
    }

    public function addColumn(DataTablesColumn $column)
    {
        $this->columns->put($column->getName(), $column);
    }

}
