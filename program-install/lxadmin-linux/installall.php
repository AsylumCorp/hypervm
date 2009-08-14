<?php 

include_once "htmllib/lib/include.php";

$installcomp['mail'] = array("vpopmail", "courier-imap-toaster", "courier-authlib-toaster", "qmail", "lxwebmail","safecat","httpd","spamassassin","ezmlm-toaster","autorespond-toaster");
$installcomp['web'] = array("lxawstats", "httpd", "pure-ftpd");
$installcomp['dns'] = array("bind","bind-chroot");
$installcomp['database'] = array("mysql");


function install_general_mine($value)
{
	$value = implode(" ", $value);
	print("Installing $value ....\n");
	system("PATH=\$PATH:/usr/sbin yum -y install $value");
}                                                                                                                


function installcomp_mail()
{
	system("pear upgrade pear");
	system("pear upgrade archive_tar ");
	system("pear upgrade structures_graph ");
	system("pear install log");
	//system("pecl install sqlite");
}

install_main();
//os_fix_some_permissions();


function install_main()
{

	global $installcomp;
	global $argv;
	$comp = array("web", "mail", "dns", "database");

	$list = parse_opt($argv);

	if ($list['server-list']) {
		$serverlist = implode(",", $list['server-list']);
	} else {
		$serverlist = $comp;
	}

	foreach($comp as $c) {
		flush();
		if (array_search($c, $serverlist) !== false) {
			print("Installing $c Components....");
			$req = $installcomp[$c];
			$func = "installcomp_$c";
			if (function_exists($func)) {
				$func();
			}
			install_general_mine($req);
			print("\n");
		}
	}

	$pattern = "Include /etc/httpd/conf/lxadmin/lxadmin.conf";
	$file = "/etc/httpd/conf/httpd.conf";
	$comment = "#Lxadmin";
	addLineIfNotExist($file, $pattern, $comment);
	mkdir("/etc/httpd/conf/lxadmin/");
	$dir_path=dirname(__FILE__);
	copy("$dir_path/lxadmin.conf", "/etc/httpd/conf/lxadmin/lxadmin.conf");
	touch("/etc/httpd/conf/lxadmin/virtualhost.conf");
	touch("/etc/httpd/conf/lxadmin/webmail.conf");
	touch("/etc/httpd/conf/lxadmin/init.conf");
	mkdir("/etc/httpd/conf/lxadmin/forward/");
	touch("/etc/httpd/conf/lxadmin/forward/forwardhost.conf");

	$pattern='include "/etc/lxadmin.named.conf";';
	$file = "/var/named/chroot/etc/named.conf";
	$comment = "//Lxadmin";
	addLineIfNotExist($file, $pattern, $comment);
	touch("/var/named/chroot/etc/lxadmin.named.conf");
	chown("/var/named/chroot/etc/lxadmin.named.conf", "named");

}

function addLineIfNotExist($filename, $pattern, $comment)
{
	$cont = lfile_get_contents($filename);

	if(!preg_match("+$pattern+i", $cont)) {
		file_put_contents($filename, "\n$comment \n\n", FILE_APPEND);
		file_put_contents($filename, $pattern, FILE_APPEND);
		file_put_contents($filename, "\n\n\n", FILE_APPEND);
	} else {
		print("Pattern '$pattern' Already present in $filename\n");
	}



}


function checkIfYes($arg)
{
	return ($arg == 'y' || $arg == 'yes' || $arg == 'Y' || $arg == 'YES' );
}


function getAcceptValue($soft)
{
		print( "Do you want me to install $soft Components? (YES/no):");
		flush();
		$argq = fread(STDIN, 5); 
		$arg = trim($argq);
		if (!$arg) {
			$arg = 'yes';
		}
		return $arg;
}

