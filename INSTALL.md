# Install files

## Copy files to /opt/gpstracker
```
mkdir -p /opt/gpstracker/gps
cp -R ./www /opt/gpstracker/
cp -R ./config /opt/gpstracker/
cp -R ./scripts /opt/gpstracker/
ln -s /opt/gpstracker/scripts ~/
```

## Link the config files
```
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

## Check base install
``` 
./scripts/config.sh
``` 
Note: 
* All files must be marked `ok` in Column "cmp" and with `exists` or `exists (link)` in "status"
* Missing packages must be installed via `opkg install [list of packages from output]` or the GL-Frontend. E.g. `coreutils-base64 diffutils socat lsblk nmap gpsd gpsd-clients gpsd-utils php8 php8-cli php8-mod-curl php8-fpm php8-mod-simplexml zoneinfo-core zoneinfo-europe zoneinfo-southamerica`

## Configure gpsd
* Compare output of `uci show gpsd` with `/opt/uci/gpsd.uci` (gpsd.core.parameters needs to be added)
* Restart gpsd with `/etc/init.d/gpsd restart` 

## Restart NGINX
* Reload with `nginx -s reload`
* Check logs `cat /var/log/nginx/error.log``

# First run

* Run `/opt/gpstracker/scripts/gpxlogger-cron.sh` to start the logging
* Check `logread -e gpx && date` for "starting gpxlogger" (output like this should shown: "05.07.2023 11:29.00 CEST: starting gpxlogger"