<?php
/*
Plugin Name: Hide Username Front Side
Plugin URI: http://www.mindstien.com
Description: Secures your wp site from hackers by hiding username of members from front side. Force user signup form to accept first name and last name, then uses the combination to replace author's display name, url slug and nick name to hide the username from frontside, also updates the url slug on user profile update.
Version: 2.0
Author: Chirag Gadara (Mindstien Technologies)
Author URI: http://www.mindstien.com
*/


add_action('user_register','hufs_user_register_func',999);
add_action( 'profile_update', 'hufs_user_register_func',999);
add_action('register_form', 'hufs_forceRegistrationField_addFields',1);
add_action('register_post', 'hufs_forceRegistrationField_checkFields', 10, 3);
add_action('user_register', 'hufs_forceRegistrationField_updateFields', 10, 1);


function hufs_user_register_func($userid)
{
	global $wpdb;
	$results = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."users WHERE ID = $userid");
	
	$firstname = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."usermeta WHERE user_id = $userid AND meta_key='first_name'");
	
	$lastname = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."usermeta WHERE user_id = $userid AND meta_key='last_name'");
		
	$newnicename = '';
	$newdisplayname = '';
	if(trim($firstname) == '' AND trim($lastname) == '')
	{
		// first name, last name does not exist, so generate random name
		$newnicename = 'unknown_user';
		$newdisplayname = 'Unknown User';
	}
	else
	{
		$newnicename = sanitize_text_field(strtolower($firstname)).'_'.sanitize_text_field(strtolower($lastname));
		$newdisplayname = $firstname.' '.$lastname;
	}
	
	$nickname = get_user_meta($userid, 'nickname',true);
	
	if($results->user_login == $nickname OR $nickname=='' OR $nickname ==null)
	{
		update_user_meta( $userid, 'nickname', $newdisplayname);
	}
	
		//update user nicename
		$dup = $wpdb->get_var("SELECT ID FROM ".$wpdb->prefix."users WHERE user_nicename = '".$newnicename."' AND ID != $userid");
		if(!$dup)
		{	
			$wpdb->update( $wpdb->prefix."users", array('user_nicename'=>$newnicename), array("ID"=>$userid));
		}
		else
		{
			$temp = false;
			$count = 1;
			while($temp == false)
			{
				
				$count++;
				$dup = $wpdb->get_var("SELECT ID FROM ".$wpdb->prefix."users WHERE user_nicename = '".$newnicename."_".$count."'");
				
				if(!$dup)
				{	
					$temp = true;
					$wpdb->update( $wpdb->prefix."users", array('user_nicename'=>$newnicename."_".$count), array("ID"=>$userid));
				}
			}
		}
	
	
	if($results->user_login == $results->display_name)
	{
		//update display name
		$wpdb->update( $wpdb->prefix."users", array('display_name'=>$newdisplayname), array("ID"=>$userid));
	}
}


$hufs_forceRegistrationField_knownFields = array(
	"first_name"	=> "First Name",
	"last_name"		=> "Last Name",
	);
	
$hufs_forceRegistrationField_getOptions = array("first_name","last_name");

function hufs_forceRegistrationField_addFields(){

	global $hufs_forceRegistrationField_knownFields, $hufs_forceRegistrationField_getOptions;

	
		foreach($hufs_forceRegistrationField_getOptions as $thisValue){
		?>
		<p>
			<label><?php _e($hufs_forceRegistrationField_knownFields[$thisValue], 'hufs_forceRegistrationField') ?><br />
			<input type="text" name="<?php echo $thisValue; ?>" id="<?php echo $thisValue; ?>" class="input" value="<?php echo esc_attr(stripslashes($_POST[$thisValue])); ?>" size="25"  style="font-size: 20px; width: 97%;	padding: 3px; margin-right: 6px;" /></label>
		</p>
		<?php
		}
	

}


function hufs_forceRegistrationField_checkFields($user_login, $user_email, $errors){

	global $hufs_forceRegistrationField_knownFields, $hufs_forceRegistrationField_getOptions;
	
	foreach($hufs_forceRegistrationField_getOptions as $thisValue){
		if ($_POST[$thisValue] == '') {
			$errors->add('empty_'.$thisValue , __("<strong>ERROR</strong>: Please type your ".$hufs_forceRegistrationField_knownFields[$thisValue].".",'hufs_forceRegistrationField'));
		}
	}
}

function hufs_forceRegistrationField_updateFields($user_id){

	global $hufs_addField, $hufs_forceRegistrationField_getOptions;

	foreach($hufs_forceRegistrationField_getOptions as $thisValue){
		update_user_meta( $user_id, $thisValue, $_POST[$thisValue]);
	}

}


add_action('admin_menu', 'huf_plugin_menu');

function huf_plugin_menu() {
	add_submenu_page('users.php','Hide Username', 'Hide Username', 'manage_options', 'hide_username_front_side', 'huf_options_page_render');
}

function huf_options_page_render()
{
	?>
	<div class='wrap'>
		<h2>Hide Username Front Side <span style='font-size:12px;'><br>Plugin by: <a href='http://www.mindstien.com' target='_blank'>Mindstien Technologies.</a></span></h2>
	<?php
	
	$huf_error = array();
	
	//process form data if submitted
	if(isset($_POST['huf_verification']) AND $_POST['huf_verification']==$_SESSION['huf_verification'])
	{
		//first vefiy the form data..
		if(trim($_POST['firstname'])=="")
			$huf_error[]="Please enter default firstname.";
		if(trim($_POST['lastname'])=="")
			$huf_error[]="Please enter default lastname.";
		
		$pat = explode(" ",$_POST['format']);
		if(empty($pat))
			$huf_error[]="Please enter valid display name format";
		$allowed_tags = array('#firstname','#lastname');	
		foreach ($pat as $p)
		{
			if(!in_array($p,$allowed_tags))
				$huf_error[]="Invalid tag found in Display Format: Allowed tags are: ".implode(', ',$allowed_tags);
		}
	
		if(empty($huf_error))
		{
			global $wpdb;
			$limit = 10;
			if(intval($_POST['max']>0))
				$limit = $_POST['max'];
			
			$table = $wpdb->prefix."users";
			$users = $wpdb->get_results("SELECT * FROM $table WHERE user_login = user_nicename OR user_login = display_name LIMIT $limit");
			
			foreach ($users as $user)
			{
				$firstname = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."usermeta WHERE user_id = ".$user->ID." AND meta_key='first_name'");
				
				$lastname = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."usermeta WHERE user_id = ".$user->ID." AND meta_key='last_name'");
					
				$newnicename = '';
				$newdisplayname = '';
				if(trim($firstname) == '')
					$firstname = $_POST['firstname'];
				if(trim($lastname) == '')
					$lastname = $_POST['lastname'];
				
				$_POST['format'] = str_replace("#firstname",$firstname,$_POST['format']);
				$_POST['format'] = str_replace("#lastname",$lastname,$_POST['format']);
				
				$pat = explode(" ",$_POST['format']);
				
				foreach($pat as $k=>$p)
					$pat[$k] = sanitize_text_field(strtolower($p));
				
				$newnicename = implode("_",$pat);
				
				$newdisplayname = $_POST['format'];
				
				$nickname = get_user_meta($user->ID, 'nickname',true);
				
				if($user->user_login == $nickname OR $nickname=='' OR $nickname ==null)
				{
					update_user_meta( $user->ID, 'nickname', $newdisplayname);
				}
				
					//update user nicename
					$dup = $wpdb->get_var("SELECT ID FROM ".$wpdb->prefix."users WHERE user_nicename = '".$newnicename."' AND ID != ".$user->ID);
					if(!$dup)
					{	
						$wpdb->update( $wpdb->prefix."users", array('user_nicename'=>$newnicename), array("ID"=>$user->ID));
					}
					else
					{
						$temp = false;
						$count = 1;
						while($temp == false)
						{
							$count++;
							$dup = $wpdb->get_var("SELECT ID FROM ".$wpdb->prefix."users WHERE user_nicename = '".$newnicename."_".$count."'");
							
							if(!$dup)
							{	
								$temp = true;
								$wpdb->update( $wpdb->prefix."users", array('user_nicename'=>$newnicename."_".$count), array("ID"=>$user->ID));
							}
						}
					}
				
				
				if($user->user_login == $user->display_name)
				{
					//update display name
					$wpdb->update( $wpdb->prefix."users", array('display_name'=>$newdisplayname), array("ID"=>$user->ID));
				} 
			}
			echo "<div class='updated'><p><strong>".count($users)." Users</strong> Updated</p></div>";
		}
		else
		{
			echo "<div class='error'><p>";
			foreach ($huf_error as $e)	
				echo $e."<br>";
			echo "</p></div>";
		}
	}
	

	$huf_verification = md5(time());
	$_SESSION['huf_verification'] = $huf_verification;
	
	global $wpdb;
	$table = $wpdb->prefix.'users';
	$visible = $wpdb->get_var("SELECT count(*) FROM $table WHERE user_login = display_name");
	$url = $wpdb->get_var("SELECT count(*) FROM $table WHERE user_login = user_nicename");
	$total = $wpdb->get_var("SELECT count(*) FROM $table WHERE user_login = user_nicename OR user_login = display_name");
	if ($visible >0 OR $url >0)
		echo "<div class='error'><p><b>Warning:</b> Your site has <b>$visible users</b> with publicaly vissible usernames and <b>$url Users</b> with username visible in profile url or athour pages url. <b>Total $total users</b> should hide their username.</p></div>";
	else
		echo "<div class='updated'><p><b>Congratulations!</b> Your site has <b>0 users</b> with visible username on fronside.</p></div>";
	?>
		<?php if ($visible >0 OR $url >0): ?>
		<form action='' method='post'>
		<h2>Bulk Fix Usernames</h2>
			<input type='hidden' name='huf_verification' value='<?php echo $huf_verification; ?>'>
			<p>
				<label for='format'><strong>User's Display Name Format</strong></label>
				<input type='text' id='format' name='format' value='#firstname #lastname' />
			</p>
			<p>
				<label for='firstname'><strong>Dummy Firstname</strong></label>
				<input type='text' id='firstname' name='firstname' value='Unknown' />
			</p>
			<p>
				<label for='lastname'><strong>Dummy Lastname</strong></label>
				<input type='text' id='lastname' name='lastname' value='User' />
			</p>
			<p class='description'>
				If you enter above values as "<b>#firstname</b> <b>#lastname</b>",<b>Unknown</b>,<b>User</b> respectively, Plugin will try to create display name in "<b>#firstname #lastname</b>" format, if firstname and lastname is found in user's profile, if not found than will use "<b>Unknown User</b>" as display name.
			</p>
			<p>
				<label for='max'><strong>No. of users to fix in one attempt</strong></label>
				<input type='number' id='max' name='max' value='10' /> <span class='description'>(Default:10) Please enter only numeric value</span>
			</p>
			<input type='submit' name='submit' class='button-primary' value='Fix Usernames Now...'>
		</form>
		
		<p style='color:red'><strong>Warning:</strong><br>
			This "Bulk Fix" feature should be used carefully as it deals directly with database (users and usermeta table). Once User's data have been updated by this plugin can not be undone. We are not responsible for any damage/changes caused to your user data with this plugin. Take backup of database (users and usermeta tables) before using this plugin.  <br>**Use At Your Own Risk**
		</p>
		<?php endif; ?>
	
	<p><strong>Why This Plugin ?</strong><br>
		What is most important to secure your user accounts (admin account) from hackers ? A Strong Password. Right ? .. It's wrong. Actually the username has the same importance as password, wordpress access is limited to user with both username/password combination. If you don't take care of hiding your admin username from hackers, you are offering 50% work done for hackers as than they only have to guess your password or break it via brute force attack etc..
		</p>
		
		<p><strong>How it works?</strong><br>
		To hide your username from front side, you have to setup your firstname / lastname in your user profile and choose display name to something different than your username. That's pretty easy but wait, what when you have hundreds of users on your site ? Would you like to go through editing profile for each user ? Well, its ok, than what if future users that will signup on your site ? Here the "Hide Username Front Side" plugin comes in action. It allows you to update all user profiles in one click and forces new user to provide their firstname and lastname to create user account and automatically sets public name as "Firstname Lastname" combination instead of username. 
		</p>
		
		<p>Send your valuable comments/feedback/suggestion to <a href='http://www.mindstien.com/contact-us/' target='_blank'>Mindstien Technoloiges</a></p>
		
	</div>
	
	<?php
}

?>