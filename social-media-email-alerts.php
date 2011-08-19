<?php
 /*
Plugin Name: Social Media Email Alerts
Version: 1.3
Plugin URI: http://infolific.com/technology/software-worth-using/social-media-email-alerts-for-wordpress/
Description: Receive e-mail alerts when your site gets traffic from social media sites of your choosing. You can also set up alerts for when certain parameters appear in the URL.
Author: Marios Alexandrou
Author URI: Author URI: http://infolific.com/technology/
Special Thanks To: Aaron Harun, http://aahacreative.com/, for his PHP / WordPress development skills
And To: Thaya Kareeson for his tutorial on making any plugin work with caching plugins http://omninoggin.com/wordpress-posts/make-any-plugin-work-with-wp-super-cache/
*/

/******************************
* Start up the plugin
/*****************************/

$wordpress_social_media_email_alerts = new wordpress_social_media_email_alerts();
add_action('init', array($wordpress_social_media_email_alerts,'init'));
add_action('init', array($wordpress_social_media_email_alerts,'js_init'),9);

Class wordpress_social_media_email_alerts{

static $url_counts = array();
/******************************
*
* Adds filters to content.
*
/*****************************/

	function init() {
		global $wra_sites;
		$wra_sites = get_option('wordpress_social_media_email_alerts');
		$options = get_option('wordpress_social_media_email_alerts_options');

		$this->install();
		define('WPSMA_ref', $_SERVER['HTTP_REFERER']);
		define('WPSMA_query', $_SERVER['QUERY_STRING']);
		define('WPSMA_uri', $_SERVER['REQUEST_URI']);

		if(is_admin()){ // The user is on and admin page
			add_action('admin_menu', array($this,'menu'));
		}else{ // The user is not in the admin panel, so get ready to print sites.

			if($options['cache'] == 1){	
				add_action('wp_head', array($this,'js_header'));            
				add_action('wp_footer', array($this,'js_footer'));
			}else{
				$this->check_referrer();
			}
		}
	}

	function js_init(){
		global $wra_sites;
		$wra_sites = get_option('wordpress_social_media_email_alerts');

		if ($_POST['wpsma_action'] == 1) {

			define('WPSMA_ref', urldecode($_POST['wpsma_ref']));
			define('WPSMA_query', $_SERVER['QUERY_STRING']);
			define('WPSMA_uri', $_SERVER['REQUEST_URI']);

			$this->check_referrer();
			echo 1;
		exit();
		}


	}

	function install(){
		global $wpdb,$wp_rewrite;

		if(get_option('wordpress_social_media_email_alerts_installed') == 'installed')
			return;
			//$wp_rewrite->flush_rules();
			$wpdb->query("CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}social_media_email_alerts` (
				`id`  INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`visits` INT NOT NULL ,
				`time` INT NOT NULL ,
				`match` TEXT NOT NULL,
				`ref` TEXT NOT NULL,
				`page` TEXT NOT NULL
			);");
			
		update_option('wordpress_social_media_email_alerts_installed','installed');
	}

	function js_header(){

		wp_print_scripts('jquery');
	}

	function js_footer(){
	?>

		<script type="text/javascript">
 		jQuery(document).ready(function(){
			jQuery.ajax({type : "POST", url : window.location.href ,data : { wpsma_action : "1", wpsma_ref : encodeURIComponent(document.referrer) },success : function(response){}})

 		});
		</script>
	<?php }

	function check_referrer(){
		global $wra_sites;

		$ref = WPSMA_ref;
		$query_string = WPSMA_query;

		if($ref){
			$domain = $this->get_domain($ref);
			
			if($domain){
				$this->is_referrer($domain);
			}
		}

		if($query_string){
			$query = $this->get_query($query_string);

			if($query){
				$this->is_query($query);
			}
		}
	}

	function get_domain($ref){
		global $wra_sites;

		$parts = parse_url($ref);

		if (isset($parts['host'])) {
			$domain = str_replace('www.','',$parts['host']);
			if(is_array($wra_sites[$domain]))
				return $domain;
		}

		return false;
	}

	function get_query($qs){
		global $wra_sites;

		if ($qs) {
			$query = explode("&",$qs);

			foreach($query as $query_string){
				if(is_array($wra_sites[$query_string])){
					return $query_string;
				}
			}
		}

		return false;
	}

	function is_referrer($match){
		global $wra_sites,$wpdb;

		$site = $wra_sites[$match];
		$ref = WPSMA_ref;
		$page = WPSMA_uri;
		$details = $wpdb->get_row($wpdb->prepare("SELECT id, visits, time FROM {$wpdb->prefix}social_media_email_alerts WHERE ref='%s' && page='%s'", $ref, $page));

		if($details->id){

			if(((time() - $details->time)/60 > $site['reset'])) {
				$deleted = true;
				$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}social_media_email_alerts WHERE id='%s'", $details->id));
			} else {
				$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}social_media_email_alerts SET visits='%s' WHERE id='%s' LIMIT 1", $details->visits + 1, $details->id));
			}

		} else {
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}social_media_email_alerts (`ref`, `page`, `time`, `match`, `visits`) VALUES ('%s', '%s', '".time()."', '%s', '1')", $ref, $page, $match));
		}

		//If the email has already been sent. No need to check the count again. 
		if(($details->visits > $site['min_visits']) && !$deleted) 
			return;

		if($details->visits <= $site['min_visits'] && $this->should_email($match)) 
			$this->do_email($match, $ref, $page, $details);
	}

	function is_query($match){
		global $wra_sites,$wpdb;

		$site = $wra_sites[$match];
		$ref = WPSMA_ref;
		$page = WPSMA_uri;
		$query = WPSMA_query;
		
		$details = $wpdb->get_row($wpdb->prepare("SELECT id, visits, time FROM {$wpdb->prefix}social_media_email_alerts WHERE `match`='%s' && `page`='%s'", $match, $page));

		if($details->id){

 			//if($details->visits == $site['min_visits'])
			//	$this->do_email($match, $ref, $page, $details);

			if(((time() - $details->time)/60 > $site['reset'])){
				$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}social_media_email_alerts WHERE id='%s'", $details->id));
			} else {
				$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}social_media_email_alerts SET visits='%s' WHERE id='%s' LIMIT 1", $details->visits + 1, $details->id));
			}

		} else {
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}social_media_email_alerts (`page`, `time`, `match`, `visits`) VALUES ('%s', '".time()."', '%s', '1')", $page, $match));
		}

				//If the email has already been sent. No need to check the count again. 
		if(($details->visits > $site['min_visits']) && !$deleted) 
			return;

		if($details->visits <= $site['min_visits'] && $this->should_email($match)) 
			$this->do_email($match, $ref, $page, $details);
	
	}


	function should_email($match){
		global $wra_sites,$wpdb;

		$site = $wra_sites[$match];
		$page = WPSMA_uri;

		$total = $wpdb->get_var($wpdb->prepare("SELECT SUM(visits) as total FROM {$wpdb->prefix}social_media_email_alerts WHERE `match`='%s' && `page`='%s'", $match, $page));

		if($total == $site['min_visits'])
			return true;

		return false;
	}

	function do_email($match, $referrer, $page, $details){
		global $wra_sites,$wpdb;

		$total = $wpdb->get_var($wpdb->prepare("SELECT SUM(visits) as total FROM {$wpdb->prefix}social_media_email_alerts WHERE `match`='%s' && `page`='%s'", $match, $page));
		
		$options = get_option('wordpress_social_media_email_alerts_options');
		$to = ($wra_sites[$domain]['email']) ? $wra_sites[$match]['email'] : $options['email'];
		$subject = $total . " visits matching $match to http://" . $_SERVER['HTTP_HOST'] . $page . " (via Social Media E-Mail Alerts Plugin)";
		$body = "";

		if ($to && mail($to, $subject, $body)) {

		} else {

		}

	}

// *******************************
// Admin Panel
// *******************************
	function update_sites($options) {

		$sites = null;

		foreach($options as $option) {
			if($option['delete'] == 1)
				continue;

			$option['site'] = trim($option['site']);

			if($option['site'] && $option['min_visits']){

				$sites[$option['site']] =
					array( 	'url' => $option['url'], 
							'min_visits' => $option['min_visits'], 
							'type' => $option['type'], 
							'reset' => $option['reset'], 
							'email' => $option['email']
					);
			}

			unset($link_array,$percent_array,$blanks);
		}

		return $sites;
	}

	function update_options($options) {
		return $options;
	}

/******************************
*
* Purpose: Adds admin menu item to WP menu.
*
/*****************************/

	function menu() {
		add_options_page('Social Media Email Alerts', 'Social Media Email Alerts', 8, __FILE__,array($this,'admin'));
	}

/******************************
*
* Prints and handles the admin menu.
* Called directly by WP.
*
/*****************************/

	function admin(){
		global $wpdb,$wra_sites;

		$sites = get_option('wordpress_social_media_email_alerts');
		$options = get_option('wordpress_social_media_email_alerts_options');

		if($_GET['remove'] && $_GET['to_remove'])
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}social_media_email_alerts WHERE id='%s'",$_GET['to_remove']));

		if ($_POST["action"] == "saveconfiguration") {
			$sites = $this->update_sites($_REQUEST['wordpress_social_media_email_alerts']);
			$options = $this->update_options($_REQUEST['wordpress_social_media_email_alerts_options']);

			update_option('wordpress_social_media_email_alerts',$sites);
			update_option('wordpress_social_media_email_alerts_options',$options);

			$message .= 'Rules Updated.<br/>';
		}
	?>

		<style type="text/css">
			td{padding-top:10px;}
		</style>
	
		<div class="wrap">
			<h2>Social Media Email Alerts Configuration</h2>
			<div style="float: right; border: 1px #AAAAAA solid; width: 300px; padding-left: 5px; padding-right: 5px; padding-bottom: 5px; padding-top: 0px;">
				<h3>Instructions</h3>
				<ul>
						<li>An e-mail will be sent when the visits equals Min. Visits within the number of Reset minutes.</li>
						<li>Domain matches should be of the form: domain.com</li>
						<li>Query strings should be of the form: someparam=somevalue</li>
						<li>Set Min. Visits to a value above "normal" and Reset minutes to a relatively small value (e.g. 30) to be notified of spikes in a traffic.</li>
				</ul>
			</div>

			<div>
				<form method="post">	
					<table border="0" cellpadding="0" cellspacing="0">
					<tr>
						<td>
							<h3>Default Email Address</h3>
							<input type='text' size='50' name='wordpress_social_media_email_alerts_options[email]' value='<?php echo $options['email'];?>'>
						</td>
						<td>&nbsp;&nbsp;&nbsp;</td>
						<td>
							<h3>Is WP-Cache or WP Super Cache Enabled?</h3>
							<label><input type='radio' size='' value="1" name='wordpress_social_media_email_alerts_options[cache]' <?php if($options['cache']) echo "checked='checked'";?>> Yes</label>
							<label><input type='radio' size='' value="0" name='wordpress_social_media_email_alerts_options[cache]' <?php if(!$options['cache']) echo "checked='checked'";?>> No </label>
						</td>
					</tr>
					</table>
					
					<h3>Matching Rules</h3>
					<table cellpadding="0" cellspacing="0" id="site_links">
						<thead><tr><td>Value To Match&nbsp;</td><td>&nbsp;Min. Visits&nbsp;</td><td>Reset (minutes)&nbsp;&nbsp;</td><td>Send This Alert To&nbsp;&nbsp;</td><td align='center'>&nbsp;Delete&nbsp;</td></tr></thead>

						<tr id="site_link_first9999">
							<td><input type='text' style='width: 90%;' value='' name='wordpress_social_media_email_alerts[0][site]'></td>
							<td><input type='text' size='5' value='' name='wordpress_social_media_email_alerts[0][min_visits]'></td>
							<td><input type='text' size='5' value='' name='wordpress_social_media_email_alerts[0][reset]'></td>
							<td><input type='text' size='50' value='' name='wordpress_social_media_email_alerts[0][email]'></td>
							<td align='center'></td>
						</tr>

						<?php
						$x++;

						if($sites){

							while (list($name, $ops) = each($sites)) {	
								$name = attribute_escape(stripslashes($name));
								echo "
									<tr>
									<td><input type='text' style='width: 90%;' value='$name' name='wordpress_social_media_email_alerts[$x][site]'  ></td>
									<td><input type='text' size='5' value='$ops[min_visits]' name='wordpress_social_media_email_alerts[$x][min_visits]' onblur='more_site_links();'></td>
									<td><input type='text' size='5' value='$ops[reset]' name='wordpress_social_media_email_alerts[$x][reset]'></td>
									<td><input type='text' size='50' value='$ops[email]' name='wordpress_social_media_email_alerts[$x][email]'></td>
									<td align='center'><input type='checkbox' value='1' name='wordpress_social_media_email_alerts[$x][delete]' /></td>
									</tr>
								";

								$x++;
							}
						}
						?>
					</table>
					<br/>
					<input type="hidden" name="action" value="saveconfiguration">
					<input type="submit" value="Save" style="width: 100px;" >
					&nbsp;
					<input type="button" value="Reload Page" style="width: 100px;" onClick="window.location.href=window.location.href">
				</form>
			</div>
			<hr style="margin-top: 13px;" />
			<h3>Current Matched Visits</h3>
			<table id="site_links" border="1" rules="rows" frame="hsides">
				<thead><tr><td><strong>Date</strong></td><td>&nbsp;<strong>Match</strong>&nbsp;</td><td align="center">&nbsp;<strong>Visits</strong>&nbsp;</td><td ><strong>Referrer</strong></td><td><strong>Page</strong></td><td align="center"><strong>Delete</strong></td></tr></thead>
				<?php
				$groups = $wpdb->get_results("SELECT `match`, page, SUM(visits) as c FROM {$wpdb->prefix}social_media_email_alerts GROUP BY `match`,page ORDER BY c DESC",ARRAY_A); 

				if(is_array($groups)){

					foreach($groups as $group){
					
						$hits = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}social_media_email_alerts WHERE `match`='{$group[match]}' AND `page` = '{$group[page]}' ORDER BY time DESC",ARRAY_A); 

						if(is_array($hits)){

								$referrer_list = '';
								$total = 0;

							foreach($hits as $hit){
								$site = $wra_sites[$hit['match']]; 

								if(!is_array($site) || !$hit['match']) {
									// Delete records with no matching rules
									$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}social_media_email_alerts WHERE id='%s'",$hit['id']));
									continue;
								}

								$time = $site['reset'] - floor((time()-$hit['time'])/60); 

								if($time <= 0) {
									// Delete expired matches
									$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}social_media_email_alerts WHERE id='%s'",$hit['id']));
									continue;
								}
		
								$reset = ($site['reset'] - floor((time()-$hit['time'])/60));
								if($hit[ref]){
									
									$referrer_list .= "<a href='$hit[ref]'>$hit[ref]</a> <br/><em>(visits $hit[visits], resets in: $reset min)</em><br/>";
								}
								$total += $hit['visits'];
							}

							$hit_page = $hit['page'];
							$hit_page = str_replace('?' . $hit['match'] . '&', '?', $hit_page); // remove match which is first of multiple querystring elements
							$hit_page = str_replace('?' . $hit['match'], '', $hit_page); // remove match which is first and only querystring element
							$hit_page = str_replace('&' . $hit['match'], '', $hit_page); // remove match which is second, but not the last of multiple querystring elements
							$hit_page = str_replace('&' . $hit['match'], '', $hit_page); // remove match which is last of multiple querystring elements

							$hit_page_url = 'http://' . $_SERVER['HTTP_HOST'] . $hit_page;

							
						?>
								<tr><td><?php echo date('F j, Y h:i:s A', $hit['time']);?></td><td><?php echo $hit['match'];?></td><td align="center"><?php echo $total;?></td><td><?php echo $referrer_list;?></td><td><a href="<?php echo $hit_page_url;?>"><?php echo $hit_page;?></a></td><td align="center"><a href="./options-general.php?page=social-media-email-alerts/social-media-email-alerts.php&amp;remove=1&amp;to_remove=<?php echo $hit['id']?>">X</a></td></tr>
						<?php
							
						
						} // if
					} // foreach
				} // if
				?>
			</table>

			<h3>Other Plugins by Marios Alexandrou</h3>
			<a href="http://wordpress.org/extend/plugins/rss-includes-pages/">RSS Includes Pages</a> - Include pages (not just posts) into your RSS feeds. Good for sites that use WordPress as a CMS.
			<br/>
			<a href="http://wordpress.org/extend/plugins/real-time-find-and-replace/">Real-Time Find and Replace</a> - Set up find and replace rules that are executed AFTER a page is generated by WordPress, but BEFORE it is sent to a user's browser. 
			<br/><br/>
		</div>
	<?php
	}
} /*Class ends*/
?>