# GL-X3000-gpstracker

This is a fork of https://github.com/habeIchVergessen/GL-X3000.

Credits to https://github.com/habeIchVergessen. Mainly it's his work, I only tweaked it for my personell needs.
Due to authorization problems in my setup, the files are installed in /opt/gpstracker instead of on the SD card.

## What does it do?

* Monitor gpsd, gpxlogger
* Serve gpx-tracks on website
* Report GPS position via SMS (poll incoming messages and reply to accepted masters)
* Upload gpx-tracks to remote website

## TODO:

[ ] Documentation about gpsd config

## Install
Requires GPSD enabled, see https://forum.gl-inet.com/t/howto-gl-x3000-gps-configuration-guide/30260
See INSTALL.md

#### check uci settings (/opt/gpstracker/uci)
* uci show gpsd
* compare output off command with file gpsd.uci (gpsd.core.parameters needs to be added)
* restart gpsd
#### reload web server config
* nginx -s reload


### additional tests
* ~/scripts/gpx-parse.php gpxlog.gpx
* web-gui (http://[your router name here]/gps/) should show gpxlog.gpx

## configure cron
* copy line #1 from /opt/gpstracker/cron/crontab
* crontab -e
* add copied line and remove #

## more testing
* /etc/init.d/gpsd restart
* run logread from above
* repeat until cron starts gpxlogger-cron.sh

## sms support
* send "Report GPS-Position" to GL-X3000 (exact typing pls)
* send "Upload GPX-Track" to GL-X3000
* send "Mail GPX-Track" to GL-X3000

### extract the phone number from incoming message
* find /etc/spool/sms/incoming/ -type f -exec grep -iE "^from: " {} \\;
* pick your number and add to /opt/gpstracker/scripts/tools/sms-check.masters
* the message should be answered when cron job runs (use logread from above)

## upload gpx-track's
### prepare the remote website
* configure your server to serve /opt/gpstracker/www/upload/upload.php
* generate ca and user certificates
### configure local service
* edit /opt/gpstracker/www/gps/RemoteConfig.php
### testing
* ~/scripts/gpx-upload.php
### configure cron
* copy line #2 to crontab
## email support (current gpx track)
* configure /etc/ssmtp/revaliases
* if you want to use it via sms you have to attach an email address to your phone number in sms-check.masters
### encrypted emails
* create scripts/certs
* [recipient].cer (by now only one is supported; if present, than the recipient is extract from this file)
* signer.pem and signer_key.pem (with -nodes!)
### testing
* ~/scripts/email.sh [your e-mail addresse]
## upgrade source
* download tar ball
* extract changed files
echo -e ".www/gps/[RL]*Config.php\nscripts/tools/sms-check.masters" | tar xvzf GL-X3000\\ gpx.tgz -X -

## simple WebGUI
![simple WebGUI](Web.png?raw=true "WebGUI")
