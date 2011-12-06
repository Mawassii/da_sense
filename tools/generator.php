<?php
require_once("../parameters.php");

set_time_limit(0);

if($_REQUEST["action"] == "update")
{ 
	$mysqli = new mysqli(DB_HOST, DB_LGN, DB_PWD, DB_DB);
	
	//check, if node exists in DB	
	$stmt = $mysqli->prepare("SELECT count(*) FROM nodes WHERE nodes.ID = ?"); // prepare select statement
	$stmt->bind_param('s', $_REQUEST["nodeID"]); // bind parameters
	$stmt->bind_result($count);

	$stmt->execute(); // execute statement

	if($stmt->fetch() !== NULL) //fetch, if data exists
	{
		$stmt->free_result();
		//doesn't exist: insert
		if($count == 0)
		{
			$insert_stmt = $mysqli->prepare("INSERT INTO nodes (ID, type, description) VALUES (?, 'DUMMY', ?)"); // prepare insert statement
			$insert_stmt->bind_param('ss', $_REQUEST["nodeID"], $_REQUEST["nodeName"]); // bind parameters
			$insert_stmt->execute();
			if ($error = $insert_stmt->error) echo "Error while inserting new node: ";print_r($error);
			$insert_stmt->close();
		}
	
	}
	$stmt->close();
	
	$select_stmt_location = $mysqli->prepare("SELECT ID FROM locations WHERE longitude = ? AND latitude = ? AND altitude = 150"); // prepare select statement
	$select_stmt_location->bind_param("dd", $randLong, $randLat); 	
	
	$insert_stmt_location = $mysqli->prepare("INSERT INTO locations (longitude, latitude, altitude, accuracy) VALUES (?, ?, 150, 10)");
	$insert_stmt_location->bind_param("dd", $randLong, $randLat); // bind parameters
	
	$insert_stmt_data = $mysqli->prepare("INSERT INTO data (nodeID, datatypeID, locationID, value, description, timestamp, samples) VALUES (?,?,?,?,?,?,1)");
	
	$insert_stmt_data->bind_param("ssidss", $_REQUEST["nodeID"], $_REQUEST["type"], $locID, $randValue, $_REQUEST["desc"], $randDate); // bind parameters
	
	//iterate over dataValues
	for ($i = 1; $i <= $_REQUEST["count"]; $i++)
	{
		//random time in span
		$randDate_tmp = mt_rand(strtotime($_REQUEST["dateFrom"]),strtotime($_REQUEST["dateTo"]))*1000;
		$randDate = $randDate_tmp."";
		
		$randLong = mt_rand($_REQUEST["longFrom"]*1000000,$_REQUEST["longTo"]*1000000)/1000000;
		$randLat = mt_rand($_REQUEST["latFrom"]*1000000,$_REQUEST["latTo"]*1000000)/1000000;
		
		$randValue = mt_rand($_REQUEST["valueFrom"],$_REQUEST["valueTo"]);
		
		//exists location, then use ID
		$select_stmt_location->execute(); // execute statement
		$select_stmt_location->bind_result($locID);
									
		//... else insert location
		if ($select_stmt_location->fetch() === NULL) //fetch, if data exists
		{		
			$select_stmt_location->free_result();
			
			$insert_stmt_location->execute();
			if ($error = $insert_stmt_location->error) echo "Error while inserting new location: ".print_r($error);
			$locID = $insert_stmt_location->insert_id; //  get the id of the new location
		}
	
		//insert data
		$insert_stmt_data->execute();
		if ($error = $insert_stmt_data->error) echo "Error while inserting new dataValue: ".print_r($error);
	}
	
	$select_stmt_location->close();
	$insert_stmt_location->close();
	$insert_stmt_data->close();
	echo "Daten erfolgreich angelegt.<br><br>";
}

?>

<html>
<body>

<form action="generator.php" method="post">
<input type="hidden" id="action" name="action" value="update" />
Geräte-ID: <input id="nodeID" name="nodeID" value="123456789012345" /></br>
Beschreibung Gerät: <input id="nodeName" name="nodeName" value="TestMote" /></br>
Beschreibung Daten: <input id="desc" name="desc" value="Testwerte <?php echo date("d.m.Y");?>" /></br></br>

Anzahl Testwerte: <input id="count" name="count" value="1000" /></br>
Art Testwerte: <select name="type" id="type">
					<option value="CO">CO</option>
					<option value="CO2">CO2</option>
					<option value="LEQ">dB</option>
				</select></br>
Werte-Intervall: <input id="valueFrom" name="valueFrom" value="0" /> <input id="valueTo" name="valueTo" value="100" /></br></br>
Zeitraum: <input id="dateFrom" name="dateFrom" value="<?php echo date("d.m.Y");?>" /> <input id="dateTo" name="dateTo" value="<?php echo date("d.m.Y", mktime(0, 0, 0, date("m")+1, date("d"), date("Y")));?>" /></br></br>
Längengrad: <input id="longFrom" name="longFrom" value="8.621385" /> <input id="longTo" name="longTo" value="8.675394" /></br>
Breitengrad: <input id="latFrom" name="latFrom" value="49.855727" /> <input id="latTo" name="latTo" value="49.89044" /></br>
<input type="button" value="Zurücksetzen auf DA-Innenstadt" onclick="JavaScript:longFrom.value='8.621385';longTo.value='8.675394';latFrom.value='49.855727';latTo.value='49.89044';" /></br>
</br> 
<input type="submit" value="Abschicken" />
</form>

</body>
</html>



