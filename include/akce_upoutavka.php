<?php
    $akce1 = vratPromennou("akce_upoutavka1");
    $akce2 = vratPromennou("akce_upoutavka2");
    if($akce1["hodnota2"]=="zverejneno" || $akce2["hodnota2"]=="zverejneno"){
        echo "<p class='nadpis'>NEJBLIŽŠÍ AKCE:</p>";
    }
    if($akce1["hodnota2"]=="zverejneno"){
        echo '<a href="img/akce_upoutavka/akce1-full.jpg" rel="lightbox" title="Nejbližší plánovaná akce"><img src="img/akce_upoutavka/akce1.jpg" alt="akce"></a>'; 
        echo '<div class="popisekUpoutavek">'."\n";
        echo $akce1["hodnota"]."\n";
        echo '</div>'."\n";
    }

    if($akce2["hodnota2"]=="zverejneno"){
        echo '<a href="img/akce_upoutavka/akce2-full.jpg" rel="lightbox" title="2. nejbliže plánovaná akce"><img src="img/akce_upoutavka/akce2.jpg" alt="akce"></a>'; 
        echo '<div class="popisekUpoutavek">'."\n";
        echo $akce2["hodnota"]."\n";
        echo '</div>'."\n";
    }
?>
