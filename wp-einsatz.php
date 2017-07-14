<?php
/*
Plugin Name: wp-einsatz
Plugin Script: wp-einsatz.php
Plugin URI: http://www.feuerwehr-guenzburg.de/links/eigene-plugins/
Description: Einsatzliste und Widget fuer Feuerwehren
Version: 0.6.3
Author: Stefan Hauf
Author URI: http://www.feuerwehr-guenzburg.de
*/

$loc_de = setlocale(LC_ALL, 'de_DE@euro', 'de_DE', 'de', 'ge');
setlocale(LC_TIME, $loc_de);
// Erstellt die Tabelle beim ersten Start
function install () {
  global $wpdb;
   
  $table_name = $wpdb->prefix . "einsaetze";
  if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
    $sql = "CREATE TABLE " . $table_name . " (
	    ID mediumint(9) NOT NULL AUTO_INCREMENT,
	    Datum datetime NOT NULL default '0000-00-00 00:00:00',
	    Ort varchar(100) NOT NULL default '',
	    Art varchar(100) NOT NULL default '',
	    UNIQUE KEY ID (ID)
	    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    $insert = "INSERT INTO $table_name (Datum, Ort, Art) VALUES (NOW(), 'Musterstadt', 'Fehlalarm')";
    $results = $wpdb->query( $insert );
  }
  add_option( 'wpeinsatz_widgetlink', '/',       '', 'no' );
  add_option( 'wpeinsatz_link',       'Bericht', '', 'no' );
  add_option( 'wpeinsatz_charset',    'none',    '', 'no' );
}
register_activation_hook(__FILE__,'install');

//  Fügt das CSS-File in den Header ein
function addHeaderCode() {
  echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/wp-einsatz/wp-einsatz.css" />' . "\n";		
}
add_action('wp_head', 'addHeaderCode', 1);	      

function f_charset($html) {
    $wpeinsatz_charset = get_option('wpeinsatz_charset');
    if ($wpeinsatz_charset != "none") {
      $f_charset = $wpeinsatz_charset;
      $html = $f_charset($html);
    }
    return $html;
}

function einsatz_liste($edit, $start) {
  global $wpdb;  
  $table_name = $wpdb->prefix . "einsaetze";
  $limit = "";
  $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
  if ($edit == 1) {  
    $limit = "$start, 10";
    echo "<table border='0'><tr><td>Eins&auml;tze beginnend ab:</td><td>\n";

    for ($i = 0; $i < $count/10; $i++) {
      $next  = $i*10;
      if ($start == $next)
        echo "<td><b><input style=\"font-weight: bold;\" type=\"submit\" name=\"start\" value=\"-".$next."-\" /></b></td>";
      else
        echo "<td><form method=\"post\"><input type=\"submit\" name=\"start\" value=\"".$next."\" /></form></td>";
    }
    echo "</tr></table>\n";
    
  }
  else {  
    $jahr  = $wpdb->escape(get_post_meta(get_the_ID(),'jahr',  TRUE));
    $monat = $wpdb->escape(get_post_meta(get_the_ID(),'monat', TRUE));
    $limit = $wpdb->escape(get_post_meta(get_the_ID(),'letzte',TRUE));
  }
  
  $html  = "<table class='einsatzliste' border='1'>\n";
  $html .= "  <tr>\n";
  $ueberschriften = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
  foreach ($ueberschriften as $ueberschrift) {
    $field = $ueberschrift->Field;
    $field = str_replace("_", " ", $field);
    if ($field == "ID") continue; // ignore ID
    $html .= "    <th>".$field."</th>\n";         
    if ($field == "Datum")
      $html .= "    <th>Uhrzeit</th>\n";
    else          
      $fields[] = $field;
  }
  if ($edit == 1) {
    $html .= "    <th>&Auml;ndern</th>\n";
    $html .= "    <th>L&ouml;schen</th>\n";
  }

  $html .= "  </tr>\n";
  
  $mainsql = "SELECT *, UNIX_TIMESTAMP(Datum) AS Datum_F, DATE_FORMAT(Datum, '%H:%i') AS Uhrzeit FROM $table_name ";
  if ( $limit != "") {  	
    $sql = "$mainsql ORDER BY Datum DESC LIMIT $limit";
  }
  else if (strlen($jahr) == 4) {
    if ($monat > 0 && $monat < 13) {
      $monat_sql = "AND MONTH(Datum) = '$monat'";
    }
    $sql = "$mainsql WHERE YEAR(Datum) = '$jahr' $monat_sql ORDER BY Datum ASC$limit";
  }
  else {                            
    $sql = "$mainsql ORDER BY Datum DESC LIMIT 10";
  }
  $einsaetze = $wpdb->get_results("$sql", ARRAY_A);    
    
  foreach ($einsaetze as $einsatz) {    
    $i++;
    $html .= ($i%2) ? "  <tr>\n" : "  <tr class='alt'>\n";
    $html .= "    <td>".strftime('%d. %b', $einsatz['Datum_F'])."</td>\n";
    $html .= "    <td>".$einsatz['Uhrzeit']."</td>\n";
    foreach ($fields as $field) {
      $field = str_replace(" ", "_", $field);
      if ($field == "Link") {
        if ($einsatz[$field] == "") {
          $html .= "    <td> </td>\n";
        }
        else {  
        	$linktext = str_replace("\\\"","\"", get_option( 'wpeinsatz_link'));
          $html .= "    <td><a class=\"einsatzbericht\" href=\"".$einsatz[$field]."\">".$linktext."</a></td>\n";
        }
      }
      else {
        $html .= "    <td>".$einsatz[$field]."</td>\n";
      }
    }          
    if ($edit == 1) {
      $html .= "    <td><form method=\"post\" id=\"edit\"  ><fieldset class=\"options\"><input type=\"hidden\" name=\"ID\" value=\"".$einsatz['ID']."\"><input type=\"submit\" name=\"edit\"   value=\"&Auml;ndern\"  /></fieldset></form></td>";
      $html .= "    <td><form method=\"post\" id=\"delete\"><fieldset class=\"options\"><input type=\"hidden\" name=\"ID\" value=\"".$einsatz['ID']."\"><input type=\"submit\" name=\"delete\" value=\"L&ouml;schen\" /></fieldset></form></td>";        
    }
    $html .= "  </tr>\n";
  }	 
  $html .= "</table>\n";
  return f_charset($html);
}

function einsatz_filter($content) {
  return ereg_replace( '<!--einsatzliste-->', einsatz_liste(0, 0), $content );
  return ereg_replace( 'wpeinsatzlistewp', einsatz_liste(0, 0), $content );
}
add_filter('the_content', 'einsatz_filter');


function einsatz_adminliste() {
  global $wpdb;   
  $table_name = $wpdb->prefix . "einsaetze";
  if($_POST['delete']) {
    $wpdb->query("DELETE FROM $table_name WHERE ID = ".$_POST['ID']." LIMIT 1");
    echo "<div class='updated'><p>Einsatz wurde erfolgreich gel&ouml;scht.</p></div>";
  }
  if($_POST['update']) {
    $sql = "UPDATE $table_name SET "; 
    foreach ($_POST as $key => $value) {    
      if ($key == "ID" || $key == "update") continue;
      $sql .= "`$key` = \"$value\", ";
    }
    $sql   = substr($sql,0,-2);    
    $sql .= " WHERE ID = ".$_POST['ID']." LIMIT 1";  
    $wpdb->query($sql);
    
    echo "<div class='updated'><p>Einsatz wurde erfolgreich aktualisiert.</p></div>";
  }

  if($_POST['edit']) {
    $sql = "SELECT * FROM $table_name WHERE ID = ".$_POST['ID']." LIMIT 1";       
    $einsatz = $wpdb->get_row($sql, ARRAY_A);
    
    $html  = "<table>\n  <form METHOD='POST'>\n";
    foreach ($einsatz as $key => $value) {
      $type = "text";      
      if ($key == "ID")  {
        $type = "hidden";
        $html .= "<input name='$key' value='$value' type='$type'>";
      }
      else {
        $html .= "<tr><td>$key</td><td><input name='$key' value='$value' type='$type'></td></tr>\n";
      }    
    }
    $html .= "    <tr><td>&nbsp;</td><td><input type='submit' name='update'></td></tr>\n  </form>\n</table>\n";
    echo f_charset($html);
  }
  else {
    if (!isset($_POST["start"])) $start = 0;
    else                         $start = $_POST["start"];
    
    $html = einsatz_liste(1, $start);   
    echo $html;
  }
}

function einsatz_new() {
  global $wpdb;  
  $table_name = $wpdb->prefix . "einsaetze";
  
  if($_POST['new']) {
  
    foreach($_POST as $key=>$value) {
  	  $key = str_replace(" ", "_", $key);
      if ($value!='' && $key!="new") {
        $keys .= "`$key`, ";
        $values .= "'$value', ";
      }
    }
    $keys   = substr($keys,0,-2);
    $values = substr($values,0,-2);
    $sql = "INSERT INTO $table_name ($keys) VALUES ($values)";
    $results = $wpdb->query($sql);
    echo "<div class='updated'><p>Einsatz wurde erfolgreich eingetragen.</p></div>";
  }
  $date = $wpdb->get_var("SELECT NOW()");
     
  $html  = "<table>\n  <form METHOD='POST'>\n";
  $ueberschriften = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
  foreach ($ueberschriften as $ueberschrift) {
    $field = $ueberschrift->Field;
  	$field = str_replace("_", " ", $field);
    $default = $ueberschrift->Default;
    if ($field == "ID") continue; // ignore ID             
    if ($field == "Datum")
      
      $html .= "    <tr><td>$field</td><td><input name='$field' type='text' value='".$date."'></td></tr>\n";
    else    
      $html .= "    <tr><td>$field</td><td><input name='$field' type='text' value='$default'></td></tr>\n";
  }
  $html .= "    <tr><td>&nbsp;</td><td><input type='submit' name='new'></td></tr>\n  </form>\n</table>";
  echo f_charset($html);
}

function einsatz_settings() {
		
  global $wpdb;  
  $table_name = $wpdb->prefix . "einsaetze";
  if($_POST['field_delete']) {
  	$old = str_replace(" ", "_", $_POST['old']);
    $wpdb->query("ALTER TABLE $table_name DROP `$old`");    
    echo "<div class='updated'><p>Feld wurde erfolgreich gel&ouml;scht.</p></div>";
  }
  if($_POST['field_edit']) {
  	$new = str_replace(" ", "_", $_POST['new']);
  	$old = str_replace(" ", "_", $_POST['old']);
    $wpdb->query("ALTER TABLE $table_name CHANGE `$old` `$new` VARCHAR(100)");
    echo "<div class='updated'><p>Feld wurde erfolgreich bearbeitet.</p></div>";
  }
  if($_POST['field_new']) {
  	$field = str_replace(" ", "_", $_POST['field']);
  	$query = "ALTER TABLE $table_name ADD `$field` VARCHAR(100) NOT NULL";
    $wpdb->query($query);    
    echo "<div class='updated'><p>Feld wurde erfolgreich angelegt.</p></div>";
  }
  if($_POST['setting_link']) {
  	if (isset($_POST['widgetlink']))
  	  update_option( 'wpeinsatz_widgetlink', $_POST['widgetlink']);
  	if ($_POST['link'])
  	  update_option( 'wpeinsatz_link', $_POST['link']);
  	if ($_POST['charset'])
  	  update_option( 'wpeinsatz_charset', $_POST['charset']);
  	  
    echo "<div class='updated'><p>Einstellung wurde erfolgreich aktualisiert.</p></div>";
  }
  
  $html  = "<h3>Vorhandene Felder bearbeiten:</h3>\n";
  $ueberschriften = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
  foreach ($ueberschriften as $ueberschrift) {
    $field = $ueberschrift->Field;    
  	$field = str_replace("_", " ", $field);
    $default = $ueberschrift->Default;
    if ($field == "ID") continue; // ignore ID             
    if ($field == "Datum") continue; // ignore Datum
    $html .= "<form METHOD='POST'>\n<input name='old' type='hidden' value='$field'><input name='new' type='text' value='$field'><input type='submit' name='field_edit' value='Bearbeiten'><input type='submit' name='field_delete' value='L&ouml;schen'></form>\n";
  }
  $html .= "</table>\n";
  $html .= "<h3>Neues Feld anlegen:</h3>";
  $html .= "<form METHOD='POST'>\n<input name='field' type='text' value=''><input type='submit' name='field_new' value='Anlegen'>\n</form>\n";
  $html .= "<h3>Einstellungen:</h3>\n";
  $html .= "Widget-Linkadresse:<br>\n";	
  $html .= "<form METHOD='POST'>\n<code>".get_bloginfo( 'wpurl')."</code><input name='widgetlink' type='text' value='".get_option( 'wpeinsatz_widgetlink')."'><input type='submit' name='setting_link' value='&Auml;ndern'> Leer f&uuml;r keinen Link\n</form>\n";  	
  $html .= "Text f&uuml;r 'Links':<br>\n";	
  $html .= "<form METHOD='POST'>\n<input name='link' type='text' value='".get_option( 'wpeinsatz_link')."'><input type='submit' name='setting_link' value='&Auml;ndern'> <code>&lt;img src=\\\"url_zum_bild\\\"&gt;</code> f&uuml;r ein Bild statt Text\n</form>\n";  	
  $html .= "Zeichencodierung:<br>\n";	
  $html .= "<form METHOD='POST'>\n<select name='charset'>\n";
  $char = array('keine' => 'none', 'UTF8 Enc' => 'utf8_encode', 'UTF8 Dec' => 'utf8_decode');
  foreach($char as $besch => $wert) {
    if (get_option( 'wpeinsatz_charset') == $wert) $selected = "selected";
    else                                           $selected = "";
    $html .= "<option value='$wert' $selected>$besch</option>\n";
  }
  $html .= "</select>\n<input type='submit' name='setting_link' value='&Auml;ndern'></form>\n";
  echo $html;
}

//  Erstellen der Menus und deren Anzeige im wp-Adminbereich
function einsatz_menu() {
  add_menu_page('Eins&auml;tze', 'Eins&auml;tze', 8, __FILE__, 'einsatz_adminliste');
  add_submenu_page(__FILE__, 'Neuer Einsatz', 'Neuer Einsatz', 8, 'einsatz_new', 'einsatz_new');
  add_submenu_page(__FILE__, 'Einstellungen', 'Einstellungen', 8, 'einsatz_settings', 'einsatz_settings');
}
add_action('admin_menu', 'einsatz_menu');


function einsatz_widget_init() 
{
  function einsatz_widget_list() 
  {
    global $wpdb;
    $table_name = $wpdb->prefix . "einsaetze";

    $einsatz = $wpdb->get_row("SELECT *, UNIX_TIMESTAMP(Datum) AS Datum_F, DATE_FORMAT(Datum, '%H:%i') AS Uhrzeit FROM $table_name ORDER BY Datum DESC LIMIT 1");

    echo "<li id=\"archives\" class=\"widget widget_archive\"><h2 class=\"widgettitle\">Letzter Einsatz</h2>";
    $text = strftime('%d. %b', $einsatz->Datum_F)." ".$einsatz->Uhrzeit."<br>".$einsatz->Ort."<br>".$einsatz->Art;
    if ( get_option( 'wpeinsatz_widgetlink') != "") {
      $text = "<a href='".get_settings('home').get_option( 'wpeinsatz_widgetlink')."'>".$text."</a>";
    }
    $html = "<ul><li>".$text."</li></ul></li>\n";
    echo f_charset($html);
        
  }

  register_sidebar_widget(
    array(__('Lezter Einsatz','einsatz'),'widgets'),
    'einsatz_widget_list'
  );
  register_widget_control(
    array(__('Letzter Einsatz','einsatz'),'widgets'),
    'einsatz_widget_list_control'
  );
}

add_action('widgets_init', 'einsatz_widget_init');
?>
