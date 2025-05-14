function kontrola_delka(){
    if (isInteger(document.longline_add.delka.value)){
        document.longline_add.delka.style.border="1px solid black";    
    }else{
        document.longline_add.delka.style.border="1px solid red";
    }
}

function zmena3Pismena(typ) {
    var jmeno = document.getElementById(typ);  
    if ((jmeno.value.indexOf(" ") > -1) || jmeno.value == "" || jmeno.value.length < 3){
      jmeno.style.border="1px solid red"; 
    }else{
      jmeno.style.border="1px solid black";   
    }
}

function zmenaTelefon() {

  var reg = /^[1-9]{1}[0-9]*$/;
  var reg2= /^[1-9]{1}[0-9]{2}[ ]{1}[0-9]{3}[ ]{1}[0-9]{3}$/;
  var reg3= /^[+]{1}[1-9]{1}[0-9]*$/;
  var reg4= /^[+]{1}[0-9]{3}[ ]{1}[1-9]{1}[0-9]{2}[ ]{1}[0-9]{3}[ ]{1}[0-9]{3}$/;
  var num = document.getElementById('telefon').value;
  // test vrati true alebo false
    if ((num.length == 0 || reg.test(num)== true && num.length == 9) || (reg2.test(num)== true) || (reg3.test(num)== true && num.length == 13) || (reg4.test(num)== true)){
     document.getElementById('telefon').style.border="1px solid black";       
    }else{
     document.getElementById('telefon').style.border="1px solid red";       
    }

}

function zmenaEmail() {
  var adresa = document.getElementById('email').value;
  var pozice_zavinace = adresa.indexOf("@");
  var kontrola = true;
    if (pozice_zavinace < 0)
        kontrola = false;
    var cast_pred_zavinacem = adresa.substring(0,pozice_zavinace);
    var cast_po_zavinaci = adresa.substring(pozice_zavinace+1,adresa.length);
    if (cast_po_zavinaci.indexOf("@") >= 0)
        kontrola = false;
    if (cast_pred_zavinacem.length <= 0)
        kontrola = false;
    if (cast_po_zavinaci.length <= 2)
        kontrola = false;
    if (adresa.indexOf(".") == (-1))
        kontrola = false;
    var pozice_tecky = adresa.indexOf(".");  
    var cast_po_tecce = adresa.substring(pozice_tecky+1,adresa.length); 
     if (cast_po_tecce.length <= 1)
        kontrola = false; 
    
    if (kontrola == true){
       document.getElementById('email').style.border="1px solid black";
    }else{
      document.getElementById('email').style.border="1px solid red";
    }
}

function zmenaHesla() {
     if (document.getElementById('heslo').value == "" || document.getElementById('heslo').value.length < 5){
       document.getElementById('heslo').style.border="1px solid red";
    }else{
      document.getElementById('heslo').style.border="1px solid black"; 
    }
}

function zmenaHesla2() {
     if (document.getElementById('heslo').value !=  document.getElementById('heslo2').value){
       document.getElementById('heslo2').style.border="1px solid red";
    }else{
      document.getElementById('heslo2').style.border="1px solid black"; 
    }
}

function isInteger(s) {
  return (s.toString().search(/^-?[0-9]+$/) == 0);
}

function zmenaRok() {
    promenna = new Date();
    aRok= promenna.getFullYear();
    
     var rok = document.getElementById('rok'); 
     if (!isInteger(rok.value) || rok.value == "" || rok.value > aRok || (rok.value < (aRok - 100)) ){
       rok.style.border="1px solid red";
    }else{
      rok.style.border="1px solid black"; 
    }
}

function vypis_nazvu(text)
{
    var out = document.getElementById('vystup');
    if(text != "")
    {
        out.style.width = "450px";
        out.style.marginLeft = "15px";
        out.style.color = "#fff974";
    }
    else
    {
        out.style.width = "50px";
        out.style.margin = "0 auto";
        out.style.color = "white";
        text = "novinky";
    }
    out.innerHTML = text;
}