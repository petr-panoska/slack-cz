<script type="text/javascript" src="include/kalendar/js/datepicker.js"></script>
<fieldset>
    <legend>Přidání longline do deníčku</legend>
    <form method="post" action="form_zpr.php" name="longline_add">
        <input type="hidden" name="akce" value="longline_add">
        <label>Délka:</label><input type="text" name="delka" onkeyup="javascript: kontrola_delka();">m<br />
        <label>Místo:</label><input type="text" name="misto" id="longlineAddMistoNasept"><br />
        <label>Styl:</label>
        <select name="styl">
            <option value="-">-
            <option value="OS fm">OS FM
            <option value="OS">OS
            <option value="OS, fm">OS, fm
            <option value="fm">fm
            <option value="one way">one way
        </select><br />
        <label>Datum:</label><input type="text" name="datum" id="datepicker" value="<?php echo date("Y-m-d"); ?>">

        <br />
        <label>&nbsp;</label><input type="submit" value="Přidej">
    </form>
</fieldset>