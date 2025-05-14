<?php

/**
 * @author Roman Fischer
 * @copyright 2011
 */


?>

<html>
    <?php
    if(isset($_SESSION["chyby"]))
    {
        $text = $_SESSION["chyby"];
        echo "<div class=\"stranka_chyby\">$text</div>";
        unset($_SESSION["chyby"]);
    }
    ?>
    <form method="POST" action="form_zpr.php" enctype="multipart/form-data" class="stranka_form">
        <input type="hidden" name="akce" value="skupina"/>  
        
        <label>název:</label>
        <input type="text" name="nazev" class="stranka_textbox"/>
        <br />
        
        <label>odkaz:</label>
        <input type="text" name="odkaz" class="stranka_textbox"/>
        <br />
        
        <label>obrázek:</label><br />
        <input type="file" name="obrazek" class="stranka_soubor" />
        <br />
        
        <label>popis:</label><br />
        <textarea class="stranka_area" name="popis"></textarea>
        
        <input type="submit" value="odeslat" class="stranka_tlacitko"/>
    </form>

</html>
