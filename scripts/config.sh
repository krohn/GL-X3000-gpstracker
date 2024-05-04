#! /bin/sh

confDir=/opt/gpstracker/config

check () {
	cmp=""
	link=""
	extra=""
	printf "%-50s " "$1" 
	if [ -e $1 ]; then
		cmp=`cmp -s $1 $confDir/$1 && echo ok`
		[ -L $1 ] && link="(link)"
	else
		extra="not "
	fi
	printf "%-6s %s\n" "$cmp" "${extra}exists ${link}"
}

echo -e "\nchecking all files in $confDir\n"

printf "%-50s %-6s %-20s\n" "name" "cmp" "status"
printf "%s %s %s\n" "--------------------------------------------------" "------" "--------------------"
for file in `find $confDir -type f`; do
	cFile=${file//$confDir/}
	check "$cFile"
done
printf "\n"

# checking packages
echo -n "checking required packages: "

packReqCore="bash coreutils-base64 diffutils socat lsblk"
packReqNet="nmap"
packReqGps="gpsd gpsd-clients gpsd-utils"
packReqPhp="php8 php8-cli php8-mod-curl php8-fpm php8-mod-simplexml"
packReqTz="zoneinfo-core zoneinfo-europe zoneinfo-southamerica"
packReq="$packReqCore $packReqNet $packReqGps $packReqPhp $packReqTz"
packsReq=`echo -e "${packReq// /\\\n}" | wc -l`

packInst=`opkg list-installed | awk ' { print $1 } ' | grep -E "(${packReq// /|})" | awk ' { printf "%s%s", (NR > 1 ? " " : ""), $1 } '`
packMiss=`echo -en "${packReq// /\\\n}" | grep -v -E "^(${packInst// /|})$" | awk ' { printf "%s%s", (NR > 1 ? " " : ""), $1 } '`
packsMiss=`echo -e "${packMiss// /\\\n}" | wc -l`

[ -z "$packMiss" ] && echo -e "ok ($packsReq installed)\n" || \
	echo -e "failed\n\t$packsMiss/$packsReq missed: $packMiss\n"
