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

/* Forward the request onwards */
// Extract headers
$password = '';
$cmpId = '';
foreach ($_SERVER as $header => $value) {
	if (strcasecmp($header, "http_competition") == 0)
	    $cmpId = (int)$value;
	if (strcasecmp($header, "http_pwd") == 0)
	    $password = $value;
}

$data = file_get_contents("php://input");

$opts = array('http' =>
array(
    'method' => 'POST',
    'header' => "competition: " . $cmpId . "\r\n"
    ."pwd: " . $password . "\r\n",
    'content' => $data,
    'timeout' => '60',
)
);

$context = stream_context_create($opts);
$url = 'https://bcoc2018.ca/results/zipupdate.php';
$result = file_get_contents($url, false, $context, -1, 40000);
error_log($result);

/* Start original */
$cmpId = checkHeaders();



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
