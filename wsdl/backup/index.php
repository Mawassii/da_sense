<?php
//IMPORTANT: <?php has to be in line 1 or the XML-Encoding will fuck you in the arse 

if(isset($_GET["wsdl"]))
{
	header("Content-type: text/xml");
	header ("Location:da_sense.wsdl");
	exit();
}
else
{
	require_once("../parameters.php");
		
		
	function array_objectsearch($array, $obj_key, $value)
	{
		$found = null;  
		foreach($array as $element)
		{  
			if ($value == $element->$obj_key)
			{  
				$found = $element;  
				break;  
			}  
		}
		return $found;		
	}
	 
	/*
	* Class descriptions for SOAP-Request/Response
	*/
	class TypesConfig{}
	class TimeConfig{}
	class LocationConfig{}
	class NodeType{}

	class Node
	{
		public $ID;
		public $type;
		public $description;
		public $values = array();
		
		public function __construct($ID, $type, $desc)
		{
			$this->ID = $ID;
			$this->type = $type;
			$this->description = $desc;
		}
		
		public function addValue($datavalue)
		{
			$this->values[] = $datavalue;
		}
	}

	class DataValue
	{
		public $type;
		public $value;
		public $timestamp;
		public $samples;
		public $description;
		public $location;
		
		public function __construct($type, $value, $ts, $samples, $desc, $loc)
		{
			$this->type = $type;
			$this->value = $value;
			$this->timestamp = $ts;
			$this->samples = $samples;
			$this->description = $desc;
			$this->location = $loc;
		}
	}

	class Location
	{
		public $longitude;
		public $latitude;
		public $altitude;
		public $accuracy;
		public $provider;
		
		public function __construct($long, $lat, $alt, $acc, $prov)
		{
			$this->longitude = $long;
			$this->latitude = $lat;
			$this->altitude = $alt;
			$this->accuracy = $acc;
			$this->provider = $prov;
		}	
	}

	class DataType
	{
		public $ID;
		public $displayname;
		public $measure;
		public $description;
		
		public function __construct($ID, $name, $measure, $desc)
		{
			$this->ID = $ID;
			$this->displayname = $name;
			$this->measure = $measure;
			$this->description = $desc;
		}
	}
	
	class SuccessObj
	{
		public $success;
		
		public function __construct()
		{
			$this->success = true;
		}
	}
	
	//Master-Class; contains all functions
	class DASense
	{
		/**
		 * Gets data-values for specified parameters
		 * Return: Array( Nodes( Array(DataValues) ) )
		 */
		function getData($typesConfig, $timeConfig, $locationConfig)
		{ 
			//check if all parameters exist
			if($typesConfig == null || $timeConfig == null || $locationConfig == null) throw new SoapFault("ERROR", "Not all segments are specified.");
			
			
			//array for return
			$response = array();
			
			$mysqli = new mysqli(DB_HOST, DB_LGN, DB_PWD, DB_DB);
			if($mysqli->connect_error)
				throw new SoapFault("ERROR", "Couldn't reach database.");
				
			$unprep_stmt = "SELECT nodes.ID as nodeID, nodes.type, nodes.description, ".
							"data.datatypeID, data.value, data.timestamp, data.samples, ".
							"locations.longitude, locations.latitude, locations.altitude, locations.accuracy, locations.provider ".
							"FROM nodes ".
							"LEFT JOIN data ON (nodes.ID = data.nodeID) ".
							"LEFT JOIN locations ON (data.locationID = locations.ID) ";		
			//create where-statements for restrictions with wild cards (for mysqli)
			//only if returnAll is false
			$arrWhere_stmts = array();
			$bind_params = "";
			$arrBind_params_value = array();
			if(!$typesConfig->returnAll)
			{
				//no types specified
				if($typesConfig->returnTypes === NULL)
					throw new SoapFault("ERROR", "No types specified!");
				
				if(is_array($typesConfig->returnTypes))
					$joinedTypes = join("','",$typesConfig->returnTypes);
				else
					$joinedTypes = $typesConfig->returnTypes;
				$joinedTypes = "'".$joinedTypes."'";
					
				$arrWhere_stmts[] = "(data.datatypeID in (".$joinedTypes."))";		
			}
			if(!$timeConfig->returnAll)
			{
				//no timespan specified
				if($timeConfig->from === NULL || $timeConfig->to === NULL)
					throw new SoapFault("ERROR", "At least one end of the timespan is missing!");

				$arrWhere_stmts[] = "(data.timestamp BETWEEN ? AND ?)";
				$bind_params .= "ss";
				$arrBind_params_value[] = &$timeConfig->from;
				$arrBind_params_value[] = &$timeConfig->to;
			}
			if(!$locationConfig->returnAll)
			{
				//no location specified
				if($locationConfig->topLeft === NULL || $locationConfig->topLeft->longitude === NULL || $locationConfig->topLeft->latitude === NULL || 
					$locationConfig->bottomRight === NULL || $locationConfig->bottomRight->longitude === NULL || $locationConfig->bottomRight->latitude === NULL)
					throw new SoapFault("ERROR", "location square is not proper specified!");
				$arrWhere_stmts[] = "((locations.longitude BETWEEN ? AND ?) AND (locations.latitude BETWEEN ? AND ?))";
				$bind_params .= "dddd";
				$arrBind_params_value[] = &$locationConfig->topLeft->longitude;
				$arrBind_params_value[] = &$locationConfig->bottomRight->longitude;
				$arrBind_params_value[] = &$locationConfig->topLeft->latitude;
				$arrBind_params_value[] = &$locationConfig->bottomRight->latitude;
			}

			//combine where-statements to one big AND-separated statement
			if(count($arrWhere_stmts)>0)
				$unprep_stmt .= "WHERE (".join(" AND ",$arrWhere_stmts).")";

			$stmt = $mysqli->prepare($unprep_stmt); // prepare select statement

			if($bind_params != "" && count($arrBind_params_value)>0)
			{
				//add bind_params as first element to array
				$arrBind_params_value = array_merge(array($bind_params),$arrBind_params_value);
				//use call_user_func_array to pass argument list as array

				call_user_func_array(array($stmt, "bind_param"), $arrBind_params_value);
			}
					
			$stmt->bind_result($nodeId, $nodeType, $nodeDesc, $dataType, $dataValue, $dataTime, $dataSamples, $locLong, $locLat, $locAlt, $locAcc, $locProv);

			$stmt->execute(); // execute statement
			while($stmt->fetch() !== NULL) //fetch, while new data exist
			{
				$node = array_objectsearch($response, "ID", $nodeId);
		
				if($node === NULL)
				{
					$node = new Node($nodeId, $nodeType, $nodeDesc); //add node to $response-array
					$response[] = $node;
				}
				
				$dataValue = new DataValue($dataType, $dataValue, $dataTime, $dataSamples, new Location($locLong, $locLat, $locAlt, $locAcc, $locProv));
				$node->addValue($dataValue);
			}
			$stmt->close();

			return $response;
		}

		/**
		 * Gets data-values for a node
		 * Return: Node( Array(DataValues) )
		 */
		function getDataForNode($nodeID)
		{ 
			$mysqli = new mysqli(DB_HOST, DB_LGN, DB_PWD, DB_DB);
			
			$unprep_stmt = "SELECT nodes.ID as nodeID, nodes.type, nodes.description, ".
							"data.datatypeID, data.value, data.timestamp, data.samples, data.description, ".
							"locations.longitude, locations.latitude, locations.altitude, locations.accuracy, locations.provider ".
							"FROM nodes ".
							"LEFT JOIN data ON (nodes.ID = data.nodeID) ".
							"LEFT JOIN locations ON (data.locationID = locations.ID) WHERE nodes.ID = ?";
			
			$stmt = $mysqli->prepare($unprep_stmt); // prepare select statement
			$stmt->bind_param('s', $nodeID); // bind parameters
			$stmt->bind_result($nodeId, $nodeType, $nodeDesc, $dataType, $dataValue, $dataTime, $dataSamples, $dataDesc, $locLong, $locLat, $locAlt, $locAcc, $locProv);
			
			$stmt->execute(); // execute statement
			$first=true;
			while($stmt->fetch() !== NULL) //fetch, while new data exist
			{
				//initialize "highlander"-node: "There could only be one" 
				if($first)
				{
					$node = new Node($nodeId, $nodeType, $nodeDesc);
					$first=false;
				}
				
				$dataValue = new DataValue($dataType, $dataValue, $dataTime, $dataSamples, $dataDesc, new Location($locLong, $locLat, $locAlt, $locAcc, $locProv));
				$node->addValue($dataValue);
			}
			$stmt->close();
			
			return $node;
		}

		/**
		 * Sets data-values for a node
		 */
		function setData($nodeContainer)
		{ 
			$node = $nodeContainer->node;

			$mysqli = new mysqli(DB_HOST, DB_LGN, DB_PWD, DB_DB);

			//check, if node exists in DB	
			$stmt = $mysqli->prepare("SELECT count(*) FROM nodes WHERE nodes.ID = ?"); // prepare select statement
			$stmt->bind_param('s', $node->ID); // bind parameters
			$stmt->bind_result($count);
			
			$stmt->execute(); // execute statement
			if($stmt->fetch() !== NULL) //fetch, if data exists
			{
				$stmt->free_result();
				//doesn't exist: insert
				if($count == 0)
				{
					$insert_stmt = $mysqli->prepare("INSERT INTO nodes (ID, type, description) VALUES (?, ?, ?)"); // prepare insert statement
					$insert_stmt->bind_param('sss', $node->ID, $node->type, $node->description); // bind parameters
					$insert_stmt->execute();
					if ($error = $insert_stmt->error) throw new SoapFault("ERROR", "Error while inserting new node: ".print_r($error,1));
					$insert_stmt->close();
				}

			}
			$stmt->close();
			
			//check, if dataValues is array. If not, wrap it up
			if(!is_array($node->values))
				$node->values = array($node->values);

			//iterate over dataValues
			foreach ($node->values as $dataValue)
			{
				$locObj = $dataValue->location;
				//exists location, then use ID
				$select_stmt_location = $mysqli->prepare("SELECT ID FROM locations WHERE longitude = ? AND latitude = ? AND altitude = ?"); // prepare select statement
				
				$select_stmt_location->bind_param("ddd", $locObj->longitude, $locObj->latitude, $locObj->altitude);  
				$select_stmt_location->execute(); // execute statement
				$select_stmt_location->bind_result($locID);
											
				//... else insert location
				if ($select_stmt_location->fetch() === NULL) //fetch, if data exists
				{		
					$select_stmt_location->free_result();
					
					$insert_stmt_location = $mysqli->prepare("INSERT INTO locations (longitude, latitude, altitude, accuracy, provider) VALUES (?, ?, ?, ?, ?)");
					$insert_stmt_location->bind_param("dddds", $locObj->longitude, $locObj->latitude, $locObj->altitude, $locObj->accuracy, $locObj->provider); // bind parameters
					$insert_stmt_location->execute();
					if ($error = $insert_stmt_location->error) throw new SoapFault("ERROR", "Error while inserting new location: ".print_r($error,1));
					$locID = $insert_stmt_location->insert_id; //  get the id of the new location
					$insert_stmt_location->close();
				}
				$select_stmt_location->close();

				//insert data
				$insert_stmt_data = $mysqli->prepare("INSERT INTO data (nodeID, datatypeID, locationID, value, timestamp, samples) VALUES (?,?,?,?,?,?)");
				$insert_stmt_data->bind_param("ssidsi", $node->ID, $dataValue->type, $locID, $dataValue->value, $dataValue->timestamp, $dataValue->samples); // bind parameters
				$insert_stmt_data->execute();
				if ($error = $insert_stmt_data->error) throw new SoapFault("ERROR", "Error while inserting new dataValue: ".print_r($error,1));
				$insert_stmt_data->close();
			}
die;
			return true;
		}

		/**
		 * Gets all nodes, which support specified types
		 * Return: Array( Nodes )
		 */
		function getNodes($typesConfig)
		{ 
			
			$response = array();
			
			$mysqli = new mysqli(DB_HOST, DB_LGN, DB_PWD, DB_DB);
			
			//ALL: no restriction on "type"
			if($typesConfig == "ALL")
				$unprep_stmt = "SELECT ID, type, description FROM nodes";
			else
				$unprep_stmt = "SELECT ID, type, description FROM nodes WHERE type = ?";
				
			$stmt = $mysqli->prepare($unprep_stmt); // prepare select statement
			$stmt->bind_param('s', $typesConfig); // bind parameters
			$stmt->bind_result($ID, $type, $desc); //bind result variables
			$stmt->execute(); // execute statement
			while($stmt->fetch() !== NULL) //fetch, while new data exist
			{
				$response[] = new Node($ID, $type, $desc); //add node to $response-array
			}

			return $response; 
		}

		/**
		 * Gets all datatype-description for specified types
		 * Return: Array( DataTypes )
		 */
		function getDataTypeInfos()
		{
			$response = array();
			
			$mysqli = new mysqli(DB_HOST, DB_LGN, DB_PWD, DB_DB);
			
			$unprep_stmt = "SELECT ID, displayname, measure, description FROM datatypes";
				
			$stmt = $mysqli->prepare($unprep_stmt); // prepare select statement
			$stmt->bind_result($ID, $name, $measure, $desc); //bind result variables
			$stmt->execute(); // execute statement
			while($stmt->fetch() !== NULL) //fetch, while new data exist
			{
				$response[] = new DataType($ID, $name, $measure, $desc); //add datatype to $response-array
			}
			$stmt->close();

			return $response; 
		}
	}
	
	//path to wsdl
	$wsdl_url = "da_sense.wsdl";
	if(isset($_GET["flush_cache"]))
		$wsdl_cache = WSDL_CACHE_NONE;
	else
		$wsdl_cache = WSDL_CACHE_BOTH;
	//mapping: wsdl-Classes => php-Classes
	$classmap = array(
		'Types' => "TypesConfig",
		'Time' => "TimeConfig",
		'Location' => "LocationConfig",
		'Node' => "Node",
		'NodeType' => "NodeType",
		'DataTypeInfo' => "DataType",
		'DataValue' => "DataValue",
		'LocationExtended' => "Location"
	);

	//initialize server
	$server = new SoapServer($wsdl_url, array("classmap" => $classmap, "cache_wsdl" => $wsdl_cache));
	//initialize webservice-class
	$server->setClass("DASense");
	$server->handle(); 
	
	


}
?>