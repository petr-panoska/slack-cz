<style>
    input {margin-left: 15px;}
    select {margin-left: 15px;}
    #effect h3 { margin: 0; padding: 0.4em; text-align: center; }
    #effect { display: none; margin: 10px 0 0 0; }
    </style>
    <script>
    $(function() {
        // run the currently selected effect
        function runEffect() {
            // get effect type from 
            var selectedEffect = "blind";
            
            // most effect types need no options passed by default
            var options = {};
            // some effects have required parameters
            if ( selectedEffect === "scale" ) {
                options = { percent: 0 };
            } else if ( selectedEffect === "size" ) {
                options = { to: { width: 200, height: 60 } };
            }
            
            // run the effect
            $( "#effect" ).toggle( selectedEffect, options, 500 );
        };
        
        // set effect from select menu value
        $( "#button" ).click(function() {
            runEffect();
            return false;
        });
    });
    </script>
    
    <script type="text/javascript">
    $(document).ready(function() {
        filtrujHighline();
    });


    function filtrujHighline(){
        var load = "js_php/highlineVypis.php?";
        var text = "";
        if($("#filtrText").val()!=""){ text = $("#filtrText").val(); }
        var check = "";
        
        if($("#tHighline").is(":checked")){ check = "1"; }
        if($("#tTop").is(":checked")){ check = check + "2"; }
        if($("#tMidline").is(":checked")){ check = check + "3"; }
        if($("#tUrban").is(":checked")){ check = check + "4"; }
        
        if(text!=""){
            load = load + "s=" + text + "&";
        }
        
        if(check!=""){
            load = load + "t=" + check + "&";
        }
        
        load = load + "o=" + $("#sort").val();
        
        $("#highlineVypis").load(load);
    }
</script>

 <h2 style="margin: 0 0 10px 0;">Highline</h2>
<a href="#" id="button" style="padding: 2px 5px 2px 5px;" class="ui-state-default ui-corner-all">Filtry</a>
    <div id="effect" class="ui-widget-content ui-corner-all">
        <h3 class="ui-widget-header ui-corner-all">Filtry</h3>
        <form>
        <table width="700px">
        <tr><td colspan="4">
        
            Jméno:<input type="text" id="filtrText" onkeyup="filtrujHighline();" /><br />
        </td></tr>
        <tr>
            <td>
            Highline:<input type="checkbox" id="tHighline" onchange="filtrujHighline();"/><br />
            </td>
            <td>
            Midline:<input type="checkbox" id="tMidline" onchange="filtrujHighline();"/><br />
            </td>
            <td>
            TOP Highline:<input type="checkbox" id="tTop" onchange="filtrujHighline();" checked="checked"/><br />
            </td>
            <td>
            UrbanLine:<input type="checkbox" id="tUrban" onchange="filtrujHighline();"/><br />
            </td>
            </tr>
            <tr>
                <td colspan="4">
            Řazení:<select id="sort" onchange="filtrujHighline();">
              <option value="1">Jméno (vzest)</option>
              <option value="2">Jméno (sest)</option>
              <option value="3">Délka (vzest)</option>
              <option value="4">Délka (sest)</option>
              <option value="5">Výška (vzest)</option>
              <option value="6">Výška (sest)</option>
              <option value="7">Expozice (vzest)</option>
              <option value="8">Expozice (sest)</option>
            </select><br />
                </td>
            </tr>    
        </table>
        </form>
    </div>











<div>
 

<div id="highlineVypis" style="margin: 10px 0 0 0;">
</div>
</div>

