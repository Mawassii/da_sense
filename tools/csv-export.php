<?php
require_once("../parameters.php");

$delimiter = ";";

if($_REQUEST["action"] == "export")
{ 


	$mysqli = new mysqli(DB_HOST, DB_LGN, DB_PWD, DB_DB);
	
	$stmt = "SELECT data.ID, nodeID, timestamp, value, samples, longitude, latitude, altitude, accuracy, provider ".
			"FROM data, locations ".
			"WHERE locationID = locations.ID ";
			
	if($_REQUEST["nodeID"] != "")
	{
		$stmt.= "AND nodeID = '" . $_REQUEST["nodeID"] ."' ";
	}
	$stmt.= "ORDER BY data.ID ASC";

	$query = $mysqli->query($stmt);

	$csvdata = "ID". $delimiter .
				"deviceID". $delimiter .
				"timestamp". $delimiter .
				"timestamp (seconds)". $delimiter .
				"timestamp". $delimiter .
				"value". $delimiter .
				"samples". $delimiter .
				"longitude". $delimiter .
				"latitude". $delimiter .
				"altitude". $delimiter .
				"accuracy". $delimiter .
				"provider\n";
	while($row = $query->fetch_row())
	{
		$timestamp = $row[2];
		$timestamp = date('D, d M Y H:i:s',$timestamp/1000).":".sprintf('%03d',bcmod($timestamp,1000));
		
		$csvdata .=  $row[0]. $delimiter .
					 $row[1]. $delimiter .
					 $row[2]. $delimiter . 
					 floor($row[2]/1000). $delimiter . 
					 $timestamp. $delimiter .
					 $row[3]. $delimiter . 
					 $row[4]. $delimiter . 
					 $row[5]. $delimiter . 
					 $row[6]. $delimiter . 
					 $row[7]. $delimiter . 
					 $row[8]. $delimiter . 
					 $row[9]. "\n";
	}
	$mysqli->close();
	
	/**
	 * CSV-Deal
	 */
	header("content-type: application/csv-tab-delimited-table");
    header("content-length: ".strlen($csvdata));
    header("content-disposition: attachment; filename=\"dasense_data.csv\"");	
	echo $csvdata;
	die;
}

?>

<html>
<body>

<form action="csv-export.php" method="post">
<input type="hidden" id="action" name="action" value="export" />
Geräte-ID: <input id="nodeID" name="nodeID" value="" /></br>
- leer für alle Daten - 
</br> 
<input type="submit" value="CSV erstellen" />
</form>

</body>
</html>



