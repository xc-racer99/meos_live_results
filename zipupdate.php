<?php
  /*
  Copyright 2013 Melin Software HB

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

      http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.
  */

include_once("update-common.php");

$cmpId = checkHeaders();

$data = file_get_contents("php://input");

if ($data[0] == 'P') { //Zip starts with 'PK'
    $fn = tempnam('/tmp', 'meos');
    if ($fn) {
	$f = fopen ($fn, 'wb');
	fwrite($f, $data);
	$zip = zip_open($fn);
	unlink ($fn);  // even if fopen failed, because tempnam created the file
    }

    if ($zip) {
	if($zip_entry = zip_read($zip)) {
	    $data = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
	    $update = new SimpleXMLElement($data);
	}
	zip_close($zip);
    }
    @fclose($f);

    if (!isset($update))
	returnStatus('ERROR');
} else {
    $update = new SimpleXMLElement($data);
}

processXML($update, $cmpId);

/* Close SQL Connection */
$mysqli->close();
?>
