<?php
    class GibbonTrick{
        private $name; 
        private $text;
        private $date;
        private $address;
        private $object;
        private $db;
        private $id;

        function GibbonTrick($link, $name=false, $text=false, $date=false, $adress=false){

            $this->db = $link;
            $this->id = null;
            $this->name = $name;
            $this->text = $text;
            if($this->kontrolaData($date)){
                $this->date = $date;   
            }
            $this->address = $adress;
            $this->object = null;    
        }

        private function kontrolaData($date){
            return true;
        }
        
        function setObject($object){
            $this->object = addslashes(htmlspecialchars($object));
        }
        
        function setPopis($text){
            $this->text = addslashes(htmlspecialchars($text));
        }
        
        function setDate($date){
            $this->date = $date;
        }
        
        function setName($name){
            $this->name = addslashes(htmlspecialchars($name));
        }
        
        function setAddress($address){
            $this->address = addslashes(htmlspecialchars($address));
        }

        function getObject(){
            return stripslashes(htmlspecialchars_decode($this->object));
        }

        function getName(){
            return $this->name;
        }

        function getAddress(){
            return $this->address;
        }

        function getDatum(){
            return $this->date;
        }

        function getPopis(){
            return $this->text;
        }

        function load($id){
            $sql = "SELECT * FROM `gibbon_trick` WHERE id=$id";
            $link = $this->db;
            $req = $link->query($sql);
            if(req){
                $trik = $req->fetch_assoc();
                $this->id = $id;
                $this->date = $trik["date"];
                $this->address = $trik["address"];
                $this->name = $trik["name"];
                $this->text = $trik["text"];
                $this->object = $trik["object"];
                return true;
            }else{
                return false;
            }
        }

        function save(){
            $link = $this->db;
            if($this->id == null){
                $sql = "INSERT INTO `gibbon_trick` (name, text, date, address, object) VALUES (";
                $sql.= "'".$this->name."', ";
                $sql.= "'".$this->text."', ";
                $sql.= "'".$this->date."', ";
                $sql.= "'".$this->address."'";
                if($this->object == null){
                    $sql.= ", NULL)";
                }else{  $sql.= ", '".$this->object."')";    }
                if($link->query($sql)){
                    return true;
                }else{ return false; }
            }else{
                $sql = "UPDATE `gibbon_trick` SET ";
                $sql.= "name='".$this->name."', ";
                $sql.= "text='".$this->text."', ";
                $sql.= "date='".$this->date."', ";
                $sql.= "address='".$this->address."', ";
                $sql.= "object='".$this->object."' WHERE id=".$this->id;
                $req = $link->query($sql);
                if(req){
                    return true;
                }else{ return false; }
            }
        }   

    }
?>