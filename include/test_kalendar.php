<script>
$(document).ready(function() {
   
  $('#fullcalendar').fullCalendar({
    theme: true,
    weekends: true, // will hide Saturdays and Sundays
    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'month, agendaDay'
    }, 
    events: "js_php/kalendarData.php",
    timeFormat: 'H(:mm)',
    agenda: 'H:mm{ - H:mm}',
    '': 'H(:mm)',
    loading: function(bool) { 
            if (bool) $('#loading').show(); 
            else $('#loading').hide(); 
         }
  });
});

$(function(){
      $("#eventAdd").dialog({
        autoOpen:false,
        title: "Vytvoření nové události",
        show: "blind",
        hide: "blind",
        width: 400,
        modal: true,
        buttons: {
          OK: function(){
        
            $( this ).dialog("close");
        
          },
          Storno: function(){
            $( this ).dialog("close");
          }
        }
      });
  });

$(function(){  
  $("#kalendarEventAdd").click(function(){
    $("#eventAdd").dialog("open");
  }); 
}); 
  
</script>
<div id="fullcalendar"></div>
<button id="kalendarEventAdd">Přidat událost</button>

<div id="eventAdd">
  Název:<input type="text" name="nazev"><br>
  Začátek:<input type="text" name="start" id="datepicker"><br>
  Konec:<input type="text" name="end" ><br>
  
  
</div>