<?php
error_reporting(0);
$pass = $_GET["f"];
$filename = __DIR__ . "/datahook/" . $pass . ".json";
$strJsonFileContents = file_get_contents($filename);
$data = json_decode($strJsonFileContents, true);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <title>Ergebnisse für <?php echo $data['region']; ?></title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="datahook-extern.css">
</head>
<body>
  <div class="py-5">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <p class="lead">
          	<?php if (!file_exists($filename)) { echo ("Falsche Parameter übergeben, diese Daten existieren nicht.</p></div></div></div></div></body>"); die; }
          	?>
          	Hallo <?php echo $data['rufbname'] . ", die Suche in der <strong>Region " . $data['region']; ?>
          </strong> ergab Folgendes:</p>
        </div>

<?php
foreach ($data['ergebnisse'] as $key => $ergebnis) {
  $output = '<div class="col-md-12"><div class="table-responsive"><table class="table table-bordered "><thead class="thead-dark"><tr><th style="width: 20%;" scope="col"><strong>' . strtoupper($ergebnis['zeilensprache']) . '</strong></th><th scope="col">' . $ergebnis['name'] . '</th></tr></thead>';
  $output .= '<tbody><tr><th scope="row">Telefon</th><td><a href="tel:' . preg_replace('/[^0-9+]+/', '', $ergebnis['telefon']) . '">' . $ergebnis['telefon'] . '</a></td></tr>';
  $output .= '<tr><th scope="row">Mobil</th><td><a href="tel:' . preg_replace('/[^0-9+]+/', '', $ergebnis['mobil']) . '">' . $ergebnis['mobil'] . '</a></td></tr>';
  $output .= '<tr><th scope="row">Aktuelles</th><td>';
  	if ($ergebnis['aktuelles']) {
  		$output .= '<mark>' . $ergebnis['aktuelles'] . '</mark>';
  	}
  $output .= '</td></tr><tr><th scope="row">Bemerkungen</th><td>' . $ergebnis['bemerkungen'] . '</td></tr></tbody></table></div></div>';
  echo $output;
}
?>
  	</div>
  </div>
</div>
</body>

</html>