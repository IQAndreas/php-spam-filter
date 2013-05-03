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

define(SPAM_BLACKLISTS_DIR, dirname(__FILE__) . DIRECTORY_SEPARATOR . 'blacklists' . DIRECTORY_SEPARATOR );
define(ALL_BLACKLISTS, SPAM_BLACKLISTS_DIR . 'blacklist-*.txt');

function spam_check_text($text, $blacklist = ALL_BLACKLISTS)
{
	if ($blacklist == ALL_BLACKLISTS)
	{
		$blacklists = glob(ALL_BLACKLISTS);
		foreach ($blacklists as $blacklist_filename)
		{
			$match = regex_match_from_blacklist($text, $blacklist_filename);
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
		$blacklist_absolute = SPAM_BLACKLISTS_DIR . $blacklist;
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
