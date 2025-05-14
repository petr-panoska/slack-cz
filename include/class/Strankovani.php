<?php
    class Strankovani{
        private $db;
        private $table;           // tabulka na strankovani
        private $pocetNaStranu;   // pocet zaznamu na stranku
        private $pocetZaznamu;    // celkovy pocet zaznamu v tabulce
        private $pocetStran;
        private $strana;
        private $odkaz;
        function Strankovani($db, $table, $pocet){
            $this->db = $db;
            $this->table = $table;
            $this->pocetNaStranu = $pocet;
            $this->pocetZaznamu();
            $this->pocetStran = ceil($this->pocet / $this->pocetNaStranu);    
        }

        public function vypisInfo(){
             var_dump(get_object_vars($this));
        }
        
        private function pocetZaznamu(){
            $sql = "SELECT COUNT(*) as pocet FROM ".$this->table;
            $this->pocetZaznamu = $this->db->query($sql)->fetch_assoc();
            $this->pocetZaznamu = $this->pocetZaznamu["pocet"];
        }

        function nastavStranu($strana){
            if(is_int($strana)){
                $this->strana = $strana;    
            }else{
                echo '<p class="error">Strana musí být celé číslo!</p>';
            }
        }

        function vratLimity(){
            $od = ($this->strana - 1) * $this->pocetNaStranu;
            return "LIMIT ".$od.", ".$this->pocetNaStranu;        
        }

        function nastavOdkaz($odkaz){
            $this->odkaz = $odkaz;    
        }
        

        function vypisNavigaci(){
            echo '<div class="odstrankovani">';
            echo '  <ul>';
            //Predchozi
            if(($this->strana - 1) > 0){ 
                echo '<li class="dalsi-stranka"><a href="'.$this->odkaz.'-'.($this->strana - 1).'">« Předchozí</a></li>'; 
            }else{
                echo '<li class="zruseny-odkaz">« Předchozí</li>';
            }

            //Strany
            if($this->pocetStran >= 11){
                if($this->strana > 6){
                    $od = $this->strana - 6;
                }else{
                    $od = 0;
                }

                if(($this->pocetStran - $this->strana) >= 6){
                    $do = $this->strana + 6;
                }else{
                    $do = $this->pocetStran;
                }

                while($od <= $do){
                    if($this->strana == $od){
                        echo '<li class="aktualni-stranka">'.($od).'</li>';    
                    }else{
                        echo '<li><a href="'.$this->odkaz.'-'.$od.'">'.$od.'</a></li>';
                    }
                    $od++; 
                }
            }else{
                $od = 0;
                $do = $this->pocetStran;
                while($od <= $do){
                    if($this->strana == $od){
                        echo '<li class="aktualni-stranka">'.($od).'</li>';    
                    }else{
                        echo '<li><a href="'.$this->odkaz.'-'.$od.'">'.$od.'</a></li>';
                    }
                    $od++; 
                }
            }
            
            //Nasledujici
            if($this->strana < $this->pocetStran){
                echo '<li class="dalsi-stranka"><a href="'.$this->odkaz.'-'.($this->strana + 1).'">Další »</a></li>'; 
            }else{
                echo "<li class=\"zruseny-odkaz\">Další »</li>"; 
            }
            echo '  </ul>';
            echo '</div>';    
        }

    }

    require("../databaze.php");
    $strankovani = new Strankovani($link, "forum", 20);
    $strankovani->nastavOdkaz("forum");
    $strankovani->nastavStranu(5);
    //$strankovani->vypisNavigaci();
    nl2br(print_r($strankovani));

?>
