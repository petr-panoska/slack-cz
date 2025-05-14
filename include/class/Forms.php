<?php
  class Forms{
      private $name, $action, $method;
      private $elements;
      private $submit_value;
      private $show_reset;
      function Forms($name, $action, $method){
          $this->name = $name;
          $this->action = $action;
          $this->method = $method;
          $this->show_reset = true;
          $this->submit_value = "Odeslat";
      }
      
      function ShowResetButton($show){
          $this->show_reset = $show;
      }
      
      function AddInput($text, $name, $type, $maxlength = false, $value = false, $readonly = false){
        if(!isset($maxlength)){$maxlength = "";}
        if(!isset($value)){$value = "";}
        if($readonly){
            $this->elements[] = "<label>$text</label><input type=\"$type\" name=\"$name\" maxlength=\"$maxlength\" value=\"$value\" readonly=\"readonly\"><br />\n";
        }else{
            $this->elements[] = "<label>$text</label><input type=\"$type\" name=\"$name\" maxlength=\"$maxlength\" value=\"$value\"><br />\n";
        }  
      }
      
      function AddUpload($text, $name, $max_file_size = false){
          if($max_file_size!=null){
              $this->elements[] = "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"$max_file_size\">\n";
          }
          $this->elements[] = "<label>$text</label><input type=\"file\" name=\"$name\"><br />\n";
      }
      
      function AddTextarea($text, $name, $width, $height, $value = false){
          $this->elements[] = "<label>$text</label><textarea name=\"$name\" style=\"width: $width;height: $height;\">$value</textarea><br />";
      }
      
      function AddTinymce($name, $width, $height, $value = false){
      $this->elements[] = "<textarea name=\"$name\" style=\"width: $width;height: $height;\">$value</textarea><br />";    
      }
      
      function AddHidden($name, $value){
          $this->elements[] = "<input type=\"hidden\" name=\"$name\" value=\"$value\">\n";
      }
      
      function SetSubmitValue($text){
          $this->submit_value = $text;
      }
      
      function ShowForm(){
          echo "<form action=\"".$this->action."\" name=\"".$this->name."\" method=\"".$this->method."\" enctype=\"multipart/form-data\">\n";
          foreach($this->elements as $value){
              echo $value;
          }
          echo "<label>&nbsp;</label><input type=\"submit\" value=\"".$this->submit_value."\">";
          if($this->show_reset){
            echo "<input type=\"reset\" value=\"Reset\"><br />\n";
          }
          echo "</form>\n";
      }
  }
?>
