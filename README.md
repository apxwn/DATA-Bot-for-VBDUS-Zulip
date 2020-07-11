# DATA Bot for VBDUS-Zulip
 Outgoing Webhook Bot für Zulip, nimmt Nutzeranfragen und durchsucht Google Spreadsheet danach
 
 Setup:
 - Einrichtung eines Outgoing webhook Bots mit Endpunkt [Uri]/datahook.php
 
 Variablendefinitionen in `datahook-secrets.php`:
 - `$bot_email` = E-Mail-Adresse des Bots, nur für Prüfung der Legitimität einer Anfrage
 - `$spreadsheet_id` = ID des Google Spreadsheets
 - `$authfile_json` = Pfad und Name.Ext der [JSON-Auth-Datei für Google](https://cloud.google.com/docs/authentication/production?hl=de)
