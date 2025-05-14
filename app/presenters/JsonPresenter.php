<?php

use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Environment;
use Nette\Image;
use VisualPaginator\VisualPaginator;
use HighlinesBook\Rating;

class JsonPresenter extends BasePresenter {

    /**
     * @var HighlinesBook\Highline
     */
    private $highline;

    protected function startup() {
        parent::startup();
        $this->highline = $this->context->highline;
        }

        /*
         *        Render a Action metody
         */

        public function renderHighlines($iDisplayStart, $iDisplayLength, $iSortingCols, $iSortCol_0, $sSearch) {

        $aColumns = array('jmeno', 'delka', 'vyska', 'oblast');
        $sIndexColumn = "id";

        $vyber = $this->highline->findAll();

        /*
         * Paging
         */

        if (isset($iDisplayStart) && $iDisplayLength != '-1') {
            $vyber->limit($iDisplayLength, $iDisplayStart);
        }

        /*
         * Ordering
         */

        if (isset($iSortCol_0)) {
            for ($i = 0; $i < intval($iSortingCols); $i++) {
                if ($this->getHttpRequest()->getQuery('bSortable_' . intval($this->getHttpRequest()->getQuery('iSortCol_' . $i))) == "true") {
                    if ($this->getHttpRequest()->getQuery('sSortDir_' . $i) == 'desc') {
                        $vyber->order($aColumns[intval($this->getHttpRequest()->getQuery('iSortCol_' . $i))] . " DESC");
                    } else {
                        $vyber->order($aColumns[intval($this->getHttpRequest()->getQuery('iSortCol_' . $i))]);
                    }
                }
            }
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
        if (isset($sSearch) && $sSearch != "") {
            $vyber->where("jmeno LIKE ?", "%".$sSearch."%");
        }

        /* Individual column filtering */
        /*
          for ($i = 0; $i < count($aColumns); $i++) {
          if (isset($_GET['bSearchable_' . $i]) && $_GET['bSearchable_' . $i] == "true" && $_GET['sSearch_' . $i] != '') {
          if ($sWhere == "") {
          $sWhere = "WHERE ";
          } else {
          $sWhere .= " AND ";
          }
          $sWhere .= "`" . $aColumns[$i] . "` LIKE '%" . mysql_real_escape_string($_GET['sSearch_' . $i]) . "%' ";
          }
          }
         */
        /*
         * SQL queries
         * Get data to display
         */
        /*        $sQuery = "
          SELECT SQL_CALC_FOUND_ROWS `" . str_replace(" , ", " ", implode("`, `", $aColumns)) . "`
          FROM   $sTable
          $sWhere
          $sOrder
          $sLimit
          ";
          $rResult = mysql_query($sQuery, $gaSql['link']) or die(mysql_error());
         */
        /* Data set length after filtering */
        /*       $sQuery = "
          SELECT FOUND_ROWS()
          ";
          $rResultFilterTotal = mysql_query($sQuery, $gaSql['link']) or die(mysql_error());
          $aResultFilterTotal = mysql_fetch_array($rResultFilterTotal);
          $iFilteredTotal = $aResultFilterTotal[0];

          /* Total data set length */
        /* $sQuery = "
          SELECT COUNT(`" . $sIndexColumn . "`)
          FROM   $sTable
          ";
          $rResultTotal = mysql_query($sQuery, $gaSql['link']) or die(mysql_error());
          $aResultTotal = mysql_fetch_array($rResultTotal);
          $iTotal = $aResultTotal[0];
         */


        $iFilteredTotal = $this->highline->findAll()->count();
        $iTotal = $vyber->count();
        $aaData = array();

        foreach ($vyber as $v) {
            $detail = "<a href='http://book.slack.cz/highlines/detail/$v->id'><img src='http://book.slack.cz/images/ico/lupa.png'/></a>";
            $aaData[] = array($v->jmeno, $v->delka, $v->vyska, $v->oblast, $detail);
        }




        $output = array(
            "sEcho" => intval($_GET['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => $aaData
        );

        $this->sendResponse(new Nette\Application\Responses\JsonResponse($output));
    }

}
