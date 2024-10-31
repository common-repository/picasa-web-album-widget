<?php
/*
Plugin Name: Picasa Web Album Widget
Plugin URI: http://www.kivela.be/index.php/apps/wordpress-plugin-picasa-web-album-widget/
Description: Adds a sidebar widget to display random picasa images
Author: Stefan Venken
Version: 0.6.4
Author URI: http://kivela.be
*/

// with some modifications by Adam Brown - http://adambrown.info/ (wordpress.org forums: adamrbrown)

global $imgLinkBase;
$imgLinkBase = 'http://picasaweb.google.com/'; // default
	
if ( !in_array('PicasaWebAlbumWidget', get_declared_classes() ) ) :

class WP_PicasaWebAlbumWidget
{
		function setArguments($args){
			$options = get_option('widget_PicasaWebAlbumWidget');
			
			$nationality = "en_US";
			$p=array();
			
			$p['url']="";
			$p['random']=true;
			$p['num']= 0;
			$p['size']= 144;
			$p['username']= $options['username'];
			$p['albumid']= '';
			$p['showRandomAlbum']= true;
			$p['linkToAlbum']= false;
			$p['title'] = $options['title'];

			
			if (is_array($options)){
				if(!isset($options['url'])){

					if(!isset($options['username'])){
						print "No username Provided";
						return false;
					} else {
						$p['username'] = $options['username'];
						$category='album';
						$p['url'] = "http://picasaweb.google.com/data/feed/api/user/" . $p['username'] ."/";
					}

					if(isset($options['albumid']) && $options['albumid'] != ''){
						$p['url'] = 'http://picasaweb.google.com/data/feed/base/user/' . $p['username'] . '/albumid/';
						$p['albumid'] = $options['albumid'];
						$category='photo';
						$p['url'] .= $p['albumid'];
                     $p['showRandomAlbum'] = false;
					}

					$p['url'] .= "?kind=". $category ."&access=public&hl=" . $nationality;

				}else{
					$p['url'] = $options['url'];
				}


				if (isset($options['random'])){

						$p['random'] = $this->trueOrFalse($options['random']);

				} 
				
								
				if (isset($options['number']) && is_numeric($options['number']))
				{
					$p['num'] = $options['number'];
				}
				if(isset($options['width']) && is_numeric($options['width']) && ($options['width'] == 144 || $options['width'] == 160 || $options['width'] == 288  || $options['width'] == 576 || $options['width'] == 720 )){
					$p['size'] = $options['width'];
				}
				if (isset($options['link']))
				{
					$p['linkToAlbum'] = ($options['link'] != 'checked="checked"');
				}
			} else {
				$p['url'] = $args;
			}

		return $p;
	}

	function trueOrFalse($val){
		$isTrue = false;
		if($val == 'true' || (is_numeric($val) && $val != 0)){
			$isTrue = true;
	}
	
	return $isTrue;

}

	function display($args){

		extract($args);
		echo  $before_widget;
		
		$p = WP_PicasaWebAlbumWidget::setArguments($args);
		
		if ( !function_exists('fetch_rss') )
		{	
		    // adam's hack to prevent fatals on "fetch_rss"
			if ( file_exists(ABSPATH . WPINC . '/rss.php') )
				require_once(ABSPATH . WPINC . '/rss.php');
			else
				require_once(ABSPATH . WPINC . '/rss-functions.php');
		} // end hack


		if($p['showRandomAlbum']){ // we need to try and load all albums, pick one at random, then try this over again...
			if($albums =  WP_PicasaWebAlbumWidget::loadWpAlbums($p['url'])) {
				shuffle($albums);
				$p['url'] = $albums[0]['album_feed_url'];
              $p['albumid'] = $albums[0]['id'];
              $p['albumname'] = $albums[0]['name'];
              
			}
		}

		

		$list = '';
						
		$p['title'] ? print($before_title . $p['title'] . $after_title) : null;
		$list .= '<div class="picasewebalbumwidget"><p>';	// adam's hack. put quotes around class name and replace <ul> with <p> (b/c there's no <li> below)
		// a general note: the html here was invalid. I replaced the <ul> tags with <p> tags, but you could just as easily insert <li></li> into the code below instead.

		if($images = WP_PicasaWebAlbumWidget::loadWpImages($p['albumid'], $p['username'])){
			if ($p['random'])
			{
				// We want a random selection, so lets shuffle it
				shuffle($images);
			}
			if ($p['num'] > 0)
			{
				// Slice off the number of items that we want:
				$images = array_slice($images, 0, $p['num']);
			}
			
			if ( is_array( $images ) ) { // prevent fatals (adam's hack)
				foreach ($images as $image) {
					$imgUrl = $image['album_thumbnail_url'].'?imgmax='. $p['size'];
					$imgLink= $image['image_url'];
	              
					// Provides: replacement of s288 to the image needed
					$imgUrl = str_replace("/s288/", "/s".$p['size']."/", $imgUrl);
					
					if($p['size'] == 160){
						$imgUrl .= '&amp;crop=1'; // adam's hack. Change & to &amp;
					}
					
					if($p['linkToAlbum']){
						global $imgLinkBase; // adam's hack. see top of file.
						$imgLink= $imgLinkBase . $p['username'].'/'.$p['albumname'];
					}

					$list .= '<a href="'.$imgLink.'" target="_blank" class="snap_noshots"><img src="' . $imgUrl .'" alt="'.$image['title'].'" /></a> <br />'; // adam's hack. you had invalid html here (no <li> and </li>). I changed it a couple times, I think it's back to your original code now, but I don't remember.
				}
			} // end if (adam's hack)
			$list .= "</p></div>"; // adam's hack. deleted </ul> and replaced with </p> since no <li> tags in above.

		}

		print $list;		
		
		
		echo $after_widget;
	}
   
   function loadWpAlbums($url)
    {
       $feed = @fetch_rss($url);
       $feedItems = $feed->items;
       $count = 0;
	   if ( is_array( $feedItems ) ){	// prevent fatals (adam's hack)
	       foreach ($feedItems as $key=>$album){
		     if ( '' == $album['gphoto']['id'] && '' != $album['gphoto:id'] ){	// makes it more robust (adam's hack)
			  $album['gphoto']['id'] = $album['gphoto:id'];
			  $album['link_http://schemas.google.com/g/2005#feed'] = $album['link'];
			  $album['gphoto']['name'] = $album['gphoto:name'];
			 }
	         $albums[$count]['id'] = $album['gphoto']['id'];
	         $albums[$count]['album_feed_url'] = $album['link_http://schemas.google.com/g/2005#feed'];
	         $albums[$count]['name'] = $album['gphoto']['name'];          
	         $count++;
	       }
		} // end if (adam's hack)
       return $albums;
   }

    function loadWpImages($albumId, $userName)
   {
      $url = 'http://picasaweb.google.com/data/feed/base/user/'.$userName.'/albumid/'.$albumId.'?kind=photo';
      
      $feed = @fetch_rss($url);
      $feedItems = $feed->items;
      $count = 0;
	 if ( is_array( $feedItems ) ){ // prevent fatals when feed fails to load (adam's hack)
      foreach ($feedItems as $key=>$image)
      {
         $images[$count]['id'] = $image['id'];
         $images[$count]['image_url'] = $image['link'];
         //print_r($image);
	preg_match('/href="http(s)?:\/\/[^"]+".*src="(http(s)?:\/\/[^"]+)"/', $image['summary'], $thumbMatches);
//print_r($thumbMatches);
	$images[$count]['album_thumbnail_url'] = $thumbMatches[2];   
	
         $count++;
      }
	 } // close "if" (adam's hack)
      return $images;
  }


	

	function debug($val){

		print("<pre>");
		print_r($val);
		print("</pre>");

	}

	
}

endif;

function widget_PicasaWebAlbumWidget_init()
{
	// Check for the required API functions
	if (!function_exists('register_sidebar_widget'))
		return;

	// main widget function
	function widget_PicasaWebAlbumWidget($args) {		
		global $pw;
                $pw = new WP_PicasaWebAlbumWidget();		
		$pw->display($args);
	}
	
	// control panel
	function widget_PicasaWebAlbumWidget_control() {
		$options = $newoptions = get_option('widget_PicasaWebAlbumWidget');
		if ( $_POST["PicasaWebAlbumWidget-submit"] ) {
			$newoptions['title'] = trim(strip_tags(stripslashes($_POST["PicasaWebAlbumWidget-title"])));
			$newoptions['albumid'] = trim(strip_tags(stripslashes($_POST["PicasaWebAlbumWidget-albumid"])));
			$newoptions['username'] = strip_tags(stripslashes($_POST["PicasaWebAlbumWidget-username"]));
			$newoptions['link'] = isset($_POST["PicasaWebAlbumWidget-link"]);
			$newoptions['number'] = trim(strip_tags(stripslashes($_POST["PicasaWebAlbumWidget-number"])));;
			$newoptions['width'] = trim(strip_tags(stripslashes($_POST["PicasaWebAlbumWidget-width"])));;
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			
			update_option('widget_PicasaWebAlbumWidget', $options);
		}
		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		$username = htmlspecialchars($options['username'], ENT_QUOTES);
		$number = htmlspecialchars($options['number'], ENT_QUOTES);
		$width = htmlspecialchars($options['width'], ENT_QUOTES);
		$link = $options['link'] ? 'checked="checked"' : '';
		$albumid = htmlspecialchars($options['albumid'], ENT_QUOTES);
		
		if (empty($delay)) $delay ='5000';
		?>
	<p style="text-align:center;">
	<br><br><label for="PicasaWebAlbumWidget-feeds"><strong>Picasa username:</strong><br><br /></label>
		<textarea style="width: 100%;" id="PicasaWebAlbumWidget-username" name="PicasaWebAlbumWidget-username"><?php echo $username; ?></textarea></p>
		<p><label for="PicasaWebAlbumWidget-title"><strong>Widget Title (optional):</strong></label>
		<input style="width: 100%;" id="PicasaWebAlbumWidget-title" name="PicasaWebAlbumWidget-title" type="text" value="<?php echo $title; ?>" /></p>
		<p><label for="PicasaWebAlbumWidget-albumid"><strong>Picasa Album Id (optional):</strong><br /><small>If you want images of only 1 album, provide its album id here</small></label>
		<input style="width: 100%;" id="PicasaWebAlbumWidget-albumid" name="PicasaWebAlbumWidget-albumid" type="text" value="<?php echo $albumid; ?>" /></p>
		<p><label for="PicasaWebAlbumWidget-number"><strong>Number of random album thumbnails to grab:</strong></label><br>
		<input style="width: 30%;" id="PicasaWebAlbumWidget-number" name="PicasaWebAlbumWidget-number" type="text" value="<?php echo $number; ?>" /></p>	
		<p><label for="PicasaWebAlbumWidget-width"><strong>Width of the thumbnails:</strong></label><br>
		<select id="id="PicasaWebAlbumWidget-width" name="PicasaWebAlbumWidget-width" style="width:30%">
		<option value="144" <?php echo (($width == '144') ? 'selected' : ''); ?> >144</option>
		<option value="160" <?php echo (($width == '160') ? 'selected' : ''); ?>>160</option>
		<option value="288" <?php echo (($width == '288') ? 'selected' : ''); ?>>288</option>
		<option value="576" <?php echo (($width == '576') ? 'selected' : ''); ?>>576</option>
		<option value="720" <?php echo (($width == '720') ? 'selected' : ''); ?>>720</option>
		</select></p>		
		<p style='text-align: center; line-height: 30px;'><label for="PicasaWebAlbumWidget-link">Link thumbnails to photos in Picasa Web Albums (if not the images are linked to the album)? <input class="checkbox" type="checkbox" <?php echo $link; ?> id="PicasaWebAlbumWidget-link" name="PicasaWebAlbumWidget-link" /></label></p>
				<input type="hidden" id="PicasaWebAlbumWidget-submit" name="PicasaWebAlbumWidget-submit" value="1" />
	<?php
	}
        
    
	register_sidebar_widget(__('Picasa Web Album widget'), 'widget_PicasaWebAlbumWidget');
	register_widget_control(__('Picasa Web Album widget'), 'widget_PicasaWebAlbumWidget_control', 230, 230);
	
}
	
// Tell Dynamic Sidebar about our new widget and its control
add_action('plugins_loaded', 'widget_PicasaWebAlbumWidget_init');
?>