
Here is a quick example of the use:

```php
require 'spamfilter.php';
$text = "Do you want to purchase some [url=shadywebsite.ca]Canadian viagra[/url] from me?";

// Search in a specific blacklist
$result = spam_check_text($text, 'blacklists/blacklist-trading.txt');
if ($result)
{
	echo "You like talking about economics and trading, right? Go away!";
}

// Search in all available blacklists
$result = spam_check_text($text);
if ($result)
{
	// Result contains the matched word (not the matched regular expression)
	// In our example, $result will contain the value "viagra".
	echo "There is a special place in hell reserved for people who talk about '$result' on my blog!";
}
```

There is an additional function named `spam_check_url()`, but is currently just an alias for `spam_check_text()` until I have it wired up as I want it.

An example use of this library can be seen in [IQAndreas/jekyll-static-comments](https://github.com/IQAndreas/jekyll-static-comments/).

The blacklists are stored as a submodule just to ease in updating the lists via automated scripts. For details on the formatting to use in the blacklists, see [blacklists/README.md](blacklists/README.md).

### License ###

Pre-emptively released under GPLv3, but I may change this in the future to suit the needs of others.


I hate writing documentation.
