shell=$(ps w | grep -E "^\s{0,}$$" | basename `awk ' { print (substr($5, 1, 1) == "{" ? $6 : $5) } '` 2> /dev/null)

function currentTime() {
	[ $# -ge 1 -a "$1" == "raw" ] && \
		echo $(date +"%s") || \
		echo $(date +"%d.%m.%Y %H:%M.%S")
}

function bootTime() {
	bootTimeInSecs=$(expr `date +%s` - `cat /proc/uptime | awk -F "." ' { print $1 } '`)
	
	[ $# -ge 1 -a "$1" == "raw" ] && \
		echo $bootTimeInSecs || \
		echo $(date +"%d.%m.%Y %H:%M.%S" -d "@$bootTimeInSecs")
}

function upTime() {
	upTimeSecs=$(awk ' { print int($1) } ' /proc/uptime)
	
	[ $# -ge 1 -a "$1" == "raw" ] && \
		echo $upTimeSecs && \
		return
		
	IFS=": " read -r utD utH utM utS << EOF
`echo $upTimeSecs | awk ' { print int($1/86400)" "int(($1%86400)/3600)":"int((($1%86400)%3600)/60)":"int($1%60)} '`
EOF

	[ $utD > 0 ] && \
		uptime=$(printf '%dd ' $utD) && \
		uptime=$(printf "%s%02d:%02d.%02d\n" "$uptime" $utH $utM $utS)
		
	echo $uptime
}
