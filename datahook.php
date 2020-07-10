<?php
ini_set('error_log', __DIR__ . '/datahook-php_errors.log');

  // initialisiere Loggingsystem
  include('./logsys.php');
  $log = new Logging();
  $log->lfile('./datahook.log');
  $log->lwrite('---- datahook.php wurde aufgerufen ----------');

  $statistikfile = "./datahook-statistik.json";
  if (file_exists($statistikfile)) {
    $strJsonFileContents = file_get_contents($statistikfile);
    $statistik = json_decode($strJsonFileContents, true);
    unlink($statistikfile);
  } else { $statistik = array(); }

  $statistik['aufrufe'] = $statistik['aufrufe'] + 1 ?? 1;

header('Content-Type: application/json');
$anfrage = json_decode(file_get_contents('php://input'), true);

include('./datahook-secrets.php');

if ($anfrage['bot_email'] != $bot_email) {
    $log->lwrite('Nicht autorisierter Zugriff auf Datahook.');
  $statistik['unauthorized'] = $statistik['unauthorized'] + 1 ?? 1;
	httpAntwort(403,'Unauthorized');
	die;
}
if ($anfrage['message']['type'] != 'private') {
  $statistik['mentions'] = $statistik['mentions'] + 1 ?? 1;
	httpAntwort(200, 'Hallo @**' . $anfrage['message']['sender_full_name'] . "**, Anfragen an mich bitte immer als private Nachricht an @**DATA**.");
    $log->lwrite('Erwähnung außerhalb einer privaten Nachricht durch ' . $anfrage['message']['sender_full_name']);
	die;
}

  $log->lwrite("Anfrage von " . $anfrage['message']['sender_full_name'] . ", komplette Nachricht: " . $anfrage['message']['content']);
  $statistik['nutzer'][$anfrage['message']['sender_full_name']] = $statistik['nutzer'][$anfrage['message']['sender_full_name']] + 1 ?? 1;

// entferne Zahlen, Interpunktion, mehrere Leerzeichen und Zeilenumbrüche ==> Leerzeichen:
$anfrage['message']['content'] = preg_replace('/[\d[:punct:]]+/u', ' ', $anfrage['message']['content']);
$anfrage['message']['content'] = trim(preg_replace(array('/\s+/','/\n\r+/'), ' ', $anfrage['message']['content']));

$anrede = explode(' ', $anfrage['message']['sender_full_name'], 2);
// $anrede[0] müsste der Vorname sein, $anrede[1] der Nachname.

if (preg_match('/(hilf|help)/i', $anfrage['message']['content']) === 1) { // Test auf "hilf" oder "help" im Text
	$antwort = "Hallo " . $anrede[0] . ", ich bin @**DATA** und kann bei der Suche nach einem Dolmetscher schnell Auskunft geben.\r\n\r\n";
	$antwort .= "Bitte sende einfach eine private Nachricht an mich, in der die **Region** und die **Sprache(n)** vorkommen, für die Du Dolmetscher / Übersetzer suchst. Die **Region** entspricht dabei dem **Namen eines der Tabellenblätter** (**nicht** dem Wohnort eines Dolmetschers!) in der Dolmetscherdatenbank. Du kannst mehrere Sprachen angeben, aber nur eine Region; gibst Du mehr als eine Region an, so wird nur die zuletzt angegebene für die Suche genutzt.\r\n\r\n";
	$antwort .= "Beispiele: `Polnisch Dresden`, `Arabisch Leipzig`, `Leipzig Kurdisch`, `Chemnitz Tschechisch`, `Zwickau Urdu Farsi`, `Russisch, Ukrainisch Oberlausitz`.\r\n\r\n";
	$antwort .= "Weitere Funktionen:\r\n* `hilfe` oder `help` im Nachrichtentext gibt diese Informationen aus.\r\n* `statistik` listet kurze Nutzungsstatistiken auf.";
    $log->lwrite($anfrage['message']['sender_full_name'] . " hat die Hilfefunktion ausgelöst.");

  $statistik['hilfe'] = $statistik['hilfe'] + 1 ?? 1;
	httpAntwort(200,$antwort);
	die;
}

if (preg_match('/statistik/i', $anfrage['message']['content']) === 1) {  // Test auf Aufruf der Statistikfunktion
  $statistik['statistik'] = $statistik['statistik'] + 1 ?? 1;
  $antwort = statistik();
  httpAntwort(200,$antwort);
  die;
}

        require __DIR__ . '/vendor/autoload.php';

        $client = new \Google_Client();
        $client->setApplicationName('Zulip-Transmitter');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
        $client->setAccessType('offline');
        $client->setAuthConfig(__DIR__ . $authfile_json);
        $sheetservice = new Google_Service_Sheets($client);

        // Hier das RUFBEREITSCHAFTS-Spreadsheet:
        $rufbereitschaftId = $spreadsheet_id;
        $response = $sheetservice->spreadsheets->get($rufbereitschaftId);
        $spreadsheetProperties = $response->getProperties();
        $blaetter = array();
        foreach ($response->getSheets() as $sheet) {
        	$sheetProperties = $sheet->getProperties();
        	$blaetter[$sheetProperties->title] = $sheetProperties->sheetId;
        }

   		foreach ($blaetter as $titel => $id) {
   			$titeltemp = preg_replace('/[\d[:punct:]]+/u', ' ', $titel); // $titel selbst nicht verändern, damit wird nachher die Datenbank adressiert!
   			$titeltemp = preg_replace(array('/\s+/','/\n\r+/'), ' ', $titeltemp);
   			$titelarray = explode(' ', $titeltemp);
   			foreach ($titelarray as $titelitem) {
   				if ( strpos(strtolower($anfrage['message']['content']), strtolower($titelitem)) !== false ) {
   					$zielblattname = $titel;
	   				$zielblattid = $id;
	   			}
	   		}
   		}

   		if (!$zielblattname) {
        $statistik['keinErgebnis'] = $statistik['keinErgebnis'] + 1 ?? 1;
   			httpAntwort(200,"Hallo " . $anrede[0] . ", zu Deiner Anfrage habe ich leider nichts finden können. Schau mal nach, ob Du Dich nicht evtl. verschrieben hast.\r\n\r\nEin paar Beispiele für mögliche Sucheingaben siehst Du, wenn Du einfach das Wort `Hilfe` als private Nachricht an mich sendest.\r\n");
        $log->lwrite('Keine Ergebnisse für die Suchanfrage von ' . $anrede[0] . " " . $anrede[1] . ".");
   			die;
   		}
      $statistik['region'][$zielblattname] = $statistik['region'][$zielblattname] + 1 ?? 1;

   		// jetzt haben wir:
   		// $zielblattname = Name des Tabellenblatts, in dem gesucht werden muss
   		// $zielblattid = ID des Tabellenblatts, in dem gesucht werden muss

   		$range = $zielblattname . "!A1:Q"; // Hier die Zielrange inkl. Tabellenblatt
   		// mit Range = Spalten bis Q fliegen die Spalten "Straße" und "E-Mail" raus!
        $response = $sheetservice->spreadsheets_values->get($rufbereitschaftId, $range);
        $values = $response->getValues();
        if(empty($values)) {
          $statistik['dbfehler'] = $statistik['dbfehler'] + 1 ?? 1;
          httpAntwort (200, "Hallo " . $anrede[0] . ", tut mir leid, ich kann derzeit keine Daten aus der Dolmetscherdatenbank bekommen.");
          $log->lwrite ("Datenabfrage von Spreadsheet liefert NULL Daten.");
          die;
        }
        $resultate = array();
        $sprachstatistik = array();

        foreach ($values as $nummer => $zeile) {
        	if (empty($zeile[2])) { continue; }  // kein Name
        	if ($zeile[4] == "TRUE") { continue; }  // will/soll nicht vermittelt werden
       		$sprachspalte = preg_replace('/[\d[:punct:]]+/u', ' ', $zeile[0]);  // Sonderzeichen zu Space
       		$sprachspalte = trim(preg_replace(array('/\s+/','/\n\r+/'), ' ', $sprachspalte));  // mehrere Spaces weg
       		$zeilenspracharray = explode(' ', $sprachspalte);  // $zeilenspracharray[0] enthält die Sprache der Tabellenzeile
        	$zeilensprache = strtolower($zeilenspracharray[0]);
        	if ( strpos(strtolower($anfrage['message']['content']), $zeilensprache) !== false ) {  // gefunden
        		array_push($resultate, $zeile);
        		if ( !in_array($zeile[0], $sprachstatistik, true) ) {	// für Sprachstatistik
        			array_push($sprachstatistik, $zeile[0]);
        		}
       		}
           	}
	$suffix = 'Die komplette aktuelle Tabelle findest Du als Dateianhang in der letzten Nachricht im [Thema "Liste Rufbereitschaft"](https://team.beeidigte-dolmetscher.de/#narrow/stream/6-Rufbereitschaft/topic/Liste.20Rufbereitschaft) im Stream #**Rufbereitschaft**.';
	if (empty($resultate)) {
		$antwort = "Hallo " . $anrede[0] . ", die Suche in der Region **" . $zielblattname . "** hat leider keine Ergebnisse geliefert.\r\n\r\n" . $suffix;
    $log->lwrite ("Suche liefert keine Ergebnisse.");
    $statistik['keinErgebnis'] = $statistik['keinErgebnis'] + 1 ?? 1;
		httpAntwort(200, $antwort);
		die;
	}
	foreach ($sprachstatistik as $suchsprache) {
		$statistik['sprache'][$suchsprache] = $statistik['sprache'][$suchsprache] + 1 ?? 1;
	}

    	// Baue die Ergebnistabelle:
$fussnoten = "";
$fn = 1;
$resultatetabelle = "Sprache | Name | Telefon | Mobil | Aktuelles | Bemerkungen\r\n--- | --- | --- | --- | --- | ---\r\n";

  $data_extern = array( 'rufbname' => $anrede[0], 'region' => $zielblattname, 'ergebnisse' => array());
  $data_i = 0;
foreach ($resultate as $key=>$zeile) {
	$zeile[0] = preg_replace('/[\d[:punct:]]+/u', ' ', $zeile[0]);
    $zeile[0] = trim(preg_replace(array('/\s+/','/\n\r+/'), ' ', $zeile[0]));
    $zeilenspracharray = explode(' ', $zeile[0]);  // $zeilenspracharray[0] enthält die Sprache der Tabellenzeile
    $zeilensprache = $zeilenspracharray[0];
    $eintrag .= $zeilensprache . " | ";
      $data_ii = strval($data_i);
      $data_extern['ergebnisse'][$data_ii]['zeilensprache'] = $zeilensprache;

	if ($zeile[6] == "TRUE" && $zeile[7] == "TRUE") { $status = "§DÜ"; }
	elseif ($zeile[6] == "TRUE" && $zeile[7] == "FALSE") { $status = "§D"; }
	elseif ($zeile[6] == "FALSE" && $zeile[7] == "TRUE") { $status = "§Ü"; }
	else { $status = ""; }

	$eintrag .= $status . " **" . mb_strtoupper($zeile[2]) . "** " . $zeile[3];  // **NAME** Vorname
      $data_extern['ergebnisse'][$data_ii]['name'] = $status . " " . mb_strtoupper($zeile[2]) . " " . $zeile[3];
	if ( $zeile[16] && $zeile[16] !== "" && strpos(strtolower($zielblattname), strtolower(substr($zeile[16],0,5))) === false ) {  // Falls Ort 1) vorhanden, 2) nicht gleich Tabellenblatt:
		$eintrag .= " (" . $zeile[16] . ")";
      $data_extern['ergebnisse'][$data_i]['name'] = $data_extern['ergebnisse'][$data_i]['name'] . " (" . $zeile[16] . ")";
	}
  $telefon = removeNbsp($zeile[11]);
      $data_extern['ergebnisse'][$data_ii]['telefon'] = $telefon;
  $mobil = removeNbsp($zeile[13]);
      $data_extern['ergebnisse'][$data_ii]['mobil'] = $mobil;
	$eintrag .= " | " . $telefon . " | " . $mobil . " | " . $zeile[1];
  if ($zeile[1]) {  // falls "Aktuelles" gesetzt ist, Hinweis setzen und Fußnote bauen
    $fussnoten .= "(" . $fn . ") - " . $zeile[9] . "\r\n";
    $eintrag .= " (" . $fn . ")";
    $fn++;
      $data_extern['ergebnisse'][$data_ii]['aktuelles'] = $zeile[9];
  }
  $eintrag .= " | " . trim(preg_replace(array('/\s+/','/\n\r+/'), ' ', $zeile[10])) . "\r\n";  // Bemerkungen bereinigen
      $data_extern['ergebnisse'][$data_ii]['bemerkungen'] = trim(preg_replace(array('/\s+/','/\n\r+/'), ' ', $zeile[10]));
	$resultatetabelle .= $eintrag;
	$eintrag = "";
      $data_i++;
}

    $data_a = bin2hex(random_bytes(8));
    $data_date = new DateTime('NOW');
    $data_fname = "data" . $anrede[0] . "_" . $anrede[1] . $data_b . "@" . $data_a;
    $data_b = $data_date->format('Y-m-dTHis');
    $data_fpath = __DIR__ . "/datahook/" . $data_fname . ".json";
    file_put_contents($data_fpath, json_encode($data_extern));

	$prefix = "Hallo " . $anrede[0] . ", die Suche in der Region **" . $zielblattname . "** ergab Folgendes:\r\n\r\n";
  $prefix .= "[(hier klicken für externe Ansicht der Ergebnisse)](http://rb.beeidigte-dolmetscher.de/_einsaetze/datahook-extern.php?f=" . $data_fname . ")\r\n\r\n";
	$antwort = $prefix . $resultatetabelle . "\r\n" . $fussnoten . "\r\n" . $suffix . "\r\n";
    $log->lwrite ("Suche liefert " . count($resultate) . " Ergebnisse.");

	httpAntwort(200,$antwort);
die;

function httpAntwort($code,$body) {
	echo json_encode(['content' => $body]);
	http_response_code($code);
  global $statistikfile, $statistik;
  file_put_contents($statistikfile, json_encode($statistik));
}

function removeNbsp($str)
{
    $str = htmlentities($str);
    $str = str_replace(" ", "&nbsp;", $str);
    $str = html_entity_decode($str);

    return $str;
  }

function statistik() {
  global $statistik;
  $antwort = "# Nutzungsstatistik für !avatar(28) @_**DATA**\r\n\r\n";

  $statistiken = array(
    "aufrufe" => $statistik['aufrufe'] ?? 'keine',
    "unauthorized" => $statistik['unauthorized'] ?? 'keine',
    "mentions" => $statistik['mentions'] ?? 'keine',
    "hilfe" => $statistik['hilfe'] ?? 'keine',
    "dbfehler" => $statistik['dbfehler'] ?? 'keine',
    "statistik" => $statistik['statistik'] ?? 'keine',
    "keinErgebnis" => $statistik['keinErgebnis'] ?? 'keine'
  );
  $antwort .= "Ereignis | Anzahl Vorkommnisse\r\n--- | ---\r\n";
  $antwort .= "nicht autorisierte Anfragen | " . $statistiken['unauthorized'] . "\r\n";
  $antwort .= "Erwähnungen außerhalb von PN | " . $statistiken['mentions'] . "\r\n";
  $antwort .= "legitime Anfragen | " . $statistiken['aufrufe'] . "\r\n";
  $antwort .= "Aufrufen der Hilfefunktion | " . $statistiken['hilfe'] . "\r\n";
  $antwort .= "Aufrufen der Statistiken | " . $statistiken['statistik'] . "\r\n";
  $antwort .= "DB-Verbindungsfehler | " . $statistiken['dbfehler'] . "\r\n";
  $antwort .= "ergebnislose Suchanfragen | " . $statistiken['keinErgebnis'] . "\r\n\r\n---\r\n";
  
  $antwort .= "\r\nNutzer | Anzahl Anfragen\r\n--- | ---\r\n";
  foreach ($statistik['nutzer'] as $name => $anzahl) {
    $antwort .= $name . " | " . $anzahl . "\r\n";
  }
  $antwort .= "\r\n---\r\n";
  $antwort .= "\r\ngesuchte Sprache | Anzahl Anfragen\r\n--- | ---\r\n";
  foreach ($statistik['sprache'] as $sprache => $anzahl) {
    $antwort .= strtoupper($sprache) . " | " . $anzahl . "\r\n";
  }
  $antwort .= "\r\n---\r\n";
  $antwort .= "\r\nangefragte Region | Anzahl Anfragen\r\n--- | ---\r\n";
  foreach ($statistik['region'] as $region => $anzahl) {
    $antwort .= $region . " | " . $anzahl . "\r\n";
  }

  return $antwort;
}
?>
