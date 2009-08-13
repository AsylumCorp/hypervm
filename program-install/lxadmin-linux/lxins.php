<?php

// PHp4, without the lxlabs infrastructure...

include_once "../install_common.php";
lxins_main();


function lxins_main()
{
	global $argv;

	$opt = parse_opt($argv);


	$dir_name=dirname(__FILE__);
	$installtype = $opt['install-type'];

	
	$dbroot = isset($opt['db-rootuser'])? $opt['db-rootuser']: "root";
	$dbpass = isset($opt['db-rootpassword'])? $opt['db-rootpassword']: "";

	if (!$dbpass) {
		//$dbpass = slave_get_db_pass("lxadmin");
	}

	$osversion = find_os_version();

	$arch = `arch`;
	$arch = trim($arch);
	if ($arch === 'x86_64') {
		print("Lxadmin does not support 64 bit at present\n");
		exit;
	}

	if (!char_search_beg($osversion, "centos") && !char_search_beg($osversion, "rhel")) {
		print("Lxadmin is only supported on centos 5, rhel 5\n");
		exit;
	}

	if(file_exists("/usr/local/lxlabs/lxadmin")) {
		print("Lxadmin is installed do you wish to continue?(No/Yes):\n");
		flush();
		$stdin = fopen('php://stdin','r');
		$argq = fread($stdin, 5);
		$arg=trim($argq);
		if(!($arg=='y' ||$arg=='yes'||$arg=='Yes'||$arg=='Y'||$arg=='YES')) {
			print("Exiting.....\n");
			exit;
		}
	}

	/*
	$file = "http://download.lxlabs.com/download/update/$osversion/headers/header.info";
	$cont = @file_get_contents($file);
	if (!$cont) {
		print("This OS is not suported at this moment.... Please contact our Support personnel\n");
		exit;
	}
*/


	system("groupadd nogroup");
	system("useradd nouser -g nogroup -s '/sbin/nologin'");
	system("groupadd lxlabs");
	system("useradd lxlabs -g lxlabs -s '/sbin/nologin'");
	install_yum_repo($osversion);


	exec("rpm -e --nodeps sendmail");
	exec("rpm -e --nodeps exim");
	exec("rpm -e --nodeps sendmail vsftpd postfix vpopmail qmail lxphp lxzend pure-ftpd imap > /dev/null 2>&1");


	$package = array("php-mysql", "which", "gcc-c++", "php-imap", "php-pear", "php-devel", "lxlighttpd", "httpd", "mod_ssl", "zip","unzip","lxphp", "mysql", "mysql-server",  "mysqlclient10", "lxzend","curl","autoconf","automake","libtool", "bogofilter", "gcc", "cpp", "openssl097a", "pure-ftpd");

	$list = implode(" ", $package);
	while (true) {
		//exec("up2date --nosig $list", $output, $return_value);
		print("Installing packages $list...\n");
		system("PATH=\$PATH:/usr/sbin yum -y install $list", $return_value);
		if (file_exists("/usr/local/lxlabs/ext/php/php")) {
			break;
		} else {
			print("Up2date Gave Error... Trying Again...\n");
		}
	}


	system("mkdir -p /usr/local/lxlabs/lxadmin");
	chdir("/usr/local/lxlabs/lxadmin");
	system("mkdir -p /usr/local/lxlabs/lxadmin/log");
	@ unlink("lxadmin-current.zip");
	system("wget http://download.lxlabs.com/download/lxadmin/production/lxadmin/lxadmin-current.zip");
	system("unzip -oq lxadmin-current.zip", $return); 

	if ($return) {
		print("Unzipping the core Failed.. Most likely it is corrupted. Please contact the support personnel\n");
		exit;
	}

	unlink("lxadmin-current.zip");
	system("chown -R lxlabs:lxlabs /usr/local/lxlabs/");



	chdir("/usr/local/lxlabs/lxadmin/httpdocs/");

	system("service mysqld start");


	if ($installtype !== 'slave') {
		check_default_mysql($dbroot, $dbpass);
	}
	$mypass = password_gen();


	system("/usr/local/lxlabs/ext/php/php $dir_name/installall.php");
	our_file_put_contents("/etc/sysconfig/spamassassin", "SPAMDOPTIONS=\" -v -d -p 783 -u lxpopuser\"");
	//system("/etc/init.d/spamassassin restart >> lxadmin_installlog.txt  2>&1 &");

	print("Creating Vpopmail database...\n");
	system("sh $dir_name/vpop.sh $dbroot \"$dbpass\" lxpopuser $mypass");


	//@ unlink("/usr/local/lxlabs/lxadmin/bin/install/create.php");


	#system("/sbin/chkconfig --add lxadmin");
	#system("/sbin/chkconfig lxadmin on");
	//system("cp -r /usr/local/frontpage/version5.0/apache2/.libs/mod_frontpage.so /etc/httpd/modules/");
	system("chmod -R 755 /var/log/httpd/");
	system("chmod -R 755 /var/log/httpd/fpcgisock >/dev/null 2>&1");
	system("mkdir -p /var/log/lxadmin/");
	system("mkdir -p /var/log/news");
	system("ln -sf /var/qmail/bin/sendmail /usr/sbin/sendmail");
	system("ln -sf /var/qmail/bin/sendmail /usr/lib/sendmail");

     

	system("echo `hostname` > /var/qmail/control/me");
	system("service qmail restart >/dev/null 2>&1 &");
	system("service courier-imap restart >/dev/null 2>&1 &");

	/*
	$fdata = our_file_get_contents("/etc/httpd/conf/httpd.conf");
	if(!preg_match('/mod_frontpage.so/i', $fdata)) {
		$modadd="LoadModule frontpage_module modules/mod_frontpage.so";
		//apend to file
		our_file_put_contents("/etc/httpd/conf/httpd.conf","\n\n", true);
		our_file_put_contents("/etc/httpd/conf/httpd.conf", $modadd, true);
		our_file_put_contents("/etc/httpd/conf/httpd.conf","\n\n", true);
		system("/etc/init.d/httpd restart > /dev/null 2>&1 &");
	}
*/

	$dbfile="/home/lxadmin/httpd/webmail/horde/scripts/sql/create.mysql.sql";
	if(file_exists($dbfile)) {
		if($dbpass == "") {
			system("mysql -u $dbroot  <$dbfile");
		} else {
			system("mysql -u $dbroot -p$dbpass <$dbfile");
		}
	}
	system("mkdir -p /home/lxadmin/httpd");
	chdir("/home/lxadmin/httpd");
	@ unlink("skeleton-disable.zip");
	//system("wget http://download.lxlabs.com/download/lxadmin/production/lxasource/skeleton-disable.zip");
    //system("unzip -oq skeleton-disable.zip"); 
	//unlink("skeleton-disable.zip");
	system("chown -R lxlabs:lxlabs /home/lxadmin/httpd");
    system("/etc/init.d/lxadmin restart >/dev/null 2>&1 &");
	chdir("/usr/local/lxlabs/lxadmin/httpdocs/");
	system("/usr/local/lxlabs/ext/php/php /usr/local/lxlabs/lxadmin/bin/install/create.php --install-type=$installtype --db-rootuser=$dbroot --db-rootpassword=$dbpass");
	system("/script/centos5-postupgrade");

	print("Congratuations. Lxadmin has been installed succesfully on your server as $installtype \n");
	if ($installtype === 'master') {
		print("You can connect to the server at https://<ip-address>:7777 or http://<ip-address>:7778\n");
		print("Please note that first is secure ssl connection, while the second is normal one.\n");
		print("The login and password are 'admin' 'admin'. After Logging in, you will have to change your password to something more secure\n");
		print("We hope you will find managing your hosting with Lxadmin refreshingly pleasurable, and also we wish you all the success on your hosting venture\n");
		print("Thanks for choosing Lxadmin to manage your hosting, and allowing us to be of service\n");
	} else {
		print("You should open the port 7779 on this server, since this is used for the communication between master and slave\n");
		print("To access this slave, to go admin->servers->add server, give the ip/machine name of this server. The password is 'admin'. The slave will appear in the list of slaves, and you can access it just like you access localhost\n\n");
	}

}

