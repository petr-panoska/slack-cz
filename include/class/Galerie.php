<?php
    class Galerie{
        private $lightbox;
        private $pictures;
        function Galerie(){
        }

        public function AddPicture($thumb, $full, $text, $del){
            $picture["thumb"] = $thumb;
            $picture["full"] = $full;
            $picture["text"] = $text;
            $picture["del"] = $del;
            $this->pictures[] = $picture;     
        }

        public function Show(){
            echo "<div>\n";
            foreach($this->pictures as $value){
                $thumb = $value["thumb"];
                $full = $value["full"];
                $text = $value["text"];
                echo "  <div class=\"thumbnail\">\n";
                echo "    <a href=\"$full\" rel=\"lightbox[roadtrip]\" title=\"$text\"><img src=\"$thumb\" alt=\"$text\"></a><br />\n";
                echo "    <p class=\"thumbnail\">$text</p>";
                echo $value["del"];
                echo "  </div>\n";
            }
            echo "<div class=\"clear\"></div>\n";
            echo "</div>\n";
            unset($thumb);
            unset($full);
            unset($text);
        }
    }
?>
