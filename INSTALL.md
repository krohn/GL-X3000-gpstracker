# Install files

## Copy files to /opt/gpstracker
```
mkdir -p /opt
mkdir -p /opt/gpstracker
mkdir -p /opt/gpstracker/gps
cp -R ./www /opt/gpstracker/www
cp -R ./config /opt/gpstracker/config
cp -R ./scripts /opt/gpstracker/scripts
ln -s /opt/gpstracker/scripts ~/
```

## Link the config files
```
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
* nginx -s reload

# First run
