# GL-X3000-gpstracker

This is a fork of https://github.com/habeIchVergessen/GL-X3000.
Credits to https://github.com/habeIchVergessen. Mostly, it's his work, I only tweaked it for my personell needs.

## What does it do?

* Monitor gpsd, gpxlogger
* Serve gpx-tracks on website
* Report GPS position via SMS (poll incoming messages and reply to accepted masters)
* Upload gpx-tracks to remote website

--- 

## Installation

### Pre-Requirements
* Requires GPSD enabled, see https://forum.gl-inet.com/t/howto-gl-x3000-gps-configuration-guide/30260

### Let's go:
```
mkdir -p /opt
git clone https://github.com/krohn/GL-X3000-gpstracker.git /opt/gpstracker
git checkout beta
ln -s /opt/gpstracker/scripts ~/
rm /etc/php.ini
rm /etc/php8-fpm.d/gps.conf
rm /etc/init.d/gpsd
rm /etc/php8-fpm.conf
rm /etc/nginx/gl-conf.d/service-gps.conf
ln -s /opt/gpstracker/config/etc/php.ini /etc/php.ini
ln -s /opt/gpstracker/config/etc/php8-fpm.d/gps.conf /etc/php8-fpm.d/gps.conf
ln -s /opt/gpstracker/config/etc/init.d/gpsd /etc/init.d/gpsd
ln -s /opt/gpstracker/config/etc/php8-fpm.conf /etc/php8-fpm.conf
ln -s /opt/gpstracker/config/etc/nginx/gl-conf.d/service-gps.conf /etc/nginx/gl-conf.d/service-gps.conf
```

## Check install

### Checking the configs
``` 
~/scripts/config.sh
``` 
**Note**:
* All files must be marked `ok` in Column "cmp" and with `exists` or `exists (link)` in "status"
* Missing packages must be installed via `opkg install [list of packages from output]` or the GL-Frontend. E.g. `coreutils-base64 diffutils socat lsblk nmap gpsd gpsd-clients gpsd-utils php8 php8-cli php8-mod-curl php8-fpm php8-mod-simplexml zoneinfo-core zoneinfo-europe zoneinfo-southamerica`

### Check uci / gpsd data
* Compare output of `uci show gpsd` with `/opt/gpstracker/uci/gpsd.uci` (gpsd.core.parameters needs to be added)
* Restart gpsd with `/etc/init.d/gpsd restart`

### Restart NGINX
```
nginx -s reload
tail /var/log/nginx/error.log
```

---

## First run
* Run `~/scripts/gpxlogger-cron.sh` to start the logging
* Check `logread -e gpx && date` for "starting gpxlogger" (output like this should shown: "05.07.2023 11:29.00 CEST: starting gpxlogger"
* Check http://[YOUR-ROUTER-IP]/gps/ for a gpxlog.gpx (NOTE: GPX file is updated every 60 secs - but only if device moved by >=20 meters)
* Run `~/scripts/gpx-parse.php gpxlog.gpx` for additional test data
* Copy line 1 from `/opt/gpstracker/cron/crontab/` to `crontab -e`

--- 

## SMS support
* Send "Report GPS-Position" to GL-X3000 (having exact typing!)
* Send "Upload GPX-Track" to GL-X3000
* send "Mail GPX-Track" to GL-X3000

### Extract the phone number from incoming message
* Run `find /etc/spool/sms/incoming/ -type f -exec grep -iE "^from: " {} \\;`
* Pick your number and add to `/opt/gpstracker/scripts/tools/sms-check.masters`
* Copy line 2 from `/opt/gpstracker/cron/crontab/` to `crontab -e`
* The message should be answered when cron job runs (use `logread -e gpx && date`)

---

## Upload gpx-tracks

### Prepare the remote website
* configure your server to serve `/opt/gpstracker/www/upload/upload.php`
* generate ca and user certificates

### Configure local service
* edit `/opt/gpstracker/www/gps/RemoteConfig.php`

### Testing
* `~/scripts/gpx-upload.php`

### Configure cron
* Copy line #3 from `/opt/gpxtracker/cron/crontab`to `crontab -e`

--- 

## Email support (current gpx track)
* Configure `/etc/ssmtp/revaliases`
* If you want to use it via sms you have to attach an email address to your phone number in sms-check.masters

### encrypted emails
* create scripts/certs
* `[recipient].cer` (by now only one is supported; if present, than the recipient is extract from this file)
* `signer.pem` and `signer_key.pem` (with -nodes!)
### Testing
* `~/scripts/email.sh [your e-mail addresse]`

--- 

## Upgrade source
```
cd /opt/gpstracker
git pull
/etc/init.d/gpsd restart
/etc/init.d/php8-fpm restart
nginx -s reload
```

--- 

## Simple WebGUI
![simple WebGUI](Web.png?raw=true "WebGUI")

---

## Problems?
* gpsmon delivering a position?
* gpslogger is up and running?
* nginx daemon is up and running?
* php8-fpm is up and running?
* something in the logs?
