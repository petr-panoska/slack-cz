<?php
    
    class ClanekAdd{
        private $id = null;    
        private $db;
        private $album;
        private $nazev, $popis;
        private $text;
        private $datum;
        private $uzivatel;
        private $viditelnost;
        private $cast; // cast podle ktere se kontrolujou hodnoty

        function ClanekAdd($db){
            $this->db = $db;
            $this->generujAlbum();    
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

        public function ulozClanek(){
            $sql = "INSERT INTO clanky (nazev, popis, text, album, datum, uzivatel) VALUES ";
            $sql.= "('".$this->nazev."', '".$this->popis."', '".$this->text."', '".$this->album."', '".$this->datum."', '".$this->uzivatel."')";
            $req = $this->db->query($sql);
            if($req){ return true; }else{ return false; } 
        }

        public function vypisChyby(){
            if(!isset($_SESSION["username"])){
                echo '<p class="chyba">Pro přidávání článků musíte být přihlášen.</p>';
            }    
        }

        public function kontrolaCasti(){
            return true;
        }

        public function nastavCast($cast){
            $this->cast = $cast;
        }

        private function generujAlbum(){
            $this->album = date("Y-m-d")."_".rand(1000, 9999);
            if(!is_dir("clanky/".$this->album)){
                mkdir("clanky/".$this->album, 0777);
            }
        }

        public function nastavAlbum($album){
            $this->album = $album;
        }
        
        public function nastavNazev($nazev){
            $this->nazev = addslashes(htmlspecialchars($nazev));       
        }

        public function nastavPopis($popis){
            $this->popis = addslashes(htmlspecialchars($popis));    
        }

        public function nastavText($text){
            $this->text = $text;
        }

        public function nastavDatum($datum){
            $this->datum = $datum;
        }

        public function nastavUzivatele($uzivatel){
            $this->uzivatel = $uzivatel;
        }

        public function nastavViditelnost($viditelnost){
            $this->viditelnost = $viditelnost;
        }
        
        public function nastavId($id){
            $this->id = $id;
        }

        public function getID(){
            return $this->id;
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



    }
?>
