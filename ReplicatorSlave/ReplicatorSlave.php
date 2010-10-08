<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/ReplicatorSlave/ReplicatorSlave.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'ReplicatorSlave',
	'author' => 'Michal Svec',
	'url' => 'http://michalsvec.cz',
	'description' => 'ReplicatorSlave replicates wiki to another server automatically',
	'descriptionmsg' => 'myextension-desc',
	'version' => 0.1,
);
 
$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['ReplicatorSlave'] = $dir . 'ReplicatorSlave_body.php'; # Tell MediaWiki to load the extension body.
$wgExtensionMessagesFiles['ReplicatorSlave'] = $dir . 'ReplicatorSlave.i18n.php';
//$wgExtensionAliasesFiles['ReplicatorSlave'] = $dir . 'ReplicatorSlave.alias.php';
$wgSpecialPages['ReplicatorSlave'] = 'ReplicatorSlave'; # Let MediaWiki know about your new special page.

?>
