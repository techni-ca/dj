<?php
require('constants.php');
/* Connect to the Database */
echo "Please wait while I connect to the database\r\n";
$connection=mysql_connect(MYSQLSERVER,'dj','password');
mysql_select_db('store');
echo "Connected.\r\n";

function stock_movements($from,$to,$item_id,&$sales,&$returns,&$orders) {
  $my_result=mysql_query("select * from stock_history where stock_history.item_id='$item_id' and change_time > '$from' and change_time < '$to'");
  while ($my_row=mysql_fetch_object($my_result)) {
    $diff=($my_row->old_qty - $my_row->new_qty);
    if ($my_row->reason=="sale") $sales+=$diff;
    elseif (substr($my_row->reason,0,19)=="Returned for refund") $returns-=$diff;
    elseif (substr($my_row->reason,0,11)=="order entry") $orders-=$diff;
    elseif (substr($my_row->reason,0,18)=="Posting from item#") {
      $new_item=substr($my_row->reason,18);
stock_movements($from,$my_row->change_time,$new_item,$sales,$returns,$orders);
    }
  }
  
}

$datestamp=date("YmdHi");
if (!file_exists('reports')) {
  mkdir('reports', 0777, true);
}
echo "Outputing stock take report.\r\n";
$file=fopen("reports/stock_take.".$datestamp.".html",'w');
fwrite($file,'<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Stock Take Report</title></head><body>\n');

$total=0;
$stock_take_start=strftime("%Y").'-02-01 00:00:00';
$year_end=strftime("%Y").'-04-01 00:00:00';
$result=mysql_query("select item.* from item join item_type using (item_type_id) where (item_type_id <> 827) and (department_id<13 or department_id>19) order by description");
fwrite($file,"<p>The following items were stock checked since ".$stock_take_start.".  The number in stock is calculated based on stock movements the stock date and year end, ".$year_end.".  Note that negative stock quantitites are given a value of -$0.00.\n");
fwrite($file, "<p><table border=0>\n");
fwrite($file, "<tr><th align='left'>Item ID</th><th align='left'>Description</th><th align='left'>Stock Take Date</th><th align='right'>Stock Take Qty</th><th align='right'>Sales</th><th align='right'>Returns</th><th align='right'>Orders In</th><th align='right'>On Apr 1</th><th align='right'>Cost(each)</th><th align='right'>Cost(line)</th></tr>");
while ($row=mysql_fetch_object($result)) {
  $result2=mysql_query("select * from stock_history where stock_history.item_id='$row->item_id' and reason='stock take' order by change_time desc limit 1");
  $row2=mysql_fetch_object($result2);
  $sales=0;
  $returns=0;
  $orders=0;
  if ($row2->change_time > $year_end) {
    stock_movements($year_end,$row2->change_time,$row->item_id,$sales,$returns,$orders);
    $calculated=$row2->new_qty + $sales - $returns - $orders;
  } elseif ($row2->change_time > $stock_take_start) {
    stock_movements($row2->change_time,$year_end,$row->item_id,$sales,$returns,$orders);
    $calculated=$row2->new_qty - $sales + $returns + $orders;
  } else {
	  continue;
  }
  $result3=mysql_query("select cost($row->item_id) as cost");
  $row3=mysql_fetch_object($result3);
  $cost=number_format($row3->cost / $row->default_quantity,2);
  if ($cost==0.00) { 
	  $result3=mysql_query("select round((reg_price / per_qty) / 2,2) as cost from pricelist where item_id='$row->item_id' order by per_qty desc");
    $row3=mysql_fetch_object($result3);
    $cost=$row3->cost;
  }
  $rowcost=number_format($calculated * $cost,2);
  if ($rowcost < 0) {
	  $rowcost="-0.00";
  }
  $total+=$rowcost;
  fwrite($file,"<tr><td align='left'>$row->item_id</td><td align='left'>$row->description</td><td align='left'>$row2->change_time</td><td align='right'>$row2->new_qty<td align='right'>$sales</td><td align='right'>$returns</td><td align='right'>$orders</td><td align='right'>$calculated</td><td align='right'>\$$cost</td><td align='right'>\$$rowcost</td></tr>\n");
}
fwrite($file, "<tr><th colspan='9' align='right'>TOTAL</th><th align='right'>\$".number_format($total,2)."</th></tr>\n");
fwrite($file, "</table>");
$sub_total=0;
fwrite($file, "<p>In addition, the following items have not been stock taken since ".$stock_take_start." but appear to have had a non-zero stock at year end, ".$year_end.". Note that negative stock quantities have been given a value of -$0.00.\n");
fwrite($file, "<p><table border=0>\n");
fwrite($file, "<tr><th align='left'>Item ID</th><th align='left'>Description</th><th align='right'>On Apr 1</th><th align='right'>Cost(each)</th><th align='right'>Cost(line)</th></tr>");
$result=mysql_query("select item.* from item join item_type using (item_type_id) where (item_type_id <> 827) and (department_id<13 or department_id>19) order by description");
while ($row=mysql_fetch_object($result)) {
  $result2=mysql_query("select * from stock_history where stock_history.item_id='$row->item_id' and reason='stock take' and change_time > '$stock_take_start' order by change_time desc limit 1");
  if ($row2=mysql_fetch_object($result2)) continue;
  $result2=mysql_query("select * from stock_history where stock_history.item_id='$row->item_id' and change_time < '$year_end' order by change_time desc limit 1");
  if ($row2=mysql_fetch_object($result2)) {
	  if ($row2->new_qty==0) continue;
    $result3=mysql_query("select cost($row->item_id) as cost");
    $row3=mysql_fetch_object($result3);
    $cost=number_format($row3->cost / $row->default_quantity,2);
    if ($cost==0.00) { 
	    $result3=mysql_query("select round((reg_price / per_qty) / 2,2) as cost from pricelist where item_id='$row->item_id' order by per_qty desc");
      $row3=mysql_fetch_object($result3);
      $cost=$row3->cost;
    }
    $rowcost=number_format($row2->new_qty * $cost,2);
    if ($rowcost < 0) {
	    $rowcost="-0.00";
    }
    $sub_total+=$rowcost;
    fwrite($file,"<tr><td align='left'>$row->item_id</td><td align='left'>$row->description</td><td align='right'>$row2->new_qty<td align='right'>\$$cost</td><td align='right'>\$$rowcost</td></tr>\n");
  }
}
fwrite($file, "<tr><th colspan='4' align='right'>TOTAL</th><th align='right'>\$".number_format($sub_total,2)."</th></tr>\n");
$total+=$sub_total;
fwrite($file, "<tr><th colspan='4' align='right'>GRAND TOTAL</th><th align='right'>\$".number_format($total,2)."</th</tr>\n");
fwrite($file, "</table>");

fwrite($file,"</body></html>\n");


fclose($file);
echo "Done.\r\n";
sleep(10)
?>