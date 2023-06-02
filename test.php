<?php
require('constants.php');

/* Connect to the Database */
echo "Please wait while I connect to the database\r\n";
$connection=mysql_pconnect(MYSQLSERVER,'dj','password');
mysql_select_db('store');
echo "Connected.\r\n";

$datestamp=date("YmdHi");
if (!file_exists('reports')) {
  mkdir('reports', 0777, true);
}
echo "Outputing stock take report.\r\n";
$file=fopen("reports/stock_take.".$datestamp.".csv",'w');
$rows=mysql_query("select * from item limit 5;");
while ($row=mysql_fetch_row($rows)) {
  fputcsv($file,$row);
}
fclose($file);
echo "Done.\r\n";
sleep(10)
?>