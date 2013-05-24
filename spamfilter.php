<?php

// spamfilter.php -- Filter through text, searching for spam
// Copyright (C) 2013 Andreas Renberg <iq_andreas@hotmail.com>
//
//  This program is free software; you can redistribute it and/or modify it
//  under the terms of the GNU General Public License version 3, as
//  published by the Free Software Foundation.
//
//  This program is distributed in the hope that it will be useful, but
//  WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
//  General Public License for more details.
//
//  You should have received a copy of the GNU General Public License along
//  with this program; if not, see <http://www.gnu.org/licences/>

define(BLACKLIST_DIR, dirname(__FILE__) . DIRECTORY_SEPARATOR . 'blacklists' . DIRECTORY_SEPARATOR );
define(BLACKLIST_INDEX, BLACKLIST_DIR . 'index' );
define(BLACKLIST_VERSION, file_get_contents(BLACKLIST_DIR . 'version') );
define(ALL_BLACKLISTS, file_get_contents(BLACKLIST_INDEX) );

define(BLACKLIST_UPDATE_URL, 'https://raw.github.com/IQAndreas/php-spam-filter/blacklists/');

function spam_check_text($text, $blacklist = ALL_BLACKLISTS)
{
	if ($blacklist == ALL_BLACKLISTS)
	{
		$blacklists = preg_split("/((\r?\n)|(\r\n?))/", ALL_BLACKLISTS);
		foreach ($blacklists as $blacklist_filename)
		{
			if (!$blacklist_filename) continue; // Ignore empty lines
			$match = regex_match_from_blacklist($text, BLACKLIST_DIR . $blacklist_filename);
			if ($match) return $match;
		}
	}
	else
	{
		return regex_match_from_blacklist($text, $blacklist);
	}
}

function spam_check_url($url, $blacklist = ALL_BLACKLISTS)
{
	// TODO! Just treat the url as plain text for now.
	return spam_check_text($url, $blacklist);
}

function regex_match_from_blacklist($text, $blacklist)
{
	if (!file_exists($blacklist))
	{
		// Check to see if they supplied a relative path instead of an absolute one.
		$blacklist_absolute = BLACKLIST_DIR . $blacklist;
		if (file_exists($blacklist_absolute))
		{
			$blacklist = $blacklist_absolute;
		}
		else
		{
			// Is this the proper way to throw errors in PHP?
			trigger_error("[spamfilter.php::regex_match_from_blacklist()] Error: Cannot find blacklist with name `$blacklist`.");
			return false;
		}
	}
	
	$keywords = file($blacklist);
	$current_line = 0;
	$regex_match = array();
	
	foreach($keywords as $regex) 
	{
		$current_line++;
		
		// Remove comments and whitespace before and after a keyword
		$regex = preg_replace('/(^\s+|\s+$|\s*#.*$)/i', "", $regex);
		if (empty($regex)) continue;
		
		$match = @preg_match("/$regex/i", $text, $regex_match);
		if ($match)
		{
			// Spam found. Return the text that was matched
			return $regex_match[0];
		}
		else if ($match === false)
		{
			trigger_error("[spamfilter.php::regex_match_from_blacklist()] Error: Invalid regular expression in `$blacklist` line $current_line.");
			continue;	
		}
	}
	
	// No spam found
	return false;
}

// WARNING: This will overwrite any of the old files!
// I'm not sure if I should also delete any old blacklists too, or if I should leave them.
// Returns the current blacklist version as a string.
function update_blacklists($force = false, $source_url = BLACKLIST_UPDATE_URL)
{
	// If there is a better way to download stuff from another server, please let me know.
	// I'm using this method because there is a limit to how large the files can be, which is good,
	// since the blacklists should all be rather small. I really should put some more type of protection in.
	$index = BLACKLIST_UPDATE_URL . 'index';
	
	if ($force || blacklist_update_available($source_url))
	{
		// Delete old blacklist files
		$blacklists = preg_split("/((\r?\n)|(\r\n?))/", ALL_BLACKLISTS);
		foreach ($blacklists as $blacklist_filename)
		{
			if (!$blacklist_filename) continue; // Skip empty lines
			$blacklist_filename = basename($blacklist_filename); // Prevent injection (just in case)
			$blacklist_file = BLACKLIST_DIR . $blacklist_filename;
			
			if (file_exists($blacklist_file))
			{
				unlink($blacklist_file);
			}
			else
			{
				// Complain or something?
			}
		}
		
		function download_file($source_url, $filename)
		{
			// Pray that the filename does not contain invalid characters
			$remote_filename = $source_url . $filename;
			file_put_contents(BLACKLIST_DIR . $filename, file_get_contents($remote_filename));
		}
		
		download_file($source_url, 'index');
		download_file($source_url, 'version');
		
		// Loop through index, downloading new files as needed
		//  (I see nothing wrong in re-using variable names here)
		$new_index_contents = file_get_contents(BLACKLIST_INDEX);
		$blacklists = preg_split("/((\r?\n)|(\r\n?))/", $new_index_contents);
		foreach ($blacklists as $blacklist_filename)
		{
			if (!$blacklist_filename) continue; // Skip empty lines
			download_file($source_url, $blacklist_filename);
		}
	}
	
	
	$current_version = file_get_contents(BLACKLIST_DIR . 'version');
	return $current_version;
	
}

function blacklist_update_available($source_url = BLACKLIST_UPDATE_URL)
{
	// Will only check if the version numbers do not match, not if one is newer than the other.
	$current_version = BLACKLIST_VERSION;
	$remote_version = file_get_contents($source_url . 'version');
	
	return ($current_version != $remote_version);
}

