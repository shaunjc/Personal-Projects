<?php

/****************************************************/
/*  Active Campaign Provisioning Module for WHMCS   */
/*                                                  */
/*  Created by: Shaun Cockerill (MediaCloud)        */
/*  Date: August 2013                               */
/*                                                  */
/*      Copyright 2013 (Creative Commons)           */
/*                                                  */
/****************************************************/

/*	Config Options - Key and URL Required (Other Options can be added to match desired plans or Specific Packages)	*/
function activecampaign_ConfigOptions() {

    $configarray = array(
	// Package Name in WHMCS
	"Package Name" => array( "Type" => "text", "Size" => "25", ),
	// API Username
	"API User" => array( "Type" => "text", "Size" => "25", "Description" => "Required for the API to update the product on Create and Terminate" ),
	// Email Sending Limit
	"Email Limit" => array( "Type" => "text", "Size" => "5", "Description" => "emails" ),
	// Renewal Period for sending emails (Rolling Period)
	"Time Period" => array( "Type" => "dropdown", "Options" => "monthly,annually,ever",
     	// Time period and emails can be overridden by the current billing cycle
     	"Description" => "<div>
     		The time period will be overridden by the selected billing cycle unless it is set to 'ever'.<br/>
     		Billing cycles less than one year will set the Active Campaign limit to 'monthly' and visa versa.<br/>
     		The total number of emails will also be adjusted to reflect any changes to the time period by the billing cycle.</div>"),
	// API URL for Active Campaign
	"API URL" => array( "Type" => "text", "Size" => "50", ),
	// API Key for Active Campaign (Administrator)
	"API Key" => array( "Type" => "text", "Size" => "50", ),
    );

	# Return an array of the module options for each product/package
	return $configarray;

}

/*	Base function to obtain current billing cycle - For Monthly or Annual Plans	*/
function activecampaign_getBillingCycle($query) {

	// Determine the Account and Service ID 
	$accid = $query['clientdetails']['userid'];
	$serviceid = $query['serviceid'];
	
	// Billing Cycle is only available by use of the API
	$command = "getclientsproducts";
	$adminuser = $query["configoption2"];
	$values = array('clientid' => $accid, 'serviceid' => $serviceid);
	$results = localAPI($command,$values,$adminuser);
	
	// We're only interested in the billing cycle at this point
	// Given the fact that we supplied the exact service ID, we should get a single product
	$cycle = $results['products']['product'][0]['billingcycle'];

	// Store the email limit and timeframe for easy access
	$emails = $query['configoption3'];
	$time = $query['configoption4'];
	
	// If the product is set to ever, then that is what we want
	if ($time == 'ever') {
		// Return the array for Active Campaign
		return array('limit_mail' => $emails, 'limit_mail_type' => $time);
	}
	
	// Determine the total amount of emails and billing period by the billing cycle
	switch($cycle) {
		// For billing cycles less than a year
		case 'Monthly':
		case 'Quarterly':
		case 'Semi-Annually':
			// If the package is set to annually, then work out the monthly amount or emails
			if ($time == 'annually') {
				$emails = $emails / 12;
			}
			// Return the monthly amount of emails and period
			//return array('limit_mail' => $emails, 'limit_mail_type' => 'monthly');
			$time = 'monthly';
			break;
		// For billing cycles a year or longer
		case 'Annually':
		case 'Biennially':
		case 'Triennially':
			// If the package is set to monthly, then calculate the annual amount of emails
			if ($time == 'monthly') {
				$emails = $emails * 12;
			}
			// Return the annual amount of emails and period
			//return array('limit_mail' => $emails, 'limit_mail_type' => 'annually');
			$time = 'annually';
			break;
		// For one time or free services
		case 'Free Account':
		case 'One Time':
			// Limit emails to the default listing and set to ever
			//return array('limit_mail' => $emails, 'limit_mail_type' => 'ever');
			$time = 'ever';
			break;
		// For all other cases (unforseen)
		default:
			// Return the default limit and time period as determined by the package
			//return array('limit_mail' => $emails, 'limit_mail_type' => $time);
	}
	
	return array('limit_mail' => $emails, 'limit_mail_type' => $time);
}

/*	Base function to return user or group information from Active Campaign	*/
function activecampaign_confirm($type, $value, $url, $key) {

	// load API wrapper
    require_once("includes/ActiveCampaign.class.php");

    // Connect to API
    $ac = new ActiveCampaign($url, $key);
	
	// Return the correct type of output depending on the type of request
	switch($type) {
		case 'username':
			$user = $ac->api("user/view?username=" . $value);
			return $user;
			break;
		case 'id':
			$user = $ac->api("user/view?id=" . $value);
			return $user;
			break;
		case 'group':
			$group = $ac->api("group/view?id=" . $value);
			return $group;
			break;
		case 'all':
			// Special check - returns an array of group names (lowercase with only the letters and numbers remaining)
			$groups = $ac->api("group/list");
			$list = array();
			foreach($groups as $group) {
				$list[] = strtolower(preg_replace("/[^a-zA-Z0-9]/", "", $group->title));
			}
			return $list;
			break;
		default:
			break;
			return "No work type supplied.";
	}
}

/*	Function called to add group and users once a unique name is found (Adjust Parameters to include other options as necessary)	*/
function activecampaign_activate($query, $cycle) {

    // load API wrapper
    require_once("includes/ActiveCampaign.class.php");

    // Connect to API
    $ac = new ActiveCampaign($query["configoption5"], $query["configoption6"]);
    
    // Group parameters (Limit Emails as set by Package)
    $params = array(
        "title" => $query['domain'],
        "lists" => array(),
        "sendmethods" => array(1, 2, 3),
        "pg_form_edit" => 1,
        "pg_list_add" => 1,
        "pg_list_bounce" => 1,
        "pg_list_delete" => 1,
        "pg_list_edit" => 1,
        "pg_list_emailaccount" => 1,
        "pg_list_headers" => 1,
        "pg_message_add" => 1,
        "pg_message_delete" => 1,
        "pg_message_edit" => 1,
        "pg_message_send" => 1,
        "pg_reports_campaign" => 1,
        "pg_reports_list" => 1,
        "pg_reports_user" => 1,
        "pg_reports_trend" => 1,
        "pg_subscriber_actions" => 1,
        "pg_subscriber_add" => 1,
        "pg_subscriber_approve" => 1,
        "pg_subscriber_delete" => 1,
        "pg_subscriber_edit" => 1,
        "pg_subscriber_export" => 1,
        "pg_subscriber_fields" => 1,
        "pg_subscriber_filters" => 1,
        "pg_subscriber_import" => 1,
        "pg_subscriber_sync" => 1,
        "pg_template_add" => 1,
        "pg_template_delete" => 1,
        "pg_template_edit" => 1,
        "pg_user_add" => 1,
        "pg_user_delete" => 1,
        "pg_user_edit" => 1,
        "group_limit_mail_checkbox" => "on",
        "limit_mail" => $cycle['limit_mail'],
        "limit_mail_type" => $cycle['limit_mail_type'],
    );
	
	// Create Group using above parameters
    $cg = $ac->api("group/add", $params);
        
	if ($cg->result_code) {
		// User Parameters (Assign to above group)
		$params = array(
			"username" => $query['username'],
			"password" => $query['password'],
			"password_r" => $query['password'],
			"email" => $query['clientsdetails']['email'],
			"first_name" => $query['clientsdetails']['firstname'],
			"last_name" => $query['clientsdetails']['lastname'],
			"group" => array($cg->group_id)
		);
	
		// Create User using above parameters
		$cu = $ac->api("user/add", $params);
		
		if ($cu->result_code) {
			return array('gid' => $cg->group_id, 'uid' => $cu->userid);
		}
		else {
			return array('error' => 1, 'message' => 'Error Creating User: ' . $cu->result_message . ' (Group ' . $cg->group_id . ')');
		}
	}
	else {
		return array('error' => 1, 'message' => 'Error Creating Group ' . $cg->result_message);
	}
}

/*	WHMCS Create Mudule Function - Verifies unsername and group names are unique before calling activate function	*/
function activecampaign_CreateAccount($params) {
	// Set Active Campaign API key for easy call back.
	$url		= $query["configoption5"];
	$key		= $query["configoption6"];

	// Obtain 'Domain' and username from WHMCS
	// We will use this to create our group and user in Active Campaign respectively
	$domain		= $params['domain'];
	$username	= $params['username'];
	// Create a blank array to update WHMCS afterwards if the username or group needs to be slightly different
	$set		= array();
	
	// Ensure the Domain and Username fields are set
	if (empty($domain)){
		// Temporarily set the domain to the company name while creating the group
		$domain	= $params['clientdetails']['companyname'];
		// Add to the update array which we will run once we have a unique group name
		$set['domain'] = $domain;
	}
	if (empty($username)) {
		// Temporarily set the username to the first 8 characters of the group name (lowercase with no spaces or special characters)
		$username = strtolower(substr(preg_replace("/[^a-zA-Z0-9]/", "", $params['clientdetails']['companyname']), 0, 8));
		// Add to the update array which we will run once we have a unique group name
		$set['username'] = $username;
	}

	// Confirm group does not already exist
	// Collect all the group names as lowercase strings without spaces
	$all = activecampaign_confirm("all", "", $url, $key);
	// Match each item against the current domain name and ensure it is a unique value
	$test = strtolower(preg_replace("/[^a-zA-Z0-9]/", "", $domain));
	$c = 1; // Assume that it already exists until we find a unique name
	while ($c > 0) { // We'll create unique names by adding a counter to the end (hopefully 1-2 iterations at most)
		$s = 0; // Set to 1 if we find a match
		for ($i = 0; $i < count($all); $i++) {
			if ($all[$i] == $test) { 
				$s = 1;
			}
		}
		// If a match was found
		if ($s) {
			// Retest again with a higher number at the end
			$test = strtolower(preg_replace("/[^a-zA-Z0-9]/", "", $domain . $c));
			// Loop increment to determine next number to add
			$c++;
		}
		else { // Otherwise
			// If this is not the first time we have looped
			if ($c > 1) {
				// Append the highest number we have tested to the end of our domain name
				$domain = $domain . ' ' . ($c - 1);
				// Add to the update array (regardles of whether it has been already) which we will run once we have a unique group name
				$set['domain'] = $domain;
			}
			// Stop the loop
			$c = 0;
		}
	}
	
	// Confirm username not in use by setting a test variable
	$test = $username;
	// Start a loop with at least 1 iteration
	$c = 1; // Assume that it already exists until we find a unique name
	while ($c > 0) { // We'll create unique username by adding a counter to the end (do not expect more than a handful of iterations)
		$user = activecampaign_confirm("username", $test, $url, $key);
		// Do not need a for loop as we can check each username individually
		if ($user->success) {
			// Increment and test again if the username exists
			$test = $username . $c;
			$c++;
		}
		else { // Otherwise
			// If this is not the first time through the loop
			if ($c > 1) {
				// Ensure we append the highest number we have tested
				$username = $username . ($c - 1);
				// Add to the update array (regardles of whether it has been already) which we will run once we have a unique username
				$set['username'] = $username;
			}
			// Stop the loop
			$c = 0;
		}
	}
	
	// Generate password from custom script if none supplied
	$password = $params['password'];
	if (empty($password)){
		require_once('password.php');
		$password = genpwd(8,1,1,1);
		$set['password'] = $password;
	}
	
	// Set the username, password, and group name
	$params['username'] = $username;
	$params['password'] = $password;
	$params['domain'] = $domain;
	
	// Confirm the correct email limits as determined by the billing cycle
	$cycle = activecampaign_getBillingCycle($params);
		
	// Create Active Campaign Account if possible
	$output = activecampaign_activate($params, $cycle);
	
	if ($output['error']) { // If creation fails return the failure message
		return $output['message'];
	}
	
	// Prepare the rest of the variables to update the current product
	$command = "updateclientproduct";
	$adminuser = $query["configoption2"];
	// username, domain, and password have already been set if they need to be updated
	// Set the service id - required
	$set['serviceid'] = $params["serviceid"];
	// Set the customfields (Where I plan to store the user id of the Active Campaign user)
	$custom = activecampaign_packages($params);
	if (empty($custom) || empty($custom['Active Campaign User ID']) || empty($custom['Active Campaign Group ID'])) {
		return "Package needs to be updated to include the fields 'Active Campaign User ID' and 'Active Campaign Group ID'.";
	}
	$set['customfields'] = base64_encode(serialize(array($custom['Active Campaign User ID'] => $user->id, $custom['Active Campaign Group ID'] => $user->groups)));
	// Update the fields
	$results = localAPI($command,$set,$adminuser);
	
	if ($results[result] == "error") { // If update fails return the failure message
		return $results['message'];
	}
	
	// CreateAccount complete
	return "success";
}

/*	Function called to remove users and groups separately - Archive group with no access is currently set statically	*/
function activecampaign_delete($type, $value, $url, $key) {

	// load API wrapper
    require_once("includes/ActiveCampaign.class.php");

    // Connect to API
    $ac = new ActiveCampaign($url, $key);
	$output = array();
	
	switch($type) {
		case 'user':
			// Delete the user including all lists
			$output = $ac->api("user/delete?extra=1&id=" . $value);
			break;
		case 'group':
			// Delete the group and assign all remaining users to the special Archive group (Trial with no emails or other permissions)
			$output = $ac->api("group/delete?alt=242&id=" . $value);
			break;
		default:
			$output['error'] = 1;
			$output['message'] = "Invalid Type Selected";
	}
	
	return $output;
}

/*	WHMCS Terminate Module Function - Removes user and group once username and subscription id are shown to match	*/
function activecampaign_TerminateAccount($query) {

	$url = $query["configoption5"];
	$key = $query["configoption6"];
	
	// Locate the client information from Active Campaign
	$user = activecampaign_confirm('id', $query['customfields']['Active Campaign User ID'], $url, $key);
	// Confirm the Group ID matches Active Campaign
	if ($user->groups != $query['customfields']['Active Campaign Group ID']) {
		return "Group ID does not match User ID.";
	}
	// Locate the Group information from Active Campaign
	$group = activecampaign_confirm('group', $user->groups, $url, $key);
	
	if ($user->username == $query["username"]) {
		$du = activecampaign_delete('user', $user->id, $url, $key);
			
		if (!$du->result_code) {
			return $du->result_message;
		}
		
		$dg = activecampaign_delete('group', $user->groups, $url, $key);

		if (!$dg->result_code) {
			return $dg->result_message;
		}
		if ($dg->result_code && $du->result_code) {
			// Update subscription ID to NULL and set to terminated
			$command = "updateclientproduct";
			$adminuser = $query["configoption2"];
			$set['serviceid'] = $query["serviceid"];

			// Determine the custom fields depending on the package
			$custom = activecampaign_packages($params);
			if (empty($custom) || empty($custom['Active Campaign User ID']) || empty($custom['Active Campaign Group ID'])) {
				return "Package needs to be updated to include the fields 'Active Campaign User ID' and 'Active Campaign Group ID'.";
			}
			$set['customfields'] = base64_encode(serialize(array($custom['Active Campaign User ID'] => $user->id, $custom['Active Campaign Group ID'] => $user->groups)));
			
			$set['status'] = 'Terminated';
			$results = localAPI($command,$set,$adminuser);
	
			if ($results[result] == "error") { // If update fails return the failure message
				return $results['message'];
			}			
		}
		# return array('user' => $du, 'group' => $dg);
	}
	else {
		// Confirm the username in WHMCS matches the username in Active Campaign
		return "Username and Subscription ID does not match";
	}
	
	return "success";
}

// Function called to update the current group with the config options for the specific package
function activecampaign_update($query, $type, $params) {

	// load API wrapper
    require_once("includes/ActiveCampaign.class.php");

    // Connect to API
    $ac = new ActiveCampaign($query["configoption5"], $query["configoption6"]);

	// Execute API
	$updt = $ac->api($type . "/edit", $params);
	
	// Return response
	return $updt;
	
}

/*	WHMCS Terminate Module Function - Updates package options in Active Campaign to match current package in WHMCS	*/
function activecampaign_ChangePackage($params) {
	
	// Locate the client information from Active Campaign
	$user = activecampaign_confirm('id', $params['customfields']['Active Campaign User ID'], $params["configoption5"], $params["configoption6"]);
		
	if ($user->groups != $params['customfields']['Active Campaign Group ID']) {
		return "Group ID does not match User ID.";
	}
	// Confirm the username matches
	if ($user->username == $params['username']) {	// Confirm the Group ID matches Active Campaign
		// Confirm the correct amount of emails and time period as determined by the billing cycle
		$cycle = activecampaign_getBillingCycle($params);
		$cycle['id'] = $user->groups;

		// Update the group with the current product
		$output = activecampaign_update($params, 'group', $cycle);
		// Check the response
		if (!$output->result_code) {
			// Output the error message if it exists
			return $output->result_message;
		}
	}
	else {
		// Confirm the username in WHMCS matches the username in Active Campaign
		return "Username and Subscription ID do not match";
	}
	
	return "success";
	
}

/*	WHMCS Terminate Module Function - Updates password in Active Campaign to match password listed in WHMCS	*/
function activecampaign_ChangePassword($params) {

	$id = $params['customfields']['Active Campaign User ID'];
	$username = $params['username'];
	$password = $params['password'];

	// Locate the current user information from Active Campaign
	$user = activecampaign_confirm('id', $id, $params["configoption5"], $params["configoption6"]);
	
	// Check username matches Active Campaign
	if ($user->username != $username) {
		return "Username does not match User ID.";
	}
	// Check to see if group ID matches User ID
	if ($user->groups != $params['customfields']['Active Campaign Group ID']) {
		return "Group ID does not match User ID.";
	}
	
	// Create the post array for editing a contact (all the adjustable fields are required)
	$query = array(
		'id' => $id,
		'username' => $username,
		'password' => $password,
		'password_r' => $password,
		'email' => $user->email,
		'first_name' => $user->first_name,
		'last_name' => $user->last_name,
		'group' => array($user->groups)
	);
	
	// Submit the update
	$output = activecampaign_update($params, 'user', $query);
	
	if (!$output->result_code) {
		// Return an error message if unsuccessful
		return $output->result_message;
	}

	return 'success';

}

/*	Added link to log into Active Campaign - Does not log all the way in unless ported	*/
function activecampaign_LoginLink($params) {
	$code = '<a target="_blank" href="' . $params["configoption5"] . '/admin/login.php?idt=&rmu=1&user=' . $params['username'] . '&pass=' . $params['password'] . '" onclick="f=document.getElementById(\'ac_form\');if(f){f.submit();return false;}">Log in to Active Campaign</a>';
	return $code;

}

/*	WHMCS Function to add an extra button into the admin section	*/
function activecampaign_AdminCustomButtonArray() {
    $buttonarray = array(
	 "Update from Active Campaign" => "load",
	);
	return $buttonarray;
}

/*	Function called to determine custom set values for packages, as they are different for each package, and connot be identified unless already set	*/
function activecampaign_packages($query) {
	// Determine the Account and Service ID 
	$accid = $query['clientdetails']['userid'];
	$serviceid = $query['serviceid'];
	
	// Custom fields are only available by use of the API if not already set for this service
	$command = "getclientsproducts";
	$adminuser = $query["configoption2"];
	$values = array('clientid' => $accid, 'serviceid' => $serviceid);
	$results = localAPI($command,$values,$adminuser);
	
	// Only one product should be returned
	$custom = $results['products']['product'][0]['customfields']['customfield'];
	// Create new associative array
	$customfields = array();
	// Assign each key to the name
	foreach ($custom as $c) {
		$customfields[$c['name']] = $c['id'];
	}
	
	return $customfields;
}

/*	Function called to download the User and Group ID from Active Campaign - used when an account was provisioned manually (username has to be supplied)	*/
function activecampaign_load($params) {

	$url = $params["configoption5"];
	$key = $params["configoption6"];
	$set = array();

	// Check to see if a username is saved to the service
	$username = $params['username'];
	if (empty($username)) {
		return "Please save a username first";
	}
	
	// Check Active Campaign to see if an account exists with this username
	$user = activecampaign_confirm('username', $username, $url, $key);
	if (!$user->result_code) {
		return $user->result_message . " for username " . $username;
	}
	
	// If no domain is already supplied
	if (empty($params['domain'])) {
		// Collect the group information for this user and save it to the 'Domain' field
		$group = activecampaign_confirm('group', $user->groups, $url, $key);
		if (!$group->result_code) {
			return $group->result_message . " for group " . $user->groups;
		}
		$set['domain'] = $group->title;
	}
	
	// Set the User and group ID to the custom fields
	$custom = activecampaign_packages($params);
	if (empty($custom) || empty($custom['Active Campaign User ID']) || empty($custom['Active Campaign Group ID'])) {
		return "Package needs to be updated to include the fields 'Active Campaign User ID' and 'Active Campaign Group ID'.";
	}
	$set['customfields'] = base64_encode(serialize(array($custom['Active Campaign User ID'] => $user->id, $custom['Active Campaign Group ID'] => $user->groups)));
	
	// Save the information to WHMCS
	$command = "updateclientproduct";
	$adminuser = $params["configoption2"];
	$set['serviceid'] = $params["serviceid"];
	$results = localAPI($command,$set,$adminuser);
	
	if ($results[result] == "error") { // If update fails return the failure message
		return $results['message'];
	}			

	return "success";
}

?>
