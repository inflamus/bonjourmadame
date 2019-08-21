#!/usr/bin/php
<?php
/* Usage :
 *  php bonjourmadame.php 				Will download the latest bonjourmadame via the RSS feed
 *  php bonjourmadame.php [pageno]		Will download the madames from the archives page, and loop it by [pageno] times (~50 madames per page)
 * Requires : simple_html_dom.php
*/

define('DISPLAY_TODAY_ONLY', true); // set to true to display only the BM of the day. false => cycle des BM aléatoire.

define('BM_URL', 'http://bonjourmadame.fr');
// define('RSS_URL', BM_URL.'/rss');
define('RSS_URL', 'http://feeds2.feedburner.com/BonjourMadame');
define('ARCHIVE_URL', BM_URL.'/archive');
define('IMAGE_URL', BM_URL.'/image');
define('DOWNLOAD_DIR', '/home/romein/Pictures/BonjourMadame');
define('PICTURE_SIZE', 1280);
require('simple_html_dom.php');


class BlogPost
{
    var $date;
    var $ts;
    var $link;

    var $title;
    var $text;
}

class BlogFeed
{
    var $posts = array();

    function __construct($file_or_url)
    {
        $file_or_url = $this->resolveFile($file_or_url);
        if (!($x = simplexml_load_file($file_or_url)))
            return;
	
        foreach ($x->channel->item as $item)
        {
            $post = new BlogPost();
            $post->date  = (string) $item->pubDate;
            $post->ts    = @strtotime($item->pubDate);
            $post->link  = (string) $item->link;
            $post->title = (string) $item->title;
//          $post->text  = (string) $item->description;
		$post->text = (string) $item->children('content', true)->encoded;

		// Create summary as a shortened body and remove images, 
            // extraneous line breaks, etc.
            $post->summary = $this->summarizeText($post->text);

            $this->posts[] = $post;
        }
    }

    private function resolveFile($file_or_url) {
        if (!preg_match('|^https?:|', $file_or_url))
            $feed_uri = $_SERVER['DOCUMENT_ROOT'] .'/shared/xml/'. $file_or_url;
        else
            $feed_uri = $file_or_url;

        return $feed_uri;
    }

    private function summarizeText($summary) {
        $summary = strip_tags($summary);

        // Truncate summary line to 100 characters
        $max_len = 100;
        if (strlen($summary) > $max_len)
            $summary = substr($summary, 0, $max_len) . '...';

        return $summary;
    }
}
$increment=0;
function png2jpg($originalFile, $outputFile, $quality) {
global $increment;
	if($image = imagecreatefrompng($originalFile))
	{
		imagejpeg($image, $outputFile, $quality);
		imagedestroy($image);
		$increment=0;
	}
	else
		if(!(boolean)$increment++)
			png2jpg($originalFile, $outputFile, $quality);
}

function parse_all($string){
	preg_match_all('/http:\/\/w?w?w?\.?bonjourmadame\.fr\/post\/([0-9]+)/', $string, $matches);
	$_id = 0;
	foreach($matches[1] as $id)
		download_id('/'.$_id = $id);
	//return $_id;
	preg_match('/\/archive\?before_time=([0-9]+)/', $string, $matches);
	return $matches[0];
}


function download_id($id, $imgurl=null){
	if(!substr($id, 0, 1)=='/')
		$id = '/'.$id;

	$file = DOWNLOAD_DIR.'/'.$id.'.jpg';
	print( 'Downloading image id '.$id." at url <".$imgurl."> ..." );
	if(file_exists($file))
	{
		print 'already exists !'."\n";
		return false;
	}
	if(is_null($imgurl))
	{
		$imgurl = IMAGE_URL.$id;
		$imghtml = file_get_html($imgurl);
		$finalurl = $imghtml->find('img[id=content-image]', 0)->{"data-src"};
	}
	else
		$finalurl = $imgurl;
	if(strrchr($finalurl, '.') == '.png')
	{
		if(!file_exists($file))
			png2jpg($finalurl, $file, 95);
	}else
		if(!file_exists($file))
			if(!copy($finalurl, $file))
			{
				@unlink($file); //incomplete file
				print 'error occured.';
			}else
				print 'done.';
	print "\n";
	if(isset($imghtml)) unset ($imghtml);
	return true;
}


if(!empty($argv[1]))
{
	if(is_file($argv[1])) // PODCASTADDICT sqlite file
	{
		$DB = new SQLite3($argv[1], SQLITE3_OPEN_READONLY);
		$contents = $DB->query('SELECT publication_date, content FROM episodes WHERE podcast_id=(SELECT _id FROM podcasts WHERE name like "%Bonjour%");');
		while($c = $contents->fetchArray(SQLITE3_ASSOC))
		{
			if(preg_match('#https?:\/\/i[0-9]{1,3}.wp.com\/bonjourmadame.fr\/wp-content\/uploads\/20[0-9]{2}\/[0-9]{2}\/[a-zA-Z0-9_-]+.(jpe?g|png)#', $c['content'], $match))
			{
				$c['publication_date'] = (int)substr($c['publication_date'], 0, -3);
				$d = (int)date("w", $c['publication_date']);
				if($d != 0 && $d != 6) // zap saturday and sundays
					download_id($c['publication_date'], $match[0]);
			}
		}
	}
	$next_page = parse_all(file_get_contents(ARCHIVE_URL));
	if($argv[1] > 1)
	for($i = 1; $i<= $argv[1]; $i++)
	{
		print "\nPage $i\n";
		$next_page = parse_all(file_get_contents(BM_URL.$next_page)); // format : /archive?before_time=0-9
	}
	exit;
}


$rss = new BlogFeed(RSS_URL);
foreach($rss->posts as $p)
{
	//print $p->link."\n";
	$id = strrchr($p->link, '/');
	$id = $p->ts;
	//http://41.media.tumblr.com/de2babab556c386245f5c5707143a9f8/tumblr_nv69p6fH611qzy9ouo1_500.jpg
// 	preg_match('#(https?:\/\/[0-9]{1,3}.media.tumblr.com\/[a-f0-9]{32}\/tumblr_[a-zA-Z0-9_]+)_500.(jpe?g|png)#', $p->text, $match);
	//https://i1.wp.com/bonjourmadame.fr/wp-content/uploads/2018/12/181221.jpg
// 	print $p->text;
// 	continue;
	preg_match('#https?:\/\/i[0-9]{1,3}.wp.com\/bonjourmadame.fr\/wp-content\/uploads\/20[0-9]{2}\/[0-9]{2}\/[a-zA-Z0-9_-]+.(jpe?g|png)#', $p->text, $match);
// 	$url = $match[1].'_'.PICTURE_SIZE.'.'.$match[2];
	$url = $match[0];
	print $p->date;
	print strtotime($p->date);
	$d = (int)date("w", strtotime($p->date));
	if($d != 0 && $d != 6) // zap saturday and sundays
		download_id($id, $url);
}

exit();


// Generator for xml backgrounds.
chdir(DOWNLOAD_DIR);
//$dir = dirname(__FILE__);
$dir = 'BonjourMadame';

define('DEFAULT_DURATION', 600); //10m

$files = glob('./*.jpg');
usort($files, function($a, $b) {
    return strcmp($b, $a);
});


exit();


if(DISPLAY_TODAY_ONLY)
	$files = array($files[0]);   //// ONLY THE MADAME OF TODAY !


//array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_ASC, $files);
/*$out = '<starttime>
    <year>2013</year>
    <month>09</month>
    <day>11</day>
    <hour>00</hour>
    <minute>00</minute>
    <second>00</second>
  </starttime>
<!-- This animation will start at midnight. -->';*/
$out = '';
for($i = 0; $i < count($files); $i++)
	$out .= '
	<static>
    <duration>'.DEFAULT_DURATION.'</duration>
    <file>'.realpath($files[$i]).'</file>
  </static>
    <transition>
    <duration>0.5</duration>
    <from>'.realpath($files[$i]).'</from>
    <to>'.realpath(isset($files[$i+1]) ? $files[$i+1] : $files[0]).'</to>
  </transition>';
  
  
file_put_contents('../'.$dir.'.xml', '<background>'.$out.'</background>');

