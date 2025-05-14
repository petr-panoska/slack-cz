<h2>Zapoměli jste heslo?</h2>
<p>Pokud jste zapoměli svoje heslo nic se neděje. Stačí vyplnit Vaší přezdívku a 
    Vaši e-mailovou adresu, kterou jste zadali při registraci. Potom se Vám automaticky vygeneruje nové heslo.
    To si ale můžete kdykoliv poté změnit.</p>

<form  action="index.php?str=odesliNaMail" method="post">
        <br/>
        <label for="nick">Váš Nick: </label>
        <input id="nick" type="text" name="nick" value=""/>
        <br/>
        <label for="email">Váš email: </label>
        <input id="email" type="text" name="email" value=""/>
        <br/>
        <input class="odsazeniForm" type="submit" value="Odeslat heslo na můj mail" />
</form>