<?php
    class ClanekEdit{
        private $id;    
        private $db;
        private $album;
        private $nazev, $popis;
        private $text;
        private $datum;
        private $uzivatel;
        private $viditelnost;
        private $cast; // cast podle ktere se kontrolujou hodnoty

        function ClanekEdit($db, $id){
            $this->db = $db;
            $this->id = $id;
            $this->cast = 1;
            $this->load();    
        }

        function __sleep() {
            $vlastnosti = get_object_vars($this);
            foreach ($vlastnosti as $klic => $hodnota) {
                $pole[] = $klic;
            }
            return $pole;
        } 

        function __wakeup() {
            include("include/databaze.php");
            $this->db = $link;    
        }
        
        private function load(){
            $sql = "SELECT * FROM clanky WHERE id=".$this->id." LIMIT 1";
            $req = $this->db->query($sql);
            $row = $req->fetch_assoc();
            
            $this->album = $row["album"];
            $this->datum = $row["datum"];
            $this->nazev = $row["nazev"];
            $this->popis = $row["popis"];
            $this->uzivatel = $row["uzivatel"];
            $this->text = $row["text"];
            $this->viditelnost = $row["viditelnost"];
            $this->uzivatel = $row["uzivatel"];
        }

        public function save(){
            $sql = "UPDATE clanky SET nazev='".$this->nazev."', popis='".$this->popis."', text='".$this->text."', datum='".$this->datum."' WHERE id=".$this->id;
            $req = $this->db->query($sql);
            if($req){ return true; }
            else{ return false; }    
        }

        public function vypisChyby(){
            if(!isset($_SESSION["username"])){
                echo '<p class="chyba">Pro přidávání článků musíte být přihlášen.</p>';
            }
            switch($this->cast){
                case 1:
                
                break;
                case 2:
                
                break;
                case 3:
                    if(!file_exists("clanky/".$this->album."/nahled.jpg")){
                        echo "<p class=\"error\">Článek nemá náhledovou fotku.</p>";
                    }
                break;
            }    
        }

        public function setCast($cast){
            $this->cast = $cast;
        }

        public function setAlbum($album){
            $this->album = $album;
        }
        
        public function setNazev($nazev){
            $this->nazev = addslashes(htmlspecialchars($nazev));       
        }

        public function setPopis($popis){
            $this->popis = addslashes(htmlspecialchars($popis));    
        }

        public function setText($text){
            $this->text = $text;
        }

        public function setDatum($datum){
            $this->datum = $datum;
        }

        public function setUzivatel($uzivatel){
            $this->uzivatel = $uzivatel;
        }

        public function setViditelnost($viditelnost){
            $this->viditelnost = $viditelnost;
        }

        public function getDatum(){
            return $this->datum;
        }
        public function getAlbum(){
            return $this->album;
        }
        public function getCast(){
            return $this->cast;
        }
        public function getNazev(){
            return stripslashes(htmlspecialchars_decode($this->nazev));
        }
        public function getPopis(){
            return $this->popis;
        }
        public function getTextClanku(){
            return stripslashes(htmlspecialchars_decode($this->text));
        }
        
        public function getId(){
            return $this->id;
        }
        
        public function getUzivatel(){
            return $this->uzivatel;
        }



    }
?>
