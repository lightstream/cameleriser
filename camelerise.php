#!/usr/bin/php
<?php
error_reporting(E_ALL ^ E_NOTICE);
if ($argc < 2 || $argc > 3 || in_array($argv[1], array('-h', '--help', '/?'))) {
	// Display usage info
	echo "Usage: ".substr($_SERVER["SCRIPT_NAME"], strrpos($_SERVER["SCRIPT_NAME"], DIRECTORY_SEPARATOR)+1)." [-f] filename
Converts all variable names containing underscores to their camel case equivalents. 

If the -f option is specified, it will also try to convert form field names that
contain underscores. Also fixes a few other formatting issues for compliance with
coding standards, primarily missing whitespace in expressions and PHP short tags.

The original file is saved as filename.precamel.php.\n\n";
} else {
	// file to camelerise is last argument
	$filename = $argv[$argc-1];
	
	if ($sourceFile = @file($filename)) {
		// Select Reg Exp according to whether or not -f option was used
		if ($argc == 3 && $argv[1] == '-f') $regex = '/name="[a-z][a-zA-Z0-9_]+(\[\])?"|\$[a-z][a-zA-Z0-9_]+/'; else $regex = '/\$[a-z][a-zA-Z0-9_]+/';
		
		// Process each line of source file
		foreach ($sourceFile as $lineNumber => $line) {
			$matches = array();
			if (preg_match_all($regex, $line, $matches)) {
				$showLine = true;
				rsort($matches[0]); // rsort prevents issue with vars like $my_var and $my_var_two
				foreach ($matches[0] as $match) {
					// Build replacement camelerised variable name
					$fixedVar = '';
					$startOffset = 0;
					while (($underscorePos = strpos($match, '_', $startOffset)) !== false) {
						$fixedVar .= substr($match, $startOffset, $underscorePos - $startOffset);
						$fixedVar .= strtoupper($match[$underscorePos + 1]);
						$startOffset = $underscorePos + 2;
					}
					if ($startOffset) {
						$fixedVar .= substr($match, $startOffset);
						if ($showLine) {
							$showLine = false;
							echo "\n".$lineNumber.": ".trim($line)."\n";
						}
						echo "	Replacing \033[31m$match\033[0m with \033[34m$fixedVar\033[0m\n";
					} else {
						$fixedVar = $match;
					}
					$line = str_replace($match, $fixedVar, $line);
				}
			}
			
			// Fix a few more things and add line to processed array
			$matches = array();
			if (preg_match('#^(\s*)//\*+(.*?)\*+\s*$#', $line, $matches)) {
				$line = $matches[1].'// '.ucfirst(trim(strtolower($matches[2])));
			}
			$processedFile[] = preg_replace(array('/(if|for|foreach|while)\(/',
			                                      '#([^ !])([=<>!+\-*/.]==?)([^ !])#', 
			                                      '#^(?:require(?:_once)?|include) ?\(?([^\)]+)\);#',
			                                      '#<font color="([^"]+)">([^<]+)</font>#i',
			                                      '/<\?= ?(.*?);? ?\?>/'), 
			                                array('$1 (', 
			                                      '$1 $2 $3',
			                                      'require_once $1;',
			                                      '<span style="color:$1">$2</span>',
			                                      '<?php echo $1; ?>'),
			                                rtrim($line));
		}
		
		// Remove blank lines / PHP closing tag from end of file
		while (!$processedFile[count($processedFile)-1] || trim($processedFile[count($processedFile)-1]) == '?>') {
			unset($processedFile[count($processedFile)-1]);
		}
		 
		// Back up old file
		rename($filename, str_replace('.php', '.precamel.php', $filename));
		
		// Save camelerised file
		file_put_contents($filename, implode("\n", $processedFile));
	} else echo $filename.": file not found\n";
}
