<script src="js/jquery.validate.js" type="text/javascript">
<script src="js/bbq.js" type="text/javascript">
<script src="js/jquery-ui-1.8.5.custom.min.js" type="text/javascript">
<script src="js/jquery.form.wizard.js" type="text/javascript">
<script type="text/javascript">
$(function(){
$("#demoForm").formwizard({
formPluginEnabled: true,
validationEnabled: true,
focusFirstInput : true,
formOptions :{
success: function(data){$("#status").fadeTo(500,1,function(){ $(this).html("You are now registered!").fadeTo(5000, 0); })},
beforeSubmit: function(data){$("#data").html("data sent to the server: " + $.param(data));},
dataType: 'json',
resetForm: true
}
}
);
});
</script>

<div id="demoWrapper">    
  <h5 id="status"></h5>
  <form id="demoForm" class="bbq ui-formwizard ui-helper-reset ui-widget ui-widget-content ui-helper-reset ui-corner-all" action="json.html" method="post">
    <div id="fieldWrapper">
      <fieldset id="first" class="step ui-formwizard-content ui-helper-reset ui-corner-all" style="display: none;"><h1>First step - Name</h1>
        <br>
        <label for="firstname">First name
        </label>
        <br>
        <input id="firstname" class="input_field_12em ui-wizard-content ui-helper-reset ui-state-default" name="firstname" disabled="">
        <br>
        <label for="surname">Surname
        </label>
        <br>
        <input id="surname" class="input_field_12em ui-wizard-content ui-helper-reset ui-state-default" name="surname" disabled="">
        <br>
      </fieldset>
      <fieldset id="finland" class="step ui-formwizard-content ui-helper-reset ui-corner-all" style="display: block;"><h1>Step 2 - Personal information</h1>
        <br>
        <label for="day_fi">Social Security Number
        </label>
        <br>
        <input id="day_fi" class="input_field_25em ui-wizard-content ui-helper-reset ui-state-default valid" value="DD" name="day">
        <input id="month_fi" class="input_field_25em ui-wizard-content ui-helper-reset ui-state-default" value="MM" name="month">
        <input id="year_fi" class="input_field_3em ui-wizard-content ui-helper-reset ui-state-default" value="YYYY" name="year">- 
        <input id="lastFour_fi" class="input_field_3em ui-wizard-content ui-helper-reset ui-state-default" value="XXXX" name="lastFour">
        <br>
        <label for="countryPrefix_fi">Phone number
        </label>
        <br>
        <input id="countryPrefix_fi" class="input_field_35em ui-wizard-content ui-helper-reset ui-state-default" value="+358" name="countryPrefix">- 
        <input id="areaCode_fi" class="input_field_3em ui-wizard-content ui-helper-reset ui-state-default" name="areaCode">- 
        <input id="phoneNumber_fi" class="input_field_12em ui-wizard-content ui-helper-reset ui-state-default" name="phoneNumber">
        <br>
        <label for="myemail">*Email
        </label>
        <br>
        <input id="myemail" class="input_field_12em email required ui-wizard-content ui-helper-reset ui-state-default valid" name="myemail">
        <br>
      </fieldset>
      <fieldset id="confirmation" class="step ui-formwizard-content ui-helper-reset ui-corner-all" style="display: none;"><h1>Last step - Username</h1>
        <br>
        <label for="username">User name
        </label>
        <br>
        <input id="username" class="input_field_12em ui-wizard-content ui-helper-reset ui-state-default" name="username">
        <br>
        <label for="password">Password
        </label>
        <br>
        <input id="password" class="input_field_12em ui-wizard-content ui-helper-reset ui-state-default" type="password" name="password">
        <br>
        <label for="retypePassword">Retype password
        </label>
        <br>
        <input id="retypePassword" class="input_field_12em ui-wizard-content ui-helper-reset ui-state-default" type="password" name="retypePassword">
        <br>
      </fieldset>
    </div>
    <div id="demoNavigation">
      <input id="back" class="navigation_button ui-wizard-content ui-formwizard-button ui-helper-reset ui-state-default ui-state-active" type="reset" value="Back">
      <input id="next" class="navigation_button ui-wizard-content ui-formwizard-button ui-helper-reset ui-state-default ui-state-active" type="submit" value="Next">
    </div>
  </form>
  <hr>
  <p id="data">
  </p>
</div>
<?php
/*
session_start();
function rozepsanej_clanek(){
  return false;
}
if(isset($_SESSION["username"]) && !rozepsanej_clanek()){
  
}
include("include/class/Forms.php");
if(isset($_SESSION["clanek_add"])){
    $clanek = unserialize($_SESSION["clanek_add"]);
    $clanek->vypisChyby();
}else{
    echo '<p class="chyba">Špatné vytvoření článku!</p>';
}
$form = new Forms("clanek_add_1", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_1");
$form->AddInput("Název článku:", "nazev", "text", 50, $clanek->getNazev());
$form->AddTextarea("Popis článku:", "popis", 200, 100, $clanek->getPopis());
$form->ShowResetButton(false);
$form->SetSubmitValue("Další");
$form->ShowForm();
unset($form);
$form = new Forms("clanek_add_konec", "form_zpr.php", "POST");
$form->AddHidden("akce", "clanek_add_konec");
$form->SetSubmitValue("Ukončit přidávání článku");
$form->ShowResetButton(false);
$form->ShowForm();
unset($form);
*/
?>