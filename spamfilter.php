<?php

// spamfilter.php -- Filter through text, searching for spam
// Copyright (C) 2012 Andreas Renberg <iq_andreas@hotmail.com>
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


function spam_check_text($text, $blacklist = 'spam_blacklist_keywords.txt')
{
	return regex_match_from_file($text, $blacklist);
}

function spam_check_url($url, $blacklist = 'spam_blacklist_urls.txt')
{
	// I'm lazy. Just do the same here
	return regex_match_from_file($url, $blacklist);
}

function regex_match_from_file($text, $blacklist)
{
	$keywords = file($blacklist);

	foreach($keywords as $keyword) 
	{
		// Remove comments and whitespace before and after a keyword
		$keyword = preg_replace('/(^\s+|\s+$|#.*^)/i', "", $keyword);
		
		if (!empty($keyword) && preg_match("/$keyword/i", $text))
		{
			// Spam found
			return $keyword;
		}
	}
	
	// No spam found
	return false;
}
