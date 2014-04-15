<?php

/**
 *  Developped with love by Nicolas Devenet <nicolas[at]devenet.info>
 *  Code hosted on github.com/nicolabricot
 */

error_reporting(0);

// constants to be used
define('VERSION', '1.2.0-dev');
define('URL_DAY', 'day');
define('PRIVATE_FOLDER', './private');
define('SETTINGS_FILE', PRIVATE_FOLDER.'/settings.json');
define('CALENDAR_FILE', PRIVATE_FOLDER.'/calendar.json');

// load settings from file
if (file_exists(SETTINGS_FILE)) {
	$settings = json_decode(file_get_contents(SETTINGS_FILE));

	define('TITLE', $settings->title);
	define('YEAR', $settings->year);
	
	// is it a private calendar?
	if (isset($settings->passkey) && !empty($settings->passkey)) { define('PASSKEY', $settings->passkey); }
	
	// do the user want an other background?
	if (isset($settings->background) && $settings->background == 'alternate') { define('ALTERNATE_BACKGROUND', TRUE); }

	// want to add disqus thread?
	if (isset($settings->disqus_shortname) && !empty($settings->disqus_shortname)) { define('DISQUS', $settings->disqus_shortname); }
}
else { die('<!doctype html><html><head><title>Advent Calendar</title><style>body{width:600px;margin:50px auto 20px;}</style></head><body><div style="font-size:30px;"><strong>Oups!</strong> Settings file not found.</div><div><p>Edit <code>private/settings.example.json</code> to personnalize title and year and rename it <code>settings.json</code>.</p><p>If it is not already done, put your photos in the <code>private/</code> folder, and name them with the number of the day you want to illustrate.</p></div></body></html>'); }

// is the directory writable ?
if (!is_writable(realpath(dirname(__FILE__)))) die('<div><strong>Oups!</strong> Application does not have the right to write in its own directory <code>'.realpath(dirname(__FILE__)).'</code>.</div>');
// is the private folder already created? yes, with a .htaccess file
/*if (!is_dir(PRIVATE_FOLDER)) { mkdir(PRIVATE_FOLDER,0705); chmod($_CONFIG['data'],0705); }*/
// are photos deny from web access? [just in case]
if (!is_file(PRIVATE_FOLDER.'/.htaccess')) { file_put_contents(PRIVATE_FOLDER.'/.htaccess', 'Deny from all'); }
if (!is_file(PRIVATE_FOLDER.'/.htaccess')) die('<div><strong>Oups!</strong> Application does not have the right to write in its own directory <code>'.realpath(dirname(__FILE__)).'</code>.</div>');

/*
 *  Core classes
 */
abstract class Image {
	function get($day) {
		// check if we can display the request photo
		if (Advent::acceptDay($day) && Advent::isActiveDay($day)) {
			$day = $day+1;
			$extension = '.jpg';
			// if .jpg does not exist, load .jpeg photo
			if (! self::exists($day.$extension)) {
				$extension = '.jpeg';
				// in case of .jpg or .jpeg file is not found
				if (! self::exists($day.$extension)) {
					// enhancement #8: use a default image when not found
					header('Content-type: image/png');
					exit(file_get_contents('./assets/404.png'));
				}
			}
			$photo = file_get_contents(PRIVATE_FOLDER.'/'.$day.$extension);
			header('Content-type: image/jpeg');
			exit($photo);
		}
		header('Location: ./');
		exit();
	}
	
	private function exists($file) {
		return file_exists(PRIVATE_FOLDER.'/'.$file);
	}
}
 
abstract class Advent {
	
	const DAYS = 24;
	const BEFORE_ADVENT = -1;
	const CURRENT_ADVENT = 0;
	const AFTER_ADVENT = 1;
	const BEGIN_DATE = 1201;
	const END_DATE = 1224;
	
	function state() {
		$now = date('Ymd');
		
		// if we are before the advent
		if ($now < YEAR.self::BEGIN_DATE) { return self::BEFORE_ADVENT; }
		// if we are after
		if ($now > YEAR.self::END_DATE) { return self::AFTER_ADVENT; }
		// else we are currently in advent \o/
		return self::CURRENT_ADVENT;
	}
	
	function acceptDay($day) {
		return $day >= 0 && $day < self::DAYS;
	}
	
	function isActiveDay($day) {
		$state = self::state();
		return ($state == self::CURRENT_ADVENT && $day < date('d')) || $state == self::AFTER_ADVENT;
	}
	
	private function getDayColorClass($day, $active = FALSE) {
		$result = '';
		// is the day active ?
		if ($active) { $result .= 'active '; }    
		// set a color for the background
		$result .= 'day-color-'.($day%4 + 1);
		return $result;
	}
	
	function getDays() {
		$result = '<div class="container days">';
		for ($i=0; $i<self::DAYS; $i++) {
			$active = self::isActiveDay($i);
			if ($active) { $result .= '<a href="?'. URL_DAY .'='. ($i+1) .'" title="Day '. ($i+1) .'"'; }
			else { $result .= '<div'; }
			$result .= ' class="day-row '. self::getDayColorClass($i, $active) .'"><span>'. ($i+1) .'</span>';
			if ($active) { $result .= '</a>'; }
			else { $result .= '</div>'; }
		}
		return $result.'</div>';
	}
	
	function getDay($day) {
		$result = '<div class="container day">';
		
		// check if we have info to display
		if (file_exists(CALENDAR_FILE)) {
			$file = json_decode(file_get_contents(CALENDAR_FILE));
			if (!empty($file->{$day+1})) {
				if (!empty($file->{$day+1}->title)) { $title = htmlspecialchars($file->{$day+1}->title); }
				if (!empty($file->{$day+1}->legend)) { $legend = htmlspecialchars($file->{$day+1}->legend); }
				if (!empty($file->{$day+1}->text)) { $text = $file->{$day+1}->text; }
			}
		}
		
		// set the day number block 
		$result .= '<a href="./?'. URL_DAY.'='. ($day+1) .'" class="day-row '. self::getDayColorClass($day, TRUE) .'"><span>'. ($day+1) .'</span></a>';
		// set the title
		$result .= '<h1><span>';
		if (!empty($title)) { $result .= $title; }
		else { $result .= 'Day '.($day+1); }
		$result .= '</span></h1>';
		// clearfix
		$result .= '<div class="clearfix"></div>';
		
		// display image
		$result .= '<div class="text-center"><img src="./?photo='. ($day+1) .'" class="img-responsive img-thumbnail" alt="Day '. ($day+1) .'" />';
		// do we have a legend?
		if (!empty($legend)) { $result .= '<p class="legend">&mdash; '.$legend.'</p>'; }
		$result .= '</div>';
		// clearfix
		$result .= '<div class="clearfix"></div>';
		
		// do we have a text?
		if (!empty($text)) { $result .= '<div class="text panel panel-default"><div class="panel-body">'.$text.'</div></div>'; }
		
		// we do not forget the pagination
		$result .= '<ul class="pager"><li class="previous';
		if (self::isActiveDay($day-1) && ($day-1)>=0) { $result .= '"><a href="?'. URL_DAY .'='. $day .'" title="yesterday" class="tip" data-placement="right">'; }
		else { $result .= ' disabled"><a>'; }
		$result .= '<i class="glyphicon glyphicon-hand-left"></i></a></li><li class="next';
		if (self::isActiveDay($day+1) && ($day+1)<self::DAYS) { $result .= '"><a href="?'. URL_DAY .'='. ($day+2) .'" title="tomorrow" class="tip" data-placement="left">'; }
		else { $result .= ' disabled"><a>'; }
		$result .= '<i class="glyphicon glyphicon-hand-right"></i></a></li></ul>';
		
		// we add disqus thread if supported
		if (defined('DISQUS')) { $result .= '<div id="disqus_thread"></div>'; }

		return $result.'</div>';
	}
	
	function bePatient($day) {
		return '<div class="container error"><div class="panel panel-info"><div class="panel-heading"><h3 class="panel-title">Christmas is coming soon!</h3></div><div class="panel-body">But before, <strong>be patient</strong>, day '. ($day+1) .' is only in few days. <a href="./" class="illustration text-center tip" title="home"><i class="glyphicon glyphicon-home"></i></a></div></div></div>';
	}
		
}

/*
 * Session managment
 */

if (defined('PASSKEY')) {
	// for calendars on same server, set a different cookie name based on the script path
	session_name(md5($_SERVER['SCRIPT_NAME']));
	
	session_start();
	
	// want to log out
	if (isset($_GET['logout'])) {
		$_SESSION['welcome'] = FALSE;
		session_destroy();
		header('Location: ./');
		exit();
	}
	
	// want to log in
	if (isset($_POST['pass']) && !empty($_POST['pass'])) {
		if ($_POST['pass'] == PASSKEY) {
			$_SESSION['welcome'] = TRUE;
			header('Location: ./');
			exit();
		}
	}
	
	// not logged in: we ask passkey
	if (!isset($_SESSION['welcome']) || !$_SESSION['welcome']) {
		$loginRequested = TRUE;
	}
}


/*
 * Load template
 */
 
$template = NULL;

// need to display log form?
if (defined('PASSKEY') && isset($loginRequested)) {
	$template = '
	<div class="container text-center">
		<div class="page-header"><h1 class="text-danger">This is a private area!</h1></div>
		<p>Please sign in with your <span class="font-normal">passkey</span> to continue.</p> 
		<form method="post" role="form" class="espace-lg form-inline">
			<div class="form-group"><input type="password" name="pass" id="pass" class="form-control input-lg" autofocus required /></div>
			<button type="submit" class="btn btn-default btn-lg tip" data-placement="right" data-title="sign in"><i class="glyphicon glyphicon-eye-open"></i></button>
		</form>
	</div>';
}
// want to see a photo ?
else if (isset($_GET['photo'])) { Image::get($_GET['photo']-1); }
// nothing asked, display homepage
else if (empty($_GET)) {
	$template = Advent::getDays();    
}
// want to display a day
else if (isset($_GET['day'])) {
	$day = $_GET['day'] - 1;
	if (! Advent::acceptDay($day)) { header('Location: ./'); exit(); }
	if (Advent::isActiveDay($day)) {
		$template = Advent::getDay($day);
	}
	else { $template = Advent::bePatient($day); }
}

// want to display about page [no need to be logged in to access]
if (isset($_GET['about'])) {
	// if ugly URL
	if (!empty($_GET['about'])) { header('Location: ./?about'); exit(); }
	$template = file_get_contents('./assets/about.html');
}

// default template is 404
if (empty($template)) {
	$template = '<div class="container error"><div class="panel panel-danger"><div class="panel-heading"><h3 class="panel-title">404 Not Found</h3></div><div class="panel-body">The requested URL was not found on this server. <a href="./" class="illustration illustration-danger text-center tip" title="home"><i class="glyphicon glyphicon-home"></i></a></div></div></div>';
	header('HTTP/1.1 404 Not Found', true, 404);
}
 

?><!doctype html>
<html lang="fr">
	<head>
		<meta charset="UTF-8" />
		<title><?php echo TITLE; ?> &middot; Advent Calendar</title>
		
		<!-- Parce qu’il y a toujours un peu d’humain derrière un site... -->
		<meta name="author" content="Nicolas Devenet" />
		
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico" />
		<link rel="icon" type="image/png" href="assets/favicon.png" />
		
		<link href="assets/bootstrap.min.css" rel="stylesheet">
		<link href="//fonts.googleapis.com/css?family=Lato:300,400,700" rel="stylesheet" type="text/css">
		<link href="assets/adventcalendar.css" rel="stylesheet">

	</head>

	<body>
		
		<nav class="navbar navbar-default navbar-static-top" role="navigation">
		<div class="container">
		<div class="navbar-header">
		<!--<button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-collapse">
			<span class="sr-only">navigation</span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
		</button>-->
		<a class="navbar-brand tip" href="./" title="home" data-placement="right"><i class="glyphicon glyphicon-home"></i> <?php echo TITLE; ?></a>
		</div>
		
		<div class="collapse navbar-collapse" id="navbar-collapse">
		<ul class="nav navbar-nav navbar-right">
			<li><a href="./?about" class="tip" data-placement="left" title="about"><i class="glyphicon glyphicon-tree-conifer"></i> Advent Calendar</a></li>
			<?php
			if (defined('PASSKEY') && isset($_SESSION['welcome'])) { echo '<li><a href="./?logout" title="logout" class="tip" data-placement="bottom"><i class="glyphicon glyphicon-user"></i></a></li>'; }
			?>
		</ul>
		</div>
		</div>
		</nav>
		
		<div class="background<?php if(defined('ALTERNATE_BACKGROUND')) { echo ' alternate-background'; } ?>">
		<?php
			echo $template;
		?>
		</div>
		
		<footer>
		<hr />
		<div class="container">
			<p class="pull-right"><a href="#" id="goHomeYouAreDrunk" class="tip" data-placement="left" title="upstairs"><i class="glyphicon glyphicon-tree-conifer"></i></a></p>
			<div class="notice">
				<a href="https://github.com/nicolabricot/AdventCalendar" rel="external">Advent Calendar</a> &middot; Version <?php echo VERSION; ?>
				<br />Developed with love by <a href="http://nicolas.devenet.info" rel="external">Nicolas Devenet</a>.
			</div>
		</div>
		</footer>
		
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script src="assets/bootstrap.min.js"></script>
		<script src="assets/adventcalendar.js"></script>
		<?php if (defined('DISQUS')): ?>
		<script>
			var disqus_shortname = '<?php echo DISQUS; ?>';
			(function() {
				var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
				dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
				(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
			})();
		</script>
		<?php endif; ?>
	</body>
</html>