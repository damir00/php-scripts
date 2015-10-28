<?php

/*
Author: Damir Srpcic

Uses database created by:


CREATE TABLE IF NOT EXISTS `count` (
  `page` varchar(100) NOT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `views` (
  `page` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `date` date NOT NULL,
  UNIQUE KEY `page` (`page`,`ip`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

*/

//prepared statement helper functions
function execute_statement($conn,$query,$param_types,$params) {
	$st=$conn->prepare($query);
	//required to convert val to ref since 5.3
        $params2=array();
        foreach($params as $key => $value) {
		$params2[$key] = &$params[$key];
	}
	call_user_func_array(array($st,"bind_param"),array_merge(array($param_types),$params2));
	$st->execute();
	return $st;
}
function get_result($st) {
	$st->bind_result($res);
	$st->fetch();
	$st->close();
	return $res;
}
function get_affected_rows($st) {
	$rows=$st->affected_rows;
	$st->close();
	return $rows;
}

//adds a hit and returns current hit count for page
function addHit($conn,$page,$ip,$date) {
	//unique on (page,ip,date) enforces that duplicates are not inserted
	$affected_rows=get_affected_rows(execute_statement($conn,"INSERT into views (page,ip,date) VALUES (?,?,?)","sss",array($page,$ip,$date)));
	if($affected_rows>0) {
		//record was inserted, add hit count
		$st=execute_statement($conn,"SELECT count FROM count WHERE page=?","s",array($page));
		$st->bind_result($count);
		if($st->fetch()!=true) {
			$count=0;
		}
		$st->close();

		if($count==0) {
			//count record doesn't exist yet
			execute_statement($conn,"INSERT into count (page) VALUES (?)","s",array($page))->close();
			return 1;
		}
		else {
			//increment count record
			execute_statement($conn,"UPDATE count SET count=count+1 WHERE page=?","s",array($page))->close();
			return $count+1;
		}
	}
	return get_result(execute_statement($conn,"SELECT count FROM count WHERE page=?","s",array($page)));
}
//delete hits older than date
function cleanHits($conn,$date) {
	execute_statement($conn,"DELETE FROM views WHERE date < ?","s",array($date))->close();
}

function makeConnection() {
	$servername = "localhost";
	$username = "test";
	$password = "test";
	$dbname="counter";

	$conn = new mysqli($servername, $username, $password,$dbname);
	if($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	}
	return $conn;
}

if(isset($_GET["src"])) {
	header('Content-type: text/plain');
	echo file_get_contents("counter.php");
	die();
}


//quick categories
$pages=array(
	"home"		=> array("Homepage"),
	"books"		=> array("Harry Potta","PHP for beginners","MS office for dummies"),
	"games"		=> array("GTA15","COD16","MoW:DCS TNG 15 Xtra"),
	"hardware"	=> array("Deluxe mobo","Xenon CPU Ultra 2",""),
	"software"	=> array("Notepad Pro","Llama AV","PC Speed Booster")
);

//parse request
$p1=explode(",",$_GET["p"]);
$p=(defined($pages[$p1[0]]) ? $p1 : array("home","0"));
$cur_cat=$p1[0];
$cur_page=$pages[$cur_cat][$p1[1]];

if(!isset($cur_cat) || !isset($cur_page)) {
	$cur_cat="home";
	$cur_page=$pages[$cur_cat][0];
}

$client_ip=$_SERVER["REMOTE_ADDR"];
$date=date("Y-m-d");

//process database
$conn=makeConnection();
$cur_count=addHit($conn,$cur_cat,$client_ip,$date);
cleanHits($conn,$date);
$conn->close();

//render page
echo "<div>";
foreach($pages as $cat => $subpages) {
	echo "<div style=\"float:left;width:200px\">";
	echo "<h4>$cat</h4>";
	foreach($subpages as $i => $page) {
		echo "<h6><a href=\"?p=$cat,$i\">$page</a></h6>";
	}
	echo "</div>";
}
echo "</div>";
echo "<div style=\"float:normal;clear: both;\">&nbsp;</div>";


echo "<h1>$cur_cat</h1>";
echo "<h2>$cur_page</h2><h2>hits: $cur_count</h2>";

echo "<h3><a href=?src>Download source</a></h3>";


