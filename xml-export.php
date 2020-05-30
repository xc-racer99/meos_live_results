<?php
include_once('functions.php');

$link = ConnectToDB();

if (isset($_GET['cmp']) && is_numeric($_GET['cmp']))
	$cmpId = $_GET['cmp'];
else
	$cmpId = 0;

if ($cmpId <= 0) {
	header('Content-type: text/html;charset=utf-8');
	die("<!DOCTYPE html><html><body><p>Invalid competition specified</p></body></html>");
}

$xml = new SimpleXMLElement(<<<EOT
<?xml version="1.0" encoding="utf-8" ?>
<ResultList xmlns="http://www.orienteering.org/datastandard/3.0"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	iofVersion="3.0"
	creator="Sage Live Results">
</ResultList>
EOT
);

$event = $xml->addChild("Event");

$sql = "SELECT name FROM mopCompetition WHERE cid = '$cmpId'";
$res = $link->query($sql);

if ($r = $res->fetch_assoc()) {
	$event->addChild("Name", $r['name']);

	header('Content-type: application/xml');
	header('Content-Disposition: attachment; filename="' . $r['name']  . '.xml"');
	$res->close();
} else {
	die("<!DOCTYPE html><html><body><p>Invalid competition specified</p></body></html>");
}

$sql = "SELECT id, name FROM mopClass WHERE cid = '$cmpId'";
$res = $link->query($sql);

while ($r = $res->fetch_assoc()) {
	$pos = 1;
	$classResult = $xml->addChild("ClassResult");
	$class = $classResult->addChild("Class");
	$class->addChild("Id", $r['id']);
	$class->addChild("Name", $r['name']);

	$cls = $r['id'];
	$sql2 = "SELECT id, name, org, stat, st, rt FROM mopCompetitor WHERE cid = '$cmpId' AND cls = '$cls' ORDER BY rt ASC";
	$res2 = $link->query($sql2);

	while ($r2 = $res2->fetch_assoc()) {
		$personResult = $classResult->addChild("PersonResult");

		// Person
		$person = $personResult->addChild("Person");
		$person->addChild("Id", $r2['id']);
		$name = $person->addChild("Name");
		$names = explode(' ', $r2['name'], 2);

		if (count($names) == 2) {
			$name->addChild("Family", $names[1]);
			$name->addChild("Given", $names[0]);
		} else {
			$name->addChild("Family");
			$name->addChild("Given", $r2['name']);
		}

		// Organisation
		$sql3 = "SELECT id, name FROM mopOrganization WHERE cid = '$cmpId'";
		$res3 = $link->query($sql3);
		if ($r3 = $res3->fetch_assoc()) {
			$organisation = $personResult->addChild("Organisation");
			$organisation->addChild("Id", $r3['id']);
			$organisation->addChild("Name", $r3['name']);
		}
		$res3->close();

		// Result
		$result = $personResult->addChild("Result");
		// TODO - could add StartTime/FinishTime here, but needs a date
		$result->addChild("Time", $r2['rt'] / 10);

		if ($r2['stat'] == 1) {
			$result->addChild("Position", $pos++);
		}

		switch ($r2['stat']) {
		case 0:
			$result->addChild("Status", "Active");
			break;
		case 1:
			$result->addChild("Status", "OK");
			break;
		case 20:
			$result->addChild("Status", "DidNotStart");
			break;
		case 21:
			$result->addChild("Status", "Cancelled");
			break;
		case 3:
			$result->addChild("Status", "MissingPunch");
			break;
		case 4:
			$result->addChild("Status", "DidNotFinish");
			break;
		case 5:
			$result->addChild("Status", "Disqualified");
			break;
		case 6:
			$result->addChild("Status", "OverTime");
			break;
		case 99:
			$result->addChild("Status", "DidNotEnter");
			break;
		default:
			$result->addChild("Status", "Inactive");
			break;
		}

		$id = $r2['id'];
		$sql3 = "SELECT ctrl, rt FROM mopRadio WHERE cid = '$cmpId' AND id = '$id' ORDER BY rt ASC";
		$res3 = $link->query($sql3);
		while ($r3 = $res3->fetch_assoc()) {
			$splitTime = $result->addChild("SplitTime");
			$splitTime->addChild("ControlCode", $r3['ctrl']);
			$splitTime->addChild("Time", $r3['rt'] / 10);
		}
		$res3->close();
	}
	$res2->close();
}
$res->close();

$link->close();

echo $xml->asXml();
