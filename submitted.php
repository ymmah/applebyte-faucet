<?php

/**
 * @author Greedi
 * @copyright 2012
 */
function clean_input($instr) {
    if(get_magic_quotes_gpc()) {
        $str = stripslashes($instr);
    }
    return mysql_real_escape_string(strip_tags(trim($instr)));
}
//error_reporting(E_ALL);
include ('core/banned.php');
include_once ("core/wallet.php");
include_once ('templates/header.php');
//include ('core/dnsbl.php');

$donaddress = $don_faucet;
$don = $btclient->getbalance();

?>
<div class="row">
  <div class="span10">
    <center>
      <br />
      <?php
      $ip = $_SERVER['REMOTE_ADDR'];
      $challengeValue = $_POST['adscaptcha_challenge_field'];
      $responseValue = $_POST['adscaptcha_response_field'];
      $remoteAddress = $_SERVER["REMOTE_ADDR"];
      function ordinal($a)
      {
	$b = abs($a);
	$c = $b % 10;
	$e = (($b % 100 < 21 && $b % 100 > 4) ? 'th' : (($c < 4) ? ($c < 3) ? ($c < 2) ?
								    ($c < 1) ? 'th' : 'st' : 'nd' : 'rd' : 'th'));
	return $a . $e;
      }
      if (strtolower(ValidateCaptcha($adscaptchaID, $adsprivkey, $challengeValue, $responseValue, $remoteAddress)) == "true") {
	$isvalid = $btclient->validateaddress($_POST['ABY']);
	if ($isvalid['isvalid'] != '1') {
            echo srserr("Invalid Address: " . $_POST['ABY'] . " <a href='/index.php'>Go back</a>");
            echo "</center></div>";
            include ('templates/sidebar.php');
            include ('templates/footer.php');
            die();
	} else {
	    $ltcaddress = clean_input($_POST['ABY']);
	    $command = "SELECT 1 FROM dailyltc WHERE ltcaddress='$ltcaddress' OR ip='$ip'";
	    $q = mysql_query($command);
	    $rows = mysql_num_rows($q);
	    if ($rows > 0) {
		echo srserr("There is already an entry for you in this 24 hour round, please come back tomorrow.");
                echo "</center></div>";
                include ('templates/sidebar.php');
                include ('templates/footer.php');
		die();
	    }
	    // Compare first three octets of local IP with IPs from last hour
	    $command = "SELECT `ip` FROM dailyltc WHERE `time` > UNIX_TIMESTAMP(NOW() - INTERVAL 1 HOUR);";
	    $q = mysql_query($command);
	    $ipArray = explode(".", $ip, 4);
	    array_pop($ipArray);
	    if ($q) {
	        while($row = mysql_fetch_array($q)) {
		    $exArray = explode(".", $row[0], 4);
		    array_pop($exArray);
		    if ($ipArray == $exArray) {
			echo srserr("An entry from this subnet was received within the last hour. Please try after an hour has passed.");
                        echo "</center></div>";
                        include ('templates/sidebar.php');
                        include ('templates/footer.php');
                        die();
		    }
		}
	    }

            function octetCompare($remote, $lowLimit, $highLimit) {
                $remoteArray = explode(".", $remote, 4);
                $lowArray = explode(".", $lowLimit, 4);
                $highArray = explode(".", $highLimit, 4);
                return rCompare($remoteArray, $lowArray, $highArray);
            }

            function rCompare($remote, $low, $high) {
		if (($remote[0] > $low[0]) && ($remote[0] < $high[0])) {
                    return true;
                }
		if (($remote[0] == $low[0]) && ($remote[0] < $high[0])) {
                    for ($i = 1; $i < count($high); $i++) {
			$high[$i] = 255;
		    }
                }
		if (($remote[0] > $low[0]) && ($remote[0] == $high[0])) {
                    for ($i = 1; $i < count($low); $i++) {
                        $low[$i] = 0;
                    }
                }
                if (($remote[0] == $low[0]) || ($remote[0] == $high[0])) {
                    if (count($remote) == 1) {
                        return true;
                    } else {
                        array_shift($remote);
                        array_shift($low);
                        array_shift($high);
                        return rCompare($remote, $low, $high);
                    }
                }
                return false;
            }
            
            for ($i = 0; $i < count($bannedIPs); $i++) {
                if (octetCompare($ip, $bannedIPs[$i][0], $bannedIPs[$i][1])) {
                    echo srserr("Your subnet is banned from using this faucet.");
                    echo "</center></div>";
                    include ('templates/sidebar.php');
                    include ('templates/footer.php');
                    die();
                }
            }

	    $time = time();
            mysql_query("INSERT INTO dailyltc (ltcaddress, ip, time) SELECT * FROM (SELECT '$ltcaddress', '$ip', '$time') AS tmp
                WHERE NOT EXISTS (SELECT ip FROM dailyltc WHERE ip = '$ip') LIMIT 1;") or die(mysql_error());
            mysql_query("INSERT INTO subtotal (ltcaddress, ip) VALUES('$ltcaddress', '$ip' ) ") or die(mysql_error());
	    $coins_in_account = $btclient->getbalance();
       	    if ($coins_in_account > $payout) {
	        $btclient->sendtoaddress($ltcaddress,$payout);
		echo srsnot("Congratulations, you have now been sent " . $payout . " ABY. Come back tomorrow for the next round.");
		echo "</center></div>";
                include ('templates/sidebar.php');
                include ('templates/footer.php');
		die(); 
	    } else {
	        echo srserr("Looks like we haven't got enough donations to pay out. The faucet will continue once the faucet has received more funds.");
                echo "</center></div>";
                include ('templates/sidebar.php');
                include ('templates/footer.php');
                die();
	    }
        }
      } else { // Wrong answer, you may display a new AdsCaptcha and add an error message
	echo srserr("Invalid CAPTCHA. <a href='/index.php'>Go back</a>");
      }
      ?>
    </center>
  </div>
  <?php
  include ('templates/sidebar.php');
  include ('templates/footer.php');
  ?>
