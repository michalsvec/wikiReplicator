<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/ReplicatorMaster/ReplicatorMaster.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'ReplicatorMaster',
	'author' => 'Michal Svec',
	'url' => 'http://misa.ufb.cz',
	'description' => 'ReplicatorMaster replicates wiki to another server automatically',
	'descriptionmsg' => 'myextension-desc',
	'version' => 0.1,
);
 
$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['ReplicatorMaster'] = $dir . 'ReplicatorMaster_body.php'; # Tell MediaWiki to load the extension body.
$wgExtensionMessagesFiles['ReplicatorMaster'] = $dir . 'ReplicatorMaster.i18n.php';
//$wgExtensionAliasesFiles['ReplicatorMaster'] = $dir . 'ReplicatorMaster.alias.php';
$wgSpecialPages['ReplicatorMaster'] = 'ReplicatorMaster'; # Let MediaWiki know about your new special page.

?>
