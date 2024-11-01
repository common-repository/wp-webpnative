=== WP WebPNative ===
Contributors: alexalouit
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=4GJGDY4J4PRXS
Tags: compressing, image, speed, performance, webp, seo
Requires at least: 3.0.1
Tested up to: 5.3.2
Requires PHP: 5.2.4
Stable tag: 5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WebP support for Wordpress

== Description ==

This module provide a reliable webp solution, without configuration.
Supported file formats are JPEG and PNG up to 8 megabytes.

Module look for compatible media images, sends them to an API which is convert them.
They are saved next to the original file.
When a visitor is on your site, "HTML Transformation" function take care to provide the WebP version.
This function can be disabled because some web server takes care of presenting the file themselves.

== Installation ==

The installation is like any other plugin:

Automatic installation:
Install it from Wordpress plugins repository, activate it.

Manual installation:
Unzip files under /wp-content/plugins directory, activate it.

Automatic uninstallation:
Use Wordpress built-in extension manager.

Manual uninstallation:
 - remove plugin directory /wp-content/plugins/wp-webpnative

== Frequently Asked Questions ==

= Does it support non-WebP browser like Safari? =
Yes, this is supported when using the html transformation function.

= Does I need a PHP specific module? =
No, you don't.
The compression is done on several remote servers.

= What is HTML modification? =
This  is the process of modifying the final HTML content of your page,
it will verify that the images are available in WebP format so that the client is WebP compatible.
If these last two cases are valid, the content will be update to present the image in WebP format.

= How use Apache rule =
Disable HTML transformation on module configuration page

*add to .htaccess:*

`<IfModule mod_setenvif.c>
  SetEnvIf Request_URI "\.(jpe?g|png)$" REQUEST_image
</IfModule>

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTP_ACCEPT} image/webp
  RewriteCond %{DOCUMENT_ROOT}/$1.webp -f
  RewriteRule (.+)\.(jpe?g|png)$ $1.webp [T=image/webp]
</IfModule>

<IfModule mod_headers.c>
  Header append Vary Accept env=REQUEST_image
</IfModule>

<IfModule mod_mime.c>
  AddType image/webp .webp
</IfModule>`

= How use Nginx rule =
Disable HTML transformation on module configuration page

*Add to /etc/nginx/conf.d/webp.conf:*

`map $http_accept $webp_suffix {
  default "";
  "~*webp" ".webp";
}`

*Add to your vhost file:*

`location ~ \.(png|jpe?g)$ {
  add_header Vary "Accept-Encoding";
  try_files $uri$webp_suffix $uri =404;
}`

== Screenshots ==

1. Exemple
2. General configure

== Changelog ==

= 1.0 =
* Initial version

== Upgrade Notice ==

= 1.0 =
* Initial version