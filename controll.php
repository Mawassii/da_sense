<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<!---  
The control part of the testsuit for the JSON parser
Author: Jan Meub
Version: 1.0
 -->

<html><head><title>If you see this you escaped the frameset</title></head><body>
<h2>Kontrollen:</h2>

<form action="smartphones.php" method="post" target="smartphones">
<textarea name="json" cols="40" rows="11">
{"deviceID" : "TEST",
 "values" : [{
	"timestamp" : 1,
	"leq" : 0,
	"longitude" : 0,
	"latitude" : 0,
	"altitude" : 0,
	"provider" : "GPS",
	"accuracy" : 0,
	"countSamples" : 0 
}]}	
</textarea>
<center><input type="submit" value="JSON an smartphones.php senden" onclick='setTimeout("parent.show.location.href = parent.show.location.href;",1000)' /></center>
</form>

<form action="show.php" target="show" method="post">
	<p>Passwort zum L&ouml;schen aller Daten:  <input name="deleteAll" type="text" size="5" /> <input type="submit" value="senden" /></p>
</form>

<h2>R&uuml;ckmeldungen</h2>

</body></html> 