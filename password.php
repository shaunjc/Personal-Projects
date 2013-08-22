<?
/************************************************/
/*  Generate password function                  */
/*    - plus other useful string functions      */
/*                                              */
/*  Created by: Shaun Cockerill (iiNet)         */
/*  Date: 26/09/2011                            */
/*                                              */
/*  Adjusted for PHP: 16/08/2013 (MediaCloud)   */
/*                                              */
/************************************************/

// Generate a password
function genpwd($c, $letters, $caps, $numbers) {
	$password = '';
	// Generate the character list to choose from - based on type
	$chars = getchars(password, $letters, $caps, $numbers);
	// Special $counter to ensure at least 1 of each type of selected symbol is used
	$ensure = $letters + $caps + $numbers;

	// Generate each character 1 at a time
	for ($i=0; $i<$c; $i++) {
		
		if ($i < ($c - $ensure + 1)) {
			$password .= genchar($chars);
		}
		// When the password is nearing it's character limit, ensure that all characters are used
		else {
			$chars = getchars($password, $letters, $caps, $numbers);
			$password .= genchar($chars);
		}
	}

	return $password;
}

// Choose a random character from the list of characters
function genchar($chars) {
	$char = $chars[rand(0, count($chars) - 1)];
	return $char;
}

// Choose which characters to choose from
function getchars($word, $letters, $caps, $numbers) {
	// If $letters/$caps/$numbers/$symbols are set then generate a list of characters that match
	$letters = $letters ? "abcdefghijklmnopqrstuvwxyz" : '';
	$caps = $caps ? "ABCDEFGHIJKLMNOPQRSTUVWXYZ" : '';
	$numbers = $numbers ? "0123456789" : '';

	$chars = "";

	// If the word does not contain any type of character then ensure it gets selected
	if (!preg_match("/[a-z]/", $word)) {
		$chars .= $letters;
	}
	if (!preg_match("/[A-Z]/", $word)) {
		$chars .= $caps;
	}
	if (!preg_match("/[0-9]/", $word)) {
		$chars .= $numbers;
	}
	// Otherwise, if the word already contains all types of characters then continue generating all types of characters
	if ($chars == '') {
		$chars = $letters . $caps . $numbers;
	}

	return $chars;
}

//Example:: password = genpwd(9, 1, 1, 1);
// echo genpwd(9,1,1,1);
?>
