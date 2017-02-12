#!/bin/usr/php
<?php
define('AC_URL', 'http://www.avionsdechasse.org/avions/$page?type=last');
define('AC_PATH', '/home/romein/Pictures/Avions de Chasse/');
define('AC_DB', '.avions.db');
define('AC_PREVENT_DUPLICATE', true); // set to false to download every pictures, even if it is recurrent.

$increment=0;
function png2jpg($originalFile, $outputFile, $quality=95) {
	if($image = imagecreatefrompng($originalFile))
	{
		imagejpeg($image, $outputFile, $quality);
		imagedestroy($image);
		$increment=0;
	}
	else
		if(!(boolean)$increment++)
			png2jpg($originalFile, $outputFile, $quality);
	return true;
}

function forcejpg($originalFile, $outputFile, $quality=97)
{
	global $increment;
	
	set_error_handler(function ($no, $msg, $file, $line) {
		throw new ErrorException($msg, 0, $no, $file, $line);
	});
	try{
		$image = imagecreatefromstring(file_get_contents($originalFile));
		$increment=0;
	}catch(Exception $e)
	{
		if(!(boolean)$increment++) // try three times
			forcejpg($originalFile, $outputFile, $quality);
	}
	if(imagejpeg($image, $outputFile, $quality))
		if(imagedestroy($image))
			return true;

			
	return false;
}

// function getthelastfile()
// {
// 	$files = glob('*.jpg');
// 	array_walk($files, function(&$item){
// 		$item = substr($item, 0, strpos($item, '.'));
// 	});
// 	$files = array_flip($files);
// 	//print count($files);
// 	krsort($files, SORT_NUMERIC);
// 	reset($files);
// 	$files = array_fill(0, 1, array_keys($files));
// 	return $files[0][0];
// }

function getDirectory()
{
	$files = glob('*.jpg');
	array_walk($files, function(&$item){
		$item = substr($item, 0, strpos($item, '.'));
	});
	return $files;
// 	$files = array_flip($files);
// 	//print count($files);
// 	krsort($files, SORT_NUMERIC);
// 	reset($files);
// 	$files = array_fill(0, 1, array_keys($files));
// 	return $files;
}

function putToDb($f)
{
	return file_put_contents(AC_DB, $f."\n", FILE_APPEND);
}

function inDb($f)
{
// 	static $db = null;
// 	if(is_null($db))
// 	$db = file(AC_DB);
	return in_array($f, file(AC_DB));
}

if(!is_dir(AC_PATH))
	mkdir(AC_PATH);
chdir(AC_PATH);

// $i = isset($argv[1]) ? 1 : getthelastfile();

// $end = false;
$alreadyDownloaded = getDirectory();

if(@$argv[1] == 'DB')
{ // Rebuild the MD5 database, which prevent duplicate pictures
	@unlink(AC_DB);
	$db = array();
	sort($alreadyDownloaded, SORT_NUMERIC);
	foreach($alreadyDownloaded as $f)
	{
		$f .= '.jpg';
		$md5 = md5_file($f);
		if(in_array($md5, $db))
			print 'rm '.$f."\n";
		else
			$db[] = $md5;
	}
	file_put_contents(AC_DB, implode("\n", $db));
	exit();
}

$page = 1;
$tictac = 0;
while($tictac != (isset($argv[1]) ? $argv[1] : 3))
{
	$url = str_replace('$page', $page > 1 ? 'page/'.$page : '', AC_URL);
// 	print "DEBUG : URL = $url\n";
	$raw = file_get_contents($url);
// 	print "DEBUG : RAW successful, here : $raw\n";
	//https://i0.wp.com/public.avionsdechasse.org/images/sources/2016/10/20161016095309_4_carre.jpg
	if(preg_match_all('/(https?:\/\/[a-z0-9]+\.wp\.com\/public\.avionsdechasse\.org\/images\/sources\/[0-9]{4}\/[0-9]{2}\/[0-9]{14}[_0-9]*)_carre\.jpg.+\#([0-9]+)</Us', $raw, $matches))
	{
// 		print "DEBUG : MATCHES = ".print_r($matches, true);
		$diff=array_diff($matches[2], $alreadyDownloaded);
// 		print_r($diff);
		if(!empty($diff))
		{
		
			$tictac = 0;
			foreach(array_intersect($matches[2], $diff) as $key => $number)
			{
				$dl = $matches[1][$key];
				$file = $number.'.jpg';
// 				print "DEBUG : dl = $dl\n";
// 				print "DEBUG : file = $file\n";
				if(AC_PREVENT_DUPLICATE)
					if(inDb($file))	
						continue; // Do not download if duplicate.
				if(!forcejpg($dl, $file))
				{
					@unlink($file);
					print "An error occured when downloading avion #$number.\n";
				}
				else
					if(AC_PREVENT_DUPLICATE)
						putToDb($file);
			}
		}
		else
			$tictac++;
		$page++;
	}
	else
	{
		print "No avion found...";
		break;
	}
}

exit();
// Obsolete

// while(/*true*/ false)
// {
// 	$file = $i.'.jpg';
// 	$url = AC_URL.$i++;
// 
// 	if(file_exists(realpath($file)))
// 	{
// 		$end = 0;
// 		continue; // skip on already downloaded pictures
// 	}
// // 	print $url;
// 	$html = file_get_contents($url);
// 	if(!preg_match('/<title>\#[0-9]+ .+<\/title>/', $html))
// 	{
// 		if($end != 4)
// 		{
// 			print $end++ .'... ';
// 			continue;
// 		}
// 		else
// 		{
// 			print 'Ending on #'.$i-1 ." ($url)\n";
// 			break; // quit if we're redirecting to home page
// 		}
// 	}
// 	$end = 0;
// 	
// 	// http://avionsdechasse.org/images/images_sources/20130831125613_0_53.jpg
// 	print 'Downloading #'.$file.'... ';
// 	if(!preg_match('/http:\/\/(public.)?avionsdechasse.org\/images\/[a-z0-9_\/]+\.(jpg|png)/', $html, $matches))
// 		print 'Warning : No picture found at '.$url."\n";
// 
// /*	if(strrchr($matches[0], '.') == '.png')
// 		png2jpg($matches[0], $file);
// 	else
// 		if(!copy($matches[0], $file))
// 		{
// 			@unlink($file); //incomplete file
// 			print 'error occured.'."\n";
// 		}else
// 			print 'done.'."\n";*/
// 			
// 	if(forcejpg($matches[0], $file))
// 		print 'done.'."\n";
// 	else
// 	{
// 		@unlink($file);
// 		print "error occured.\n";
// 	}
// 	unset($html);
// 	continue;
// }


?>
