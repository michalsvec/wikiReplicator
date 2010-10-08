<?php

// I dont't want to copy that class here, because of new revisions, 
// so i include it here and use some of its function
//if(!class_exists('WikiImporter'))
//	require_once(dirname(__FILE__) . '/../includes/specials/StreamImport.php');
	
function pr($data) {
	echo "<pre>";
	print_r($data);
	echo "</pre>";
}
	
	
class ReplicatorSlave extends SpecialPage {
	
	var $dir;	//actual directory
	var $cfg;	// master config
	var $log;	// log file
	var $out;	// output file
	
	function __construct() {
		parent::__construct( 'ReplicatorSlave' );
		wfLoadExtensionMessages('ReplicatorSlave');

		$this->dir = dirname(__FILE__) . '/';
		$this->cfg = parse_ini_file($this->dir."slave.ini",true);
		$this->log = fopen($this->dir."log/log.txt", "a+");
		$this->out = fopen($this->dir."log/out.txt", "a+");
	}

	function __destruct() {
		fclose($this->log);
		fclose($this->out);
	}

	function logIt($str) {
		fwrite($this->log,date("Y-m-d H:i:s | ",mktime()).$str."\n");
	}

	/* function from php.net comments */
	function get_curl($url)	{
		$curl = curl_init();

		// Setup headers - I used the same headers from Firefox version 2.0.0.6
		// below was split up because php.net said the line was too long. :/
		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
		$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$header[] = "Cache-Control: max-age=0";
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: "; // browsers keep this blank.

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_REFERER, 'http://www.google.com');
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);

		$html = curl_exec($curl); // execute the curl command
		curl_close($curl); // close the connection

		return $html; // and finally, return $html
	}

	function dtbConnect($host, $user, $pass, $name) {

		$db = mysql_connect($host, $user, $pass);
		if(!$db) {
			$this->logIt("MySQL: Unable to connect to $host");
			return false;
		}
		else {
			$dbname = MySQL_Select_DB($name);
			if(!$dbname) {
				$this->logIt("MySQL: Unable to select database $name");
				return false;
			}
		}
		
		return $db;
	}

	function getDataXML($id) {
		global $wgOut;
		global $wgDBserver;
		global $wgDBname;
		global $wgDBuser;
		global $wgDBpassword;
		global $wgDBprefix;
		
		$url = $this->cfg['url'].'/index.php/Speciální:ReplicatorMaster/'.$id;
		$this->logIt("get_curl - $url");
		$masterXML = $this->get_curl($url);

		$xml = simplexml_load_string($masterXML, 'SimpleXMLElement');

		if(!$this->dtbConnect($wgDBserver, 
					$wgDBuser, 
					$wgDBpassword, 
					$wgDBname)) {
				$this->logIt("agent | unable to connect ".$this->cfg['database']['user']."@".$this->cfg['database']['server']);
				return;
			}

		@mysql_query("SET CHARACTER SET utf8");

        @mysql_query("SET NAMES UTF8");

        @mysql_query("SET character_set_results=utf8");

        @mysql_query("SET character_set_connection=utf8");

        @mysql_query("SET character_set_client=utf8");

		$insert = array();
		foreach ($xml as $table=>$rows) {
			
			$table = $wgDBprefix.$table;
			
			$exists = "show tables like '$table'";
			$query = mysql_query($exists);
			
			if(mysql_num_rows($query) == 0)	$this->logIt("agent | table $table doesn't exists");

			$insert[] = "TRUNCATE TABLE `$table`;\n";
			//logIt("agent | truncate $table");
			//mysql_query($truncate);

			foreach($rows as $row) {
				$colsArr = array();
				$valsArr = array();
				
				foreach($row as $key=>$val) {
					$colsArr[] = (string) $key;
					$valsArr[] = (string)  "'".mysql_real_escape_string(base64_decode($val))."'";
					
				}
				$colsStr = "";
				$valsStr = "";
				$colsStr = implode("," , $colsArr);
				$valsStr = implode("," , $valsArr);
				
				$insert[] = "REPLACE INTO $table ($colsStr) VALUES ($valsStr);";
			}
		}


		foreach($insert as $query) {
			fwrite($this->out,$query."\n");
			$res = mysql_query($query);	

			if(!$res) {
				$this->logIt("agent | cannot insert row");
				$this->logIt(mysql_error());
				$wgOut->addWikiText(mysql_error());
			}
		}
		$wgOut->addWikiText("dataXML import complete");
	}


	function execute( $par ) {
		global $wgOut,$wgRequest;

		$wgOut->addHtml("Starting import<br />");

		if(empty($par)) {
			$this->logIt("Empty parameter");
			$wgOut->addHtml("Empty parameter!!<br />");
			return;
		}
		
		$wgOut->addHtml("Importing dataXML<br />");
		$this->getDataXML($par);
	}
}


