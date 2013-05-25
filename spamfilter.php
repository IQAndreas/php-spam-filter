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

class SpamFilter
{
	/* $blacklists can be one of the following options
	 *  null: uses the default blacklist folder
	 *	a string: a path to a custom blacklist folder
	 *	an array of strings: Each string should point to a blacklist file
	 * 
	 * $blacklist_update_url can either be a url, or a local path. (the latter is yet untested)
	 */
	public function __construct($blacklists = null, $blacklist_update_url = null)
	{
		if (is_array($blacklists))
		{
			$this->blacklist_directory = null;
			$this->blacklists = $blacklists;
		}
		elseif ($blacklists === null)
		{
			$blacklists = SpamFilter::default_blacklist_directory();
			$this->blacklist_directory = $blacklists;
			$this->blacklists = $this->get_blacklists_from_directory($blacklists);
		}
		elseif (is_string($blacklists))
		{
			$this->blacklist_directory = $blacklists;
			$this->blacklists = $this->get_blacklists_from_directory($blacklists);
		}
		else
		{
			// Is this the proper way to throw errors in PHP?
			trigger_error("[SpamFilter::__construct()] Error: Invalid value for parameter \$blacklist.");
			
			$this->blacklist_directory = null;
			$this->blacklists = array();
		}
		
		if ($blacklist_update_url === null)
		{
			$this->blacklist_update_url = SpamFilter::default_blacklist_update_url();
		}
		else
		{
			$this->blacklist_update_url = $blacklist_update_url;
		}
	}
	
	private function get_blacklists_from_directory($blacklist_directory)
	{
		$blacklist_index = $blacklist_directory . DIRECTORY_SEPARATOR . 'index';
		if (!file_exists($blacklist_index))
		{
			// Is this the proper way to throw errors in PHP?
			trigger_error("[SpamFilter::__construct()] Error: Cannot find blacklist index in `$blacklist_directory`.");
			return array();
		}
		else
		{
			$index = $blacklist_directory . DIRECTORY_SEPARATOR . 'index';
			return $this->get_list_from_file($index);
		}
	}
	
	private function get_list_from_file($file_path)
	{
		$file_contents = file_get_contents($file_path);
		return preg_split("/((\r?\n)|(\r\n?))/", $file_contents, NULL, PREG_SPLIT_NO_EMPTY);	
	}
	
	public static function default_blacklist_directory()
	{
		return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'blacklists'; // absolute path
	}
	public static function default_blacklist_update_url()
	{
		return "https://raw.github.com/IQAndreas/php-spam-filter/blacklists/";
	}
		
	private $blacklist_directory;
	private $blacklist_update_url;
	private $blacklists;

	public function check_text($text)
	{
		foreach ($this->blacklists as $blacklist_filename)
		{
			$match = $this->regex_match_from_blacklist($text, $blacklist_filename);
			if ($match) return $match;
		}
	}

	public function check_url($url)
	{
		// TODO! Just treat the url as plain text for now.
		return $this->check_text($url, $blacklist);
	}

	private function regex_match_from_blacklist($text, $blacklist)
	{
		if (!file_exists($blacklist))
		{
			$path = $this->blacklist_directory;
			if ($path === null) $path = SpamFilter::default_blacklist_directory();
			
			// Check to see if they supplied a relative path instead of an absolute one.
			$blacklist_absolute = $path . DIRECTORY_SEPARATOR . $blacklist;
			if (file_exists($blacklist_absolute))
			{
				$blacklist = $blacklist_absolute;
			}
			else
			{
				// Is this the proper way to throw errors in PHP?
				trigger_error("[SpamFilter::regex_match_from_blacklist()] Error: Cannot find blacklist with name `$blacklist_absolute`.");
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
				trigger_error("[SpamFilter::regex_match_from_blacklist()] Error: Invalid regular expression in `$blacklist` line $current_line.");
				continue;	
			}
		}
	
		// No spam found
		return false;
	}
	
	
	// returns `null` if not currently using a valid blacklist directory
	public function version()
	{
		$blacklist_version_file = $this->blacklist_directory . DIRECTORY_SEPARATOR . 'version';
		if (file_exists($blacklist_version_file))
		{
			return trim(file_get_contents($blacklist_version_file));
		}
		else
		{
			return null;
		}
	}
	
	// Returns `true` if an update exists, `false` if using the same version as on the server,
	//  and `null` if not currently using a valid blacklist directory
	public function blacklist_update_available()
	{
		// Will only check if the version numbers do not match, not if one is newer than the other.
		$current_version = $this->version();
		if ($current_version === null) return null;
		
		$remote_version = trim(file_get_contents($this->blacklist_update_url . 'version'));
	
		return ($current_version != $remote_version);
	}

	// WARNING: This will overwrite any of the old files!
	// I'm not sure if I should also delete any old blacklists too, or if I should leave them.
	// Returns the current blacklist version as a string, or `null` if unable to update.
	public function update_blacklists($force = false)
	{
		// If there is a better way to download stuff from another server, please let me know.
		// I'm using this method because there is a limit to how large the files can be, which is good,
		// since the blacklists should all be rather small. I really should put some more type of protection in.
		
		if ($this->blacklist_directory === null) return null;
		
		if ($force || ($this->blacklist_update_available() === true))
		{
			if (!$this->try_blacklist_update())
			{
				if ($this->blacklist_directory_dirty)
				{
					trigger_error("[SpamFilter::update_blacklists()] Error: Failure during the middle of an update, which means some of the blacklists may be incomplete. Please fix the problems and re-update the blacklists.");
					$blacklist_version_file = $this->blacklist_directory . DIRECTORY_SEPARATOR . 'version';
					$version_error_message = "[Previous update failed. Please re-update the blacklists.]";
					file_put_contents($blacklist_version_file, $version_error_message);
				}
				else
				{
					trigger_error("[SpamFilter::update_blacklists()] Error: Update failed. Reverting to previous blacklist version.");
				}
				
				return null;
			}
		}
		
		// Returns the NEW version
		return $this->version();
	}
	
	private $blacklist_directory_dirty = false;
	private function try_blacklist_update()
	{
		// Store a reference to the old index of blacklists, as the new index is about to change
		$old_blacklists = $this->get_blacklists_from_directory($this->blacklist_directory);
		
		// Download the new index (is also a way to test if the script is able to connect to the download server)
		if (!$this->download_blacklist_file('index')) return false;
		
		// Delete old blacklist files
		foreach ($old_blacklists as $blacklist_filename)
		{
			if (!$this->delete_blacklist_file($blacklist_filename))
			{
				// Just ignore old blacklist files if you are unable to delete them.
				// They may get replaced by new files anyway.
				trigger_error("[SpamFilter::update_blacklists()] Warning: Unable to remove old blacklist file `$blacklist_filename`. Ignoring and continuing with the update.");
				//return false;
			}
		}
		
		// Loop through index, downloading new blacklist files
		$new_blacklists = $this->get_blacklists_from_directory($this->blacklist_directory);
		foreach ($new_blacklists as $blacklist_filename)
		{
			if (!$this->download_blacklist_file($blacklist_filename)) return false;
		}
		
		// Finally, download the new version file (will automatically update the current version number)
		if (!$this->download_blacklist_file('version')) return false;
		
		$this->blacklist_directory_dirty = false;
		return true;
	}
	
	private function delete_blacklist_file($blacklist_filename)
	{
		// Prevent injection which would try to place downloaded files in a different directory
		// XXX: If this option is used, you may NOT use absolute paths for the blacklist files!!
		//		However, that's not much of an issue, as the remote blacklist index will always use relative paths.
		$blacklist_filename = basename($blacklist_filename); 
		$blacklist_file = $this->blacklist_directory . DIRECTORY_SEPARATOR . $blacklist_filename;
		
		if (file_exists($blacklist_file))
		{
			$result = unlink($blacklist_file);
			if ($result)
			{
				$this->blacklist_directory_dirty = true;
				return true;
			}
			else
			{
				// Cannot delete old file. Complain or something?
				return false;
			}
		}
		else
		{
			// Cannot find file. Complain or something?
			return false;
		}
	}
	
	private function download_blacklist_file($blacklist_filename)
	{
		// Prevent injection which would try to place downloaded files in a different directory
		//	(see comment in `delete_blacklist_file` for details)
		$blacklist_filename = basename($blacklist_filename);
		
		$local_filename = $this->blacklist_directory . DIRECTORY_SEPARATOR . $blacklist_filename;
		$remote_filename = $this->blacklist_update_url . $blacklist_filename;
		
		$contents = file_get_contents($remote_filename);
		if ($contents === false) return false;
		
		$result = file_put_contents($local_filename, $contents);
		if ($result === false) return false;
		
		$this->blacklist_directory_dirty = true;
		return true;
	}

}


