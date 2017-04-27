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

include_once("config.php");

$mysqli = new mysqli(MYSQL_HOSTNAME, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DBNAME);

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

/** Update or add a record to a table. */
function updateTable($table, $cid, $id, $sqlupdate) {
    global $mysqli;
    
    $ifc = "cid='$cid' AND id='$id'";
    $res = $mysqli->query("SELECT id FROM `$table` WHERE $ifc");

    if ($res->num_rows > 0) {
	$sql = "UPDATE `$table` SET $sqlupdate WHERE $ifc";
    }
    else {
	$sql = "INSERT INTO `$table` SET cid='$cid', id='$id', $sqlupdate";
    }

    mysqli_free_result($res);
    //print "$sql\n";
    $mysqli->query($sql);
}

/** Update a link with outer level over legs and other level over fieldName (controls, team members etc)*/
function updateLinkTable($table, $cid, $id, $fieldName, $encoded) {
    global $mysqli;
    $sql = "DELETE FROM $table WHERE cid='$cid' AND id='$id'";
    $mysqli->query($sql);
    $legNumber = 1;
    $legs = explode(";", $encoded);
    foreach($legs as $leg) {
	$runners = explode(",", $leg);
	foreach($runners as $key => $runner) {
	    $sql = "INSERT INTO $table SET cid='$cid', id='$id', leg=$legNumber, ord=$key, $fieldName=$runner";
	    //print "$sql \n";
	    $mysqli->query($sql);
	}
	$legNumber++;
    }
}

/** Remove all data from a table related to an event. */
function clearCompetition($cid) {
    global $mysqli;
    
    $tables = array(0=>"mopControl", "mopClass", "mopOrganization", "mopCompetitor",
                    "mopTeam", "mopTeamMember", "mopClassControl", "mopRadio");

    foreach($tables as $table) {
	$sql = "DELETE FROM $table WHERE cid=$cid";
	$mysqli->query($sql);
    }
}

/** Update control table */
function processCompetition($cid, $cmp) {
    global $mysqli;
    $name = $mysqli->real_escape_string($cmp);
    $date = $mysqli->real_escape_string($cmp['date']);
    $organizer = $mysqli->real_escape_string($cmp['organizer']);
    $homepage = $mysqli->real_escape_string($cmp['homepage']);

    $sqlupdate = "name='$name', date='$date', organizer='$organizer', homepage='$homepage'";
    updateTable("mopCompetition", $cid, 1, $sqlupdate);
}

/** Update control table */
function processControl($cid, $ctrl) {
    global $mysqli;
    $id = $mysqli->real_escape_string($ctrl['id']);
    $name = $mysqli->real_escape_string($ctrl);
    $sqlupdate = "name='$name'";
    updateTable("mopControl", $cid, $id, $sqlupdate);
}

/** Update class table */
function processClass($cid, $cls) {
    global $mysqli;
    $id = $mysqli->real_escape_string($cls['id']);
    $ord = $mysqli->real_escape_string($cls['ord']);
    $name = $mysqli->real_escape_string($cls);
    $sqlupdate = "name='$name', ord='$ord'";
    updateTable("mopClass", $cid, $id, $sqlupdate);

    if (isset($cls['radio'])) {
	$radio = $mysqli->real_escape_string($cls['radio']);
	updateLinkTable("mopClassControl", $cid, $id, "ctrl", $radio);
    }
}

/** Update organization table */
function processOrganization($cid, $org) {
    global $mysqli;
    $id = $mysqli->real_escape_string($org['id']);
    $name = $mysqli->real_escape_string($org);
    $sqlupdate = "name='$name'";
    updateTable("mopOrganization", $cid, $id, $sqlupdate);
}

/** Update competitor table */
function processCompetitor($cid, $cmp) {
    global $mysqli;
    $base = $cmp->base;
    $id = $mysqli->real_escape_string($cmp['id']);

    $name = $mysqli->real_escape_string($base);
    $org = (int)$base['org'];
    $cls = (int)$base['cls'];
    $stat = (int)$base['stat'];
    $st = (int)$base['st'];
    $rt = (int)$base['rt'];


    $sqlupdate = "name='$name', org=$org, cls=$cls, stat=$stat, st=$st, rt=$rt";

    if (isset($cmp->input)) {
	$input = $cmp->input;
	$it = (int)$input['it'];
	$tstat = (int)$input['tstat'];
	$sqlupdate.=", it=$it, tstat=$tstat";
    }

    updateTable("mopCompetitor", $cid, $id, $sqlupdate);
    if (isset($cmp->radio)) {
	$sql = "DELETE FROM mopRadio WHERE cid='$cid' AND id='$id'";
	$mysqli->query($sql);
	$radios = explode(";", $cmp->radio);
	foreach($radios as $radio) {
	    $tmp = explode(",", $radio);
	    $radioId = (int)$tmp[0];
	    $radioTime = (int)$tmp[1];
	    $sql = "REPLACE INTO mopRadio SET cid='$cid', id='$id', ctrl='$radioId', rt='$radioTime'";
	    $mysqli->query($sql);
	}
    }
}

/** Update team table */
function processTeam($cid, $team) {
    global $mysqli;
    $base = $team->base;
    $id = $mysqli->real_escape_string($team['id']);

    $name = $mysqli->real_escape_string($base);
    $org = (int)$base['org'];
    $cls = (int)$base['cls'];
    $stat = (int)$base['stat'];
    $st = (int)$base['st'];
    $rt = (int)$base['rt'];

    $sqlupdate = "name='$name', org=$org, cls=$cls, stat=$stat, st=$st, rt=$rt";
    updateTable("mopTeam", $cid, $id, $sqlupdate);

    if (isset($team->r)) {
	updateLinkTable("mopTeamMember", $cid, $id, "rid", $team->r);
    }
}

/** MOP return code. */
function returnStatus($stat) {
    die('<?xml version="1.0"?><MOPStatus status="'.$stat.'"></MOPStatus>');
}

function checkHeaders() {
    // Extract headers
    $password = '';
    $cmpId = '';
    foreach ($_SERVER as $header => $value) {
	if (strcasecmp($header, "http_competition") == 0)
	    $cmpId = (int)$value;
	if (strcasecmp($header, "http_pwd") == 0)
	    $password = $value;
    }

    if (!($cmpId > 0)) {
	returnStatus('BADCMP');
    }

    if ($password != MEOS_PASSWORD) {
	returnStatus('BADPWD');
    }

    return $cmpId;
}

/** Common code to process the XML file */
function processXML($update, $cmpId) {
    if ($update->getName() == "MOPComplete")
	clearCompetition($cmpId);
    else if ($update->getName() != "MOPDiff")
    die("Unknown data");

    foreach ($update->children() as $d) {
	if ($d->getName() == "cmp")
	    processCompetitor($cmpId, $d);
	else if ($d->getName() == "tm")
	processTeam($cmpId, $d);
	else if ($d->getName() == "cls")
	processClass($cmpId, $d);
	else if ($d->getName() == "org")
	processOrganization($cmpId, $d);
	else if ($d->getName() == "ctrl")
	processControl($cmpId, $d);
	else if ($d->getName() == "competition")
	processCompetition($cmpId, $d);
    }

    returnStatus('OK');
}

?>
