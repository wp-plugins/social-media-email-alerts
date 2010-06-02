=== Social Media E-Mail Alerts ===
Contributors: marios-alexandrou
Tags: alerts, referrers, referrals, social media
Requires at least: 2.9.2
Tested up to: 2.9.2
Stable tag: 1.0.0

Receive e-mail alerts when your site gets traffic from social media sites of your choosing. You can also set up alerts for when certain parameters appear in the URL.

== Description ==

Have you ever noticed that your site was submitted to social media sites, but only days after the submission? Ever wished you had known about the submission so you take measures to increase the visibility of the submission?

By settng up rules that are specific to the traffic patterns of your site, you can be notified of a new social media submission when the initial visitors from that submission come trickling in. For social media sites like Digg.com where your window of opportunity to act is just 24 hours, this early notification can be the difference between thousands of visitors and next to none.

== Installation ==

1. Upload the social-media-email-alerts folder to the '/wp-content/plugins/' directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Set up the rules for triggering an alert on the admin page. Rules can be based on referring domain e.g. Digg.com or by parameters in the querystring e.g. source=twitter.com.

Example 1:

These settings will send an e-mail if your site receives 5 visitor from Digg.com to any one page on your site within a 60 minute window.

Value to Match: digg.com
Min. Visits: 5
Reset (minutes): 60

Example 2:

These settings will send an e-mail if your site recives 10 visits to any one page, within a 2 hour window, and the querystring includes source=twitter.com. In this case, the referring site doesn't matter.

Value to Match: source=twitter.com
Min. Visits: 10
Reset (minutes): 120

== Frequently Asked Questions ==

= Where is data stored? =

A table, wp_social_media_email_alerts, is created (the wp_ prefix may actually vary based on your configuration). This table stores the visits that match the rules you specified. The records in this table are deleted once they no longer match a rule or have expired so the table shouldn't get too large.

= Does this plugin work with caching plugins? =

Yes. Simply specify on the admin page that you are using WP-Cache or WP Super Cache. Should work with other caching plugins too.

= Does this alert system work only for social media sites? =

No. You can specify any referral domain of your choosing. Social media sites are simply what prompted the creation of this plugin.