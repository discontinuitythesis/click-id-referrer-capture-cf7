# Click ID & Referrer Capture for Contact Form 7

Captures `gclid`, `gbraid`, `wbraid`, `dclid`, `msclkid`, `fbclid`, `ttclid`, `li_fat_id` and the UTM parameters, keeps a first touch and a last touch record in a first-party cookie, writes them into hidden fields on every Contact Form 7 form, and exports Google Ads and Microsoft Advertising offline conversion CSV files.

Free, GPLv2 or later, no external services and no account required.

Built and maintained by [Fire Pixel](https://firepixel.co.uk), a UK Google Ads consultancy.

## Documentation

The full documentation, the FAQ and the developer reference live in [`readme.txt`](readme.txt), which is the WordPress.org readme for this plugin. Plugin page: <https://firepixel.co.uk/wordpress-click-id-capture>.

## Requirements

* WordPress 6.2 or later
* PHP 7.4 or later
* Contact Form 7 for the form integration. The capture layer works without it.

## Development

```
php test/check-standards.php        # escaping, sanitising, prepared statements, text domain
node --test test/capture.test.js    # front-end capture logic in a minimal fake DOM
for f in $(find . -name '*.php'); do php -l "$f"; done
```

## Licence

GPLv2 or later. See <https://www.gnu.org/licenses/gpl-2.0.html>.
