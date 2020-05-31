<?php

/* We store authentication info here */
include_once("config.php");

/* Common functions are here for now... */
include_once("output.php");

$mysqli = new mysqli(MYSQL_HOSTNAME, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DBNAME);

/* check connection */
if (mysqli_connect_errno()) {
	printf("Connect failed: %s\n", mysqli_connect_error());
	exit();
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
	<title>Live Results</title>
	<link href="style.css" rel="stylesheet" type="text/css" />
	<!-- favicon -->
	<link rel="shortcut icon" href="images/favicon.png">

	<!-- Auto-refresh -->
	<meta http-equiv="refresh" content="120">
	</head>

	<body>
	<header>
	<img src="images/SageLogo.png" />
	<h1>Sage Orienteering Results</h1>
<?php
	if (isset($_GET['cmp']) && is_numeric($_GET['cmp'])) {
		$cid = $_GET['cmp'];
	}

	if (isset($cid) && !isset($_GET['select'])) {
		$query = "SELECT name FROM mopCompetition WHERE cid=" . $cid;
		if ($result = $mysqli->query($query)) {
			echo "<h2>" . $result->fetch_assoc()['name'] . "</h2>";
			$result->close();
		} else {
			printf("Error: %s\n", $mysqli->error);
		}
	}
?>
</header>
<?php
if (!isset($cid) || isset($_GET['select'])) {
	echo "<h2>Select Competition</h2>";
	echo "<section>";
	$query = "SELECT name, date, cid FROM mopCompetition ORDER BY date DESC";
	if ($result = $mysqli->query($query)) {
		echo '<ul>';
		while ($row = $result->fetch_assoc())
			echo '<li><a href="?cmp=' . $row['cid'] . '">' . $row['date'] . " - " . $row['name'] . '</a></li>';
		$result->close();
		echo '</ul>';
	} else {
		printf("Error: %s\n", $mysqli->error);
	}
	echo "</section>";
} else {
?>
	<aside>
	<a href="?select"><button>Select Competition</button></a>
<?php
	$query = "SELECT homepage FROM mopCompetition WHERE cid=" . $cid;
	if ($result = $mysqli->query($query)) {
		$homepage = $result->fetch_assoc()['homepage'];
		if (!empty($homepage)) {
			echo '<a href="' . $homepage . '"><button>Event Homepage</button></a>';
		}
		$result->close();
	} else {
		printf("Error: %s\n", $mysqli->error);
	}
?>
	<h3>Select Class</h3>
<?php
	$query = "SELECT id, name FROM mopClass WHERE cid=$cid ORDER BY ord";
	if ($result = $mysqli->query($query)) {
		echo "<ul>";
		while ($row = $result->fetch_assoc())
			echo '<li><a href="?cmp=' . $cid . '&cls=' . $row['id'] . '">' . $row['name'] . "</a></li>";
		echo "</ul>";
		$result->close();
	} else {
		printf("Error: %s\n", $mysqli->error);
	}
?>
	</aside>
<?php
	if (!isset($_GET['cls'])) {
		echo "<section><h3>Please select a class</h3></section>";
	} else if (!is_numeric($_GET['cls'])) {
		/* How did we get here? Someone putting in random get parameters? */
		echo "<p>Invalid Class</p>";
		exit();
	} else {
		/* We've selected a class, start setting up the page */
		echo '<section id="results-section">';
		$cls = $_GET['cls'];

		/* Get the name of the selected class and output it */
		$query = "SELECT name FROM mopClass WHERE cid='$cid' AND id='$cls'";
		if ($result = $mysqli->query($query)) {
			echo "<h3>" . $result->fetch_assoc()['name'] . "</h3>";

			$result->close();
		} else {
			printf("Error: %s\n", $mysqli->error);
		}

		/* Determine if we're running an normal event, a relay, or one with "patrols" - aka groups of people */
		$query = "SELECT max(leg) FROM mopTeamMember tm, mopTeam t WHERE tm.cid = '$cid' AND t.cid = '$cid' AND tm.id = t.id AND t.cls = $cls";
		if ($result = $mysqli->query($query)) {
			$numlegs = $result->fetch_row()[0];

			$result->close();
		} else {
			printf("Error: %s\n", $mysqli->error);
			exit();
		}

		if ($numlegs > 1) {
			/* We're running a relay */
			echo "<p>Fix-Me - Need to implement a relay...</p>";
		} else {
			/* We're running a normal race, determine the controls on the course */
			$control_list = "(";
			$controls = array();

			$query = "SELECT ctrl FROM mopClassControl WHERE cid=$cid AND id=$cls ORDER BY ord";
			if ($result = $mysqli->query($query)) {
				/* fetch object array */
				while ($row = $result->fetch_row()) {
					$control_list .= $row[0] . ",";
					$controls[] = $row[0];
				}

				/* Remove the tailing comma and add the closing brace */
				$control_list = substr($control_list, 0, -1) . ")";

				$result->close();
			} else {
				printf("Error: %s\n", $mysqli->error);
			}

			/* Get the OK or on course competitors */
			$query = "SELECT cmp.id AS id, cmp.name AS name, org.name AS team, cmp.rt AS time, cmp.st AS start, cmp.stat AS status FROM mopCompetitor cmp LEFT JOIN mopOrganization AS org ON cmp.org = org.id AND cmp.cid = org.cid WHERE cmp.cls = $cls AND cmp.cid = $cid AND cmp.stat <= 1 ORDER BY cmp.stat, cmp.rt ASC, cmp.st ASC, cmp.id";
			if ($result = $mysqli->query($query)) {
				/* Store the competitiors info in an array, add their ID's to an SQL query */
				$competitors = array();
				$competitor_ids = "(";
				while ($row = $result->fetch_assoc()) {
					$competitors[] = $row;
					$competitor_ids .= $row['id'] . ",";
				}
				/* Remove the tailing comma and add the closing brace */
				$competitor_ids = substr($competitor_ids, 0, -1) . ")";

				$result->free();
			} else {
				printf("Error: %s\n", $mysqli->error);
				exit();
			}

			/* Get the non-OK competitors */
			$query = "SELECT cmp.id AS id, cmp.name AS name, org.name AS team, cmp.rt AS time, cmp.st AS start, cmp.stat AS status FROM mopCompetitor cmp LEFT JOIN mopOrganization AS org ON cmp.org = org.id AND cmp.cid = org.cid WHERE cmp.cls = $cls AND cmp.cid = $cid AND cmp.stat > 1 ORDER BY cmp.stat, cmp.rt ASC, cmp.st ASC, cmp.id";
			if ($result = $mysqli->query($query)) {
				/* Store the competitors and IDs in different data structures */
				$competitors2 = array();
				$competitor_ids2 = "(";
				while ($row = $result->fetch_assoc()) {
					$competitors2[] = $row;
					$competitor_ids2 .= $row['id'] . ",";
				}
				/* Remove the tailing comma and add the closing brace */
				$competitor_ids2 = substr($competitor_ids2, 0, -1) . ")";

				$result->free();
			} else {
				printf("Error: %s\n", $mysqli->error);
				exit();
			}

			/* Find all the controls that OK people punched */
			$punches = array();
			$query = "SELECT rt, ctrl, id FROM mopRadio WHERE cid=$cid AND ctrl IN " . $control_list . " AND id IN " . $competitor_ids . " ORDER BY rt";
			if ($result = $mysqli->query($query)) {
				while ($entry = $result->fetch_assoc())
					$punches[] = $entry;
				$result->free();
			}

			/* Sort competitors and add split times to them */
			$competitors = organizeCompetitors( $competitors, $punches, $controls );

			/* Find all the controls that other people punched and add them to punches
			 * Done afterwards so we don't pollute the current standings of OK people
			 */
			$query = "SELECT rt, ctrl, id FROM mopRadio WHERE cid=$cid AND ctrl IN " . $control_list . " AND id IN " . $competitor_ids2 . " ORDER BY rt";
			if ($result = $mysqli->query($query)) {
				while ($entry = $result->fetch_assoc())
					$punches[] = $entry;
				$result->free();
			}
			$competitors2 = organizeCompetitors( $competitors2, $punches, $controls );

			/* setup our table */
			$now = ( time() - strtotime("today") ) * 10;
			echo "<div id='table-container' data-current-time='" . $now . "'>";
			echo '<table>';
			echo "<tr>";
			echo "<th>Place</th><th class='left-aligned'>Name<br />Club</th><th>Start Time</th>";
			writeSplitsHeader($controls);
			echo "</tr>";

			writeSplits( $competitors, count( $controls ) );
			writeSplits( $competitors2, count( $controls ) );

			echo "</table>";
			echo "</div>";
?>
			</section>
<?php
		} // number of legs
	} // class selected
} // select competition

/* Close SQL connection */
$mysqli->close();
if (isset($cid) && $cid > 0) { ?>
<section>
	<a href="/xml-export.php?cmp=<?php echo $cid; ?>">Download IOF XML (v3)</a>
</section>
<?php } ?>
</body>
</html>
