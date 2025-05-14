<?
pripojdatabazi();

$sql="SELECT * FROM products WHERE del='' AND slack=1 ORDER BY RAND() LIMIT 1";
$vysledek=mysql_query($sql) or die (mysql_error());
if ($vysledek){
	echo "<p class='nadpis'>VYBÍRÁME Z NAŠEHO E-SHOPU</p>";
	while($radek=mysql_fetch_array($vysledek)){
		echo "<p><a href='/E-shop/'>".$radek["nazev"]."</a></p>";
		echo "<p><a href='/E-shop/'><img src='/E-shop/galery/".$radek["id"]."/".$radek["obrshow"]."' alt='".$radek["nazev"]."'></a></p>";
		echo "<p>Cena: ".number_format ($radek["cena"], 2, '.', ' ')." Kč</p>";
	} 
}
?>