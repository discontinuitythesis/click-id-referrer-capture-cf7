=== Click ID & Referrer Capture for Contact Form 7 ===
Contributors: firepixel
Tags: gclid, msclkid, utm, contact-form-7, lead-tracking
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
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
* Google Consent Mode: the `ad_user_data` and `ad_personalization` state on the page at the moment the form is sent
* Optional extended attribution: a complete first touch and last touch, each with its own UTM values, referrer, landing page and channel, plus the page the form was sent from

First touch values are written once and never overwritten. Last touch values are refreshed whenever a new value appears, so you can see both the campaign that introduced the visitor and the campaign that brought them back.

= Why offline conversions need this =

Google Ads Smart Bidding learns from conversions. For a lead generation business the conversion that matters is the enquiry that turned into a customer, not the form submission itself. To report that back to Google you need the `gclid` of the click that produced the lead, the conversion name, the conversion time and, ideally, a value. That is exactly what the CSV export in this plugin produces, together with an order ID for each lead and the Ad User Data and Ad Personalization consent columns Google's import accepts. Since 2022, clicks from iOS App campaigns and some Performance Max placements arrive with `gbraid` or `wbraid` instead of `gclid`, so the plugin captures all three, and the export can include GBRAID and WBRAID columns when your import route accepts them.

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
3. Go to Contact, Click ID Capture (the Contact Form 7 menu), and check the settings. The defaults are sensible: always store, a 90 day cookie, a submission log with 90 day retention.
4. Set the default conversion name to match the conversion action you created in Google Ads or Microsoft Advertising exactly, and set a currency.
5. Visit any page of your site with `?gclid=test123` on the end, then open a page that contains a Contact Form 7 form and send a test message. The attribution block should appear in the notification email.

Contact Form 7 is not required for the capture itself. With Contact Form 7 inactive the cookie is still written, and `cidrc_get_values()` and the `[cidrc_debug]` shortcode still work.

== Frequently Asked Questions ==

= How do I pass gclid to my CRM? =

Three ways, and you can use all three at once. First, the plugin adds a hidden field called `cidrc_gclid` to every Contact Form 7 form, so anything that reads posted form data, including Flamingo and most CRM connectors, picks it up with no extra configuration. Second, the `[cidrc_summary]` mail tag puts a readable block into the notification email. Third, set a webhook URL in the settings and every sent form is posted as JSON to your endpoint, which is the neatest route into n8n, Make, Zapier or a custom CRM API.

= Does this work with Consent Mode v2? =

It works alongside it, in two ways. First, when a form is sent the plugin reads the Consent Mode v2 state for `ad_user_data` and `ad_personalization` from the page and stores it with the lead, so that the Google Ads export can pass it on (see "Does the Google Ads export include consent?" below). Second, Consent Mode governs what Google tags do, while this plugin governs a first-party cookie of its own, so set Storage mode to "Wait for consent" and give it your consent banner's cookie name and the text that indicates advertising consent. Until that is present, values stay in the browser session and no cookie is written. The settings page lists the cookie names used by CookieYes, Cookiebot and Complianz. You can also call `window.cidrc.consentGranted()` from your banner's own callback, or dispatch a `cidrc:consent` event on the document.

= Where is the data stored? =

In two places, both of which belong to you. A first-party cookie named `cidrc` on your own domain, with a localStorage copy as a fallback, holds the visitor's values. If the submission log is switched on, one row per sent form is written to a table named `wp_cidrc_submissions` in your own database, with hashed contact details rather than raw ones. No data leaves your site unless you configure a webhook yourself.

= How do I upload the CSV to Google Ads? =

In Google Ads, go to Goals, Conversions, Uploads, then upload the file from the Submissions tab of the plugin. Choose the source that matches your workflow. The file already contains the header row Google expects and a first line reading `Parameters:TimeZone=Europe/London`, or whichever time zone your site uses, so that the conversion times are read correctly. If the importer complains about that first line, delete it and make sure the conversion times carry their own offset, which they do. The conversion name in the file has to match the name of an import-enabled conversion action in your account exactly.

The header follows Google's click conversion import template: `Google Click ID`, `Conversion Name`, `Conversion Time`, `Conversion Value`, `Conversion Currency`, `Order ID`, `Ad User Data` and `Ad Personalization`. The order ID is `cidrc-` followed by the log row number, so each lead has a stable, unique ID that Google can use to spot a duplicate upload. By default only leads with a `gclid` are exported, because Google's file import template documents the Google Click ID column only. If your import route accepts them, switch on "Include GBRAID and WBRAID columns in the Google Ads export" in the settings: the file then gains `GBRAID` and `WBRAID` columns straight after `Google Click ID`, leads that only have a `gbraid` or `wbraid` are included, and exactly one of the three identifiers is filled on each row. Microsoft Advertising exports work the same way through Tools, Conversion tracking, Offline conversions, using the `Microsoft Click ID` column; if your account's template calls the last column `Conversion Currency Code`, rename the header before uploading.

= Does the Google Ads export include consent? =

Yes. The export has an `Ad User Data` column and an `Ad Personalization` column, each holding `Granted`, `Denied` or nothing. The values are read in the visitor's browser at the moment the form is sent, not stored in the cookie, so a visitor who accepts your banner after the page has loaded is recorded correctly. The plugin looks first at the consent state the Google tag keeps for itself, using the update value when there is one and the default value otherwise. If that is not available it looks for `gtag( 'consent', ... )` calls in the `dataLayer`, preferring the most recent update and then the most recent default. If neither source has a value, the "Consent fallback" setting is used. It is blank by default, and you should leave it blank unless your consent banner records this consent for every lead. Rows without a consent value are exported with the cell left blank, which Google reads as unspecified and handles according to its own consent rules.

= What does extended attribution record? =

Extended attribution is off by default. Switch it on under "Extended attribution" in the settings. It keeps two complete touches for each visitor: the first touch and the latest one. A touch is a page view that arrives with a tracked parameter (a UTM value or a click ID) or from another website. Moving between pages of your own site is never a touch, and neither is a later visit with no referrer and no parameters, so the last touch is kept until a new campaign or referral brings the visitor back. When the very first page view is a direct visit, the first touch is recorded as Direct.

Each touch records `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`, the click ID type (`gclid`, `gbraid`, `wbraid`, `msclkid`, `fbclid`, `ttclid`, `li_fat_id` or `dclid`, never the click ID itself), the external referrer, the landing page, the time and a channel. The form also sends the path and title of the page it was submitted from. The values appear in the `[cidrc_summary]` block, in an Attribution column in the Submissions tab, as extra columns at the end of the full log CSV and as an `attribution` object in the webhook payload. The Google Ads and Microsoft Advertising exports do not change.

The channel is worked out in the browser, with the first matching rule winning:

* `gclid`, `gbraid`, `wbraid` or `msclkid`: Paid Search. `dclid`: Display. `fbclid`, `ttclid` or `li_fat_id`: Paid Social.
* A medium of `cpc`, `ppc`, `paid`, `paidsearch` or `sem`: Paid Search.
* A medium of `paidsocial`, or `paid_social`, `social_paid` or `cpm` with a social network as the source: Paid Social.
* A medium containing `display`, `banner` or `programmatic`: Display.
* A medium of `email` or `e-mail`, or a source or medium containing `newsletter`: Email.
* A medium containing `affiliate`: Affiliate.
* A medium of `social`, `social-network` or `sm`: Organic Social.
* A medium of `referral`: Referral.
* Any other UTM values: Other Campaign.
* No UTM values and a referrer from Google, Bing, DuckDuckGo, Yahoo, Ecosia, Yandex, Baidu or Brave Search: Organic Search. From Facebook, Instagram, LinkedIn, X or Twitter, Reddit, YouTube, TikTok or Pinterest: Organic Social. From ChatGPT, Perplexity, Claude, Gemini or Copilot: AI Assistant. From any other website: Referral.
* Nothing at all: Direct.

Extended attribution adds no personal data. It records only campaign values, referrers, page paths, a page title, times and channel names, all of which are stored in the same `cidrc` cookie and log table as the rest of the plugin's data and are covered by the same consent mode, retention period and uninstall routine.

= Does it work with WPForms or Gravity Forms? =

Not yet. At present the plugin fills hidden fields in Contact Form 7 only. The capture layer is form agnostic, so if you add a hidden field to a WPForms or Gravity Forms form you can populate it from `window.cidrc.getValues()` yourself, and `cidrc_get_values()` is available to any PHP code. Support for other form plugins is planned.

= Does it capture gbraid and wbraid? =

Yes, both, and `dclid` as well. Clicks from iOS App campaigns and some Performance Max placements carry `gbraid` or `wbraid` instead of `gclid`. They are always stored in the log and the full log export. The Google Ads CSV export includes `GBRAID` and `WBRAID` columns when you switch on "Include GBRAID and WBRAID columns in the Google Ads export", because Google's file import template documents the Google Click ID column only.

= Is it GDPR compliant? =

The plugin gives you the tools to be compliant, and the rest depends on how you configure it. Click identifiers stored against a visitor are personal data in the UK and EU, so you need a lawful basis, normally consent, and you need to mention the `cidrc` cookie in your cookie notice. Use the wait for consent storage mode with your consent banner. The submission log stores hashes rather than raw contact details, retention is capped by a setting, and uninstalling removes everything. The plugin sends nothing to any third party on its own.

= Can I send submissions to n8n? =

Yes. Put your n8n webhook URL in the settings, optionally set a secret that is sent as the `X-CIDRC-Secret` header so that your workflow can reject anything else, and each sent form is posted as JSON. The payload carries the click identifiers, the campaign parameters, the landing page, the referrer, the form ID and title, the hashed email, the conversion name, value and currency, the `ad_user_data` and `ad_personalization` consent values and the `order_id` used in the Google Ads export. The order ID is blank when the submission log is switched off. With extended attribution on, the payload also has an `attribution` object holding `form_page`, `form_page_title`, `first` and `last`. The raw email address is left out unless you explicitly switch that on.

= How long is the click identifier kept? =

The cookie lifetime is a setting, 90 days by default, and can be anything from 1 to 400 days. Note that Safari caps the lifetime of a cookie set by script at seven days on some configurations, which is a browser limit rather than a plugin limit. Google Ads accepts offline conversion uploads for clicks up to 90 days old by default, so 90 days is a sensible figure.

= Does it slow my site down? =

The script is under twenty two kilobytes of plain JavaScript with no jQuery dependency, no build step and no external request. It runs once per page view and writes a single cookie.

= What is the shortcode for? =

`[cidrc_debug]` prints a table of the current visitor's stored values so that you can confirm the capture is working. By default only administrators see it. Add `public="yes"` if you want it visible to everyone while you are testing, and remove it afterwards.

== Screenshots ==

1. The settings screen, with storage mode, cookie lifetime, consent cookie matching and log retention.
2. The offline conversion settings: default conversion name, value and currency, plus per form overrides.
3. The submissions tab, showing the logged click identifiers, campaigns and landing pages with a date and form filter.
4. The three export buttons and an example Google Ads offline conversion CSV file.
5. The attribution summary block as it appears in a Contact Form 7 notification email.

== Changelog ==

= 1.2.0 =
* New: an "Extended attribution" setting, off by default. When it is on, the plugin keeps a complete first touch and last touch for each visitor, each with every UTM value, the click ID type, the external referrer, the landing page, the time and a channel such as Paid Search, Organic Search, Organic Social, Email, Referral, AI Assistant or Direct. It also records the path and title of the page the form was sent from.
* New: four hidden fields, added only when extended attribution is on: `cidrc_form_page`, `cidrc_form_page_title`, `cidrc_touch_first` and `cidrc_touch_last`.
* New: with extended attribution on, the `[cidrc_summary]` block gains Form page, First touch and Last touch lines, the Submissions tab gains an Attribution column, the full log CSV gains `form_page`, `form_page_title` and a `first_` and `last_` set of touch columns, and the webhook payload gains an `attribution` object.
* New: `window.cidrc.classifyChannel()` returns the channel for a touch.
* Change: the database table gains an `extended` column. The upgrade runs by itself on the first request after the update, and existing rows are kept. With extended attribution off, the hidden fields, emails and exports are the same as in 1.1.0.

= 1.1.0 =
* New: Google Consent Mode capture. When a form is sent, the `ad_user_data` and `ad_personalization` consent state is read from the page, first from the Google tag's own consent state and then from the `dataLayer`, and stored with the lead. Two new hidden fields carry it: `cidrc_ad_user_data` and `cidrc_ad_personalization`.
* New: a "Consent fallback" setting, used only when the page gives no Consent Mode signal. It is blank (unspecified) by default.
* New: the Google Ads export now has `Order ID`, `Ad User Data` and `Ad Personalization` columns and follows the column order of Google's click conversion import template.
* New: an "Include GBRAID and WBRAID columns in the Google Ads export" setting, off by default. When it is off, the Google Ads export contains leads with a `gclid` only.
* New: a Consent column in the Submissions tab, consent values in the `[cidrc_summary]` mail tag, and `ad_user_data`, `ad_personalization` and `order_id` in the full log export and the webhook payload.
* Change: the database table gains two columns. The upgrade runs by itself on the first request after the update, including updates made by uploading a ZIP file, and existing rows are kept with a blank consent value.

= 1.0.1 =
* Change: the settings and submissions screen now sits under the Contact Form 7 Contact menu, next to your forms. It falls back to Settings if Contact Form 7 is not active.
* Fix: the first referrer and first landing page are now recorded on the first page view only, and same-site referrers are ignored. Previously a visit with no referrer could later record the site's own URL as the referrer.

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

= 1.2.0 =
Adds an optional Extended attribution setting that records full first and last touches with a channel for each lead. It is off by default and nothing changes until you switch it on.

= 1.1.0 =
Adds consent capture and the Order ID, Ad User Data and Ad Personalization columns to the Google Ads export. GBRAID and WBRAID columns are now optional and off by default.

= 1.0.1 =
Fixes self-referrals appearing in the Referrer column.

= 1.0.0 =
First release. After activating, visit Contact, Click ID Capture and set your conversion name and currency before exporting anything.

== Developer reference ==

= JavaScript API =

The script exposes `window.cidrc`:

* `window.cidrc.init()` reads the query string, merges it into the stored record, saves it and fills the hidden fields. It runs automatically on DOMContentLoaded.
* `window.cidrc.getValues()` returns the stored record as an object with `first`, `last`, `first_referrer`, `first_landing_page`, `first_seen` and `last_seen`, plus `touches.first` and `touches.last` when extended attribution is on.
* `window.cidrc.classifyChannel( touch )` returns the channel for a touch object with `utm_source`, `utm_medium`, the other UTM keys, `click_id_type` and `referrer`. The referrer host list is the `REFERRER_CHANNELS` array at the top of the script.
* `window.cidrc.refresh()` fills the hidden fields again. It runs automatically on the Contact Form 7 events `wpcf7init`, `wpcf7invalid`, `wpcf7spam`, `wpcf7mailsent` and `wpcf7submit`, so values survive an AJAX reset.
* `window.cidrc.consentGranted()` records consent, writes the cookie and fills the fields. Dispatching a `cidrc:consent` event on `document` does the same thing.
* `window.cidrc.hasConsent()` reports whether storage is currently allowed.
* `window.cidrc.readConsent()` returns the current Google Consent Mode state as `{ ad_user_data, ad_personalization }`, each `granted`, `denied` or an empty string. The two consent hidden fields are filled from it whenever the other fields are filled, and again when a form that contains the plugin's fields is submitted.

= PHP =

* `cidrc_get_values()` returns the same record, read from the cookie, for use in themes and other plugins.
* Filter `cidrc_captured_params` changes the list of query parameters that are captured.
* Filter `cidrc_cookie_days` changes the cookie lifetime.
* Filter `cidrc_should_log` decides, per submission, whether a row is written to the log.
* Filter `cidrc_webhook_payload` changes the JSON sent to the webhook.

= Hidden fields added to Contact Form 7 =

`cidrc_gclid`, `cidrc_gbraid`, `cidrc_wbraid`, `cidrc_msclkid`, `cidrc_fbclid`, `cidrc_ttclid`, `cidrc_utm_source`, `cidrc_utm_medium`, `cidrc_utm_campaign`, `cidrc_utm_term`, `cidrc_utm_content`, `cidrc_referrer`, `cidrc_landing_page`, `cidrc_first_seen`, `cidrc_last_touch`, `cidrc_ad_user_data`, `cidrc_ad_personalization`.

With extended attribution on, also: `cidrc_form_page`, `cidrc_form_page_title`, `cidrc_touch_first` and `cidrc_touch_last`. The two touch fields hold JSON.
