<?php

// current api request
$request = array_merge($_GET, $_POST);

define('DB_CLASSPATH','classes/db/');
define('DB_SUBFOLDER',((isset($request['call']))?$request['call'].'/':''));

// register custom autoload function for different controller contexts
function __custom_autoload($classname){
	require_once DB_CLASSPATH.DB_SUBFOLDER.$classname.'.php';
}
spl_autoload_register('__custom_autoload');

// check type of call -> assign corresponding controller
if(isset($request['call'])){
	
	switch($request['call']){
		
		// Controller for analyse the data
		case 'analysis':
			$controller = new AnalysisController($request);
			break;
		
		// Controller for user/account management
		case 'account':
			$controller = new AccountController($request);
			break;
			
		// Controller for data acquisition
		case 'input':
			$controller = new AcquisitionController($request);
			break;
		
		default: 
			die("No corresponding controller found!");
	}
	
}else{

	// default api controller for standard select and modify calls
	$controller = new ArgumentController($request);

}

$controller->doQuery();
print $controller->getResults();
	
?>