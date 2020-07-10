<?php
error_reporting(0);
$pass = $_GET["f"];
$filename = __DIR__ . "/datahook/" . $pass . ".json";
$strJsonFileContents = file_get_contents($filename);
$data = json_decode($strJsonFileContents, true);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="datahook-extern.css">
</head>
<body >
  <div class="py-5">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <p class="lead">Hallo <?php echo $data['rufbname'] . ", in der <strong>Region " . $data['region']; ?>
          </strong> war zu Deiner Anfrage zu finden:</p>
        </div>

<?php
foreach ($data['ergebnisse'] as $key => $ergebnis) {
  $output = '<div class="col-md-12"><div class="table-responsive"><table class="table table-bordered "><thead class="thead-dark"><tr><th style="width: 20%;"><strong>' . strtoupper($ergebnis['zeilensprache']) . '</strong></th><th>' . $ergebnis['name'] . '</th></tr></thead>';
  $output .= '<tbody><tr><th>Telefon</th><td><a href="tel:' . str_replace(array("\xc2\xa0", " "), '', $ergebnis['telefon']) . '">' . $ergebnis['telefon'] . '</td></tr>';
  $output .= '<tbody><tr><th>Mobil</th><td><a href="tel:' . str_replace(array("\xc2\xa0", " "), '', $ergebnis['mobil']) . '">' . $ergebnis['mobil'] . '</td></tr>';
  $output .= '<tr><th>Aktuelles</th><td><mark>' . $ergebnis['aktuelles'] . '</mark></td></tr>';
  $output .= '<tr><th scope="row">Bemerkungen</th><td>' . $ergebnis['bemerkungen'] . '</td></tr></tbody></table></div></div>';
  echo $output;
}
?>
  </div>
</body>

</html>