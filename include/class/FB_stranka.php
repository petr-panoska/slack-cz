<?php

/**
 * @author Roman Fischer
 * @copyright 2011
 */

include('include/SimpleImage.php');

class FB_stranka
{
    private $nazev;
    private $odkaz;
    private $popis;
    private $id;
    private $db;
    private $obrazek;
    
    public function __construct($jmeno,$cesta,$komentar,$fotka)
    {
        include "include/databaze.php";
        $this->db = $link; 
        $this->nazev = $jmeno;
        $this->popis = $komentar;
        $this->odkaz = $cesta;
        $this->obrazek = $fotka; 
        $this->get_id();               
    }
        
    public function pridej()
    {
        $sql = "INSERT INTO fb_skupiny VALUES (";
        $sql .= "\"\",";
        $sql .= "\"$this->nazev\",";
        $sql .= "\"$this->odkaz\",";
        $sql .= "\"$this->popis\"";
        $sql .=")";
            
        if($req = $this->db->query($sql))
        {
            $this->ulozeni_obrazku();
        }
        else
        {
            $_SESSION["chyby"] =  "připojení k databázi se nezdařilo";
        }
    }
        
    private function ulozeni_obrazku()
    {
        if($this->id == "")
        {
            $this->get_id();          
        }
        if($this->id != "")
        {
            move_uploaded_file($this->obrazek,"img/facebook/$this->id.jpg");
            $fotka = new SimpleImage();
            $fotka->load("img/facebook/$this->id.jpg");
            $fotka->resizeToWidth(60);
            $fotka->save("img/facebook/$this->id.jpg");
            
        }
        else
        {
            $_SESSION["chyby"] = "Není vybrán žádný záznam";
        }
    }
    
    private function get_id()
    {
        $sql = "SELECT id FROM fb_skupiny WHERE nazev=\"$this->nazev\"";
        if($reg = $this->db->query($sql))
        {
            if($reg->num_rows != 0)
            {
                $radek = $reg->fetch_assoc();
                $this->id = $radek["id"];
            }
            else
            {
                $this->id = "";
            }
        }
        else
        {
            $_SESSION["chyby"] =  "chyba databáze";
        }
    } 
}

?>