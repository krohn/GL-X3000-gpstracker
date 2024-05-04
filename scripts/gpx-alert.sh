#! /bin/bash

set -e

source `dirname $0`/tools/shell.sh
source `dirname $0`/tools/modem.sh

scriptName=`basename $0`
logCmd=/usr/bin/logger
gpsFile="/tmp/gps.last"
gpsSync="/tmp/gps.sync"

writeLog() {
	$logCmd -t $scriptName "$1"
	echo $(date +"%d.%m.%Y %H:%M.%S %Z")": $1" >&2;
}

printf "\nboot:   %s\nuptime: %s\nnow:    %s\n\n" "$(bootTime)" "$(upTime)" "$(currentTime)"

echo "# connected network clients"
# searching connected devices (using static lease for known devices)
ips=$(awk -F "[,]" ' { if (match($0, /^dhcp-host=/) == 0) next; print $2 } ' /var/etc/dnsmasq.conf* | xargs)
nmap -vv -n -sn --version-light --max-parallelism 51 $ips | \
	awk ' /^Nmap /{ip=$NF}/^Host /{st=gensub(/^(\w+).*/, "\\1", "g", $3)}/^MAC /{mac=$3}// { if (ip!="" && st!="" && mac!= "") { print ip" "mac" "st; ip="";st="";mac=""; } }' | \
while read -r ip mac status; do
	cTime=$(currentTime raw)
	lTime=$(awk ' { IGNORECASE=1; if (match($2, "'$mac'") == 0) next; print $1 } ' /tmp/dhcp.leases)
	lMax=$(awk -F"," -v mac="$mac" ' { IGNORECASE=1; if (match($0, "^dhcp-host="mac",") == 0) next;
		n=gensub(/(\d+)\w{0,1}/, "\\1", "", $4); c=gensub(/^\d+(\w{0,1})/, "\\1", "", $4);
		if (c=="d") print n*81400; else if (c=="h") print n*3600; else if (c=="m") print n*60; else print n; } ' /var/etc/dnsmasq.conf*)
	echo "ip: $ip, mac: $mac, status: $status, time: $cTime, lease: $(($lMax - ($lTime - $cTime)))"
done
echo ""

function convCoord() {
	echo $1 | awk ' { 
		dd=gensub(/^(\d{2,3})\d{2}\.\d+\w/, "\\1", "g", $0); 
		mm=gensub(/^\d{2,3}(\d{2}\.\d+)\w/, "\\1", "g", $0); 
		D=gensub(/^\d{2,3}\d{2}\.\d+(\w)/, "\\1", "g", $0); 
		if (match("NS", D) > 0) r=(dd + (mm/60)) * (D == "N" ? 1 : -1);
		if (match("WE", D) > 0) r=(dd + (mm/60)) * (D == "E" ? 1 : -1);
		printf("%.9f", r); } '
}

function convUtc() {
	echo $1 | awk ' {
		h=gensub(/^(\d{2})\d{2}\d{2}/, "\\1", "", $0);
		m=gensub(/^\d{2}(\d{2})\d{2}/, "\\1", "", $0);
		s=gensub(/^\d{2}\d{2}(\d{2})/, "\\1", "", $0);
		printf("%02d:%02d:%02d", h, m, s);
		} '
}

function convSpeed() {
	echo $1 | awk ' {
		printf("%0.3f", ($1 * 1.852));
		} '
}

function convDirection() {
	echo $1 | awk ' {
		printf("%0.3f", $1);
		} '
}

function distCoords() {
	res=$(echo -n "" | awk -v lon1="$1" -v lat1="$2" -v lon2="$3" -v lat2="$4" '
		function deg2rad(deg) { return deg * (3.1415926535/180.0); }
		function rad2deg(rad) { return rad * (180/3.1415926535); }
		function acos(x) { return atan2(sqrt(1-x*x), x); }
		function is_float(x) { return x+0 == x && int(x) != x }
		BEGIN
		{
			if (lon1 == lon2 && lat1 == lat2) { print "0.0"; exit; }
			degrees=rad2deg(acos((sin(deg2rad(lat1)) * sin(deg2rad(lat2))) + (cos(deg2rad(lat1)) * cos(deg2rad(lat2)) * cos(deg2rad(lon1 - lon2)))));
			printf "%0.6f", (is_float(degrees) ? degrees : 0.0);
		} ' 2> /dev/null
	) || res="0.0"
	echo -n "$res"
}

function parseGps() {
	gpsData=$(readGps)
	gps[gpgga]=${gpsData/|*/}
	gps[gprmc]=${gpsData/*|/}
	
	[ -z "${gps[gpgga]}" -o -z "${gps[gprmc]}" ] &&
		gps[valid]="N" && \
		return
		
	read -r lat lon alt sats qual utc hdop ignored << EOF
`echo ${gps[gpgga]}`
EOF
	gps[lat]=$(convCoord $lat)
	gps[lon]=$(convCoord $lon)
	gps[alt]=$alt
	gps[sats]=$sats
	gps[qual]=$qual
	gps[utc]=$(convUtc $utc)
	gps[hdop]=$hdop
	
	read -r lat lon alt sats qual utc hdop speed direction date << EOF
`echo ${gps[gprmc]}`
EOF
	gps[speed]=$(convSpeed $speed)
	gps[direction]=$(convDirection $direction)
	gps[date]=$(date -d "$date" +"%d.%m.%Y")
	gps[mode]=$qual
	dateStr=${date: 4:2}${date: 2:2}${date: 0:2}${utc: 0:4}"."${utc: 4:2}
	gps[local]=$(date -d `date -u -d "$dateStr" +"@%s"` +"%d.%m.%Y %H:%M:%S %Z")
	
	[ gps[qual] == "0" -o gps[mode] == "N" ] && \
		gps[valid]="N" && \
		return
		
	gps[valid]="Y"
	unset gps[gpgga]
	unset gps[gprmc]
	
	# probe time diff to gps
	if [ ! -f $gpsSync ]; then
		timeDiff=$(( `date -u -d "$dateStr" +%s` - `date +%s` ))
		if [ ${timeDiff#-} -gt 300 ]; then
			gpsDateLocal=$(date -d `date -u -d "$dateStr" +"@%s"` +"%Y%m%d%H%M.%S")
			date -s $gpsDateLocal && \
				dateSet="ok" || dateSet="failed"
			writeLog "sync system time with gps: $dateSet"
		fi
		touch $gpsSync
	fi
}

function readGps() {
	echo $(
		gpspipe -x 3 --nmea | \
			awk -F "," ' { if (match($1, /^\$GP(GGA|RMC)/) == 0) next;
				if ($1=="\$GPGGA")            print $1" "$2" "$3$4" "$5$6" "$10" "$7" "$8" "$9" - - -";
				if ($1=="\$GPRMC" && $3=="A") print $1" "$2" "$4$5" "$6$7" - "$13" - - "$8" "($9=="" ? "-" : $9)" "$10;
			} ' | \
		while read -r rec utc lat lon alt qual sats hdop speed degree date; do
			if [[ "$rec" =~ ^\$GPGGA ]]; then
				gpgga="$lat $lon $alt $sats $qual $utc $hdop $speed $degree $date"
			fi
			if [[ "$rec" =~ ^\$GPRMC ]]; then
				gprmc="$lat $lon $alt - $qual $utc - $speed $degree $date"
			fi
			
			[ -n "$gpgga" -a -n "$gprmc" ] && \
				echo -e "$gpgga|$gprmc" && \
				break;
		done
	)
}

function exportGps() {
	[ -z $1 ] && return
	
	# sort keys
	keys="${!gps[@]}" && keys=$(echo -e "${keys// /\\n}" | sort | xargs)
	echo -n "" > $1
	for key in $(echo -e "${keys// /\\n}"); do
		echo $key'|'${gps[$key]} >> $1
	done
}

function importGps() {
	while IFS="|" read -r key value; do
		gpsLast[$key]="$value"
	done < $gpsFile
}

function inetCon() {
	# wan interfaces (state up)
	ifup=`ip link show | awk -F"[: ]" ' { 
		if (match($0, /^\d+: (apcli0|apclix0|eth0|usb0|rmnet_mhi0).* state UP/) == 0) next;
		print $3;
	} ' | xargs`
	# probe wan interfaces
	ip address show | \
		awk -v ifup="${ifup// /|}" ' /^\d+: /{ifa=$2;mac="";ip="";}/link/{mac=$2;ip="";}/inet /{ip=$2}// { if (ifa!="" && mac!="" && ip!="") { if (ifa ~ "^("ifup")") print ifa" "ip" "mac; ifa=""; mac=""; ip=""; } } ' | \
	while read -r int ip mac; do
		if=${int//:/}
		con=`ping -Aqc 2 -W 1 -w 1 8.8.8.8 -I apcli0 | awk ' { if (match($0, /^\d+ packets transmitted, \d+ packets received/) == 0) next; print ($1 == $4 && $7 == "0%" ? "" : "dis")"connected"; } '`
		
		if [ "$con" == "connected" ]; then
			echo -n "$con"
			return
		fi
		inet="$con"
	done
	
	echo -n "$inet"
}

function notify() {
	inet=$(inetCon)

	if ( "$inet" == "connected" ); then
		echo "send email"
	else
		echo "send sms"
	fi
	
	writeLog "$1, inet: $inet"
}
# read and parse gps data
declare -A gps && parseGps

# no gps yet
if [ "${gps[valid]}" != "Y" ]; then
	echo "no valid gps"
	exit
fi

# export gps (initial after boot)
if [ ! -f "$gpsFile" ]; then
	notify "boot: pos ${gps[lat]},${gps[lon]}, sats ${gps[sats]}, hdop ${gps[hdop]}"
	exportGps $gpsFile
	exit
fi

# import last gps data
declare -A gpsLast && importGps
# printf "last:   %s\n\n" "${gpsLast[local]}"

# calc distance
dist=$(distCoords "${gpsLast[lon]}" "${gpsLast[lat]}" "${gps[lon]}" "${gps[lat]}")
echo -e "# metric units (km based)\ndistance $dist, speed ${gps[speed]}, sats ${gps[sats]}, hdop ${gps[hdop]}"

# 1 or more meters moved or 3 or more km/h speed
if (( `awk -v d="$dist" -v s="${gps[speed]}" ' BEGIN { print (d >= 0.001 || s >= 3.000 ? 1 : 0); } '` == 1 )); then
	[ "${gps[speed]}" == "0.000" ] && \
		status="moved" || \
		status="moving"
		
	echo -e "status: $status, distance: $dist, speed: ${gps[speed]}"
	
	# time check if ongoning moving
	[ "${gpsLast[status]}" == "moving" -a "$status" == "moving" ] && \
		age=`awk -v dateStr="${gpsLast[local]}" ' BEGIN { date=gensub(/^(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2})[:\.](\d{2})\s(\w+)/, "\\3 \\2 \\1 \\4 \\5 \\6 \\7", "", dateStr); printf "%d", systime() - mktime(date); } '` && \
		(( $age <= 3600 )) && \
			exit
	
	# 
	notify "$status: dist $dist, speed ${gps[speed]}, pos ${gps[lat]},${gps[lon]}, sats ${gps[sats]}, hdop ${gps[hdop]}"
	gps[status]=$status
	exportGps $gpsFile
else
	[ -n "${gpsLast[status]}" ] && \
		notify "stop: pos ${gps[lat]},${gps[lon]}, sats ${gps[sats]}, hdop ${gps[hdop]}" && \
		exportGps $gpsFile
fi

echo ""