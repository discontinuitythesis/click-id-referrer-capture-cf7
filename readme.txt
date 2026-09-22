=== Click ID & Referrer Capture for Contact Form 7 ===
Contributors: firepixel
Tags: gclid, msclkid, utm, contact-form-7, lead-tracking
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Capture gclid, gbraid, wbraid, msclkid and UTM values, add them to Contact Form 7 submissions and export offline conversion CSV files.

== Description ==

If you run Google Ads or Microsoft Advertising for a business that generates enquiries rather than online sales, the click that produced the enquiry is the piece of data you need, and it is the piece that is usually lost. The visitor arrives with a `gclid` in the address bar, browses for a while, fills in your Contact Form 7 form two days later, and by then the click identifier has gone.

This plugin keeps it. On every page view it reads the advertising parameters from the query string, stores a first touch and a last touch record in a first-party cookie on your own domain, and writes those values into hidden fields on every Contact Form 7 form. When the form is sent, the values arrive in your notification email, in Flamingo, and in an optional submission log that you can export as a CSV file ready for the Google Ads or Microsoft Advertising offline conversion importer.

= What it captures =

* Google Ads: `gclid`, `gbraid`, `wbraid` and `dclid`
* Microsoft Advertising: `msclkid`
* Meta, TikTok and LinkedIn: `fbclid`, `ttclid`, `li_fat_id`
* Campaign parameters: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`
* The first referrer, the first landing page including its query string, the first seen time and the last seen time

First touch values are written once and never overwritten. Last touch values are refreshed whenever a new value appears, so you can see both the campaign that introduced the visitor and the campaign that brought them back.

= Why offline conversions need this =

Google Ads Smart Bidding learns from conversions. For a lead generation business the conversion that matters is the enquiry that turned into a customer, not the form submission itself. To report that back to Google you need the `gclid` of the click that produced the lead, the conversion name, the conversion time and, ideally, a value. That is exactly what the CSV export in this plugin produces. Since 2022, clicks from iOS App campaigns and some Performance Max placements arrive with `gbraid` or `wbraid` instead of `gclid`, so the plugin captures and exports all three.

= Data protection =

* Nothing is sent anywhere. There is no external service, no account, no API key and no phone-home.
* Everything is stored on your own site: a first-party cookie in the visitor's browser, and an optional table in your own database.
* The submission log never stores a raw email address or telephone number. It stores a SHA-256 hash, which is what Google and Microsoft accept for enhanced conversions anyway.
* There is a wait for consent storage mode. In that mode the values are held in the browser session only and the cookie is written after your consent banner reports that advertising storage has been allowed.
* Log retention is a setting in days, and a daily scheduled task deletes anything older.
* Uninstalling the plugin drops the table and deletes the options.

= Free =

The whole plugin is free and GPL licensed. There is no paid tier, no upsell screen and no feature that asks for a subscription.

Built and maintained by [Fire Pixel](https://firepixel.co.uk), a UK Google Ads consultancy.

== Installation ==

1. In your WordPress admin, go to Plugins, Add New, search for "Click ID & Referrer Capture", and install it. You can also upload the ZIP file under Plugins, Add New, Upload Plugin.
2. Activate the plugin.
3. Go to Settings, Click ID Capture, and check the settings. The defaults are sensible: always store, a 90 day cookie, a submission log with 90 day retention.
4. Set the default conversion name to match the conversion action you created in Google Ads or Microsoft Advertising exactly, and set a currency.
5. Visit any page of your site with `?gclid=test123` on the end, then open a page that contains a Contact Form 7 form and send a test message. The attribution block should appear in the notification email.

Contact Form 7 is not required for the capture itself. With Contact Form 7 inactive the cookie is still written, and `cidrc_get_values()` and the `[cidrc_debug]` shortcode still work.

== Frequently Asked Questions ==

= How do I pass gclid to my CRM? =

Three ways, and you can use all three at once. First, the plugin adds a hidden field called `cidrc_gclid` to every Contact Form 7 form, so anything that reads posted form data, including Flamingo and most CRM connectors, picks it up with no extra configuration. Second, the `[cidrc_summary]` mail tag puts a readable block into the notification email. Third, set a webhook URL in the settings and every sent form is posted as JSON to your endpoint, which is the neatest route into n8n, Make, Zapier or a custom CRM API.

= Does this work with Consent Mode v2? =

It works alongside it. Consent Mode v2 governs what Google tags do. This plugin governs a first-party cookie of its own, so set Storage mode to "Wait for consent" and give it your consent banner's cookie name and the text that indicates advertising consent. Until that is present, values stay in the browser session and no cookie is written. The settings page lists the cookie names used by CookieYes, Cookiebot and Complianz. You can also call `window.cidrc.consentGranted()` from your banner's own callback, or dispatch a `cidrc:consent` event on the document.

= Where is the data stored? =

In two places, both of which belong to you. A first-party cookie named `cidrc` on your own domain, with a localStorage copy as a fallback, holds the visitor's values. If the submission log is switched on, one row per sent form is written to a table named `wp_cidrc_submissions` in your own database, with hashed contact details rather than raw ones. No data leaves your site unless you configure a webhook yourself.

= How do I upload the CSV to Google Ads? =

In Google Ads, go to Goals, Conversions, Uploads, then upload the file from the Submissions tab of the plugin. Choose the source that matches your workflow. The file already contains the header row Google expects and a first line reading `Parameters:TimeZone=Europe/London`, or whichever time zone your site uses, so that the conversion times are read correctly. If the importer complains about that first line, delete it and make sure the conversion times carry their own offset, which they do. The conversion name in the file has to match the name of an import-enabled conversion action in your account exactly.

The file carries a `Google Click ID` column, a `GBRAID` column and a `WBRAID` column, because a row is identified by whichever one the click arrived with. Only one of the three is filled per row. If your account's template expects a single identifier column, delete the two columns you do not need before uploading. Microsoft Advertising exports work the same way through Tools, Conversion tracking, Offline conversions, using the `Microsoft Click ID` column; if your account's template calls the last column `Conversion Currency Code`, rename the header before uploading.

= Does it work with WPForms or Gravity Forms? =

Not yet. Version 1.0.0 fills hidden fields in Contact Form 7 only. The capture layer is form agnostic, so if you add a hidden field to a WPForms or Gravity Forms form you can populate it from `window.cidrc.getValues()` yourself, and `cidrc_get_values()` is available to any PHP code. Support for other form plugins is planned.

= Does it capture gbraid and wbraid? =

Yes, both, and `dclid` as well. Clicks from iOS App campaigns and some Performance Max placements carry `gbraid` or `wbraid` instead of `gclid`, and the Google Ads CSV export includes a column for each of the three so that the importer can match whichever one is present.

= Is it GDPR compliant? =

The plugin gives you the tools to be compliant, and the rest depends on how you configure it. Click identifiers stored against a visitor are personal data in the UK and EU, so you need a lawful basis, normally consent, and you need to mention the `cidrc` cookie in your cookie notice. Use the wait for consent storage mode with your consent banner. The submission log stores hashes rather than raw contact details, retention is capped by a setting, and uninstalling removes everything. The plugin sends nothing to any third party on its own.

= Can I send submissions to n8n? =

Yes. Put your n8n webhook URL in the settings, optionally set a secret that is sent as the `X-CIDRC-Secret` header so that your workflow can reject anything else, and each sent form is posted as JSON. The payload carries the click identifiers, the campaign parameters, the landing page, the referrer, the form ID and title, the hashed email and the conversion name, value and currency. The raw email address is left out unless you explicitly switch that on.

= How long is the click identifier kept? =

The cookie lifetime is a setting, 90 days by default, and can be anything from 1 to 400 days. Note that Safari caps the lifetime of a cookie set by script at seven days on some configurations, which is a browser limit rather than a plugin limit. Google Ads accepts offline conversion uploads for clicks up to 90 days old by default, so 90 days is a sensible figure.

= Does it slow my site down? =

The script is under nine kilobytes of plain JavaScript with no jQuery dependency, no build step and no external request. It runs once per page view and writes a single cookie.

= What is the shortcode for? =

`[cidrc_debug]` prints a table of the current visitor's stored values so that you can confirm the capture is working. By default only administrators see it. Add `public="yes"` if you want it visible to everyone while you are testing, and remove it afterwards.

== Screenshots ==

1. The settings screen, with storage mode, cookie lifetime, consent cookie matching and log retention.
2. The offline conversion settings: default conversion name, value and currency, plus per form overrides.
3. The submissions tab, showing the logged click identifiers, campaigns and landing pages with a date and form filter.
4. The three export buttons and an example Google Ads offline conversion CSV file.
5. The attribution summary block as it appears in a Contact Form 7 notification email.

== Changelog ==

= 1.0.0 =
* First release.
* Captures gclid, gbraid, wbraid, dclid, msclkid, fbclid, ttclid, li_fat_id and the six UTM parameters, with first touch and last touch records.
* Stores the referrer, landing page, first seen time and last seen time in a first-party cookie with a localStorage fallback.
* Adds hidden fields to every Contact Form 7 form, provides the `[cidrc_summary]` mail tag and an option to append the summary to every mail body.
* Optional submission log with hashed email and telephone values, a retention setting and a daily purge.
* CSV exports for Google Ads offline conversions, Microsoft Advertising offline conversions and the full log.
* Optional JSON webhook for n8n, Make, Zapier and custom endpoints.
* Wait for consent storage mode, a JavaScript API, the `[cidrc_debug]` shortcode, the `cidrc_get_values()` helper and four filters.

== Upgrade Notice ==

= 1.0.0 =
First release. After activating, visit Settings, Click ID Capture and set your conversion name and currency before exporting anything.

== Developer reference ==

= JavaScript API =

The script exposes `window.cidrc`:

* `window.cidrc.init()` reads the query string, merges it into the stored record, saves it and fills the hidden fields. It runs automatically on DOMContentLoaded.
* `window.cidrc.getValues()` returns the stored record as an object with `first`, `last`, `first_referrer`, `first_landing_page`, `first_seen` and `last_seen`.
* `window.cidrc.refresh()` fills the hidden fields again. It runs automatically on the Contact Form 7 events `wpcf7init`, `wpcf7invalid`, `wpcf7spam`, `wpcf7mailsent` and `wpcf7submit`, so values survive an AJAX reset.
* `window.cidrc.consentGranted()` records consent, writes the cookie and fills the fields. Dispatching a `cidrc:consent` event on `document` does the same thing.
* `window.cidrc.hasConsent()` reports whether storage is currently allowed.

= PHP =

* `cidrc_get_values()` returns the same record, read from the cookie, for use in themes and other plugins.
* Filter `cidrc_captured_params` changes the list of query parameters that are captured.
* Filter `cidrc_cookie_days` changes the cookie lifetime.
* Filter `cidrc_should_log` decides, per submission, whether a row is written to the log.
* Filter `cidrc_webhook_payload` changes the JSON sent to the webhook.

= Hidden fields added to Contact Form 7 =

`cidrc_gclid`, `cidrc_gbraid`, `cidrc_wbraid`, `cidrc_msclkid`, `cidrc_fbclid`, `cidrc_ttclid`, `cidrc_utm_source`, `cidrc_utm_medium`, `cidrc_utm_campaign`, `cidrc_utm_term`, `cidrc_utm_content`, `cidrc_referrer`, `cidrc_landing_page`, `cidrc_first_seen`, `cidrc_last_touch`.
