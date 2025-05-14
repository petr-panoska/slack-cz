function getColumnIndex(table, column)
{
    var columns = window[table + "TableColumns"];
    for (var i = 0; i < columns.length; i++)
    {
        if (columns[i] == column)
        {
            return i;
        }
    }
    console.log('column ' + column + ' not found in table ' + table);
    return null;
}

/*
 * Required columns: h.id, h.jmeno
 */
function renderHighlineName(oObj, table)
{
    var highlineId = oObj.row[getColumnIndex(table, "h.id")];
    var highlineJmeno = oObj.row[getColumnIndex(table, "h.jmeno")];
    return '<a href="/highlines/detail/' + highlineId + '?tab=info">' + highlineJmeno + '</a>';
}

/*
 * Required columns: h.id, h.jmeno
 */
function renderAttemptHighlineName(oObj, table)
{
    var highlineId = oObj.row[getColumnIndex(table, "h.id")];
    var highlineJmeno = oObj.row[getColumnIndex(table, "h.jmeno")];
    return '<a href="/highlines/detail/' + highlineId + '?tab=info">' + highlineJmeno + '</a>';
}

/*
 * Required columns: h.typ
 */
function renderHighlineTyp(oObj, table)
{
    var highlineTyp = oObj.row[getColumnIndex(table, "h.typ")];
    switch (highlineTyp)
    {
        case "1":
            return "Highline";
        case "2":
            return "TOP Highline";
        case "3":
            return "Midline";
        case "4":
            return "UrbanLine";
        default:
            return "Neznámý";
    }
}

/*
 * Required columns: h.typ
 */
function renderStarRating(oObj, table)
{
    var val = '<span title="Hodnocení expozice ' + oObj["data"] + ' z 5 hvězd.">';
    for (i = 1; i <= oObj["data"]; i++) {
        val+='<span style="color:#f0ad4e;" class="glyphicon glyphicon-star" aria-hidden="true"></span>';
    }
    val+="</span>";
    return val;
}
/*
 * Required columns: h.delka
 */
function renderHighlineLong(oObj, table)
{
    var delka = oObj.row[getColumnIndex(table, "h.delka")];
    return '<strong>' + delka + ' m</strong>';
}
/*
 * Required columns: h.id, h.jmeno
 */
function renderHighlineHigh(oObj, table)
{
    var vyska = oObj.row[getColumnIndex(table, "h.vyska")];
    return '<strong>' + vyska + ' m</strong>';
}
/*
 * Required columns: u.id, u.nick
 */
function renderUsersNick(oObj, table)
{
    var userId = oObj.row[getColumnIndex(table, "u.id")];
    var userNick = oObj.row[getColumnIndex(table, "u.nick")];
    return '<a href="/users/detail?user=' + userId + '">' + userNick + '</a>';
}

/*
 ********************************************************
 *			Row Renders			*
 ********************************************************
 */

/*
 * Required columns: c.phone, c.lastStatus
 */
function calculationsRowCallback(nRow, row, iDisplayIndex, iDisplayIndexFull, table)
{
    var phone = row[getColumnIndex(table, "c.phone")];
    var lastStatus = row[getColumnIndex(table, "c.lastStatus")];

    if (phone == '')
    {
        $(nRow).addClass('opacity50');
    }
    switch (parseInt(lastStatus))
    {
        case 1:
        case 3:
            $(nRow).addClass('orange');
            break;
        case 2:
        case 6:
        case 5:
            $(nRow).addClass('red');
            break;
        case 4:
        case 8:
            $(nRow).addClass('green');
            break;
    }
}